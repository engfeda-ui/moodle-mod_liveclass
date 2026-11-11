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
- **Remote Plugin Path**: `/home/ubuntu/moodle-project/mod/liveclass/`

## Mandatory 4-Step Release Workflow
Every edit must automatically run:
1. Bump version in `version.php` and update changelog in `README.md`.
2. Push changes to GitHub: `git push origin master`.
3. Package ZIP: `powershell -ExecutionPolicy Bypass -File "c:\Users\mahmo\OneDrive - Energy & Water Academy\Work\Repo\package_moodle_plugins.ps1"`.
4. Deploy to `150.230.241.37`:
   ```powershell
   cmd /c "tar -czf - -C `"$localPath`" . | ssh -i `"$sshKey`" -o StrictHostKeyChecking=no ubuntu@150.230.241.37 `"sudo tar -xzf - -C /home/ubuntu/moodle-project/mod/liveclass/`""
   ssh -i $sshKey -o StrictHostKeyChecking=no ubuntu@150.230.241.37 "sudo docker exec -u www-data moodle-app php /var/www/html/admin/cli/upgrade.php --non-interactive && sudo docker exec -u www-data moodle-app php /var/www/html/admin/cli/purge_caches.php"
   ```

## Verification Suite
Run zero-dependency test gates before every commit:
```bash
php tests/i18n_parity_test.php
php tests/token_factory_test.php
php tests/xmldb_schema_test.php
```

