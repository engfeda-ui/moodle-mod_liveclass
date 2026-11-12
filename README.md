# 🎥 LiveClass Virtual Classroom (`mod_liveclass`)

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.1+-orange.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-v1.0.8-blue.svg)](version.php)
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

