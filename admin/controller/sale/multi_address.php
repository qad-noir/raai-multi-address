<?php
namespace Opencart\Admin\Controller\Extension\RaaiMultiAddress\Sale;

class MultiAddress extends \Opencart\System\Engine\Controller {
	public function order(): string {
		$this->load->language('extension/raai_multi_address/sale/multi_address');
		$this->load->model('extension/raai_multi_address/sale/multi_address');

		$order_id = (int)($this->request->get['order_id'] ?? 0);
		$summary = $this->model_extension_raai_multi_address_sale_multi_address->getOrderSummary($order_id);

		if (!$summary) {
			return '<div class="alert alert-info">' . $this->language->get('text_not_multi_address') . '</div>';
		}

		$data = $this->language->all();
		$data['order_id'] = $order_id;
		$data['summary'] = $summary;
		$data['shipments'] = $this->model_extension_raai_multi_address_sale_multi_address->getShipments($order_id);
		$data['shipment_statuses'] = $this->model_extension_raai_multi_address_sale_multi_address->getShipmentStatuses();
		$data['update'] = $this->url->link('extension/raai_multi_address/sale/multi_address.update', 'user_token=' . $this->session->data['user_token']);
		$data['packing_slip'] = $this->url->link('extension/raai_multi_address/sale/multi_address.packingSlip', 'user_token=' . $this->session->data['user_token']);

		$this->load->model('sale/order');
		$order_info = $this->model_sale_order->getOrder($order_id);
		$data['shipping_total'] = $this->currency->format((float)$summary['shipping_total'], $order_info['currency_code'], $order_info['currency_value']);

		foreach ($data['shipments'] as &$shipment) {
			$shipment['shipping_cost_text'] = $this->currency->format((float)$shipment['shipping_cost'], $order_info['currency_code'], $order_info['currency_value']);
		}

		return $this->load->view('extension/raai_multi_address/sale/multi_address_order', $data);
	}

	public function update(): void {
		$this->load->language('extension/raai_multi_address/sale/multi_address');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/raai_multi_address/sale/multi_address')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$shipment_id = (int)($this->request->post['shipment_id'] ?? 0);

		if (!$shipment_id) {
			$json['error'] = $this->language->get('error_shipment');
		}

		if (!$json) {
			$this->load->model('extension/raai_multi_address/sale/multi_address');
			$shipment = $this->model_extension_raai_multi_address_sale_multi_address->getShipment($shipment_id);

			if (!$shipment) {
				$json['error'] = $this->language->get('error_shipment');
			}
		}

		if (!$json) {
			$this->model_extension_raai_multi_address_sale_multi_address->updateShipment($shipment_id, [
				'shipment_status_id' => (int)($this->request->post['shipment_status_id'] ?? $shipment['shipment_status_id']),
				'tracking_number'    => (string)($this->request->post['tracking_number'] ?? ''),
				'tracking_url'       => (string)($this->request->post['tracking_url'] ?? ''),
				'comment'            => (string)($this->request->post['comment'] ?? ''),
				'history_comment'    => (string)($this->request->post['history_comment'] ?? ''),
				'notify'             => !empty($this->request->post['notify'])
			]);

			$json['success'] = $this->language->get('text_update_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function packingSlip(): void {
		$this->load->language('extension/raai_multi_address/sale/multi_address');
		$this->load->model('extension/raai_multi_address/sale/multi_address');

		$shipment_id = (int)($this->request->get['shipment_id'] ?? 0);
		$shipment = $this->model_extension_raai_multi_address_sale_multi_address->getShipment($shipment_id);

		if (!$shipment || !$this->user->hasPermission('access', 'extension/raai_multi_address/sale/multi_address')) {
			$this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token']));
			return;
		}

		$shipments = $this->model_extension_raai_multi_address_sale_multi_address->getShipments((int)$shipment['order_id']);
		$current = [];

		foreach ($shipments as $item) {
			if ((int)$item['shipment_id'] === $shipment_id) {
				$current = $item;
				break;
			}
		}

		$data = $this->language->all();
		$data['shipment'] = $current;
		$data['direction'] = $this->language->get('direction');
		$data['code'] = $this->language->get('code');

		$this->response->setOutput($this->load->view('extension/raai_multi_address/sale/multi_address_packing_slip', $data));
	}
}
