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
 * AJAX endpoint for real-time participant heartbeat, attendance interval tracking, and classroom telemetry.
 *
 * @package    mod_liveclass
    * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

if (!confirm_sesskey()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid sesskey']);
    exit;
}

require_login();

$cmid      = required_param('cmid', PARAM_INT);
$sessionid = required_param('sessionid', PARAM_INT);
$action    = optional_param('action', 'heartbeat', PARAM_ALPHA);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'liveclass');
$context = context_module::instance($cm->id);
require_capability('mod/liveclass:view', $context);

// Verify session belongs to this liveclass instance.
$session = $DB->get_record('liveclass_sessions', ['id' => $sessionid], '*', MUST_EXIST);
if ((int)$session->liveclassid !== (int)$cm->instance) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'IDOR guard triggered']);
    exit;
}

$now = time();
$userid = (int)$USER->id;

switch ($action) {
    case 'join':
        \mod_liveclass\attendance_engine::participant_joined($sessionid, $userid, $now);
        \mod_liveclass\attendance_engine::log_presence_event($sessionid, $userid, 'joined_class', 'Participant entered virtual stage');

        echo json_encode([
            'status'  => 'success',
            'action'  => 'join',
            'time'    => $now,
            'message' => 'Joined classroom successfully'
        ]);
        break;

    case 'leave':
        \mod_liveclass\attendance_engine::participant_left($sessionid, $userid, $now);
        \mod_liveclass\attendance_engine::log_presence_event($sessionid, $userid, 'left_class', 'Participant exited virtual stage');

        echo json_encode([
            'status'  => 'success',
            'action'  => 'leave',
            'time'    => $now,
            'message' => 'Left classroom successfully'
        ]);
        break;

    case 'heartbeat':
    default:
        $mic_active   = optional_param('mic', 1, PARAM_INT);
        $cam_active   = optional_param('cam', 1, PARAM_INT);
        $hand_raised  = optional_param('hand', 0, PARAM_INT);
        $screen_share = optional_param('screen', 0, PARAM_INT);

        $client_state = [
            'mic'    => (bool)$mic_active,
            'cam'    => (bool)$cam_active,
            'hand'   => (bool)$hand_raised,
            'screen' => (bool)$screen_share
        ];

        $result = \mod_liveclass\attendance_engine::heartbeat($sessionid, $userid, $now, $client_state);

        // Synchronize client-reported hand raise state with presence logs.
        $last_hand_event = $DB->get_record_sql("
            SELECT id, event_type 
              FROM {liveclass_presence_logs}
             WHERE sessionid = :sid 
               AND userid = :uid 
               AND event_type IN ('raise_hand', 'lower_hand', 'left_class')
             ORDER BY id DESC
        ", ['sid' => $sessionid, 'uid' => $userid], IGNORE_MULTIPLE);

        if ($hand_raised && (!$last_hand_event || $last_hand_event->event_type !== 'raise_hand')) {
            \mod_liveclass\attendance_engine::log_presence_event($sessionid, $userid, 'raise_hand', 'Student raised hand via heartbeat');
        } else if (!$hand_raised && $last_hand_event && $last_hand_event->event_type === 'raise_hand') {
            \mod_liveclass\attendance_engine::log_presence_event($sessionid, $userid, 'lower_hand', 'Student lowered hand via heartbeat');
        }

        // Check for recent commands (like mute all in last 25 seconds).
        $recent_mute = $DB->record_exists_select(
            'liveclass_presence_logs',
            'sessionid = :sid AND event_type = :ev AND timestamp > :recent',
            ['sid' => $sessionid, 'ev' => 'mute_all', 'recent' => $now - 25]
        );

        // Check if recording is active.
        $recent_rec_start = (int)$DB->get_field_sql(
            'SELECT MAX(timestamp) FROM {liveclass_presence_logs} WHERE sessionid = :sid AND event_type = :ev',
            ['sid' => $sessionid, 'ev' => 'recording_started']
        );
        $recent_rec_stop = (int)$DB->get_field_sql(
            'SELECT MAX(timestamp) FROM {liveclass_presence_logs} WHERE sessionid = :sid AND event_type = :ev',
            ['sid' => $sessionid, 'ev' => 'recording_stopped']
        );
        $is_recording = ($recent_rec_start > 0 && $recent_rec_start > $recent_rec_stop);

        // Fetch active classmates count.
        $recent_threshold = $now - 90;
        $active_count = (int)$DB->get_field_sql("
            SELECT COUNT(DISTINCT a.userid)
              FROM {liveclass_intervals} i
              JOIN {liveclass_attendance} a ON a.id = i.attendanceid
             WHERE a.sessionid = :sessionid
               AND ((i.leave_time = 0 AND (a.timemodified > :recent OR i.join_time > :recent2))
                    OR i.leave_time > :recent3)
        ", [
            'sessionid' => $sessionid,
            'recent'    => $recent_threshold,
            'recent2'   => $recent_threshold,
            'recent3'   => $recent_threshold
        ]);

        // Fetch roster and participants state for live Gallery Grid.
        $roster = \mod_liveclass\roster_manager::get_roster_data($cm, $liveclass);
        $raised_hands = $DB->get_records_sql("
            SELECT p1.userid 
              FROM {liveclass_presence_logs} p1
             WHERE p1.sessionid = :sid 
               AND p1.timestamp > :recent
               AND p1.id = (SELECT MAX(p2.id) FROM {liveclass_presence_logs} p2 
                             WHERE p2.sessionid = :sid2 AND p2.userid = p1.userid 
                               AND p2.event_type IN ('raise_hand', 'lower_hand', 'left_class'))
               AND p1.event_type = 'raise_hand'
        ", ['sid' => $sessionid, 'sid2' => $sessionid, 'recent' => $now - 600]);

        $participants = [];
        if (!empty($roster->students)) {
            foreach ($roster->students as $st) {
                $is_connected = ($st->status === 'in_class');
                $participants[] = [
                    'id'         => (int)$st->id,
                    'fullname'   => $st->fullname,
                    'avatar'     => $st->avatar_url,
                    'status'     => $st->status,
                    'percent'    => (int)$st->percent,
                    'duration'   => (int)$st->duration_mins,
                    'handRaised' => !empty($raised_hands[$st->id]) && $is_connected
                ];
            }
        }

        echo json_encode([
            'status'         => 'success',
            'duration'       => $result['duration_seconds'] ?? 0,
            'percentage'     => $result['attendance_percent'] ?? 0,
            'presence_status'=> $result['presence_status'] ?? 'active',
            'active_students'=> $active_count,
            'participants'   => $participants,
            'must_mute'      => (bool)$recent_mute,
            'is_recording'   => (bool)$is_recording,
            'server_time'    => $now
        ]);
        break;
}
exit;
