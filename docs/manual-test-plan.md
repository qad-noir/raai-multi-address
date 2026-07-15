# Manual Integration Test Plan

## Product X x3 Split Across Two Addresses

1. Install `build/raai_multi_address.ocmod.zip` through Extensions > Installer.
2. Install and enable the module at Extensions > Extensions > Modules > Multi-Address Shipping.
3. Confirm `Preserve data on uninstall` is enabled.
4. Confirm a standard shipping method is enabled.
5. Register or log in as a customer.
6. Add two customer addresses:
   - Address A
   - Address B
7. Add Product X with quantity 3 to the cart.
8. Go to checkout.
9. Select a normal shipping address and shipping method.
10. Select "Ship products to multiple addresses".
11. Assign:
    - Address A -> Product X quantity 1
    - Address B -> Product X quantity 2
12. Save destinations.
13. Confirm checkout shows:
    - one destination for Address A with Product X x1
    - one destination for Address B with Product X x2
    - one combined shipping total
14. Complete one payment.
15. Confirm the customer success page and order history show one order number.
16. In the database, confirm:
    - exactly one `order` row was created for the checkout
    - the matching `order_product.quantity` is 3
    - one `raai_multi_address_order` row exists for the order
    - two `raai_multi_address_shipment` rows exist
    - shipment product quantities are 1 and 2
    - shipment product quantities total 3
17. In admin Sales > Orders, open the order.
18. Confirm the Multi-Address Shipments tab shows two destination shipments.
19. Update one shipment status and tracking number.
20. Print a packing slip for each shipment.

## Failure Checks

The flow must fail before payment if:

- Product X x3 is allocated as only 1 + 1.
- Product X x3 is allocated as 2 + 2.
- a shipment group has no products.
- a destination address does not belong to the logged-in customer.
- the cart quantity changes after allocation and before payment.
- no shipping method is selected.

The flow must not:

- create two OpenCart orders
- alter `order_product.quantity` to 1 or 2
- invoke payment separately per address
- add a second shipping total row
