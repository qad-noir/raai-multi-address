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

Prerequisites:

- Git
- PHP CLI available as `php`
- OpenCart 4 admin access

Clone the repository:

```cmd
git clone https://github.com/qad-noir/raai-multi-address.git raai_multi_address
cd raai_multi_address
```

Build the installable OpenCart package:

```cmd
php scripts\build-extension.php
```

The package is written to:

```text
build/raai_multi_address.ocmod.zip
```

If `php` is not available in Command Prompt, run the build script with the full path to your local PHP executable:

```cmd
"C:\path\to\php.exe" scripts\build-extension.php
```

For example, a WAMP install might look like:

```cmd
C:\wamp64\bin\php\php8.2.0\php.exe scripts\build-extension.php
```

Adjust the path to match the PHP version and install location on your machine.

## Tests

```cmd
php tests\run.php
```

The test harness validates the Product X x3 split into Address A x1 and Address B x2, option-distinct cart rows, under/over allocation failures, shipping pricing modes, one-order persistence shape, and duplicate callback idempotence.

## Installation

1. Build the zip locally with `php scripts\build-extension.php`.
2. In OpenCart admin, go to Extensions > Installer.
3. Upload `build/raai_multi_address.ocmod.zip`.
4. Go to Extensions > Extensions.
5. Select Modules from the extension type dropdown.
6. Install and enable Multi-Address Shipping.
7. Configure shipping pricing mode and limits.

For updates to an existing local test store, uninstall the module first, then upload and install the newly built zip so OpenCart re-registers extension files and events.

## Updating From Git

From the cloned repository:

```cmd
git pull
php scripts\build-extension.php
```

Then reinstall the newly built `build\raai_multi_address.ocmod.zip` through OpenCart admin.
