<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0
 */

// We need the Settings.php info for database stuff.
if (file_exists(dirname(__FILE__) . '/Settings.php'))
	require_once(dirname(__FILE__) . '/Settings.php');

// Initialize everything and load the language files.
initialize_inputs();

load_language_data();

if (isset($_POST['submit']))
	action_set_settings();
if (isset($_POST['remove_hooks']))
	action_remove_hooks();
if (isset($_GET['delete']))
	action_deleteScript();

template_initialize();

show_settings();

template_show_footer();

function initialize_inputs()
{
	global $smcFunc, $db_connection, $sourcedir, $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_type, $context;

	// Turn off magic quotes runtime and enable error reporting.
	@set_magic_quotes_runtime(0);
	error_reporting(E_ALL);
	if (ini_get('session.save_handler') == 'user')
		ini_set('session.save_handler', 'files');
	@session_start();

	// Slashes as soo old-fashion...
	if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() != 0)
	{
		foreach ($_POST as $k => $v)
			if (is_array($v))
				foreach ($v as $k2 => $v2)
					$_POST[$k][$k2] = stripslashes($v2);
			else
				$_POST[$k] = stripslashes($v);
	}

	foreach ($_POST as $k => $v)
	{
		if (is_array($v))
			foreach ($v as $k2 => $v2)
				$_POST[$k][$k2] = addcslashes($v2, '\'');
		else
			$_POST[$k] = addcslashes($v, '\'');
	}

	$db_connection = false;
	if (empty($smcFunc))
			$smcFunc = array();

	if (isset($sourcedir) && file_exists($sourcedir))
	{
		define('ELKARTE', 1);

		// Default the database type to MySQL.
		if (empty($db_type) || !file_exists($sourcedir . '/database/Db-' . $db_type . '.subs.php'))
			$db_type = 'mysql';

		require_once($sourcedir . '/Load.php');
		// require_once($librarydir . '/Auth.subs.php');

		require_once($sourcedir . '/database/Db-' . $db_type . '.subs.php');
		require_once($sourcedir . '/database/DbExtra-' . $db_type . '.php');
		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('non_fatal' => true));
		db_extra_init();
	}
}

/**
 * Display the current settings.
 *
 * This function reads Settings.php, and if it can connect, the database settings.
 */
function show_settings()
{
	global $txt, $smcFunc, $db_connection, $db_type, $db_name, $db_prefix, $context;

	// Check to make sure Settings.php exists!
	if (file_exists(dirname(__FILE__) . '/Settings.php'))
		$settingsArray = file(dirname(__FILE__) . '/Settings.php');
	else
		$settingsArray = array();

	if (count($settingsArray) == 1)
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);

	$settings = array();
	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		$settingsArray[$i] = rtrim(stripslashes($settingsArray[$i]));

		if (isset($settingsArray[$i][0]) && $settingsArray[$i][0] != '.')
		{
			preg_match('~^[$]([a-zA-Z_]+)\s*=\s*(?:(["\'])(?:(.*?)["\'])(?:\\2)?|(.*?)(?:\\2)?);~', $settingsArray[$i], $match);
			if (isset($match[3]))
			{
				if ($match[3] == 'dirname(__FILE__)')
					$settings[$match[1]] = dirname(__FILE__);
				elseif ($match[3] == 'dirname(__FILE__) . \'/Sources\'')
					$settings[$match[1]] = dirname(__FILE__) . '/Sources';
				elseif ($match[3] == 'dirname(__FILE__) . \'/sources\'')
					$settings[$match[1]] = dirname(__FILE__) . '/sources';
				elseif ($match[3] == '$boarddir . \'/Sources\'')
					$settings[$match[1]] = $settings['boarddir'] . '/Sources';
				elseif ($match[3] == '$boarddir . \'/sources\'')
					$settings[$match[1]] = $settings['boarddir'] . '/sources';
				elseif ($match[3] == 'dirname(__FILE__) . \'/cache\'')
					$settings[$match[1]] = dirname(__FILE__) . '/cache';
				else
					$settings[$match[1]] = $match[3];
			}
		}
	}

	if ($db_connection == true)
	{
		$request = $smcFunc['db_query'](true, '
			SELECT DISTINCT variable, value
			FROM {db_prefix}settings',
			array(
				'db_error_skip' => true
			),
			$db_connection
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$settings[$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);

		// Load all the themes.
		$request = $smcFunc['db_query'](true, '
			SELECT variable, value, id_theme
			FROM {db_prefix}themes
			WHERE id_member = 0
				AND variable IN ({array_string:variables})',
			array(
				'variables' => array('theme_dir', 'theme_url', 'images_url', 'name'),
				'db_error_skip' => true
			)
		);

		$theme_settings = array();
		while ($row = $smcFunc['db_fetch_row']($request))
			$theme_settings[$row[2]][$row[0]] = $row[1];
		$smcFunc['db_free_result']($request);

		$show_db_settings = $request;
	}
	else
		$show_db_settings = false;

	$known_settings = array(
		'critical_settings' => array(
			'maintenance' => array('flat', 'int', 2),
			'language' => array('flat', 'string', 'english'),
			'cookiename' => array('flat', 'string', 'ELKCookie' . (!empty($db_name) ? abs(crc32($db_name . preg_replace('~[^A-Za-z0-9_$]~', '', $db_prefix)) % 1000) : '20')),
			'queryless_urls' => array('db', 'int', 1),
			'enableCompressedOutput' => array('db', 'int', 1),
			'databaseSession_enable' => array('db', 'int', 1),
			'theme_default' => array('db', 'int', 1),
		),
		'database_settings' => array(
			'db_server' => array('flat', 'string', 'localhost'),
			'db_name' => array('flat', 'string'),
			'db_user' => array($db_type == 'sqlite' ? 'hidden' : 'flat', 'string'),
			'db_passwd' => array($db_type == 'sqlite' ? 'hidden' : 'flat', 'string'),
			'ssi_db_user' => array($db_type == 'sqlite' ? 'hidden' : 'flat', 'string'),
			'ssi_db_passwd' => array($db_type == 'sqlite' ? 'hidden' : 'flat', 'string'),
			'db_prefix' => array('flat', 'string'),
			'db_persist' => array('flat', 'int', 1),
		),
		'path_url_settings' => array(
			'boardurl' => array('flat', 'string'),
			'boarddir' => array('flat', 'string'),
			'sourcedir' => array('flat', 'string'),
			'cachedir' => array('flat', 'string'),
			'librarydir' => array('flat', 'string'),
			'controllerdir' => array('flat', 'string'),
			'attachmentUploadDir' => array('db', 'array_string'),
			'avatar_url' => array('db', 'string'),
			'avatar_directory' => array('db', 'string'),
			'smileys_url' => array('db', 'string'),
			'smileys_dir' => array('db', 'string'),
		),
		'theme_path_url_settings' => array(),
	);

	// !!! Multiple Attachment Dirs not supported as yet, so hide this field
	// if (empty($known_settings['path_url_settings']['attachmentUploadDir']))
	// unset($known_settings['path_url_settings']['attachmentUploadDir']);

	// Let's assume we don't want to change the current theme
	$settings['theme_default'] = 0;

	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
	$url = 'http://' . $host . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
	$known_settings['path_url_settings']['boardurl'][2] = $url;
	$known_settings['path_url_settings']['boarddir'][2] = dirname(__FILE__);

	if (file_exists(dirname(__FILE__) . '/sources'))
		$known_settings['path_url_settings']['sourcedir'][2] = realpath(dirname(__FILE__) . '/sources');

	if (file_exists(dirname(__FILE__) . '/cache'))
		$known_settings['path_url_settings']['cachedir'][2] = realpath(dirname(__FILE__) . '/cache');

	if (file_exists(dirname(__FILE__) . '/sources/subs'))
		$known_settings['path_url_settings']['librarydir'][2] = realpath(dirname(__FILE__) . '/sources/subs');

	if (file_exists(dirname(__FILE__) . '/sources/controllers'))
		$known_settings['path_url_settings']['controllerdir'][2] = realpath(dirname(__FILE__) . '/sources/controllers');

	if (file_exists(dirname(__FILE__) . '/avatars'))
	{
		$known_settings['path_url_settings']['avatar_url'][2] = $url . '/avatars';
		$known_settings['path_url_settings']['avatar_directory'][2] = realpath(dirname(__FILE__) . '/avatars');
	}

	if (file_exists(dirname(__FILE__) . '/smileys'))
	{
		$known_settings['path_url_settings']['smileys_url'][2] = $url . '/smileys';
		$known_settings['path_url_settings']['smileys_dir'][2] = realpath(dirname(__FILE__) . '/smileys');
	}

/*	if (file_exists(dirname(__FILE__) . '/themes/default'))
	{
		$known_settings['path_url_settings']['theme_url'][2] = $url . '/themes/default';
		$known_settings['path_url_settings']['images_url'][2] = $url . '/themes/default/images';
		$known_settings['path_url_settings']['theme_dir'][2] = realpath(dirname(__FILE__) . '/themes/default');
	}
*/

	if (!empty($theme_settings))
	{
		// Create the values for the themes.
		foreach ($theme_settings as $id => $theme)
		{
			$this_theme = ($pos = strpos($theme['theme_url'], '/themes/')) !== false ? substr($theme['theme_url'], $pos+8) : '';
			if (!empty($this_theme))
				$exist = file_exists(dirname(__FILE__) . '/themes/' . $this_theme);
			else
				$exist = false;

			$old_theme = ($pos = strpos($theme['theme_url'], '/Themes/')) !== false ? substr($theme['theme_url'], $pos+8) : '';
			$new_theme_exists = file_exists(dirname(__FILE__) . '/themes/' . $this_theme);

			$known_settings['theme_path_url_settings'] += array(
				'theme_'. $id.'_theme_url'=>array('theme', 'string', $exist && !empty($this_theme) ? $url . '/themes/' . $this_theme : $new_theme_exists && !empty($old_theme) ? $url . '/themes/' . $this_theme : null),
				'theme_'. $id.'_images_url'=>array('theme', 'string', $exist && !empty($this_theme) ? $url . '/themes/' . $this_theme . '/images' : $new_theme_exists && !empty($old_theme) ? $url . '/themes/' . $this_theme . '/images' : null),
				'theme_' . $id . '_theme_dir' => array('theme', 'string', $exist && !empty($this_theme) ? realpath(dirname(__FILE__) . '/themes/' . $this_theme) : $new_theme_exists && !empty($old_theme) ? realpath(dirname(__FILE__) . '/themes/' . $this_theme) : null),
			);
			$settings += array(
				'theme_' . $id . '_theme_url' => $theme['theme_url'],
				'theme_' . $id . '_images_url' => $theme['images_url'],
				'theme_' . $id . '_theme_dir' => $theme['theme_dir'],
			);

			$txt['theme_' . $id . '_theme_url'] = $theme['name'] . ' URL';
			$txt['theme_' . $id . '_images_url'] = $theme['name'] . ' Images URL';
			$txt['theme_' . $id . '_theme_dir'] = $theme['name'] . ' Directory';
		}
	}

	if ($db_connection == true)
	{
		$request = $smcFunc['db_list_tables']('', '
			{db_prefix}log_topics',
			array(
				'db_error_skip' => true,
			)
		);
		if ($request == true)
		{
			if ($smcFunc['db_num_rows']($request) == 1)
				list ($known_settings['database_settings']['db_prefix'][2]) = preg_replace('~log_topics$~', '', $smcFunc['db_fetch_row']($request));
			$smcFunc['db_free_result']($request);
		}
	}
	elseif (empty($show_db_settings))
	{
		echo '
			<div class="error_message" style="margin-bottom: 2ex;">
				', $txt['database_settings_hidden'], '
			</div>';
	}

	echo '
			<script type="text/javascript"><!-- // --><![CDATA[
				// Get the inner HTML of an element.
				var resetSettings = new Array();
				var settingsCounter = 0;
				function getInnerHTML(element)
				{
					return element.innerHTML;
				}

				function restoreAll()
				{
					for (var i=0;i<resetSettings.length;i++)
					{
						var elem = document.getElementById(resetSettings[i]);
						var val = elem.value;
						elem.value = getInnerHTML(document.getElementById(resetSettings[i] + \'_default\'));
						if (val != elem.value)
						elem.parentNode.parentNode.className += " changed";
					}
				}
			// ]]></script>

			<form action="', $_SERVER['PHP_SELF'], '" method="post">
				<div class="panel">';

	foreach ($known_settings as $settings_section => $section)
	{
		echo '
					<h2>', $txt[$settings_section], '</h2>
					<h3>', $txt[$settings_section . '_info'], '</h3>

					<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom: 3ex;">
						<tr>';

		foreach ($section as $setting => $info)
		{
			if ($info[0] == 'hidden')
				continue;

			if ($info[0] != 'flat' && empty($show_db_settings))
				continue;

			echo '
							<td width="20%" valign="top" class="textbox" style="padding-bottom: 1ex;">
								<label', $info[1] != 'int' ? ' for="' . $setting . '"' : '', '>', $txt[$setting], ': '.
									( isset($txt[$setting . '_desc']) ? '<span class="smalltext">' . $txt[$setting . '_desc'] . '</span>' : '' ).'
								</label>', !isset($settings[$setting]) && $info[1] != 'check' ? '<br />
								' . $txt['no_value'] : '', '
							</td>
							<td style="padding-bottom: 1ex;">';

			if ($info[1] == 'int' || $info[1] == 'check')
			{
				for ($i = 0; $i <= $info[2]; $i++)
					echo '
								<label for="', $setting, $i, '"><input type="radio" name="', $info[0], 'settings[', $setting, ']" id="', $setting, $i, '" value="', $i, '"', isset($settings[$setting]) && $settings[$setting] == $i ? ' checked="checked"' : '', ' class="input_radio" /> ', $txt[$setting . $i], '</label><br />';
			}
			elseif ($info[1] == 'string')
			{
				echo '
								<input type="text" name="', $info[0], 'settings[', $setting, ']" id="', $setting, '" value="', isset($settings[$setting]) ? htmlspecialchars($settings[$setting]) : '', '" size="', $settings_section == 'path_url_settings' || $settings_section == 'theme_path_url_settings' ? '60" style="width: 80%;' : '30', '" class="input_text" />';

				if (isset($info[2]))
					echo '
								<div style="font-size: smaller;">', $txt['default_value'], ': &quot;<strong><a href="javascript:void(0);" id="', $setting, '_default" onclick="document.getElementById(\'', $setting, '\').value = ', $info[2] == '' ? '\'\';">' . $txt['recommend_blank'] : 'getInnerHTML(this);">' . $info[2], '</a></strong>&quot;.</div>',
								$info[2] == '' ? '' : ($setting != 'language' && $setting != 'cookiename' ? '
								<script type="text/javascript"><!-- // --><![CDATA[
									resetSettings[settingsCounter++] = "' . $setting . '"; // ]]></script>' : '');
			}
			elseif ($info[1] == 'array_string')
			{
				if (!is_array($settings[$setting]))
					$array_settings = @unserialize($settings[$setting]);
				if (!is_array($array_settings))
					$array_settings = array($settings[$setting]);

				$item = 1;
				foreach ($array_settings as $array_setting)
				{
					$suggested = false;
					echo '
								<input type="text" name="', $info[0], 'settings[', $setting, '_', $item, ']" id="', $setting, $item, '" value="', $array_setting, '" size="', $settings_section == 'path_url_settings' || $settings_section == 'theme_path_url_settings' ? '60" style="width: 80%;' : '30', '" class="input_text" />';

					$suggested = guess_attachments_directories($item, $array_setting);

					if (!empty($suggested))
					{
						echo '
								<div style="font-size: smaller;">', $txt['default_value'], ': &quot;<strong><a href="javascript:void(0);" id="', $setting, $item, '_default" onclick="document.getElementById(\'', $setting, $item, '\').value = ', $suggested[0] == '' ? '\'\';">' . $txt['recommend_blank'] : 'getInnerHTML(this);">' . $suggested[0], '</a></strong>&quot;.</div>',
								$suggested[0] == '' ? '' : '
								<script type="text/javascript"><!-- // --><![CDATA[
									resetSettings[settingsCounter++] = "' . $setting . $item . '"; // ]]></script>';

						for ($i = 1; $i < count($suggested); $i++)
							echo '
								<div style="font-size: smaller;">', $txt['other_possible_value'], ': &quot;<strong><a href="javascript:void(0);" id="', $setting, $item, '_default" onclick="document.getElementById(\'', $setting, $item, '\').value = ', $suggested[$i] == '' ? '\'\';">' . $txt['recommend_blank'] : 'getInnerHTML(this);">' . $suggested[$i], '</a></strong>&quot;.</div>';
					}
					else
						echo '
								<div style="font-size: smaller;">', $txt['no_default_value'], '</div>';

					$item++;
				}
			}

			echo '
							</td>
						</tr><tr>';
		}

		echo '
							<td colspan="2"></td>
						</tr>
					</table>';
	}

	echo '

					<div class="righttext" style="margin: 1ex;">';

	$failure = false;
	if (strpos(__FILE__, ':\\') !== 1)
	{
		// On linux, it's easy - just use is_writable!
		$failure |= !is_writable('Settings.php') && !chmod('Settings.php', 0777);
	}
	// Windows is trickier.  Let's try opening for r+...
	else
	{
		// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
		chmod(dirname(__FILE__) . '/' . 'Settings.php', 0777);
		$fp = @fopen(dirname(__FILE__) . '/' . 'Settings.php', 'r+');

		// Hmm, okay, try just for write in that case...
		if (!$fp)
			$fp = @fopen(dirname(__FILE__) . '/' . 'Settings.php', 'w');

		$failure |= !$fp;
		fclose($fp);
	}

	if ($failure)
		echo '
				<input type="submit" name="submit" value="', $txt['save_settings'], '" disabled="disabled" class="button_submit" /><br />', $txt['not_writable'];
	else
		echo '
				[<a href="javascript:restoreAll();">', $txt['restore_all_settings'], '</a>]
				<input type="submit" name="submit" value="', $txt['save_settings'], '" class="button_submit" />
				<input type="submit" name="remove_hooks" value="' . $txt['remove_hooks'] . '" class="button_submit" />';

	echo '
				</div>
				</div>
			</form>';
}

function guess_attachments_directories($id, $array_setting)
{
	global $smcFunc, $context;
	static $usedDirs;

	if (empty($userdDirs))
	{
		$usedDirs = array();
		$request = $smcFunc['db_query'](true, '
			SELECT {raw:select_tables}, file_hash
			FROM {db_prefix}attachments',
			array(
				'select_tables' => 'DISTINCT(id_folder), id_attach',
			)
		);

		if ($smcFunc['db_num_rows']($request) > 0)
		{
			while ($row = $smcFunc['db_fetch_assoc']($request))
				$usedDirs[$row['id_folder']] = $row;
			$smcFunc['db_free_result']($request);
		}
	}

	if ($basedir = opendir(dirname(__FILE__)))
	{
		$availableDirs = array();
		while (false !== ($file = readdir($basedir)))
			if ($file != '.' && $file != '..' && is_dir($file) && $file != 'sources'  && $file != 'packages' && $file != 'themes' && $file != 'cache' && $file != 'avatars' && $file != 'smileys')
				$availableDirs[] = $file;
	}

	// 1st guess: let's see if we can find a file...if there is at least one.
	if (isset($usedDirs[$id]))
		foreach ($availableDirs as $aDir)
			if (file_exists(dirname(__FILE__) . '/' . $aDir . '/' . $usedDirs[$id]['id_attach'] . '_' . $usedDirs[$id]['file_hash']))
				return array(dirname(__FILE__) . '/' . $aDir);

	// 2nd guess: directory name
	if (!empty($availableDirs))
		foreach ($availableDirs as $dirname)
			if (strrpos($array_setting, $dirname) == (strlen($array_setting) - strlen($dirname)))
				return array(dirname(__FILE__) . '/' . $dirname);

	// Doing it later saves in case the attached files have been deleted from the file system
	if (empty($usedDirs) && empty($availableDirs))
		return false;
	elseif (empty($usedDirs) && !empty($availableDirs))
	{
		$guesses = array();
		// attachments is the first guess
		foreach ($availableDirs as $dir)
			if ($dir == 'attachments')
				$guesses[] = dirname(__FILE__) . '/' . $dir;

		// all the others
		foreach ($availableDirs as $dir)
			if ($dir != 'attachments')
				$guesses[] = dirname(__FILE__) . '/' . $dir;
		return $guesses;
	}
}

function action_set_settings()
{
	global $smcFunc, $context;

	$db_updates = isset($_POST['dbsettings']) ? $_POST['dbsettings'] : array();
	$theme_updates = isset($_POST['themesettings']) ? $_POST['themesettings'] : array();
	$file_updates = isset($_POST['flatsettings']) ? $_POST['flatsettings'] : array();
	$attach_dirs = array();

	if (empty($db_updates['theme_default']))
		unset($db_updates['theme_default']);
	else
	{
		$db_updates['theme_guests'] = 1;
		$smcFunc['db_query'](true, '
			UPDATE {db_prefix}members
			SET {raw:theme_column} = 0',
			array(
				'theme_column' => 'id_theme',
			)
		);
	}

	$settingsArray = file(dirname(__FILE__) . '/Settings.php');
	$settings = array();
	for ($i = 0, $n = count($settingsArray); $i < $n; $i++)
	{
		$settingsArray[$i] = rtrim($settingsArray[$i]);

		// Remove the redirect...
		if ($settingsArray[$i] == 'if (file_exists(dirname(__FILE__) . \'/install.php\'))')
		{
			$settingsArray[$i] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			continue;
		}

		if (isset($settingsArray[$i][0]) && $settingsArray[$i][0] != '.' && preg_match('~^[$]([a-zA-Z_]+)\s*=\s*(?:(["\'])(.*?["\'])(?:\\2)?|(.*?)(?:\\2)?);~', $settingsArray[$i], $match) == 1)
			$settings[$match[1]] = stripslashes($match[3]);

		foreach ($file_updates as $var => $val)
		{
			if (strncasecmp($settingsArray[$i], '$' . $var, 1 + strlen($var)) == 0)
			{
				$comment = strstr($settingsArray[$i], '#');
				$settingsArray[$i] = '$' . $var . ' = \'' . $val . '\';' . ($comment != '' ? "\t\t" . $comment : '');
			}
		}
	}

	// Blank out the file - done to fix a oddity with some servers.
	$fp = @fopen(dirname(__FILE__) . '/Settings.php', 'w');
	fclose($fp);

	$fp = fopen(dirname(__FILE__) . '/Settings.php', 'r+');
	$lines = count($settingsArray);
	for ($i = 0; $i < $lines - 1; $i++)
	{
		// Don't just write a bunch of blank lines.
		if ($settingsArray[$i] != '' || $settingsArray[$i - 1] != '')
			fwrite($fp, $settingsArray[$i] . "\n");
	}
	fwrite($fp, $settingsArray[$i]);
	fclose($fp);

	// Make sure it works.
	require(dirname(__FILE__) . '/Settings.php');

	$setString = array();
	foreach ($db_updates as $var => $val)
		$setString[] = array($var, stripslashes($val));

	// Attachments dirs
	$attach_count = 1;
	foreach ($setString as $key => $value)
		if (strpos($value[0], 'attachmentUploadDir') == 0 && strpos($value[0], 'attachmentUploadDir') !== false)
		{
			$attach_dirs[$attach_count++] = $value[1];
			unset($setString[$key]);
		}

	// Only one dir...or maybe nothing at all
	if (count($attach_dirs) > 1)
	{
		$setString[] = array('attachmentUploadDir', @serialize($attach_dirs));
		// If we want to (re)set currentAttachmentUploadDir here is a good place
// 		foreach ($attach_dirs as $id => $attach_dir)
// 			if (is_dir($attach_dir) && is_writable($attach_dir))
// 				$setString[] = array('currentAttachmentUploadDir', $id + 1);
	}
	elseif (isset($attach_dirs[1]))
	{
		$setString[] = array('attachmentUploadDir', $attach_dirs[1]);
		$setString[] = array('currentAttachmentUploadDir', 0);
	}
	else
	{
		$setString[] = array('attachmentUploadDir', '');
		$setString[] = array('currentAttachmentUploadDir', 0);
	}

	if (!empty($setString))
		$smcFunc['db_insert']('replace',
			'{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string-65534'),
			$setString,
			array('variable')
		);

	$setString = array();
	foreach ($theme_updates as $var => $val)
	{
		// Extract the data
		preg_match('~theme_([\d]+)_(.+)~', $var, $match);
		if (empty($match[0]))
			continue;

		$setString[] = array($match[1], 0, $match[2], stripslashes($val));
	}

	if (!empty($setString))
		$smcFunc['db_insert']('replace',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string', 'value' => 'string-65534'),
			$setString,
			array('id_theme', 'id_member', 'variable')
		);
}

function action_remove_hooks()
{
	global $smcFunc;

	$smcFunc['db_query']('', '
		DELETE FROM {db_prefix}settings
		WHERE variable LIKE {string:variable}',
		array(
			'variable' => 'integrate_%'
		)
	);

	// Now fixing the cache...
	cache_put_data('modsettings', null, 0);
}

function action_deleteScript()
{
	@unlink(__FILE__);

	// Now just redirect to a blank.gif...
	header('Location: http://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/themes/default/images/blank.gif');
	exit;
}

function load_language_data()
{
	global $txt, $db_type;

	$txt['elkarte_repair_settings'] = 'Elkarte Settings Repair Tool';
	$txt['no_value'] = '<em style="font-weight: normal; color: red;">Value not found!</em>';
	$txt['default_value'] = 'Recommended value';
	$txt['other_possible_value'] = 'Other possible value';
	$txt['no_default_value'] = 'No recommended value';
	$txt['save_settings'] = 'Save Settings';
	$txt['remove_hooks'] = 'Remove all hooks';
	$txt['restore_all_settings'] = 'Restore all settings';
	$txt['not_writable'] = 'Settings.php cannot be written to by your webserver.  Please modify the permissions on this file to allow write access.';
	$txt['recommend_blank'] = '<em>(blank)</em>';
	$txt['database_settings_hidden'] = 'Some settings are not being shown because the database connection information is incorrect.';

	$txt['critical_settings'] = 'Critical Settings';
	$txt['critical_settings_info'] = 'These are the settings most likely to be screwing up your board, but try the things below (especially the path and URL ones) if these don\'t help.  You can click on the recommended value to use it.';
	$txt['maintenance'] = 'Maintenance Mode';
	$txt['maintenance0'] = 'Off (recommended)';
	$txt['maintenance1'] = 'Enabled';
	$txt['maintenance2'] = 'Unusable <em>(not recommended!)</em>';
	$txt['language'] = 'Language File';
	$txt['cookiename'] = 'Cookie Name';
	$txt['queryless_urls'] = 'Queryless URLs';
	$txt['queryless_urls0'] = 'Off (recommended)';
	$txt['queryless_urls1'] = 'On';
	$txt['enableCompressedOutput'] = 'Output Compression';
	$txt['enableCompressedOutput0'] = 'Off (recommended if you have problems)';
	$txt['enableCompressedOutput1'] = 'On (saves a lot of bandwidth)';
	$txt['databaseSession_enable'] = 'Database driven sessions';
	$txt['databaseSession_enable0'] = 'Off (not recommended)';
	$txt['databaseSession_enable1'] = 'On (recommended)';
	$txt['theme_default'] = 'Set ElkArte Default theme as overall forum default<br />for all users';
	$txt['theme_default0'] = 'No (keep the current users\' theme settings)';
	$txt['theme_default1'] = 'Yes (recommended if you have problems)';

	$txt['database_settings'] = 'Database Info';
	$txt['database_settings_info'] = 'This is the server, username, password, and database for your server.';
	$txt['db_server'] = 'Server';
	$txt['db_name'] = 'Database name';
	$txt['db_user'] = 'Username';
	$txt['db_passwd'] = 'Password';
	$txt['ssi_db_user'] = 'SSI Username';
	$txt['ssi_db_passwd'] = 'SSI Password';
	$txt['ssi_db_user_desc'] = '(Optional)';
	$txt['ssi_db_passwd_desc'] = '(Optional)';
	$txt['db_prefix'] = 'Table prefix';
	$txt['db_persist'] = 'Connection type';
	$txt['db_persist0'] = 'Standard (recommended)';
	$txt['db_persist1'] = 'Persistent (might cause problems)';
	$txt['db_mysql'] = 'MySQL';
	$txt['db_postgresql'] = 'PostgreSQL';
	$txt['db_sqlite'] = 'SQLite';

	$txt['path_url_settings'] = 'Paths &amp; URLs';
	$txt['path_url_settings_info'] = 'These are the paths and URLs to your ElkArte installation. Correct them if they are wrong, otherwise you can experience serious issues.';
	$txt['boardurl'] = 'Forum URL';
	$txt['boarddir'] = 'Forum Directory';
	$txt['sourcedir'] = 'Sources Directory';
	$txt['cachedir'] = 'Cache Directory';
	$txt['librarydir'] = 'Library Directory';
	$txt['controllerdir'] = 'Controller Directory';
	$txt['attachmentUploadDir'] = 'Attachment Directory';
	$txt['avatar_url'] = 'Avatar URL';
	$txt['avatar_directory'] = 'Avatar Directory';
	$txt['smileys_url'] = 'Smileys URL';
	$txt['smileys_dir'] = 'Smileys Directory';
	$txt['theme_url'] = 'Default Theme URL';
	$txt['images_url'] = 'Default Theme Images URL';
	$txt['theme_dir'] = 'Default Theme Directory';

	$txt['theme_path_url_settings'] = 'Paths &amp; URLs For Themes';
	$txt['theme_path_url_settings_info'] = 'These are the paths and URLs to your ElkArte themes.';
}

function template_initialize()
{
	global $context, $txt;

	// try to find the logo: could be a .gif or a .png
	$logo = "themes/default/images/logo.png";
	if (!file_exists(dirname(__FILE__) . "/" . $logo))
		$logo = "Themes/default/images/logo_sm.png";
	if (!file_exists(dirname(__FILE__) . "/" . $logo))
		$logo = "Themes/default/images/smflogo.png";
	if (!file_exists(dirname(__FILE__) . "/" . $logo))
		$logo = "Themes/default/images/smflogo.gif";

	// Note that we're using the default URLs because we aren't even going to try to use Settings.php's settings.
	echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="robots" content="noindex" />
		<title>', $txt['elkarte_repair_settings'], '</title>
		<script type="text/javascript" src="themes/default/scripts/script.js"></script>
		<style type="text/css">
			body
			{
				background-color: #e5e5e8;
				margin: 0px;
				padding: 0px;
			}
			body, td
			{
				color: #000000;
				font-size: small;
				font-family: verdana, sans-serif;
			}
			div#header
			{
				background-image: url(themes/default/images/catbg.jpg);
				background-repeat: repeat-x;
				background-color: #88a6c0;
				padding: 22px 4% 12px 4%;
				color: white;
				font-family: Georgia, serif;
				font-size: xx-large;
				border-bottom: 1px solid black;
				height: 40px;
			}
			div#content
			{
				padding: 20px 30px;
			}
			div.error_message
			{
				border: 2px dashed red;
				background-color: #e1e1e1;
				margin: 1ex 4ex;
				padding: 1.5ex;
			}
			div.panel
			{
				border: 1px solid gray;
				background-color: #f6f6f6;
				margin: 1ex 0;
				padding: 1.2ex;
			}
			div.panel h2
			{
				margin: 0;
				margin-bottom: 0.5ex;
				padding-bottom: 3px;
				border-bottom: 1px dashed black;
				font-size: 14pt;
				font-weight: normal;
			}
			div.panel h3
			{
				margin: 0;
				margin-bottom: 2ex;
				font-size: 10pt;
				font-weight: normal;
			}
			form
			{
				margin: 0;
			}
			td.textbox
			{
				padding-top: 2px;
				font-weight: bold;
				white-space: nowrap;
				padding-', empty($txt['lang_rtl']) ? 'right' : 'left', ': 2ex;
			}
			.smalltext
			{
				font-size: 0.8em;
				font-weight: normal;
			}
			.centertext
			{
				margin: 0 auto;
				text-align: center;
			}
			.righttext
			{
				margin-left: auto;
				margin-right: 0;
				text-align: right;
			}
			.lefttext
			{
				margin-left: 0;
				margin-right: auto;
				text-align: left;
			}
			.changed td
			{
				color: red;
			}
		</style>
	</head>
	<body>
		<div id="header">
			<a href="http://www.elkarte.net" target="_blank"><img src="' . $logo . '" style="width: 250px; float: right;" alt="Elkarte" border="0" /></a>
			<div>', $txt['elkarte_repair_settings'], '</div>
		</div>
		<div id="content">';

	// Fix Database title to use $db_type if available
	if (!empty($db_type) && isset($txt['db_' . $db_type]))
		$txt['database_settings'] = $txt['db_' . $db_type] . ' ' . $txt['database_settings'];

}

function template_show_footer()
{
	echo '
		</div>
	</body>
</html>';

}