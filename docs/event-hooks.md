# Event Hooks

Detected OpenCart version: 4.0.2.3.

OpenCart 4.0.2.3 registers extension events from `oc_event` through `catalog/controller/startup/event.php` and `admin/controller/startup/event.php`. Stored triggers are prefixed with `catalog/`, `admin/`, or `system/`; startup removes the application prefix before registering the runtime event.

## Hooks Used

| Code | Trigger | Action | Purpose |
| --- | --- | --- | --- |
| `raai_multi_address_catalog_checkout_view` | `catalog/view/checkout/checkout/after` | `extension/raai_multi_address/event/catalog.checkoutViewAfter` | Injects the checkout allocation UI. |
| `raai_multi_address_catalog_confirm_before` | `catalog/controller/checkout/confirm/before` | `extension/raai_multi_address/event/catalog.confirmBefore` | Revalidates allocation before confirmation totals and payment output. |
| `raai_multi_address_catalog_confirm_view` | `catalog/view/checkout/confirm/after` | `extension/raai_multi_address/event/catalog.confirmViewAfter` | Adds shipment destination summary to checkout confirmation. |
| `raai_multi_address_catalog_order_persist` | `catalog/model/checkout/order/addOrder/after` | `extension/raai_multi_address/event/catalog.orderAddAfter` | Persists extension shipment rows after the OpenCart order and order products exist. |
| `raai_multi_address_customer_order_view` | `catalog/view/account/order_info/after` | `extension/raai_multi_address/event/catalog.customerOrderInfoAfter` | Shows shipment groups under the customer's one order. |
| `raai_multi_address_admin_order_tab` | `admin/view/sale/order_info/before` | `extension/raai_multi_address/event/admin.orderInfoBefore` | Adds a Multi-Address Shipments tab to admin order view. |

## Event Signatures Verified

- Controller before: `controller/{route}/before`, args `&$route`, `&$args`.
- Controller after: `controller/{route}/after`, args `&$route`, `&$args`, `&$output`.
- Model before: `model/{route}/{method}/before`, args `&$route`, `&$args`.
- Model after: `model/{route}/{method}/after`, args `&$route`, `&$args`, `&$output`.
- View before: `view/{route}/before`, args `&$route`, `&$data`, `&$code`.
- View after: `view/{route}/after`, args `&$route`, `&$data`, `&$output`.

## OCMOD

No OCMOD XML is used in version 1.0.0.
