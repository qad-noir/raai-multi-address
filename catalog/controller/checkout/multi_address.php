<?php
namespace Opencart\Catalog\Controller\Extension\RaaiMultiAddress\Checkout;

class MultiAddress extends \Opencart\System\Engine\Controller {
	public function index(): string {
		$this->load->language('extension/raai_multi_address/checkout/multi_address');
		$this->load->model('extension/raai_multi_address/checkout/multi_address');

		if (!$this->model_extension_raai_multi_address_checkout_multi_address->isAvailable()) {
			return '';
		}

		$prepared = $this->model_extension_raai_multi_address_checkout_multi_address->getPreparedData();
		$data = $this->language->all();
		$data['save'] = $this->url->link('extension/raai_multi_address/checkout/multi_address.save', 'language=' . $this->config->get('config_language'));
		$data['language'] = $this->config->get('config_language');
		$data['currency'] = $this->session->data['currency'];
		$data['enabled'] = $prepared['enabled'];
		$data['cart_lines'] = $prepared['cart_lines'];
		$data['addresses'] = $prepared['addresses'];
		$data['groups'] = $prepared['groups'];
		$data['errors'] = $prepared['validation']['errors'];
		$data['show_product_images'] = (bool)$this->config->get('module_raai_multi_address_show_product_images');
		$data['show_delivery_instructions'] = (bool)$this->config->get('module_raai_multi_address_show_delivery_instructions');
		$data['show_shipping_cost_per_address'] = (bool)$this->config->get('module_raai_multi_address_show_shipping_cost_per_address');

		return $this->load->view('extension/raai_multi_address/checkout/multi_address', $data);
	}

	public function summary(): string {
		$this->load->language('extension/raai_multi_address/checkout/multi_address');
		$this->load->model('extension/raai_multi_address/checkout/multi_address');

		$prepared = $this->model_extension_raai_multi_address_checkout_multi_address->getPreparedData();

		if (!$prepared['enabled']) {
			return '';
		}

		$data = $this->language->all();
		$data['cart_lines'] = $prepared['cart_lines'];
		$data['addresses'] = $prepared['addresses'];
		$data['groups'] = $prepared['groups'];
		$data['errors'] = $this->session->data['raai_multi_address_error'] ?? $prepared['validation']['errors'];
		$data['shipping'] = $this->session->data['raai_multi_address']['shipping'] ?? [];

		if (isset($data['shipping']['combined_cost'])) {
			$data['shipping']['combined_cost_text'] = $this->currency->format((float)$data['shipping']['combined_cost'], $this->session->data['currency']);
		}

		return $this->load->view('extension/raai_multi_address/checkout/multi_address_summary', $data);
	}

	public function save(): void {
		$this->load->language('extension/raai_multi_address/checkout/multi_address');

		$json = [];

		if (!$this->customer->isLogged()) {
			$json['error'] = [$this->language->get('error_logged_in')];
		}

		if (!$this->cart->hasShipping()) {
			$json['error'] = [$this->language->get('error_shipping_required')];
		}

		if (!$json) {
			$this->load->model('extension/raai_multi_address/checkout/multi_address');

			$result = $this->model_extension_raai_multi_address_checkout_multi_address->saveAllocation($this->request->post);

			if ($result['valid']) {
				$json['success'] = $this->language->get('text_saved');
				$json['shipping'] = $result['shipping'] ?? [];
			} else {
				$json['error'] = $result['errors'];
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
