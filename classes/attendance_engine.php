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

namespace mod_liveclass;

defined('MOODLE_INTERNAL') || die();

/**
 * Automated Attendance & Presence Engine.
 * Computes granular connection intervals, presence metrics, and syncs with Moodle core attendance and completion.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attendance_engine {

    /**
     * Record participant joining a live session.
     *
     * @param int $sessionid Session record ID.
     * @param int $userid Moodle user ID.
     * @param int $timestamp Event UNIX timestamp.
     * @return int Created interval ID.
     */
    public static function participant_joined(int $sessionid, int $userid, int $timestamp = 0): int {
        global $DB;

        $time = $timestamp ?: time();

        // 1. Ensure master attendance record exists.
        $attendance = $DB->get_record('liveclass_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $userid
        ]);

        if (!$attendance) {
            $record = (object)[
                'sessionid'              => $sessionid,
                'userid'                 => $userid,
                'first_join_time'        => $time,
                'last_leave_time'        => 0,
                'total_duration_seconds' => 0,
                'attendance_percentage'  => 0,
                'status'                 => 'absent',
                'synctomoodle_done'      => 0,
                'timecreated'            => $time,
                'timemodified'           => $time
            ];
            $attendanceid = $DB->insert_record('liveclass_attendance', $record);
        } else {
            $attendanceid = $attendance->id;
        }

        // 2. Open a new discrete interval segment.
        $interval = (object)[
            'attendanceid'     => $attendanceid,
            'join_time'        => $time,
            'leave_time'       => 0,
            'duration_seconds' => 0
        ];
        return $DB->insert_record('liveclass_intervals', $interval);
    }

    /**
     * Record participant leaving a live session and recalculate duration.
     *
     * @param int $sessionid Session record ID.
     * @param int $userid Moodle user ID.
     * @param int $timestamp Event UNIX timestamp.
     * @return bool Success status.
     */
    public static function participant_left(int $sessionid, int $userid, int $timestamp = 0): bool {
        global $DB;

        $time = $timestamp ?: time();

        $attendance = $DB->get_record('liveclass_attendance', [
            'sessionid' => $sessionid,
            'userid'    => $userid
        ]);

        if (!$attendance) {
            return false;
        }

        // 1. Close open intervals.
        $open_intervals = $DB->get_records_select(
            'liveclass_intervals',
            'attendanceid = :attid AND leave_time = 0',
            ['attid' => $attendance->id],
            'join_time ASC'
        );

        foreach ($open_intervals as $inv) {
            $duration = max(0, $time - $inv->join_time);
            $inv->leave_time = $time;
            $inv->duration_seconds = $duration;
            $DB->update_record('liveclass_intervals', $inv);
        }

        // 2. Calculate cumulative duration.
        $total_seconds = (int)$DB->get_field_sql(
            'SELECT SUM(duration_seconds) FROM {liveclass_intervals} WHERE attendanceid = :attid',
            ['attid' => $attendance->id]
        );

        // 3. Compute percentage against expected session length.
        $session = $DB->get_record('liveclass_sessions', ['id' => $sessionid]);
        $liveclass = $DB->get_record('liveclass', ['id' => $session->liveclassid]);

        $planned_duration = max(1, $liveclass->end_time - $liveclass->start_time);
        if ($session->actual_start > 0 && $session->actual_end > $session->actual_start) {
            $planned_duration = $session->actual_end - $session->actual_start;
        }

        $pct = min(100, (int)round(($total_seconds / $planned_duration) * 100));

        // 4. Determine status.
        $status = 'absent';
        if ($pct >= $liveclass->min_attendance_pct) {
            $status = 'present';
        } else if ($pct >= 40) {
            $status = 'late';
        }

        $attendance->total_duration_seconds = $total_seconds;
        $attendance->last_leave_time = $time;
        $attendance->attendance_percentage = $pct;
        $attendance->status = $status;
        $attendance->timemodified = $time;

        $DB->update_record('liveclass_attendance', $attendance);

        // 5. Trigger automated Moodle sync if completed.
        if ($liveclass->auto_sync_attendance && $status === 'present') {
            self::sync_completion($liveclass, $userid);
        }

        return true;
    }

    /**
     * Mark Moodle activity completion when student fulfills presence requirements.
     *
     * @param \stdClass $liveclass LiveClass instance record.
     * @param int $userid Moodle user ID.
     */
    public static function sync_completion(\stdClass $liveclass, int $userid): void {
        global $CFG;
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($liveclass->course);
        $cm = get_coursemodule_from_instance('liveclass', $liveclass->id, $course->id);

        if (!$cm) {
            return;
        }

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm)) {
            $completion->update_state($cm, COMPLETION_COMPLETE, $userid);
        }
    }

    /**
     * Log a behavioral or presence event (tab switch, poll response, mic toggle).
     *
     * @param int $sessionid Session ID.
     * @param int $userid User ID.
     * @param string $event_type Type of event (e.g. 'tab_hidden', 'poll_missed').
     * @param string $details Additional context or metadata.
     * @return int Log record ID.
     */
    public static function log_presence_event(int $sessionid, int $userid, string $event_type, string $details = ''): int {
        global $DB;

        $log = (object)[
            'sessionid'  => $sessionid,
            'userid'     => $userid,
            'event_type' => substr($event_type, 0, 32),
            'details'    => $details,
            'timestamp'  => time()
        ];
        return $DB->insert_record('liveclass_presence_logs', $log);
    }
}

