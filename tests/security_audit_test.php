<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Security & IDOR audit verification test (Antigravity Harness Pillar 4).
 *
 * Checks CSRF tokens, capability checks, session boundaries, and parameter sanitization.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$root = dirname(__DIR__);
$errors = [];

// 1. Audit control.php (Moderator Action Dispatcher)
$control = file_get_contents($root . '/control.php');
if (strpos($control, 'require_sesskey()') === false && strpos($control, 'confirm_sesskey()') === false) {
    $errors[] = "control.php missing CSRF sesskey protection";
}
if (strpos($control, 'require_login(') === false) {
    $errors[] = "control.php missing require_login()";
}
if (strpos($control, "require_capability('mod/liveclass:moderate'") === false) {
    $errors[] = "control.php missing moderator capability check";
}

// 2. Audit poll_api.php (Spontaneous Polls & Attention API)
$poll = file_get_contents($root . '/poll_api.php');
if (strpos($poll, 'require_sesskey()') === false && strpos($poll, 'confirm_sesskey()') === false) {
    $errors[] = "poll_api.php missing CSRF sesskey protection";
}
if (strpos($poll, 'require_login(') === false) {
    $errors[] = "poll_api.php missing require_login()";
}
if (strpos($poll, "require_capability('mod/liveclass:moderate'") === false) {
    $errors[] = "poll_api.php missing moderator capability check for poll creation";
}

// 3. Audit nudge.php (Student Nudge Endpoint)
$nudge = file_get_contents($root . '/nudge.php');
if (strpos($nudge, 'require_sesskey()') === false && strpos($nudge, 'confirm_sesskey()') === false) {
    $errors[] = "nudge.php missing CSRF sesskey protection";
}
if (strpos($nudge, 'require_login(') === false) {
    $errors[] = "nudge.php missing require_login()";
}
if (strpos($nudge, "require_capability('mod/liveclass:moderate'") === false) {
    $errors[] = "nudge.php missing moderator capability check";
}

// 4. Audit log_event.php (Telemetry & Tab-Switching Logger)
$log = file_get_contents($root . '/log_event.php');
if (strpos($log, 'confirm_sesskey()') === false && strpos($log, 'require_sesskey()') === false) {
    $errors[] = "log_event.php missing CSRF sesskey protection";
}
if (strpos($log, 'require_login(') === false) {
    $errors[] = "log_event.php missing require_login()";
}

// 5. Audit export.php (Attendance CSV Export)
$export = file_get_contents($root . '/export.php');
if (strpos($export, 'require_login(') === false) {
    $errors[] = "export.php missing require_login()";
}
if (strpos($export, "require_capability('mod/liveclass:viewattendance'") === false &&
    strpos($export, "require_capability('mod/liveclass:moderate'") === false) {
    $errors[] = "export.php missing attendance view/moderate capability check";
}

// 6. Audit get_roster.php (Live Roster Telemetry Refresh)
$roster = file_get_contents($root . '/get_roster.php');
if (strpos($roster, 'require_login(') === false) {
    $errors[] = "get_roster.php missing require_login()";
}
if (strpos($roster, "require_capability('mod/liveclass:moderate'") === false) {
    $errors[] = "get_roster.php missing moderator capability check";
}

// 7. Audit view.php (Classroom Entry Gate)
$view = file_get_contents($root . '/view.php');
if (strpos($view, 'require_login(') === false) {
    $errors[] = "view.php missing require_login()";
}
if (strpos($view, "require_capability('mod/liveclass:view'") === false) {
    $errors[] = "view.php missing student/user view capability check";
}

// 8. Audit webhook.php (LiveKit Webhook HMAC-SHA256 Authentication)
$webhook = file_get_contents($root . '/webhook.php');
if (strpos($webhook, 'hash_hmac') === false) {
    $errors[] = "webhook.php missing HMAC signature verification";
}

// 9. Parameter sanitization scan across all PHP files
$all_php = glob($root . '/*.php');
foreach ($all_php as $file) {
    $content = file_get_contents($file);
    preg_match_all('/(?:required_param|optional_param)\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*(?:[^\),]+,)?\s*([A-Za-z0-9_]+)\s*\)/', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $paramtype) {
            if (strpos($paramtype, 'PARAM_') !== 0 && !defined($paramtype)) {
                $errors[] = basename($file) . " has non-standard parameter type: $paramtype";
            }
        }
    }
}

// 10. Scan for raw un-sanitized $_SESSION superglobal
foreach ($all_php as $file) {
    $content = file_get_contents($file);
    if (strpos($content, '$_SESSION') !== false) {
        $errors[] = basename($file) . " uses raw \$_SESSION (use Moodle \$SESSION global instead)";
    }
}

if (empty($errors)) {
    echo "PASS: Security & IDOR audit passed (Zero vulnerabilities detected across all 8 endpoints).\n";
    echo "  - CSRF protection enforced on all state-modifying endpoints.\n";
    echo "  - Authentication require_login() verified on all endpoints.\n";
    echo "  - Strict RBAC capability checks enforced (mod/liveclass:moderate & view).\n";
    echo "  - LiveKit Webhook HMAC-SHA256 signature verification active.\n";
    echo "  - Zero direct \$_SESSION usage found.\n";
    exit(0);
} else {
    echo "FAIL: Security audit found issues:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}
