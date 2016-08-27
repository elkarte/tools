<?php

/** **** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is http://code.mattzuba.com code.
 *
 * The Initial Developer of the Original Code is
 * Matt Zuba.
 * Portions created by the Initial Developer are Copyright (C) 2011
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *
 * ***** END LICENSE BLOCK ***** */

require_once('../SSI.php');
require_once('autoload.php');

if (!isset($_GET['continue']))
	unset($_SESSION['members']);

// This instantiates the object and autoruns it
$populate = new Populate();

/**
 * Populate Class
 *
 * @author mzuba
 */
class Populate
{
	private $blockSize = 150;
	private $refreshRate = 0;
	private $faker = null;
	private $counters = array();
	private $timeStart = 0;
	private $members = array();
	private $debug = false;

	/**
	 * Populator constructor
	 *
	 * @param array $options
	 */
	public function __construct($options = array())
	{
		$this->counters['categories']['max'] = 3;
		$this->counters['categories']['current'] = 0;
		$this->counters['boards']['max'] = 10;
		$this->counters['boards']['current'] = 0;
		$this->counters['members']['max'] = 500;
		$this->counters['members']['current'] = 0;
		$this->counters['topics']['max'] = 2500;
		$this->counters['topics']['current'] = 0;
		$this->counters['messages']['max'] = 25000;
		$this->counters['messages']['current'] = 0;
		$this->timeStart = microtime(true);

		$db = database();
		$end = false;

		// Override defaults as provided
		foreach ($options as $_key => $_value)
			$this->$_key = $_value;

		// Initialize faker with english provider
		$this->faker = Faker\Factory::create('en_EN');

		// Determine our 'currents' values so we only do as much work as needed
		foreach ($this->counters as $key => $val)
		{
			$request = $db->query('', '
				SELECT COUNT(*)
				FROM {db_prefix}' . $key,
				array()
			);
			list($this->counters[$key]['current']) = $db->fetch_row($request);
			$db->free_result($request);

			if (!empty($_SESSION['members']))
				$this->members = $_SESSION['members'];

			// Determine the data build function to call
			if ($key !== 'topics' && $this->counters[$key]['current'] < $this->counters[$key]['max'])
			{
				$func = '_make' . ucfirst($key);
				$end = false;
				break;
			}
			else
				$end = true;
		}

		// Call the appropriate function to build the data
		if (!empty($func))
			$this->$func();

		$this->_complete($end);
	}

	/**
	 * Creates the site categories
	 */
	private function _makeCategories()
	{
		require_once(SUBSDIR . '/Categories.subs.php');

		while ($this->counters['categories']['current'] < $this->counters['categories']['max'] && $this->blockSize--)
		{
			$catOptions = array(
				'move_after' => 0,
				'cat_name' => 'Category Number ' . ++$this->counters['categories']['current'],
				'is_collapsible' => 1,
			);

			++$this->counters['categories']['current'];

			if ($this->debug)
				echo $catOptions['cat_name'] . '<br>';
			else
				createCategory($catOptions);
		}

		$this->_pause();
	}

	/**
	 * Create the boards for the categories
	 */
	private function _makeBoards()
	{
		require_once(SUBSDIR . '/Boards.subs.php');

		while ($this->counters['boards']['current'] < $this->counters['boards']['max'] && $this->blockSize--)
		{
			++$this->counters['boards']['current'];
			$boardOptions = array(
				'board_name' => 'Board Number ' . ++$this->counters['boards']['current'],
				'board_description' => $this->faker->catchPhrase() . ' ' . $this->faker->bs(),
				'target_category' => mt_rand(1, $this->counters['categories']['current']),
				'move_to' => 'top',
				'id_profile' => 1,
			);

			if (mt_rand() < (mt_getrandmax() / 2))
			{
				$boardOptions = array_merge($boardOptions, array(
					'target_board' => $this->counters['boards']['current'] > 1 ? mt_rand(1, $this->counters['boards']['current'] - 1) : 1,
					'move_to' => 'child',
				));
			}

			if ($this->debug)
				echo $boardOptions['board_name'] . ' - ' . $boardOptions['board_description'] . '<br>';
			else
				createBoard($boardOptions);
		}

		$this->_pause();
	}

	/**
	 * Create member data
	 */
	private function _makeMembers()
	{
		require_once(SUBSDIR . '/Members.subs.php');

		while ($this->counters['members']['current'] < $this->counters['members']['max'] && $this->blockSize--)
		{
			$password = $this->faker->password;
			$regOptions = array(
				'interface' => 'admin',
				'real_name' => $this->faker->name,
				'username' => $this->faker->userName,
				'email' => $this->faker->email,
				'password' => $password,
				'password_check' => $password,
				'require' => 'nothing',
				'time' => $this->faker->unixTime,
			);

			++$this->counters['members']['current'];

			if ($this->debug)
				echo $regOptions['username'] . ' ' . $regOptions['email'] . '<br>';
			else
				registerMember($regOptions);

			$this->members[$this->counters['members']['current']] = array('username' => $regOptions['username'], 'email' => $regOptions['email']);
		}

		$this->_pause();
	}

	/**
	 * Creates post text and subject
	 */
	private function _makeMessages()
	{
		require_once(SUBSDIR . '/Post.subs.php');

		if (empty($this->members));
		{
			$db = database();

			$request = $db->query('', '
				SELECT id_member, member_name, email_address
				FROM {db_prefix}members
				WHERE id_member > 0',
				array()
			);
			while ($row = $db->fetch_assoc($request))
			{
				$this->members[$row['id_member']] = array(
					'username' => $row['member_name'],
					'email' => $row['email_address']
				);
			}
			$db->free_result($request);
			$this->counters['members']['max'] = count($this->members);
		}

		while ($this->counters['messages']['current'] < $this->counters['messages']['max'] && $this->blockSize--)
		{
			// Random "alice in wonderland" text of 100 to 1000 characters
			$msgOptions = array(
				'body' => trim($this->faker->realText(mt_rand(100, 1000), 5)),
				'approved' => true,
			);

			$topicOptions = array(
				'id' => $this->counters['topics']['current'] < $this->counters['topics']['max'] && mt_rand() < (int) (mt_getrandmax() * ($this->counters['topics']['max'] / $this->counters['messages']['max']))
					? 0
					: ($this->counters['topics']['current'] < $this->counters['topics']['max']
						? mt_rand(1, ++$this->counters['topics']['current'])
						: mt_rand(1, $this->counters['topics']['current'])),
				'board' => mt_rand(1, $this->counters['boards']['max']),
				'mark_as_read' => true,
			);

			// New topic, new subject or the existing one
			$msgOptions['subject'] = $this->_getSubject($topicOptions['id']);

			// Find a member we can associate with this post
			$member = array();
			while (empty($member))
			{
				$member_id = mt_rand(2, $this->counters['members']['max']);
				$member = isset($this->members[$member_id]) ? $this->members[$member_id] : array();
			}

			$posterOptions = array(
				'id' => $member_id,
				'name' => $member['username'],
				'email' => $member['email'],
				'update_post_count' => true,
				'ip' => $this->faker->ipv4,
			);

			if ($this->debug)
				echo $msgOptions['subject'] . '<br>' . $msgOptions['body'] . '<br>';
			else
				createPost($msgOptions, $topicOptions, $posterOptions);
		}

		$this->_pause();
	}

	/**
	 * Return the subject of a given topic, if the topic does not exist
	 * create a new subject
	 *
	 * @param $id_topic
	 *
	 * @return string
	 */
	private function _getSubject($id_topic)
	{
		$db = database();

		$request = $db->query('', '
			SELECT ms.subject
			FROM {db_prefix}topics AS t
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
				INNER JOIN {db_prefix}messages AS ms ON (ms.id_msg = t.id_first_msg)
			WHERE t.id_topic = {int:search_topic_id}
				AND {query_see_board}
			LIMIT 1',
			array(
				'search_topic_id' => $id_topic,
			)
		);
		$subject = '';
		if (!empty($db->num_rows($request)))
		{
			list ($subject) = $db->fetch_row($request);

			// Add 'Re: ' to the front of the subject.
			if (Util::strpos($subject, 'Re: ') !== 0)
				$subject = 'Re: ' . $subject;
		}
		$db->free_result($request);

		return !empty($subject) ? $subject : implode(' ', array_slice(explode(' ', $this->faker->realText(100, 3)), 0, mt_rand(3, 10)));
	}

	/**
	 * All done, clear the session
	 *
	 * @param boolean $end
	 */
	private function _complete($end)
	{
		if ($end)
		{
			$this->_fixupTopicsBoards();
			$this->_pause($end);
			unset($_SESSION['members']);
		}
	}

	/**
	 * Maintenance
	 */
	private function _fixupTopicsBoards()
	{
		$db = database();

		$db->query('', '
			UPDATE {db_prefix}messages AS mes, {db_prefix}topics AS top
			SET mes.id_board = top.id_board
			WHERE mes.id_topic = top.id_topic',
			array()
		);
	}

	/**
	 * Pause between sections or after every block count inserts
	 *
	 * @param bool|false $end
	 */
	private function _pause($end = false)
	{
		if (!$end)
		{
			$_SESSION['members'] = $this->members;
			header('Refresh: ' . $this->refreshRate . '; URL=' . $_SERVER['PHP_SELF'] . '?continue');

			// Pausing while we start again (server timeouts = bad)
			echo '
			Please wait while we refresh the page... <br />
			Stats so far:<br />';
		}
		else
			echo '
			Final stats:<br />';

		foreach ($this->counters as $key => $val)
		{
			echo '
			' . $val['current'] . ' of ' . $val['max'] . ' ' . $key . ' created<br />';
		}

		echo '
			Time taken for last request: ' . round(microtime(true) - $this->timeStart, 3) . ' seconds';

		if ($end)
			echo '
			<br /><br />
			<b>Completed</b>';
	}
}