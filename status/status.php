<?php

/**
 * @name      Elkarte Forum
 * @copyright Elkarte Forum contributors
 *
 * Forum slow? Having  performance problems?  This little blue pill will assist in finding the problem!
 *
 * This software is a derived product, based on:
 *
 * Simple Machines Forum (SMF)
 * copyright:	2011 Simple Machines (http://www.simplemachines.org)
 * license:  	BSD, See included LICENSE.TXT for terms and conditions.
 *
 * @version 1.0 Alpha
 */

// @todo eAccelerator, etc.?

initialize_inputs();

$command_line = php_sapi_name() === 'cli' && empty($_SERVER['REMOTE_ADDR']);

generate_status();

/**
 * Gets things started
 */
function initialize_inputs()
{
	global $db_prefix, $db_show_debug;

	// Turn off magic quotes runtime and enable error reporting.
	@set_magic_quotes_runtime(0);
	error_reporting(E_ALL);
	$db_show_debug = false;

	// All the places we may be running from (commonly)
	$possible = array(
		dirname(__FILE__),
		dirname(dirname(__FILE__)),
		dirname(dirname(dirname(__FILE__))),
		dirname(__FILE__) . '/forum',
		dirname(__FILE__) . '/forums',
		dirname(__FILE__) . '/community',
		dirname(dirname(__FILE__)) . '/forum',
		dirname(dirname(__FILE__)) . '/forums',
		dirname(dirname(__FILE__)) . '/community',
	);

	// Try to find ourslelfs
	foreach ($possible as $dir)
	{
		if (@file_exists($dir . '/SSI.php'))
			break;
	}

	if (!@file_exists($dir . '/Settings.php'))
	{
		// It's search time!  This could take a while!
		$possible = array(dirname(__FILE__));
		$checked = array();
		while (!empty($possible))
		{
			$dir = array_pop($possible);
			if (@file_exists($dir . '/SSI.php') && @file_exists($dir . '/Settings.php'))
				break;
			$checked[] = $dir;

			$dp = dir($dir);
			while ($entry = $dp->read())
			{
				// Do the parents last, children first.
				if ($entry == '..' && !in_array(dirname($dir), $checked))
					array_unshift($possible, dirname($dir));
				elseif (is_dir($dir . '/' . $entry) && $entry != '.' && $entry != '..')
					array_push($possible, $dir . '/' . $entry);
			}
			$dp->close();
		}

		if (!@file_exists($dir . '/Settings.php'))
			return;
	}

	// Found it, lets connect to the db
	require_once($dir . '/Settings.php');
	if (empty($db_persist))
		$db_connection = @mysql_connect($db_server, $db_user, $db_passwd);
	else
		$db_connection = @mysql_pconnect($db_server, $db_user, $db_passwd);

	if ($db_connection === false)
		$db_prefix = false;

	@mysql_select_db($db_name, $db_connection);
}

/**
 * Load in some server settings, time, load averages, etc.
 */
function get_linux_data()
{
	global $context;

	$context['current_time'] = strftime('%B %d, %Y, %I:%M:%S %p');

	$context['load_averages'] = @implode('', @get_file_data('/proc/loadavg'));
	if (!empty($context['load_averages']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $context['load_averages'], $matches) != 0)
		$context['load_averages'] = array($matches[1], $matches[2], $matches[3]);
	elseif (($context['load_averages'] = @`uptime 2>/dev/null`) != null && preg_match('~load average[s]?: (\d+\.\d+),? (\d+\.\d+),? (\d+\.\d+)~i', $context['load_averages'], $matches) != 0)
		$context['load_averages'] = array($matches[1], $matches[2], $matches[3]);
	else
		unset($context['load_averages']);

	// How about the cpu and speed ?
	$context['cpu_info'] = array(
		'frequency' => 'MHz',
	);
	$cpuinfo = @implode('', @get_file_data('/proc/cpuinfo'));
	if (!empty($cpuinfo))
	{
		// This only gets the first CPU!
		if (preg_match('~model name\s+:\s*([^\n]+)~i', $cpuinfo, $match) != 0)
			$context['cpu_info']['model'] = $match[1];

		if (preg_match('~cpu mhz\s+:\s*([^\n]+)~i', $cpuinfo, $match) != 0)
			$context['cpu_info']['hz'] = $match[1];
	}
	else
	{
		// Solaris, perhaps?
		$cpuinfo = @`psrinfo -pv 2>/dev/null`;
		if (!empty($cpuinfo))
		{
			if (preg_match('~clock (\d+)~', $cpuinfo, $match) != 0)
				$context['cpu_info']['hz'] = $match[1];

			$cpuinfo = explode("\n", $cpuinfo);
			if (isset($cpuinfo[2]))
				$context['cpu_info']['model'] = trim($cpuinfo[2]);
		}
		else
		{
			// Mac OS X?
			if (strpos(strtolower(PHP_OS), 'darwin') === 0)
			{
				$cpuinfo = @`sysctl machdep.cpu.brand_string 2>/dev/null`;
				if (preg_match('~machdep\.cpu\.brand_string:(.+)@([\s\d\.]+)(.+)~', $cpuinfo, $match) != 0)
				{
					$context['cpu_info']['model'] = trim($match[1]);
					$context['cpu_info']['hz'] = trim($match[2]);
					$context['cpu_info']['frequency'] = strtolower(trim($match[3])) == 'ghz' ? 'GHz' : 'MHz';
				}
			}

			// BSD?
			$cpuinfo = @`sysctl hw.model 2>/dev/null`;
			if (empty($context['cpu_info']['model']) && preg_match('~hw\.model:(.+)~', $cpuinfo, $match) != 0)
				$context['cpu_info']['model'] = trim($match[1]);

			$cpuinfo = @`sysctl dev.cpu.0.freq 2>/dev/null`;
			if (empty($context['cpu_info']['hz']) && preg_match('~dev\.cpu\.0\.freq:(.+)~', $cpuinfo, $match) != 0)
				$context['cpu_info']['hz'] = trim($match[1]);
		}
	}

	// Memory usage is also good to know
	$context['memory_usage'] = array();
	$meminfo = @get_file_data('/proc/meminfo');
	if (!empty($meminfo))
	{
		if (preg_match('~:\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)~', $meminfo[1], $matches) != 0)
		{
			$context['memory_usage']['total'] = $matches[1];
			$context['memory_usage']['used'] = $matches[2];
			$context['memory_usage']['free'] = $matches[3];
			/* $context['memory_usage']['shared'] = $matches[4] / 1024;
			  $context['memory_usage']['buffers'] = $matches[5] / 1024;
			  $context['memory_usage']['cached'] = $matches[6] / 1024; */
		}
		else
		{
			$mem = implode('', $meminfo);
			if (preg_match('~memtotal:\s*(\d+ [kmgb])~i', $mem, $match) != 0)
				$context['memory_usage']['total'] = memory_ReturnBytes($match[1]);
			if (preg_match('~memfree:\s*(\d+ [kmgb])~i', $mem, $match) != 0)
				$context['memory_usage']['free'] = memory_ReturnBytes($match[1]);
			if (isset($context['memory_usage']['total'], $context['memory_usage']['free']))
				$context['memory_usage']['used'] = $context['memory_usage']['total'] - $context['memory_usage']['free'];

			/* if (preg_match('~buffers:\s*(\d+ [kmgb])~i', $mem, $match) != 0)
			  $context['memory_usage']['buffers'] = memory_ReturnBytes($match[1]);
			  if (preg_match('~cached:\s*(\d+ [kmgb])~i', $mem, $match) != 0)
			  $context['memory_usage']['cached'] = memory_ReturnBytes($match[1]); */

			if (preg_match('~swaptotal:\s*(\d+ [kmgb])~i', $mem, $match) != 0)
				$context['memory_usage']['swap_total'] = memory_ReturnBytes($match[1]);
			if (preg_match('~swapfree:\s*(\d+ [kmgb])~i', $mem, $match) != 0)
				$context['memory_usage']['swap_free'] = memory_ReturnBytes($match[1]);
			if (isset($context['memory_usage']['swap_total'], $context['memory_usage']['swap_free']))
				$context['memory_usage']['swap_used'] = $context['memory_usage']['swap_total'] - $context['memory_usage']['swap_free'];
		}

		if (preg_match('~:\s+(\d+)\s+(\d+)\s+(\d+)~', $meminfo[2], $matches) != 0)
		{
			$context['memory_usage']['swap_total'] = $matches[1];
			$context['memory_usage']['swap_used'] = $matches[2];
			$context['memory_usage']['swap_free'] = $matches[3];
		}

		$meminfo = false;
	}
	// Maybe a generic free?
	elseif (empty($context['memory_usage']))
	{
		$meminfo = explode("\n", @`free -k 2>/dev/null | awk '{ if ($2 * 1 > 0) print $2, $3, $4; }'`);
		if (!empty($meminfo[0]))
		{
			$meminfo[0] = explode(' ', $meminfo[0]);
			$meminfo[1] = explode(' ', $meminfo[1]);
			$context['memory_usage']['total'] = $meminfo[0][0];
			$context['memory_usage']['used'] = $meminfo[0][1];
			$context['memory_usage']['free'] = $meminfo[0][2];
			$context['memory_usage']['swap_total'] = $meminfo[1][0];
			$context['memory_usage']['swap_used'] = $meminfo[1][1];
			$context['memory_usage']['swap_free'] = $meminfo[1][2];
		}
	}

	// Solaris, Mac OS X, or FreeBSD?
	if (empty($context['memory_usage']))
	{
		// Well, Solaris will have kstat.
		$meminfo = explode("\n", @`kstat -p unix:0:system_pages:physmem unix:0:system_pages:freemem 2>/dev/null | awk '{ print $2 }'`);
		if (!empty($meminfo[0]))
		{
			$pagesize = `/usr/bin/pagesize`;
			$context['memory_usage']['total'] = memory_ReturnBytes($meminfo[0] * $pagesize);
			$context['memory_usage']['free'] = memory_ReturnBytes($meminfo[1] * $pagesize);
			$context['memory_usage']['used'] = $context['memory_usage']['total'] - $context['memory_usage']['free'];

			$meminfo = explode("\n", @`swap -l 2>/dev/null | awk '{ if ($4 * 1 > 0) print $4, $5; }'`);
			$context['memory_usage']['swap_total'] = 0;
			$context['memory_usage']['swap_free'] = 0;
			foreach ($meminfo as $memline)
			{
				$memline = explode(' ', $memline);
				if (empty($memline[0]))
					continue;

				$context['memory_usage']['swap_total'] += $memline[0];
				$context['memory_usage']['swap_free'] += $memline[1];
			}
			$context['memory_usage']['swap_used'] = $context['memory_usage']['swap_total'] - $context['memory_usage']['swap_free'];
		}
	}

	if (empty($context['memory_usage']))
	{
		// FreeBSD should have hw.physmem.
		$meminfo = @`sysctl hw.physmem 2>/dev/null`;
		if (strpos(strtolower(PHP_OS), 'darwin') !== 0 && !empty($meminfo) && preg_match('~hw\.physmem: (\d+)~i', $meminfo, $match) != 0)
		{
			$context['memory_usage']['total'] = memory_ReturnBytes($match[1]);

			$meminfo = @`sysctl hw.pagesize vm.stats.vm.v_free_count 2>/dev/null`;
			if (!empty($meminfo) && preg_match('~hw\.pagesize: (\d+)~i', $meminfo, $match1) != 0 && preg_match('~vm\.stats\.vm\.v_free_count: (\d+)~i', $meminfo, $match2) != 0)
			{
				$context['memory_usage']['free'] = $match1[1] * $match2[1];
				$context['memory_usage']['used'] = $context['memory_usage']['total'] - $context['memory_usage']['free'];
			}

			$meminfo = @`swapinfo 2>/dev/null | awk '{ print $2, $4; }'`;
			if (preg_match('~(\d+) (\d+)~', $meminfo, $match) != 0)
			{
				$context['memory_usage']['swap_total'] = $match[1];
				$context['memory_usage']['swap_free'] = $match[2];
				$context['memory_usage']['swap_used'] = $context['memory_usage']['swap_total'] - $context['memory_usage']['swap_free'];
			}
		}
		// Let's guess Mac OS X?
		else
		{
			$meminfo = @`top -l1 2>/dev/null`;

			if (!empty($meminfo) && preg_match('~PhysMem:\s+(?:.+?)\s+([\d\.]+\w) used,\s+([\d\.]+\w) free~', $meminfo, $match) != 0)
			{
				$context['memory_usage']['used'] = memory_ReturnBytes($match[1]);
				$context['memory_usage']['free'] = memory_ReturnBytes($match[2]);
				$context['memory_usage']['total'] = $context['memory_usage']['used'] + $context['memory_usage']['free'];
			}
		}
	}

	// Can we obtain an uptime?
	$lastreboot = @get_file_data('/proc/uptime');
	if (!empty($lastreboot))
	{
		$lastreboot = explode(' ', $lastreboot[0]);
		$context['lastserverreboot'] = time() - trim($lastreboot[0]);
	}

	// Mac OS X and others?
	if (empty($context['lastserverreboot']))
	{
		$lastreboot = @`sysctl kern.boottime 2>/dev/null`;

		if (!empty($lastreboot) && preg_match('~kern\.boottime: { sec\s+=\s+(\d+),~', $lastreboot, $match) != 0)
			$context['lastserverreboot'] = $match[1];
	}

	// What OS are we running ?
	$context['operating_system']['type'] = 'unix';
	$check_release = array('centos', 'fedora', 'gentoo', 'redhat', 'slackware', 'yellowdog');
	foreach ($check_release as $os)
	{
		if (@file_exists('/etc/' . $os . '-release'))
			$context['operating_system']['name'] = implode('', get_file_data('/etc/' . $os . '-release'));
	}

	if (isset($context['operating_system']['name']))
		true;
	elseif (@file_exists('/etc/SuSE-release'))
	{
		$temp = get_file_data('/etc/SuSE-release');
		$context['operating_system']['name'] = trim($temp[0]);
	}
	elseif (@file_exists('/etc/release'))
	{
		$temp = get_file_data('/etc/release');
		$context['operating_system']['name'] = trim($temp[0]);
	}
	elseif (@file_exists('/etc/os-release'))
	{
		$temp = get_file_data('/etc/os-release');
		foreach ($temp as $info)
		{
			if (strpos($info, 'PRETTY_NAME=') !== false)
			{
				$context['operating_system']['name'] = trim(trim(str_replace('PRETTY_NAME=', '', $info)), '"');
				break;
			}
		}
	}
	elseif (@file_exists('/etc/debian_version'))
		$context['operating_system']['name'] = 'Debian ' . implode('', get_file_data('/etc/debian_version'));

	// Nothing found?
	if (empty($context['operating_system']['name']))
		$context['operating_system']['name'] = trim(@`uname -s -r 2>/dev/null`);

	// How many processes are running?
	$context['running_processes'] = array();
	$processes = @`ps auxc 2>/dev/null | awk '{ print $2, $3, $4, $8, $11, $12 }'`;
	if (empty($processes))
		$processes = @`ps aux 2>/dev/null | awk '{ print $2, $3, $4, $8, $11, $12 }'`;

	// Maybe it's Solaris?
	if (empty($processes))
		$processes = @`ps -eo pid,pcpu,pmem,s,fname 2>/dev/null | awk '{ print $1, $2, $3, $4, $5, $6 }'`;

	// Okay, how about QNX?
	if (empty($processes))
		$processes = @`ps -eo pid,pcpu,comm 2>/dev/null | awk '{ print $1, $2, 0, "", $5, $6 }'`;

	// If we found them, lets process them to something useful
	if (!empty($processes))
	{
		$processes = explode("\n", $processes);

		$context['num_zombie_processes'] = 0;
		$context['num_sleeping_processes'] = 0;
		$context['num_running_processes'] = 0;

		for ($i = 1, $n = count($processes) - 1; $i < $n; $i++)
		{
			$proc = explode(' ', $processes[$i], 5);
			$additional = @implode('', @get_file_data('/proc/' . $proc[0] . '/statm'));

			if ($proc[4][0] != '[' && strpos($proc[4], ' ') !== false)
				$proc[4] = strtok($proc[4], ' ');

			$context['running_processes'][$proc[0]] = array(
				'id' => $proc[0],
				'cpu' => $proc[1],
				'mem' => $proc[2],
				'title' => $proc[4],
			);

			if (strpos($proc[3], 'Z') !== false)
				$context['num_zombie_processes'] ++;
			elseif (strpos($proc[3], 'S') !== false)
				$context['num_sleeping_processes'] ++;
			else
				$context['num_running_processes'] ++;

			if (!empty($additional))
			{
				$additional = explode(' ', $additional);
				$context['running_processes'][$proc[0]]['mem_usage'] = $additional[0];
			}
		}

		$context['top_memory_usage'] = array('(other)' => array('name' => '(other)', 'percent' => 0, 'number' => 0));
		$context['top_cpu_usage'] = array('(other)' => array('name' => '(other)', 'percent' => 0, 'number' => 0));
		foreach ($context['running_processes'] as $proc)
		{
			$id = basename($proc['title']);

			if (!isset($context['top_memory_usage'][$id]))
				$context['top_memory_usage'][$id] = array('name' => $id, 'percent' => $proc['mem'], 'number' => 1);
			else
			{
				$context['top_memory_usage'][$id]['percent'] += $proc['mem'];
				$context['top_memory_usage'][$id]['number'] ++;
			}

			if (!isset($context['top_cpu_usage'][$id]))
				$context['top_cpu_usage'][$id] = array('name' => $id, 'percent' => $proc['cpu'], 'number' => 1);
			else
			{
				$context['top_cpu_usage'][$id]['percent'] += $proc['cpu'];
				$context['top_cpu_usage'][$id]['number'] ++;
			}
		}

		// @todo shared memory?
		foreach ($context['top_memory_usage'] as $proc)
		{
			if ($proc['percent'] >= 1 || $proc['name'] == '(other)')
				continue;

			unset($context['top_memory_usage'][$proc['name']]);
			$context['top_memory_usage']['(other)']['percent'] += $proc['percent'];
			$context['top_memory_usage']['(other)']['number'] ++;
		}

		foreach ($context['top_cpu_usage'] as $proc)
		{
			if ($proc['percent'] >= 0.6 || $proc['name'] == '(other)')
				continue;

			unset($context['top_cpu_usage'][$proc['name']]);
			$context['top_cpu_usage']['(other)']['percent'] += $proc['percent'];
			$context['top_cpu_usage']['(other)']['number'] ++;
		}
	}
}

/**
 * Windows is a bit special
 *
 * @global type $context
 */
function get_windows_data()
{
	global $context;

	// Time is easy
	$context['current_time'] = strftime('%B %d, %Y, %I:%M:%S %p');

	// Sysinfo, windows style
	$systeminfo = @`systeminfo /fo csv`;
	if (!empty($systeminfo))
	{
		$systeminfo = explode("\n", $systeminfo);
		$headings = explode('","', substr($systeminfo[0], 1, -1));
		$values = explode('","', substr($systeminfo[1], 1, -1));

		// CPU
		$context['cpu_info'] = array();
		if ($i = array_search('Processor(s)', $headings))
			if (preg_match('~\[01\]: (.+?) (\~?\d+) Mhz$~i', $values[$i], $match) != 0)
			{
				$context['cpu_info']['model'] = $match[1];
				$context['cpu_info']['hz'] = $match[2];
				$context['cpu_info']['frequency'] = '';
			}

		// Memory
		$context['memory_usage'] = array();
		if ($i = array_search('Total Physical Memory', $headings))
			$context['memory_usage']['total'] = windows_memsize($values[$i]);
		if ($i = array_search('Available Physical Memory', $headings))
			$context['memory_usage']['free'] = windows_memsize($values[$i]);
		if (isset($context['memory_usage']['total'], $context['memory_usage']['free']))
			$context['memory_usage']['used'] = $context['memory_usage']['total'] - $context['memory_usage']['free'];
		if ($i = array_search('Virtual Memory: Available', $headings))
			$context['memory_usage']['swap_total'] = windows_memsize($values[$i]);
		if ($i = array_search('Virtual Memory: In Use', $headings))
			$context['memory_usage']['swap_used'] = windows_memsize($values[$i]);
		if (isset($context['memory_usage']['swap_total'], $context['memory_usage']['swap_free']))
			$context['memory_usage']['swap_free'] = $context['memory_usage']['swap_total'] - $context['memory_usage']['swap_used'];
	}

	// Version, name, etc
	$context['operating_system']['type'] = 'windows';
	$context['operating_system']['name'] = `ver`;
	if (empty($context['operating_system']['name']))
		$context['operating_system']['name'] = 'Microsoft Windows';

	// Tasklist for processes
	$context['running_processes'] = array();
	$processes = @`tasklist /fo csv /v /nh`;
	if (!empty($processes))
	{
		$processes = explode("\n", $processes);
		$total_mem = 0;
		$total_cpu = 0;

		$context['num_zombie_processes'] = 0;
		$context['num_sleeping_processes'] = 0;
		$context['num_running_processes'] = 0;

		// Convert the tasklist to a nice summary
		foreach ($processes as $proc)
		{
			if (empty($proc))
				continue;

			$proc = explode('","', substr($proc, 1, -1));
			$proc[7] = explode(':', $proc[7]);
			$proc[7] = ($proc[7][0] * 3600) + ($proc[7][1] * 60) + $proc[7][2];

			if (substr($proc[4], -1) == 'K')
				$proc[4] = (int) $proc[4];
			elseif (substr($proc[4], -1) == 'M')
				$proc[4] = $proc[4] * 1024;
			elseif (substr($proc[4], -1) == 'G')
				$proc[4] = $proc[4] * 1024 * 1024;
			else
				$proc[4] = $proc[4] / 1024;

			$context['running_processes'][$proc[1]] = array(
				'id' => $proc[1],
				'cpu_time' => $proc[7],
				'mem_usage' => $proc[4],
				'title' => $proc[0],
			);

			if (strpos($proc[5], 'Not') !== false)
				$context['num_zombie_processes'] ++;
			else
				$context['num_running_processes'] ++;

			$total_mem += $proc[4];
			$total_cpu += $proc[7];
		}

		foreach ($context['running_processes'] as $proc)
		{
			$context['running_processes'][$proc['id']]['cpu'] = ($proc['cpu_time'] * 100) / $total_cpu;
			$context['running_processes'][$proc['id']]['mem'] = ($proc['mem_usage'] * 100) / $total_mem;
		}

		$context['top_memory_usage'] = array('(other)' => array('name' => '(other)', 'percent' => 0, 'number' => 0));
		$context['top_cpu_usage'] = array('(other)' => array('name' => '(other)', 'percent' => 0, 'number' => 0));
		foreach ($context['running_processes'] as $proc)
		{
			$id = basename($proc['title']);

			if (!isset($context['top_memory_usage'][$id]))
				$context['top_memory_usage'][$id] = array('name' => $id, 'percent' => $proc['mem'], 'number' => 1);
			else
			{
				$context['top_memory_usage'][$id]['percent'] += $proc['mem'];
				$context['top_memory_usage'][$id]['number'] ++;
			}

			if (!isset($context['top_cpu_usage'][$id]))
				$context['top_cpu_usage'][$id] = array('name' => $id, 'percent' => $proc['cpu'], 'number' => 1);
			else
			{
				$context['top_cpu_usage'][$id]['percent'] += $proc['cpu'];
				$context['top_cpu_usage'][$id]['number'] ++;
			}
		}

		// @todo shared memory?
		foreach ($context['top_memory_usage'] as $proc)
		{
			if ($proc['percent'] >= 1 || $proc['name'] == '(other)')
				continue;

			unset($context['top_memory_usage'][$proc['name']]);
			$context['top_memory_usage']['(other)']['percent'] += $proc['percent'];
			$context['top_memory_usage']['(other)']['number'] ++;
		}

		foreach ($context['top_cpu_usage'] as $proc)
		{
			if ($proc['percent'] >= 0.6 || $proc['name'] == '(other)')
				continue;

			unset($context['top_cpu_usage'][$proc['name']]);
			$context['top_cpu_usage']['(other)']['percent'] += $proc['percent'];
			$context['top_cpu_usage']['(other)']['number'] ++;
		}
	}
}

/**
 * For windows, we need to see what the system is reporting for installed memory
 */
function windows_memsize($str)
{
	$str = strtr($str, array(',' => ''));
	$check = strtolower(substr($str, -2));

	if ($check == 'gb')
		return $str * 1024 * 1024;
	elseif ($check == 'mb')
		return $str * 1024;
	elseif ($check == 'kb')
		return (int) $str;
	elseif ($check == ' b')
		return $str / 1024;
	else
		trigger_error('Unknown memory format \'' . $str . '\'', E_USER_NOTICE);
}

/**
 * MySQL data
 */
function get_mysql_data()
{
	global $context, $db_prefix;

	if (!isset($db_prefix) || $db_prefix === false)
		return;

	// Version of MySQL running
	$request = mysql_query("
		SELECT CONCAT(SUBSTRING(VERSION(), 1, LOCATE('.', VERSION(), 3)), 'x')");
	list ($context['mysql_version']) = mysql_fetch_row($request);
	mysql_free_result($request);

	// my.cnf configuration
	$request = mysql_query("
		SHOW VARIABLES");
	$context['mysql_variables'] = array();
	while ($row = @mysql_fetch_row($request))
		$context['mysql_variables'][$row[0]] = array(
			'name' => $row[0],
			'value' => $row[1],
		);
	@mysql_free_result($request);

	// Current status variables that provide information about mysql operation
	$request = mysql_query("
		SHOW GLOBAL STATUS");
	$context['mysql_status'] = array();
	while ($row = @mysql_fetch_row($request))
		$context['mysql_status'][$row[0]] = array(
			'name' => $row[0],
			'value' => $row[1],
		);
	@mysql_free_result($request);

	$context['mysql_num_sleeping_processes'] = 0;
	$context['mysql_num_locked_processes'] = 0;
	$context['mysql_num_running_processes'] = 0;

	// Get all threads that are running
	$request = mysql_query("
		SHOW FULL PROCESSLIST");
	$context['mysql_processes'] = array();
	while ($row = @mysql_fetch_assoc($request))
	{
		if ($row['State'] == 'Locked' || $row['State'] == 'Waiting for tables')
			$context['mysql_num_locked_processes'] ++;
		elseif ($row['Command'] == 'Sleep')
			$context['mysql_num_sleeping_processes'] ++;
		elseif (trim($row['Info']) == 'SHOW FULL PROCESSLIST' && $row['Time'] == 0 || trim($row['Info']) == '')
			$context['mysql_num_running_processes'] ++;
		else
		{
			$context['mysql_num_running_processes'] ++;

			$context['mysql_processes'][] = array(
				'id' => $row['Id'],
				'database' => $row['db'],
				'time' => $row['Time'],
				'state' => $row['State'],
				'query' => $row['Info'],
			);
		}
	}
	@mysql_free_result($request);

	$context['mysql_statistics'] = array();

	// Connections per second
	if (isset($context['mysql_status']['Connections'], $context['mysql_status']['Uptime']))
		$context['mysql_statistics'][] = array(
			'description' => 'Connections per second',
			'value' => $context['mysql_status']['Connections']['value'] / max(1, $context['mysql_status']['Uptime']['value']),
		);

	// Kilobytes received per second
	if (isset($context['mysql_status']['Bytes_received'], $context['mysql_status']['Uptime']))
		$context['mysql_statistics'][] = array(
			'description' => 'Kilobytes received per second',
			'value' => ($context['mysql_status']['Bytes_received']['value'] / max(1, $context['mysql_status']['Uptime']['value'])) / 1024,
		);

	// Kilobytes sent per second
	if (isset($context['mysql_status']['Bytes_sent'], $context['mysql_status']['Uptime']))
		$context['mysql_statistics'][] = array(
			'description' => 'Kilobytes sent per second',
			'value' => ($context['mysql_status']['Bytes_sent']['value'] / max(1, $context['mysql_status']['Uptime']['value'])) / 1024,
		);

	// Queries per second
	if (isset($context['mysql_status']['Questions'], $context['mysql_status']['Uptime']))
		$context['mysql_statistics'][] = array(
			'description' => 'Queries per second',
			'value' => $context['mysql_status']['Questions']['value'] / max(1, $context['mysql_status']['Uptime']['value']),
		);

	// Percentage of slow queries
	if (isset($context['mysql_status']['Slow_queries'], $context['mysql_status']['Questions']))
		$context['mysql_statistics'][] = array(
			'description' => 'Percentage of slow queries',
			'value' => $context['mysql_status']['Slow_queries']['value'] / max(1, $context['mysql_status']['Questions']['value']),
		);

	// Opened_tables vs Open_tables ratio
	if (isset($context['mysql_status']['Opened_tables']) && !empty($context['mysql_status']['Open_tables']['value']))
	{
		$value = $context['mysql_status']['Opened_tables']['value'] / max(1, $context['mysql_status']['Open_tables']['value']);
		if ($value > 1000)
		{
			$health = 2;
			$note = "Try increasing your table cache to " . formatBytes($context['mysql_status']['Open_tables']['value'] * 1.5) . ".";
		}
		elseif ($value > 100)
		{
			$health = 1;
			$note = "Try increasing your table cache to " . formatBytes($context['mysql_status']['Open_tables']['value'] * 1.5) . ".";
		}
		else
		{
			$health = 0;
			$note = '';
		}

		$context['mysql_statistics'][] = array(
			'description' => 'Opened vs. Open tables',
			'value' => $value,
			'setting' => 'table_cache',
			'health' => $health,
			'note' => $note,
			'explain' => "MySQL uses the table cache to keep tables open when database connections aren't using them. This makes future accesses to those tables faster."
		);
	}

	if (isset($context['mysql_status']['Opened_tables'], $context['mysql_variables']['table_cache']['value']))
	{
		$value = $context['mysql_status']['Open_tables']['value'] / max(1, $context['mysql_variables']['table_cache']['value']);
		if ($value > 0.95)
		{
			$health = 2;
			$note = "Your table cache is full. Try increasing your table cache to " . formatBytes($context['mysql_variables']['table_cache']['value'] * 1.5) . ".";
		}
		elseif ($value > 0.85)
		{
			$health = 1;
			$note = "Your table cache is nearly full. Consider increasing your table cache to " . formatBytes($context['mysql_variables']['table_cache']['value'] * 1.25) . ".";
		}
		elseif ($value < 0.5 && $context['mysql_variables']['table_cache']['value'] > 200)
		{
			$health = 1;
			$note = "Your table cache is more than half empty. Having a table cache bigger than needed uses memory that is likely better used elsewhere. Make sure MySQL has been running for some days before adjusting this downwards.";
		}
		else
		{
			$health = 0;
			$note = '';
		}
		$context['mysql_statistics'][] = array(
			'description' => 'Table cache usage',
			'value' => $value,
			'setting' => 'table_cache',
			'health' => $health,
			'note' => $note
		);
	}

	// Key Buffer ratios
	$kb_size = $context['mysql_variables']['key_buffer_size']['value'];
	$kb_size_min = 8192 * $context['mysql_variables']['key_cache_block_size']['value'];
	$mem_minus_overhead = $context['memory_usage']['total'] * 1024 * 0.8;
	$kb_size_mixed_with_innodb = floor($mem_minus_overhead * 0.1);
	$kb_size_mixed = floor($mem_minus_overhead * 0.2);
	$kb_size_max = floor($mem_minus_overhead * 0.4);
	if (isset($context['mysql_status']['Key_reads'], $context['mysql_status']['Key_read_requests']))
	{
		$value = max(1, $context['mysql_status']['Key_read_requests']['value']) / max(1, $context['mysql_status']['Key_reads']['value']);
		$filled = $context['mysql_status']['Key_blocks_unused']['value'] * $context['mysql_variables']['key_cache_block_size']['value'] < 0.1 * $context['mysql_variables']['key_buffer_size']['value'];
		if ($value < 20 && !$filled)
		{
			$health = 2;
			$note = "Your key buffer is too small. <b>This will cause performance issues. Fix this first.</b> ";

			// Give up... tell 'em to upgrade.
			if ($kb_size > $kb_size_max)
				$note .= "You should seriously consider increasing your system memory.";
			// Things are pretty serious... double it or go to max, whichever is lower
			elseif ($kb_size > $kb_size_mixed)
				$note .= "Try setting your key_buffer_size to " . formatBytes(min($kb_size_max + 1, $kb_size * 2)) . " if this is a database only machine, or consider increasing your system memory.";
			// Things are bad...
			elseif ($kb_size > $kb_size_mixed_with_innodb)
				$note .= "If you are not using InnoDB, try increasing your key_buffer_size to " . formatBytes(min($kb_size_mixed + 1, $kb_size * 2)) . ". Think about increasing your system memory.";
			// Probably never tuned...
			else
				$note .= "Try increasing your key_buffer_size to " . formatBytes(min($kb_size_mixed_with_innodb + 1, max($kb_size_min, $kb_size * 2))) . ".";
		}
		elseif ($value < 100 && !$filled)
		{
			$health = 1;
			$note = "Your key buffer would benefit from an increase. If MySQL hasn't been running long, wait a day before adjusting this value. ";

			// Give up... tell 'em to upgrade.
			if ($kb_size > $kb_size_max)
				$note .= "You should consider increasing your system memory.";
			// Things could be better... double it or go to max, whichever is lower
			elseif ($kb_size > $kb_size_mixed_with_innodb)
				$note .= "Try setting your key_buffer_size to " . formatBytes(min($kb_size_max + 1, $kb_size * 2)) . " if this is a database only machine, or consider increasing your system memory to increase performance.";
			// Things aren't too shabby, but there's still room
			elseif ($kb_size > $kb_size_mixed_with_innodb)
				$note .= "If you are not using InnoDB, try increasing your key_buffer_size to " . formatBytes(min($kb_size_mixed + 1, $kb_size * 2)) . ". Think about increasing your system memory.";
			// Probably never tuned...
			else
				$note .= "Try increasing your key_buffer_size to " .formatBytes(min($kb_size_mixed_with_innodb + 1, max($kb_size_min, $kb_size * 2))) . ".";
		}
		else
		{
			$health = 0;
			$note = '';
			if ($kb_size > $kb_size_min && $context['mysql_status']['Key_blocks_unused']['value'] > 2 * $context['mysql_status']['Key_blocks_used']['value'])
				$note = "Your key buffer is mostly empty. Try decreasing it to " . formatBytes(max($context['mysql_status']['Key_blocks_used']['value'] * $context['mysql_variables']['key_cache_block_size']['value'] * 2, $kb_size_min)) . ".";
		}

		$context['mysql_statistics'][] = array(
			'description' => 'MyISAM key buffer read hit rate',
			'value' => $value,
			'setting' => 'key_buffer_size',
			'health' => $health,
			'note' => $note,
			'explain' => "The MyISAM key buffer holds the indexes for MyISAM tables. The indexes help MySQL find the actual data in the table quickly. If the indexes aren't in memory, MySQL must load them from disk first, causing severe performance degredation."
		);
	}

	// This is useless for tunning, nice to report
	if (isset($context['mysql_status']['Key_writes'], $context['mysql_status']['Key_write_requests']))
		$context['mysql_statistics'][] = array(
			'description' => 'Key buffer write hit rate',
			'value' => $context['mysql_status']['Key_writes']['value'] / max(1, $context['mysql_status']['Key_write_requests']['value']),
			'setting' => 'key_buffer_size',
			'health' => 0,
			'note' => '',
		);

	// InnoDB buffer memory settings...
	$bs_size = $context['mysql_variables']['innodb_buffer_pool_size']['value'];
	$bs_size_min = 67108864;
	$bs_size_mixed_with_myisam = floor($mem_minus_overhead * 0.25);
	$bs_size_mixed = floor($mem_minus_overhead * 0.45);
	$bs_size_max = floor($mem_minus_overhead * 0.9);
	if (isset($context['mysql_status']['Innodb_buffer_pool_reads'], $context['mysql_status']['Innodb_buffer_pool_read_requests']))
	{
		$value = $context['mysql_status']['Innodb_buffer_pool_read_requests']['value'] / max(1, $context['mysql_status']['Innodb_buffer_pool_reads']['value']);
		if ($value < 50)
		{
			$health = 2;
			$note = "Your InnoDB buffer is too small. <b>This will cause serious performance issues. Fix this first.</b> ";

			// Give up... tell 'em to upgrade.
			if ($bs_size > $bs_size_max)
				$note .= "You should seriously consider increasing your system memory.";
			// Things are pretty serious... double it or go to max, whichever is lower
			elseif ($bs_size > $bs_size_mixed_with_myisam)
				$note .= "Try setting your innodb_buffer_pool_size to " . formatBytes(min($bs_size_max + 1, $bs_size * 2)) . " if this is a database only machine, or seriously consider increasing your system memory.";
			// Things are bad...
			elseif ($bs_size > $bs_size_mixed_with_myisam)
				$note .= "If you are not using MyISAM, try increasing your innodb_buffer_pool_size to " . formatBytes(min($bs_size_mixed + 1, $bs_size * 2)) . ". Think about increasing your system memory.";
			// Probably never tuned...
			else
				$note .= "Try increasing your innodb_buffer_pool_size to " . formatBytes(min($bs_size_mixed_with_myisam + 1, max($bs_size_min, $bs_size * 2))) . ".";
		}
		elseif ($value < 500)
		{
			$health = 1;
			$note = "Your InnoDB buffer would benefit from an increase. If MySQL hasn't been running long, wait a week before adjusting this value. ";

			// Give up... tell 'em to upgrade.
			if ($bs_size > $bs_size_max)
				$note .= "You should consider increasing your system memory.";
			// Things could be better... double it or go to max, whichever is lower
			elseif ($bs_size > $bs_size_mixed_with_myisam)
				$note .= "Try setting your innodb_buffer_pool_size to " . formatBytes(min($bs_size_max + 1, $bs_size * 2)) . " if this is a database only machine, or consider increasing your system memory to increase performance.";
			// Things aren't too shabby, but there's still room
			elseif ($bs_size > $bs_size_mixed_with_myisam)
				$note .= "If you are not using MyISAM, try increasing your innodb_buffer_pool_size to " . formatBytes(min($bs_size_mixed + 1, $bs_size * 2)) . ". Think about increasing your system memory.";
			// Probably never tuned...
			else
				$note .= "Try increasing your innodb_buffer_pool_size to " . formatBytes(max($bs_size_mixed_with_myisam + 1, max($bs_size_min, $bs_size * 2))) . ".";
		}
		else
		{
			$health = 0;
			$note = '';
		}

		$context['mysql_statistics'][] = array(
			'description' => 'InnoDB buffer pool read hit rate',
			'value' => $value,
			'setting' => 'innodb_buffer_pool_size',
			'health' => $health,
			'note' => $note,
			'explain' => "The InnoDB buffer pool holds both indexes <em>and</em> data for InnoDB tables. To ensure good performance, the buffer pool must be large enough to hold the hot areas in both indexes and data."
		);
	}

	// Threads
	if (isset($context['mysql_status']['Threads_created'], $context['mysql_status']['Connections']))
	{
		$value = $context['mysql_status']['Connections']['value'] / $context['mysql_status']['Threads_created']['value'];
		$running_long_enough = $context['mysql_status']['Threads_created']['value'] > 3 * $context['mysql_variables']['thread_cache_size']['value'];
		$thread_cache_suggest = max($context['mysql_variables']['max_connections']['value'], $context['mysql_variables']['thread_cache_size']['value'] * 2);
		if ($value < 5 && $running_long_enough)
		{
			$health = 2;
			$note = "MySQL is spending a lot of time creating threads. Try setting your thread_cache_size to $thread_cache_suggest.";
		}
		elseif ($value < 30 && $running_long_enough)
		{
			$health = 1;
			$note = "MySQL is spending a lot of time creating threads. Try setting your thread_cache_size to $thread_cache_suggest.";
		}
		else
		{
			// We can't really suggest setting this lower in case the forum has a bursty usage
			//  pattern -- we'd set it too low in such cases.
			$health = 0;
			$note = '';
		}

		$context['mysql_statistics'][] = array(
			'description' => 'Thread cache hit rate',
			'value' => $value,
			'setting' => 'thread_cache_size',
			'health' => $health,
			'note' => $note,
			'explain' => "Each connection to MySQL requires a thread. Threads can be re-used between connections if there is room in the cache to store the thread when not in use."
		);
	}

	if (isset($context['mysql_status']['Threads_created'], $context['mysql_variables']['thread_cache_size']))
	{
		$value = $context['mysql_status']['Threads_cached']['value'] / max(1, $context['mysql_variables']['thread_cache_size']['value']);
		if ($context['mysql_variables']['thread_cache_size']['value'] > $context['mysql_variables']['max_connections']['value'] && $running_long_enough)
		{
			$health = 2;
			$note = "Your thread cache is higher than the maximum number of connections and should be reduced to save memory. Reduce it to at most " . formatBytes($context['mysql_variables']['max_connections']['value']);
		}
		elseif ($value < 0.5 && $running_long_enough && $value !== 0)
		{
			$health = 1;
			$note = "Your thread cache is more than half empty. If you don't experience bursts of traffic, consider lowering your thread cache to " . (formatBytes($context['mysql_status']['Threads_cached']['value'] * 2)) . ".";
		}
		else
		{
			$health = 0;
			$note = '';
		}
		$context['mysql_statistics'][] = array(
			'description' => 'Thread cache usage',
			'value' => $value,
			'setting' => 'thread_cache_size',
			'health' => $health,
			'note' => $note
		);
	}

	// Temporary Tables
	if (isset($context['mysql_status']['Created_tmp_tables'], $context['mysql_status']['Created_tmp_disk_tables']))
	{
		$value = $context['mysql_status']['Created_tmp_disk_tables']['value'] / max(1, $context['mysql_status']['Created_tmp_tables']['value']);
		if ($value > 0.8)
		{
			$health = 2;
			$note = '';
		}
		elseif ($value > 0.4)
		{
			$health = 1;
			$note = '';
		}
		else
		{
			$health = 0;
			$note = '';
		}

		if ($health)
		{
			// Don't let a temporary table eat all the RAM...
			$max_tmp_table_size = min(floor($context['memory_usage']['total'] * 1024 / 8), max($context['mysql_variables']['max_heap_table_size']['value'] * 2, 33554432));
			if ($context['mysql_variables']['max_heap_table_size']['value'] < $max_tmp_table_size)
			{
				// Controls explicitly created temporary tables
				$note .= ' Try increasing your max_heap_table_size to ' . formatBytes($max_tmp_table_size);
			}

			if ($context['mysql_variables']['tmp_table_size']['value'] < $max_tmp_table_size)
			{
				// Controls internally created temporary tables
				$note .= ' Try increasing your tmp_table_size to ' . formatBytes($max_tmp_table_size);
			}
		}

		$context['mysql_statistics'][] = array(
			'description' => 'Temporary table disk usage',
			'value' => $value,
			'setting' => 'tmp_table_size, max_heap_table_size',
			'health' => $health,
			'note' => $note,
			'explain' => 'If a temporary table in memory exceeds the specified limits, it is pushed to disk. This can slow complex queries down.'
		);
	}

	// Don't suggest changing this. The default is fine. In fact, increasing it can make sorts SLOWER.
	// See http://www.mysqlperformanceblog.com/2007/08/18/how-fast-can-you-sort-data-with-mysql/
	if (isset($context['mysql_status']['Sort_merge_passes'], $context['mysql_status']['Sort_rows']))
		$context['mysql_statistics'][] = array(
			'description' => 'Sort merge pass rate',
			'value' => $context['mysql_status']['Sort_merge_passes']['value'] / max(1, $context['mysql_status']['Sort_rows']['value']),
			'setting' => 'sort_buffer',
			'max' => 0.001,
			'health' => 0,
			'note' => '',
	  );

	// Query cache
	$value = !empty($context['mysql_variables']['query_cache_type']['value']) ? (int) ($context['mysql_variables']['query_cache_type']['value'] == 'ON') : 0;
	if ($value != 1)
	{
		$health = 1;
		$note = "ElkArte benefits from having the query cache enabled. Unless you have a good reason to disable it, set it to ON.";
	}
	else
	{
		$health = 0;
		$note = '';
	}
	$context['mysql_statistics'][] = array(
		'description' => 'Query cache enabled',
		'value' => $value,
		'setting' => 'query_cache_type',
		'health' => $health,
		'note' => $note,
		'explain' => "The query cache is used avoid executing the same query again. If the tables the query accesses haven't changed, MySQL can return the cached results for the query."
	);

	if (isset($context['mysql_status']['Qcache_not_cached'], $context['mysql_status']['Com_select']))
	{
		$value = 1 - $context['mysql_status']['Qcache_hits']['value'] / max(1, $context['mysql_status']['Com_select']['value'] + $context['mysql_status']['Qcache_hits']['value']);
		if ($value == 1)
		{
			$health = 0;
			$note = 'You do not have any cached querys, is there any site traffic?';
		}
		elseif ($value > 0.7)
		{
			$health = 2;
			$note = "You are getting a lot of query cache misses. The query cache is likely too small. Try setting query_cache_size to " . formatBytes(($context['mysql_variables']['query_cache_size']['value'] * 2)) . ' and query_cache_limit to ' . formatBytes(max(4194304, floor($context['mysql_variables']['query_cache_size']['value'] / 4)));
		}
		elseif ($value > 0.6)
		{
			$health = 1;
			$note = "You are getting a fair amount of query cache misses. The query cache is possibly too small. Try setting query_cache_size to " . formatBytes(($context['mysql_variables']['query_cache_size']['value'] * 1.5)) . ' and query_cache_limit to ' . formatBytes(max(4194304, floor($context['mysql_variables']['query_cache_size']['value'] / 6)));
		}
		else
		{
			$health = 0;
			$note = '';
		}
		$context['mysql_statistics'][] = array(
			'description' => 'Query cache miss rate',
			'value' => $value,
			'setting' => 'query_cache_size, query_cache_limit',
			'health' => $health,
			'note' => $note,
			'explain' => "This value will report higher if caching is enable in ElkArte than if caching is not enabled. That's okay; ElkArte is simply caching some of the cacheable queries itself."
		);
	}

	if (isset($context['mysql_status']['Qcache_lowmem_prunes'], $context['mysql_status']['Com_select']))
	{
		$value = $context['mysql_status']['Qcache_lowmem_prunes']['value'] / max(1, $context['mysql_status']['Com_select']['value']);
		if ($value > 0.1)
		{
			$health = 2;
			$note = "You are getting a lot of query cache prunes. The query cache is too small. Try setting it to " . formatBytes(($context['mysql_variables']['query_cache_size']['value'] * 2));
		}
		elseif ($value > 0.5)
		{
			$health = 1;
			$note = "You are getting a fair amount of query cache prunes. The query cache is probably too small. Try setting it to " . formatBytes(($context['mysql_variables']['query_cache_size']['value'] * 1.5));
		}
		else
		{
			$health = 0;
			$note = '';
		}
		$context['mysql_statistics'][] = array(
			'description' => 'Query cache prune rate',
			'value' => $value,
			'setting' => 'query_cache_size',
			'health' => $health,
			'note' => $note
		);
	}
}

/**
 * Main routine to call the various status functions
 */
function generate_status()
{
	global $context, $command_line, $db_show_debug;

	$context['debug'] = empty($db_show_debug) ? false : true;
	show_header();

	if (strpos(strtolower(PHP_OS), 'win') === 0)
		get_windows_data();
	else
		get_linux_data();
	get_mysql_data();

	if ($command_line)
	{
		if (!empty($context['operating_system']['name']))
			echo 'Operating System:   ', trim($context['operating_system']['name']), "\n";
		if (!empty($context['cpu_info']))
			echo 'Processor:          ', trim($context['cpu_info']['model']), ' @ ', trim($context['cpu_info']['hz']), $context['cpu_info']['frequency'], ' (may report lower if power saving is enabled)', "\n";
		if ($context['debug'] && !empty($context['lastserverreboot']))
			echo 'Server Last Reboot:        ', date(DATE_RSS, $context['lastserverreboot']), "\n";
		if (isset($context['load_averages']))
			echo 'Load averages:      ', implode(', ', $context['load_averages']), "\n";
		if (!empty($context['running_processes']))
			echo 'Current processes:  ', count($context['running_processes']), ' (', !empty($context['num_sleeping_processes']) ? $context['num_sleeping_processes'] . ' sleeping, ' : '', $context['num_running_processes'], ' running, ', $context['num_zombie_processes'], ' zombie)', "\n";

		if (!empty($context['top_cpu_usage']))
		{
			echo 'Processes by CPU:   ';

			$temp = array();
			foreach ($context['top_cpu_usage'] as $proc)
				$temp[$proc['percent']] = $proc['name'] . ($proc['number'] > 1 ? ' (' . $proc['number'] . ') ' : ' ') . number_format($proc['percent'], 1) . '%';

			krsort($temp);
			echo implode(', ', $temp), "\n";
		}

		if (!empty($context['memory_usage']))
			echo 'Memory usage:       ', round(($context['memory_usage']['used'] * 100) / $context['memory_usage']['total'], 3), '% (', formatBytes($context['memory_usage']['used']), ' / ', formatBytes($context['memory_usage']['total']), ')', "\n";

		if (isset($context['memory_usage']['swap_used']))
			echo 'Swap usage:         ', round(($context['memory_usage']['swap_used'] * 100) / max(1, $context['memory_usage']['swap_total']), 3), '% (', formatBytes($context['memory_usage']['swap_used']), ' / ', formatBytes($context['memory_usage']['swap_total']), ')', "\n";

		if (!empty($context['mysql_processes']) || !empty($context['mysql_num_sleeping_processes']) || !empty($context['mysql_num_locked_processes']))
			echo 'MySQL processes:    ', $context['mysql_num_running_processes'] + $context['mysql_num_locked_processes'] + $context['mysql_num_sleeping_processes'], ' (', $context['mysql_num_sleeping_processes'], ' sleeping, ', $context['mysql_num_running_processes'], ' running, ', $context['mysql_num_locked_processes'], ' locked)', "\n";

		if (!empty($context['mysql_statistics']))
		{
			echo "\n", 'MySQL statistics:', "\n";

			foreach ($context['mysql_statistics'] as $stat)
			{
				$warning = (isset($stat['max']) && $stat['value'] > $stat['max']) || (isset($stat['min']) && $stat['value'] < $stat['min']);
				$warning = $warning ? '(should be ' . (isset($stat['min']) ? '>= ' . $stat['min'] . ' ' : '') . (isset($stat['max'], $stat['min']) ? 'and ' : '') . (isset($stat['max']) ? '<= ' . $stat['max'] : '') . ')' : '';

				echo sprintf('%-34s%-6.6s %34s', $stat['description'] . ':', round($stat['value'], 4), $warning), "\n";
			}
		}

		return;
	}

	echo '
		<div class="panel">
			<h2>Basic Information</h2>
			<div class="righttext">', $context['current_time'], '</div>
			<table class="status_table">';

	if (!empty($context['operating_system']['name']))
		echo '
				<tr>
					<th style="text-align: left;">Operating System:</th>
					<td>', $context['operating_system']['name'], '</td>
				</tr>';

	if (!empty($context['cpu_info']))
		echo '
				<tr>
					<th style="text-align: left;">Processor:</th>
					<td>', strtr($context['cpu_info']['model'], array('(R)' => '&reg;')), ' (', $context['cpu_info']['hz'], $context['cpu_info']['frequency'], ')</td>
				</tr>';

	if ($context['debug'] && !empty($context['lastserverreboot']))
		echo '
				<tr>
					<th style="text-align: left;">Server Last Reboot:</th>
					<td>', date(DATE_RSS, $context['lastserverreboot']), '</td>
				</tr>';

	if (isset($context['load_averages']))
		echo '
				<tr>
					<th style="text-align: left;">Load averages:</th>
					<td>', implode(', ', $context['load_averages']), '</td>
				</tr>';

	if (!empty($context['running_processes']))
		echo '
				<tr>
					<th style="text-align: left;">Current processes:</th>
					<td>', count($context['running_processes']), ' (', !empty($context['num_sleeping_processes']) ? $context['num_sleeping_processes'] . ' sleeping, ' : '', $context['num_running_processes'], ' running, ', $context['num_zombie_processes'], ' zombie)</td>
				</tr>';

	if (!empty($context['top_cpu_usage']))
	{
		echo '
				<tr>
					<th style="text-align: left;">Processes by CPU:</th>
					<td>';

		$temp = array();
		foreach ($context['top_cpu_usage'] as $proc)
			$temp[$proc['percent']] = htmlspecialchars($proc['name']) . ' <em>(' . $proc['number'] . ')</em> ' . number_format($proc['percent'], 1) . '%';

		krsort($temp);
		echo implode(', ', $temp);

		echo '
					</td>
				</tr>';
	}

	if (!empty($context['memory_usage']))
	{
		echo '
				<tr>
					<th style="text-align: left;">Memory usage:</th>
					<td>
						', round(($context['memory_usage']['used'] * 100) / $context['memory_usage']['total'], 3), '% (', formatBytes($context['memory_usage']['used']), ' / ', formatBytes($context['memory_usage']['total']), ')';
		if (isset($context['memory_usage']['swap_used']))
			echo '<br />
						Swap: ', round(($context['memory_usage']['swap_used'] * 100) / max(1, $context['memory_usage']['swap_total']), 3), '% (', formatBytes($context['memory_usage']['swap_used']), ' / ', formatBytes($context['memory_usage']['swap_total']), ')';
		echo '
					</td>
				</tr>';
	}

	echo '
			</table>
		</div>';

	if (!empty($context['mysql_processes']) || !empty($context['mysql_num_sleeping_processes']) || !empty($context['mysql_num_locked_processes']))
	{
		echo '
		<div class="panel">
			<h2>MySQL processes</h2>

			<table class="status_table">
				<tr>
					<th style="text-align: left;">Total processes:</th>
					<td>', $context['mysql_num_running_processes'] + $context['mysql_num_locked_processes'] + $context['mysql_num_sleeping_processes'], ' (', $context['mysql_num_sleeping_processes'], ' sleeping, ', $context['mysql_num_running_processes'], ' running, ', $context['mysql_num_locked_processes'], ' locked)</td>
				</tr>
			</table>';

		if (!empty($context['mysql_processes']))
		{
			echo '
			<br />
			<h2>Running processes</h2>

			<table class="status_table">
				<tr>
					<th style="width: 14ex;">State</th>
					<th style="width: 8ex;">Time</th>
					<th>Query</th>
				</tr>';

			foreach ($context['mysql_processes'] as $proc)
			{
				echo '
				<tr>
					<td>', $proc['state'], '</td>
					<td style="text-align: center;">', $proc['time'], 's</td>
					<td><div style="width: 100%; ', strpos($_SERVER['HTTP_USER_AGENT'], 'Gecko') !== false ? 'max-' : '', 'height: 7em; overflow: auto;"><pre style="margin: 0; border: 1px solid gray;">';

				$temp = explode("\n", $proc['query']);
				$min_indent = 0;
				foreach ($temp as $line)
				{
					preg_match('/^(\t*)/', $line, $x);
					if (strlen($x[0]) < $min_indent || $min_indent == 0)
						$min_indent = strlen($x[0]);
				}

				if ($min_indent > 0)
				{
					$proc['query'] = '';
					foreach ($temp as $line)
						$proc['query'] .= preg_replace('~^\t{0,' . $min_indent . '}~i', '', $line) . "\n";
				}

				// Now, let's clean up the query.
				$clean = '';
				$old_pos = 0;
				$pos = -1;
				while (true)
				{
					$pos = strpos($proc['query'], '\'', $pos + 1);
					if ($pos === false)
						break;
					$clean .= substr($proc['query'], $old_pos, $pos - $old_pos);

					$str_pos = $pos;
					while (true)
					{
						$pos1 = strpos($proc['query'], '\'', $pos + 1);
						$pos2 = strpos($proc['query'], '\\', $pos + 1);
						if ($pos1 === false)
							break;
						elseif ($pos2 == false || $pos2 > $pos1)
						{
							$pos = $pos1;
							break;
						}

						$pos = $pos2 + 1;
					}
					$str = substr($proc['query'], $str_pos, $pos - $str_pos + 1);
					$clean .= strlen($str) < 12 ? $str : '\'%s\'';

					$old_pos = $pos + 1;
				}
				$clean .= substr($proc['query'], $old_pos);

				echo strtr(htmlspecialchars($clean), array("\n" => '<br />', "\r" => ''));

				echo '</pre></div></td>
				</tr>';
			}

			echo '
			</table>';
		}

		echo '
		</div>';
	}

	if (!empty($context['mysql_statistics']))
	{
		echo '
		<div class="panel">
			<h2>MySQL Statistics</h2>

			<div class="righttext">MySQL ', $context['mysql_version'], '</div>
			<div class="roundframe">It is extremely important you fully understand each change you make to a MySQL database server. If you don\'t understand the output, or if you don\'t understand the recommendations, you should consult a knowledgeable DBA or system administrator that you trust. Always test your changes on staging environments, and always keep in mind that improvements in one area can negatively affect MySQL in other areas.</div>
			<table class="status_table">';

		// Has this server been running less than 1 day?
		if (!empty($context['mysql_status']['Uptime']['value']) && $context['mysql_status']['Uptime']['value'] < 86400)
			echo '
				<tr>
					<th  colspan="2" style="color:red;">We have detected MySQL was restarted less than 24 Hours ago. These recommendations may not be accurate.</th>
				</tr>';

		foreach ($context['mysql_statistics'] as $stat)
		{
			echo '
				<tr>
					<th style="text-align: left;">';

			// Good, Bad or Ugly
			if (isset($stat['health']))
			{
			  if ($stat['health'] == 0)
				echo '<i class="fa fa-check good"></i>';
			  elseif ($stat['health'] == 1)
				echo '<i class="fa fa-exclamation-triangle pass"></i>';
			  else
				echo '<i class="fa fa-times bad"></i>';
			}

			echo $stat['description'], ':', isset($stat['setting']) ? '<br />
						<em style="font-size: smaller;">(' . $stat['setting'] . ')</em>' : '', '
					</th>
					<td>
						';

			echo round($stat['value'], 4);

			if (isset($stat['note']) && strlen($stat['note']) > 0)
			  echo ' ' . $stat['note'];

			if (isset($stat['explain']))
			  echo ' ' . $stat['explain'];

			echo '
					</td>
				</tr>';
		}

		echo '
			</table>';

		if (isset($_GET['mysql_info']))
		{
			echo '
			<br />
			<h2>MySQL status</h2>

			<table width="100%" cellpadding="2" cellspacing="0" border="0">';

		foreach ($context['mysql_status'] as $var)
		{
			echo '
				<tr>
					<th style="text-align: left;">', $var['name'], ':</th>
					<td>', $var['value'], '</td>
				</tr>';
		}

		echo '
			</table>

			<br />
			<h2>MySQL variables</h2>

			<table class="status_table>';

		foreach ($context['mysql_variables'] as $var)
		{
			echo '
				<tr>
					<th style="text-align: left;">', $var['name'], ':</th>
					<td>', $var['value'], '</td>
				</tr>';
		}

		echo '
			</table>';
		}
		else
			echo '
			<br />
			<a href="', $_SERVER['PHP_SELF'], '?mysql_info=1">Show more information...</a><br />';

		echo '
		</div>';
	}

	show_footer();
}

/**
 * Output the page header, including css etc
 */
function show_header()
{
	global $command_line;

	if ($command_line)
		return;

	echo '<!DOCTYPE html">
<html>
	<head>
		<meta name="robots" content="noindex" />
		<title>Server Status</title>
		<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
		<style type="text/css" rel="stylesheet">
			body {
				background: #555;
				background-image: linear-gradient(to right, #333 0%, #888 50%, #333 100%);
				font: 93.75%/150% "Segoe UI", "Helvetica Neue", "Liberation Sans", "Nimbus Sans L", "Trebuchet MS", Arial, sans-serif;
				margin: 0;
				padding: 0;
			}
			body, td, th {
				color: #666;
				font-size: 1em;
			}
			td, th {
				vertical-align: top;
				padding: .2em 0;
				word-wrap: break-word;
				-webkit-hyphens: auto;
				-moz-hyphens: auto;
				-ms-hyphens: auto;
			}
			th {
				width: 30%;
				min-width: 26em;
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
				padding: 22px 4% 12px 4%;
				color: 49643d;
				font-size: 2em;
				height: 40px;
			}
			#header img {
			    float: right;
				margin-top: -15px;
			}
			#content {
				margin: 0 auto;
				width: 95%;
			}
			.roundframe {
				overflow: hidden;
				margin: 2px 0 0 0;
				padding: 9px;
				border: 1px solid #c5c5c5;
				border-radius: 7px;
				background: #f5f5f5;
			}
			.status_table {
				width: 100%;
				padding: 0 .5em;
				margin: 0:
				border: 0;
				table-layout: fixed
			}
			.error_message {
				border: 2px dashed red;
				background-color: #e1e1e1;
				margin: 1ex 4ex;
				padding: 1.5em;
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
			.panel h2 {
				margin: 0;
				margin-bottom: 0.5ex;
				padding-bottom: 3px;
				border-bottom: 1px dashed black;
				font-size: 1.5em;
				font-weight: normal;
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
			td.textbox {
				padding-top: 2px;
				font-weight: bold;
				white-space: nowrap;
				padding-right: 2ex;
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
			.good, .bad, .pass {
				padding: 0 5px 0 0;
			}
			.good {
				color: green;
			}
			.pass {
				color: orange;
			}
			.bad {
				color: red;
			}
		</style>
	</head>
	<body>
		<div id="top_section">
			<div id="header">
				Server Status
				', file_exists(dirname(__FILE__) . '/themes/default/images/logo.png') ? '<a href="http://www.elkarte.net/" target="_blank"><img src="themes/default/images/logo.png" alt="ElkArte" border="0" /></a>
				' : '', '
			</div>
		</div>
		<div id="content">';
}

/**
 * Ends the template
 */
function show_footer()
{
	global $command_line;

	if ($command_line)
		return;

	echo '
		</div>
	</body>
</html>';
}

/**
 * List a files contents either on windows or *nix
 * Cleans any out of range characters from the filename to prevent os errors
 *
 * @param string $filename
 */
function get_file_data($filename)
{
	$data = @file($filename);
	if (is_array($data))
		return $data;

	if (strpos(strtolower(PHP_OS), 'win') !== false)
		@exec('type ' . preg_replace('~[^/a-zA-Z0-9\-_:]~', '', $filename), $data);
	else
		@exec('cat ' . preg_replace('~[^/a-zA-Z0-9\-_:]~', '', $filename) . ' 2>/dev/null', $data);

	if (!is_array($data))
		return false;

	foreach ($data as $k => $dummy)
		$data[$k] .= "\n";

	return $data;
}

/**
 * Format bytes to something more readable
 *
 * @param int $bytes
 * @param int $precision
 */
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Convert a formatted memory value to bytes
 *
 * @param string $val
 */
function memory_ReturnBytes($val)
{
	if (is_integer($val))
		return $val;

	// Separate the number from the designator
	$val = trim($val);
	$num = intval(substr($val, 0, strlen($val) - 1));
	$last = strtolower(substr($val, -1));

	// Convert to bytes
	switch ($last)
	{
		// fall through select g = 1024*1024*1024
		case 'g':
			$num *= 1024;
		// fall through select m = 1024*1024
		case 'm':
			$num *= 1024;
		case 'k':
			$num *= 1024;
	}

	return $num;
}