<?php
/*
    AutoMove by KuJoe <www.jmd.cc>
    Based off of the AutoExpunge plugin by The forum.kde.org team <kde-forum@nerdstock.org>
*/
function task_automove($task) {
    global $db,$mybb;
 
        $mvaction = array(
		'fid' => $mybb->settings['automv_mvfid'],
		'closed' => $mybb->settings['automv_close']
		);
        $caction = array(
		'closed' => $mybb->settings['automv_close']
		);

	$age = $mybb->settings['automv_age'] * 3600;
	$mvfid = $mybb->settings['automv_mvfid'];
	$forums = $mybb->settings['automv_fid'];

        if ($age && $mvfid > 0) {
            $db->update_query('threads', $mvaction, "fid IN ($forums) AND sticky ='0' and replies >= '{$mybb->settings['automv_replies']}' and (" . TIME_NOW . " - " . ($mybb->settings['automv_lastpost'] == 1 ? "lastpost" : "dateline") . ") > $age");

            // Rebuild forum count.
            require_once MYBB_ROOT . 'inc/functions_rebuild.php';
            rebuild_forum_counters($mybb->settings['automv_fid']);
            rebuild_forum_counters($mybb->settings['automv_mvfid']);
        }
        else {
            $db->update_query('threads', $caction, "fid IN ($forums) AND sticky ='0' and replies = '{$mybb->settings['automv_replies']}' and (" . TIME_NOW . " - " . ($mybb->settings['automv_lastpost'] == 1 ? "lastpost" : "dateline") . ") > $age");

            // Rebuild forum count.
            require_once MYBB_ROOT . 'inc/functions_rebuild.php';
            rebuild_forum_counters($mybb->settings['automv_fid']);
            rebuild_forum_counters($mybb->settings['automv_mvfid']);
        }

    add_task_log($task, 'The AutoMove task successfully ran.');
}
?>
