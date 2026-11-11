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
 * Core library functions for LiveClass activity module.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a new liveclass instance.
 *
 * @param \stdClass $liveclass Submitted form data.
 * @param \mod_liveclass_mod_form|null $mform
 * @return int Instance ID.
 */
function liveclass_add_instance($liveclass, $mform = null) {
    global $DB;

    $liveclass->timecreated = time();
    $liveclass->timemodified = time();

    if (empty($liveclass->room_name)) {
        $liveclass->room_name = 'liveclass_' . $liveclass->course . '_' . time();
    }

    $liveclass->id = $DB->insert_record('liveclass', $liveclass);
    return $liveclass->id;
}

/**
 * Update an existing liveclass instance.
 *
 * @param \stdClass $liveclass Submitted form data.
 * @param \mod_liveclass_mod_form|null $mform
 * @return bool Success.
 */
function liveclass_update_instance($liveclass, $mform = null) {
    global $DB;

    $liveclass->timemodified = time();
    $liveclass->id = $liveclass->instance;

    return $DB->update_record('liveclass', $liveclass);
}

/**
 * Delete a liveclass instance and associated records.
 *
 * @param int $id Liveclass ID.
 * @return bool Success.
 */
function liveclass_delete_instance($id) {
    global $DB;

    if (!$liveclass = $DB->get_record('liveclass', ['id' => $id])) {
        return false;
    }

    // Delete sessions, intervals, attendance, polls.
    $sessions = $DB->get_records('liveclass_sessions', ['liveclassid' => $id], '', 'id');
    if ($sessions) {
        $sessionids = array_keys($sessions);
        list($insql, $inparams) = $DB->get_in_or_equal($sessionids);

        // Delete intervals.
        $attendanceids = $DB->get_fieldset_select('liveclass_attendance', 'id', "sessionid $insql", $inparams);
        if ($attendanceids) {
            list($att_in, $att_params) = $DB->get_in_or_equal($attendanceids);
            $DB->delete_records_select('liveclass_intervals', "attendanceid $att_in", $att_params);
        }

        $DB->delete_records_select('liveclass_attendance', "sessionid $insql", $inparams);
        $DB->delete_records_select('liveclass_presence_logs', "sessionid $insql", $inparams);

        // Polls.
        $pollids = $DB->get_fieldset_select('liveclass_polls', 'id', "sessionid $insql", $inparams);
        if ($pollids) {
            list($poll_in, $poll_params) = $DB->get_in_or_equal($pollids);
            $DB->delete_records_select('liveclass_poll_answers', "pollid $poll_in", $poll_params);
        }
        $DB->delete_records_select('liveclass_polls', "sessionid $insql", $inparams);
        $DB->delete_records_select('liveclass_sessions', "id $insql", $inparams);
    }

    $DB->delete_records('liveclass', ['id' => $id]);
    return true;
}

/**
 * Feature support flags for liveclass.
 *
 * @param string $feature FEATURE_xx constant.
 * @return bool|null
 */
function liveclass_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

