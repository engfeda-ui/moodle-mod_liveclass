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
 * LiveKit Webhook Handler for automated attendance and session state tracking.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

$raw_body = file_get_contents('php://input');
if (empty($raw_body)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Empty payload']);
    exit;
}

$data = json_decode($raw_body, true);
if (!$data || !isset($data['event'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$event = $data['event'];
$room_name = $data['room']['name'] ?? '';
$participant = $data['participant'] ?? [];
$identity = $participant['identity'] ?? '';

// Resolve user id from identity (format: "user_42" or raw id).
$userid = 0;
if (preg_match('/user_(\d+)/', $identity, $matches)) {
    $userid = (int)$matches[1];
} else if (is_numeric($identity)) {
    $userid = (int)$identity;
}

// Find matching liveclass instance by room_name.
$liveclass = $DB->get_record('liveclass', ['room_name' => $room_name]);
if (!$liveclass) {
    http_response_code(404);
    echo json_encode(['status' => 'ignored', 'message' => 'Room not found in Moodle']);
    exit;
}

// Find or activate session.
$session = $DB->get_record('liveclass_sessions', [
    'liveclassid' => $liveclass->id,
    'status'      => 'active'
]);

if (!$session && $event === 'room_started') {
    $session = (object)[
        'liveclassid'        => $liveclass->id,
        'session_uuid'       => \core\uuid::generate(),
        'livekit_room_sid'   => $data['room']['sid'] ?? '',
        'actual_start'       => time(),
        'actual_end'         => 0,
        'status'             => 'active',
        'recording_url'      => '',
        'total_participants' => 1,
        'timecreated'        => time()
    ];
    $session->id = $DB->insert_record('liveclass_sessions', $session);
}

// Handle specific WebRTC telemetry events.
switch ($event) {
    case 'participant_joined':
        if ($session && $userid > 0) {
            \mod_liveclass\attendance_engine::participant_joined($session->id, $userid);
        }
        break;

    case 'participant_left':
        if ($session && $userid > 0) {
            \mod_liveclass\attendance_engine::participant_left($session->id, $userid);
        }
        break;

    case 'room_finished':
        if ($session) {
            $session->status = 'completed';
            $session->actual_end = time();
            $DB->update_record('liveclass_sessions', $session);
        }
        break;

    case 'egress_ended':
        if ($session && isset($data['egressInfo']['fileResults'][0]['downloadUrl'])) {
            $session->recording_url = clean_param($data['egressInfo']['fileResults'][0]['downloadUrl'], PARAM_URL);
            $DB->update_record('liveclass_sessions', $session);
        }
        break;
}

echo json_encode(['status' => 'success', 'event' => $event]);

