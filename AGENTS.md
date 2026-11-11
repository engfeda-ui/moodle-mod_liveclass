# LiveClass Moodle Plugin (`mod_liveclass`) - Architecture & Context

## Architecture Overview
`mod_liveclass` is a native Moodle Activity Module (`mod_`) providing virtual classrooms using a self-hosted **LiveKit SFU** server.

- **Frankenstyle Component**: `mod_liveclass`
- **Moodle Version Requirement**: 4.0+ (Tested up to 4.5+)
- **Media Engine**: LiveKit WebRTC SFU (`livekit/livekit-server:latest`)
- **Key Tables**:
  - `mdl_liveclass`
  - `mdl_liveclass_sessions`
  - `mdl_liveclass_attendance`
  - `mdl_liveclass_intervals`
  - `mdl_liveclass_presence_logs`
  - `mdl_liveclass_polls`
  - `mdl_liveclass_poll_answers`

## Server Infrastructure
- **Server**: `150.230.241.37` (Ubuntu Linux)
- **LiveKit Server Port**: `7880` (WebSocket / Signaling), `7881` (TCP RTC), `50000-50200` (UDP RTC)
- **LiveKit API Key**: `SANAD_API_KEY`
- **LiveKit API Secret**: `sanad_proctoring_secret_key_2026_livekit`
- **Moodle App Container**: `moodle-app`

## Verification Suite
Run zero-dependency test gates:
```bash
php tests/i18n_parity_test.php
php tests/token_factory_test.php
php tests/xmldb_schema_test.php
```
