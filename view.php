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

// Find or initialize active session.
$session = $DB->get_record('liveclass_sessions', [
    'liveclassid' => $liveclass->id,
    'status'      => 'active'
]);

if (!$session) {
    $session = $DB->get_record('liveclass_sessions', [
        'liveclassid' => $liveclass->id,
        'status'      => 'scheduled'
    ]);
}

if (!$session && $is_moderator) {
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

// Initialize AMD Classroom module.
$PAGE->requires->js_call_amd('mod_liveclass/classroom', 'init', [[
    'host'             => $livekit_host,
    'token'            => $token,
    'roomName'         => $liveclass->room_name,
    'displayName'      => $displayname,
    'isModerator'      => (bool)$is_moderator,
    'sessionId'        => (int)$session_id,
    'cmid'             => (int)$cm->id,
    'sesskey'          => sesskey(),
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
                <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                    <?php if (count($group_info->all_groups) > 1): ?>
                        <div class="dropdown me-2">
                            <select id="group-filter-select" class="form-select form-select-sm" onchange="window.location.href='view.php?id=<?php echo $cm->id; ?>&group='+this.value">
                                <?php foreach ($group_info->all_groups as $g): ?>
                                    <option value="<?php echo $g->id; ?>" <?php echo ($roster->selected_group_id == $g->id) ? 'selected' : ''; ?>>
                                        <?php echo s($g->name); ?> (<?php echo $g->member_count; ?>) <?php echo ($g->is_allowed ? '✅' : '🔒'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <button id="btn-refresh-roster" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="fa fa-refresh" id="refresh-spinner"></i> <?php echo get_string('refresh_roster', 'mod_liveclass'); ?>
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
                                                    <div class="fw-bold"><?php echo s($st->fullname); ?></div>
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
                                                <span class="badge bg-danger rounded-pill px-2">
                                                    <i class="fa fa-exclamation-triangle"></i> <?php echo $st->tab_switches; ?>
                                                </span>
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

        <!-- Command Center Live Sync Script -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var refreshBtn = document.getElementById('btn-refresh-roster');
            var spinner = document.getElementById('refresh-spinner');
            var cmid = <?php echo (int)$cm->id; ?>;
            var groupid = <?php echo (int)$roster->selected_group_id; ?>;
            var sesskey = '<?php echo sesskey(); ?>';

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

            // Auto-refresh every 25 seconds for moderator
            setInterval(refreshRoster, 25000);

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
</div>

<?php
echo $OUTPUT->footer();
