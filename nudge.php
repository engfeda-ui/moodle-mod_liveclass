<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AJAX endpoint for sending instant nudge alerts to students.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

$cmid = required_param('cmid', PARAM_INT);
$target_userid = required_param('userid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('liveclass', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$liveclass = $DB->get_record('liveclass', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/liveclass:moderate', $context);

$target_user = $DB->get_record('user', ['id' => $target_userid, 'deleted' => 0], '*', MUST_EXIST);

// Prepare notification message.
$classroom_url = new moodle_url('/mod/liveclass/view.php', ['id' => $cm->id]);
$a = (object)[
    'classname' => format_string($liveclass->name),
    'url'       => $classroom_url->out(false)
];
$msg_text = get_string('nudge_message_body', 'mod_liveclass', $a);

$message = new \core\message\message();
$message->component         = 'mod_liveclass';
$message->name              = 'nudge';
$message->userfrom          = $USER;
$message->userto            = $target_user;
$message->subject           = get_string('pluginname', 'mod_liveclass') . ': ' . format_string($liveclass->name);
$message->fullmessage       = $msg_text;
$message->fullmessageformat = FORMAT_PLAIN;
$message->fullmessagehtml   = '<div class="alert alert-warning"><strong>' . s($a->classname) . '</strong>: ' .
                              s($msg_text) . '<br><a href="' . s($a->url) . '" class="btn btn-primary mt-2">' .
                              get_string('join_now', 'mod_liveclass') . '</a></div>';
$message->smallmessage      = $msg_text;
$message->notification      = 1;
$message->contexturl        = $a->url;
$message->contexturlname    = format_string($liveclass->name);

$messageid = message_send($message);

echo json_encode([
    'status'    => 'success',
    'message'   => get_string('nudge_sent', 'mod_liveclass'),
    'messageid' => $messageid
]);
exit;
