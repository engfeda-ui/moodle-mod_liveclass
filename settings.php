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
 * Global administration settings for mod_liveclass.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // LiveKit Server Connection.
    $settings->add(new admin_setting_heading(
        'mod_liveclass/server_hdr',
        get_string('server_settings', 'mod_liveclass'),
        get_string('server_settings_desc', 'mod_liveclass')
    ));

    $settings->add(new admin_setting_configtext(
        'mod_liveclass/livekit_host',
        get_string('livekit_host', 'mod_liveclass'),
        get_string('livekit_host_desc', 'mod_liveclass'),
        'wss://lms.sanad.ws:7880',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'mod_liveclass/livekit_api_key',
        get_string('livekit_api_key', 'mod_liveclass'),
        get_string('livekit_api_key_desc', 'mod_liveclass'),
        'SANAD_API_KEY',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_liveclass/livekit_api_secret',
        get_string('livekit_api_secret', 'mod_liveclass'),
        get_string('livekit_api_secret_desc', 'mod_liveclass'),
        'sanad_proctoring_secret_key_2026_livekit'
    ));

    // Automated Attendance Defaults.
    $settings->add(new admin_setting_heading(
        'mod_liveclass/attendance_defaults_hdr',
        get_string('attendance_settings', 'mod_liveclass'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'mod_liveclass/default_min_attendance',
        get_string('min_attendance_pct', 'mod_liveclass'),
        get_string('min_attendance_pct_desc', 'mod_liveclass'),
        80,
        PARAM_INT
    ));
}
