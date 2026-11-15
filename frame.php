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
 * High-speed live camera snapshot streaming endpoint for classroom participant tiles.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$sessionid = required_param('sessionid', PARAM_INT);
$action    = optional_param('action', 'get', PARAM_ALPHA);

require_login();

$session = $DB->get_record('liveclass_sessions', ['id' => $sessionid], '*', MUST_EXIST);
$liveclass = $DB->get_record('liveclass', ['id' => $session->liveclassid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('liveclass', $liveclass->id, $liveclass->course, false, MUST_EXIST);
$context = context_module::instance($cm->id);
require_capability('mod/liveclass:view', $context);

// IDOR guard check.
if ((int)$session->liveclassid !== (int)$cm->instance) {
    http_response_code(403);
    echo json_encode(['error' => 'IDOR guard triggered']);
    exit;
}

$temp_dir = make_temp_directory('liveclass_frames');
if (empty($temp_dir) || !is_dir($temp_dir)) {
    $temp_dir = sys_get_temp_dir();
}

if ($action === 'push') {
    if (!confirm_sesskey()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid sesskey']);
        exit;
    }

    $raw = optional_param('frame', '', PARAM_RAW);
    if (empty($raw)) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (!empty($json['frame'])) {
            $raw = $json['frame'];
        }
    }

    if (!empty($raw) && preg_match('#^data:image/jpeg;base64,#', $raw)) {
        $data = base64_decode(substr($raw, strpos($raw, ',') + 1));
        if ($data !== false && strlen($data) < 500000) { // Max 500KB guard
            $filepath = $temp_dir . '/liveclass_f_' . $sessionid . '_' . $USER->id . '.jpg';
            file_put_contents($filepath, $data);
            echo json_encode(['status' => 'success', 'time' => time()]);
            exit;
        }
    }
    echo json_encode(['status' => 'ignored']);
    exit;
}

if ($action === 'get') {
    $target_userid = required_param('userid', PARAM_INT);
    $filepath = $temp_dir . '/liveclass_f_' . $sessionid . '_' . $target_userid . '.jpg';

    // Serve fresh frame if available within last 30 seconds
    if (file_exists($filepath) && (filemtime($filepath) > (time() - 30))) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($filepath);
        exit;
    }

    // Fallback: Redirect to default user avatar
    $avatar_url = (new \moodle_url('/user/pix.php/' . $target_userid . '/f1.jpg'))->out(false);
    redirect($avatar_url);
    exit;
}
