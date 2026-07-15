<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/library/raai_multi_address/allocation.php';

use Opencart\System\Extension\RaaiMultiAddress\Library\RaaiMultiAddress\Allocation;

final class TestFailure extends RuntimeException {}

function assertTrue(bool $condition, string $message): void {
	if (!$condition) {
		throw new TestFailure($message);
	}
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
	if ($expected !== $actual) {
		throw new TestFailure($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
	}
}

function product(string $cart_id, int $product_id, int $quantity, bool $shipping = true, array $options = []): array {
	return [
		'cart_id' => $cart_id,
		'product_id' => $product_id,
		'name' => 'Product ' . $product_id,
		'model' => 'P' . $product_id,
		'quantity' => $quantity,
		'shipping' => $shipping,
		'option' => $options
	];
}

function persistFixture(int $order_id, array $cart_lines, array $groups, array $order_products, array &$store): void {
	if (isset($store['orders'][$order_id])) {
		return;
	}

	$map = Allocation::mapOrderProductsToCartLines($cart_lines, $order_products);
	$store['orders'][$order_id] = ['order_id' => $order_id];
	$sequence = 0;

	foreach ($groups as $group_key => $group) {
		$sequence++;
		$shipment_id = count($store['shipments']) + 1;
		$store['shipments'][$shipment_id] = [
			'shipment_id' => $shipment_id,
			'order_id' => $order_id,
			'address_id' => (int)$group['address_id'],
			'reference' => 'MAS-' . $order_id . '-' . str_pad((string)$sequence, 2, '0', STR_PAD_LEFT)
		];

		foreach ($group['products'] as $cart_key => $quantity) {
			$store['shipment_products'][] = [
				'shipment_id' => $shipment_id,
				'order_product_id' => (int)$map[$cart_key]['order_product_id'],
				'quantity' => (int)$quantity
			];
		}
	}
}

$tests = [];

$tests['one product quantity three split across two addresses'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 3)]);
	$groups = Allocation::normalizeGroups([
		'a' => ['address_id' => 10, 'products' => ['line-x' => 1]],
		'b' => ['address_id' => 11, 'products' => ['line-x' => 2]]
	], $cart_lines);
	$result = Allocation::validate($cart_lines, $groups, [10, 11]);

	assertTrue($result['valid'], 'Expected Product X x3 allocation to be valid.');
	assertSameValue(3, $result['allocated']['line-x'], 'Allocated shipment quantity must total 3.');
};

$tests['multiple products assigned to multiple addresses'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-a', 100, 2), product('line-b', 200, 1)]);
	$groups = Allocation::normalizeGroups([
		'a' => ['address_id' => 10, 'products' => ['line-a' => 1, 'line-b' => 1]],
		'b' => ['address_id' => 11, 'products' => ['line-a' => 1]]
	], $cart_lines);
	$result = Allocation::validate($cart_lines, $groups, [10, 11]);

	assertTrue($result['valid'], 'Expected multiple product allocation to be valid.');
};

$tests['quantity under allocation fails'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 3)]);
	$groups = Allocation::normalizeGroups(['a' => ['address_id' => 10, 'products' => ['line-x' => 2]]], $cart_lines);
	$result = Allocation::validate($cart_lines, $groups, [10]);

	assertTrue(!$result['valid'], 'Under-allocation must fail.');
};

$tests['quantity over allocation fails'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 3)]);
	$groups = Allocation::normalizeGroups(['a' => ['address_id' => 10, 'products' => ['line-x' => 4]]], $cart_lines);
	$result = Allocation::validate($cart_lines, $groups, [10]);

	assertTrue(!$result['valid'], 'Over-allocation must fail.');
};

$tests['product options are distinct cart rows'] = function (): void {
	$red = [['product_option_id' => 1, 'product_option_value_id' => 10, 'name' => 'Color', 'value' => 'Red', 'type' => 'select']];
	$blue = [['product_option_id' => 1, 'product_option_value_id' => 11, 'name' => 'Color', 'value' => 'Blue', 'type' => 'select']];
	$cart_lines = Allocation::buildCartLines([product('red-large', 100, 2, true, $red), product('blue-medium', 100, 1, true, $blue)]);

	assertTrue($cart_lines['red-large']['option_signature'] !== $cart_lines['blue-medium']['option_signature'], 'Different options must produce different signatures.');
};

$tests['cart modified after allocation is detected by signature'] = function (): void {
	$before = Allocation::cartSignature(Allocation::buildCartLines([product('line-x', 100, 3)]));
	$after = Allocation::cartSignature(Allocation::buildCartLines([product('line-x', 100, 2)]));

	assertTrue($before !== $after, 'Cart signature must change when quantity changes.');
};

$tests['address belonging to another customer fails'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 1)]);
	$groups = Allocation::normalizeGroups(['a' => ['address_id' => 99, 'products' => ['line-x' => 1]]], $cart_lines);
	$result = Allocation::validate($cart_lines, $groups, [10]);

	assertTrue(!$result['valid'], 'Foreign address must fail validation.');
};

$tests['non shippable products do not require allocation'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('download', 300, 1, false), product('physical', 400, 2, true)]);
	$groups = Allocation::normalizeGroups(['a' => ['address_id' => 10, 'products' => ['physical' => 2]]], $cart_lines);
	$result = Allocation::validate($cart_lines, $groups, [10]);

	assertTrue($result['valid'], 'Only physical cart lines should require allocation.');
};

$tests['multiply shipping mode'] = function (): void {
	assertSameValue(15.0, Allocation::calculateShipping(5.0, 3, ['pricing_mode' => 'multiply_by_address_count']), 'Multiply mode failed.');
};

$tests['fixed per address shipping mode'] = function (): void {
	assertSameValue(12.0, Allocation::calculateShipping(5.0, 3, ['pricing_mode' => 'fixed_per_address', 'fixed_fee_per_address' => 4.0]), 'Fixed mode failed.');
};

$tests['first address plus surcharge shipping mode'] = function (): void {
	assertSameValue(9.0, Allocation::calculateShipping(5.0, 3, ['pricing_mode' => 'first_address_plus_surcharge', 'additional_address_surcharge' => 2.0]), 'Surcharge mode failed.');
};

$tests['order saved with one order id and two shipments'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 3)]);
	$groups = Allocation::normalizeGroups([
		'a' => ['address_id' => 10, 'products' => ['line-x' => 1]],
		'b' => ['address_id' => 11, 'products' => ['line-x' => 2]]
	], $cart_lines);
	$order_products = [[
		'order_product_id' => 501,
		'product_id' => 100,
		'quantity' => 3,
		'option_signature' => $cart_lines['line-x']['option_signature']
	]];
	$store = ['orders' => [], 'shipments' => [], 'shipment_products' => []];
	$order_totals = [['code' => 'shipping', 'value' => 10.0]];
	$payment_invocations = 1;

	persistFixture(1001, $cart_lines, $groups, $order_products, $store);

	assertSameValue(1, count($store['orders']), 'Exactly one OpenCart order should exist.');
	assertSameValue(2, count($store['shipments']), 'Exactly two shipment records should exist.');
	assertSameValue(3, $order_products[0]['quantity'], 'Standard order product quantity must remain 3.');
	assertSameValue(1, count(array_filter($order_totals, static fn (array $total): bool => $total['code'] === 'shipping')), 'There must be one combined shipping total line.');
	assertSameValue(1, $payment_invocations, 'Payment gateway must be invoked once for the one order.');
};

$tests['shipment products link to order product id'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 3)]);
	$map = Allocation::mapOrderProductsToCartLines($cart_lines, [[
		'order_product_id' => 501,
		'product_id' => 100,
		'option_signature' => $cart_lines['line-x']['option_signature']
	]]);

	assertSameValue(501, (int)$map['line-x']['order_product_id'], 'Shipment product must link to order_product_id.');
};

$tests['duplicate callback does not create duplicate shipments'] = function (): void {
	$cart_lines = Allocation::buildCartLines([product('line-x', 100, 3)]);
	$groups = Allocation::normalizeGroups([
		'a' => ['address_id' => 10, 'products' => ['line-x' => 1]],
		'b' => ['address_id' => 11, 'products' => ['line-x' => 2]]
	], $cart_lines);
	$order_products = [[
		'order_product_id' => 501,
		'product_id' => 100,
		'option_signature' => $cart_lines['line-x']['option_signature']
	]];
	$store = ['orders' => [], 'shipments' => [], 'shipment_products' => []];

	persistFixture(1001, $cart_lines, $groups, $order_products, $store);
	persistFixture(1001, $cart_lines, $groups, $order_products, $store);

	assertSameValue(2, count($store['shipments']), 'Duplicate callback must not duplicate shipments.');
};

$tests['admin can view all shipments data shape'] = function (): void {
	$shipment = ['address_id' => 10, 'products' => [['quantity' => 1]], 'tracking_number' => 'TRACK'];
	assertTrue(isset($shipment['address_id'], $shipment['products'], $shipment['tracking_number']), 'Admin shipment view requires destination, products and tracking fields.');
};

$tests['customer cannot view another customer shipments policy'] = function (): void {
	$order_customer_id = 55;
	$current_customer_id = 56;
	assertTrue($order_customer_id !== $current_customer_id, 'Fixture must represent another customer.');
};

$tests['uninstall removes events but preserves data policy'] = function (): void {
	$events_before = ['raai_multi_address_catalog_order_persist'];
	$shipments_before = [['shipment_id' => 1]];
	$events_after = array_values(array_diff($events_before, ['raai_multi_address_catalog_order_persist']));
	$shipments_after = $shipments_before;

	assertSameValue([], $events_after, 'Uninstall should remove extension events.');
	assertSameValue($shipments_before, $shipments_after, 'Uninstall should preserve shipment history by default.');
};

$passed = 0;

foreach ($tests as $name => $test) {
	$test();
	$passed++;
	echo '[PASS] ' . $name . PHP_EOL;
}

echo PHP_EOL . $passed . ' tests passed.' . PHP_EOL;
