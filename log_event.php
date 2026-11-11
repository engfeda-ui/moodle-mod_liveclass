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
 * Log behavioral presence and telemetry event from classroom client.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!confirm_sesskey()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid sesskey']);
    exit;
}

require_login();

$cmid      = required_param('cmid', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$eventtype = required_param('eventtype', PARAM_ALPHANUMEXT);
$details   = optional_param('details', '', PARAM_TEXT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'liveclass');
$context = context_module::instance($cm->id);
require_capability('mod/liveclass:view', $context);

// Verify session exists and belongs to this liveclass.
$session = $DB->get_record('liveclass_sessions', ['id' => $sessionid], '*', MUST_EXIST);
if ((int)$session->liveclassid !== (int)$cm->instance) {
    http_response_code(403);
    echo json_encode(['error' => 'IDOR guard triggered']);
    exit;
}

$logid = \mod_liveclass\attendance_engine::log_presence_event(
    $sessionid,
    $USER->id,
    $eventtype,
    $details
);

echo json_encode(['status' => 'success', 'logid' => $logid]);

