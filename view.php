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
 * Main activity view and virtual classroom interface.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID.
$l  = optional_param('l', 0, PARAM_INT);  // Liveclass instance ID.
$action = optional_param('action', 'view', PARAM_ALPHA);

if ($id) {
    $cm = get_coursemodule_from_id('liveclass', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $liveclass = $DB->get_record('liveclass', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    $liveclass = $DB->get_record('liveclass', ['id' => $l], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $liveclass->course], '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('liveclass', $liveclass->id, $course->id, false, MUST_EXIST);
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/liveclass:view', $context);

$is_moderator = has_capability('mod/liveclass:moderate', $context);
$can_join     = has_capability('mod/liveclass:join', $context);

$PAGE->set_url('/mod/liveclass/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($liveclass->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Generate LiveKit Access Token for this user.
$identity = 'user_' . $USER->id;
$displayname = fullname($USER);
$roomname = $liveclass->room_name;

$token = \mod_liveclass\token_factory::create_token(
    $roomname,
    $identity,
    $displayname,
    $is_moderator,
    ['role' => $is_moderator ? 'moderator' : 'student', 'email' => $USER->email]
);

$livekit_host = get_config('mod_liveclass', 'livekit_host') ?: 'wss://lms.sanad.ws:7880';

echo $OUTPUT->header();

// Tab Switch Alert & Presence Listener.
$PAGE->requires->js_amd_inline("
    require(['jquery'], function($) {
        if (" . ($liveclass->warn_tab_switch ? '1' : '0') . ") {
            let tabSwitchCount = 0;
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    tabSwitchCount++;
                    console.warn('[LiveClass] Student switched tabs. Count: ' + tabSwitchCount);
                }
            });
        }
    });
");

?>
<div class="liveclass-container">
    <div class="liveclass-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2><i class="fa fa-video-camera text-primary"></i> <?php echo format_string($liveclass->name); ?></h2>
            <p class="text-muted mb-0"><?php echo format_module_intro('liveclass', $liveclass, $cm->id); ?></p>
        </div>
        <div>
            <?php if ($is_moderator): ?>
                <span class="badge bg-danger p-2"><i class="fa fa-shield"></i> <?php echo get_string('role_moderator', 'mod_liveclass'); ?></span>
            <?php else: ?>
                <span class="badge bg-success p-2"><i class="fa fa-user"></i> <?php echo get_string('role_student', 'mod_liveclass'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- LiveClass WebRTC Viewport -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div id="liveclass-stage" class="bg-dark text-white rounded d-flex flex-column justify-content-center align-items-center" style="min-height: 520px; position: relative;">
                <div class="text-center p-4">
                    <div class="mb-3">
                        <i class="fa fa-graduation-cap fa-4x text-info"></i>
                    </div>
                    <h4><?php echo get_string('ready_to_join', 'mod_liveclass'); ?></h4>
                    <p class="text-muted"><?php echo get_string('ready_desc', 'mod_liveclass'); ?></p>
                    
                    <button id="btn-join-liveclass" class="btn btn-primary btn-lg px-4 shadow">
                        <i class="fa fa-play-circle"></i> <?php echo get_string('join_now', 'mod_liveclass'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Session Info & Automated Attendance Indicators -->
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="card-title"><i class="fa fa-clock-o text-warning"></i> <?php echo get_string('session_details', 'mod_liveclass'); ?></h5>
                    <ul class="list-unstyled mb-0">
                        <li><strong><?php echo get_string('starttime', 'mod_liveclass'); ?>:</strong> <?php echo userdate($liveclass->start_time); ?></li>
                        <li><strong><?php echo get_string('endtime', 'mod_liveclass'); ?>:</strong> <?php echo userdate($liveclass->end_time); ?></li>
                        <li><strong><?php echo get_string('min_attendance_req', 'mod_liveclass'); ?>:</strong> <?php echo $liveclass->min_attendance_pct; ?>%</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body">
                    <h5 class="card-title"><i class="fa fa-check-circle text-success"></i> <?php echo get_string('attendance_status', 'mod_liveclass'); ?></h5>
                    <p class="text-muted small mb-2"><?php echo get_string('attendance_auto_tracked_desc', 'mod_liveclass'); ?></p>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" role="progressbar" style="width: 0%;" id="attendance-progress">0%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
