<?php
/*
	AutoMove by KuJoe <www.jmd.cc>
	Based off of the AutoExpunge plugin by The forum.kde.org team <kde-forum@nerdstock.org>
*/

// Don't allow direct initialization.
if (! defined('IN_MYBB')) {
	die('Nope.');
}

// The info for this plugin.
function automove_info() {
	return array(
		'name'		=> 'AutoMove',
		'description'	=> 'Automatically closes and/or moves threads from one forum to another after the threads become a certain age.',
		'website'		=> 'http://jmd.cc',
		'author'		=> 'KuJoe',
		'authorsite'	=> 'http://jmd.cc',
		'version'		=> '1.0',
		'compatibility'	=> '18*',
		'codename'		=> 'automove',
	);
}

function automove_activate() {
	global $db;
	$me = automove_info();

	$automove_group = array(
		'name'			=> 'automove',
		'title'			=> 'AutoMove',
		'description'	=> 'AutoMove Settings.',
		'disporder'		=> '99',
		'isdefault'		=> 'no'
	);

	$db->insert_query('settinggroups', $automove_group);
	$gid = $db->insert_id();

	$automove_setting_1 = array(
		'name'			=> 'automv_fid',
		'title'			=> 'Forum ID(s) to move from.',
		'description'	=> 'Enter the forum ID(s) that you want the threads moved from. (Comma seperated, 0 to disable.)',
		'optionscode'	=> 'text',
		'value'		=> '0',
		'disporder'		=> 1,
		'gid'			=> intval($gid)
	);

	$automove_setting_2 = array(
		'name'			=> 'automv_mvfid',
		'title'			=> 'Forum ID to move to.',
		'description'	=> 'Enter the forum ID that you want the threads moved to (0 to not move.)',
		'optionscode'	=> 'text',
		'value'		=> '0',
		'disporder'		=> 2,
		'gid'			=> intval($gid)
	);
	$automove_setting_3 = array(
		'name'			=> 'automv_age',
		'title'			=> 'Age',
		'description'	=> 'Enter how old threads must be to be moved. (In hours)',
		'optionscode'	=> 'text',
		'value'		=> '0',
		'disporder'		=> 3,
		'gid'			=> intval($gid)
	);
	$automove_setting_4 = array(
		'name'			=> 'automv_replies',
		'title'			=> 'Replies',
		'description'	=> 'Enter the minimum amount of replies a thread needs to be moved.',
		'optionscode'	=> 'text',
		'value'		=> '0',
		'disporder'		=> 4,
		'gid'			=> intval($gid)
	);
	$automove_setting_5 = array(
		"sid"			=> "0",
		"name"			=> "automv_lastpost",
		"title"			=> "Age Based",
		"description"	=> "Should age be based on the last post in the thread? (Otherwise based on the first post.)",
		"optionscode"	=> "onoff",
		"value"		=> '0',
		"disporder"		=> 5,
		"gid"			=> intval($gid),
	);
	$automove_setting_6 = array(
		"sid"			=> "0",
		"name"			=> "automv_close",
		"title"			=> "Close Thread",
		"description"	=> "Do you want to close the thread?",
		"optionscode"	=> "onoff",
		"value"		=> '0',
		"disporder"		=> 6,
		"gid"			=> intval($gid),
	);

	$db->insert_query('settings', $automove_setting_1);
	$db->insert_query('settings', $automove_setting_2);
	$db->insert_query('settings', $automove_setting_3);
	$db->insert_query('settings', $automove_setting_4);
	$db->insert_query('settings', $automove_setting_5);
	$db->insert_query('settings', $automove_setting_6);
	
	rebuild_settings();
	
	global $message;

	// Add task if task file exists, warn otherwise.
	if (! file_exists(MYBB_ROOT . "inc/tasks/{$me['codename']}.php")) {
		$message = "The {$me['name']} task file (<code>inc/tasks/{$me['codename']}.php</code>) does not exist. Install this first!";
	}
	else {
		automove_add_task();
	}
}
// Action to take to deactivate the plugin.
function automove_deactivate() {
	global $db, $mybb;

	$me = automove_info();

	// Remove task.

	// Switch modules and actions.
	$prev_module = $mybb->input['module'];
	$prev_action = $mybb->input['action'];
	$mybb->input['module'] = 'tools/tasks';
	$mybb->input['action'] = 'delete';

	// Fetch ID and title.
	$result = $db->simple_select('tasks', 'tid, title', "file = '{$me['codename']}'");
	while ($task = $db->fetch_array($result)) {
		// Log.
		log_admin_action($task['tid'], $task['title']);
	}

	// Delete.
	# or should I just disable the task here and not remove it until _deactivate()?
	$result = $db->delete_query('tasks', "file = '{$me['codename']}'");

	//Remove settings.
	$db->delete_query("settings","name='automv_fid'");
	$db->delete_query("settings","name='automv_mvfid'");
	$db->delete_query("settings","name='automv_age'");
	$db->delete_query("settings","name='automv_replies'");
	$db->delete_query("settings","name='automv_lastpost'");
	$db->delete_query("settings","name='automv_close'");
	$db->delete_query("settinggroups","name='automove'");
	
	// Reset module.
	$mybb->input['module'] = $prev_module;

	// Reset action.
	$mybb->input['action'] = $prev_action;

	// Log.
	log_admin_action($me['name']);
	
	rebuild_settings();

}

// Function to add task to task system.
function automove_add_task() {
	global $db, $mybb;

	require_once MYBB_ROOT . 'inc/functions_task.php';

	$me = automove_info();

	$result = $db->simple_select('tasks', 'count(tid) as count', "file = '{$me['codename']}'");
	if (! $db->fetch_field($result, 'count')) {
		// Switch modules and actions.
		$prev_module = $mybb->input['module'];
		$prev_action = $mybb->input['action'];
		$mybb->input['module'] = 'tools/tasks';
		$mybb->input['action'] = 'add';

		// Create task. Have it run every 15 minutes by default.
		$insert_array = array(
			'title'			=> $me['name'],
			'description'	=> "Moves threads from specified forums when they reach a specified age.",
			'file'			=> $me['codename'],
			'minute'		=> '15,45',
			'hour'			=> '*',
			'day'			=> '*',
			'month'			=> '*',
			'weekday'		=> '*',
			'lastrun'		=> 0,
			'enabled'		=> 1,
			'logging'		=> 1,
			'locked'		=> 0,
		);
		$insert_array['nextrun'] = fetch_next_run($insert_array);
		$result = $db->insert_query('tasks', $insert_array);
		$tid = $db->insert_id();

		log_admin_action($tid, $me['name']);

		// Reset module and action.
		$mybb->input['module'] = $prev_module;
		$mybb->input['action'] = $prev_action;

		return TRUE;
	}
	return FALSE;
}
?>
