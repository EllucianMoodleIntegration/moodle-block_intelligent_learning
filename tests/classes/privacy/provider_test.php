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
 * Privacy tests for core_course.
 *
 * @package    block_intelligent_learning
 * @category   test
 * @copyright  2019 Ellucian
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/blocks/intelligent_learning/locallib.php');
require_once($CFG->dirroot.'/blocks/intelligent_learning/classes/privacy/provider.php');

use \core_privacy\local\request\transform;

/**
 * Unit tests for block_intelligent_learning/classes/privacy/provider
 *
 * @copyright  Ellucian
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_intelligent_learning_privacy_testcase extends \core_privacy\tests\provider_testcase {

    /**
     * Test getting the appropriate context for the userid. This should only ever
     * return the user context for the user id supplied.
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);

        // Make sure contexts are not being returned for user1.
        $contextlist = \block_intelligent_learning\privacy\provider::get_contexts_for_userid($user1->id);
        $this->assertCount(0, $contextlist->get_contextids());

        // Create submitted grade data for user1.
        $this->create_usergrade($user1->id, $course->id);

        // Make sure the course context is being returned for user1.
        $contextlist = \block_intelligent_learning\privacy\provider::get_contexts_for_userid($user1->id);
        $expected = [$coursecontext->id];
        $actual = $contextlist->get_contextids();
        sort($expected);
        sort($actual);
        $this->assertCount(1, $actual);
		$this->assertEquals($expected, $actual);
    }

    /**
     * Test fetching users within a context.
     */
    public function test_get_users_in_context() {
        $this->resetAfterTest();
        $component = 'core_course';
        
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        // Create submitted grade data for user1 and user2.
        $this->create_usergrade($user1->id, $course->id);
        $this->create_usergrade($user2->id, $course->id);

        // Ensure only users that have a submitted course grade are returned.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, $component);
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $expected = [
            $user1->id,
            $user2->id
        ];
        $actual = $userlist->get_userids();
        sort($expected);
        sort($actual);
        $this->assertCount(2, $actual);
        $this->assertEquals($expected, $actual);

        // Ensure that users are not being returned in other contexts than the course context.
        $systemcontext = \context_system::instance();
        $userlist = new \core_privacy\local\request\userlist($systemcontext, $component);
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $this->assertCount(0, $actual);
    }

    /**
     * Test that user data is exported.
     */
    public function test_export_user_data() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();        
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        
        // Create submitted grade data for user.
        $grade = $this->create_usergrade($user->id, $course->id);
        
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user, 'core_course',
                [$coursecontext->id]);
        $writer = \core_privacy\local\request\writer::with_context($coursecontext);
        
        //test function
        \block_intelligent_learning\privacy\provider::export_user_data($approvedlist);

        $gradedata = $writer->get_data([get_string('privacy:intelligentlearninggradespath', 'block_intelligent_learning')]);

        $this->assertEquals($grade->mt1, $gradedata->midterm1);
        $this->assertEquals($grade->mt2, $gradedata->midterm2);
        $this->assertEquals($grade->mt3, $gradedata->midterm3);
        $this->assertEquals($grade->mt4, $gradedata->midterm4);
        $this->assertEquals($grade->mt5, $gradedata->midterm5);
        $this->assertEquals($grade->mt6, $gradedata->midterm6);
        $this->assertEquals($grade->finalgrade, $gradedata->finalgrade);
        $this->assertEquals($grade->incompletefinalgrade, $gradedata->incompletefinalgrade);
        $this->assertEquals(transform::datetime($grade->expiredate), $gradedata->expiredate);
        $this->assertEquals(transform::datetime($grade->lastaccess), $gradedata->lastaccess);
    }

    /**
     * Test deleting all user data for one context.
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();    
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);

        $systemcontext = \context_system::instance();
        
        // User1 and user2 have submitted grades.    
        $this->create_usergrade($user1->id, $course->id);
        $this->create_usergrade($user2->id, $course->id);

        // Ensure only users that have course grades are returned in the course context (user1 and user2).
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $this->assertCount(2, $actual);

        // Delete data for all users in a context different than the course context (system context).
       \block_intelligent_learning\privacy\provider::delete_data_for_all_users_in_context($systemcontext);

        // Ensure the data in the course context has not been deleted.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $this->assertCount(2, $actual);

        // Delete data for all users in the course context.
        \block_intelligent_learning\privacy\provider::delete_data_for_all_users_in_context($coursecontext);

        // Ensure the grades data has been removed in the course context.
        $records = $DB->get_records('block_intelligent_learning');
        $this->assertCount(0, $records);

        // Ensure that users are not returned after the deletion in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $this->assertCount(0, $actual);
    }

    /**
     * Test deleting data for only one user.
     */
    public function test_delete_data_for_user() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();    
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);

        $systemcontext = \context_system::instance();
        
        // User1 has submitted grades.    
        $this->create_usergrade($user1->id, $course->id);

        // Ensure user1 is returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [$user1->id];
        $this->assertCount(1, $actual);
        $this->assertEquals($expected, $actual);

        // User2 and user3 have submitted grades
        $this->create_usergrade($user2->id, $course->id);
        $this->create_usergrade($user3->id, $course->id);

        // Ensure user1, user2 and user3 are returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user1->id,
            $user2->id,
            $user3->id
        ];
        sort($expected);
        sort($actual);
        $this->assertCount(3, $actual);
        $this->assertEquals($expected, $actual);

        // Delete user1's data in the course context.
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user1, 'core_course',
                [$coursecontext->id]);
        \block_intelligent_learning\privacy\provider::delete_data_for_user($approvedlist);

        // Ensure user1's data is deleted and only user2 and user3 are returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user2->id,
            $user3->id
        ];
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);

        // Delete user2's data in a context different than the course context (system context).
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user2, 'core_course',
                [$systemcontext->id]);
        \block_intelligent_learning\privacy\provider::delete_data_for_user($approvedlist);

        // Ensure user2 and user3 are still returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user2->id,
            $user3->id
        ];
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);

        // Delete user2's data in the course context.
        $approvedlist = new \core_privacy\local\request\approved_contextlist($user2, 'core_course',
                [$coursecontext->id]);
        \block_intelligent_learning\privacy\provider::delete_data_for_user($approvedlist);

        // Ensure user2's is deleted and user3 is still returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user3->id
        ];
        $this->assertEquals($expected, $actual);
    }

    /**
     * Test deleting data within a context for an approved userlist.
     */
    public function test_delete_data_for_users() {
        $this->resetAfterTest();

        $component = 'core_course';
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
  
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = context_course::instance($course->id);
        
        // User1 and User2 have submitted grades.    
        $this->create_usergrade($user1->id, $course->id);
        $this->create_usergrade($user2->id, $course->id);

        // Ensure user1, user2 are returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user1->id,
            $user2->id
        ];
        sort($expected);
        sort($actual);
        $this->assertCount(2, $actual);
        $this->assertEquals($expected, $actual);

        $systemcontext = \context_system::instance();
        // User3 has submitted grades.
        $this->create_usergrade($user3->id, $course->id);

        // Ensure user1, user2 and user3 are now returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user1->id,
            $user2->id,
            $user3->id
        ];
        sort($expected);
        sort($actual);
        $this->assertCount(3, $actual);
        $this->assertEquals($expected, $actual);

        // Delete data for user1 and user3 in the course context.
        $approveduserids = [$user1->id, $user3->id];
        $approvedlist = new \core_privacy\local\request\approved_userlist($coursecontext, $component, $approveduserids);
        \block_intelligent_learning\privacy\provider::delete_data_for_users($approvedlist);

        // Ensure user1 and user3 are deleted and user2 is still returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [$user2->id];
        $this->assertCount(1, $actual);
        $this->assertEquals($expected, $actual);

        // Try to delete user2's data in a context different than course (system context).
        $approveduserids = [$user2->id];
        $approvedlist = new \core_privacy\local\request\approved_userlist($systemcontext, $component, $approveduserids);
        \block_intelligent_learning\privacy\provider::delete_data_for_users($approvedlist);

        // Ensure user2 is still returned in the course context.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, 'core_course');
        \block_intelligent_learning\privacy\provider::get_users_in_context($userlist);
        $actual = $userlist->get_userids();
        $expected = [
            $user2->id
        ];
        $this->assertCount(1, $actual);
        $this->assertEquals($expected, $actual);
    }
    

    /**
     * Create a new usergrade object
     */
    protected function create_usergrade($userid, $courseid) {
        global $DB;
        $mr_db_records = array();

        $usergrade = new stdClass();
        $usergrade->userid = $userid;
        $usergrade->course = $courseid;
        $usergrade->mt1 = 'A';
        $usergrade->mt2 = 'B';
        $usergrade->mt3 = 'C';
        $usergrade->mt4 = 'D';
        $usergrade->mt5 = 'A';
        $usergrade->mt6 = 'B';
        $usergrade->finalgrade = 'I';
        $usergrade->expiredate = time() + (30 * DAYSECS);
		$usergrade->lastaccess = time();
		$usergrade->neverattended = 0;
		$usergrade->incompletefinalgrade = 'D';
		
		$usergrade->id = $DB->insert_record('block_intelligent_learning', $usergrade);
		
        return $usergrade;
    }
}
