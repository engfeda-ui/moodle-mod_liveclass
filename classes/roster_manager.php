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
 * LiveClass Roster and Target Group Presence Manager.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_liveclass;

defined('MOODLE_INTERNAL') || die();

class roster_manager {

    /**
     * Determine which groups are allowed vs restricted for a course module.
     *
     * @param \stdClass $cm Course module record.
     * @return \stdClass Group metadata object.
     */
    public static function get_group_info($cm) {
        global $DB;

        $restricted_group_ids = [];

        // 1. Scan Moodle Restrict Access (availability JSON).
        if (!empty($cm->availability)) {
            $avail = json_decode($cm->availability, true);
            if ($avail && isset($avail['c']) && is_array($avail['c'])) {
                $extract = function($conditions) use (&$extract, &$restricted_group_ids, $cm) {
                    foreach ($conditions as $c) {
                        if (isset($c['type']) && $c['type'] === 'group' && isset($c['id'])) {
                            $restricted_group_ids[] = (int)$c['id'];
                        } else if (isset($c['type']) && $c['type'] === 'grouping' && isset($c['id'])) {
                            $groups = groups_get_all_groups($cm->course, 0, (int)$c['id']);
                            foreach ($groups as $g) {
                                $restricted_group_ids[] = (int)$g->id;
                            }
                        } else if (isset($c['c']) && is_array($c['c'])) {
                            $extract($c['c']);
                        }
                    }
                };
                $extract($avail['c']);
            }
        }

        $all_course_groups = groups_get_all_groups($cm->course);
        $allowed_groups = [];
        $restricted_groups = [];
        $has_restrictions = !empty($restricted_group_ids);

        foreach ($all_course_groups as $g) {
            $count = $DB->count_records('groups_members', ['groupid' => $g->id]);
            $g->member_count = $count;

            if ($has_restrictions) {
                if (in_array((int)$g->id, $restricted_group_ids)) {
                    $g->is_allowed = true;
                    $allowed_groups[] = $g;
                } else {
                    $g->is_allowed = false;
                    $restricted_groups[] = $g;
                }
            } else {
                $g->is_allowed = true;
                $allowed_groups[] = $g;
            }
        }

        $target_group = !empty($allowed_groups) ? $allowed_groups[0] : null;

        return (object)[
            'has_restrictions'  => $has_restrictions,
            'target_group'      => $target_group,
            'allowed_groups'    => $allowed_groups,
            'restricted_groups' => $restricted_groups,
            'all_groups'        => array_values($all_course_groups)
        ];
    }

    /**
     * Get real-time student presence and attendance roster.
     *
     * @param \stdClass $cm Course module.
     * @param \stdClass $liveclass Activity instance.
     * @param int|null $groupid Optional specific group to filter.
     * @return \stdClass Roster data and statistical metrics.
     */
    public static function get_roster_data($cm, $liveclass, $groupid = null) {
        global $DB, $OUTPUT;

        $context = \context_module::instance($cm->id);
        $group_info = self::get_group_info($cm);

        // Determine active group.
        $selected_group_id = 0;
        if ($groupid !== null && $groupid > 0) {
            $selected_group_id = (int)$groupid;
        } else if ($group_info->target_group) {
            $selected_group_id = (int)$group_info->target_group->id;
        }

        // Fetch students.
        $userfieldsapi = \core_user\fields::for_userpic()->including('lastaccess', 'username', 'email');
        $userfields = $userfieldsapi->get_sql('u', false, '', '', false)->selects;

        if ($selected_group_id > 0) {
            $members = groups_get_members($selected_group_id, $userfields);
        } else {
            $members = get_enrolled_users($context, 'mod/liveclass:join', 0, $userfields);
        }

        // Get active/latest session.
        $session = $DB->get_record('liveclass_sessions', [
            'liveclassid' => $liveclass->id,
            'status'      => 'active'
        ]);
        if (!$session) {
            $session = $DB->get_record_sql("SELECT * FROM {liveclass_sessions} WHERE liveclassid = ? ORDER BY id DESC LIMIT 1", [$liveclass->id]);
        }

        $session_id = $session ? (int)$session->id : 0;

        // Fetch attendance records.
        $attendance_records = [];
        $active_interval_userids = [];
        $tab_switch_counts = [];

        if ($session_id > 0) {
            $att_rows = $DB->get_records('liveclass_attendance', ['sessionid' => $session_id]);
            foreach ($att_rows as $row) {
                $attendance_records[$row->userid] = $row;
            }

            // Find currently active intervals (leave_time is null/0 or left within last 90 seconds).
            $recent_limit = time() - 90;
            $active_users = $DB->get_records_sql("
                SELECT DISTINCT a.userid 
                  FROM {liveclass_intervals} i
                  JOIN {liveclass_attendance} a ON a.id = i.attendanceid
                 WHERE a.sessionid = :sessionid
                   AND (i.leave_time IS NULL OR i.leave_time = 0 OR i.leave_time > :recent)
            ", ['sessionid' => $session_id, 'recent' => $recent_limit]);

            foreach ($active_users as $au) {
                $active_interval_userids[$au->userid] = true;
            }

            // Tab switch presence warnings.
            $tab_logs = $DB->get_records_sql("
                SELECT userid, COUNT(id) as tab_switches
                  FROM {liveclass_presence_logs}
                 WHERE sessionid = :sessionid AND event_type = 'tab_hidden'
                 GROUP BY userid
            ", ['sessionid' => $session_id]);

            foreach ($tab_logs as $tl) {
                $tab_switch_counts[$tl->userid] = (int)$tl->tab_switches;
            }
        }

        // Fetch course last access for all students.
        $course_access_map = [];
        $ca_rows = $DB->get_records('user_lastaccess', ['courseid' => $cm->course]);
        foreach ($ca_rows as $ca) {
            $course_access_map[$ca->userid] = (int)$ca->timeaccess;
        }

        // Fetch active Moodle sessions within last 15 minutes.
        $recent_session_userids = [];
        $active_sessions = $DB->get_records_select('sessions', 'timemodified > ?', [time() - 900]);
        foreach ($active_sessions as $as) {
            $recent_session_userids[$as->userid] = (int)$as->timemodified;
        }

        $students = [];
        $in_class_count = 0;
        $online_not_in_class_count = 0;
        $absent_count = 0;

        foreach ($members as $user) {
            $userid = (int)$user->id;
            $att = isset($attendance_records[$userid]) ? $attendance_records[$userid] : null;
            $is_connected_now = isset($active_interval_userids[$userid]);
            $tab_switches = isset($tab_switch_counts[$userid]) ? $tab_switch_counts[$userid] : 0;

            $duration = $att ? (int)$att->total_duration_seconds : 0;
            $percent  = $att ? (int)$att->attendance_percentage : 0;

            // Classify presence state.
            $is_in_class = false;
            $is_online_moodle = false;

            if ($is_connected_now || ($att && $percent >= (int)$liveclass->min_attendance_pct)) {
                $is_in_class = true;
                $status = 'in_class';
                $in_class_count++;
            } else {
                // Check if active on Moodle.
                $last_site_access = (int)$user->lastaccess;
                $last_course_access = isset($course_access_map[$userid]) ? $course_access_map[$userid] : 0;
                $has_active_session = isset($recent_session_userids[$userid]);

                $time_window = 900; // 15 minutes.
                $is_recent_site = ($last_site_access > (time() - $time_window));
                $is_recent_course = ($last_course_access > (time() - $time_window));
                $is_during_class = ($last_site_access >= (int)$liveclass->start_time && $last_site_access <= (int)$liveclass->end_time);

                if ($has_active_session || $is_recent_site || $is_recent_course || $is_during_class) {
                    $is_online_moodle = true;
                    $status = 'online_not_in_class';
                    $online_not_in_class_count++;
                } else {
                    $status = 'absent_offline';
                    $absent_count++;
                }
            }

            // Human readable last seen.
            $last_active_time = max((int)$user->lastaccess, isset($course_access_map[$userid]) ? $course_access_map[$userid] : 0);
            $last_seen_str = self::format_time_ago($last_active_time);

            $duration_mins = round($duration / 60);

            // User avatar URL.
            $avatar_url = (new \moodle_url('/user/pix.php/' . $userid . '/f2.jpg'))->out(false);

            $students[] = (object)[
                'id'                 => $userid,
                'fullname'           => fullname($user),
                'username'           => $user->username,
                'email'              => $user->email,
                'avatar_url'         => $avatar_url,
                'status'             => $status,
                'is_in_class'        => $is_in_class,
                'is_online_moodle'   => $is_online_moodle,
                'duration_seconds'   => $duration,
                'duration_mins'      => $duration_mins,
                'percent'            => $percent,
                'tab_switches'       => $tab_switches,
                'last_seen_str'      => $last_seen_str,
                'last_active_time'   => $last_active_time
            ];
        }

        // Sort students: in_class first, then online_not_in_class, then absent.
        usort($students, function($a, $b) {
            $order = ['in_class' => 1, 'online_not_in_class' => 2, 'absent_offline' => 3];
            $oa = $order[$a->status];
            $ob = $order[$b->status];
            if ($oa !== $ob) {
                return $oa - $ob;
            }
            return strcmp($a->fullname, $b->fullname);
        });

        $total_students = count($students);
        $attendance_rate = $total_students > 0 ? round(($in_class_count / $total_students) * 100) : 0;

        return (object)[
            'group_info'                => $group_info,
            'selected_group_id'         => $selected_group_id,
            'total_students'            => $total_students,
            'in_class_count'            => $in_class_count,
            'online_not_in_class_count' => $online_not_in_class_count,
            'absent_count'              => $absent_count,
            'attendance_rate'           => $attendance_rate,
            'students'                  => $students
        ];
    }

    /**
     * Format unix timestamp to localized time ago string.
     *
     * @param int $timestamp Unix timestamp.
     * @return string Localized string.
     */
    public static function format_time_ago($timestamp) {
        if (empty($timestamp)) {
            return get_string('never_logged', 'mod_liveclass');
        }
        $diff = time() - $timestamp;
        if ($diff < 60) {
            return get_string('online_now', 'mod_liveclass');
        } else if ($diff < 3600) {
            $mins = max(1, floor($diff / 60));
            return get_string('min_ago', 'mod_liveclass', $mins);
        } else if ($diff < 86400) {
            $hrs = floor($diff / 3600);
            return get_string('hours_ago', 'mod_liveclass', $hrs);
        } else {
            return userdate($timestamp, get_string('strftimedatetimeshort', 'core_langconfig'));
        }
    }
}
