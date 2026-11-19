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
 * Attendance Export Script (CSV / Excel format with UTF-8 BOM).
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

global $DB;

/**
 * Prevent CSV formula injection: prefix cells starting with =, +, - or @
 * so Excel treats them as text when exporting attendance data.
 *
 * @param mixed $value Cell value.
 * @return mixed Sanitized value.
 */
function export_sanitize_csv_cell($value) {
    if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
        return "'" . $value;
    }
    return $value;
}

/**
 * Write a sanitized CSV row.
 *
 * @param resource $output Output resource (php://output).
 * @param array $fields Field values.
 * @return void
 */
function export_fputcsv($output, array $fields) {
    $clean = array_map('export_sanitize_csv_cell', $fields);
    return fputcsv($output, $clean);
}

$id = required_param('id', PARAM_INT); // Course Module ID.
$groupid = optional_param('group', 0, PARAM_INT);

$cm = get_coursemodule_from_id('liveclass', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$liveclass = $DB->get_record('liveclass', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = \context_module::instance($cm->id);
require_capability('mod/liveclass:viewattendance', $context);

$roster = \mod_liveclass\roster_manager::get_roster_data($cm, $liveclass, $groupid);

$filename = 'LiveClass_' . clean_filename($liveclass->name) . '_' . date('Y-m-d_Hi') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output buffer.
$output = fopen('php://output', 'w');

// Output UTF-8 BOM for Microsoft Excel Arabic compatibility.
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Header metadata rows.
export_fputcsv($output, [get_string('pluginname', 'mod_liveclass'), format_string($liveclass->name)]);
export_fputcsv($output, [get_string('course'), format_string($course->fullname)]);
if ($roster->group_info->target_group) {
    export_fputcsv($output, [get_string('target_group', 'mod_liveclass'), $roster->group_info->target_group->name]);
}
export_fputcsv($output, [get_string('starttime', 'mod_liveclass'), userdate($liveclass->start_time)]);
export_fputcsv($output, [get_string('endtime', 'mod_liveclass'), userdate($liveclass->end_time)]);
export_fputcsv($output, [get_string('min_attendance_req', 'mod_liveclass'), $liveclass->min_attendance_pct . '%']);
export_fputcsv($output, [get_string('total_students', 'mod_liveclass'), $roster->total_students]);
export_fputcsv($output, [get_string('in_class_count', 'mod_liveclass'), $roster->in_class_count]);
export_fputcsv($output, [get_string('online_moodle_count', 'mod_liveclass'), $roster->online_not_in_class_count]);
export_fputcsv($output, [get_string('absent_count', 'mod_liveclass'), $roster->absent_count]);
export_fputcsv($output, []); // Blank line.

// Column titles.
export_fputcsv($output, [
    '#',
    get_string('student_id', 'mod_liveclass'),
    get_string('student_name', 'mod_liveclass'),
    get_string('email'),
    get_string('attendance_status', 'mod_liveclass'),
    get_string('session_duration', 'mod_liveclass') . ' (' . get_string('min_ago', 'mod_liveclass', '') . ')',
    get_string('attendance_progress_col', 'mod_liveclass') . ' %',
    get_string('last_seen_moodle', 'mod_liveclass'),
    get_string('tab_switches_col', 'mod_liveclass')
]);

$i = 1;
foreach ($roster->students as $st) {
    $status_label = '';
    if ($st->status === 'in_class') {
        $status_label = get_string('status_in_class', 'mod_liveclass');
    } else if ($st->status === 'online_not_in_class') {
        $status_label = get_string('status_online_moodle', 'mod_liveclass');
    } else {
        $status_label = get_string('status_absent', 'mod_liveclass');
    }

    export_fputcsv($output, [
        $i++,
        $st->username,
        $st->fullname,
        $st->email,
        $status_label,
        $st->duration_mins,
        $st->percent . '%',
        $st->last_seen_str,
        $st->tab_switches
    ]);
}

fclose($output);
exit;

