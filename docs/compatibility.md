# Compatibility

## Tested Locally

- OpenCart 4.0.2.3 source layout and event signatures.
- PHP syntax with the local PHP CLI.
- Allocation validation and shipping pricing modes through `tests/run.php`.
- Package build rules through `scripts/build-extension.php`.

## Supported in 1.0.0

- Registered customers only.
- One OpenCart order with multiple extension shipment records.
- Cart lines with product options treated separately.
- Physical products only require allocation; non-shippable products are ignored by allocation validation.
- Standard OpenCart shipping total reused with adjusted cost to avoid double-charging.
- Multiple languages for initial shipment statuses.
- Configurable `DB_PREFIX`.

## Limitations

- Guest checkout is intentionally unchanged.
- `per_address_quote` is not implemented because OpenCart 4.0.2.3 bundled shipping methods quote against the global cart; a safe virtual-package adapter is not available in the inspected interfaces.
- Separate shipping method per address is exposed as a future-facing setting, but 1.0.0 uses one base shipping method and distributes the calculated charge across shipment rows.
- Customer order email and invoice insertion are settings-ready but not injected in 1.0.0; customer and admin order pages display shipment records.
- Admin destination editing and product reassignment controls are not enabled in 1.0.0. Admins can update shipment status, tracking, comments, and print packing slips.

## Untested Areas

- Third-party checkout replacements.
- Third-party themes that remove the standard checkout IDs.
- Third-party shipping extensions with custom side effects during quote calculation.
- Multi-store checkout behavior beyond standard OpenCart session handling.
- Live payment gateway callback behavior; persistence is idempotent by `order_id`.
