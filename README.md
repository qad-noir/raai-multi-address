# Multi-Address Shipping

OpenCart 4 extension package: `raai_multi_address`

Version 1.0.0 implements registered-customer multi-address shipping for one OpenCart checkout order. Product quantities can be split across multiple customer addresses, while OpenCart still creates one order, one payment flow, and standard `order_product` rows with the full purchased quantities.

## Supported OpenCart Version

Built and inspected against OpenCart 4.0.2.3.

## Order Policy

Version 1.0.0 is fixed to:

```text
single_order_multiple_shipments
```

The extension does not create one OpenCart order per address.

## Build

```bash
php scripts/build-extension.php
```

The package is written to:

```text
build/raai_multi_address.ocmod.zip
```

## Tests

```bash
php tests/run.php
```

The test harness validates the Product X x3 split into Address A x1 and Address B x2, option-distinct cart rows, under/over allocation failures, shipping pricing modes, one-order persistence shape, and duplicate callback idempotence.

## Local Reference Store Deployment

Dry run:

```bash
php scripts/deploy-to-reference-store.php --dry-run
```

Copy extension files into `oc_store_project/`:

```bash
php scripts/deploy-to-reference-store.php
```

Cleanup deployed extension files:

```bash
php scripts/cleanup-reference-store.php
```

The reference store is excluded from Git and from the package.

## Installation

1. Upload `build/raai_multi_address.ocmod.zip` in Extensions > Installer.
2. Install the extension.
3. Go to Extensions > Extensions > Modules.
4. Install and enable Multi-Address Shipping.
5. Configure shipping pricing mode and limits.
