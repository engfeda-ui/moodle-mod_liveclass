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

$now = time();
$start_time = (int)$liveclass->start_time;
$end_time   = (int)$liveclass->end_time;

// Check existing session state.
$session = $DB->get_record('liveclass_sessions', [
    'liveclassid' => $liveclass->id,
    'status'      => 'active'
]);

// 1. Student Access Control: Waiting Lobby (Before Start Time)
if (!$is_moderator && $start_time > 0 && $now < $start_time) {
    echo $OUTPUT->header();
    $diff_seconds = max(0, $start_time - $now);
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0 text-center p-4 rounded-4">
                    <div class="card-body">
                        <div class="mb-4">
                            <span class="badge bg-warning-subtle text-warning border border-warning rounded-circle p-4 mb-3 shadow-sm d-inline-block">
                                <i class="fa fa-clock-o fa-4x text-warning"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold text-dark mb-2"><?php echo format_string($liveclass->name); ?></h3>
                        <h5 class="text-secondary mb-4"><?php echo get_string('class_not_started', 'mod_liveclass'); ?></h5>

                        <div class="alert alert-light border shadow-sm py-3 px-4 mb-4 text-start">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted"><i class="fa fa-calendar me-1"></i> <?php echo get_string('starttime', 'mod_liveclass'); ?>:</span>
                                <span class="fw-bold text-primary"><?php echo userdate($start_time); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted"><i class="fa fa-hourglass-end me-1"></i> <?php echo get_string('endtime', 'mod_liveclass'); ?>:</span>
                                <span class="fw-bold text-muted"><?php echo userdate($end_time); ?></span>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-4 border">
                            <div class="text-muted small mb-2"><?php echo get_string('starts_in', 'mod_liveclass'); ?></div>
                            <div id="lobby-countdown" class="display-6 fw-bold font-monospace text-primary">--:--:--</div>
                        </div>

                        <p class="text-muted small mb-4">
                            <?php echo get_string('class_not_started_msg', 'mod_liveclass', userdate($start_time)); ?>
                        </p>

                        <div class="d-flex justify-content-center gap-2">
                            <button onclick="window.location.reload();" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fa fa-refresh"></i> تحديث الآن
                            </button>
                            <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $course->id; ?>" class="btn btn-secondary rounded-pill px-4">
                                <i class="fa fa-arrow-left"></i> <?php echo get_string('return_to_course', 'mod_liveclass'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var remaining = <?php echo (int)$diff_seconds; ?>;
        var display = document.getElementById('lobby-countdown');
        function updateTimer() {
            if (remaining <= 0) {
                display.textContent = '00:00:00';
                display.classList.remove('text-primary');
                display.classList.add('text-success');
                setTimeout(function() { window.location.reload(); }, 1500);
                return;
            }
            var h = Math.floor(remaining / 3600);
            var m = Math.floor((remaining % 3600) / 60);
            var s = remaining % 60;
            display.textContent = (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            remaining--;
        }
        updateTimer();
        setInterval(updateTimer, 1000);
    });
    </script>
    <?php
    echo $OUTPUT->footer();
    exit;
}

// 2. Student Access Control: Session Ended (After End Time or Completed)
$is_session_expired = ($end_time > 0 && $now > $end_time);
$is_explicitly_closed = ($session && $session->status === 'completed');

if (!$is_moderator && ($is_session_expired || $is_explicitly_closed || (!$session && $start_time > 0 && $now > $start_time))) {
    echo $OUTPUT->header();

    // Student personal attendance record.
    $user_att = $DB->get_record_sql(
        "SELECT a.* 
           FROM {liveclass_attendance} a
           JOIN {liveclass_sessions} s ON s.id = a.sessionid
          WHERE s.liveclassid = ? AND a.userid = ?
          ORDER BY a.id DESC",
        [$liveclass->id, $USER->id],
        IGNORE_MULTIPLE
    );

    $att_mins = $user_att ? round($user_att->total_duration_seconds / 60) : 0;
    $att_pct = $user_att ? (int)$user_att->attendance_percentage : 0;
    $req_pct = (int)$liveclass->min_attendance_pct;
    $is_met = ($att_pct >= $req_pct);

    $recording_session = $DB->get_record_select('liveclass_sessions', 'liveclassid = ? AND recording_url IS NOT NULL AND recording_url != "" ORDER BY id DESC', [$liveclass->id]);
    ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0 text-center p-4 rounded-4">
                    <div class="card-body">
                        <div class="mb-4">
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary rounded-circle p-4 mb-3 shadow-sm d-inline-block">
                                <i class="fa fa-calendar-check-o fa-4x text-secondary"></i>
                            </span>
                        </div>
                        <h3 class="fw-bold text-dark mb-2"><?php echo format_string($liveclass->name); ?></h3>
                        <h5 class="text-danger fw-bold mb-3"><?php echo get_string('class_ended', 'mod_liveclass'); ?></h5>
                        <p class="text-muted small mb-4">
                            <?php echo get_string('class_ended_msg', 'mod_liveclass', userdate($end_time ?: time())); ?>
                        </p>

                        <!-- Student Attendance Summary Card -->
                        <div class="card bg-light border-0 shadow-sm rounded-3 p-3 mb-4 text-start">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">
                                <i class="fa fa-user-circle me-1"></i> <?php echo get_string('attendance_summary', 'mod_liveclass'); ?>
                            </h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">مدة حضورك المسجلة:</span>
                                <span class="fw-bold"><?php echo $att_mins; ?> دقيقة</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">نسبة الحضور المحققة:</span>
                                <span class="fw-bold <?php echo $is_met ? 'text-success' : 'text-danger'; ?>"><?php echo $att_pct; ?>% (الحد الأدنى: <?php echo $req_pct; ?>%)</span>
                            </div>
                            <div class="mt-2 text-center">
                                <?php if ($is_met): ?>
                                    <span class="badge bg-success-subtle text-success border border-success px-3 py-2 rounded-pill">
                                        <i class="fa fa-check-circle"></i> <?php echo get_string('attendance_status_met', 'mod_liveclass'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-3 py-2 rounded-pill">
                                        <i class="fa fa-exclamation-triangle"></i> <?php echo get_string('attendance_status_unmet', 'mod_liveclass'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($recording_session && !empty($recording_session->recording_url)): ?>
                            <div class="mb-4">
                                <a href="<?php echo s($recording_session->recording_url); ?>" target="_blank" class="btn btn-outline-success rounded-pill px-4">
                                    <i class="fa fa-play-circle"></i> <?php echo get_string('play_recording', 'mod_liveclass'); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex justify-content-center gap-2">
                            <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $course->id; ?>" class="btn btn-primary rounded-pill px-4">
                                <i class="fa fa-arrow-left"></i> <?php echo get_string('return_to_course', 'mod_liveclass'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    echo $OUTPUT->footer();
    exit;
}

if (!$session) {
    $session = $DB->get_record('liveclass_sessions', [
        'liveclassid' => $liveclass->id,
        'status'      => 'scheduled'
    ]);
}

if (!$session) {
    // Auto-create or resume active session for scheduled lecture timeframe or moderator.
    $session = (object)[
        'liveclassid'        => $liveclass->id,
        'session_uuid'       => \core\uuid::generate(),
        'livekit_room_sid'   => '',
        'actual_start'       => time(),
        'actual_end'         => 0,
        'status'             => 'active',
        'recording_url'      => '',
        'total_participants' => 1,
        'timecreated'        => time()
    ];
    $session->id = $DB->insert_record('liveclass_sessions', $session);
}

$session_id = $session ? $session->id : 0;

// Find instructor name for this course/activity to display to students.
if ($is_moderator) {
    $instructor_name = fullname($USER);
    $instructor_id = (int)$USER->id;
} else {
    $instructor_name = '';
    $instructor_id = 2;
    $teachers = get_enrolled_users($context, 'mod/liveclass:moderate');
    if (!empty($teachers)) {
        $t = reset($teachers);
        $instructor_name = fullname($t);
        $instructor_id = (int)$t->id;
    }
}

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

$livekit_host = get_config('mod_liveclass', 'livekit_host') ?: 'wss://lms.sanad.ws';
$livekit_host = str_replace(':7880', '', $livekit_host);

// Initialize AMD Classroom module.
$PAGE->requires->js_call_amd('mod_liveclass/classroom', 'init', [[
    'host'             => $livekit_host,
    'token'            => $token,
    'roomName'         => $liveclass->room_name,
    'displayName'      => $displayname,
    'currentUserId'    => (int)$USER->id,
    'instructorName'   => $instructor_name ?: get_string('instructor', 'mod_liveclass'),
    'instructorId'     => (int)$instructor_id,
    'isModerator'      => (bool)$is_moderator,
    'sessionId'        => (int)$session_id,
    'cmid'             => (int)$cm->id,
    'sesskey'          => sesskey(),
    'recordingMode'    => (int)$liveclass->recording_mode,
    'warnTabSwitch'    => (bool)$liveclass->warn_tab_switch,
    'minAttendancePct' => (int)$liveclass->min_attendance_pct,
    'startTime'        => (int)$liveclass->start_time,
    'endTime'          => (int)$liveclass->end_time
]]);

echo $OUTPUT->header();
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

    <?php if ($is_moderator && $start_time > 0 && $now < $start_time): ?>
        <div class="alert alert-info border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center">
            <i class="fa fa-info-circle fa-2x me-3 ms-2 text-info"></i>
            <div>
                <strong>تنبيه للمدرب:</strong> <?php echo get_string('moderator_early_notice', 'mod_liveclass'); ?>
                <div class="small text-muted mt-1">موعد البدء المجدول: <?php echo userdate($start_time); ?> (بعد <?php echo format_time($start_time - $now); ?>).</div>
            </div>
        </div>
    <?php elseif ($is_moderator && $end_time > 0 && $now > $end_time): ?>
        <div class="alert alert-warning border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center">
            <i class="fa fa-exclamation-triangle fa-2x me-3 ms-2 text-warning"></i>
            <div>
                <strong>تنبيه للمدرب:</strong> <?php echo get_string('moderator_ended_notice', 'mod_liveclass'); ?>
                <div class="small text-muted mt-1">انتهى موعد الحصة المجدول في: <?php echo userdate($end_time); ?>. المتدربون لا يستطيعون الدخول حالياً.</div>
            </div>
        </div>
    <?php endif; ?>

    <!-- LiveClass WebRTC Viewport -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div id="liveclass-stage" class="bg-dark text-white rounded d-flex flex-column justify-content-center align-items-center" style="min-height: 540px; position: relative;">
                <div class="text-center p-4">
                    <div class="mb-3">
                        <i class="fa fa-graduation-cap fa-4x text-info"></i>
                    </div>
                    <h4><?php echo get_string('ready_to_join', 'mod_liveclass'); ?></h4>
                    <p class="text-muted"><?php echo get_string('ready_desc', 'mod_liveclass'); ?></p>
                    
                    <button id="btn-join-liveclass" class="btn btn-primary btn-lg px-5 shadow rounded-pill">
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

    <?php if ($is_moderator): ?>
        <?php
        $selected_group_id = optional_param('group', 0, PARAM_INT);
        $roster = \mod_liveclass\roster_manager::get_roster_data($cm, $liveclass, $selected_group_id);
        $group_info = $roster->group_info;
        ?>
        <!-- Instructor Roster & Presence Command Center -->
        <div class="card shadow-sm border-0 mt-4 liveclass-command-center" id="liveclass-command-center">
            <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary">
                        <i class="fa fa-users text-primary"></i> <?php echo get_string('roster_command_center', 'mod_liveclass'); ?>
                    </h4>
                    <p class="text-muted small mb-0">
                        <?php if ($group_info->has_restrictions && $group_info->target_group): ?>
                            <span><?php echo get_string('target_group', 'mod_liveclass'); ?>: <strong><?php echo s($group_info->target_group->name); ?></strong> (<?php echo $group_info->target_group->member_count; ?> <?php echo get_string('total_students', 'mod_liveclass'); ?>)</span>
                        <?php else: ?>
                            <span><?php echo get_string('no_group_restriction', 'mod_liveclass'); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0 flex-wrap">
                    <?php if (count($group_info->all_groups) > 1): ?>
                        <div class="dropdown me-1">
                            <select id="group-filter-select" class="form-select form-select-sm" onchange="window.location.href='view.php?id=<?php echo $cm->id; ?>&group='+this.value">
                                <?php foreach ($group_info->all_groups as $g): ?>
                                    <option value="<?php echo $g->id; ?>" <?php echo ($roster->selected_group_id == $g->id) ? 'selected' : ''; ?>>
                                        <?php echo s($g->name); ?> (<?php echo $g->member_count; ?>) <?php echo ($g->is_allowed ? '✅' : '🔒'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <a href="export.php?id=<?php echo $cm->id; ?>&group=<?php echo $roster->selected_group_id; ?>" class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm" title="<?php echo get_string('export_csv', 'mod_liveclass'); ?>">
                        <i class="fa fa-file-excel-o"></i> <?php echo get_string('export_attendance', 'mod_liveclass'); ?>
                    </a>
                    <button id="btn-mute-all" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm" title="<?php echo get_string('mute_all', 'mod_liveclass'); ?>">
                        <i class="fa fa-microphone-slash"></i> <?php echo get_string('mute_all', 'mod_liveclass'); ?>
                    </button>
                    <button id="btn-roster-lower-hands" class="btn btn-warning text-dark btn-sm rounded-pill px-3 shadow-sm <?php echo empty($roster->raised_hands_count) ? 'd-none' : ''; ?>" title="<?php echo get_string('lower_all_hands', 'mod_liveclass'); ?>">
                        <i class="fa fa-hand-paper-o"></i> <?php echo get_string('lower_all_hands', 'mod_liveclass'); ?> (<span id="counter-raised-hands"><?php echo (int)($roster->raised_hands_count ?? 0); ?></span>)
                    </button>
                    <button id="btn-open-poll-modal" class="btn btn-outline-info btn-sm rounded-pill px-3 shadow-sm" title="<?php echo get_string('quick_poll', 'mod_liveclass'); ?>">
                        <i class="fa fa-bar-chart"></i> <?php echo get_string('quick_poll', 'mod_liveclass'); ?>
                    </button>
                    <button id="btn-refresh-roster" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                        <i class="fa fa-refresh" id="refresh-spinner"></i> <?php echo get_string('refresh_roster', 'mod_liveclass'); ?>
                    </button>
                    <button id="btn-end-session" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm" title="<?php echo get_string('end_session_btn', 'mod_liveclass'); ?>">
                        <i class="fa fa-power-off"></i> <?php echo get_string('end_session_btn', 'mod_liveclass'); ?>
                    </button>
                </div>
            </div>

            <!-- Group Eligibility Status Bar -->
            <?php if ($group_info->has_restrictions): ?>
                <div class="px-4 py-2 bg-light border-bottom d-flex flex-wrap align-items-center gap-2">
                    <span class="small fw-bold text-muted"><?php echo get_string('allowed_groups', 'mod_liveclass'); ?>:</span>
                    <?php foreach ($group_info->allowed_groups as $ag): ?>
                        <span class="group-badge group-badge-allowed shadow-sm">
                            <i class="fa fa-check-circle"></i> <?php echo s($ag->name); ?> (<?php echo $ag->member_count; ?>) - <?php echo get_string('allowed_badge', 'mod_liveclass'); ?>
                        </span>
                    <?php endforeach; ?>

                    <?php if (!empty($group_info->restricted_groups)): ?>
                        <span class="small fw-bold text-muted ms-3"><?php echo get_string('restricted_groups', 'mod_liveclass'); ?>:</span>
                        <?php foreach ($group_info->restricted_groups as $rg): ?>
                            <span class="group-badge group-badge-restricted">
                                <i class="fa fa-lock"></i> <?php echo s($rg->name); ?> (<?php echo $rg->member_count; ?>) - <?php echo get_string('restricted_badge', 'mod_liveclass'); ?>
                            </span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Real-Time Metrics Summary Cards -->
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-3 col-6">
                        <div class="liveclass-stat-card stat-card-total shadow-sm">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-muted small text-uppercase fw-bold"><?php echo get_string('total_students', 'mod_liveclass'); ?></div>
                                    <h3 class="mb-0 fw-bold text-primary" id="counter-total"><?php echo $roster->total_students; ?></h3>
                                </div>
                                <div class="text-primary opacity-50"><i class="fa fa-users fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="liveclass-stat-card stat-card-inclass shadow-sm">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-success small text-uppercase fw-bold">
                                        <span class="pulse-dot pulse-dot-green"></span><?php echo get_string('in_class_count', 'mod_liveclass'); ?>
                                    </div>
                                    <h3 class="mb-0 fw-bold text-success" id="counter-inclass"><?php echo $roster->in_class_count; ?></h3>
                                </div>
                                <div class="text-success opacity-50"><i class="fa fa-video-camera fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="liveclass-stat-card stat-card-online shadow-sm">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-warning small text-uppercase fw-bold">
                                        <span class="pulse-dot pulse-dot-amber"></span><?php echo get_string('online_moodle_count', 'mod_liveclass'); ?>
                                    </div>
                                    <h3 class="mb-0 fw-bold text-warning" id="counter-online"><?php echo $roster->online_not_in_class_count; ?></h3>
                                </div>
                                <div class="text-warning opacity-50"><i class="fa fa-globe fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="liveclass-stat-card stat-card-absent shadow-sm">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="text-danger small text-uppercase fw-bold">
                                        <span class="pulse-dot pulse-dot-gray"></span><?php echo get_string('absent_count', 'mod_liveclass'); ?>
                                    </div>
                                    <h3 class="mb-0 fw-bold text-danger" id="counter-absent"><?php echo $roster->absent_count; ?></h3>
                                </div>
                                <div class="text-danger opacity-50"><i class="fa fa-user-times fa-2x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Students Roster Table -->
                <div class="table-responsive rounded border">
                    <table class="table table-hover align-middle mb-0" id="roster-table">
                        <thead class="table-light">
                            <tr>
                                <th><?php echo get_string('student_name', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('student_id', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('attendance_status', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('attendance_progress_col', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('last_seen_moodle', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('tab_switches_col', 'mod_liveclass'); ?></th>
                                <th class="text-center"><?php echo get_string('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="roster-tbody">
                            <?php if (empty($roster->students)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fa fa-info-circle"></i> <?php echo get_string('nostudentsyet'); ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($roster->students as $st): ?>
                                    <tr id="student-row-<?php echo $st->id; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $st->avatar_url; ?>" class="student-avatar me-2 ms-2 shadow-sm border" alt="<?php echo s($st->fullname); ?>">
                                                <div>
                                                    <div class="fw-bold">
                                                        <?php echo s($st->fullname); ?>
                                                        <span id="roster-hand-<?php echo $st->id; ?>" class="badge bg-warning text-dark border border-warning shadow-sm animate-bounce ms-2 badge-hand-raised <?php echo empty($st->hand_raised) ? 'd-none' : ''; ?>">✋ <?php echo get_string('raise_hand', 'mod_liveclass'); ?></span>
                                                    </div>
                                                    <div class="small text-muted"><?php echo s($st->email); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border"><?php echo s($st->username); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($st->status === 'in_class'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                                                    <span class="pulse-dot pulse-dot-green"></span> <?php echo get_string('status_in_class', 'mod_liveclass'); ?>
                                                </span>
                                            <?php elseif ($st->status === 'online_not_in_class'): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1 shadow-sm">
                                                    <span class="pulse-dot pulse-dot-amber"></span> <?php echo get_string('status_online_moodle', 'mod_liveclass'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2 py-1">
                                                    <span class="pulse-dot pulse-dot-gray"></span> <?php echo get_string('status_absent', 'mod_liveclass'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="min-width: 140px;">
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar <?php echo $st->percent >= $liveclass->min_attendance_pct ? 'bg-success' : 'bg-info'; ?>" 
                                                         style="width: <?php echo min(100, $st->percent); ?>%;"></div>
                                                </div>
                                                <span class="small fw-bold"><?php echo $st->percent; ?>%</span>
                                            </div>
                                            <div class="small text-muted"><?php echo $st->duration_mins; ?> دقيقة</div>
                                        </td>
                                        <td>
                                            <span class="small <?php echo ($st->status === 'online_not_in_class') ? 'text-warning-emphasis fw-bold' : 'text-muted'; ?>">
                                                <i class="fa fa-clock-o"></i> <?php echo $st->last_seen_str; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($st->tab_switches > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0 btn-focus-detail" 
                                                        data-userid="<?php echo $st->id; ?>" 
                                                        data-name="<?php echo s($st->fullname); ?>" 
                                                        data-count="<?php echo $st->tab_switches; ?>" 
                                                        title="<?php echo get_string('focus_alerts_desc', 'mod_liveclass'); ?>">
                                                    <i class="fa fa-exclamation-triangle"></i> <?php echo $st->tab_switches; ?> <span style="font-size:0.75rem;">(تفاصيل)</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($st->status === 'online_not_in_class'): ?>
                                                <button class="btn btn-sm btn-warning text-dark btn-nudge shadow-sm" 
                                                        data-userid="<?php echo $st->id; ?>" 
                                                        data-name="<?php echo s($st->fullname); ?>"
                                                        title="<?php echo get_string('action_nudge', 'mod_liveclass'); ?>">
                                                    <i class="fa fa-bell"></i> <?php echo get_string('action_nudge', 'mod_liveclass'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <a href="<?php echo $CFG->wwwroot; ?>/message/index.php?id=<?php echo $st->id; ?>" 
                                               target="_blank" 
                                               class="btn btn-sm btn-outline-secondary ms-1" 
                                               title="Chat">
                                                <i class="fa fa-comment-o"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick Poll Modal for Moderator -->
        <div id="poll-modal" class="modal fade" tabindex="-1" style="display:none; background: rgba(0,0,0,0.6); z-index: 1080;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title"><i class="fa fa-bar-chart"></i> <?php echo get_string('quick_poll', 'mod_liveclass'); ?></h5>
                        <button type="button" class="btn btn-sm btn-outline-light rounded-circle" id="btn-close-poll-modal">&times;</button>
                    </div>
                    <div class="modal-body p-4">
                        <form id="form-create-poll">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo get_string('poll_question', 'mod_liveclass'); ?></label>
                                <input type="text" id="poll-input-question" class="form-control" value="هل أنت متابع ومستوعب للشرح الآن؟" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo get_string('poll_options', 'mod_liveclass'); ?></label>
                                <textarea id="poll-input-options" class="form-control" rows="3">نعم، مستوعب تماماً
أحتاج إعادة نقطة معينة
لدي سؤال</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo get_string('poll_duration', 'mod_liveclass'); ?></label>
                                <select id="poll-input-duration" class="form-select">
                                    <option value="20">20 ثانية</option>
                                    <option value="30" selected>30 ثانية</option>
                                    <option value="45">45 ثانية</option>
                                    <option value="60">60 ثانية</option>
                                </select>
                            </div>
                            <button type="submit" id="btn-submit-create-poll" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">
                                <i class="fa fa-paper-plane"></i> <?php echo get_string('create_poll', 'mod_liveclass'); ?>
                            </button>
                        </form>

                        <!-- Poll Live Results Container -->
                        <div id="poll-live-results" class="mt-4 pt-3 border-top d-none">
                            <h6 class="fw-bold text-success mb-2"><i class="fa fa-pie-chart"></i> <?php echo get_string('poll_results', 'mod_liveclass'); ?> (<span id="poll-results-total">0</span> إجابة)</h6>
                            <div id="poll-results-bars"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Focus Alerts Breakdown Modal for Moderator -->
        <div id="modal-focus-detail" class="modal fade" tabindex="-1" style="display:none; background: rgba(0,0,0,0.6); z-index: 1085;">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fa fa-exclamation-triangle"></i> <?php echo get_string('focus_modal_title', 'mod_liveclass'); ?></h5>
                        <button type="button" class="btn btn-sm btn-outline-light rounded-circle" id="btn-close-focus-modal">&times;</button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            <i class="fa fa-info-circle me-1"></i> <?php echo get_string('focus_alerts_desc', 'mod_liveclass'); ?>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-bold"><i class="fa fa-user me-1"></i> <span id="focus-modal-student-name">...</span></h6>
                            <span class="badge bg-danger rounded-pill px-3 py-2" id="focus-modal-count-badge">0 تنبيه</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>وقت الحدث</th>
                                        <th>نوع التنبيه</th>
                                        <th>التفاصيل</th>
                                    </tr>
                                </thead>
                                <tbody id="focus-modal-tbody">
                                    <tr><td colspan="4" class="text-center py-3 text-muted">جاري تحميل السجلات...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" id="btn-close-focus-modal-footer">إغلاق</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Command Center Live Sync Script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var refreshBtn = document.getElementById('btn-refresh-roster');
            var spinner = document.getElementById('refresh-spinner');
            var cmid = <?php echo (int)$cm->id; ?>;
            var groupid = <?php echo (int)$roster->selected_group_id; ?>;
            var sesskey = '<?php echo sesskey(); ?>';

            // Mute all button
            var muteAllBtn = document.getElementById('btn-mute-all');
            if (muteAllBtn) {
                muteAllBtn.addEventListener('click', function() {
                    if (confirm('<?php echo get_string('mute_all_confirm', 'mod_liveclass'); ?>')) {
                        muteAllBtn.disabled = true;
                        fetch('control.php?cmid=' + cmid + '&action=muteall&sesskey=' + sesskey)
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                muteAllBtn.disabled = false;
                                if (res.status === 'success') {
                                    alert(res.message);
                                }
                            })
                            .catch(function() {
                                muteAllBtn.disabled = false;
                            });
                    }
                });
            }

            // Poll modal handlers
            var pollModal = document.getElementById('poll-modal');
            var openPollBtn = document.getElementById('btn-open-poll-modal');
            var closePollBtn = document.getElementById('btn-close-poll-modal');

            if (openPollBtn && pollModal) {
                openPollBtn.addEventListener('click', function() {
                    pollModal.style.display = 'block';
                    pollModal.classList.add('show');
                });
            }
            if (closePollBtn && pollModal) {
                closePollBtn.addEventListener('click', function() {
                    pollModal.style.display = 'none';
                    pollModal.classList.remove('show');
                });
            }

            // Launch poll submit
            var formPoll = document.getElementById('form-create-poll');
            var currentPollId = 0;
            var pollResultsTimer = null;

            if (formPoll) {
                formPoll.addEventListener('submit', function(e) {
                    e.preventDefault();
                    var q = document.getElementById('poll-input-question').value;
                    var opts = document.getElementById('poll-input-options').value;
                    var dur = document.getElementById('poll-input-duration').value;
                    var submitBtn = document.getElementById('btn-submit-create-poll');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> جاري إطلاق الاستطلاع...';

                    fetch('poll_api.php?action=create&cmid=' + cmid + '&question=' + encodeURIComponent(q) + '&options=' + encodeURIComponent(opts) + '&duration=' + dur + '&sesskey=' + sesskey, {
                        method: 'POST'
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> <?php echo get_string('create_poll', 'mod_liveclass'); ?>';
                        if (res.status === 'success' && res.poll) {
                            currentPollId = res.poll.id;
                            document.getElementById('poll-live-results').classList.remove('d-none');
                            pollResults();
                            if (pollResultsTimer) clearInterval(pollResultsTimer);
                            pollResultsTimer = setInterval(pollResults, 3000);
                        }
                    })
                    .catch(function() {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fa fa-paper-plane"></i> <?php echo get_string('create_poll', 'mod_liveclass'); ?>';
                    });
                });
            }

            function pollResults() {
                if (!currentPollId) return;
                fetch('poll_api.php?action=results&cmid=' + cmid + '&poll_id=' + currentPollId + '&sesskey=' + sesskey)
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.status === 'success') {
                            document.getElementById('poll-results-total').textContent = res.total;
                            var barsHtml = '';
                            res.options.forEach(function(opt) {
                                var cnt = res.counts[opt] || 0;
                                var pct = res.total > 0 ? Math.round((cnt / res.total) * 100) : 0;
                                barsHtml += '<div class="mb-2">' +
                                    '<div class="d-flex justify-content-between small fw-bold mb-1">' +
                                    '<span>' + opt + '</span><span>' + cnt + ' (' + pct + '%)</span>' +
                                    '</div>' +
                                    '<div class="progress" style="height: 10px;">' +
                                    '<div class="progress-bar bg-success" style="width: ' + pct + '%;"></div>' +
                                    '</div></div>';
                            });
                            document.getElementById('poll-results-bars').innerHTML = barsHtml;
                        }
                    });
            }

            // Function to reload roster data
            function refreshRoster() {
                if (spinner) spinner.classList.add('fa-spin');
                fetch('get_roster.php?cmid=' + cmid + '&groupid=' + groupid + '&sesskey=' + sesskey)
                    .then(function(res) { return res.json(); })
                    .then(function(json) {
                        if (spinner) spinner.classList.remove('fa-spin');
                        if (json.status === 'success' && json.data) {
                            var d = json.data;
                            document.getElementById('counter-total').textContent = d.total_students;
                            document.getElementById('counter-inclass').textContent = d.in_class_count;
                            document.getElementById('counter-online').textContent = d.online_not_in_class_count;
                            document.getElementById('counter-absent').textContent = d.absent_count;

                            // Update active hand raises counter and button
                            var lowerHandsRosterBtn = document.getElementById('btn-roster-lower-hands');
                            var counterRaised = document.getElementById('counter-raised-hands');
                            var raisedCount = d.raised_hands_count || 0;
                            if (counterRaised) counterRaised.textContent = raisedCount;
                            if (lowerHandsRosterBtn) {
                                if (raisedCount > 0) {
                                    lowerHandsRosterBtn.classList.remove('d-none');
                                } else {
                                    lowerHandsRosterBtn.classList.add('d-none');
                                }
                            }

                            if (d.students && d.students.length > 0) {
                                d.students.forEach(function(st) {
                                    var row = document.getElementById('student-row-' + st.id);
                                    if (row) {
                                        var statusTd = row.cells[2];
                                        var progressTd = row.cells[3];
                                        var focusTd = row.cells[5];
                                        var actionsTd = row.cells[6];

                                        // Hand raised badge in student name
                                        var handBadge = document.getElementById('roster-hand-' + st.id);
                                        if (handBadge) {
                                            if (st.hand_raised) {
                                                handBadge.classList.remove('d-none');
                                            } else {
                                                handBadge.classList.add('d-none');
                                            }
                                        }

                                        if (st.status === 'in_class') {
                                            statusTd.innerHTML = '<span class="badge bg-success-subtle text-success border border-success px-2 py-1">' +
                                                '<span class="pulse-dot pulse-dot-green"></span> <?php echo get_string('status_in_class', 'mod_liveclass'); ?>' +
                                                '</span>';
                                            if (actionsTd) {
                                                var nudgeBtn = actionsTd.querySelector('.btn-nudge');
                                                if (nudgeBtn) nudgeBtn.style.display = 'none';
                                            }
                                        } else if (st.status === 'online_not_in_class') {
                                            statusTd.innerHTML = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning px-2 py-1 shadow-sm">' +
                                                '<span class="pulse-dot pulse-dot-amber"></span> <?php echo get_string('status_online_moodle', 'mod_liveclass'); ?>' +
                                                '</span>';
                                        } else {
                                            statusTd.innerHTML = '<span class="badge bg-light text-muted border px-2 py-1">' +
                                                '<span class="pulse-dot pulse-dot-gray"></span> <?php echo get_string('status_absent', 'mod_liveclass'); ?>' +
                                                '</span>';
                                        }

                                        if (progressTd) {
                                            var bgClass = (st.percent >= <?php echo (int)$liveclass->min_attendance_pct; ?>) ? 'bg-success' : 'bg-info';
                                            progressTd.innerHTML = '<div class="d-flex align-items-center">' +
                                                '<div class="progress flex-grow-1 me-2" style="height: 8px;">' +
                                                '<div class="progress-bar ' + bgClass + '" style="width: ' + Math.min(100, st.percent) + '%;"></div>' +
                                                '</div>' +
                                                '<span class="small fw-bold">' + st.percent + '%</span>' +
                                                '</div>' +
                                                '<div class="small text-muted">' + st.duration_mins + ' دقيقة</div>';
                                        }

                                        if (focusTd) {
                                            if (st.tab_switches > 0) {
                                                focusTd.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-2 py-0 btn-focus-detail" data-userid="' + st.id + '" data-name="' + encodeURIComponent(st.fullname) + '" data-count="' + st.tab_switches + '" title="<?php echo get_string('focus_alerts_desc', 'mod_liveclass'); ?>"><i class="fa fa-exclamation-triangle"></i> ' + st.tab_switches + ' <span style="font-size:0.75rem;">(تفاصيل)</span></button>';
                                            } else {
                                                focusTd.innerHTML = '<span class="text-muted small">-</span>';
                                            }
                                        }
                                    }
                                });
                            }
                        }
                    })
                    .catch(function(err) {
                        if (spinner) spinner.classList.remove('fa-spin');
                    });
            }

            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    refreshRoster();
                });
            }

            // Auto-refresh every 12 seconds for moderator
            setInterval(refreshRoster, 12000);

            // Lower all hands button handler
            var lowerHandsRosterBtn = document.getElementById('btn-roster-lower-hands');
            if (lowerHandsRosterBtn) {
                lowerHandsRosterBtn.addEventListener('click', function() {
                    if (confirm('هل أنت متأكد من رغبتك في خفض جميع أيدي المتدربين؟')) {
                        lowerHandsRosterBtn.disabled = true;
                        fetch('control.php?cmid=' + cmid + '&action=lower_all_hands&sesskey=' + sesskey)
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                lowerHandsRosterBtn.disabled = false;
                                if (res.status === 'success') {
                                    lowerHandsRosterBtn.classList.add('d-none');
                                    document.querySelectorAll('.badge-hand-raised').forEach(function(b) {
                                        b.classList.add('d-none');
                                    });
                                    var rhCounter = document.getElementById('counter-raised-hands');
                                    if (rhCounter) rhCounter.textContent = '0';
                                }
                            })
                            .catch(function() {
                                lowerHandsRosterBtn.disabled = false;
                            });
                    }
                });
            }

            // End session for everyone button handler
            var endSessionBtn = document.getElementById('btn-end-session');
            if (endSessionBtn) {
                endSessionBtn.addEventListener('click', function() {
                    if (confirm('<?php echo get_string('end_session_confirm', 'mod_liveclass'); ?>')) {
                        endSessionBtn.disabled = true;
                        fetch('control.php?cmid=' + cmid + '&action=endsession&sesskey=' + sesskey)
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                if (res.status === 'success') {
                                    window.location.reload();
                                } else {
                                    endSessionBtn.disabled = false;
                                }
                            })
                            .catch(function() {
                                endSessionBtn.disabled = false;
                            });
                    }
                });
            }

            // Focus detail modal handlers
            var focusModal = document.getElementById('modal-focus-detail');
            var closeFocusBtn = document.getElementById('btn-close-focus-modal');
            var closeFocusBtnFooter = document.getElementById('btn-close-focus-modal-footer');

            function hideFocusModal() {
                if (focusModal) {
                    focusModal.style.display = 'none';
                    focusModal.classList.remove('show');
                }
            }
            if (closeFocusBtn) closeFocusBtn.addEventListener('click', hideFocusModal);
            if (closeFocusBtnFooter) closeFocusBtnFooter.addEventListener('click', hideFocusModal);

            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-focus-detail');
                if (!btn) return;
                var uid = btn.getAttribute('data-userid');
                var sname = btn.getAttribute('data-name');
                if (sname && sname.indexOf('%') !== -1) {
                    try { sname = decodeURIComponent(sname); } catch(err) {}
                }
                var count = btn.getAttribute('data-count');

                var nameEl = document.getElementById('focus-modal-student-name');
                if (nameEl) nameEl.textContent = sname;
                var countBadge = document.getElementById('focus-modal-count-badge');
                if (countBadge) countBadge.textContent = count + ' تنبيه';
                var tbody = document.getElementById('focus-modal-tbody');
                tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted"><i class="fa fa-spinner fa-spin"></i> جاري تحميل تفاصيل تحركات الطالب...</td></tr>';

                if (focusModal) {
                    focusModal.style.display = 'block';
                    focusModal.classList.add('show');
                }

                fetch('focus_logs.php?cmid=' + cmid + '&userid=' + uid + '&sesskey=' + sesskey)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.status === 'success' && data.logs && data.logs.length > 0) {
                            var html = '';
                            data.logs.forEach(function(item, idx) {
                                var badgeClass = (item.event_type === 'tab_hidden') ? 'bg-danger' : ((item.event_type === 'focus_lost') ? 'bg-warning text-dark' : 'bg-success');
                                html += '<tr>' +
                                    '<td>' + (idx + 1) + '</td>' +
                                    '<td class="font-monospace small">' + item.time + '</td>' +
                                    '<td><span class="badge ' + badgeClass + '">' + item.label + '</span></td>' +
                                    '<td class="small text-muted">' + (item.details ? item.details : '-') + '</td>' +
                                    '</tr>';
                            });
                            tbody.innerHTML = html;
                        } else {
                            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-muted">لا توجد سجلات مغادرة مسجلة لهذا الطالب في الجلسة الحالية.</td></tr>';
                        }
                    })
                    .catch(function() {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3 text-danger"><i class="fa fa-exclamation-triangle"></i> تعذر تحميل التفاصيل.</td></tr>';
                    });
            });

            // Nudge button handler
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-nudge');
                if (!btn) return;
                var uid = btn.getAttribute('data-userid');
                var sname = btn.getAttribute('data-name');
                btn.disabled = true;
                btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

                fetch('nudge.php?cmid=' + cmid + '&userid=' + uid + '&sesskey=' + sesskey)
                    .then(function(res) { return res.json(); })
                    .then(function(json) {
                        if (json.status === 'success') {
                            btn.className = 'btn btn-sm btn-success disabled';
                            btn.innerHTML = '<i class="fa fa-check"></i> تم التنبيه';
                        } else {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa fa-bell"></i> تنبيه';
                            alert(json.message || 'Error sending nudge');
                        }
                    })
                    .catch(function() {
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fa fa-bell"></i> تنبيه';
                    });
            });
        });
        </script>
    <?php endif; ?>

    <!-- Session Recordings & Archive Card -->
    <?php
    $past_sessions = $DB->get_records_select('liveclass_sessions', 'liveclassid = ? ORDER BY id DESC', [$liveclass->id]);
    ?>
    <div class="card shadow-sm border-0 mt-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 text-dark"><i class="fa fa-history text-secondary"></i> <?php echo get_string('recordings_archive', 'mod_liveclass'); ?></h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($past_sessions)): ?>
                <div class="p-4 text-center text-muted">
                    <i class="fa fa-video-camera fa-2x mb-2 text-secondary opacity-50"></i>
                    <p class="mb-0"><?php echo get_string('no_recordings_yet', 'mod_liveclass'); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th><?php echo get_string('session_date', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('session_duration', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('session_attendees', 'mod_liveclass'); ?></th>
                                <th><?php echo get_string('recording_status', 'mod_liveclass'); ?></th>
                                <th class="text-center"><?php echo get_string('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $s_idx = 1; foreach ($past_sessions as $ps): 
                                $duration_str = ($ps->actual_end > $ps->actual_start) 
                                    ? round(($ps->actual_end - $ps->actual_start) / 60) . ' دقيقة' 
                                    : ($ps->status === 'active' ? '<span class="badge bg-success">نشطة الآن</span>' : '-');
                            ?>
                                <tr>
                                    <td><?php echo $s_idx++; ?></td>
                                    <td><strong><?php echo userdate($ps->timecreated ?: $ps->actual_start); ?></strong></td>
                                    <td><?php echo $duration_str; ?></td>
                                    <td><span class="badge bg-primary rounded-pill"><?php echo $ps->total_participants; ?></span></td>
                                    <td>
                                        <?php if (!empty($ps->recording_url)): ?>
                                            <span class="badge bg-success"><i class="fa fa-check"></i> <?php echo get_string('recording_ready', 'mod_liveclass'); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border"><?php echo get_string('recording_none', 'mod_liveclass'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (!empty($ps->recording_url)): ?>
                                            <a href="<?php echo s($ps->recording_url); ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill">
                                                <i class="fa fa-play"></i> <?php echo get_string('play_recording', 'mod_liveclass'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
