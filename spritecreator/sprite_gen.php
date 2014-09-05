<?php

/**
 * This file will create a sprite image from a group of images in a directory
 * Although it can be used to make any sprite, its purpose is to create the specific
 * sprite images used in the ElkArte project to ease the creation of custom themes
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 */

// Show the form or create the sprite
if (!isset($_POST['sprite'], $_POST['submit']))
	show_header();
else
{
	$sprite = new Images_To_Sprite($_POST['sprite']);
	$sprite->load_files();
	$sprite->create_sprite();
}

/**
 * Class to create the standard ElkArte sprite files from a collection of images in a directory
 */
class Images_To_Sprite
{
	// Directory to load
	protected $folder = '';

	// Files found in the directory that will be sprited
	protected $files = array();

	// Vertical or horizontal sprite
	protected $direction = 'vertical';

	// Acceptable file extensions to consider loading
	protected $filetypes = array('jpg' => true, 'png' => true, 'jpeg' => true, 'gif' => true);

	// Width or Height of sprite
	protected $size;

	// Center to center pitch of sprite icons
	protected $pitch;

	// Name of the sprite array to load, set to folder
	protected $sprite;

	// Sprite build details
	protected $sprite_key = array();

	// File names to add to sprite
	protected $sprite_names = array();

	// Pitch correction (optional)
	protected $correction;

	/**
	 * Load or set arguments in to the class
	 */
	public function __construct($folder = '')
	{
		// sprite name
		$this->sprite = ($folder ? $folder : 'quickbuttons');

		// Load the definition details for this sprite
		$this->_sprite_definition();

		// Folder name to get images from, i.e. C:\\myimages or /home/user/myimages
		$this->folder = './' . ($folder ? $folder : 'quickbuttons');

		// Output filename
		$this->output = $this->sprite_key[0];

		// Direction to build the sprite
		$this->direction = $this->sprite_key[1];

		// Size (width or height)
		$this->size = $this->sprite_key[2];

		// Center to center pitch of the images in the sprite
		$this->pitch = $this->sprite_key[3];

		// Offset correction if the initial center point of the pattern does not start at pitch/2
		$this->correction = isset($this->sprite_key[4]) ? $this->sprite_key[4] : 0;
	}

	/**
	 * Load all image files that match the criteria from a directory
	 */
	public function load_files()
	{
		$cols = count($this->sprite_names);

		// Read through the directory for all suitable images to add to our sprite
		if ($handle = opendir($this->folder))
		{
			while (false !== ($file = readdir($handle)))
			{
				$split = explode('.', $file);

				// Ignore non-matching file
				if ($file[0] == '.' || !isset($this->filetypes[$split[count($split) - 1]]) || (!in_array($split[0], $this->sprite_names[0])) && (isset($this->sprite_names[1]) && !in_array($split[0], $this->sprite_names[1])))
					continue;

				// Get image size and ensure it will fit in the sprite size defined
				$output = getimagesize($this->folder . '/' . $file);
				if (($output[0] > $this->size * $cols || $output[0] == 0) || ($output[1] > $this->size * $cols || $output[1] == 0))
					continue;

				// Image will be added to sprite, add to array
				$this->files[$split[0]] = array('name' => $file, 'x' => $output[0], 'y' => $output[1]);
			}

			closedir($handle);
		}
	}

	/**
	 * Creates the sprites
	 *
	 * Adds the files found in the directory, one by one to a sprite image
	 */
	public function create_sprite()
	{
		$cols = count($this->sprite_names);
		$items = 0;

		// If there are multiple rows/cols, which one has the most items
		for ($col = 0; $col < $cols; $col++)
		{
			$temp = count($this->sprite_names[$col]);
			$items = max($temp, $items);
		}

		// Set the height / width of the sprite image to fit the longest row/col
		if ($this->direction == 'vertical')
		{
			$xx = $this->size * $cols;
			$yy = $this->pitch * $items;
		}
		else
		{
			$xx = $this->pitch * $items;
			$yy = $this->size * $cols;
		}

		// Create the empty sprite image
		$im = imagecreatetruecolor($xx, $yy);

		// Add alpha channel for transparency
		imagesavealpha($im, true);
		$alpha = imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefill($im, 0, 0, $alpha);

		// For each column of the sprite
		for ($col = 0; $col < $cols; $col++)
		{
			$i = 0;

			// Append images to sprite
			foreach ($this->sprite_names[$col] as $sprite)
			{
				if (!isset($this->files[$sprite]))
				{
					echo 'Sprite file ', $sprite,' was not loaded (wrong size or name?)';
					$i++;
					continue;
				}

				$file = $this->files[$sprite];

				// Load the image
				$im2 = imagecreatefrompng($this->folder . '/' . $file['name']);

				// Add & position it in the sprite
				if ($this->direction == 'vertical')
				{
					$x = round(($this->size - ($file['x'])) / 2, 0);
					$x += $this->size * ($col) - $this->correction;
					$x = max($x, 0);
					$y = round($this->pitch * $i + $this->pitch / 2 - ($file['y'] / 2), 0);
					imagecopy($im, $im2, $x, $y, 0, 0, $file['x'], $file['y']);
				}
				else
				{
					$x = round($this->pitch * $i + $this->pitch / 2 - ($file['x'] / 2), 0) - $this->correction;
					$y = round(($this->size - $file['x']) / 2, 0);
					$y += $this->size * ($col);
					$y = max($y, 0);
					imagecopy($im, $im2, $x, $y, 0, 0, $file['x'], $file['y']);
				}

				$i++;
			}
		}

		// Save image to a file
		imagepng($im, $this->folder . '/' . $this->sprite . '.png');

		// Show the image
		header('Content-Type: image/png');
		imagepng($im);

		// Clean up
		imagedestroy($im);
		imagedestroy($im2);
	}

	/**
	 * These define the sprite image files
	 *
	 * sprite_key =
	 *	 sprint name to save,
	 *   direction to grow,
	 *   size (the size opposite the direction to grow),
	 *   pitch center to center distance of the sprite images
	 *	 initial offset (optional), if the first sprite position is not pitch/2
	 * sprite_names =
	 *   file name of the sprite to add, the order defined is the order they will be added
	 */
	private function _sprite_definition()
	{
		switch ($this->sprite)
		{
			case 'header':
				$this->sprite_key = array('header.png', 'vertical', 24, 24);
				$this->sprite_names = array(
					0 => array(
						'attachments',
						'buddies',
						'config',
						'contacts',
						'helptopics',
						'inbox',
						'login',
						'mail',
						'moderation',
						'plus',
						'posts',
						'profile',
						'search',
						'stats_info',
						'topics',
						'write',
						'database',
						'address',
						'calendar',
						'minus',
						'star',
						'clock',
						'eye',
						'piechart',
						'talk'
					)
				);
				break;
			case 'topicicons':
				$this->sprite_key = array('topicicons.png', 'vertical', 18, 18);
				$this->sprite_names = array(
					0 => array(
						'last_post',
						'docpoll',
						'profile',
						'locked',
						'move',
						'remove',
						'sticky',
						'sortdown',
						'sortup',
						'normal',
						'lockedsticky'
					),
					1 => array(
						'clip',
						'lamp',
						'poll',
						'question',
						'xx',
						'moved',
						'exclamation',
						'thumbup',
						'thumbdown',
						'last_post_rtl'
					)
				);
				break;
			case 'quickbuttons':
				$this->sprite_key = array('quickbuttons.png', 'vertical', 18, 24);
				$this->sprite_names = array(
					0 => array(
						'quote',
						'remove',
						'modify',
						'approve',
						'restore',
						'split',
						'reply',
						'notify',
						'unapprove',
						'close',
						'im_reply',
						'details',
						'ignore',
						'report',
						'warn',
						'quotetonew',
						'like',
						'unlike',
						'star',
						'quick_edit',
						'likes',
					)
				);
				break;
			case 'expcol':
				$this->sprite_key = array('expcol.png', 'horizontal', 110, 116);
				$this->sprite_names = array(
					0 => array(
						'collapse',
						'expand',
					)
				);
				break;
			case 'board_icons':
				$this->sprite_key = array('board_icons.png', 'horizontal', 48, 72, 12);
				$this->sprite_names = array(
					0 => array(
						'on',
						'on2',
						'off',
						'redirect',
						'new_some',
						'new_none',
						'new_redirect',
					)
				);
				break;
			case 'admin_sprite':
				$this->sprite_key = array('admin_sprite.png', 'horizontal', 16, 16);
				$this->sprite_names = array(
					0 => array(
						'administration',
						'attachment',
						'ban',
						'boards',
						'calendar',
						'corefeatures',
						'current_theme',
						'engines',
						'exit',
						'features',
						'languages',
						'logs',
						'mail',
						'maintain',
						'membergroups',
						'members',
						'modifications',
						'news',
						'packages',
						'paid',
						'permissions',
						'posts',
						'regcenter',
						'reports',
						'scheduled',
						'search',
						'security',
						'server',
						'smiley',
						'support',
						'themes'
					)
				);
				break;
			default:
				die('unknown sprite');
		}
	}
}

/**
 * Output the page, including css etc
 */
function show_header()
{
	echo '<!DOCTYPE html">
<html>
	<head>
		<meta name="robots" content="noindex" />
		<title>Sprite Generator</title>
		<style type="text/css" rel="stylesheet">
			body {
				background: #555;
				background-image: linear-gradient(to right, #333 0%, #888 50%, #333 100%);
				font: 93.75%/150% "Segoe UI", "Helvetica Neue", "Liberation Sans", "Nimbus Sans L", "Trebuchet MS", Arial, sans-serif;
				margin: 0;
				padding: 0;
				color: #666;
				font-size: 1em;
			}
			#top_section {
				margin: 0 0 10px;
				padding: 0;
				background: #f4f4f4;
				background-image: linear-gradient(to bottom, #fff, #eee);
				box-shadow: 0 1px 4px rgba(0,0,0,0.3), 0 1px 0 #3a642d inset;
				border-top: 4px solid #5ba048;
				border-bottom: 4px solid #3d6e32;
			}
			#header {
				padding: 22px 4% 12px 4%;
				color: 49643d;
				font-size: 2em;
				height: 40px;
			}
			#content {
				margin: 0 auto;
				width: 95%;
			}
			.panel {
				padding: .929em 1.2em;
				border: 3px solid #4b863c;
				margin-top: 16px;
				border-radius: 5px;
				background: #fafafa;
				box-shadow: 0 2px 4px #111;
				border-top: 3px solid #5ba048;
				border-bottom: 3px solid #437837;
			}
			.panel h3 {
				margin: 0;
				margin-bottom: 2ex;
				font-size: 1.5em;
				font-weight: normal;
			}
			form {
				margin: 0;
			}
			#footer_section {
				margin: 35px 0 0 0;
				color: #bbb;
				background: #222;
				border-top: 6px solid #3d6e32;
				box-shadow: 0 -1px 0 #777, 0 1px 0 #0e0e0e inset;
			}
			#footer_section .wrapper {
				padding: 20px 5px;
			}
			#footer_section a {
				font-size: 0.857em;
				color: #bbb;
			}
			#footer_section li {
				display: inline;
				padding-right: 5px;
			}
			#footer_section .copyright {
				display: inline;
				visibility: visible;
				font-family: Verdana, Arial, sans-serif;
				font-size: 0.857em;
			}
		</style>
	</head>
	<body>
		<div id="top_section">
			<div id="header">
				ElkArte Sprite Generator
			</div>
		</div>
		<div id="content" class="panel">
			<form action="', htmlspecialchars($_SERVER["PHP_SELF"]), '" method="post" accept-charset="UTF-8" name="sprite" id="sprite">
			<h3>Choose Sprite</h3>
			<div class="content">
				Choose the sprite you want to generate.
				The image files inside that directory will be used to create a new sprite file that you can copy to your theme.
				The resulting image will be output to the screen as well as written to the directory.
			</div>
			<h4 class="category_header">
				<select name="sprite">
					<option value="header">Header Sprite (images/icons/header.png)</option>
					<option value="board_icons">Board icons Sprite (images/_varient/board_icons.png)</option>
					<option value="quickbuttons">Quick buttons Sprite (images/theme/quickbuttons.png)</option>
					<option value="expcol">Expand Collapse Sprite (images/_varient/expcol.png)</option>
					<option value="admin_sprite">Admin Sprite (images/admin/admin_sprite.png)</option>
					<option value="topicicons">Topic Sprite (images/topic/topicicons.png)</option>
				 </select>
				<input type="submit" name="submit" class="button_submit" value="Create" />
			</h4>
		</div>
		<div id="footer_section">
			<div class="wrapper">
				<ul>
					<li class="copyright">
						<a href="http://www.elkarte.net/" title="ElkArte Community" target="_blank" class="new_win">ElkArte &copy; 2012 - 2014, ElkArte Community</a>
					</li>
				</ul>
			</div>
		</div>
	</body>
</html>';
}