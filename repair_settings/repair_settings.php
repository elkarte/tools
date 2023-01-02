<?php

/**
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * This file contains code covered by:
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:		BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.1
 */

// We need the Settings.php info for database stuff.
if (file_exists(__DIR__ . '/Settings.php'))
{
	require_once(__DIR__ . '/Settings.php');
}

// Initialize everything
initialize_inputs();

// Load the language files.
load_language_data();

// Any actions we need to take care of this pass?
$result = false;
if (isset($_POST['submit']))
{
	$result = action_set_settings();
}

if (isset($_POST['remove_hooks']))
{
	$result = action_remove_hooks();
}

if (isset($_POST['delete']))
{
	action_deleteScript();
}

// Off to the template
template_initialize($result);
action_show_settings();
template_show_footer();

/**
 * Start things up
 *
 * - It sets up variables for other steps
 * - It makes the initial connection to the db
 */
function initialize_inputs()
{
	global $db_connection, $sourcedir, $boarddir, $languagedir, $extdir, $cachedir,
		   $db_server, $db_name, $db_user, $db_passwd, $db_prefix, $db_type, $db_port, $db_show_debug;

	// Turn off magic quotes runtime and enable error reporting.
	if (function_exists('set_magic_quotes_runtime'))
		@set_magic_quotes_runtime(0);

	error_reporting(E_ALL);

	ob_start();

	if (ini_get('session.save_handler') === 'user')
	{
		@ini_set('session.save_handler', 'files');
	}

	if (function_exists('session_start'))
	{
		@session_start();
	}

	// Reject magic_quotes_sybase='on'.
	if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc() != 0)
	{
		die('magic_quotes_gpc=on was detected: your host is using an unsecure PHP configuration, deprecated and removed in current versions. Please upgrade PHP.');
	}

	// Add slashes, as long as they aren't already being added.
	foreach ($_POST as $k => $v)
	{
		if (is_array($v))
		{
			foreach ($v as $k2 => $v2)
			{
				$_POST[$k][$k2] = addcslashes($v2, '\\\'');
			}
		}
		else
		{
			$_POST[$k] = addcslashes($v, '\\\'');
		}
	}

	// PHP might complain if we don't do this now.
	date_default_timezone_set(date_default_timezone_get());

	$db_connection = false;
	$db_show_debug = false;

	// If we read Settings.php, verify its pointing to the correct sources
	$sourcedir = discoverSourceDirectory();

	if (!empty($sourcedir))
	{
		if (!defined('ELK'))
		{
			define('ELK', 1);
		}

		// Time to set some constants
		DEFINE('BOARDDIR', $boarddir);
		DEFINE('CACHEDIR', $cachedir);
		DEFINE('EXTDIR', $extdir);
		DEFINE('LANGUAGEDIR', $languagedir);
		DEFINE('SOURCEDIR', $sourcedir);
		DEFINE('ADMINDIR', $sourcedir . '/admin');
		DEFINE('CONTROLLERDIR', $sourcedir . '/controllers');
		DEFINE('DATABASEDIR', $sourcedir . '/database');
		DEFINE('SUBSDIR', $sourcedir . '/subs');
		unset($boarddir, $cachedir, $sourcedir, $languagedir, $extdir);

		// Default the database type to MySQL if not set in settings
		if (empty($db_type) || !file_exists(DATABASEDIR . '/Db-' . $db_type . '.class.php'))
		{
			$db_type = 'mysql';
		}

		// Attempt to make a connection to the db
		require_once(SOURCEDIR . '/Load.php');

		// Needed for 1.1 as autoloader is later in the process
		if (file_exists(SOURCEDIR . '/Autoloader.class.php'))
		{
			require_once(SUBSDIR . '/Util.class.php');
		}

		require_once(DATABASEDIR . '/Database.subs.php');
		$db_connection = elk_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => false, 'dont_select_db' => false, 'port' => $db_port, 'non_fatal' => true), $db_type);

		// Validate we have a proper connection to our ElkArte db and tables
		if (!empty($db_connection))
		{
			$db = database();
			if ($db->select_db($db_name, $db_connection) === null)
			{
				$db_connection = null;
			}
			else
			{
				$tables = $db->db_list_tables($db_name, $db_prefix . 'settings');
				if (empty($tables) || $tables[0] !== $db_prefix . 'settings')
				{
					$db_connection = null;
				}
			}
		}
	}
}

/**
 * Display the current settings.
 *
 * This function reads Settings.php, and if it can connect, the database settings.
 */
function action_show_settings()
{
	global $txt, $db_name, $db_prefix, $db_connection;

	if ($db_connection)
	{
		$db = database();
	}

	// Check to make sure Settings.php exists!
	$settingsArray = array();
	if (file_exists(__DIR__ . '/Settings.php'))
	{
		$settingsArray = file(__DIR__ . '/Settings.php');
	}

	// Make sure we have an array of lines
	if (count($settingsArray) === 1)
	{
		$settingsArray = preg_split('~[\r\n]~', $settingsArray[0]);
	}

	// Load the Settings.php file in to our settings array
	$settings = array();
	foreach ($settingsArray as $i => $iValue)
	{
		$settingsArray[$i] = rtrim(stripslashes($iValue));

		// Process only the lines that may have information
		if (isset($iValue[0]) && $iValue[0] === '$')
		{
			// 1 var name w/o $, 2 ' or " if quoted value, 3 quoted value if any, 4 unquoted value if any
			preg_match('~^[$]([a-zA-Z_]+)\s*=\s*(?:(["\'])(?:(.*?)["\'])(?:\\2)?|(.*?)(?:\\2)?);~', $settingsArray[$i], $match);

			// Replace dirname(__FILE__) commands with the actual value
			if (isset($match[3]) && ($match[2] === "'" || $match[2] === '"'))
			{
				if ($match[3] === 'dirname(__FILE__)')
				{
					$settings[$match[1]] = __DIR__;
				}
				elseif ($match[3] === 'dirname(__FILE__) . \'/sources\'')
				{
					$settings[$match[1]] = __DIR__ . '/sources';
				}
				elseif ($match[3] === 'BOARDDIR . \'/sources\'')
				{
					$settings[$match[1]] = $settings['boarddir'] . '/sources';
				}
				elseif ($match[3] === 'dirname(__FILE__) . \'/cache\'')
				{
					$settings[$match[1]] = __DIR__ . '/cache';
				}
				elseif ($match[3] === 'dirname(__FILE__) . \'/sources/ext\'')
				{
					$settings[$match[1]] = __DIR__ . '/sources/ext';
				}
				elseif ($match[3] === 'dirname(__FILE__) . \'/themes/default/languages\'')
				{
					$settings[$match[1]] = __DIR__ . '/themes/default/languages';
				}
				else
				{
					$settings[$match[1]] = $match[3];
				}
			}
			elseif (isset($match[4]))
			{
				if ($match[4] === 'dirname(__FILE__)')
				{
					$settings[$match[1]] = __DIR__;
				}
				elseif ($match[4] === 'dirname(__FILE__) . \'/sources\'')
				{
					$settings[$match[1]] = __DIR__ . '/sources';
				}
				elseif ($match[4] === 'BOARDDIR . \'/sources\'')
				{
					$settings[$match[1]] = $settings['boarddir'] . '/sources';
				}
				elseif ($match[4] === 'dirname(__FILE__) . \'/cache\'')
				{
					$settings[$match[1]] = __DIR__ . '/cache';
				}
				elseif ($match[4] === 'dirname(__FILE__) . \'/sources/ext\'')
				{
					$settings[$match[1]] = __DIR__ . '/sources/ext';
				}
				elseif ($match[4] === 'dirname(__FILE__) . \'/themes/default/languages\'')
				{
					$settings[$match[1]] = __DIR__ . '/themes/default/languages';
				}
				else
				{
					$settings[$match[1]] = $match[4];
				}
			}
		}
	}

	// If we were able to make a db connection, load in more settings
	if (!empty($db))
	{
		// Load all settings
		$request = $db->query('', '
			SELECT 
			    DISTINCT variable, value
			FROM {db_prefix}settings',
			array(
				'db_error_skip' => true
			)
		);
		while ($row = $db->fetch_assoc($request))
		{
			if (!isset($settings[$row['variable']]))
			{
				$settings[$row['variable']] = $row['value'];
			}
		}
		$db->free_result($request);

		// Load all the themes.
		$request = $db->query('', '
			SELECT 
				variable, value, id_theme
			FROM {db_prefix}themes
			WHERE id_member = 0
				AND variable IN ({array_string:variables})',
			array(
				'variables' => array('theme_dir', 'theme_url', 'images_url', 'name'),
				'db_error_skip' => true
			)
		);
		$theme_settings = array();
		while ($row = $db->fetch_row($request))
		{
			$theme_settings[$row[2]][$row[0]] = $row[1];
		}
		$db->free_result($request);

		$show_db_settings = $request;
	}
	else
	{
		$show_db_settings = false;
	}

	// Known settings that are found in Settings.php
	$known_settings = array(
		'critical_settings' => array(
			'maintenance' => array('flat', 'int', 2),
			'language' => array('flat', 'string', 'english'),
			'cookiename' => array('flat', 'string', 'ELKCookie' . (!empty($db_name) ? abs(crc32($db_name . preg_replace('~[^A-Za-z0-9_$]~', '', $db_prefix)) % 1000) : '20')),
			'queryless_urls' => array('db', 'check', 1),
			'enableCompressedOutput' => array('db', 'check', 1),
			'databaseSession_enable' => array('db', 'check', 1),
			'theme_default' => array('db', 'int', 1),
			'minify_css_js' => array('db', 'check', 1),
		),
		'database_settings' => array(
			'db_server' => array('flat', 'string', 'localhost'),
			'db_name' => array('flat', 'string'),
			'db_user' => array('flat', 'string'),
			'db_passwd' => array('flat', 'string'),
			'ssi_db_user' => array('flat', 'string'),
			'ssi_db_passwd' => array('flat', 'string'),
			'db_prefix' => array('flat', 'string'),
			'db_persist' => array('flat', 'int', 1),
		),
		'path_url_settings' => array(
			'boardurl' => array('flat', 'string'),
			'boarddir' => array('flat', 'string'),
			'sourcedir' => array('flat', 'string'),
			'extdir' => array('flat', 'string'),
			'languagedir' => array('flat', 'string'),
			'attachmentUploadDir' => array('db', 'array_string'),
			'avatar_url' => array('db', 'string'),
			'avatar_directory' => array('db', 'string'),
			'custom_avatar_url' => array('db', 'string'),
			'custom_avatar_dir' => array('db', 'string'),
			'smileys_url' => array('db', 'string'),
			'smileys_dir' => array('db', 'string'),
		),
		'cache_settings' => array(
			'cache_accelerator' => array('flat', 'string'),
			'cache_enable' => array('flat', 'check', 1),
			'cachedir' => array('flat', 'string'),
			'cache_memcached' => array('flat', 'string'),
			'cache_uid' => array('flat', 'string'),
			'cache_password' => array('flat', 'string'),
		),
		'theme_path_url_settings' => array(),
	);

	// Don't display custom_avatar settings if it is currently off
	if (empty($settings['custom_avatar_enabled']))
	{
		unset($known_settings['path_url_settings']['custom_avatar_url'], $known_settings['path_url_settings']['custom_avatar_dir']);
	}

	// Let's assume we don't want to change the current theme
	$settings['theme_default'] = 0;

	$schema = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || $_SERVER['HTTPS'] === 443) ? 'https://' : 'http://';
	$host = empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
	$url = $schema . $host . substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
	$known_settings['path_url_settings']['boardurl'][2] = $url;
	$known_settings['path_url_settings']['boarddir'][2] = __DIR__;

	if (file_exists(__DIR__ . '/sources'))
	{
		$known_settings['path_url_settings']['sourcedir'][2] = realpath(__DIR__ . '/sources');
	}

	if (file_exists(__DIR__ . '/cache'))
	{
		$known_settings['cache_settings']['cachedir'][2] = realpath(__DIR__ . '/cache');
	}

	if (file_exists(__DIR__ . '/sources/ext'))
	{
		$known_settings['path_url_settings']['extdir'][2] = realpath(__DIR__ . '/sources/ext');
	}

	if (file_exists(__DIR__ . '/themes/default/languages'))
	{
		$known_settings['path_url_settings']['languagedir'][2] = realpath(__DIR__ . '/themes/default/languages');
	}

	if (file_exists(__DIR__ . '/avatars'))
	{
		$known_settings['path_url_settings']['avatar_url'][2] = $url . '/avatars';
		$known_settings['path_url_settings']['avatar_directory'][2] = realpath(__DIR__ . '/avatars');
	}

	if (!empty($settings['custom_avatar_enabled']) && !empty($settings['custom_avatar_dir']) && file_exists(__DIR__ . '/' . basename($settings['custom_avatar_dir'])))
	{
		$known_settings['path_url_settings']['custom_avatar_url'][2] = $url . '/' . basename($settings['custom_avatar_dir']);
		$known_settings['path_url_settings']['custom_avatar_dir'][2] = realpath(__DIR__ . '/' . basename($settings['custom_avatar_dir']));
	}

	if (file_exists(__DIR__ . '/smileys'))
	{
		$known_settings['path_url_settings']['smileys_url'][2] = $url . '/smileys';
		$known_settings['path_url_settings']['smileys_dir'][2] = realpath(__DIR__ . '/smileys');
	}

	if (!empty($theme_settings))
	{
		// Create the values for the themes.
		foreach ($theme_settings as $id => $theme)
		{
			$this_theme = ($pos = strpos($theme['theme_url'], '/themes/')) !== false ? substr($theme['theme_url'], $pos + 8) : '';

			$exist = false;
			if (!empty($this_theme))
			{
				$exist = file_exists(__DIR__ . '/themes/' . $this_theme);
			}

			$old_theme = ($pos = strpos($theme['theme_url'], '/Themes/')) !== false ? substr($theme['theme_url'], $pos + 8) : '';
			$new_theme_exists = file_exists(__DIR__ . '/themes/' . $this_theme);

			$theme_url = $exist && !empty($this_theme) ? $url . '/themes/' . $this_theme : ($new_theme_exists && !empty($old_theme) ? $url . '/themes/' . $this_theme : null);
			$images_url = $exist && !empty($this_theme) ? $url . '/themes/' . $this_theme . '/images' : ($new_theme_exists && !empty($old_theme) ? $url . '/themes/' . $this_theme . '/images' : null);
			$theme_dir = $exist && !empty($this_theme) ? realpath(__DIR__ . '/themes/' . $this_theme) : ($new_theme_exists && !empty($old_theme) ? realpath(__DIR__ . '/themes/' . $this_theme) : null);

			$known_settings['theme_path_url_settings'] += array(
				'theme_' . $id . '_theme_url' => array('theme', 'string', $theme_url),
				'theme_' . $id . '_images_url' => array('theme', 'string', $images_url),
				'theme_' . $id . '_theme_dir' => array('theme', 'string', $theme_dir),
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

	if (!empty($db))
	{
		// Determine the db_prefix
		$tables = $db->db_list_tables($db_name, '%log_topics');
		if (count($tables) === 1)
		{
			$known_settings['database_settings']['db_prefix'][2] = preg_replace('~log_topics$~', '', $tables[0]);
		}
	}
	elseif (empty($show_db_settings))
	{
		echo '
			<div class="errorbox">
				', $txt['database_settings_hidden'], '
			</div>';
	}

	echo '
			<script>
				var resetSettings = [],
					settingsCounter = 0;

				function restoreAll()
				{
					for (var i = 0; i < resetSettings.length; i++)
					{
						var elem = document.getElementById(resetSettings[i]),
							val = elem.value;

						elem.value = document.getElementById(resetSettings[i] + \'_default\').innerHTML;
						if (val != elem.value)
							elem.parentNode.parentNode.className += " changed";
					}
				}
			</script>

			<form action="', $_SERVER['PHP_SELF'], '" method="post">
				<div class="panel">';

	foreach ($known_settings as $settings_section => $section)
	{
		echo '
					<h2>', $txt[$settings_section], '</h2>
					<h3 class="infobox">', $txt[$settings_section . '_info'], '</h3>

					<table class="table_settings">
						<tr>';

		foreach ($section as $setting => $info)
		{
			if ($info[0] === 'hidden')
			{
				continue;
			}

			if ($info[0] !== 'flat' && empty($show_db_settings))
			{
				continue;
			}

			echo '
							<td class="textbox">
								<label', $info[1] !== 'int' ? ' for="' . $setting . '"' : '', '>', $txt[$setting], ': ' .
									(isset($txt[$setting . '_desc']) ? '<span class="smalltext">' . $txt[$setting . '_desc'] . '</span>' : '') . '
								</label>', !isset($settings[$setting]) && $info[1] !== 'check' ? '<span class="no_value">
								' . $txt['no_value'] . '</span>' : '', '
							</td>
							<td>';

			if ($info[1] === 'int' || $info[1] === 'check')
			{
				// Default checkmarks to off if they are not set
				if ($info[1] === 'check' && !isset($settings[$setting]))
				{
					$settings[$setting] = 0;
				}

				for ($i = 0; $i <= $info[2]; $i++)
				{
					echo '
								<label for="', $setting, $i, '">
									<input type="radio" name="', $info[0], 'settings[', $setting, ']" id="', $setting, $i, '" value="', $i, '"', isset($settings[$setting]) && $settings[$setting] == $i ? ' checked="checked"' : '', ' class="input_radio" /> ', $txt[$setting . $i], '
								</label>
								<br />';
				}
			}
			elseif ($info[1] === 'string')
			{
				echo '
								<input type="text" name="', $info[0], 'settings[', $setting, ']" id="', $setting, '" value="', isset($settings[$setting]) ? htmlspecialchars($settings[$setting]) : '', '" style="width: ', $settings_section === 'path_url_settings' || $settings_section === 'theme_path_url_settings' ? '80%;' : '30%;', '" class="input_text" />';

				if (!empty($settings[$setting]) && !empty($info[2]) && $info[2] !== $settings[$setting])
				{
					echo '
								<span class="input_text_warn"></span>';
				}

				if (isset($txt[$setting . '_hint']))
				{
					echo '
								<div class="smalltext">', $txt[$setting . '_hint'], '</div>';
				}

				if (isset($info[2]))
				{
					echo '
								<div class="smalltext">', $txt['default_value'], ': &quot;<strong><a href="javascript:void(0);" id="', $setting, '_default" onclick="document.getElementById(\'', $setting, '\').value = ', $info[2] === '' ? '\'\';">' . $txt['recommend_blank'] : 'this.innerHTML;">' . $info[2], '</a></strong>&quot;.</div>',

					$info[2] === '' ? '' : ($setting !== 'language' && $setting !== 'cookiename' ? '
								<script>
									resetSettings[settingsCounter++] = "' . $setting . '";
								</script>' : '');
				}
			}
			// Only used for attachments
			elseif ($info[1] === 'array_string')
			{
				$array_settings = '';
				if (!is_array($settings[$setting]))
				{
					$array_settings = @unserialize($settings[$setting]);
				}

				if (!is_array($array_settings))
				{
					$array_settings = array($settings[$setting]);
				}

				$item = 1;
				foreach ($array_settings as $array_setting)
				{
					echo '
								<input type="text" name="', $info[0], 'settings[', $setting, '_', $item, ']" id="', $setting, $item, '" value="', $array_setting, '" style="width: ', $settings_section === 'path_url_settings' || $settings_section === 'theme_path_url_settings' ? '80%;' : '30%', '" class="input_text" />';

					$suggested = guess_attachments_directories($item, $array_setting);

					if (!empty($suggested))
					{
						echo '
								<div class="smalltext">', $txt['default_value'], ': &quot;<strong><a href="javascript:void(0);" id="', $setting, $item, '_default" onclick="document.getElementById(\'', $setting, $item, '\').value = ', $suggested[0] === '' ? '\'\';">' . $txt['recommend_blank'] : 'this.innerHTML;">' . $suggested[0], '</a></strong>&quot;.</div>',

						$suggested[0] === '' ? '' : '
								<script>
									resetSettings[settingsCounter++] = "' . $setting . $item . '"; 
								</script>';

						$num = count($suggested);
						for ($i = 1; $i < $num; $i++)
						{
							echo '
								<div class="smalltext">', $txt['other_possible_value'], ': &quot;<strong><a href="javascript:void(0);" id="', $setting, $item, '_default" onclick="document.getElementById(\'', $setting, $item, '\').value = ', $suggested[$i] === '' ? '\'\';">' . $txt['recommend_blank'] : 'this.innerHTML;">' . $suggested[$i], '</a></strong>&quot;.</div>';
						}
					}
					else
					{
						echo '
								<div class="smalltext">', $txt['no_default_value'], '</div>';
					}

					$item++;
				}
			}

			echo '
							</td>
						</tr>
						<tr>';
		}

		echo '
							<td colspan="2"></td>
						</tr>
					</table>';
	}

	echo '
					<div class="submitbutton">';

	$failure = checkSettingsAccess();
	if ($failure)
	{
		echo '
						<input type="submit" name="submit" value="', $txt['save_settings'], '" disabled="disabled" class="button_submit" /><br />', $txt['not_writable'];
	}
	else
	{
		echo '
						<a class="linkbutton" href="javascript:restoreAll();">', $txt['restore_all_settings'], '</a>
						<input type="submit" name="submit" value="', $txt['save_settings'], '" class="button_submit" />
						<input type="submit" name="remove_hooks" value="' . $txt['remove_hooks'] . '" class="button_submit" />
						<input type="submit" name="delete" value="' . $txt['remove_script'] . '" class="button_submit" />';
	}

	echo '
					</div>
				</div>
			</form>';
}

/**
 * Determines the best correct attachment directory location
 *
 * - looking to see if it can find an attachment file in a selection of directories
 * - failing that, look to find the directory name that matches whats in settings
 * - failing that look for directory's named attachment
 *
 * @param int $id
 * @param array $array_setting
 */
function guess_attachments_directories($id, $array_setting)
{
	static $usedDirs;

	$db = database();

	// Fetch the attachment folder id's that have been used
	if (empty($usedDirs))
	{
		$request = $db->query('', '
			SELECT 
				DISTINCT(id_folder), id_attach, file_hash
			FROM {db_prefix}attachments',
			array()
		);
		$usedDirs = array();
		if ($db->num_rows($request) > 0)
		{
			while ($row = $db->fetch_assoc($request))
			{
				$usedDirs[$row['id_folder']] = $row;
			}
		}
		$db->free_result($request);
	}

	// Find all potential attachment directories
	$exclude = array('sources', 'packages', 'themes', 'cache', 'avatars', 'smileys', 'install', 'vendor', 'tests');
	$directory = new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS);
	$filter = new RecursiveCallbackFilterIterator($directory, function ($current) use ($exclude) {
		return $current->isDir() && !in_array($current->getFilename(), $exclude, true);
	});
	$availableDirs = array();
	$iterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
	foreach ($iterator as $dirs)
	{
		$availableDirs[] = $dirs->getPathname();
	}

	// 1st guess: let's see if we can find a file...if there is at least one.
	if (isset($usedDirs[$id]))
	{
		foreach ($availableDirs as $aDir)
		{
			if (file_exists($aDir . '/' . $usedDirs[$id]['id_attach'] . '_' . $usedDirs[$id]['file_hash'] . '.elk'))
			{
				return array($aDir);
			}
		}
	}

	// 2nd guess: directory name
	if (!empty($availableDirs))
	{
		foreach ($availableDirs as $dirname)
		{
			if (strrpos($array_setting, $dirname) === (strlen($array_setting) - strlen($dirname)))
			{
				return array($dirname);
			}
		}
	}

	// Doing it later saves in case the attached files have been deleted from the file system
	if (empty($usedDirs) && empty($availableDirs))
	{
		return false;
	}

	if (empty($usedDirs) && !empty($availableDirs))
	{
		$guesses = array();

		// Attachments is the first guess
		foreach ($availableDirs as $dir)
		{
			if (basename($dir) === 'attachments')
			{
				$guesses[] = $dir;
			}
		}

		// All the others
		foreach ($availableDirs as $dir)
		{
			if (basename($dir) !== 'attachments')
			{
				$guesses[] = $dir;
			}
		}

		return $guesses;
	}
}

/**
 * Used when save settings is selected from the repair settings form
 */
function action_set_settings()
{
	$db = database();

	// What areas are we updating
	$db_updates = $_POST['dbsettings'] ?? array();
	$theme_updates = $_POST['themesettings'] ?? array();
	$file_updates = $_POST['flatsettings'] ?? array();
	$attach_dirs = array();

	// Updating theme settings
	if (empty($db_updates['theme_default']))
	{
		unset($db_updates['theme_default']);
	}
	else
	{
		$db->query('', '
			UPDATE {db_prefix}members
			SET id_theme = {int:default_theme}',
			array(
				'default_theme' => 0,
			)
		);
	}

	// Updating the Settings.php file
	action_outputSettings($file_updates);

	$setString = array();
	foreach ($db_updates as $var => $val)
	{
		$setString[] = array($var, stripslashes($val));
	}

	// Attachments dirs
	$attach_count = 1;
	foreach ($setString as $key => $value)
	{
		if (strpos($value[0], 'attachmentUploadDir') === 0)
		{
			$attach_dirs[$attach_count++] = $value[1];
			unset($setString[$key]);
		}
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

	if ($db && !empty($setString))
	{
		$db->insert('replace', '
			{db_prefix}settings',
			array('variable' => 'string', 'value' => 'string-65534'),
			$setString, array('variable')
		);
	}

	$setString = array();
	foreach ($theme_updates as $var => $val)
	{
		// Extract the data
		preg_match('~theme_(\d+)_(.+)~', $var, $match);
		if (empty($match[0]))
		{
			continue;
		}

		$setString[] = array($match[1], 0, $match[2], stripslashes($val));
	}

	if ($db && !empty($setString))
	{
		$db->insert('replace',
			'{db_prefix}themes',
			array('id_theme' => 'int', 'id_member' => 'int', 'variable' => 'string', 'value' => 'string-65534'),
			$setString, array('id_theme', 'id_member', 'variable')
		);
	}

	return 'settings_saved_success';
}

/**
 * Checks to see if Settings.php is writable
 *
 * @return int
 */
function checkSettingsAccess()
{
	$failure = false;

	if (strpos(__FILE__, ':\\') !== 1)
	{
		// On Linux, it's easy - just use is_writable!
		$failure |= !is_writable('Settings.php') && !chmod('Settings.php', 0777);
	}
	// Windows is trickier.  Let's try opening for r+...
	else
	{
		// Funny enough, chmod actually does do something on windows - it removes the read only attribute.
		chmod(__DIR__ . '/' . 'Settings.php', 0777);
		$fp = @fopen(__DIR__ . '/' . 'Settings.php', 'rb+');

		// Hmm, okay, try just for write in that case...
		if (!$fp)
		{
			$fp = @fopen(__DIR__ . '/' . 'Settings.php', 'wb');
		}

		$failure |= !$fp;
		fclose($fp);
	}

	return $failure;
}

/**
 * Saves any updates made to the Settings.php values
 *
 * @param array $file_updates
 */
function action_outputSettings($file_updates)
{
	require_once(SOURCEDIR . '/Subs.php');

	$settingsArray = file(__DIR__ . '/Settings.php');
	$settings = array();

	foreach ($settingsArray as $i => $iValue)
	{
		$settingsArray[$i] = rtrim($iValue);

		// Remove the redirect...
		if ($iValue === 'if (file_exists(dirname(__FILE__) . \'/install.php\'))')
		{
			$settingsArray[$i] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			$settingsArray[$i++] = '';
			continue;
		}

		if (isset($iValue[0]) && $iValue[0] !== '.' && preg_match('~^[$]([a-zA-Z_]+)\s*=\s*(?:(["\'])(.*?["\'])(?:\\2)?|(.*?)(?:\\2)?);~', $settingsArray[$i], $match) == 1)
		{
			$settings[$match[1]] = stripslashes($match[3]);
		}

		foreach ($file_updates as $var => $val)
		{
			if (strncasecmp($iValue, '$' . $var, 1 + strlen($var)) === 0)
			{
				$comment = strstr(substr(un_htmlspecialchars($iValue), strpos(un_htmlspecialchars($iValue), ';')), '#');

				// Only quote strings, not known ints, bools, checkboxes, etc
				if (in_array($val, array('0', '1', '2', 'true', 'false')))
				{
					$settingsArray[$i] = '$' . $var . ' = ' . $val . ';' . ($comment !== '' ? "\t\t" . rtrim($comment) : '');
				}
				else
				{
					$settingsArray[$i] = '$' . $var . ' = \'' . $val . '\';' . ($comment !== '' ? "\t\t" . rtrim($comment) : '');
				}

				// This one's been 'used', so to speak.
				unset($file_updates[$var]);
			}
		}
	}

	// Still more variables to go?  Then add them at the end.
	if (!empty($file_updates))
	{
		// Add in the missing defined vars that were passed
		foreach ($file_updates as $var => $val)
		{
			$settingsArray[] = '$' . $var . ' = \'' . $val . '\';';
		}
	}

	// Blank out the file - done to fix a oddity with some servers.
	clearstatcache();
	file_put_contents(__DIR__ . '/Settings.php', '', LOCK_EX);

	// Write it out with the updates
	$write_settings = implode("\n", $settingsArray);
	$write_settings = strtr($write_settings, "\n\n\n", "\n\n");
	file_put_contents(__DIR__ . '/Settings.php', $write_settings, LOCK_EX);

	// Make sure it works.
	require(__DIR__ . '/Settings.php');
}

/**
 * Remove ALL of the hooks in the system
 */
function action_remove_hooks()
{
	global $db_connection;

	$db = database();

	if ($db_connection)
	{
		$db->query('', '
			DELETE FROM {db_prefix}settings
			WHERE variable LIKE {string:variable}',
			array(
				'variable' => 'integrate_%'
			)
		);
	}

	// Now fixing the cache...
	require_once(SUBSDIR . '/Cache.subs.php');
	cache_put_data('modsettings', null, 0);

	return 'hook_removal_success';
}

/**
 * Locate / validate the sources directory is correct. Useful under the event
 * that a site has been moved to a new directory. (eg: from "site.com/test" to "site.com/forum")
 * in these cases, the Settings.php file will have wrong values.
 *
 * @return null|string
 */
function discoverSourceDirectory()
{
	$basedir = __DIR__;
	$directory = new RecursiveDirectoryIterator($basedir, FilesystemIterator::SKIP_DOTS);
	$filter = new RecursiveCallbackFilterIterator($directory, function ($current) {
		$check = $current->getFilename();
		if ($check[0] === '.')
		{
			return false;
		}

		if ($current->isDir())
		{
			return true;
		}

		return $current->getFilename() === 'SiteDispatcher.class.php';
	});
	$iterator = new RecursiveIteratorIterator($filter);

	if (empty(iterator_count($iterator)))
	{
		return null;
	}

	// Use the first found
	$iterator->rewind();
	return $iterator->current()->getPath();
}

/**
 * Remove this script when asked, done for security reasons
 */
function action_deleteScript()
{
	@unlink(__FILE__);

	// Now just redirect to forum home /index.php
	$schema = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1 || $_SERVER['HTTPS'] === 443) ? 'https://' : 'http://';
	header('Location: ' . $schema . ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] . ':' . $_SERVER['SERVER_PORT']) . dirname($_SERVER['PHP_SELF']) . '/index.php');

	exit;
}

/**
 * Since we are running outside the forum, we need to define our language strings here
 */
function load_language_data()
{
	global $txt;

	$txt['elkarte_repair_settings'] = 'ElkArte %1$s Repair Utility';
	$txt['no_value'] = '<em style="font-weight: normal; color: red;">Value not found!</em>';
	$txt['default_value'] = 'Recommended value';
	$txt['other_possible_value'] = 'Other possible value';
	$txt['no_default_value'] = 'No recommended value';
	$txt['save_settings'] = 'Save Settings';
	$txt['remove_hooks'] = 'Remove all hooks';
	$txt['remove_script'] = 'Remove this script';
	$txt['restore_all_settings'] = 'Restore all settings';
	$txt['not_writable'] = 'Settings.php cannot be written to by your webserver.  Please modify the permissions on this file to allow write access.';
	$txt['recommend_blank'] = '<em>(blank)</em>';
	$txt['database_settings_hidden'] = 'Some settings are not being shown because the database connection information is incorrect.';

	$txt['critical_settings'] = 'Critical Settings';
	$txt['critical_settings_info'] = 'These are the settings most likely to cause problems with your board.  You can also review the items below this area (especially the path and URL ones) if these don\'t help.<br />Click on the recommended values to use them.';
	$txt['maintenance'] = 'Maintenance Mode';
	$txt['maintenance0'] = 'Off (recommended)';
	$txt['maintenance1'] = 'Enabled';
	$txt['maintenance2'] = 'Unusable <em>(not recommended!)</em>';
	$txt['language'] = 'Language File';
	$txt['cookiename'] = 'Cookie Name';
	$txt['queryless_urls'] = 'Queryless URLs';
	$txt['queryless_urls0'] = 'Off (recommended)';
	$txt['queryless_urls1'] = 'On';
	$txt['minify_css_js'] = 'Minify Javascript and CSS files';
	$txt['minify_css_js0'] = 'Off ((recommended only if you have problems))';
	$txt['minify_css_js1'] = 'On ';
	$txt['enableCompressedOutput'] = 'Output Compression';
	$txt['enableCompressedOutput0'] = 'Off (recommended if you have problems)';
	$txt['enableCompressedOutput1'] = 'On (saves a lot of bandwidth)';
	$txt['databaseSession_enable'] = 'Database driven sessions';
	$txt['databaseSession_enable0'] = 'Off (not recommended)';
	$txt['databaseSession_enable1'] = 'On (recommended)';
	$txt['theme_default'] = 'Set ElkArte Default theme as overall forum default<br />for all users';
	$txt['theme_default0'] = 'No (keep the current users\' theme settings)';
	$txt['theme_default1'] = 'Yes (recommended if you have problems)';

	$txt['cache_settings'] = 'Cache Settings';
	$txt['cache_settings_info'] = 'These are the current cache settings for your installation.<br />You can verify/update cache settings here or turn it off and make any needed changes in your Admin Center.';
	$txt['cache_accelerator'] = 'Caching Accelerator';
	$txt['cache_accelerator_hint'] = 'Choose: apc, memcache, memcached, xcache, filebased or none';
	$txt['cache_enable'] = 'Enable Caching';
	$txt['cache_enable0'] = 'Off (recommended if you have problems)';
	$txt['cache_enable1'] = 'On';
	$txt['cache_memcached'] = 'Memcached Servers';
	$txt['cache_uid'] = 'XCache Accelerator Userid';
	$txt['cache_password'] = 'XCache Accelerator Password';

	$txt['database_settings'] = 'Database Info';
	$txt['database_settings_info'] = 'This is the server, username, password, and database for your server.<br />Click on the recommended values to use them.';
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

	$txt['path_url_settings'] = 'Paths &amp; URLs';
	$txt['path_url_settings_info'] = 'These are the paths and URLs to your ElkArte installation. Correct them if they are wrong, otherwise you can experience serious issues.<br />Click on the recommended values to use them.';
	$txt['boardurl'] = 'Forum URL';
	$txt['boarddir'] = 'Forum Directory';
	$txt['sourcedir'] = 'Sources Directory';
	$txt['cachedir'] = 'Cache Directory';
	$txt['extdir'] = 'External libraries Directory';
	$txt['languagedir'] = 'Languages Directory';
	$txt['attachmentUploadDir'] = 'Attachment Directory(s)';
	$txt['avatar_url'] = 'Avatar URL';
	$txt['avatar_directory'] = 'Avatar Directory';
	$txt['custom_avatar_url'] = 'Custom Avatar URL';
	$txt['custom_avatar_dir'] = 'Custom Avatar Directory';
	$txt['smileys_url'] = 'Smileys URL';
	$txt['smileys_dir'] = 'Smileys Directory';
	$txt['theme_url'] = 'Default Theme URL';
	$txt['images_url'] = 'Default Theme Images URL';
	$txt['theme_dir'] = 'Default Theme Directory';
	$txt['theme_path_url_settings'] = 'Paths &amp; URLs For Themes';
	$txt['theme_path_url_settings_info'] = 'These are the paths and URLs to your ElkArte themes.<br />Click on the recommended values to use them.';

	$txt['hook_removal_success'] = 'All active hooks in the system were successfully removed';
	$txt['settings_saved_success'] = 'Your settings were successfully saved.';
}

/**
 * Show the main template with the current and suggested values
 *
 * @param $results boolean
 */
function template_initialize($results = false)
{
	global $txt, $db_type, $sourcedir;

	$logo = "themes/default/images/logo.png";

	$ver = '1.0.x';
	if (file_exists($sourcedir . '/Autoloader.class.php'))
	{
		$ver = '1.1.x';
	}
	$txt['elkarte_repair_settings'] = sprintf($txt['elkarte_repair_settings'], $ver);

	// Note that we're using the default URLs because we aren't even going to try to use Settings.php's settings.
	echo '<!DOCTYPE html>
	<html>
	<head>
		<meta name="robots" content="noindex" />
		<title>', $txt['elkarte_repair_settings'], '</title>
		<script src="themes/default/scripts/script.js"></script>
		<style type="text/css">
			body {
				background: #555;
				background-image: linear-gradient(to right, #333 0%, #888 50%, #333 100%);
				margin: 0;
				padding: 0;
				font: 87.5%/150% "Segoe UI", -apple-system, BlinkMacSystemFont, "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Droid Sans", "Helvetica Neue", "Trebuchet MS", Arial, sans-serif;
				color: #555;
			}
			td, th {
				font: 87.5%/150% "Segoe UI", -apple-system, BlinkMacSystemFont, "Roboto", "Oxygen", "Ubuntu", "Cantarell", "Droid Sans", "Helvetica Neue", "Trebuchet MS", Arial, sans-serif;
				color: #555;
				font-size: 1em;
				padding-bottom: 1em;
			}
			#top_section {
				margin: 0;
				padding: 0;
				background: #f4f4f4;
				background-image: linear-gradient(to bottom, #fff, #eee);
				box-shadow: 0 1px 4px rgba(0,0,0,0.3), 0 1px 0 #3a642d inset;
				border-top: 4px solid #5ba048;
				border-bottom: 4px solid #3d6e32;
			}
			#header {
				padding: 2em 4% 1em 4%;
				color: #49643d;
				font-size: 2em;
				height: 40px;
			}
			#header img {
				float: right;
				margin-top: -1em;
			}
			#content {
				padding: 1em 1.5em;
			}
			.warningbox, .successbox, .infobox, .errorbox {
				padding: 10px 10px 10px 35px;
			}
			.successbox {
				border-top: 1px solid green;
				border-bottom: 1px solid green;
				background: #efe url(themes/default/images/icons/field_valid.png) 10px 50% no-repeat;
			}
			.infobox {
				border-top: 1px solid #3a87ad;
				border-bottom: 1px solid #3a87ad;
				background: #d9edf7 url(themes/default/images/icons/quick_sticky.png) 10px 50% no-repeat;
			}
			.errorbox {
				border-top: 2px solid #c34;
				border-bottom: 2px solid #c34;
				background: #fee url(themes/default/images/profile/warning_mute.png) 10px 50% no-repeat;
			}
			.notice {
				background: #fee url(themes/default/images/profile/warning_watch.png) 10px 50% no-repeat;
			}
			.panel {
				border: 1px solid #ccc;
				border-radius: 5px;
				background-color: #eee;
				margin: 1em 2em;
				padding: 1.5em;
			}
			.panel h2 {
				margin: 0 0 0.5em;
				padding-bottom: 3px;
				border-bottom: 1px dashed #aaa;
				font-size: 1.6em;
				font-weight: bold;
				color: #555;
			}
			.panel h3 {
				margin: 0 0 2em;
				font-size: 1em;
				font-weight: normal;
			}
			form {
				margin: 0;
			}
			td.textbox {
				padding-top: .1em;
				white-space: nowrap;
				padding-' . (empty($txt['lang_rtl']) ? 'right' : 'left') . ': 2em;
				width: 20%;
				vertical-align: top;
				padding-bottom: .1em;
			}
			.smalltext {
				font-size: 0.85em;
				font-weight: normal;
			}
			.centertext {
				margin: 0 auto;
				text-align: center;
			}
			.righttext {
				margin-left: auto;
				margin-right: 0;
				text-align: right;
			}
			.lefttext {
				margin-left: 0;
				margin-right: auto;
				text-align: left;
			}
			.changed td {
				color: red;
			}
			input, .input_text, button, select {
				padding: 0 6px;
				margin-top: 0;
				min-height: 2em;
				max-height: 2em;
				height: 2em;
				vertical-align: middle;
			}
			.input_text_warn:after {
				color: orange;
				font-size: 1.25em;
				content: "\26A0";
			}
			.no_value {
				display: block;
			}
			.no_value:before {
				color: red;
				font-size: 1.25em;
				content: "\26A0";
			}
			.linkbutton:link, .linkbutton:visited, .button_submit {
				border-radius: 2px;
				border: 1px solid #afafaf;
				border-top: 1px solid #cfcfcf;
				border-left: 1px solid #bfbfbf;
				background: #f4f4f4;
				background-image: linear-gradient(to bottom, #fff, #e4e4e4);
				color: #555;
				box-shadow: 1px 1px 2px #e5e5e5, 0 -1px 0 #e4e4e4 inset;
				text-decoration: none;
			}
			.linkbutton:link, .linkbutton:visited {
				display: inline-block;
				float: right;
				line-height: 1.643em;
				margin-left: 6px;
				padding: 1px 6px;
			}
			.button_submit:hover, .linkbutton:hover {
				cursor: pointer;
				border-left: 1px solid #ccc;
				border-right: 1px solid #afafaf;
				box-shadow: -2px 1px 1px rgba(0,0,0,0.07) inset;
				background-image: linear-gradient(to bottom, #e4e4e4, #fff);
			}
			.table_settings {
				width: 100%;
				border-spacing: 0;
    			border-collapse: collaspe;
    			padding: 0;
				margin-bottom: .1em;
			}
			.submitbutton {
				overflow: auto;
				padding: 6px 0;
				text-align: right;
				clear: both;
			}
		</style>
	</head>
	<body>
		<div id="top_section">
			<div id="header">
				<a href="http://www.elkarte.net" target="_blank">
					<img src="' . $logo . '" alt="ElkArte" />
				</a>
				<div>', $txt['elkarte_repair_settings'], '</div>
			</div>
		</div>
		<div id="content">';

	if ($results)
	{
		echo '
		<div class="successbox">', $txt[$results], '</div>';
	}

	// Fix Database title to use $db_type if available
	if (!empty($db_type) && isset($txt['db_' . $db_type]))
	{
		$txt['database_settings'] = $txt['db_' . $db_type] . ' ' . $txt['database_settings'];
	}
}

/**
 * Close the template
 */
function template_show_footer()
{
	echo '
		</div>
	</body>
</html>';
}
