<?php
/**
 * Patch to Mod
 *
 * This script should convert patches (at the moment git generated diffs)
 * to fully functional mods for ElkArte
 * To set up the script:
 *  - put it into the same directory as a working ElkArte
 *  - set the variable $create_path to an absolute path writable by the 
 *    script (the package will be saved there, remember to delete it)
 *  - point the browser to http://yourdomain.tld/forum/patch_to_mod.php
 *  - enjoy! :P
 *
 * @package PtM
 * @author emanuele
 * @copyright 2014 emanuele
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 0.1.0
 */

// CLI demo
//     php patch_to_mod.php --patch_type git_diff --author me --version "1.0.10" --elk_selected_version 1 --current_file "/home/emanuele/Devel/SMF/SMF/Dialogo_releases/diff-1.0.10" --name "elk"

$txt['PtM_menu'] = 'Package creation script';
$txt['add_a_file'] = 'Add a file';
$txt['add_new_space'] = 'Add new space';
$txt['path_not_writable'] = 'The configured path is not writable or doesn\'t exist.' . "\n\n" . '%1$s';
$txt['error_file_upload'] = 'An error occurred while uploading a file';
$txt['cannot_create_package'] = 'Cannot create the zip package';
$txt['package_creation_failed'] = 'Package creation failed with the following code: %1$s';
$txt['package_not_found'] = 'Cannot find the package you are looking for';
$txt['dir']['board'] = 'BOARDDIR';
$txt['dir']['source'] = 'SOURCEDIR';
$txt['dir']['subs'] = 'SUBSDIR';
$txt['dir']['admin'] = 'ADMINDIR';
$txt['dir']['controller'] = 'CONTROLLERDIR';
$txt['dir']['ext'] = 'EXTDIR';
$txt['dir']['avatars'] = 'AVATARSDIR';
$txt['dir']['theme'] = 'THEMEDIR';
$txt['dir']['images'] = 'IMAGESDIR';
$txt['dir']['language'] = 'LANGUAGEDIR';
$txt['dir']['smiley'] = 'SMILEYDIR';
$txt['is_code'] = 'Run code (install+uninstall)';
$txt['is_code_unin'] = 'Run code (uninstall-only)';
$txt['is_database'] = 'Run database code (install-only)';

$create_path = '';
global $ignore_install_dir;
$ignore_install_dir = true;

// ---------------------------------------------------------------------------------------------------------------------
define('ELK_INTEGRATION_SETTINGS', serialize(array(
	'integrate_menu_buttons' => 'create_menu_button',)));
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK'))
	exit('<b>Error:</b> please verify you put this in the same place as ELK\'s SSI.php.');

define('CLI', php_sapi_name() == 'cli');

if (ELK == 'SSI' && CLI === false)
{
	// Let's start the main job
	create_mod($create_path);
	// and then let's throw out the template! :P
	obExit(null, null, true);
}
elseif (CLI === true)
{
	// Guess is fun
	if (empty($create_path))
		$create_path = dirname(__FILE__) . '/packages/create';

	$d_inputs = getopt('', array(
		'patch_type:',
		'author:',
		'version:',
		'elk_selected_version:',
// 		'current_path::',
		'current_file:',
		'create_path:',
		'name:',
	));
	if (empty($d_inputs['create_path']))
	{
		$d_inputs['create_path'] = $create_path;
	}
	$d_inputs['patch'] = 1;
	$d_inputs['patch_type'] = 'Create_from_' . $d_inputs['patch_type'];

	$inputs = validateInputs($d_inputs);
	$inputs['mod_current_file'] = $d_inputs['current_file'];
	try
	{
		do_create($inputs);
	}
	catch (Exception $e)
	{
		echo "\n" . $e->getMessage() . "\n";
	}
}

function readAvailableMethods()
{
	$available_methods = array();
	$class_prefix = 'Create_from_';
	$classes = get_declared_classes();

	foreach ($classes as $class)
	{
		if (substr($class, 0, strlen($class_prefix)) == $class_prefix && defined($class . '::description'))
		{
			$available_methods[$class] = $class::description;
		}
	}
	return $available_methods;
}

function getElkVersions()
{
	return array(
		1 => 'ELK 1.0.x',
		2 => 'ELK 1.1.x',
	);
}

function validateInputs($inputs)
{
	$available_methods = readAvailableMethods();
	$elk_versions = getElkVersions();

	$v_inputs = array(
		'mod_patch' => (bool) $inputs['patch'],
		'mod_patch_type' => !empty($inputs['patch_type']) && isset($available_methods[$inputs['patch_type']]) ? $inputs['patch_type'] : '',
		'mod_name' => !empty($inputs['name']) ? Util::htmlspecialchars($inputs['name']) : '',
		'mod_author' => !empty($inputs['author']) ? Util::htmlspecialchars($inputs['author']) : '',
		'mod_version' => !empty($inputs['version']) ? Util::htmlspecialchars($inputs['version']) : '',
		'mod_elk_version' => !empty($inputs['elk_version']) ? (int) $inputs['elk_version'] : '',
	);

	$v_inputs['mod_elk_version'] = isset($elk_versions[$v_inputs['mod_elk_version']]) ? $v_inputs['mod_elk_version'] : 1;

	$v_inputs['mod_elk_selected_version'] = $elk_versions[$v_inputs['mod_elk_version']];

	$v_inputs['mod_current_path'] = $inputs['create_path'];

	return $v_inputs;
}

function create_mod ($create_path)
{
	global $context, $boardurl, $txt;

	$available_methods = readAvailableMethods();

	// Guess is fun
	if (empty($create_path))
		$create_path = dirname(__FILE__) . '/packages/create';

	if (isset($_REQUEST['download']))
	{
		$file_name = basename($_REQUEST['download']);
		$file_path = $create_path . '/' . $file_name . '/' . $file_name . '.zip';
		if (!file_exists($file_path))
			fatal_error($txt['package_not_found'], false);

		$file_name = $file_name . '.zip';

		ob_end_clean();
		header('Pragma: ');
		if (!$context['browser']['is_gecko'])
			header('Content-Transfer-Encoding: binary');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($file_path)) . ' GMT');
		header('Accept-Ranges: bytes');
		header('Connection: close');
		header('Content-type: application/zip');

		// Convert the file to UTF-8, cuz most browsers dig that.
		$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $file_name) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($file_name, 'UTF-8', $context['character_set']) : $file_name);
		$fixchar = create_function('$n', '
			if ($n < 32)
				return \'\';
			elseif ($n < 128)
				return chr($n);
			elseif ($n < 2048)
				return chr(192 | $n >> 6) . chr(128 | $n & 63);
			elseif ($n < 65536)
				return chr(224 | $n >> 12) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);
			else
				return chr(240 | $n >> 18) . chr(128 | $n >> 12 & 63) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);');

		if ($context['browser']['is_firefox'])
			header('Content-Disposition: attachment; filename*="UTF-8\'\'' . preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $utf8name) . '"');
		elseif ($context['browser']['is_opera'])
			header('Content-Disposition: attachment; filename="' . preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $utf8name) . '"');
		elseif ($context['browser']['is_ie'])
			header('Content-Disposition: attachment; filename="' . urlencode(preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $utf8name)) . '"');
		else
			header('Content-Disposition: attachment; filename="' . $utf8name . '"');

		header('Cache-Control: no-cache');

		header('Content-Length: ' . filesize($file_path));

		// Try to buy some time...
		@set_time_limit(600);

		// Forcibly end any output buffering going on.
		while (@ob_get_level() > 0)
			@ob_end_clean();

		$fp = fopen($file_path, 'rb');
		while (!feof($fp))
		{
			if (isset($callback))
				echo $callback(fread($fp, 8192));
			else
				echo fread($fp, 8192);
			flush();
		}
		fclose($fp);

		obExit(false);
// 		redirectexit($boardurl . '/patch_to_mod.php');
	}

	$inputs = validateInputs(array(
		'patch' => isset($_FILES['mod_patch']) && empty($_FILES['mod_patch']['error']),
		'patch_type' => getPost('mod_patch_type'),
		'name' => getPost('mod_name'),
		'author' => getPost('mod_author'),
		'version' => getPost('mod_version'),
		'elk_version' => getPost('mod_elk_version'),
		'create_path' => $create_path,
	));

	$context['available_types'] = $available_methods;

	$context['elk_versions'] = getElkVersions();

	$inputs['mod_current_file'] = $inputs['mod_patch'] ? $_FILES['mod_patch']['tmp_name'] : '';

	$context['sub_template'] = 'create_script';
	$context['page_title_html_safe'] = 'Path to Mod script';
	$context['addon_data'] = $inputs;

	if (!empty($inputs['mod_patch']) && !empty($inputs['mod_name']) && !empty($inputs['mod_patch_type']) && !empty($inputs['mod_author']) && !empty($inputs['mod_version']) && !empty($inputs['mod_elk_version']))
		do_create($inputs);
}

function getPost($index)
{
	return isset($_POST[$index]) ? $_POST[$index] : '';
}

function do_create ($inputs)
{
	global $context, $boardurl;

	$mod = new $inputs['mod_patch_type']();
	$mod->prepare_files();

	// Something may go wrong with the upload, better check
	if ($mod->hasError())
		return;

	$mod->setModName($inputs['mod_name'])
		->setAuthor($inputs['mod_author'])
		->setVersion($inputs['mod_version'])
		->addSupportedELK($inputs['mod_elk_selected_version'])
		->setPath($inputs['mod_current_path'])
		->setPatchFile($inputs['mod_current_file']);

	if ($mod->hasError())
		show_errors($mod->getFirstError());

	$mod->create_mod_xml()
		->create_package_xml();

	if ($mod->hasError())
		show_errors($mod->getFirstError());

	// Everything seems fine, now it's time to package everything and remove most of the files
	$mod->create_package()->clean_uploaded_files()->clean_other_files();

	$context['creation_done'] = true;
	$context['download_url'] = $boardurl . '/patch_to_mod.php?download=' . $mod->clean_mod_name;
}

function show_errors ($err)
{
	if (is_array($err))
		\Errors::instance()->fatal_lang_error($err['err_code'], false, $err['sprintf']);
	else
		\Errors::instance()->fatal_lang_error($err, false);
}

function create_menu_button (&$buttons)
{
	global $boardurl, $context, $txt;

	$context['sub_template'] = 'create_script';
	$context['current_action'] = 'create';

	$buttons['create'] = array(
		'title' => $txt['PtM_menu'],
		'show' => true,
		'href' => $boardurl . '/patch_to_mod.php',
		'active_button' => true,
		'sub_buttons' => array(
		),
	);
}

function template_create_script ()
{
	global $boardurl, $context, $txt;

	echo '
	<div class="tborder">
		<h3 class="category_header">
			Welcome to the Patch to Mod script
		</h3>
		<p class="description">This procedure will guide you to the conversion of a patch file to a mod</p>
		<div class="roundframe">';
	if (!isset($context['creation_done']))
	{
		echo '
			<form action="', $boardurl, '/patch_to_mod.php?action=create" method="post" accept-charset="UTF-8" name="file_upload" id="file_upload" class="flow_hidden" enctype="multipart/form-data">
				<dl class="settings">
					<dt', empty($context['addon_data']['mod_patch']) ? ' class="error"' : '', '>
						<strong>Please select the patch file:</strong>
					</dt>
					<dd>
						<input type="file" size="40" name="mod_patch" id="mod_patch" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFileInput(\'mod_patch\');">Remove file</a>)',
						'&nbsp;
						<select name="mod_patch_type">';
		foreach ($context['available_types'] as $class => $desc)
			echo '
							<option value="', $class, '"', $context['mod_patch_type'] == $class ? ' selected="selected"' : '', '>', $desc, '</option>';
		echo '
						</select>
					</dd>
					<dt', empty($context['addon_data']['mod_name']) ? ' class="error"' : '', '>
						<strong><label for="mod_name">Mod name:</label></strong>
					</dt>
					<dd>
						<input type="text" name="mod_name" id="mod_name" value="', $context['addon_data']['mod_name'], '" size="40" maxlength="60" class="input_text" />
					</dd>
					<dt', empty($context['addon_data']['mod_author']) ? ' class="error"' : '', '>
						<strong><label for="mod_author">Author name:</label></strong>
					</dt>
					<dd>
						<input type="text" name="mod_author" id="mod_author" value="', $context['addon_data']['mod_author'], '" size="40" maxlength="60" class="input_text" />
					</dd>
					<dt', empty($context['addon_data']['mod_version']) ? ' class="error"' : '', '>
						<strong><label for="mod_version">Mod version:</label></strong>
					</dt>
					<dd>
						<input type="text" name="mod_version" id="mod_version" value="', $context['addon_data']['mod_version'], '" size="40" maxlength="60" class="input_text" />
					</dd>
					<dt', empty($context['addon_data']['mod_elk_version']) ? ' class="error"' : '', '>
						<strong><label for="mod_elk_version">Supported ELK version:</label></strong>
					</dt>
					<dd>
						<select name="mod_elk_version" id="mod_elk_version">';
		foreach ($context['elk_versions'] as $key => $val)
			echo '
							<option value="', $key, '"', $context['addon_data']['mod_elk_version'] == $key ? ' selected="selected"' : '', '>', $val, '</option>';
		echo'
						</select>
					</dd>
					<dd id="add_new_file"></dd>
				</dl>';

		$select = '
							<option value="code">' . $txt['is_code'] . '</option>
							<option value="code_unin">' . $txt['is_code_unin'] . '</option>
							<option value="database">' . $txt['is_database'] . '</option>';
		foreach ($txt['dir'] as $key => $dir)
		{
			$select .= '
							<option value="' . $key . '">' . $dir . '</option>';
		}

		echo '
				<script type="text/javascript"><!-- // --><![CDATA[
					var current_file = 0;
					add_new_file();

					function add_new_file()
					{
						var elem = document.getElementById(\'add_new_file\');
						current_file = current_file + 1;
						setOuterHTML(elem, ', JavaScriptEscape('
					<dt>
						<strong>' . $txt['add_a_file'] . ':</strong>
					</dt>
					<dd>
						<input type="file" size="20" name="mod_file[]" id="mod_file') . ' + current_file + ' . JavaScriptEscape('" class="input_file" /> (<a href="javascript:void(0);" onclick="cleanFieldInput(\'mod_file') . ' + current_file + ' . JavaScriptEscape('\');">Remove file</a>)
						<select name="mod_file_type[]" id="mod_file') . ' + current_file + ' . JavaScriptEscape('_select">' . $select . '
						</select>
						<label for="mod_file') . ' + current_file + ' . JavaScriptEscape('_subdir">&nbsp;/&nbsp;</label><input type="text" name="mod_file_subdir[]" id="mod_file') . ' + current_file + ' . JavaScriptEscape('_subdir" value="" size="20" class="input_text" />
					</dd>
					<dd id="add_new_file">[<a href="#" onclick="add_new_file();return false;">' . $txt['add_new_space'] . '</a>]</dd>'), ');
					}
					function cleanFieldInput(id)
					{
						cleanFileInput(id);
						document.getElementById(id + \'_select\')[0].selected = true;
						document.getElementById(id + \'_subdir\').value = \'\';
					}
				// ]]></script>';

		echo '
				<input type="submit" value="create" class="floatright button_submit" />
			</form>';
	}
	else
		echo '<strong>The package has been created successfully!</strong><br />
		You can download it from <a href="', $context['download_url'], '">here</a>';

	echo '
		</div>
		<span class="lowerframe"><span></span></span>
	</div>';
}

/**
 * A set of useful functions that must be extended before use
 */
class Create_xml
{
	protected $default_ELK_ver = '1.0';
	protected $patch_file;
	protected $content = array();
	protected $lines = 0;
	protected $current_line = '';
	protected $current_pos = 0;
	private $author;
	private $mod_name;
	private $version;
	private $elk_versions = array();
	private $create_path;
	private $modifications = array();
	private $opCounter = 0;
	private $modCounter = 0;
	private $errors = array();
	private $up_files = array();
	private $methods = array();
	public $clean_mod_name;

	public function getMethods ($prefix)
	{
		if (empty($prefix))
			return;
		if (!empty($this->methods[$prefix]))
			return $this->methods[$prefix];

		$methods = new ReflectionClass($this->getClassName());
		$return = false;
		$len = strlen($prefix);
		$this->methods[$prefix] = array();
		foreach ($methods->getMethods() as $method)
		{
			if (substr($method->name, 0, $len) === $prefix)
				$this->methods[$prefix][] = $method->name;
		}

		return $this->methods[$prefix];
	}

	public function setPatchFile ($file)
	{
		if (!empty($file) && file_exists($file))
			$this->patch_file = $file;

		return $this;
	}
	public function setAuthor ($author = 'unknown')
	{
		$this->author = htmlspecialchars($author);

		return $this;
	}
	public function setModName ($name = 'unknown')
	{
		$this->mod_name = htmlspecialchars($name);
		$this->clean_mod_name = htmlspecialchars(str_replace(array(' ', ',', ':', '.', ';', '#', '@', '='), array('_'), $name));

		return $this;
	}
	public function setVersion ($ver = '1.0')
	{
		$this->version = $ver;

		return $this;
	}
	public function addSupportedELK ($ver)
	{
		if (empty($ver))
			$ver = $this->default_ELK_ver;

		if (!in_array($ver, $this->elk_versions))
			array_push($this->elk_versions, htmlspecialchars(substr($ver, 4, 3) . ' - ' . substr($ver, 4, 3) . '.99'));

		return $this;
	}
	public function setPath ($path)
	{
		if (!file_exists($path))
			@mkdir($path);
		if (!file_exists($path) || !is_writable($path))
			$this->addError(array('err_code' => 'path_not_writable', 'sprintf' => array($path)))->clean_uploaded_files();
		$current_path = $path . '/' . $this->clean_mod_name;

		// Let's start fresh everytime
		if (file_exists($current_path))
		{
			require_once(SUBSDIR . '/Package.subs.php');
			deltree($current_path);
		}

		@mkdir($current_path);
		if (!file_exists($current_path) || !is_writable($current_path))
			$this->addError(array('err_code' => 'path_not_writable', 'sprintf' => array($current_path)))->clean_uploaded_files();
		$this->create_path = $current_path;

		return $this;
	}
	public function getPath ()
	{
		return $this->create_path;
	}
	protected function addModification ($type, $value)
	{
		$this->modifications[$this->modCounter][$type] = $value;

		return $this;
	}
	protected function addOperation ($value)
	{
// 		if (!empty($this->currentFileContent))
// 			$value = $this->optimizeOperation($value);

		$this->modifications[$this->modCounter]['operations'][$this->opCounter]['search'] = str_replace(array('<![CDATA[', ']]>'), array('<![CDA\' . \'TA[', ']\' . \']>'), implode("\n", $value['search']));
		$this->modifications[$this->modCounter]['operations'][$this->opCounter]['replace'] = str_replace(array('<![CDATA[', ']]>'), array('<![CDA\' . \'TA[', ']\' . \']>'), implode("\n", $value['replace']));;

		$this->opCounter++;

		return $this;
	}
	protected function optimizeOperation ($value, $reverse = true)
	{
		// start from the bottom of the operations and reverse *again* the order later
		if ($reverse)
		{
			$value = $this->optimizeOperation(array(
				'search' => array_reverse($value['search']),
				'replace' => array_reverse($value['replace']),
			), false);
			$value = array(
				'search' => array_reverse($value['search']),
				'replace' => array_reverse($value['replace']),
			);
		}

		$searches = $value['search'];
		$replaces = $value['replace'];

		// Endless loops are funny! :P
		while(true)
		{
			if (empty($searches) || empty($replaces))
				break;

			$search = array_shift($searches);
			$replace = array_shift($replaces);
			if ($search == $replace)
			{
				if ($reverse)
					$pattern = '/' . preg_quote(str_replace("\r", '', implode('', $searches)), '/') . '/';
				else
					$pattern = '/' . preg_quote(str_replace("\r", '', implode('', array_reverse($searches))), '/') . '/';

				$matches = preg_match_all($pattern, $this->currentFileContent, $m);
				if ($matches == 1)
					continue;
				elseif ($matches > 1)
				{
					array_unshift($searches, $search);
					array_unshift($replaces, $replace);
					break;
				}
			}
			// Stop as soon as we found a difference (for the moment let's keep it simple)
			else
			{
				array_unshift($searches, $search);
				array_unshift($replaces, $replace);
				break;
			}
		}

		return array('search' => $searches, 'replace' => $replaces);
	}
	public function hasError ()
	{
		return !empty($this->errors);
	}
	public function getFirstError ()
	{
		if (!$this->hasError())
			return false;

		return array_shift($this->errors);
	}

	public function prepare_files ()
	{
		$destinations = array(
			'board' => 'BOARDDIR',
			'source' => 'SOURCEDIR',
			'subs' => 'SUBSDIR',
			'admin' => 'ADMINDIR',
			'controller' => 'CONTROLLERDIR',
			'ext' => 'EXTDIR',
			'avatars' => 'AVATARSDIR',
			'theme' => 'THEMEDIR',
			'images' => 'IMAGESDIR',
			'language' => 'LANGUAGEDIR',
			'smiley' => 'SMILEYDIR',
		);

		if (!empty($_FILES['mod_file']))
		{
			$this->up_files = array();
			foreach ($_FILES['mod_file']['name'] as $key => $file)
			{
				$file = array();
				// Something wrong, stop here and go back
				if (!empty($_FILES['mod_patch']['error'][$key]))
				{
					$this->addError('error_file_upload')->clean_uploaded_files();
					return $this;
				}

				// If no files are specified the array contains an empty item
				if (empty($_FILES['mod_file']['tmp_name'][$key]))
					continue;

				// That one goes into a subdir
				if (isset($_POST['mod_file_subdir'][$key]))
					$file['sub_dir'] = $_POST['mod_file_subdir'][$key];
				// Let's see where this should go
				if (isset($_POST['mod_file_type'][$key]))
					$file['type'] = $destinations[$_POST['mod_file_type'][$key]];
				// And finally where the file actually is and its name
				$file['path'] = $_FILES['mod_file']['tmp_name'][$key];
				$file['name'] = $_FILES['mod_file']['name'][$key];
				$this->addFile($file);
			}

			// Let's not make things too complex for the moment: all the files go to the same location
			foreach ($this->up_files as $file)
				move_uploaded_file($file['path'], $this->create_path . '/' . $file['name']);
		}
		return $this;
	}

	private function addFile ($file)
	{
		if (!empty($file))
			array_push($this->up_files, $file);
	}
	private function addError ($err)
	{
		if (!empty($err))
			array_push($this->errors, $err);

		return $this;
	}

	public function clean_uploaded_files ()
	{
		if (!empty($this->up_files))
			foreach ($this->up_files as $file)
				@unlink($this->create_path . '/' . $file['name']);
		$this->up_files = array();

		if (!empty($_FILES['mod_file']))
			foreach ($_FILES['mod_file']['tmp_name'] as $key => $file)
				@unlink($file);

		return $this;
	}
	public function clean_other_files ()
	{
		$files = array('package-info.xml', 'modifications.xml');
		foreach ($files as $file)
			if (file_exists($this->create_path . '/' . $file))
				@unlink($this->create_path . '/' . $file);

		return $this;
	}

	public function create_package ()
	{
		$zip_package = $this->create_path . '/' . $this->clean_mod_name . '.zip';

		if (file_exists($zip_package))
			@unlink($zip_package);
		if (file_exists($zip_package))
			$this->addError('cannot_create_package');

		$zip = new ZipArchive();
		$error = $zip->open($zip_package, ZIPARCHIVE::CREATE);
		if ($error !== true)
			$this->addError(array('err_code' => 'package_creation_failed', 'sprintf' => $error));

		// All the uploaded files
		if (!empty($this->up_files))
			foreach ($this->up_files as $file)
				$zip->addFile($this->create_path . '/' . $file['name'], $file['name']);

		// the modifications.xml
		if (!empty($this->modifications))
				$zip->addFile($this->create_path . '/modifications.xml', 'modifications.xml');

		// package-info.xml
		if (!empty($this->up_files) || !empty($this->modifications))
				$zip->addFile($this->create_path . '/package-info.xml', 'package-info.xml');

		$zip->close();

		return $this;
	}

	public function write_mod_xml ()
	{
		$write = '<?xml version="1.0"?>
<!DOCTYPE modification SYSTEM "http://www.elkarte.net/site/modification">
<modification xmlns="http://www.elkarte.net/site/modification" xmlns:elk="http://www.elkarte.net/">

	<id>' . $this->author . ':' . $this->clean_mod_name . '</id>
	<version>' . $this->version . '</version>';

		foreach ($this->modifications as $file)
		{
			$write .= '
	<file name="' . $file['path'] . '">';

			foreach ($file['operations'] as $operations)
				$write .= '
		<operation>
			<search position="replace"><![CDATA[' .
				$operations['search'] . ']]></search>
			<add><![CDATA[' .
				$operations['replace'] . ']]></add>
		</operation>';

			$write .= '
	</file>';
		}

		$write .= '
</modification>';
touch($this->create_path . '/modifications.xml');
		file_put_contents($this->create_path . '/modifications.xml', $write);

		return $this;
	}

	public function create_package_xml ()
	{
		$write = '<?xml version="1.0"?>
<!DOCTYPE package-info SYSTEM "http://www.elkarte.net/site/package-info">
<package-info xmlns="http://www.elkarte.net/site/package-info" xmlns:elk="http://www.elkarte.net/">
	<id>' . $this->author . ':' . $this->clean_mod_name . '</id>
	<name>' . $this->mod_name . '</name>
	<version>' . $this->version . '</version>
	<type>modification</type>';

		foreach ($this->elk_versions as $elk_version)
			foreach (array('install', 'uninstall') as $action)
			{
				$write .= '
		<' . $action . ' for="' . $elk_version . '">';

				if (!empty($this->up_files))
				{
					foreach ($this->up_files as $upfile)
					{
						if ($upfile['type'] == 'code' || ($upfile['type'] == 'code_unin' && $action == 'uninstall'))
							$write .= '
			<code>' . $upfile['name'] . '</code>';
						elseif ($upfile['type'] == 'database' && $action == 'install')
							$write .= '
			<database>' . $upfile['name'] . '</database>';
						elseif ($action == 'install')
							$write .= '
			<require-file name="' . $upfile['name'] . '" destination="' . $upfile['type'] . (!empty($upfile['sub_dir']) ? '/' . $upfile['sub_dir'] : '') . '" />';
						elseif ($action == 'uninstall')
							$write .= '
			<remove-file name="' . $upfile['type'] . (!empty($upfile['sub_dir']) ? '/' . $upfile['sub_dir'] : '') . '/' . $upfile['name'] . '" />';
					}
				}
				if (!empty($this->modifications))
					$write .= '
			<modification' . ($action == 'uninstall' ? ' reverse="true"' : '') . '>modifications.xml</modification>';
								$write .= '
		</' . $action . '>';
			}

		$write .= '
</package-info>';

		file_put_contents($this->create_path . '/package-info.xml', $write);

		return $this;
	}

	protected function readCurrentFile ($file)
	{
		global $settings;

		if (substr($file, 0, 7) == 'sources')
			$real_path = SOURCEDIR . substr($file, 7);
		elseif (substr($file, 0, 24) == 'themes/default/languages')
			$real_path = $settings['default_theme_dir'] . substr($file, 14);
		elseif (substr($file, 0, 21) == 'themes/default/images')
			$real_path = '';
		elseif (substr($file, 0, 22) == 'themes/default/scripts')
			$real_path = $settings['default_theme_dir'] . substr($file, 14);
		elseif (substr($file, 0, 18) == 'themes/default/css')
			$real_path = $settings['default_theme_dir'] . substr($file, 14);
		elseif (substr($file, 0, 14) == 'themes/default')
			$real_path = $settings['default_theme_dir'] . substr($file, 14);
		elseif (substr($file, 0, 6) == 'themes')
			$real_path = substr($settings['default_theme_dir'], 0, -8) . substr($file, 6);
		else
			$real_path = BOARDDIR . '/' . $file;

		$this->currentFileName = $real_path;
		if (!empty($real_path))
			$this->currentFileContent = str_replace(array("\n", "\r"), array(''), file_get_contents($real_path));
		else
			$this->currentFileContent = '';
	}

	public function string_starts_with ($string, $star)
	{
		return substr($string, 0, strlen($star)) == $star;
	}
	protected function hasModifications ()
	{
		return !empty($this->modifications);
	}
	protected function is_end_of_operation ()
	{
		$methods = $this->getMethods('EOOP');
		$return = false;
		foreach ($methods as $method)
			$return = $return || call_user_func_array(array($this, $method), array());

		return $return;
	}
	protected function increase_counter ()
	{
		$this->modCounter++;

		return $this;
	}

	protected function readInputFile ()
	{
		$content = file_get_contents($this->patch_file);
		$this->content = explode("\n", $content);
		$this->lines = count($this->content);

		return $this;
	}
	protected function line_starts_with ($start)
	{
		if (isset($this->current_line) && $this->current_line !== null)
			return $this->string_starts_with($this->current_line, $start);
		else
			return false;
	}
	protected function readNextLine ()
	{
		$this->current_pos++;
		if (isset($this->content[$this->current_pos]))
			$this->current_line = $this->content[$this->current_pos];
		else
			$this->current_line = null;

		return $this;
	}
	protected function next_line_exists ()
	{
		return isset($this->content[$this->current_pos + 1]);
	}
	protected function get_line_text ()
	{
		return substr($this->current_line, 1);
	}
}

class Create_from_git_diff extends Create_xml
{
	const description = 'from git diff';

	public function getClassName ()
	{
		return __CLASS__;
	}

	public function create_mod_xml ()
	{
		// Technically this is useless...but, maybe in the future...
		if (empty($this->content))
			$this->readInputFile();

		$operations = array();

		for ($this->current_pos = 0; $this->current_pos < $this->lines; $this->current_pos++)
		{
			$this->current_line = $this->content[$this->current_pos];
			if ($this->line_starts_with('--- a/'))
			{
				$dir = $this->abs_dir_to_var($this->current_line, substr($this->current_line, 6, strrpos($this->current_line, '/') - 6));

				$this->addModification('path', $dir . '/' . basename($this->current_line));
				while (!$this->line_starts_with('@@'))
					$this->readNextLine();
				continue;
			}
			// The block of code is finished, let's add an <operation>
			if ($this->is_end_of_operation() && !empty($operations))
			{
				$this->addOperation($operations);
				// Reset things.
				$operations = array();
				if ($this->line_starts_with('diff --git'))
				{
					$dir = '';
					$this->increase_counter();
				}
				continue;
			}
			if (!empty($dir))
			{
				if ($this->line_starts_with(' '))
					$operations['replace'][] = $operations['search'][] = $this->get_line_text();
				elseif ($this->line_starts_with('-'))
					$operations['search'][] = $this->get_line_text();
				elseif ($this->line_starts_with('+'))
					$operations['replace'][] = $this->get_line_text();
			}
		}

		if ($this->hasModifications())
			$this->write_mod_xml();

		return $this;
	}

	public function abs_dir_to_var ($directory, $subdir)
	{
		if (empty($directory))
			return false;

		$this->readCurrentFile(substr($directory, 6));

		if ($subdir == 'sources/subs')
			$dir = 'SUBSDIR';
		elseif ($subdir == 'sources/controllers')
			$dir = 'CONTROLLERDIR';
		elseif ($subdir == 'sources/admin')
			$dir = 'ADMINDIR';
		elseif ($subdir == 'sources/ext')
			$dir = 'EXTDIR';
		elseif ($subdir == 'sources')
			$dir = 'SOURCEDIR';
		elseif (strpos($directory, 'languages') !== false)
			$dir = 'LANGUAGEDIR';
		elseif (strpos($directory, 'images') !== false)
			$dir = 'IMAGESDIR';
		elseif ($subdir == 'themes/default')
			$dir = 'THEMEDIR';
		elseif (strpos($directory, 'themes/default') !== false)
			$dir = str_replace('themes/default', 'THEMEDIR', $subdir);
		else
			$dir = 'BOARDDIR';

		return $dir;
	}

	protected function EOOP_new_block ($line = null)
	{
		if ($this->line_starts_with('@@'))
			return true;
		return false;
	}
	protected function EOOP_new_file ($line = null)
	{
		if ($this->line_starts_with('diff --git'))
			return true;
		return false;
	}
	protected function EOOP_alt_new_file ($line = null)
	{
		if ($this->line_starts_with('--'))
			return true;
		return false;
	}
	protected function EOOP_has_another_line ($line = null)
	{
		if ($this->next_line_exists())
			return false;
		return true;
	}
}

class Create_from_svn_patch extends Create_xml
{
	const description = 'from SVN patch';

	public function getClassName ()
	{
		return __CLASS__;
	}

	public function create_mod_xml ()
	{
		// Technically this is useless...but, maybe in the future...
		if (empty($this->content))
			$this->readInputFile();

		$operations = array();

		for ($this->current_pos = 0; $this->current_pos < $this->lines; $this->current_pos++)
		{
			$this->current_line = $this->content[$this->current_pos];
			if ($this->line_starts_with('--- '))
			{
				$dir = $this->abs_dir_to_var($this->current_line, substr($this->current_line, 4, strpos($this->current_line, '/', 7) - 4));
				$current_name = substr($this->current_line, 4);

				$this->addModification('path', $dir . '/' . substr($current_name, 0, strpos($current_name, "\t")));
				while (!$this->line_starts_with('@@'))
					$this->readNextLine();
				continue;
			}
			// The block of code is finished, let's add an <operation>
			if ($this->is_end_of_operation() && !empty($operations))
			{
				$this->addOperation($operations);
				// Reset things.
				$operations = array();
				if ($this->line_starts_with('Index:'))
				{
					$dir = '';
					$this->increase_counter();
				}
				continue;
			}
			if (!empty($dir))
			{
				if ($this->line_starts_with(' '))
					$operations['replace'][] = $operations['search'][] = $this->get_line_text();
				elseif ($this->line_starts_with('-'))
					$operations['search'][] = $this->get_line_text();
				elseif ($this->line_starts_with('+'))
					$operations['replace'][] = $this->get_line_text();
			}
		}

		if ($this->hasModifications())
			$this->write_mod_xml();

		return $this;
	}

	public function abs_dir_to_var ($directory, $subdir)
	{
		if (empty($directory))
			return false;

		$this->readCurrentFile(substr($directory, 4, strpos($directory, "\t") - 4));

		if ($subdir == 'Sources')
			$dir = '$sourcedir';
		elseif (strpos($directory, 'languages') !== false)
			$dir = '$languagedir';
		elseif (strpos($directory, 'images') !== false)
			$dir = '$imagesdir';
		elseif (strpos($directory, 'default/scripts') !== false)
			$dir = '$themedir/scripts';
		elseif ($subdir == 'Themes')
			$dir = '$themedir';
		else
			$dir = '$boarddir';

		return $dir;
	}

	protected function EOOP_new_block ($line)
	{
		if ($this->line_starts_with('@@'))
			return true;
		return false;
	}
	protected function EOOP_new_file ($line)
	{
		if ($this->line_starts_with('Index:'))
			return true;
		return false;
	}
	protected function EOOP_alt_new_file ($line)
	{
		if ($this->line_starts_with('Property changes'))
			return true;
		if ($this->line_starts_with('______'))
			return true;
		return false;
	}
	protected function EOOP_has_another_line ($line)
	{
		if ($this->line_starts_with('Added:'))
			return true;
		if ($this->next_line_exists())
			return false;
		return true;
	}
}

?>