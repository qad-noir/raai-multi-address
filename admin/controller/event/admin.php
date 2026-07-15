<?php
namespace Opencart\Admin\Controller\Extension\RaaiMultiAddress\Event;

class Admin extends \Opencart\System\Engine\Controller {
	public function orderInfoBefore(string &$route, array &$data, string &$code): void {
		if (!$this->config->get('module_raai_multi_address_status')) {
			return;
		}

		$order_id = (int)($this->request->get['order_id'] ?? 0);

		if (!$order_id || !$this->user->hasPermission('access', 'extension/raai_multi_address/sale/multi_address')) {
			return;
		}

		$content = $this->load->controller('extension/raai_multi_address/sale/multi_address.order');

		if (!$content instanceof \Exception && $content) {
			$this->load->language('extension/raai_multi_address/sale/multi_address');
			$data['tabs'][] = [
				'code'    => 'raai-multi-address',
				'title'   => $this->language->get('tab_shipments'),
				'content' => $content
			];
		}
	}
}
