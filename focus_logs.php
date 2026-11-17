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
 * AJAX endpoint for retrieving detailed focus loss and tab-switching logs for a student.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB, $USER;

header('Content-Type: application/json; charset=utf-8');

$cmid      = required_param('cmid', PARAM_INT);
$target_id = required_param('userid', PARAM_INT);
require_sesskey();

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'liveclass');
$context = \context_module::instance($cm->id);
require_login($course, false, $cm);
require_capability('mod/liveclass:moderate', $context);

$user = $DB->get_record('user', ['id' => $target_id], 'id, firstname, lastname, email', MUST_EXIST);
$liveclass = $DB->get_record('liveclass', ['id' => $cm->instance], '*', MUST_EXIST);

$session = $DB->get_record('liveclass_sessions', ['liveclassid' => $liveclass->id, 'status' => 'active']);
if (!$session) {
    $session = $DB->get_record_sql('SELECT * FROM {liveclass_sessions} WHERE liveclassid = ? ORDER BY id DESC', [$liveclass->id], IGNORE_MULTIPLE);
}
$session_id = $session ? (int)$session->id : 0;

$logs = [];
if ($session_id > 0) {
    $records = $DB->get_records_select(
        'liveclass_presence_logs',
        'sessionid = :sid AND userid = :uid AND event_type IN (\'tab_hidden\', \'focus_lost\', \'tab_restored\')',
        ['sid' => $session_id, 'uid' => $target_id],
        'id DESC',
        'id, event_type, details, timestamp',
        0,
        50
    );

    foreach ($records as $r) {
        $type_label = '';
        if ($r->event_type === 'tab_hidden') {
            $type_label = get_string('focus_tab_hidden', 'mod_liveclass');
        } else if ($r->event_type === 'focus_lost') {
            $type_label = get_string('focus_window_lost', 'mod_liveclass');
        } else if ($r->event_type === 'tab_restored') {
            $type_label = get_string('focus_tab_restored', 'mod_liveclass');
        } else {
            $type_label = $r->event_type;
        }

        $logs[] = [
            'id'         => (int)$r->id,
            'time'       => userdate($r->timestamp, '%I:%M:%S %p'),
            'timestamp'  => (int)$r->timestamp,
            'event_type' => $r->event_type,
            'label'      => $type_label,
            'details'    => $r->details
        ];
    }
}

echo json_encode([
    'status'       => 'success',
    'student_name' => fullname($user),
    'count'        => count($logs),
    'logs'         => $logs
]);
exit;
