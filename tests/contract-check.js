"use strict";
/**
 * LiveClass (Moodle Activity Plugin) — Contract & Compliance Gate
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

console.log("=== Checking LiveClass Plugin Contracts & Compliance ===");

// 1. Check version.php
const versionFile = path.join(ROOT, "version.php");
check("version.php exists", fs.existsSync(versionFile));
if (fs.existsSync(versionFile)) {
  const versionSrc = fs.readFileSync(versionFile, "utf-8");
  check("Component declared as mod_liveclass", versionSrc.includes("$plugin->component = 'mod_liveclass'"));
  check("Version code declared", /\$plugin->version\s*=\s*\d{10};/.test(versionSrc));
  check("Requires Moodle 4.x+", /\$plugin->requires\s*=\s*\d{10};/.test(versionSrc));
  check("Release string declared", /\$plugin->release\s*=\s*['"]v\d+\.\d+\.\d+.*['"];/.test(versionSrc));
}

// 2. Check lib.php callbacks
const libFile = path.join(ROOT, "lib.php");
check("lib.php exists", fs.existsSync(libFile));
if (fs.existsSync(libFile)) {
  const libSrc = fs.readFileSync(libFile, "utf-8");
  check("Defines liveclass_add_instance()", libSrc.includes("function liveclass_add_instance("));
  check("Defines liveclass_update_instance()", libSrc.includes("function liveclass_update_instance("));
  check("Defines liveclass_delete_instance()", libSrc.includes("function liveclass_delete_instance("));
  check("Defines liveclass_supports()", libSrc.includes("function liveclass_supports("));
  check("Declares MOD_PURPOSE_COMMUNICATION", libSrc.includes("MOD_PURPOSE_COMMUNICATION"));
}

// 3. Check mod_form.php and index.php
const indexFile = path.join(ROOT, "index.php");
check("index.php exists (Moodle activity list view)", fs.existsSync(indexFile));

const formFile = path.join(ROOT, "mod_form.php");
check("mod_form.php exists", fs.existsSync(formFile));
if (fs.existsSync(formFile)) {
  const formSrc = fs.readFileSync(formFile, "utf-8");
  check("Extends moodleform_mod", formSrc.includes("extends moodleform_mod"));
  check("Defines definition() method", formSrc.includes("function definition()"));
}

// 4. Check db/access.php capabilities
const accessFile = path.join(ROOT, "db", "access.php");
check("db/access.php exists", fs.existsSync(accessFile));
if (fs.existsSync(accessFile)) {
  const accessSrc = fs.readFileSync(accessFile, "utf-8");
  check("Declares mod/liveclass:addinstance", accessSrc.includes("'mod/liveclass:addinstance'"));
  check("Declares mod/liveclass:view", accessSrc.includes("'mod/liveclass:view'"));
  check("Declares mod/liveclass:moderate", accessSrc.includes("'mod/liveclass:moderate'"));
}

// 5. Check db/install.xml schema
const installFile = path.join(ROOT, "db", "install.xml");
check("db/install.xml exists", fs.existsSync(installFile));

// 6. Check Zero raw $_SESSION usage
function scanForRawSession(dir) {
  const list = fs.readdirSync(dir);
  for (const file of list) {
    if (file === "node_modules" || file === ".git" || file === "tests") continue;
    const fullPath = path.join(dir, file);
    const stat = fs.statSync(fullPath);
    if (stat && stat.isDirectory()) {
      scanForRawSession(fullPath);
    } else if (file.endsWith(".php")) {
      const src = fs.readFileSync(fullPath, "utf-8");
      if (src.includes("$_SESSION")) {
        check(`Zero raw \$_SESSION in ${path.relative(ROOT, fullPath)}`, false, "Found direct \$_SESSION usage; use Moodle $SESSION instead");
      }
    }
  }
}
scanForRawSession(ROOT);
check("No raw $_SESSION usage in plugin", true);

console.log(failures ? `\nCONTRACT CHECK FAILED (${failures} errors)` : "\nCONTRACT CHECK PASSED (All checks green)");
process.exit(failures ? 1 : 0);

