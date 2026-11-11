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
 * Spontaneous in-class polls & active presence API.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: application/json; charset=utf-8');

$action = required_param('action', PARAM_ALPHA);
$cmid   = required_param('cmid', PARAM_INT);
require_sesskey();

$cm = get_coursemodule_from_id('liveclass', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$liveclass = $DB->get_record('liveclass', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

$is_moderator = has_capability('mod/liveclass:moderate', $context);

switch ($action) {
    case 'create':
        require_capability('mod/liveclass:moderate', $context);

        $question = required_param('question', PARAM_TEXT);
        $options_raw = required_param('options', PARAM_RAW);
        $duration = optional_param('duration', 30, PARAM_INT);

        // Normalize options into JSON array.
        $lines = preg_split('/[\r\n]+/', trim($options_raw));
        $options = array_values(array_filter(array_map('trim', $lines)));
        if (empty($options)) {
            $options = ['نعم / Yes', 'لا / No'];
        }

        // Active session.
        $session = $DB->get_record('liveclass_sessions', ['liveclassid' => $liveclass->id, 'status' => 'active']);
        $session_id = $session ? (int)$session->id : 0;

        $poll = (object)[
            'liveclassid'      => (int)$liveclass->id,
            'sessionid'        => $session_id,
            'question'         => $question,
            'options'          => json_encode($options, JSON_UNESCAPED_UNICODE),
            'duration_seconds' => max(10, min(300, $duration)),
            'timecreated'      => time(),
            'timemodified'     => time()
        ];
        $poll->id = $DB->insert_record('liveclass_polls', $poll);

        echo json_encode([
            'status' => 'success',
            'poll'   => [
                'id'          => $poll->id,
                'question'    => $poll->question,
                'options'     => $options,
                'duration'    => $poll->duration_seconds,
                'timecreated' => $poll->timecreated
            ]
        ]);
        break;

    case 'get_active':
        // Check for any poll created within its active duration window.
        $now = time();
        $polls = $DB->get_records_sql("
            SELECT * 
              FROM {liveclass_polls}
             WHERE liveclassid = :liveclassid
               AND timecreated >= :cutoff
             ORDER BY id DESC LIMIT 1
        ", [
            'liveclassid' => $liveclass->id,
            'cutoff'      => $now - 300 // within last 5 minutes
        ]);

        if (empty($polls)) {
            echo json_encode(['status' => 'none']);
            break;
        }

        $active_poll = reset($polls);
        $time_left = max(0, ($active_poll->timecreated + $active_poll->duration_seconds) - $now);

        // Check if current user already answered.
        $has_answered = $DB->record_exists('liveclass_poll_answers', [
            'pollid' => $active_poll->id,
            'userid' => $USER->id
        ]);

        echo json_encode([
            'status'       => 'active',
            'poll'         => [
                'id'           => (int)$active_poll->id,
                'question'     => $active_poll->question,
                'options'      => json_decode($active_poll->options, true),
                'duration'     => (int)$active_poll->duration_seconds,
                'time_left'    => $time_left,
                'has_answered' => $has_answered,
                'is_expired'   => ($time_left <= 0)
            ]
        ]);
        break;

    case 'answer':
        $poll_id = required_param('poll_id', PARAM_INT);
        $answer  = required_param('answer', PARAM_TEXT);

        $poll = $DB->get_record('liveclass_polls', ['id' => $poll_id, 'liveclassid' => $liveclass->id], '*', MUST_EXIST);

        // Check duplicate.
        $exists = $DB->get_record('liveclass_poll_answers', ['pollid' => $poll_id, 'userid' => $USER->id]);
        if ($exists) {
            echo json_encode(['status' => 'already_answered']);
            break;
        }

        $ans_record = (object)[
            'pollid'      => $poll_id,
            'userid'      => $USER->id,
            'answer'      => $answer,
            'timecreated' => time()
        ];
        $DB->insert_record('liveclass_poll_answers', $ans_record);

        echo json_encode([
            'status'  => 'success',
            'message' => get_string('poll_answered', 'mod_liveclass')
        ]);
        break;

    case 'results':
        require_capability('mod/liveclass:moderate', $context);
        $poll_id = required_param('poll_id', PARAM_INT);
        $poll = $DB->get_record('liveclass_polls', ['id' => $poll_id], '*', MUST_EXIST);

        $answers = $DB->get_records('liveclass_poll_answers', ['pollid' => $poll_id]);
        $options = json_decode($poll->options, true) ?: [];

        $counts = [];
        foreach ($options as $opt) {
            $counts[$opt] = 0;
        }
        $total = count($answers);

        foreach ($answers as $ans) {
            if (isset($counts[$ans->answer])) {
                $counts[$ans->answer]++;
            } else {
                $counts[$ans->answer] = 1;
            }
        }

        echo json_encode([
            'status'  => 'success',
            'total'   => $total,
            'counts'  => $counts,
            'options' => $options
        ]);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
exit;
