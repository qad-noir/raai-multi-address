<?php
namespace Opencart\System\Extension\RaaiMultiAddress\Library\RaaiMultiAddress;

class Allocation {
	public const ORDER_CREATION_MODE = 'single_order_multiple_shipments';

	public static function buildCartLines(array $products): array {
		$lines = [];

		foreach ($products as $product) {
			$option_signature = self::optionSignature($product['option'] ?? []);
			$cart_key = (string)($product['cart_id'] ?? '');

			if ($cart_key === '') {
				$cart_key = (string)($product['product_id'] ?? 0) . ':' . $option_signature;
			}

			$lines[$cart_key] = [
				'cart_key'         => $cart_key,
				'cart_id'          => (string)($product['cart_id'] ?? $cart_key),
				'product_id'       => (int)($product['product_id'] ?? 0),
				'name'             => (string)($product['name'] ?? ''),
				'model'            => (string)($product['model'] ?? ''),
				'quantity'         => (int)($product['quantity'] ?? 0),
				'shipping'         => !empty($product['shipping']),
				'option'           => $product['option'] ?? [],
				'option_signature' => $option_signature
			];
		}

		return $lines;
	}

	public static function optionSignature(array $options): string {
		$normalized = [];

		foreach ($options as $option) {
			$normalized[] = [
				'product_option_id'       => (int)($option['product_option_id'] ?? 0),
				'product_option_value_id' => (int)($option['product_option_value_id'] ?? 0),
				'name'                    => (string)($option['name'] ?? ''),
				'value'                   => (string)($option['value'] ?? ''),
				'type'                    => (string)($option['type'] ?? '')
			];
		}

		usort($normalized, static function (array $a, array $b): int {
			return [$a['product_option_id'], $a['product_option_value_id'], $a['name'], $a['value'], $a['type']]
				<=> [$b['product_option_id'], $b['product_option_value_id'], $b['name'], $b['value'], $b['type']];
		});

		return hash('sha256', json_encode($normalized));
	}

	public static function cartSignature(array $cart_lines): string {
		$signature = [];

		foreach ($cart_lines as $cart_key => $line) {
			$signature[$cart_key] = [
				'product_id'       => (int)$line['product_id'],
				'quantity'         => (int)$line['quantity'],
				'shipping'         => (bool)$line['shipping'],
				'option_signature' => (string)$line['option_signature']
			];
		}

		ksort($signature);

		return hash('sha256', json_encode($signature));
	}

	public static function normalizeGroups(array $raw_groups, array $cart_lines): array {
		$groups = [];

		foreach ($raw_groups as $group_key => $group) {
			$group_key = self::cleanGroupKey((string)$group_key);

			if ($group_key === '') {
				continue;
			}

			$products = [];

			foreach (($group['products'] ?? []) as $cart_key => $quantity) {
				if (!isset($cart_lines[$cart_key])) {
					continue;
				}

				$quantity = (int)$quantity;

				if ($quantity > 0) {
					$products[(string)$cart_key] = $quantity;
				}
			}

			$groups[$group_key] = [
				'group_key'            => $group_key,
				'address_id'           => (int)($group['address_id'] ?? 0),
				'delivery_instruction' => trim((string)($group['delivery_instruction'] ?? '')),
				'shipping_method_code' => trim((string)($group['shipping_method_code'] ?? '')),
				'products'             => $products
			];
		}

		return $groups;
	}

	public static function validate(array $cart_lines, array $groups, array $valid_address_ids, array $settings = []): array {
		$errors = [];
		$physical_lines = array_filter($cart_lines, static function (array $line): bool {
			return !empty($line['shipping']);
		});

		if (!$physical_lines) {
			return ['valid' => true, 'errors' => [], 'allocated' => []];
		}

		$max_addresses_per_order = max(1, (int)($settings['max_addresses_per_order'] ?? 10));
		$max_addresses_per_product = max(1, (int)($settings['max_addresses_per_product'] ?? 10));
		$valid_address_ids = array_map('intval', $valid_address_ids);
		$address_count = 0;
		$allocated = [];
		$product_address_count = [];

		foreach ($physical_lines as $cart_key => $line) {
			$allocated[$cart_key] = 0;
			$product_address_count[$cart_key] = 0;
		}

		foreach ($groups as $group_key => $group) {
			if (empty($group['products'])) {
				$errors[] = 'Shipment group ' . $group_key . ' has no assigned products.';
				continue;
			}

			$address_count++;

			if (empty($group['address_id']) || !in_array((int)$group['address_id'], $valid_address_ids, true)) {
				$errors[] = 'Shipment group ' . $group_key . ' has an invalid address.';
			}

			foreach ($group['products'] as $cart_key => $quantity) {
				if (!isset($physical_lines[$cart_key])) {
					$errors[] = 'Shipment group ' . $group_key . ' contains an invalid cart line.';
					continue;
				}

				$quantity = (int)$quantity;

				if ($quantity < 1) {
					$errors[] = 'Shipment group ' . $group_key . ' has an invalid quantity.';
					continue;
				}

				$allocated[$cart_key] += $quantity;
				$product_address_count[$cart_key]++;
			}
		}

		if ($address_count < 1) {
			$errors[] = 'At least one destination address is required.';
		}

		if ($address_count > $max_addresses_per_order) {
			$errors[] = 'The order exceeds the maximum destination address count.';
		}

		foreach ($physical_lines as $cart_key => $line) {
			if ((int)$allocated[$cart_key] < (int)$line['quantity']) {
				$errors[] = 'Cart line ' . $cart_key . ' is under-allocated.';
			}

			if ((int)$allocated[$cart_key] > (int)$line['quantity']) {
				$errors[] = 'Cart line ' . $cart_key . ' is over-allocated.';
			}

			if ((int)$product_address_count[$cart_key] > $max_addresses_per_product) {
				$errors[] = 'Cart line ' . $cart_key . ' exceeds the maximum addresses per product.';
			}
		}

		return [
			'valid'     => !$errors,
			'errors'    => $errors,
			'allocated' => $allocated
		];
	}

	public static function calculateShipping(float $base_cost, int $address_count, array $settings): float {
		$address_count = max(0, $address_count);
		$mode = (string)($settings['pricing_mode'] ?? 'multiply_by_address_count');

		if ($address_count === 0) {
			return 0.0;
		}

		if ($mode === 'fixed_per_address') {
			return round((float)($settings['fixed_fee_per_address'] ?? 0) * $address_count, 4);
		}

		if ($mode === 'first_address_plus_surcharge') {
			return round($base_cost + (max(0, $address_count - 1) * (float)($settings['additional_address_surcharge'] ?? 0)), 4);
		}

		return round($base_cost * $address_count, 4);
	}

	public static function calculateShipmentCosts(float $base_cost, array $groups, array $settings): array {
		$costs = [];
		$index = 0;
		$mode = (string)($settings['pricing_mode'] ?? 'multiply_by_address_count');

		foreach ($groups as $group_key => $group) {
			if (empty($group['products'])) {
				continue;
			}

			$index++;

			if ($mode === 'fixed_per_address') {
				$costs[$group_key] = round((float)($settings['fixed_fee_per_address'] ?? 0), 4);
			} elseif ($mode === 'first_address_plus_surcharge') {
				$costs[$group_key] = $index === 1 ? round($base_cost, 4) : round((float)($settings['additional_address_surcharge'] ?? 0), 4);
			} else {
				$costs[$group_key] = round($base_cost, 4);
			}
		}

		return $costs;
	}

	public static function mapOrderProductsToCartLines(array $cart_lines, array $order_products): array {
		$map = [];
		$bucket = [];

		foreach ($order_products as $order_product) {
			$key = (int)$order_product['product_id'] . ':' . (string)($order_product['option_signature'] ?? '');
			$bucket[$key][] = $order_product;
		}

		foreach ($cart_lines as $cart_key => $line) {
			$key = (int)$line['product_id'] . ':' . (string)$line['option_signature'];

			if (!empty($bucket[$key])) {
				$map[$cart_key] = array_shift($bucket[$key]);
			}
		}

		return $map;
	}

	public static function cleanGroupKey(string $group_key): string {
		return preg_replace('/[^a-zA-Z0-9_-]/', '', $group_key) ?: '';
	}
}
