<?php
/**
 * Builds the packages of every OpenCart branch from adapters/ and the shared core:
 *   php bin/build.php            (needs the zip extension)
 * dist/build/<package>/ — the tree as it goes into the store,
 * dist/<zip>             — the package for the store owner.
 */
$root = dirname(__DIR__);
$version = '0.1.0';
// 'shared': files of adapters/shared/ → their place in the package. 'route': where the
// extension's own files live (payment/ up to 2.2, extension/payment/ from 2.3).
$packages = array(
	'oc15' => array('zip' => 'coinspay-opencart-1.5.zip', 'base' => 'upload/', 'core' => 'upload/system/library/coinspay', 'old_langs' => true, 'route' => 'payment',
		'shared' => array()),
	'oc20' => array('zip' => 'coinspay-opencart-2.0-2.2.ocmod.zip', 'base' => 'upload/', 'core' => 'upload/system/library/coinspay', 'old_langs' => true, 'route' => 'payment',
		'shared' => array(
			'admin-bs3.tpl' => 'upload/admin/view/template/payment/coinspay.tpl',
			'catalog-bs3.tpl' => 'upload/catalog/view/theme/default/template/payment/coinspay.tpl',
		)),
	'oc23' => array('zip' => 'coinspay-opencart-2.3.ocmod.zip', 'base' => 'upload/', 'core' => 'upload/system/library/coinspay', 'old_langs' => false, 'route' => 'extension/payment',
		'shared' => array(
			'admin-bs3.tpl' => 'upload/admin/view/template/extension/payment/coinspay.tpl',
			'catalog-bs3.tpl' => 'upload/catalog/view/theme/default/template/extension/payment/coinspay.tpl',
		)),
	'oc30' => array('zip' => 'coinspay-opencart-3.0.ocmod.zip', 'base' => 'upload/', 'core' => 'upload/system/library/coinspay', 'old_langs' => false, 'route' => 'extension/payment',
		'shared' => array()),
	// OpenCart 4 makes the folder from the zip name: extension/coinspay/.
	'oc4' => array('zip' => 'coinspay.ocmod.zip', 'base' => '', 'core' => 'system/library/coinspay', 'old_langs' => false, 'route' => 'payment',
		'shared' => array()),
);

function coinspay_copy($from, $to) {
	if (is_dir($from)) {
		if (!is_dir($to)) {
			mkdir($to, 0777, true);
		}
		foreach (scandir($from) as $name) {
			if ($name !== '.' && $name !== '..') {
				coinspay_copy($from . '/' . $name, $to . '/' . $name);
			}
		}
		return;
	}
	if (!is_dir(dirname($to))) {
		mkdir(dirname($to), 0777, true);
	}
	copy($from, $to);
}

function coinspay_remove($path) {
	if (is_dir($path)) {
		foreach (scandir($path) as $name) {
			if ($name !== '.' && $name !== '..') {
				coinspay_remove($path . '/' . $name);
			}
		}
		rmdir($path);
	} elseif (file_exists($path)) {
		unlink($path);
	}
}

function coinspay_files($dir, $prefix = '') {
	$files = array();
	foreach (scandir($dir) as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}
		$path = $dir . '/' . $name;
		if (is_dir($path)) {
			$files = array_merge($files, coinspay_files($path, $prefix . $name . '/'));
		} else {
			$files[$prefix . $name] = $path;
		}
	}
	return $files;
}

$built = 0;
foreach ($packages as $name => $package) {
	$source = $root . '/adapters/' . $name;
	if (!is_dir($source)) {
		continue;
	}
	$tree = $root . '/dist/build/' . $name;
	coinspay_remove($tree);
	coinspay_copy($source, $tree);
	coinspay_copy($root . '/core', $tree . '/' . $package['core']);
	foreach ($package['shared'] as $from => $to) {
		coinspay_copy($root . '/adapters/shared/' . $from, $tree . '/' . $to);
	}
	// Installation guides at the root of the package (outside upload/: the installers skip them).
	foreach (array('install-en.md', 'install-ru.md') as $guide) {
		coinspay_copy($root . '/docs/' . $guide, $tree . '/' . $guide);
	}
	// OpenCart's own language files: the heading of the extension in its lists, the method title.
	foreach (array('admin', 'catalog') as $side) {
		foreach (array('en-gb', 'ru-ru') as $lang) {
			coinspay_copy($root . '/adapters/shared/lang/' . $side . '/' . $lang . '.php', $tree . '/' . $package['base'] . $side . '/language/' . $lang . '/' . $package['route'] . '/coinspay.php');
		}
	}

	// OpenCart up to 2.1 names language folders english/russian, later ones en-gb/ru-ru.
	if ($package['old_langs']) {
		foreach (array('admin', 'catalog') as $side) {
			foreach (array('en-gb' => 'english', 'ru-ru' => 'russian') as $new => $old) {
				$dir = $tree . '/' . $package['base'] . $side . '/language/' . $new;
				if (is_dir($dir)) {
					coinspay_copy($dir, $tree . '/' . $package['base'] . $side . '/language/' . $old);
				}
			}
		}
	}

	if (!class_exists('ZipArchive')) {
		fwrite(STDERR, "No zip extension: only dist/build/$name made\n");
		continue;
	}
	$target = $root . '/dist/' . $package['zip'];
	if (file_exists($target)) {
		unlink($target);
	}
	$zip = new ZipArchive();
	if ($zip->open($target, ZipArchive::CREATE) !== true) {
		fwrite(STDERR, "Cannot write $target\n");
		exit(1);
	}
	$files = coinspay_files($tree);
	ksort($files);
	foreach ($files as $relative => $path) {
		$zip->addFile($path, $relative);
	}
	$zip->close();
	printf("%s: %d files, %.1f KB\n", $package['zip'], count($files), filesize($target) / 1024);
	$built++;
}
if (!$built) {
	echo "nothing built\n";
}
