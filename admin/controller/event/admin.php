<?php
namespace Opencart\Admin\Controller\Extension\RaaiMultiAddress\Event;

class Admin extends \Opencart\System\Engine\Controller {
	public function orderInfoBefore(string &$route, array &$data, string &$code): void {
		$order_id = (int)($this->request->get['order_id'] ?? 0);
		$content = $this->getOrderContent($order_id);

		if ($content) {
			$this->load->language('extension/raai_multi_address/sale/multi_address');
			$data['tabs'][] = [
				'code'    => 'raai-multi-address',
				'title'   => $this->language->get('tab_shipments'),
				'content' => $content
			];
		}
	}

	public function orderInfoAfter(string &$route, array &$data, string &$output): void {
		$order_id = (int)($this->request->get['order_id'] ?? 0);
		$content = $this->getOrderContent($order_id);

		if (!$content) {
			return;
		}

		$needle = '<div id="collapse-order" class="collapse">';

		if (str_contains($output, $needle)) {
			$output = str_replace($needle, $content . $needle, $output);
		} else {
			$output .= $content;
		}
	}

	public function orderShippingAfter(string &$route, array &$data, string &$output): void {
		if (!$this->config->get('module_raai_multi_address_status') || empty($data['orders'])) {
			return;
		}

		$this->load->language('extension/raai_multi_address/sale/multi_address');
		$this->load->model('extension/raai_multi_address/sale/multi_address');

		foreach ($data['orders'] as $order) {
			$order_id = (int)($order['order_id'] ?? 0);

			if (!$order_id || str_contains($output, 'raai-multi-address-shipping-' . $order_id)) {
				continue;
			}

			$shipments = $this->model_extension_raai_multi_address_sale_multi_address->getShipments($order_id);

			if (!$shipments) {
				continue;
			}

			$view_data = $this->language->all();
			$view_data['order_id'] = $order_id;
			$view_data['shipments'] = $shipments;
			$content = $this->load->view('extension/raai_multi_address/sale/multi_address_shipping', $view_data);
			$output = $this->injectShippingContent($output, $order_id, $content);
		}
	}

	private function getOrderContent(int $order_id): string {
		if (!$this->config->get('module_raai_multi_address_status')) {
			return '';
		}

		if (!$order_id || !$this->user->hasPermission('access', 'extension/raai_multi_address/sale/multi_address')) {
			return '';
		}

		$this->load->model('extension/raai_multi_address/sale/multi_address');

		if (!$this->model_extension_raai_multi_address_sale_multi_address->getOrderSummary($order_id)) {
			return '';
		}

		$content = $this->load->controller('extension/raai_multi_address/sale/multi_address.order');

		if (!$content instanceof \Exception && $content) {
			return $content;
		}

		return '';
	}

	private function injectShippingContent(string $output, int $order_id, string $content): string {
		$pattern = '~(<h1>.*?#' . $order_id . '</h1>.*?)(<table class="table table-bordered">)~s';

		if (preg_match($pattern, $output, $matches, PREG_OFFSET_CAPTURE)) {
			return substr_replace($output, $content, $matches[2][1], 0);
		}

		return $output . $content;
	}
}
