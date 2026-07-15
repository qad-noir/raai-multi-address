# Database Schema

All tables use `DB_PREFIX` dynamically. `oc_` is never hardcoded in extension SQL.

## Tables

### `raai_multi_address_order`

Stores one multi-address extension record per OpenCart order.

- `multi_address_order_id` primary key
- `order_id` unique
- `customer_id`
- `address_count`
- `shipping_total`
- `pricing_mode`
- `status`
- `date_added`
- `date_modified`

### `raai_multi_address_shipment`

Stores one destination shipment per address group.

- `shipment_id` primary key
- `multi_address_order_id`
- `order_id`
- `customer_id`
- `customer_address_id`
- `shipment_reference` unique
- `shipping_method_code`
- `shipping_method_name`
- `shipping_cost`
- `tax`
- `weight`
- `shipment_status_id`
- `tracking_number`
- `tracking_url`
- `comment`
- `date_added`
- `date_modified`

### `raai_multi_address_shipment_address`

Stores an immutable address snapshot for each shipment.

- `shipment_address_id` primary key
- `shipment_id` unique
- `order_id`
- recipient and address fields
- `custom_field` JSON text
- `telephone`
- `delivery_instruction`

### `raai_multi_address_shipment_product`

Stores cart-line allocation linked to OpenCart order products.

- `shipment_product_id` primary key
- `shipment_id`
- `order_id`
- `order_product_id`
- `product_id`
- `cart_key`
- `quantity`
- `quantity_shipped`
- `quantity_returned`

### `raai_multi_address_shipment_status`

Stores localized shipment statuses.

- `shipment_status_id`
- `language_id`
- `name`
- `sort_order`

### `raai_multi_address_shipment_history`

Stores shipment-level status history.

- `shipment_history_id` primary key
- `shipment_id`
- `shipment_status_id`
- `comment`
- `notify`
- `date_added`

## Foreign Keys

No database foreign-key constraints are created. This matches the inspected OpenCart 4.0.2.3 convention, which generally uses indexed integer references without InnoDB foreign-key declarations.

## Idempotence

`raai_multi_address_order.order_id` is unique. If a payment or confirmation callback repeats after the same OpenCart order exists, the persistence routine detects the existing extension order row and exits without duplicating shipments.
