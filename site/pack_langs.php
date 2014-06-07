<?php

/**
 * This file can be used to automate the creation of language packages
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.0
 *
 */

define('BASEDIR', __DIR__);
define('LANGSDIR', BASEDIR . '/themes/default/languages');

$package_version = '0.1';

$elk_version = '1.0 beta 2 - 1.0.99';
$package_info_raw = '<?xml version="1.0"?>
<!DOCTYPE package-info SYSTEM "http://www.simplemachines.org/xml/package-info">
<package-info xmlns="http://www.simplemachines.org/xml/package-info" xmlns:smf="http://www.simplemachines.org/">
	<!--
/**
 * @name      ElkArte Forum {ucase_language} translation
 * @copyright ElkArte Forum {ucase_language} translation contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 */
 -->
	<id>elk_{language}_contribs:elk_{language}</id>
	<name>ElkArte Forum {ucase_language} translation</name>
	<version>{version}</version>
	<type>modification</type>
	<install for="{install_range}">
		<require-dir name="{language}" destination="LANGUAGEDIR" />
		<require-file name="agreement.{language}.txt" destination="BOARDDIR" />
	</install>
	<uninstall for="{install_range}">
		<remove-dir name="LANGUAGEDIR/{language}" />
		<remove-dir name="BOARDDIR/agreement.{language}.txt" />
	</uninstall>
</package-info>';

$langs = glob(LANGSDIR . '/*', GLOB_ONLYDIR);

foreach ($langs as $lang)
{
	create_package_info($lang, $package_version, $elk_version, $package_info_raw);
	create_zip_package($lang, BASEDIR . '/' . basename($lang) . '_' . strtr($package_version, array('.' => '-')) . '.zip');
}

function create_package_info($dir, $package_version, $elk_version, $package_info_raw)
{
	$replacements = array(
		'{language}' => basename($dir),
		'{ucase_language}' => ucfirst(basename($dir)),
		'{install_range}' => $elk_version,
		'{version}' => $package_version,
	);
	@unlink($dir . '/package-info.xml');
	return file_put_contents($dir . '/package-info.xml', strtr($package_info_raw, $replacements), LOCK_EX);
}

function create_zip_package($dir, $destination)
{
	$zip = new ZipArchive();
	if ($zip->open($destination, ZipArchive::OVERWRITE) !== true)
	{
		echo 'Failed to create ' . $destination . ' with code ' . $ret;
		return false;
	}
	else
	{
		$zip->addGlob($dir . '/*.php', GLOB_BRACE, array('add_path' => '/' . basename($dir) . '/', 'remove_all_path' => true));
		$zip->addFile($dir . '/package-info.xml', 'package-info.xml');
		$zip->addFile($dir . '/agreement.txt', 'agreement.' . basename($dir) . '.txt');
		$zip->close();
		return true;
	}
}