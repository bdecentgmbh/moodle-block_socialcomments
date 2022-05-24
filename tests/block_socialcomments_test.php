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
 * Block socialcomments privacy provider tests.
 *
 * @package   block_socialcomments
 * @copyright 2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.6
 */
namespace block_socialcomments;

defined('MOODLE_INTERNAL') || die();

use \core_privacy\local\metadata\collection;
use \core_privacy\local\request\approved_contextlist;
use \core_privacy\local\request\approved_userlist;
use \core_privacy\tests\provider_testcase;
use \block_socialcomments\privacy\provider;
use context_course;
use context_user;
use context_system;

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Unit tests for blocks\socialcomments\classes\privacy\provider.php
 *
 * @copyright 2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments_test extends \advanced_testcase {

    /**
     * Basic setup for these tests.
     */
    public function setup(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->student = $generator->create_user();
        $this->teacher = $generator->create_user();
        $this->course = $generator->create_course();
        $this->course1 = $generator->create_course();
        $this->course2 = $generator->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $this->coursecontext1 = context_course::instance($this->course1->id);
        $this->coursecontext2 = context_course::instance($this->course2->id);
        $generator->enrol_user($this->student->id, $this->course->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->student->id, $this->course1->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course1->id, 'editingteacher');
        $generator->enrol_user($this->student->id, $this->course2->id, 'student');
        $generator->enrol_user($this->teacher->id, $this->course2->id, 'editingteacher');
        $this->studentcontext = context_user::instance($this->student->id);
        $this->teachercontext = context_user::instance($this->teacher->id);
        $this->systemcontext = context_system::instance();
    }

    /**
     * Test for provider::get_metadata()
     * @covers \provider::get_metadata
     */
    public function test_get_metadata() {
        $collection = new collection('block_socialcomments');
        $newcollection = provider::get_metadata($collection);
        $itemcollection = $newcollection->get_collection();
        $this->assertCount(4, $itemcollection);

        $table = reset($itemcollection);
        $this->assertEquals('block_socialcomments_cmmnts', $table->get_name());
        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('contextid', $privacyfields);
        $this->assertArrayHasKey('content', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('groupid', $privacyfields);
        $this->assertArrayHasKey('courseid', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
        $this->assertEquals('privacy:metadata:block_socialcomments_cmmnts', $table->get_summary());

        $table = next($itemcollection);
        $this->assertEquals('block_socialcomments_subscrs', $table->get_name());
        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('courseid', $privacyfields);
        $this->assertArrayHasKey('contextid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('timelastsent', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
        $this->assertEquals('privacy:metadata:block_socialcomments_subscrs', $table->get_summary());

        $table = next($itemcollection);
        $this->assertEquals('block_socialcomments_pins', $table->get_name());
        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('itemtype', $privacyfields);
        $this->assertArrayHasKey('itemid', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertEquals('privacy:metadata:block_socialcomments_pins', $table->get_summary());

        $table = next($itemcollection);
        $this->assertEquals('block_socialcomments_replies', $table->get_name());
        $privacyfields = $table->get_privacy_fields();
        $this->assertArrayHasKey('commentid', $privacyfields);
        $this->assertArrayHasKey('content', $privacyfields);
        $this->assertArrayHasKey('userid', $privacyfields);
        $this->assertArrayHasKey('timemodified', $privacyfields);
        $this->assertEquals('privacy:metadata:block_socialcomments_replies', $table->get_summary());
    }

    /**
     * Test getting the context for the user ID related to this plugin.
     * @covers \provider::get_contexts_for_userid
     */
    public function test_social_comments_get_contexts_for_userid() {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);

        $record = array('courseid' => $course->id, 'name' => 'Group');
        $group = $generator->create_group($record);

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $generator->create_group_member(array('userid' => $user->id, 'groupid' => $group->id));

        $this->save_comment($coursecontext, $user, 'Comment0');

        $comment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $user->id));
        $this->assertNotFalse($comment);

        $data = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(1, $data);

    }

    /**
     * Test that data is exported correctly for this plugin.
     * @covers \provider::export_user_data
     */
    public function test_export_user_data() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $component = 'block_socialcomments';

        // Generate some data.
        $commentid0 = $this->save_comment($this->coursecontext, $this->student, 'Comment0');
        $commentid1 = $this->save_comment($this->coursecontext, $this->student, 'Comment1');

        // Confirm data is present.
        $params = [
          'contextid' => $this->coursecontext->id,
          'userid' => $this->student->id,
        ];

        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);

        // Export data for student.
        $approvedlist = new approved_contextlist($this->student, $component, [$this->coursecontext->id]);
        provider::export_user_data($approvedlist);

        // Confirm student's data is exported.
        $writer = \core_privacy\local\request\writer::with_context($this->coursecontext);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test that only users within a course context are fetched.
     * @covers \provider::get_users_in_context
     */
    public function test_get_users_in_context() {
        global $DB, $USER;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        // Check nothing is found before socialcomments data iscreated.
        $userlist = new \core_privacy\local\request\userlist($this->coursecontext, 'block_socialcomments');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Generate some data for both users.

        // Create a comment for student.
        $commentid = $this->save_comment($this->coursecontext, $this->student, 'Comment0');
        // Crate a pin to course context for teacher.
        $this->set_pinned($this->coursecontext, $this->teacher, $commentid);

        $userlist = new \core_privacy\local\request\userlist($this->coursecontext, 'block_socialcomments');
        provider::get_users_in_context($userlist);
        $this->assertCount(1, $userlist);
        $userids = $userlist->get_userids();
        $this->assertTrue(in_array($this->student->id, $userids));
    }


    /**
     * Test that data for users in approved userlist is deleted.
     * @covers \provider::delete_data_for_users
     */
    public function test_delete_data_for_users() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $component = 'block_socialcomments';

        // Generate data for each user.
        $i = 0;
        $users = [$this->student, $this->teacher];
        $courses = [$this->course1, $this->course2];

        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            foreach ($users as $user) {
                // Create a comment.
                $commentid = $this->save_comment($coursecontext, $user, "Comment{i}");
                $i++;
            }
        }

        // Confirm data is present for both users.
        $params = [
            'userid' => $this->teacher->id,
        ];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);

        $params = [
            'userid' => $this->student->id,
        ];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);

        // Attempt system context deletion (should have no effect).
        $systemcontext = context_system::instance();
        $approvedlist = new approved_userlist($systemcontext, $component, [$this->student->id, $this->teacher->id]);
        provider::delete_data_for_users($approvedlist);

        $params = [];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(4, $result);

        // Attempt to delete data in another user's context (should have no effect).
        $approvedlist = new approved_userlist($this->studentcontext, $component, [$this->teacher->id]);
        provider::delete_data_for_users($approvedlist);

        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(4, $result);

        // Delete users' data in teacher's context.
        $approvedlist = new approved_userlist($this->teachercontext, $component, [$this->student->id, $this->teacher->id]);
        provider::delete_data_for_users($approvedlist);

        // Attempt to delete data in user's own context (should have no effect).
        $params = [];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(4, $result);

        // Delete user's data in course context.
        $approvedlist = new approved_userlist($coursecontext, $component, [$this->student->id]);
        provider::delete_data_for_users($approvedlist);

        $params = ['userid' => $this->student->id];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(0, $result);

        $params['userid'] = $this->teacher->id;
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);
    }

    /**
     * Test that user data is deleted using the context.
     * @covers \provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        // Generate data for each user.
        $i = 0;
        $users = [$this->student, $this->teacher];
        $courses = [$this->course1, $this->course2];

        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            foreach ($users as $user) {
                // Create a comment.
                $this->save_comment($coursecontext, $user, "Comment{i}");
                $i++;
            }
        }

        // Confirm data is present for all users.
        $params = [
            'userid' => $this->teacher->id,
        ];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);

        $params = [
            'userid' => $this->student->id,
        ];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);

        // Attempt system context deletion (should have no effect).
        $systemcontext = context_system::instance();
        provider::delete_data_for_all_users_in_context($systemcontext);

        $params = [];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(4, $result);

        // Delete all data in course1 context.
        provider::delete_data_for_all_users_in_context($this->coursecontext1);

        // Confirm only course1 data is deleted.
        $params = [ 'contextid' => $this->coursecontext1->id ];
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(0, $result);

        $params['contextid'] = $this->coursecontext2->id;
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals(2, $result);
    }

    /**
     * Test that user data is deleted for this user.
     * @covers \provider::delete_data_for_user
     */
    public function test_delete_data_for_user() {
        global $DB;
        $component = 'block_socialcomments';
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();

        $userlist = new \core_privacy\local\request\userlist($this->coursecontext1, 'block_socialcomments');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        $userlist = new \core_privacy\local\request\userlist($this->coursecontext2, 'block_socialcomments');
        provider::get_users_in_context($userlist);
        $this->assertCount(0, $userlist);

        // Create data in $course0.
        $commentid = $this->save_comment($this->coursecontext1, $this->student, 'Student comment0 in course0');
        $this->set_pinned($this->coursecontext1, $this->teacher, $commentid);
        $this->save_reply($this->coursecontext1, $this->teacher, $commentid, 'Teacher reply0 to student comment0 in course0.');
        $this->save_reply($this->coursecontext1, $this->student, $commentid, 'Student reply0 to student comment0 in course0.');
        $commentid = $this->save_comment($this->coursecontext1, $this->student, 'Student comment1 in course0');
        $this->set_pinned($this->coursecontext1, $this->teacher, $commentid);
        $this->set_pinned($this->coursecontext1, $this->student, $commentid);

        // Create data in $course1.
        $commentid = $this->save_comment($this->coursecontext2, $this->teacher, 'Teacher comment0 in course1');
        $this->save_reply($this->coursecontext2, $this->teacher, $commentid, 'Teacher reply0 to teacher comment0 in course1.');
        $this->set_subscribed($this->coursecontext2, $this->student);

        // Confirm data is present.
        $params = [];
        $this->check_data_for_user($params, $this->systemcontext, 3, true);

        // Confirm data is still present.
        $this->check_data_for_user($params, $this->studentcontext, 3, true);

        // Delete teacher data in the users own context (should have no effect).
        $this->check_data_for_user($params, $this->teachercontext, 3, true);

        // Delete teacher data in their own user context (should have no effect).
        $this->check_data_for_user($params, $this->teachercontext, 3, true);

        // Delete data for teacher in specified course context.
        $this->check_data_for_user($params, $this->coursecontext1, 3, true);
    }

    /**
     * Check the deleted data for user.
     * @param array $params
     * @param object $context
     * @param int $count
     * @param bool $delete
     */
    public function check_data_for_user($params, $context, $count, $delete = false) {
        global $DB;
        $component = 'block_socialcomments';
        $result = $DB->count_records('block_socialcomments_cmmnts', $params);
        $this->assertEquals($count, $result);
        $result = $DB->count_records('block_socialcomments_replies', $params);
        $this->assertEquals($count, $result);
        $result = $DB->count_records('block_socialcomments_pins', $params);
        $this->assertEquals($count, $result);
        $result = $DB->count_records('block_socialcomments_subscrs', $params);
        $this->assertEquals($count, $result);
        if ($delete) {
            $approvedlist = new approved_contextlist($this->teacher, $component, [$context->id]);
            provider::delete_data_for_user($approvedlist);
        }
    }

    /**
     * Call external API to create a reply.
     * @param object $context
     * @param object $user
     * @param int $commentid
     * @param string $content
     */
    protected function save_reply($context, $user, $commentid, $content = 'Reply') {
        global $USER;
        $this->setUser($user);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        $params = array(
            'contextid' => $context->id,
            'content' => $content,
            'commentid' => $commentid,
            'id' => 0
        );
        $result = external::save_reply($context->id, $content, $commentid, 0);
    }

    /**
     * Call external API to create a comment.
     * @param object $context
     * @param object $user
     * @param string $content
     */
    protected function save_comment($context, $user, $content = 'Comment') {
        global $USER;
        $this->setUser($user);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        $params = array('contextid' => $context->id, 'content' => $content, 'groupid' => 0, 'id' => 0);

        $result = external::save_comment($context->id, $content, 0, 0);
        $this->assertTrue(isset($result['id']));
        return $result['id'];
    }

    /**
     * Call external API to create a pin.
     * @param object $context
     * @param object $user
     * @param int $commentid
     */
    protected function set_pinned($context, $user, $commentid = 0) {
        global $USER;
        $this->setUser($user);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;
        $params = array(
            'contextid' => $context->id,
            'checked' => true,
            'commentid' => $commentid
        );
        $result = external::set_pinned($context->id, true, $commentid);
        $this->assertTrue(is_numeric($result['commentid']) && !empty($result['commentid']));
        $this->assertEquals($commentid, $result['commentid']);
    }

    /**
     * Call external API to subscribe user to course context.
     * @param object $context
     * @param object $user
     */
    protected function set_subscribed($context, $user) {
        global $USER;
        $this->setUser($user);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;
        // Subscribe to context...
        $params = array(
            'contextid' => $context->id,
            'checked' => true,
        );
        $result = external::set_subscribed($context->id, true);
        $this->assertTrue($result['checked']);
    }
}
