<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$store = $root . DIRECTORY_SEPARATOR . 'oc_store_project';
$target = realpath($store . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'raai_multi_address');
$expected_parent = realpath($store . DIRECTORY_SEPARATOR . 'extension');

if (!$target) {
	echo 'No deployed extension directory found.' . PHP_EOL;
	exit(0);
}

if (!$expected_parent || dirname($target) !== $expected_parent || basename($target) !== 'raai_multi_address') {
	fwrite(STDERR, 'Refusing to remove unexpected path: ' . $target . PHP_EOL);
	exit(1);
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $file) {
	if ($file->isDir()) {
		rmdir($file->getPathname());
	} else {
		unlink($file->getPathname());
	}
}

rmdir($target);
echo 'Removed deployed extension directory: ' . $target . PHP_EOL;
