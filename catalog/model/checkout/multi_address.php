<?php
namespace Opencart\Catalog\Model\Extension\RaaiMultiAddress\Checkout;

require_once(DIR_EXTENSION . 'raai_multi_address/system/library/raai_multi_address/allocation.php');

use Opencart\System\Extension\RaaiMultiAddress\Library\RaaiMultiAddress\Allocation;

class MultiAddress extends \Opencart\System\Engine\Model {
	public function getSettings(): array {
		return [
			'status'                        => (bool)$this->config->get('module_raai_multi_address_status'),
			'max_addresses_per_order'       => (int)($this->config->get('module_raai_multi_address_max_addresses_per_order') ?: 10),
			'max_addresses_per_product'     => (int)($this->config->get('module_raai_multi_address_max_addresses_per_product') ?: 10),
			'allow_quantity_splitting'      => (bool)$this->config->get('module_raai_multi_address_allow_quantity_splitting'),
			'allow_checkout_address_create' => (bool)$this->config->get('module_raai_multi_address_allow_checkout_address_create'),
			'save_checkout_addresses'       => (bool)$this->config->get('module_raai_multi_address_save_checkout_addresses'),
			'default_checkout_mode'         => (string)($this->config->get('module_raai_multi_address_default_checkout_mode') ?: 'single'),
			'pricing_mode'                  => (string)($this->config->get('module_raai_multi_address_pricing_mode') ?: 'multiply_by_address_count'),
			'fixed_fee_per_address'         => (float)$this->config->get('module_raai_multi_address_fixed_fee_per_address'),
			'additional_address_surcharge'  => (float)$this->config->get('module_raai_multi_address_additional_address_surcharge'),
			'separate_shipping_method'      => (bool)$this->config->get('module_raai_multi_address_separate_shipping_method'),
			'default_shipping_method'       => (string)$this->config->get('module_raai_multi_address_default_shipping_method'),
			'requote_on_change'             => (bool)$this->config->get('module_raai_multi_address_requote_on_change'),
			'free_shipping_behaviour'       => (string)($this->config->get('module_raai_multi_address_free_shipping_behaviour') ?: 'preserve_free'),
			'initial_status_id'             => (int)($this->config->get('module_raai_multi_address_initial_status_id') ?: 1),
			'reference_format'              => (string)($this->config->get('module_raai_multi_address_reference_format') ?: 'MAS-{order_id}-{sequence}')
		];
	}

	public function isAvailable(): bool {
		return (bool)$this->config->get('module_raai_multi_address_status') && $this->customer->isLogged() && $this->cart->hasShipping();
	}

	public function getCartLines(): array {
		$this->load->model('checkout/cart');

		return Allocation::buildCartLines($this->model_checkout_cart->getProducts());
	}

	public function getPreparedData(): array {
		$cart_lines = $this->getCartLines();
		$addresses = $this->getAddresses();
		$session = $this->ensureDraft($cart_lines, $addresses);
		$validation = $this->validateSession($cart_lines, $session);

		return [
			'available'   => $this->isAvailable(),
			'enabled'     => !empty($session['enabled']),
			'cart_lines'  => $cart_lines,
			'addresses'   => $addresses,
			'groups'      => $session['groups'] ?? [],
			'validation'  => $validation,
			'settings'    => $this->getSettings(),
			'signature'   => Allocation::cartSignature($cart_lines)
		];
	}

	public function saveAllocation(array $post): array {
		$cart_lines = $this->getCartLines();
		$addresses = $this->getAddresses();
		$session = $this->ensureDraft($cart_lines, $addresses);

		$enabled = !empty($post['multi_address_enabled']);

		if (!$enabled) {
			$session['enabled'] = false;
			$session['groups'] = [];
			$this->session->data['raai_multi_address'] = $session;
			$this->restoreBaseShipping();

			return ['valid' => true, 'errors' => [], 'enabled' => false];
		}

		$groups = Allocation::normalizeGroups((array)($post['group'] ?? []), $cart_lines);

		$session['enabled'] = true;
		$session['cart_signature'] = Allocation::cartSignature($cart_lines);
		$session['groups'] = $groups;
		$this->session->data['raai_multi_address'] = $session;

		$validation = $this->validateSession($cart_lines, $session);

		if ($validation['valid']) {
			$this->applyCombinedShipping();
		}

		return [
			'valid'      => $validation['valid'],
			'errors'     => $validation['errors'],
			'enabled'    => true,
			'groups'     => $groups,
			'signature'  => $session['cart_signature'],
			'shipping'   => $this->session->data['raai_multi_address']['shipping'] ?? []
		];
	}

	public function beforeConfirm(): void {
		if (!$this->isActive()) {
			return;
		}

		$validation = $this->validateCurrent();

		if (!$validation['valid']) {
			$this->session->data['raai_multi_address_error'] = $validation['errors'];
			unset($this->session->data['shipping_method']);
			unset($this->session->data['payment_method']);

			return;
		}

		unset($this->session->data['raai_multi_address_error']);
		$this->applyCombinedShipping();
	}

	public function validateCurrent(): array {
		$cart_lines = $this->getCartLines();
		$session = $this->session->data['raai_multi_address'] ?? [];

		return $this->validateSession($cart_lines, $session);
	}

	public function applyCombinedShipping(): void {
		if (!$this->isActive() || empty($this->session->data['shipping_method'])) {
			return;
		}

		$validation = $this->validateCurrent();

		if (!$validation['valid']) {
			return;
		}

		$session = $this->session->data['raai_multi_address'];
		$settings = $this->getSettings();
		$base_method = $this->getBaseShippingMethod();
		$address_count = $this->getChargeableAddressCount($session['groups'] ?? []);
		$base_cost = (float)($base_method['cost'] ?? 0);

		if ($base_cost <= 0 && $settings['free_shipping_behaviour'] === 'preserve_free') {
			$combined_cost = 0.0;
		} else {
			$combined_cost = Allocation::calculateShipping($base_cost, $address_count, $settings);
		}

		$method = $base_method;
		$method['name'] = 'Multi-address shipping (' . ($base_method['name'] ?? $base_method['code'] ?? 'shipping') . ')';
		$method['cost'] = $combined_cost;
		$method['_raai_multi_address_adjusted'] = true;
		$method['_raai_multi_address_base_cost'] = $base_cost;

		if (isset($method['tax_class_id'])) {
			$method['text'] = $this->currency->format($this->tax->calculate($combined_cost, $method['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
		} else {
			$method['text'] = $this->currency->format($combined_cost, $this->session->data['currency']);
		}

		$this->session->data['shipping_method'] = $method;
		$session['shipping'] = [
			'base_cost'       => $base_cost,
			'combined_cost'   => $combined_cost,
			'address_count'   => $address_count,
			'pricing_mode'    => $settings['pricing_mode'],
			'base_method'     => $base_method
		];
		$this->session->data['raai_multi_address'] = $session;
	}

	public function persistOrder(int $order_id, array $order_data): void {
		if (!$order_id || !$this->isActive()) {
			return;
		}

		$cart_lines = $this->getCartLines();
		$session = $this->session->data['raai_multi_address'] ?? [];
		$validation = $this->validateSession($cart_lines, $session);

		if (!$validation['valid']) {
			$this->log->write('Multi-Address Shipping: order ' . $order_id . ' was not persisted because allocation is invalid: ' . implode('; ', $validation['errors']));
			return;
		}

		$existing_query = $this->db->query("SELECT `multi_address_order_id` FROM `" . DB_PREFIX . "raai_multi_address_order` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");

		if ($existing_query->num_rows) {
			return;
		}

		$settings = $this->getSettings();
		$shipping = $session['shipping'] ?? [];
		$base_cost = (float)($shipping['base_cost'] ?? ($this->session->data['shipping_method']['_raai_multi_address_base_cost'] ?? $this->session->data['shipping_method']['cost'] ?? 0));
		$shipment_costs = Allocation::calculateShipmentCosts($base_cost, $session['groups'], $settings);
		$order_products = $this->getOrderProductsWithSignatures($order_id);
		$product_map = Allocation::mapOrderProductsToCartLines($cart_lines, $order_products);
		$customer_id = (int)($order_data['customer_id'] ?? $this->customer->getId());

		if (count($product_map) < count(array_filter($cart_lines, static function (array $line): bool {
			return !empty($line['shipping']);
		}))) {
			$this->log->write('Multi-Address Shipping: order ' . $order_id . ' was not persisted because order products could not be mapped to cart lines.');
			return;
		}

		try {
			$this->db->query('START TRANSACTION');

			$this->db->query("INSERT INTO `" . DB_PREFIX . "raai_multi_address_order` SET `order_id` = '" . (int)$order_id . "', `customer_id` = '" . $customer_id . "', `address_count` = '" . (int)$this->getChargeableAddressCount($session['groups']) . "', `shipping_total` = '" . (float)($shipping['combined_cost'] ?? $this->session->data['shipping_method']['cost'] ?? 0) . "', `pricing_mode` = '" . $this->db->escape($settings['pricing_mode']) . "', `status` = 'active', `date_added` = NOW(), `date_modified` = NOW()");

			$multi_address_order_id = (int)$this->db->getLastId();
			$sequence = 0;

			foreach ($session['groups'] as $group_key => $group) {
				if (empty($group['products'])) {
					continue;
				}

				$sequence++;
				$address = $this->getAddress($customer_id, (int)$group['address_id']);

				if (!$address) {
					throw new \Exception('Invalid shipment address ' . (int)$group['address_id']);
				}

				$shipment_reference = $this->formatShipmentReference($settings['reference_format'], $order_id, $sequence);
				$shipping_method = $this->session->data['shipping_method'] ?? [];
				$shipping_cost = (float)($shipment_costs[$group_key] ?? 0);

				$this->db->query("INSERT INTO `" . DB_PREFIX . "raai_multi_address_shipment` SET `multi_address_order_id` = '" . $multi_address_order_id . "', `order_id` = '" . (int)$order_id . "', `customer_id` = '" . $customer_id . "', `customer_address_id` = '" . (int)$group['address_id'] . "', `shipment_reference` = '" . $this->db->escape($shipment_reference) . "', `shipping_method_code` = '" . $this->db->escape((string)($shipping_method['code'] ?? '')) . "', `shipping_method_name` = '" . $this->db->escape((string)($shipping_method['name'] ?? '')) . "', `shipping_cost` = '" . $shipping_cost . "', `tax` = '0', `weight` = '0', `shipment_status_id` = '" . (int)$settings['initial_status_id'] . "', `tracking_number` = '', `tracking_url` = '', `comment` = '" . $this->db->escape((string)$group['delivery_instruction']) . "', `date_added` = NOW(), `date_modified` = NOW()");

				$shipment_id = (int)$this->db->getLastId();

				$this->insertAddressSnapshot($shipment_id, $order_id, $address, (string)($order_data['telephone'] ?? ''), (string)$group['delivery_instruction']);

				foreach ($group['products'] as $cart_key => $quantity) {
					$order_product = $product_map[$cart_key];

					$this->db->query("INSERT INTO `" . DB_PREFIX . "raai_multi_address_shipment_product` SET `shipment_id` = '" . $shipment_id . "', `order_id` = '" . (int)$order_id . "', `order_product_id` = '" . (int)$order_product['order_product_id'] . "', `product_id` = '" . (int)$order_product['product_id'] . "', `cart_key` = '" . $this->db->escape((string)$cart_key) . "', `quantity` = '" . (int)$quantity . "', `quantity_shipped` = '0', `quantity_returned` = '0'");
				}

				$this->db->query("INSERT INTO `" . DB_PREFIX . "raai_multi_address_shipment_history` SET `shipment_id` = '" . $shipment_id . "', `shipment_status_id` = '" . (int)$settings['initial_status_id'] . "', `comment` = 'Shipment created', `notify` = '0', `date_added` = NOW()");
			}

			$this->db->query('COMMIT');
		} catch (\Throwable $exception) {
			$this->db->query('ROLLBACK');
			$this->log->write('Multi-Address Shipping: failed to persist order ' . $order_id . ': ' . $exception->getMessage());
		}
	}

	public function getOrderShipments(int $order_id): array {
		$shipments = [];
		$shipment_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "raai_multi_address_shipment` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `shipment_id` ASC");

		foreach ($shipment_query->rows as $shipment) {
			$shipment_id = (int)$shipment['shipment_id'];
			$address_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "raai_multi_address_shipment_address` WHERE `shipment_id` = '" . $shipment_id . "' LIMIT 1");
			$product_query = $this->db->query("SELECT sp.*, op.`name`, op.`model` FROM `" . DB_PREFIX . "raai_multi_address_shipment_product` sp LEFT JOIN `" . DB_PREFIX . "order_product` op ON (op.`order_product_id` = sp.`order_product_id`) WHERE sp.`shipment_id` = '" . $shipment_id . "' ORDER BY sp.`shipment_product_id` ASC");
			$status_query = $this->db->query("SELECT `name` FROM `" . DB_PREFIX . "raai_multi_address_shipment_status` WHERE `shipment_status_id` = '" . (int)$shipment['shipment_status_id'] . "' AND `language_id` = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");

			$shipment['address'] = $address_query->row;
			$shipment['products'] = $product_query->rows;
			$shipment['status_name'] = $status_query->row['name'] ?? '';
			$shipments[] = $shipment;
		}

		return $shipments;
	}

	protected function ensureDraft(array $cart_lines, array $addresses): array {
		$session = $this->session->data['raai_multi_address'] ?? [];
		$signature = Allocation::cartSignature($cart_lines);

		if (!isset($session['cart_signature'])) {
			$session['cart_signature'] = $signature;
			$session['groups'] = $this->defaultGroups($cart_lines, $addresses);
			$session['enabled'] = ($this->getSettings()['default_checkout_mode'] === 'multi');
		} elseif ($session['cart_signature'] !== $signature && empty($session['enabled'])) {
			$session['cart_signature'] = $signature;
			$session['groups'] = $this->defaultGroups($cart_lines, $addresses);
			$session['signature_mismatch'] = false;
		} elseif ($session['cart_signature'] !== $signature) {
			$session['signature_mismatch'] = true;
		} else {
			$session['signature_mismatch'] = false;
		}

		if (!isset($session['groups'])) {
			$session['groups'] = $this->defaultGroups($cart_lines, $addresses);
		}

		$this->session->data['raai_multi_address'] = $session;

		return $session;
	}

	protected function defaultGroups(array $cart_lines, array $addresses): array {
		$address_id = 0;

		if (isset($this->session->data['shipping_address']['address_id'])) {
			$address_id = (int)$this->session->data['shipping_address']['address_id'];
		} elseif ($addresses) {
			$first = reset($addresses);
			$address_id = (int)$first['address_id'];
		}

		$products = [];

		foreach ($cart_lines as $cart_key => $line) {
			if (!empty($line['shipping'])) {
				$products[$cart_key] = (int)$line['quantity'];
			}
		}

		$group_key = $this->newGroupKey();

		return [
			$group_key => [
				'group_key'            => $group_key,
				'address_id'           => $address_id,
				'delivery_instruction' => '',
				'shipping_method_code' => '',
				'products'             => $products
			]
		];
	}

	protected function validateSession(array $cart_lines, array $session): array {
		if (empty($session['enabled'])) {
			return ['valid' => true, 'errors' => [], 'allocated' => []];
		}

		if (($session['cart_signature'] ?? '') !== Allocation::cartSignature($cart_lines)) {
			return [
				'valid'     => false,
				'errors'    => ['The cart changed after the multi-address allocation was saved. Please review destinations again.'],
				'allocated' => []
			];
		}

		return Allocation::validate($cart_lines, $session['groups'] ?? [], array_column($this->getAddresses(), 'address_id'), $this->getSettings());
	}

	protected function getAddresses(): array {
		if (!$this->customer->isLogged()) {
			return [];
		}

		$this->load->model('account/address');

		return $this->model_account_address->getAddresses($this->customer->getId());
	}

	protected function getAddress(int $customer_id, int $address_id): array {
		$this->load->model('account/address');

		return $this->model_account_address->getAddress($customer_id, $address_id);
	}

	protected function isActive(): bool {
		return $this->isAvailable() && !empty($this->session->data['raai_multi_address']['enabled']);
	}

	protected function getChargeableAddressCount(array $groups): int {
		$count = 0;

		foreach ($groups as $group) {
			if (!empty($group['products'])) {
				$count++;
			}
		}

		return $count;
	}

	protected function getBaseShippingMethod(): array {
		$current = $this->session->data['shipping_method'] ?? [];
		$session = $this->session->data['raai_multi_address'] ?? [];

		if (!empty($current['_raai_multi_address_adjusted']) && !empty($session['shipping']['base_method'])) {
			return $session['shipping']['base_method'];
		}

		return $current;
	}

	protected function restoreBaseShipping(): void {
		if (!empty($this->session->data['shipping_method']['_raai_multi_address_adjusted']) && !empty($this->session->data['raai_multi_address']['shipping']['base_method'])) {
			$this->session->data['shipping_method'] = $this->session->data['raai_multi_address']['shipping']['base_method'];
		}
	}

	protected function getOrderProductsWithSignatures(int $order_id): array {
		$order_products = [];
		$product_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . (int)$order_id . "' ORDER BY `order_product_id` ASC");

		foreach ($product_query->rows as $product) {
			$option_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_option` WHERE `order_id` = '" . (int)$order_id . "' AND `order_product_id` = '" . (int)$product['order_product_id'] . "' ORDER BY `order_option_id` ASC");
			$product['option_signature'] = Allocation::optionSignature($option_query->rows);
			$order_products[] = $product;
		}

		return $order_products;
	}

	protected function insertAddressSnapshot(int $shipment_id, int $order_id, array $address, string $telephone, string $delivery_instruction): void {
		$custom_field = $address['custom_field'] ?? [];

		if (is_string($custom_field)) {
			$decoded = json_decode($custom_field, true);
			$custom_field = is_array($decoded) ? $decoded : [];
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "raai_multi_address_shipment_address` SET `shipment_id` = '" . $shipment_id . "', `order_id` = '" . (int)$order_id . "', `firstname` = '" . $this->db->escape((string)($address['firstname'] ?? '')) . "', `lastname` = '" . $this->db->escape((string)($address['lastname'] ?? '')) . "', `company` = '" . $this->db->escape((string)($address['company'] ?? '')) . "', `address_1` = '" . $this->db->escape((string)($address['address_1'] ?? '')) . "', `address_2` = '" . $this->db->escape((string)($address['address_2'] ?? '')) . "', `city` = '" . $this->db->escape((string)($address['city'] ?? '')) . "', `postcode` = '" . $this->db->escape((string)($address['postcode'] ?? '')) . "', `zone` = '" . $this->db->escape((string)($address['zone'] ?? '')) . "', `zone_id` = '" . (int)($address['zone_id'] ?? 0) . "', `country` = '" . $this->db->escape((string)($address['country'] ?? '')) . "', `country_id` = '" . (int)($address['country_id'] ?? 0) . "', `address_format` = '" . $this->db->escape((string)($address['address_format'] ?? '')) . "', `custom_field` = '" . $this->db->escape(json_encode($custom_field)) . "', `telephone` = '" . $this->db->escape($telephone) . "', `delivery_instruction` = '" . $this->db->escape($delivery_instruction) . "'");
	}

	protected function formatShipmentReference(string $format, int $order_id, int $sequence): string {
		return str_replace(
			['{order_id}', '{sequence}'],
			[(string)$order_id, str_pad((string)$sequence, 2, '0', STR_PAD_LEFT)],
			$format
		);
	}

	protected function newGroupKey(): string {
		try {
			return 'mas_' . bin2hex(random_bytes(8));
		} catch (\Throwable $exception) {
			return 'mas_' . str_replace('.', '', uniqid('', true));
		}
	}
}
