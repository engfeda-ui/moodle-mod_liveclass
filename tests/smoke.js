"use strict";
/**
 * LiveClass (Moodle Activity Plugin) — Smoke Test Suite (Zero Dependencies)
 * Part of Antigravity AI Engineering Architecture (Pillar 4: Harness Engineering).
 */

const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
let failures = 0;

function check(name, cond, extra = "") {
  if (cond) {
    console.log(`  PASS ${name}`);
  } else {
    failures++;
    console.error(`  FAIL ${name} ${extra}`);
  }
}

console.log("=== Running LiveClass Smoke Suite ===");

const criticalFiles = [
  "lib.php",
  "version.php",
  "view.php",
  "mod_form.php",
  "settings.php",
  "control.php",
  "poll_api.php",
  "nudge.php",
  "log_event.php",
  "get_roster.php",
  "export.php",
  "webhook.php",
  "styles.css",
  "pix/icon.svg",
  "pix/monologo.svg",
  "amd/src/classroom.js",
  "amd/build/classroom.min.js",
  "classes/token_factory.php",
  "classes/attendance_engine.php",
  "classes/roster_manager.php",
  "db/access.php",
  "db/messages.php",
  "db/install.xml",
  "lang/en/liveclass.php",
  "lang/ar/liveclass.php",
  ".gitignore",
  "README.md"
];

for (const f of criticalFiles) {
  check(`File exists: ${f}`, fs.existsSync(path.join(ROOT, f)));
}

// Scoped CSS check
if (fs.existsSync(path.join(ROOT, "styles.css"))) {
  const cssContent = fs.readFileSync(path.join(ROOT, "styles.css"), "utf-8");
  check("CSS rules properly scoped to .liveclass-*", cssContent.includes(".liveclass-"));
}

// Check no secrets or keys accidentally tracked
const forbiddenPatterns = [
  "BEGIN RSA PRIVATE KEY",
  "BEGIN OPENSSH PRIVATE KEY",
  "ssh-ed25519"
];
for (const pat of forbiddenPatterns) {
  const viewSrc = fs.readFileSync(path.join(ROOT, "view.php"), "utf-8");
  check(`No plaintext secret pattern "${pat}" in view.php`, !viewSrc.includes(pat));
}

console.log(failures ? `\nSMOKE SUITE FAILED (${failures} errors)` : "\nSMOKE SUITE PASSED (All checks green)");
process.exit(failures ? 1 : 0);
