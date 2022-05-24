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
 * Tests for the block.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_socialcomments;
defined('MOODLE_INTERNAL') || die();

use \block_socialcomments\local\comments_helper as comments_helper;
use context_course;

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Test for social comments.
 */
class block_socialcomments_comments_test extends \advanced_testcase {

    public function setup(): void {
        $this->resetAfterTest(true);
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->coursecontext = context_course::instance($this->course->id);
        $this->teacher = $generator->create_user();
        $this->student1 = $generator->create_user();
        $this->student2 = $generator->create_user();
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->student1->id, $this->course->id, 'student');
        $generator->enrol_user($this->student1->id, $this->course->id, 'student');
    }

    /**
     * Test, whether the plugin is properly installed.
     * @covers ::get_config
     */
    public function test_plugin_installed() {

        $config = get_config('block_socialcomments');
        $this->assertNotFalse($config);
    }

    /**
     * Test post (comments and replies) view, edit and delete actions
     * using the external functions.
     * @covers \external_api::call_external_function
     */
    public function test_post_actions() {
        global $DB, $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        // Setup course with block, groups and users.

        $record = array('courseid' => $this->course->id, 'name' => 'Group 1');
        $group1 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $this->student1->id, 'groupid' => $group1->id));

        $record = array('courseid' => $this->course->id, 'name' => 'Group 2');
        $group2 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $this->student2->id, 'groupid' => $group2->id));

        // We are in no groups mode.
        $DB->set_field('course', 'groupmode', NOGROUPS);

        // Teacher create a post, visible to all.
        $this->setUser($this->teacher);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        $result = external::save_comment($this->coursecontext->id, 'Comment0', 0, 0);
        $this->assertTrue(isset($result['id']));

        $teacherscomment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $this->teacher->id));
        $this->assertNotFalse($teacherscomment);

        // Edit post. Ensure, that only content and timemodified may change - try to change group to 1.
        $result = external::save_comment($this->coursecontext->id, 'Comment0-changed', $group1->id, $teacherscomment->id);

        // Check no additional comment is created.
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(1, $count);

        $commenupdated = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $this->teacher->id));
        $this->assertEquals(0, $commenupdated->groupid);
        $this->assertEquals('Comment0-changed', $commenupdated->content);

        // Comment to group 1 should NOT work when course is not in group mode. Assert that we have still one comment.
        $result = external::save_comment($this->coursecontext->id, 'Comment1', $group1->id, 0);
        $this->assertEquals(0, $result['id']);

        // Switch to separate group mode and assert that we can now post for group1.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);
        $result = external::save_comment($this->coursecontext->id, 'Comment1', $group1->id, 0);

        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(2, $count);

        $result = external::save_comment($this->coursecontext->id, 'Comment1', $group1->id, 0);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(3, $count);

        $commentshelper = new \block_socialcomments\local\comments_helper($this->coursecontext);
        $commentsdata = $commentshelper->get_comments(0);

        // Teacher sees all the posts.
        $this->assertEquals(3, count($commentsdata->posts));

        // Teacher can reply to comments.
        $result = external::save_reply($this->coursecontext->id, 'Reply1', $teacherscomment->id, 0);
        $teachersreply1 = $DB->get_record('block_socialcomments_replies', array('userid' => $this->teacher->id));
        $this->assertEquals($teachersreply1->commentid, $teacherscomment->id);

        // Check visible Post for student1.
        $this->setUser($this->student1);
        $USER->ignoresesskey = true;

        $commentshelper = new \block_socialcomments\local\comments_helper($this->coursecontext);
        $commentsdata = $commentshelper->get_comments(0);

        // Sees only the one comment for group 1 and the comment with groupid == 0.
        $this->assertEquals(3, count($commentsdata->posts));

        // Check, whether student1 can post to group1.
        $result = external::save_comment($this->coursecontext->id, 'Comment1-Student1', $group1->id, 0);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(4, $count);

        // Student can reply to teachers comments.
        $params = array('contextid' => $this->coursecontext->id, 'content' => 'Reply2',
            'commentid' => $teacherscomment->id, 'id' => 0);
        $result = external::save_reply($this->coursecontext->id, 'Reply2', $teacherscomment->id, 0);
        $studentsreply1 = $DB->get_record('block_socialcomments_replies', array('userid' => $this->student1->id));
        $this->assertEquals($studentsreply1->commentid, $teacherscomment->id);

        // Student1 should not be able to post to group2.
        $params = array('contextid' => $this->coursecontext->id, 'content' => 'Comment2-Student1',
            'groupid' => $group2->id, 'id' => 0);
        $result = external::save_comment($this->coursecontext->id, 'Comment2-Student1', $group2->id, 0);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(4, $count); // No additional post, should not be successfully posted.
        // Student 1 should not be able to delete or update teachers comments.
        $params = array(
            'contextid' => $this->coursecontext->id,
            'content' => 'Comment0-changed-Student1',
            'groupid' => $group1->id,
            'id' => $teacherscomment->id
        );
        $this->setUser($this->teacher);
        // Try update.
        $result = external::save_comment($this->coursecontext->id, 'Comment0-changed-Student1', $group1->id, $teacherscomment->id);
        $commentupdated = $DB->get_record('block_socialcomments_cmmnts', array('id' => $teacherscomment->id));
        $this->assertEquals('Comment0-changed-Student1', $commentupdated->content);
        $this->setUser($this->student1);
        // Try to delete own comment.
        $student1comment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $this->student1->id));
        $params = array('commentid' => $student1comment->id);
        $result = external::delete_comment($student1comment->id);
        $commentdeleted = $DB->get_record('block_socialcomments_cmmnts', array('id' => $student1comment->id));
        $this->assertFalse($commentdeleted);

        // Student 1 should not be able to delete or update teachers reply.
        $params = array(
            'contextid' => $this->coursecontext->id,
            'content' => 'Reply1-Student1',
            'commentid' => $teacherscomment->id,
            'id' => $teachersreply1->id
        );
        $this->setUser($this->teacher);
        // Try update.
        $result = external::save_reply($this->coursecontext->id, "Reply1-Student1", $teacherscomment->id, $teachersreply1->id);
        $replyupdated = $DB->get_record('block_socialcomments_replies', array('id' => $teachersreply1->id));
        $this->assertEquals('Reply1-Student1', $replyupdated->content);

        // Try delete.
        $params = array('id' => $teachersreply1->id);
        $result = external::delete_reply($teachersreply1->id);
        $replydeleted = $DB->get_record('block_socialcomments_replies', array('id' => $teachersreply1->id));
        $this->assertFalse($replydeleted);

        $this->setUser($this->student1);
        // Try to delete own reply.
        $student1reply = $DB->get_record('block_socialcomments_replies', array('userid' => $this->student1->id));
        $params = array('replyid' => $student1reply->id);
        $result = external::delete_reply($student1reply->id);
        $replydeleted = $DB->get_record('block_socialcomments_replies', array('id' => $student1reply->id));
        $this->assertFalse($replydeleted);

        // Teacher can delete own comment and all including replies.
        $this->setUser($this->teacher);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        $params = array('commentid' => $teacherscomment->id);
        $result = external::delete_comment($teacherscomment->id);
        $commentdeleted = $DB->get_record('block_socialcomments_cmmnts', array('id' => $teacherscomment->id));
        $this->assertFalse($commentdeleted);

        $replies = $DB->get_records('block_socialcomments_replies', array('commentid' => $teacherscomment->id));
        $this->assertCount(0, $replies);
    }

    /**
     *  Test several external API - functions including events.
     * @covers \external_api::call_external_function
     */
    public function test_external_functions() {
        global $USER, $DB;

        $this->resetAfterTest();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        // Teacher create a post, visible to all.
        $this->setUser($this->teacher);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        // Pin to course context...
        $params = array(
            'contextid' => $this->coursecontext->id,
            'checked' => true,
            'commentid' => 0
        );

        $result = external::set_pinned($this->coursecontext->id, true, 0);
        $this->assertEquals(0, $result['commentid']);

        // ...and check pin in database.
        $params = array(
            'itemid' => $this->coursecontext->id,
            'itemtype' => comments_helper::PINNED_PAGE,
            'userid' => $this->teacher->id
        );
        $pin = $DB->get_record('block_socialcomments_pins', $params);
        $this->assertNotFalse($pin);

        // Delete pin to course context...
        $params = array(
            'contextid' => $this->coursecontext->id,
            'checked' => false,
            'commentid' => 0
        );

        $result = external::set_pinned($this->coursecontext->id, false, 0);
        $this->assertEquals(0, $result['commentid']);

        // ...and check pin in database.
        $params = array(
            'itemid' => $this->coursecontext->id,
            'itemtype' => comments_helper::PINNED_PAGE,
            'userid' => $this->teacher->id
        );
        $pin = $DB->get_record('block_socialcomments_pins', $params);
        $this->assertFalse($pin);

        // Create a comment for test of pinning comments and event comment_created.
        $sink = $this->redirectEvents();

        $params = array('contextid' => $this->coursecontext->id, 'content' => 'Comment0', 'groupid' => 0, 'id' => 0);
        $result = external::save_comment($this->coursecontext->id, 'Comment0', 0, 0);
        $this->assertTrue(isset($result['id']));

        $teacherscomment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $this->teacher->id));
        $this->assertNotFalse($teacherscomment);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\block_socialcomments\event\comment_created', $event);
        $this->assertEquals($this->coursecontext, $event->get_context());
        $url = new \moodle_url('/course/view.php', array('id' => $this->course->id));
        $this->assertEquals($url, $event->get_url());

        // Pin comment...
        $params = array(
            'contextid' => $this->coursecontext->id,
            'checked' => true,
            'commentid' => $teacherscomment->id
        );
        $result = external::set_pinned($this->coursecontext->id, true, $teacherscomment->id);
        $this->assertEquals($teacherscomment->id, $result['commentid']);

        // ...and check pin in database.
        $params = array(
            'itemid' => $teacherscomment->id,
            'itemtype' => comments_helper::PINNED_COMMENT,
            'userid' => $this->teacher->id
        );
        $pin = $DB->get_record('block_socialcomments_pins', $params);
        $this->assertNotFalse($pin);

        // Subscribe to context...
        $params = array(
            'contextid' => $this->coursecontext->id,
            'checked' => true,
        );
        $result = external::set_subscribed($this->coursecontext->id, true);

        $this->assertEquals(true, $result['checked']);

        // ...and check subscription in database.
        $params = array(
            'contextid' => $this->coursecontext->id,
            'userid' => $this->teacher->id
        );
        $subscribed = $DB->get_record('block_socialcomments_subscrs', $params);
        $this->assertNotFalse($subscribed);

        // Delete subscription...
        $params = array(
            'contextid' => $this->coursecontext->id,
            'checked' => false,
        );
        $result = external::set_subscribed($this->coursecontext->id, false);

        $this->assertEquals(false, $result['checked']);

        // ... and check in database.
        $params = array(
            'contextid' => $this->coursecontext->id,
            'userid' => $this->teacher->id
        );
        $subscribed = $DB->get_record('block_socialcomments_subscrs', $params);
        $this->assertFalse($subscribed);

        // Test event for reply.
        $sink = $this->redirectEvents();

        $params = array('contextid' => $this->coursecontext->id, 'content' => 'Reply1',
            'commentid' => $teacherscomment->id, 'id' => 0);
        $result = external::save_reply($this->coursecontext->id, "Reply1", $teacherscomment->id, 0);

        $teachersreply1 = $DB->get_record('block_socialcomments_replies', array('userid' => $this->teacher->id));
        $this->assertEquals($teachersreply1->commentid, $teacherscomment->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\block_socialcomments\event\reply_created', $event);
        $this->assertEquals($this->coursecontext, $event->get_context());
        $url = new \moodle_url('/course/view.php', array('id' => $this->course->id));
        $this->assertEquals($url, $event->get_url());
    }

}
