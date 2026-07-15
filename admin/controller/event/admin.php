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
}
