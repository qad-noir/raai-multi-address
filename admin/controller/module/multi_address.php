<?php
namespace Opencart\Admin\Controller\Extension\RaaiMultiAddress\Module;

class MultiAddress extends \Opencart\System\Engine\Controller {
	private array $setting_keys = [
		'module_raai_multi_address_status',
		'module_raai_multi_address_max_addresses_per_order',
		'module_raai_multi_address_max_addresses_per_product',
		'module_raai_multi_address_allow_quantity_splitting',
		'module_raai_multi_address_allow_checkout_address_create',
		'module_raai_multi_address_save_checkout_addresses',
		'module_raai_multi_address_default_checkout_mode',
		'module_raai_multi_address_pricing_mode',
		'module_raai_multi_address_fixed_fee_per_address',
		'module_raai_multi_address_additional_address_surcharge',
		'module_raai_multi_address_separate_shipping_method',
		'module_raai_multi_address_allowed_shipping_methods',
		'module_raai_multi_address_default_shipping_method',
		'module_raai_multi_address_requote_on_change',
		'module_raai_multi_address_free_shipping_behaviour',
		'module_raai_multi_address_show_product_images',
		'module_raai_multi_address_show_shipment_weight',
		'module_raai_multi_address_show_shipping_cost_per_address',
		'module_raai_multi_address_show_delivery_instructions',
		'module_raai_multi_address_include_email_destinations',
		'module_raai_multi_address_display_invoice_destinations',
		'module_raai_multi_address_reference_format',
		'module_raai_multi_address_initial_status_id',
		'module_raai_multi_address_sync_order_status',
		'module_raai_multi_address_complete_when_delivered',
		'module_raai_multi_address_allow_admin_destination_edit',
		'module_raai_multi_address_allow_admin_product_reassign',
		'module_raai_multi_address_preserve_data',
		'module_raai_multi_address_audit_retention_days',
		'module_raai_multi_address_schema_version'
	];

	public function index(): void {
		$this->load->language('extension/raai_multi_address/module/multi_address');

		$this->document->setTitle($this->language->get('heading_title'));

		$data = $this->language->all();
		$data['breadcrumbs'] = [];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];
		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/raai_multi_address/module/multi_address', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/raai_multi_address/module/multi_address.save', 'user_token=' . $this->session->data['user_token']);
		$data['purge'] = $this->url->link('extension/raai_multi_address/module/multi_address.purgeData', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		foreach ($this->getDefaults() as $key => $value) {
			$data[$key] = $this->config->get($key);

			if ($data[$key] === null) {
				$data[$key] = $value;
			}
		}

		$data['registered_customers_only'] = true;
		$data['order_creation_mode'] = 'single_order_multiple_shipments';
		$data['pricing_modes'] = [
			'multiply_by_address_count' => $this->language->get('text_multiply_by_address_count'),
			'fixed_per_address' => $this->language->get('text_fixed_per_address'),
			'first_address_plus_surcharge' => $this->language->get('text_first_address_plus_surcharge')
		];
		$data['checkout_modes'] = [
			'single' => $this->language->get('text_single_address'),
			'multi' => $this->language->get('text_multi_address')
		];
		$data['free_shipping_behaviours'] = [
			'preserve_free' => $this->language->get('text_preserve_free'),
			'price_each_address' => $this->language->get('text_price_each_address')
		];

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$this->load->model('setting/extension');
		$data['shipping_extensions'] = $this->model_setting_extension->getExtensionsByType('shipping');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/raai_multi_address/module/multi_address', $data));
	}

	public function save(): void {
		$this->load->language('extension/raai_multi_address/module/multi_address');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/raai_multi_address/module/multi_address')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$data = $this->getDefaults();

			foreach ($this->setting_keys as $key) {
				if (isset($this->request->post[$key])) {
					$data[$key] = $this->request->post[$key];
				}
			}

			$data['module_raai_multi_address_schema_version'] = '1.0.0';

			$this->load->model('setting/setting');
			$this->model_setting_setting->editSetting('module_raai_multi_address', $data);

			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function install(): void {
		$this->load->model('extension/raai_multi_address/raai_multi_address/install');
		$this->model_extension_raai_multi_address_raai_multi_address_install->installSchema();
		$this->model_extension_raai_multi_address_raai_multi_address_install->installStatuses();

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('module_raai_multi_address', $this->getDefaults());

		$this->load->model('setting/event');
		foreach ($this->getEvents() as $event) {
			$this->model_setting_event->deleteEventByCode($event['code']);
			$this->model_setting_event->addEvent($event);
		}

		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/raai_multi_address/sale/multi_address');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/raai_multi_address/sale/multi_address');
	}

	public function uninstall(): void {
		$this->load->model('setting/event');

		foreach ($this->getEvents() as $event) {
			$this->model_setting_event->deleteEventByCode($event['code']);
		}

		$this->load->model('setting/setting');
		$settings = $this->getDefaults();
		$settings['module_raai_multi_address_status'] = 0;
		$settings['module_raai_multi_address_schema_version'] = '1.0.0';
		$this->model_setting_setting->editSetting('module_raai_multi_address', $settings);
	}

	public function purgeData(): void {
		$this->load->language('extension/raai_multi_address/module/multi_address');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/raai_multi_address/module/multi_address')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json && !$this->config->get('module_raai_multi_address_preserve_data')) {
			$this->load->model('extension/raai_multi_address/raai_multi_address/install');
			$this->model_extension_raai_multi_address_raai_multi_address_install->dropSchema();
			$json['success'] = $this->language->get('text_purge_success');
		} elseif (!$json) {
			$json['error'] = $this->language->get('error_preserve_enabled');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getDefaults(): array {
		return [
			'module_raai_multi_address_status' => 0,
			'module_raai_multi_address_max_addresses_per_order' => 10,
			'module_raai_multi_address_max_addresses_per_product' => 10,
			'module_raai_multi_address_allow_quantity_splitting' => 1,
			'module_raai_multi_address_allow_checkout_address_create' => 0,
			'module_raai_multi_address_save_checkout_addresses' => 0,
			'module_raai_multi_address_default_checkout_mode' => 'single',
			'module_raai_multi_address_pricing_mode' => 'multiply_by_address_count',
			'module_raai_multi_address_fixed_fee_per_address' => '0.0000',
			'module_raai_multi_address_additional_address_surcharge' => '0.0000',
			'module_raai_multi_address_separate_shipping_method' => 0,
			'module_raai_multi_address_allowed_shipping_methods' => '',
			'module_raai_multi_address_default_shipping_method' => '',
			'module_raai_multi_address_requote_on_change' => 1,
			'module_raai_multi_address_free_shipping_behaviour' => 'preserve_free',
			'module_raai_multi_address_show_product_images' => 1,
			'module_raai_multi_address_show_shipment_weight' => 1,
			'module_raai_multi_address_show_shipping_cost_per_address' => 1,
			'module_raai_multi_address_show_delivery_instructions' => 1,
			'module_raai_multi_address_include_email_destinations' => 0,
			'module_raai_multi_address_display_invoice_destinations' => 1,
			'module_raai_multi_address_reference_format' => 'MAS-{order_id}-{sequence}',
			'module_raai_multi_address_initial_status_id' => 1,
			'module_raai_multi_address_sync_order_status' => 0,
			'module_raai_multi_address_complete_when_delivered' => 0,
			'module_raai_multi_address_allow_admin_destination_edit' => 1,
			'module_raai_multi_address_allow_admin_product_reassign' => 0,
			'module_raai_multi_address_preserve_data' => 1,
			'module_raai_multi_address_audit_retention_days' => 365,
			'module_raai_multi_address_schema_version' => '1.0.0'
		];
	}

	private function getEvents(): array {
		return [
			[
				'code' => 'raai_multi_address_catalog_checkout_view',
				'description' => 'Multi-Address Shipping checkout allocation UI',
				'trigger' => 'catalog/view/checkout/checkout/after',
				'action' => 'extension/raai_multi_address/event/catalog.checkoutViewAfter',
				'status' => 1,
				'sort_order' => 10
			],
			[
				'code' => 'raai_multi_address_catalog_confirm_before',
				'description' => 'Multi-Address Shipping checkout validation before confirmation',
				'trigger' => 'catalog/controller/checkout/confirm/before',
				'action' => 'extension/raai_multi_address/event/catalog.confirmBefore',
				'status' => 1,
				'sort_order' => 1
			],
			[
				'code' => 'raai_multi_address_catalog_confirm_view',
				'description' => 'Multi-Address Shipping checkout confirmation summary',
				'trigger' => 'catalog/view/checkout/confirm/after',
				'action' => 'extension/raai_multi_address/event/catalog.confirmViewAfter',
				'status' => 1,
				'sort_order' => 10
			],
			[
				'code' => 'raai_multi_address_catalog_order_persist',
				'description' => 'Multi-Address Shipping shipment persistence after order creation',
				'trigger' => 'catalog/model/checkout/order/addOrder/after',
				'action' => 'extension/raai_multi_address/event/catalog.orderAddAfter',
				'status' => 1,
				'sort_order' => 10
			],
			[
				'code' => 'raai_multi_address_customer_order_view',
				'description' => 'Multi-Address Shipping customer order shipment display',
				'trigger' => 'catalog/view/account/order_info/after',
				'action' => 'extension/raai_multi_address/event/catalog.customerOrderInfoAfter',
				'status' => 1,
				'sort_order' => 10
			],
			[
				'code' => 'raai_multi_address_admin_order_tab',
				'description' => 'Multi-Address Shipping admin order shipment tab',
				'trigger' => 'admin/view/sale/order_info/before',
				'action' => 'extension/raai_multi_address/event/admin.orderInfoBefore',
				'status' => 1,
				'sort_order' => 10
			]
		];
	}
}
