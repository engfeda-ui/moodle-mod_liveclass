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
 * English language strings for mod_liveclass.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LiveClass Virtual Classroom';
$string['modulename'] = 'LiveClass';
$string['modulenameplural'] = 'LiveClasses';
$string['pluginadministration'] = 'LiveClass Administration';

// Capabilities.
$string['liveclass:addinstance'] = 'Add a new LiveClass virtual classroom';
$string['liveclass:view'] = 'View LiveClass virtual classroom';
$string['liveclass:join'] = 'Join live virtual classroom';
$string['liveclass:moderate'] = 'Moderate and control live session';
$string['liveclass:record'] = 'Record live session';
$string['liveclass:viewattendance'] = 'View live attendance reports';

// General Form Strings.
$string['classname'] = 'Class Name';
$string['schedule'] = 'Class Schedule';
$string['starttime'] = 'Start Time';
$string['endtime'] = 'End Time';
$string['attendance_settings'] = 'Automated Attendance & Presence Rules';
$string['min_attendance_pct'] = 'Minimum Attendance Required (%)';
$string['min_attendance_pct_help'] = 'Students who participate for at least this percentage of the total class duration will automatically be marked as Present.';
$string['min_attendance_pct_desc'] = 'Default percentage required for automated attendance confirmation.';
$string['auto_sync_attendance'] = 'Sync with Moodle Attendance & Completion';
$string['warn_tab_switch'] = 'Warn on Tab Switching';
$string['recording_mode'] = 'Recording Mode';
$string['recording_disabled'] = 'Disabled';
$string['recording_automatic'] = 'Automatic Recording';
$string['recording_moderator'] = 'Moderator Controlled';
$string['max_participants'] = 'Max Participants';

// Server Administration Settings.
$string['server_settings'] = 'LiveKit SFU Server Connection';
$string['server_settings_desc'] = 'Configure connection to your self-hosted LiveKit WebRTC media server.';
$string['livekit_host'] = 'LiveKit WebSocket URL';
$string['livekit_host_desc'] = 'e.g., wss://lms.sanad.ws:7880 or ws://150.230.241.37:7880';
$string['livekit_api_key'] = 'API Key';
$string['livekit_api_key_desc'] = 'LiveKit API Key from livekit.yaml';
$string['livekit_api_secret'] = 'API Secret';
$string['livekit_api_secret_desc'] = 'LiveKit Secret Key from livekit.yaml';

// View Page Strings.
$string['role_moderator'] = 'Instructor / Moderator';
$string['role_student'] = 'Student';
$string['ready_to_join'] = 'Ready to Join Classroom';
$string['ready_desc'] = 'Connect with crystal-clear audio, HD video, and real-time screen sharing.';
$string['join_now'] = 'Join Virtual Class';
$string['session_details'] = 'Session Information';
$string['min_attendance_req'] = 'Required Attendance';
$string['attendance_status'] = 'Attendance Progress';
$string['attendance_auto_tracked_desc'] = 'Your active participation time is automatically monitored by the LiveKit engine.';

