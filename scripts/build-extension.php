<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$package = $root . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'raai_multi_address.ocmod.zip';
$required = [
	'install.json',
	'admin/controller/module/multi_address.php',
	'admin/model/raai_multi_address/install.php',
	'catalog/model/checkout/multi_address.php',
	'system/library/raai_multi_address/allocation.php'
];
$package_roots = ['install.json', 'LICENSE', 'admin', 'catalog', 'system'];
$prohibited = ['oc_store_project/', '.git/', '.env', 'tests/', 'docs/', 'scripts/', 'build/'];

foreach ($required as $path) {
	if (!is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
		fwrite(STDERR, 'Missing required file: ' . $path . PHP_EOL);
		exit(1);
	}
}

$php_files = [];
$lint_roots = ['admin', 'catalog', 'system', 'tests', 'scripts'];

foreach ($lint_roots as $lint_root) {
	$full = $root . DIRECTORY_SEPARATOR . $lint_root;

	if (!is_dir($full)) {
		continue;
	}

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		if ($file->isFile() && substr($file->getPathname(), -4) === '.php') {
			$php_files[] = $file->getPathname();
		}
	}
}

foreach ($php_files as $file) {
	$command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file);
	exec($command, $output, $code);

	if ($code !== 0) {
		echo implode(PHP_EOL, $output) . PHP_EOL;
		exit($code);
	}
}

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run.php'), $test_code);

if ($test_code !== 0) {
	exit($test_code);
}

if (!is_dir(dirname($package))) {
	mkdir(dirname($package), 0777, true);
}

if (is_file($package)) {
	unlink($package);
}

$zip = new ZipArchive();

if ($zip->open($package, ZipArchive::CREATE) !== true) {
	fwrite(STDERR, 'Unable to create package: ' . $package . PHP_EOL);
	exit(1);
}

foreach ($package_roots as $entry) {
	$full = $root . DIRECTORY_SEPARATOR . $entry;

	if (is_file($full)) {
		$zip->addFile($full, $entry);
		continue;
	}

	if (!is_dir($full)) {
		continue;
	}

	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));

	foreach ($files as $file) {
		if (!$file->isFile()) {
			continue;
		}

		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		foreach ($prohibited as $blocked) {
			if (str_starts_with($relative, $blocked) || $relative === rtrim($blocked, '/')) {
				fwrite(STDERR, 'Prohibited package file: ' . $relative . PHP_EOL);
				$zip->close();
				exit(1);
			}
		}

		$zip->addFile($file->getPathname(), $relative);
	}
}

$zip->close();

$zip = new ZipArchive();
$zip->open($package);
$tree = [];

for ($i = 0; $i < $zip->numFiles; $i++) {
	$name = $zip->getNameIndex($i);
	$tree[] = $name;

	foreach ($prohibited as $blocked) {
		if (str_starts_with($name, $blocked) || $name === rtrim($blocked, '/')) {
			fwrite(STDERR, 'Prohibited file found in package: ' . $name . PHP_EOL);
			$zip->close();
			exit(1);
		}
	}
}

$zip->close();
sort($tree);

echo 'Built package: ' . $package . PHP_EOL;
echo 'Package tree:' . PHP_EOL;

foreach ($tree as $name) {
	echo ' - ' . $name . PHP_EOL;
}
