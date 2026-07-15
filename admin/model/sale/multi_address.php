<?php
namespace Opencart\Admin\Model\Extension\RaaiMultiAddress\Sale;

class MultiAddress extends \Opencart\System\Engine\Model {
	public function getOrderSummary(int $order_id): array {
		$query = $this->db->query("SELECT mao.*, COUNT(s.`shipment_id`) AS shipment_count, SUM(CASE WHEN s.`shipment_status_id` = '4' THEN 1 ELSE 0 END) AS delivered_count FROM `" . DB_PREFIX . "raai_multi_address_order` mao LEFT JOIN `" . DB_PREFIX . "raai_multi_address_shipment` s ON (s.`multi_address_order_id` = mao.`multi_address_order_id`) WHERE mao.`order_id` = '" . (int)$order_id . "' GROUP BY mao.`multi_address_order_id`");

		return $query->row;
	}

	public function getShipments(int $order_id): array {
		$shipments = [];
		$query = $this->db->query("SELECT s.*, ss.`name` AS status_name FROM `" . DB_PREFIX . "raai_multi_address_shipment` s LEFT JOIN `" . DB_PREFIX . "raai_multi_address_shipment_status` ss ON (ss.`shipment_status_id` = s.`shipment_status_id` AND ss.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE s.`order_id` = '" . (int)$order_id . "' ORDER BY s.`shipment_id` ASC");

		foreach ($query->rows as $shipment) {
			$shipment_id = (int)$shipment['shipment_id'];
			$address_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "raai_multi_address_shipment_address` WHERE `shipment_id` = '" . $shipment_id . "' LIMIT 1");
			$product_query = $this->db->query("SELECT sp.*, op.`name`, op.`model` FROM `" . DB_PREFIX . "raai_multi_address_shipment_product` sp LEFT JOIN `" . DB_PREFIX . "order_product` op ON (op.`order_product_id` = sp.`order_product_id`) WHERE sp.`shipment_id` = '" . $shipment_id . "' ORDER BY sp.`shipment_product_id` ASC");
			$history_query = $this->db->query("SELECT sh.*, ss.`name` AS status_name FROM `" . DB_PREFIX . "raai_multi_address_shipment_history` sh LEFT JOIN `" . DB_PREFIX . "raai_multi_address_shipment_status` ss ON (ss.`shipment_status_id` = sh.`shipment_status_id` AND ss.`language_id` = '" . (int)$this->config->get('config_language_id') . "') WHERE sh.`shipment_id` = '" . $shipment_id . "' ORDER BY sh.`shipment_history_id` DESC");

			$shipment['address'] = $address_query->row;
			$shipment['products'] = $product_query->rows;
			$shipment['histories'] = $history_query->rows;
			$shipments[] = $shipment;
		}

		return $shipments;
	}

	public function getShipmentStatuses(): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "raai_multi_address_shipment_status` WHERE `language_id` = '" . (int)$this->config->get('config_language_id') . "' ORDER BY `sort_order`, `name`");

		return $query->rows;
	}

	public function getShipment(int $shipment_id): array {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "raai_multi_address_shipment` WHERE `shipment_id` = '" . (int)$shipment_id . "'");

		return $query->row;
	}

	public function updateShipment(int $shipment_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "raai_multi_address_shipment` SET `shipment_status_id` = '" . (int)$data['shipment_status_id'] . "', `tracking_number` = '" . $this->db->escape((string)$data['tracking_number']) . "', `tracking_url` = '" . $this->db->escape((string)$data['tracking_url']) . "', `comment` = '" . $this->db->escape((string)$data['comment']) . "', `date_modified` = NOW() WHERE `shipment_id` = '" . (int)$shipment_id . "'");

		$this->db->query("INSERT INTO `" . DB_PREFIX . "raai_multi_address_shipment_history` SET `shipment_id` = '" . (int)$shipment_id . "', `shipment_status_id` = '" . (int)$data['shipment_status_id'] . "', `comment` = '" . $this->db->escape((string)$data['history_comment']) . "', `notify` = '" . (int)!empty($data['notify']) . "', `date_added` = NOW()");
	}
}
