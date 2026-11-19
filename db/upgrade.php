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
 * Upgrade procedures for mod_liveclass.
 *
 * v1.2.2 (2026112001): sync `liveclass_polls` and `liveclass_poll_answers`
 * with the poll API (poll_api.php):
 * - `liveclass_polls` gains `liveclassid`, `duration_seconds`, `timecreated`,
 *   `timemodified`.
 * - `liveclass_poll_answers` gains `timecreated`.
 *
 * @package    mod_liveclass
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Load XMDB constants/classes (normally autoloaded during DB init; ensure during upgrade).
if (defined('MOODLE_INTERNAL')) {
    require_once($CFG->libdir . '/xmldb/xmldb_constants.php');
    require_once($CFG->libdir . '/xmldb/xmldb_table.php');
    require_once($CFG->libdir . '/xmldb/xmldb_field.php');
}

/**
 * Upgrade.
 *
 * @param int $oldversion Version being upgraded from.
 * @return bool Success.
 */
function xmldb_liveclass_upgrade($oldversion) {
    global $DB;

    if ($oldversion >= 2026112001) {
        return true;
    }

    $dbman = $DB->get_manager();

    $polls_table = new xmldb_table('liveclass_polls');

    // liveclass_polls: add liveclassid (FKey to liveclass instance).
    if (!$dbman->field_exists($polls_table, 'liveclassid')) {
        $field = new xmldb_field('liveclassid', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $dbman->add_field($polls_table, $field);
    }

    // liveclass_polls: add duration_seconds.
    if (!$dbman->field_exists($polls_table, 'duration_seconds')) {
        $field = new xmldb_field('duration_seconds', XMLDB_TYPE_INTEGER, 4, null, XMLDB_NOTNULL, null, 30);
        $dbman->add_field($polls_table, $field);
    }

    // liveclass_polls: add timecreated (poll_api uses this column).
    if (!$dbman->field_exists($polls_table, 'timecreated')) {
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $dbman->add_field($polls_table, $field);
    }

    // liveclass_polls: add timemodified (poll_api uses this column).
    if (!$dbman->field_exists($polls_table, 'timemodified')) {
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $dbman->add_field($polls_table, $field);
    }

    $answers_table = new xmldb_table('liveclass_poll_answers');

    // liveclass_poll_answers: add timecreated.
    if (!$dbman->field_exists($answers_table, 'timecreated')) {
        $field = new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, 10, null, XMLDB_NOTNULL, null, 0);
        $dbman->add_field($answers_table, $field);
    }

    return true;
}
