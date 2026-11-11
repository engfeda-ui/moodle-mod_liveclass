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
 * PHPUnit tests for mod_liveclass.
 *
 * @package    mod_liveclass
 * @category   test
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_liveclass;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/liveclass/lib.php');

/**
 * PHPUnit testcase for mod_liveclass.
 *
 * @package    mod_liveclass
 * @category   test
 * @copyright  2026 Energy & Water Academy (EWA)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class liveclass_test extends advanced_testcase {

    public function test_tables_exist() {
        global $DB;
        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->table_exists('liveclass'));
        $this->assertTrue($dbman->table_exists('liveclass_sessions'));
        $this->assertTrue($dbman->table_exists('liveclass_attendance'));
        $this->assertTrue($dbman->table_exists('liveclass_intervals'));
        $this->assertTrue($dbman->table_exists('liveclass_presence_logs'));
        $this->assertTrue($dbman->table_exists('liveclass_polls'));
        $this->assertTrue($dbman->table_exists('liveclass_poll_answers'));
    }

    public function test_liveclass_lifecycle() {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $data = (object)[
            'course'              => $course->id,
            'name'                => 'Test Virtual Class',
            'intro'               => 'Intro text',
            'introformat'         => FORMAT_HTML,
            'room_name'           => 'test_room_' . time(),
            'start_time'          => time(),
            'end_time'            => time() + 3600,
            'min_attendance_pct'  => 80,
            'auto_sync_attendance'=> 1,
            'warn_tab_switch'     => 1,
            'recording_mode'      => 0,
            'max_participants'    => 50
        ];

        // 1. Add instance.
        $id = liveclass_add_instance($data);
        $this->assertNotEmpty($id);
        $record = $DB->get_record('liveclass', ['id' => $id]);
        $this->assertEquals('Test Virtual Class', $record->name);

        // 2. Update instance.
        $data->instance = $id;
        $data->name = 'Updated Virtual Class';
        $res = liveclass_update_instance($data);
        $this->assertTrue($res);
        $record = $DB->get_record('liveclass', ['id' => $id]);
        $this->assertEquals('Updated Virtual Class', $record->name);

        // 3. Delete instance.
        $del = liveclass_delete_instance($id);
        $this->assertTrue($del);
        $this->assertFalse($DB->record_exists('liveclass', ['id' => $id]));
    }

    public function test_token_factory() {
        $jwt = \mod_liveclass\token_factory::create(
            'api_test_key',
            'api_test_secret_12345678901234567890',
            'test_room',
            'user_123',
            'Mahmoud Salem',
            true
        );
        $this->assertNotEmpty($jwt);
        $parts = explode('.', $jwt);
        $this->assertCount(3, $parts);
    }
}

