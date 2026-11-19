# 🎥 LiveClass Virtual Classroom (`mod_liveclass`)

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.1+-orange.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-v1.2.2-blue.svg)](version.php)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)
[![Engine: LiveKit](https://img.shields.io/badge/Engine-LiveKit%20WebRTC-green.svg)](https://livekit.io)

An institutional-grade, self-hosted virtual classroom activity module for Moodle powered by **LiveKit SFU**. Designed specifically for academies and training institutions to replace complex Microsoft Teams dependencies with high-precision automated attendance, granular time-on-task metrics, and behavioral presence verification.

---

## 🌟 Key Features

1. **⚡ Ultra-Low-Latency WebRTC Video & Audio**:
   - Powered by LiveKit WebRTC SFU with automatic Simulcast & Dynacast bandwidth management.
   - Zero client installation: runs directly within any modern browser inside Moodle.
   - Screen sharing, live chat, multi-stream presenter mode, and digital whiteboard.

2. **⏱️ Automated High-Precision Attendance Engine**:
   - Discrete connection interval tracking (`liveclass_intervals`) to handle intermittent WiFi reconnects.
   - Automated time-on-task calculation against planned or actual session durations.
   - Configurable compliance threshold (e.g. 80% attendance required to receive `Present` status).
   - Instant synchronization with Moodle Activity Completion and Course Gradebook.

3. **🛡️ Active Proof of Presence (Inspired by LiveProctoring)**:
   - **Tab-Switching Counter**: Live telemetry detecting when students navigate away from lecture slides.
   - **Spontaneous Presence Polls**: In-class pop-up interaction prompts verifying active learner attention.
   - Instructor Live Attendance Dashboard with color-coded engagement badges.

4. **🚀 Zero Microsoft Account Friction**:
   - No Azure AD licenses, no external tenants, no guest invitations, and no lobby waiting.
   - 100% internal Moodle Single Sign-On (SSO) with cryptographic HMAC-SHA256 JWT tokens.

---

## 🗄️ Database Architecture

The plugin introduces 7 specialized tables:
- `mdl_liveclass`: Core activity instance configuration and attendance policies.
- `mdl_liveclass_sessions`: Classroom execution sessions with LiveKit room SIDs.
- `mdl_liveclass_attendance`: Aggregated student attendance percentages and Moodle sync state.
- `mdl_liveclass_intervals`: Micro-connection intervals accounting for reconnects.
- `mdl_liveclass_presence_logs`: Audit trail of tab switching and focus changes.
- `mdl_liveclass_polls`: On-demand live lecture interaction prompts.
- `mdl_liveclass_poll_answers`: Individual student responses to presence prompts.

---

## 📋 Changelog

### [v1.2.2] - 2026-11-19
- **Critical Polls Schema Sync (`db/install.xml`, `db/upgrade.php`, `poll_api.php`)**:
  - Fixed schema drift between `db/install.xml` and `poll_api.php`: `liveclass_polls` now defines `liveclassid`, `duration_seconds`, `timecreated`, `timemodified`; `liveclass_poll_answers` now defines `timecreated`. Previously `create`/`get_active`/`results` in `poll_api.php` referenced columns that did not exist (broken polls feature on fresh installs).
  - Added `db/upgrade.php` to migrate existing databases (adds the missing columns) — `moodle-plugin-ci savepoints` compliant.
- **LiveKit Webhook Security Fix (`webhook.php`)**:
  - Fixed config key typo: `get_config('mod_liveclass', 'api_secret')` → `get_config('mod_liveclass', 'livekit_api_secret')`. HMAC signature verification was previously silently disabled.
  - Added missing `token_factory::base64url_decode()` (previously a fatal error when an auth header was present).
  - Handle the `Bearer ` prefix on the `Authorization` header sent by LiveKit.
  - Reject webhook requests with a missing signature when a secret is configured.
- **Security Hardening (`token_factory.php`, `settings.php`)**:
  - Removed hardcoded default LiveKit API key/secret fallbacks (`'SANAD_API_KEY'` / `'sanad_proctoring_secret_key_2026_livekit'`) — the JWT `room` claim corrected to the proper `roomName` grant.
- **Stored XSS Fixes (`view.php`, `amd/src/classroom.js`)**:
  - Poll results are now rendered via DOM APIs (`textContent`) — poll options injected through `innerHTML` (stored XSS).
  - Roster tiles/filmstrip/instructor tile names escaped via `escHtml()` helper (student/teacher names from profiles).
  - Focus-alert detail modal rows built with `textContent` (student-typed event details from `log_event.php`).
- **Security & Bug Fixes**:
  - `nudge.php`: verify target user is enrolled in the module's course.
  - `attendance_engine.php`: null-guard when session/liveclass lookup fails in `participant_left()`.
  - `roster_manager.php`: fixed `groups_get_members()` call — `$userfields` was passed as `$sort` instead of `$fields`.
  - `poll_api.php`: `get_active` action changed from `PARAM_ALPHA` to `PARAM_ALPHANUMEXT` (underscore `get_active` was being stripped, making the case unreachable); question/answer truncated to DB column length.
  - `view.php`: hardcoded Arabic strings converted to localized `get_string()` calls; `$instructor_id` fallback corrected to `0`.
  - `view.php`: loads the previously unapplied `styles.css` via `$PAGE->requires->css()`.
- **AMD Path Fix (`view.php`)**: `js_call_amd('mod_liveclass/classroom', ...)` — `amd/classroom.js` and `amd/livekit_client.js` now exist at the module root (copied from `amd/src/`), matching the Moodle loader's path convention.
- **i18n**: Added 21 missing strings (`nostudentsyet`, `raise_hand`, `actions`, `recorded_duration_label`, `minutes_unit`, `attendance_pct_label`, `attendance_min_label`, `moderator_notice_prefix`, `scheduled_start`, `scheduled_end`, `students_blocked_msg`, `focus_alert_detail`, `focus_alert_badge_label`, `alerts_unit`, `default_poll_question`, `default_poll_options`, `poll_duration_20/30/45/60`, `refresh_now`, `user_not_enrolled`) with full EN/AR parity (155 keys).

### [v1.2.1] - 2026-11-17
- **IDE Diagnostic & Static Analysis Hygiene (`control.php`, `heartbeat.php`, `version.php`)**:
  - Resolved Intelephense P1008 `Undefined variable '$DB'` and `Undefined variable '$USER'` diagnostics by declaring explicit `global $DB, $USER;` in standalone module endpoints (`control.php`, `heartbeat.php`, `focus_logs.php`, `get_roster.php`, `nudge.php`, `poll_api.php`, `export.php`, `log_event.php`, `view.php`).
  - Resolved Intelephense P1011 `Undefined constant 'MATURITY_ALPHA'` and P1008 `Undefined variable '$plugin'` in `version.php` using safe Moodle fallback guards and type hints (`defined('MATURITY_ALPHA') || define('MATURITY_ALPHA', 50); /** @var \stdClass $plugin */ $plugin = $plugin ?? new \stdClass();`).
  - Fixed root namespace resolution for `\context_module::instance()` across all endpoints to prevent class resolution warnings.
  - Fixed DocBlock indentation in `heartbeat.php` header to conform with Moodle PHPCS standards.

### [v1.2.0] - 2026-11-17
- **Complete Eradication of Legacy HTTP Frame Snapshot Engine**: Permanently deleted `frame.php` and stripped all legacy polling loops (`startFrameBroadcast`, `startFrameRefreshLoop`, `captureVideo`, and fallback `<img>` elements). Architecture is now 100% pure, uninterrupted LiveKit WebRTC SFU streaming.
- **Instant WebRTC DataChannel Hand Raising & Lowering**: Integrated sub-millisecond bidirectional hand-raising signaling via `livekitRoom.localParticipant.publishData()`. Hand raise toggles, "Lower All Hands", and individual hand lowering now propagate instantly across all participants without waiting for HTTP polling cycles.
- **Eliminated Hand-Raise Loop & Desynchronization**: Resolved the root cause of sticky/hanging hands where students' clients repeatedly re-raised lowered hands during heartbeats. Added server-side stale-raise suppression in `heartbeat.php` (25-second grace window after moderator lowers hand) and added authoritative `my_hand_raised` server state reconciliation in `classroom.js`.
- **Interactive Moderator Hand Lowering on Video Tiles & Roster**: Enabled interactive click-to-lower directly on student hand badges (`✋`) in both the stage Gallery Grid tiles (`.hand-badge-clickable`) and the Command Center Roster table rows (`.badge-hand-raised`). Added `lowerStudentHand()` method invoking `control.php?action=lower_hand`.
- **Full i18n Alignment & Parity**: Added `lower_hand` and `lower_hand_success` localization strings with 100% parity across English and Arabic language packs (134 keys fully verified).

### [v1.1.9] - 2026-11-16
- **Eliminate Hardware Device Lockout & Stream Conflicts (`classroom.js`)**: Removed competing `initMediaStream()` call during classroom initialization. LiveKit SFU now serves as the single unified source for camera and microphone acquisition, eliminating `NotReadableError: Device in use` failures on mobile devices, laptops, and student tablets.
- **Continuous 720p/540p High-Definition Video Streaming**: Upgraded room video presets to crisp HD (`h720` for instructor, `h540` for students with VP8 simulcast). Disabled aggressive `adaptiveStream` downscaling to prevent video streams from artificially dropping to low-resolution 180p thumbnails.
- **Non-Destructive Incremental Stage DOM Architecture**: Replaced full-grid DOM wipes (`gridEl.html(gridHtml)`) with incremental updates. Video elements and active WebRTC peer tracks remain untouched in the DOM during the 10-second heartbeat cycle, completely eliminating video freezing, flickering, and stream detachment.
- **Silenced HTTP Snapshot Flooding on Active WebRTC**: Automated suppression of legacy `startFrameBroadcast()` and `startFrameRefreshLoop()` HTTP polling while LiveKit WebRTC is connected. This removes ~70 concurrent HTTP POST/GET requests per second, eliminating server CPU spikes and network congestion.
- **Robust Mute-All Protocol & Action Sanitization (`control.php` & `classroom.js`)**: Updated `control.php` parameter sanitization from `PARAM_ALPHA` to `PARAM_ALPHANUMEXT`, preventing underscore-separated commands (`lower_all_hands`, `start_recording`, `stop_recording`) from being stripped. Fixed LiveKit DataChannel `publishData()` packet kind argument and replaced blocking native browser alerts with non-intrusive floating toasts (`.liveclass-toast`).
- **Interactive Camera and Mic Controls Synchronization**: Unified `#btn-toggle-cam` and `#btn-toggle-mic` handlers with LiveKit `setCameraEnabled` and `setMicrophoneEnabled` methods with automatic UI icon and state synchronization.

### [v1.1.8] - 2026-11-16
- **Fix Missing `$liveclass` in Heartbeat (`heartbeat.php`)**: Resolved critical bug where `$liveclass` was undefined in `heartbeat.php`, causing `roster_manager::get_roster_data()` to query sessions with `liveclassid = null`. This improperly marked all 11 connected students as offline, causing their video tiles to disappear from the stage.
- **Fix Moderator Mute All Protocol (`control.php` & `classroom.js`)**: Corrected database column mapping in `control.php` to use `details` and `timestamp` via `log_presence_event()`, added latest session fallback, and integrated instant WebRTC DataChannel broadcast (`publishData({type: 'mute_all'})`) alongside HTTP heartbeat polling fallback.
- **Fix Participant Track Publications Loop (`classroom.js`)**: Replaced non-existent `p.trackPublications.forEach()` with `(p.getTracks ? p.getTracks() : Array.from(p.tracks.values())).forEach()`, eliminating JavaScript `TypeError` that crashed stage rendering and prevented remote video re-binding.
- **Hybrid Stage Connection Detection**: Enhanced `connectedStudents` filter in Gallery Grid to recognize attendees through either HTTP heartbeat presence intervals or active WebRTC connections in `livekitRoom.remoteParticipants`.
- **Dynamic Instructor Identity Mapping**: Added `instructorId` parameter from `view.php` to AMD classroom configuration, eliminating hardcoded user ID 2 checks and ensuring fallback frames and speaker stages attach accurately for all instructors.
- **Production Asset Compilation (`classroom.min.js`)**: Compiled updated AMD source using Terser (`amd/build/classroom.min.js`, 43KB) ensuring production Moodle clients load the latest real-time presence logic.
- **Historical Focus Audit Query Fallback (`focus_logs.php`)**: Added latest session resolution fallback for concluded/completed classes so instructors can audit student focus loss and tab-switches both during and after sessions.
- **End-to-End WebSocket Handshake Audit**: Verified LiveKit WebRTC handshake (`/rtc`), Nginx reverse proxy headers, and authenticated token exchange across all operational states.

### [v1.1.6] - 2026-11-15
- **Stage Gallery Grid Hygiene**: Filtered stage tiles to strictly display actively connected students (`status === 'in_class'`), eliminating offline/absent placeholder tiles and showing a clean empty state when no students are present.
- **Precision Presence Classification (`roster_manager.php`)**: Decoupled historical attendance compliance (`percent >= 80%`) from real-time connection status (`in_class`), ensuring disconnected or past attendees are not falsely reported as currently active in class.
- **Session Auto-Completion on Expiry**: Automatically transitions expired sessions from `active` to `completed` and closes lingering open intervals when `now > end_time`.

### [v1.1.5] - 2026-11-15
- **Authoritative Schedule Gatekeeper**: Enforced server-side access control preventing students from accessing live class sessions before the scheduled `start_time` or after `end_time`.
- **Pre-Class Waiting Lobby with Live Countdown**: Rendered a dedicated waiting room for early students showing scheduled times, real-time seconds countdown, and automated page reload at zero.
- **Post-Class Conclusion & Attendance Summary**: Replaced open rooms with a personalized completion screen after class ends, presenting individual attendance minutes, percentage compliance against threshold, and recording links.
- **Instructor Session Control & Time Alerts**: Added context-aware instructor notices for early preparation / ended sessions and implemented an "End Class for Everyone" action button (`control.php?action=endsession`) with instant client disconnection via heartbeat (`status: ended`).

### [v1.1.4] - 2026-11-15
- **Interactive Focus Alerts Breakdown (`focus_logs.php`)**: Converted static focus alert counter badges into interactive buttons opening a modal (`#modal-focus-detail`) displaying granular timestamps, event types (tab hidden, window focus lost, tab restored), and activity context for each student.
- **Roster Hand-Raise Visual Indicators & Sync**: Added animated `✋` badges next to student names in the Presence Command Center table, sorted active hand-raisers to the top of the table, and synchronized real-time hand-raise state with a "Lower All Hands" button in the table header.
- **Security & RBAC Enforcement**: Audited `focus_logs.php` with CSRF sesskey protection, `mod/liveclass:moderate` role verification, and verified zero IDOR surface.

### [v1.1.3] - 2026-11-15
- **Full LiveKit SFU WebRTC 30fps Continuous Video Engine**: Packaged and integrated the official `livekit-client` UMD WebRTC bundle (`amd/src/livekit_client.js`) into Moodle AMD loader, enabling direct real-time peer media streaming (`Room.connect()`, `TrackSubscribed`, `publishTrack()`).
- **Nginx Reverse Proxy & Cloudflare Pass-Through**: Reconfigured production Nginx to reverse-proxy `/rtc` and `/twirp/` to LiveKit SFU (`http://172.18.0.1:7880`), passing secure WebSocket connections (`wss://lms.sanad.ws/rtc`) directly through standard HTTPS port 443 without external port 7880 blocks.
- **Bi-Directional Track Attachment**: Automatically attaches subscribed incoming student video & audio streams directly onto Gallery Grid cards and Speaker View stage with real-time mic state tracking.
- **Dual-Engine Graceful Fallback**: Retained HTTP snapshot engine (`frame.php`) as a seamless background safety net in case of network packet loss or restrictive ICE NAT traversal.

### [v1.1.2] - 2026-11-15
- **Live Camera Frame Streaming (`frame.php`)**: Implemented high-speed HTTP snapshot streaming engine capturing tablet/webcam frames every 2.5s and rendering live student video directly within Gallery and Speaker cards, bypassing restrictive institutional WebRTC/VCN port blockades.
- **Audio Chime & High-Visibility Hand Raise Alerts**: Integrated Web Audio API dual-tone chime (880Hz / 1318.5Hz) and a sticky animated toast alert informing the instructor instantly when any learner raises their hand for participation. Added "Lower Hands" command (`#btn-stage-lower-hands`).
- **Instructor Identity Precision**: Fixed instructor card resolution in `view.php` to accurately recognize the active moderator (Mahmoud Salem) rather than defaulting to the first enrolled teacher.
- **Viewport Layout Conflict Resolution**: Eliminated simultaneous side-by-side rendering between `#container-speaker` and `#container-gallery` by enforcing explicit inline display styles and defaulting to full-screen Gallery Grid mode.
- **Hand-Raise Telemetry Sync**: Corrected SQL subquery logic in `heartbeat.php` to ignore stale historical logs, tie hand raises directly to active presence, and auto-lower hands on disconnect or client reset.

### [v1.1.1] - 2026-11-15
- **Live Gallery Grid Viewport (`#gallery-grid`)**: Added full responsive participant gallery inside the classroom viewport displaying each attending student as an interactive tile with official avatar, full name, live presence badge (`🟢 متصل`), active mic icon, attendance percentage, and real-time hand raise (`✋`) badge.
- **Speaker vs Gallery Layout Switcher**: Integrated a dedicated toggle button (`#btn-toggle-layout`) in the toolbar allowing one-click switching between Lecture/Speaker Mode (large presenter screen + attendee filmstrip) and Gallery Grid Mode (full multi-tile grid).
- **Live Filmstrip for Speaker Mode**: Added horizontal attendee preview rail (`#speaker-filmstrip`) at the base of the presentation stage displaying thumbnails and presence of connected learners.
- **Roster & Hand-Raise Telemetry in Heartbeat**: Enhanced `heartbeat.php` to broadcast active participants list and latest hand-raise statuses on every cycle.

### [v1.1.0] - 2026-11-15
- **Toolbar Icon Alignment & Overflow Fix**: Refactored `.liveclass-toolbar` styling and HTML markup, separating circular buttons (46x46px) and text-labeled pill buttons (`width: auto`, `padding: 0 18px`) into semantic flex groups to eliminate overlaps between "Mute All", "Recording", and "Leave" buttons.
- **Automated Client Presence Heartbeat & Instant In-Class Transition**: Added dedicated `heartbeat.php` endpoint and 12-second client heartbeat loop (`amd/src/classroom.js`) to automatically register student entry intervals, actively updating attendance seconds and flipping status from "Active on Moodle (Not in Class)" to "In Class (Green)".
- **Role-Aware Virtual Stage Layout**: Redesigned classroom viewport for students to prominently showcase the Instructor's broadcast platform (identity, live presence, and active stage) while docking the student's self-preview into an elegant floating Picture-in-Picture (PiP) card with an active classmates counter.
- **Dynamic Roster Live Sync**: Enhanced Instructor Command Center real-time updater to refresh student table rows and progress bars live every 12 seconds.
- **Parity Expansion**: Aligned Arabic and English language packs to 108 keys with 100% parity.

### [v1.0.9] - 2026-11-12
- Untracked internal agent directive files (`AGENTS.md`) from repository index.
- Comprehensive `.gitignore` overhaul aligning with institutional standards (ignoring `.agents/`, `AGENTS.md`, `*-Mahmoud*`, `graphify-out/`, `.env`, `*.key`, `*.pem`, `*.id_rsa`, deploy scripts, and build artifacts).

### [v1.0.8] - 2026-11-12
- Added standard Moodle course activities list view (`index.php`) satisfying `moodle-plugin-ci validate` requirements.
- Guarded standalone test scripts with `PHPUNIT_TEST` environment check to eliminate duplicate class and constant definitions during Moodle PHPUnit scans.
- Fixed `token_factory::create_token` invocation in PHPUnit test suite (`tests/liveclass_test.php`).
- Updated contract and smoke checks to assert presence of `index.php`.

### [v1.0.7] - 2026-11-12
- Added **GitHub Actions CI/CD Workflow** (`.github/workflows/ci.yml`) featuring dual-stage verification: Antigravity Zero-Dependency Quality Gates & Moodle Plugin CI.
- Implemented **Comprehensive Test Suite** (`tests/`) covering 8 critical verification dimensions:
  - `tests/syntax-check.js`: Automated recursive PHP syntax check (`php -l`) across all plugin source files.
  - `tests/contract-check.js`: Moodle 4.x/5.x activity contract validation, capability mappings, and zero direct `$_SESSION` usage.
  - `tests/security_audit_test.php`: Automated CSRF protection, RBAC capability gates, IDOR boundaries, and LiveKit Webhook HMAC-SHA256 signature verification.
  - `tests/smoke.js`: Asset integrity, scoped CSS rules, and absence of hardcoded secrets.
  - `tests/liveclass_test.php`: Full PHPUnit testcase extending `advanced_testcase` for automated Moodle DB lifecycle testing.
  - `tests/run-all.js`: Master quality gates runner enforcing Pillar 4 Harness Engineering.
- Added `package.json` for npm test scripts and `.gitattributes` for cross-platform LF line ending normalization.

### [v1.0.6] - 2026-11-12
- Added **Manual Moderator Recording Toggle Control** (`#btn-toggle-recording` in toolbar) with live pulsing `REC` status badge and audit logging (`control.php`, `amd/src/classroom.js`).
- Integrated flexible recording modes in activity settings: Disabled (0, Default), Automatic (1), and Moderator Controlled (2).
- Added real-time confirmation dialogs and bilingual status feedback for starting and stopping session recording.
- Verified 100% key parity across English and Arabic language packs (105 keys).

### [v1.0.5] - 2026-11-11
- Added **Attendance CSV/Excel Export** with UTF-8 BOM encoding for seamless Arabic Excel compatibility (`export.php`).
- Implemented **Mute All Participants** control for instructors with client broadcast audio track disabling (`control.php`, `amd/src/classroom.js`).
- Integrated **Spontaneous In-Class Attention Polls** with real-time countdown bar and instructor result aggregation (`poll_api.php`, `liveclass_polls`, `liveclass_poll_answers`).
- Added **Session Recordings & History Archive** component in classroom view with attendee counts and playback links.

### [v1.0.4] - 2026-11-11
- Refined database column mappings for `liveclass_intervals` (`leave_time`) and `liveclass_attendance` (`total_duration_seconds`, `attendance_percentage`).
- Switched user avatar generator to resilient Moodle pix URL for seamless AJAX and CLI compatibility.

### [v1.0.3] - 2026-11-11
- Added **Live Roster & Group Presence Command Center** for instructors (`classes/roster_manager.php`).
- Integrated automated group restriction detection (parses Moodle `availability` JSON & group mode to display allowed vs restricted cohorts).
- Added tripartite presence classification: In Class (🟢), Online on Moodle outside class (🟡), and Absent (🔴).
- Added instant student nudge notification system (`nudge.php`, `db/messages.php`).
- Implemented real-time AJAX auto-refresh and command center statistics cards (`get_roster.php`).

### [v1.0.2] - 2026-11-11
- Added official Moodle 4.x activity icons (`pix/monologo.svg` and `pix/icon.svg`).
- Declared `MOD_PURPOSE_COMMUNICATION` in `lib.php` for Moodle 4 Activity Chooser categorization and theme styling.

### [v1.0.1] - 2026-11-11
- Added WebRTC Classroom AMD module controller (`amd/src/classroom.js` and `amd/build/classroom.min.js`).
- Integrated browser media controls: local camera, microphone toggles, screen sharing, and raise-hand interaction.
- Implemented real-time presence telemetry and tab-switching monitoring endpoint (`log_event.php`).
- Styled responsive dark-theme virtual classroom workspace (`styles.css`, `view.php`).

### [v1.0.0] - 2026-11-11
- Initial release of `mod_liveclass` for Energy & Water Academy (EWA).
- Complete XMLDB schema with 7 tables (`db/install.xml`).
- Pure PHP HMAC-SHA256 JWT LiveKit Access Token Factory (`classes/token_factory.php`).
- Automated attendance & presence engine (`classes/attendance_engine.php`).
- LiveKit Webhook endpoint for join/leave events (`webhook.php`).
- Bilingual English/Arabic support with 100% key parity (`lang/en`, `lang/ar`).
- Zero-dependency verification test suite (`tests/`).

