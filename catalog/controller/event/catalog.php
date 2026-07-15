<?php
namespace Opencart\Catalog\Controller\Extension\RaaiMultiAddress\Event;

class Catalog extends \Opencart\System\Engine\Controller {
	public function checkoutViewAfter(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_raai_multi_address_status')) {
			return;
		}

		$multi_address = $this->load->controller('extension/raai_multi_address/checkout/multi_address');

		if (!$multi_address) {
			return;
		}

		$needle = '<div id="checkout-shipping-method"';
		$position = strpos($output, $needle);

		if ($position !== false) {
			$output = substr_replace($output, $multi_address, $position, 0);
		} else {
			$output = str_replace('<div id="checkout-confirm">', $multi_address . '<div id="checkout-confirm">', $output);
		}
	}

	public function confirmBefore(string &$route, array &$args): void {
		if (!$this->config->get('module_raai_multi_address_status')) {
			return;
		}

		$this->load->model('extension/raai_multi_address/checkout/multi_address');
		$this->model_extension_raai_multi_address_checkout_multi_address->beforeConfirm();
	}

	public function confirmViewAfter(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_raai_multi_address_status')) {
			return;
		}

		$summary = $this->load->controller('extension/raai_multi_address/checkout/multi_address.summary');

		if ($summary) {
			$output = $summary . $output;
		}
	}

	public function orderAddAfter(string &$route, array &$args, mixed &$output): void {
		if (!$this->config->get('module_raai_multi_address_status') || !$output) {
			return;
		}

		$order_data = $args[0] ?? [];

		if (!is_array($order_data)) {
			return;
		}

		$this->load->model('extension/raai_multi_address/checkout/multi_address');
		$this->model_extension_raai_multi_address_checkout_multi_address->persistOrder((int)$output, $order_data);
	}

	public function customerOrderInfoAfter(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_raai_multi_address_status')) {
			return;
		}

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		if (!$order_id) {
			return;
		}

		$this->load->model('account/order');
		$order_info = $this->model_account_order->getOrder($order_id);

		if (!$order_info || (int)$order_info['customer_id'] !== (int)$this->customer->getId()) {
			return;
		}

		$this->load->language('extension/raai_multi_address/account/order');
		$this->load->model('extension/raai_multi_address/checkout/multi_address');

		$shipments = $this->model_extension_raai_multi_address_checkout_multi_address->getOrderShipments($order_id);

		if (!$shipments) {
			return;
		}

		$view_data = $this->language->all();
		$view_data['shipments'] = $shipments;
		$view_data['currency_code'] = $order_info['currency_code'];
		$view_data['currency_value'] = $order_info['currency_value'];

		foreach ($view_data['shipments'] as &$shipment) {
			$shipment['shipping_cost_text'] = $this->currency->format((float)$shipment['shipping_cost'], $order_info['currency_code'], $order_info['currency_value']);
		}

		$html = $this->load->view('extension/raai_multi_address/account/order_shipments', $view_data);
		$output = str_replace('<h2>' . $data['text_history'] . '</h2>', $html . '<h2>' . $data['text_history'] . '</h2>', $output);
	}
}
