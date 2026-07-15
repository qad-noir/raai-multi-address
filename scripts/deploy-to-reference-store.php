<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$store = $root . DIRECTORY_SEPARATOR . 'oc_store_project';
$target = $store . DIRECTORY_SEPARATOR . 'extension' . DIRECTORY_SEPARATOR . 'raai_multi_address';
$dry_run = in_array('--dry-run', $argv, true);
$roots = ['install.json', 'README.md', 'LICENSE', 'admin', 'catalog', 'system'];

if (!is_dir($store)) {
	fwrite(STDERR, 'Reference store not found: ' . $store . PHP_EOL);
	exit(1);
}

$files = [];

foreach ($roots as $entry) {
	$source = $root . DIRECTORY_SEPARATOR . $entry;

	if (is_file($source)) {
		$files[$source] = $target . DIRECTORY_SEPARATOR . $entry;
		continue;
	}

	if (!is_dir($source)) {
		continue;
	}

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		if ($file->isFile()) {
			$relative = substr($file->getPathname(), strlen($root) + 1);
			$files[$file->getPathname()] = $target . DIRECTORY_SEPARATOR . $relative;
		}
	}
}

foreach ($files as $source => $destination) {
	$display = str_replace($root . DIRECTORY_SEPARATOR, '', $source);
	echo ($dry_run ? '[DRY-RUN] ' : '') . $display . ' -> ' . str_replace($store . DIRECTORY_SEPARATOR, 'oc_store_project' . DIRECTORY_SEPARATOR, $destination) . PHP_EOL;

	if ($dry_run) {
		continue;
	}

	$destination_dir = dirname($destination);

	if (!is_dir($destination_dir)) {
		mkdir($destination_dir, 0777, true);
	}

	copy($source, $destination);
}

echo ($dry_run ? 'Dry run complete.' : 'Deployment complete.') . PHP_EOL;
