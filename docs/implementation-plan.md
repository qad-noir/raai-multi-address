# Multi-Address Shipping Implementation Plan

## Reference Store

- Detected OpenCart version: 4.0.2.3.
- Version source: `oc_store_project/admin/index.php`, `define('VERSION', '4.0.2.3')`.
- Root `oc_store_project/composer.json`: not present in this reference store.
- Bundled extension examples inspected: `oc_store_project/extension/opencart`, `oc_store_project/system/storage/marketplace/*.ocmod.zip`.
- Checkout files inspected:
  - `catalog/controller/checkout/checkout.php`
  - `catalog/controller/checkout/shipping_address.php`
  - `catalog/controller/checkout/shipping_method.php`
  - `catalog/controller/checkout/confirm.php`
  - `catalog/model/checkout/order.php`
  - `catalog/model/checkout/cart.php`
  - `catalog/model/checkout/shipping_method.php`
  - `extension/opencart/catalog/model/total/shipping.php`
- Admin order files inspected:
  - `admin/controller/sale/order.php`
  - `admin/view/template/sale/order_info.twig`

## Package Layout

The package follows OpenCart 4.0.2.3 extension packaging:

- `install.json`
- `admin/`
- `catalog/`
- `system/`
- `README.md`

The ZIP root contains these files directly. It does not include `oc_store_project/`, `.git/`, tests, docs, build scripts, or secrets.

## Order Creation Policy

Version 1.0.0 is fixed to:

```text
single_order_multiple_shipments
```

The extension never creates multiple OpenCart orders for one checkout. OpenCart's normal `order` and `order_product` rows remain authoritative for the purchase and total product quantities. Extension tables store destination shipments and allocated quantities linked to `order_id` and `order_product_id`.

## Checkout Flow

1. A logged-in customer reaches checkout.
2. The checkout page output event injects the multi-address allocation form before shipping method selection.
3. The customer assigns physical cart-line quantities to destination address groups.
4. The allocation save endpoint validates exact cart-line quantity sums server-side.
5. The selected OpenCart shipping method remains the base method.
6. The extension rewrites `session->data['shipping_method']['cost']` to the combined multi-address shipping amount before checkout totals are calculated.
7. OpenCart creates one normal order and one payment flow.
8. The model `checkout/order.addOrder` after-event persists extension shipment rows after `order_product_id` values exist.

## Shipping Total Flow

OpenCart 4.0.2.3 standard shipping total reads:

```php
$this->session->data['shipping_method']['cost']
```

The extension uses this existing total line and changes only the session shipping method cost when multi-address mode is active. No additional shipping total row is added, avoiding double charge.

Implemented pricing modes:

- `multiply_by_address_count`
- `fixed_per_address`
- `first_address_plus_surcharge`

`per_address_quote` is not enabled in 1.0.0 because this OpenCart build's shipping models quote against the global cart and do not provide a stable virtual-package interface.

## OCMOD Requirement

No OCMOD operation is required for 1.0.0. The exact OpenCart 4.0.2.3 event system supports output and data injection at the required points.

## Services

Core allocation rules live in:

```text
system/library/raai_multi_address/allocation.php
```

This library is used by tests and by the catalog model for cart-line identity, option signatures, exact quantity validation, and shipping-mode calculations.
