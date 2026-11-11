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
 * Moderator room control actions endpoint.
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
$action = required_param('action', PARAM_ALPHA);
require_sesskey();

$cm = get_coursemodule_from_id('liveclass', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$liveclass = $DB->get_record('liveclass', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/liveclass:moderate', $context);

$session = $DB->get_record('liveclass_sessions', ['liveclassid' => $liveclass->id, 'status' => 'active']);
$session_id = $session ? (int)$session->id : 0;

switch ($action) {
    case 'muteall':
        // Log action in presence audit.
        if ($session_id > 0) {
            $log = (object)[
                'sessionid'    => $session_id,
                'userid'       => $USER->id,
                'event_type'   => 'mute_all',
                'event_data'   => json_encode(['moderator' => fullname($USER)]),
                'timecreated'  => time()
            ];
            $DB->insert_record('liveclass_presence_logs', $log);
        }

        echo json_encode([
            'status'  => 'success',
            'message' => get_string('mute_all_success', 'mod_liveclass')
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        break;
}
exit;
