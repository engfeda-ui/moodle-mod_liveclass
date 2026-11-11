"use strict";
/**
 * Master Verification Suite Runner — LiveClass Plugin
 * Part of Antigravity AI Engineering Architecture (Pillar 4: Harness Engineering).
 */

const { execSync } = require("child_process");
const path = require("path");

const ROOT = path.join(__dirname, "..");

const gates = [
  { name: "PHP Syntax Lint", cmd: "node tests/syntax-check.js" },
  { name: "i18n Parity (EN & AR)", cmd: "php tests/i18n_parity_test.php" },
  { name: "XMLDB Schema Integrity", cmd: "php tests/xmldb_schema_test.php" },
  { name: "LiveKit Token Factory & Crypto", cmd: "php tests/token_factory_test.php" },
  { name: "Roster Group Restrictions", cmd: "php tests/roster_test.php" },
  { name: "Security & IDOR Audit", cmd: "php tests/security_audit_test.php" },
  { name: "Moodle Contract & Compliance", cmd: "node tests/contract-check.js" },
  { name: "Plugin Smoke Suite", cmd: "node tests/smoke.js" }
];

console.log("==================================================");
console.log("🛡️ LiveClass Activity Plugin — Quality Gates Runner");
console.log("==================================================\n");

let failedGates = 0;

for (const gate of gates) {
  console.log(`▶ Running: ${gate.cmd}`);
  try {
    execSync(gate.cmd, {
      cwd: ROOT,
      stdio: "inherit"
    });
    console.log();
  } catch (err) {
    failedGates++;
    console.error(`\n❌ Gate FAILED: ${gate.name}\n`);
    break;
  }
}

if (failedGates > 0) {
  console.error("==================================================");
  console.error("❌ QUALITY GATES FAILED: Halting deployment!");
  console.error("==================================================");
  process.exit(1);
} else {
  console.log("==================================================");
  console.log("✅ ALL-GATES GREEN: All 8 verification checks passed!");
  console.log("==================================================");
  process.exit(0);
}
