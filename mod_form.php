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
 * Activity configuration form for mod_liveclass.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_liveclass_mod_form extends moodleform_mod {

    public function definition() {
        $mform = $this->_form;

        // General Section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('classname', 'mod_liveclass'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Schedule Section.
        $mform->addElement('header', 'schedulehdr', get_string('schedule', 'mod_liveclass'));

        $mform->addElement('date_time_selector', 'start_time', get_string('starttime', 'mod_liveclass'));
        $mform->setDefault('start_time', time());

        $mform->addElement('date_time_selector', 'end_time', get_string('endtime', 'mod_liveclass'));
        $mform->setDefault('end_time', time() + 3600);

        // Automated Attendance & Presence Controls.
        $mform->addElement('header', 'attendancehdr', get_string('attendance_settings', 'mod_liveclass'));

        $mform->addElement('text', 'min_attendance_pct', get_string('min_attendance_pct', 'mod_liveclass'), ['size' => '4']);
        $mform->setType('min_attendance_pct', PARAM_INT);
        $mform->setDefault('min_attendance_pct', 80);
        $mform->addHelpButton('min_attendance_pct', 'min_attendance_pct', 'mod_liveclass');

        $mform->addElement('selectyesno', 'auto_sync_attendance', get_string('auto_sync_attendance', 'mod_liveclass'));
        $mform->setDefault('auto_sync_attendance', 1);

        $mform->addElement('selectyesno', 'warn_tab_switch', get_string('warn_tab_switch', 'mod_liveclass'));
        $mform->setDefault('warn_tab_switch', 1);

        $record_options = [
            0 => get_string('recording_disabled', 'mod_liveclass'),
            1 => get_string('recording_automatic', 'mod_liveclass'),
            2 => get_string('recording_moderator', 'mod_liveclass')
        ];
        $mform->addElement('select', 'recording_mode', get_string('recording_mode', 'mod_liveclass'), $record_options);
        $mform->setDefault('recording_mode', 0);

        $mform->addElement('text', 'max_participants', get_string('max_participants', 'mod_liveclass'), ['size' => '4']);
        $mform->setType('max_participants', PARAM_INT);
        $mform->setDefault('max_participants', 100);

        // Standard course elements (Availability, Group, Completion).
        $this->standard_coursemodule_elements();

        // Action Buttons.
        $this->add_action_buttons();
    }
}

