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
 * Display a list of all LiveClass virtual classrooms in a given course.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/liveclass/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

$strliveclasses = get_string('modulenameplural', 'mod_liveclass');
$PAGE->navbar->add($strliveclasses);

echo $OUTPUT->header();
echo $OUTPUT->heading($strliveclasses);

if (!$liveclasses = get_all_instances_in_course('liveclass', $course)) {
    notice(get_string('thereareno', 'moodle', $strliveclasses), new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    $table->head = [$strsectionname, get_string('name'), get_string('starttime', 'mod_liveclass'), get_string('endtime', 'mod_liveclass')];
    $table->align = ['center', 'left', 'left', 'left'];
} else {
    $table->head = [get_string('name'), get_string('starttime', 'mod_liveclass'), get_string('endtime', 'mod_liveclass')];
    $table->align = ['left', 'left', 'left'];
}

$modinfo = get_fast_modinfo($course);
$currentsection = '';

foreach ($liveclasses as $liveclass) {
    $cm = $modinfo->cms[$liveclass->coursemodule];
    if (!$cm->uservisible) {
        continue;
    }

    $link = html_writer::link(new moodle_url('/mod/liveclass/view.php', ['id' => $cm->id]), format_string($liveclass->name));

    $start = !empty($liveclass->start_time) ? userdate($liveclass->start_time) : '-';
    $end = !empty($liveclass->end_time) ? userdate($liveclass->end_time) : '-';

    if ($usesections) {
        $printsection = '';
        if ($liveclass->section !== $currentsection) {
            if ($liveclass->section) {
                $printsection = get_section_name($course, $liveclass->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $liveclass->section;
        }
        $table->data[] = [$printsection, $link, $start, $end];
    } else {
        $table->data[] = [$link, $start, $end];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
