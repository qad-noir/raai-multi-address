<?php
namespace Opencart\Admin\Model\Extension\RaaiMultiAddress\RaaiMultiAddress;

class Install extends \Opencart\System\Engine\Model {
	public function installSchema(): void {
		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "raai_multi_address_order` (
			`multi_address_order_id` INT(11) NOT NULL AUTO_INCREMENT,
			`order_id` INT(11) NOT NULL,
			`customer_id` INT(11) NOT NULL,
			`address_count` INT(11) NOT NULL DEFAULT '0',
			`shipping_total` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`pricing_mode` VARCHAR(64) NOT NULL,
			`status` VARCHAR(32) NOT NULL,
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`multi_address_order_id`),
			UNIQUE KEY `order_id` (`order_id`),
			KEY `customer_id` (`customer_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "raai_multi_address_shipment` (
			`shipment_id` INT(11) NOT NULL AUTO_INCREMENT,
			`multi_address_order_id` INT(11) NOT NULL,
			`order_id` INT(11) NOT NULL,
			`customer_id` INT(11) NOT NULL,
			`customer_address_id` INT(11) NOT NULL DEFAULT '0',
			`shipment_reference` VARCHAR(64) NOT NULL,
			`shipping_method_code` VARCHAR(128) NOT NULL,
			`shipping_method_name` VARCHAR(255) NOT NULL,
			`shipping_cost` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`tax` DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
			`weight` DECIMAL(15,8) NOT NULL DEFAULT '0.00000000',
			`shipment_status_id` INT(11) NOT NULL DEFAULT '1',
			`tracking_number` VARCHAR(128) NOT NULL,
			`tracking_url` VARCHAR(2048) NOT NULL,
			`comment` TEXT NOT NULL,
			`date_added` DATETIME NOT NULL,
			`date_modified` DATETIME NOT NULL,
			PRIMARY KEY (`shipment_id`),
			UNIQUE KEY `shipment_reference` (`shipment_reference`),
			KEY `multi_address_order_id` (`multi_address_order_id`),
			KEY `order_id` (`order_id`),
			KEY `customer_id` (`customer_id`),
			KEY `shipment_status_id` (`shipment_status_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "raai_multi_address_shipment_address` (
			`shipment_address_id` INT(11) NOT NULL AUTO_INCREMENT,
			`shipment_id` INT(11) NOT NULL,
			`order_id` INT(11) NOT NULL,
			`firstname` VARCHAR(32) NOT NULL,
			`lastname` VARCHAR(32) NOT NULL,
			`company` VARCHAR(60) NOT NULL,
			`address_1` VARCHAR(128) NOT NULL,
			`address_2` VARCHAR(128) NOT NULL,
			`city` VARCHAR(128) NOT NULL,
			`postcode` VARCHAR(10) NOT NULL,
			`zone` VARCHAR(128) NOT NULL,
			`zone_id` INT(11) NOT NULL DEFAULT '0',
			`country` VARCHAR(128) NOT NULL,
			`country_id` INT(11) NOT NULL DEFAULT '0',
			`address_format` TEXT NOT NULL,
			`custom_field` TEXT NOT NULL,
			`telephone` VARCHAR(32) NOT NULL,
			`delivery_instruction` TEXT NOT NULL,
			PRIMARY KEY (`shipment_address_id`),
			UNIQUE KEY `shipment_id` (`shipment_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "raai_multi_address_shipment_product` (
			`shipment_product_id` INT(11) NOT NULL AUTO_INCREMENT,
			`shipment_id` INT(11) NOT NULL,
			`order_id` INT(11) NOT NULL,
			`order_product_id` INT(11) NOT NULL,
			`product_id` INT(11) NOT NULL,
			`cart_key` VARCHAR(128) NOT NULL,
			`quantity` INT(11) NOT NULL DEFAULT '0',
			`quantity_shipped` INT(11) NOT NULL DEFAULT '0',
			`quantity_returned` INT(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`shipment_product_id`),
			UNIQUE KEY `shipment_product` (`shipment_id`, `order_product_id`, `cart_key`),
			KEY `order_id` (`order_id`),
			KEY `order_product_id` (`order_product_id`),
			KEY `product_id` (`product_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "raai_multi_address_shipment_status` (
			`shipment_status_id` INT(11) NOT NULL,
			`language_id` INT(11) NOT NULL,
			`name` VARCHAR(64) NOT NULL,
			`sort_order` INT(11) NOT NULL DEFAULT '0',
			PRIMARY KEY (`shipment_status_id`, `language_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "raai_multi_address_shipment_history` (
			`shipment_history_id` INT(11) NOT NULL AUTO_INCREMENT,
			`shipment_id` INT(11) NOT NULL,
			`shipment_status_id` INT(11) NOT NULL,
			`comment` TEXT NOT NULL,
			`notify` TINYINT(1) NOT NULL DEFAULT '0',
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`shipment_history_id`),
			KEY `shipment_id` (`shipment_id`),
			KEY `shipment_status_id` (`shipment_status_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
	}

	public function installStatuses(): void {
		$this->load->model('localisation/language');
		$languages = $this->model_localisation_language->getLanguages();
		$statuses = [
			1 => ['name' => 'Pending', 'sort_order' => 1],
			2 => ['name' => 'Processing', 'sort_order' => 2],
			3 => ['name' => 'Dispatched', 'sort_order' => 3],
			4 => ['name' => 'Delivered', 'sort_order' => 4],
			5 => ['name' => 'Cancelled', 'sort_order' => 5]
		];

		foreach ($languages as $language) {
			foreach ($statuses as $shipment_status_id => $status) {
				$this->db->query("REPLACE INTO `" . DB_PREFIX . "raai_multi_address_shipment_status` SET `shipment_status_id` = '" . (int)$shipment_status_id . "', `language_id` = '" . (int)$language['language_id'] . "', `name` = '" . $this->db->escape($status['name']) . "', `sort_order` = '" . (int)$status['sort_order'] . "'");
			}
		}
	}

	public function dropSchema(): void {
		$tables = [
			'raai_multi_address_shipment_history',
			'raai_multi_address_shipment_product',
			'raai_multi_address_shipment_address',
			'raai_multi_address_shipment',
			'raai_multi_address_order',
			'raai_multi_address_shipment_status'
		];

		foreach ($tables as $table) {
			$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . $table . "`");
		}
	}
}
