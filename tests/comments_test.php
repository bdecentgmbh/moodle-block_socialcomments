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
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

use \block_socialcomments\local\comments_helper;

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class block_socialcomments_comments_testcase extends advanced_testcase {

    /**
     * Test, whether the plugin is properly installed.
     */
    public function test_plugin_installed() {

        $config = get_config('block_socialcomments');
        $this->assertNotFalse($config);
    }

    /**
     * Test post (comments and replies) view, edit and delete actions
     * using the external functions.
     */
    public function test_post_actions() {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        $student1 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');

        $student2 = $generator->create_user();
        $generator->enrol_user($student2->id, $course->id, 'student');

        $record = array('courseid' => $course->id, 'name' => 'Group 1');
        $group1 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $student1->id, 'groupid' => $group1->id));

        $record = array('courseid' => $course->id, 'name' => 'Group 2');
        $group2 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $student2->id, 'groupid' => $group2->id));

        // We are in no groups mode.
        $DB->set_field('course', 'groupmode', NOGROUPS);

        // Teacher create a post, visible to all.
        $this->setUser($teacher);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment0', 'groupid' => 0, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $this->assertFalse($result['error']);

        $teacherscomment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $teacher->id));
        $this->assertNotFalse($teacherscomment);

        // Edit post. Ensure, that only content and timemodified may change - try to change group to 1.
        $params = array(
            'contextid' => $coursecontext->id, 'content' => 'Comment0-changed',
            'groupid' => $group1->id, 'id' => $teacherscomment->id
        );
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);

        // Check no additional comment is created.
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(1, $count);

        $commenupdated = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $teacher->id));
        $this->assertEquals(0, $commenupdated->groupid);
        $this->assertEquals('Comment0-changed', $commenupdated->content);

        // Comment to group 1 should NOT work when course is not in group mode. Assert that we have still one comment.
        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment1', 'groupid' => $group1->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(1, $count);

        // Switch to separate group mode and assert that we can now post for group1.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);

        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment1', 'groupid' => $group1->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);

        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(2, $count);

        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment2', 'groupid' => $group2->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(3, $count);

        $commentshelper = new \block_socialcomments\local\comments_helper($coursecontext);
        $commentsdata = $commentshelper->get_comments(0);

        // Teacher sees all the posts.
        $this->assertEquals(3, count($commentsdata->posts));

        // Teacher can reply to comments.
        $params = array('contextid' => $coursecontext->id, 'content' => 'Reply1', 'commentid' => $teacherscomment->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_reply', $params);
        $teachersreply1 = $DB->get_record('block_socialcomments_replies', array('userid' => $teacher->id));
        $this->assertEquals($teachersreply1->commentid, $teacherscomment->id);

        // Check visible Post for student1.
        $this->setUser($student1);
        $USER->ignoresesskey = true;

        $commentshelper = new \block_socialcomments\local\comments_helper($coursecontext);
        $commentsdata = $commentshelper->get_comments(0);

        // Sees only the one comment for group 1 and the comment with groupid == 0.
        $this->assertEquals(2, count($commentsdata->posts));

        // Check, whether student1 can post to group1.
        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment1-Student1', 'groupid' => $group1->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(4, $count);

        // Student can reply to teachers comments.
        $params = array('contextid' => $coursecontext->id, 'content' => 'Reply2', 'commentid' => $teacherscomment->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_reply', $params);
        $studentsreply1 = $DB->get_record('block_socialcomments_replies', array('userid' => $student1->id));
        $this->assertEquals($studentsreply1->commentid, $teacherscomment->id);

        // Student1 should not be able to post to group2.
        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment2-Student1', 'groupid' => $group2->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(4, $count); // No additional post, should not be successfully posted.
        // Student 1 should not be able to delete or update teachers comments.
        $params = array(
            'contextid' => $coursecontext->id,
            'content' => 'Comment0-changed-Student1',
            'groupid' => $group1->id,
            'id' => $teacherscomment->id
        );

        // Try update.
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $commentupdated = $DB->get_record('block_socialcomments_cmmnts', array('id' => $teacherscomment->id));
        $this->assertEquals('Comment0-changed', $commentupdated->content);

        // Try delete.
        $params = array('id' => $teacherscomment->id);
        $result = external_api::call_external_function('block_socialcomments_delete_comment', $params);
        $commentdeleted = $DB->get_record('block_socialcomments_cmmnts', array('id' => $teacherscomment->id));
        $this->assertNotFalse($commentdeleted);

        // Try to delete own comment.
        $student1comment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $student1->id));
        $params = array('commentid' => $student1comment->id);
        $result = external_api::call_external_function('block_socialcomments_delete_comment', $params);
        $commentdeleted = $DB->get_record('block_socialcomments_cmmnts', array('id' => $student1comment->id));
        $this->assertFalse($commentdeleted);

        // Student 1 should not be able to delete or update teachers reply.
        $params = array(
            'contextid' => $coursecontext->id,
            'content' => 'Reply1-Student1',
            'commentid' => $teacherscomment->id,
            'id' => $teachersreply1->id
        );

        // Try update.
        $result = external_api::call_external_function('block_socialcomments_save_reply', $params);
        $replyupdated = $DB->get_record('block_socialcomments_replies', array('id' => $teachersreply1->id));
        $this->assertEquals('Reply1', $replyupdated->content);

        // Try delete.
        $params = array('id' => $teachersreply1->id);
        $result = external_api::call_external_function('block_socialcomments_delete_reply', $params);
        $replydeleted = $DB->get_record('block_socialcomments_replies', array('id' => $teachersreply1->id));
        $this->assertNotFalse($replydeleted);

        // Try to delete own reply.
        $student1reply = $DB->get_record('block_socialcomments_replies', array('userid' => $student1->id));
        $params = array('replyid' => $student1reply->id);
        $result = external_api::call_external_function('block_socialcomments_delete_reply', $params);

        $replydeleted = $DB->get_record('block_socialcomments_replies', array('id' => $student1reply->id));
        $this->assertFalse($replydeleted);

        // Teacher can delete own comment and all including replies.
        $this->setUser($teacher);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        $params = array('commentid' => $teacherscomment->id);
        $result = external_api::call_external_function('block_socialcomments_delete_comment', $params);
        $commentdeleted = $DB->get_record('block_socialcomments_cmmnts', array('id' => $teacherscomment->id));
        $this->assertFalse($commentdeleted);

        $replies = $DB->get_records('block_socialcomments_replies', array('commentid' => $teacherscomment->id));
        $this->assertCount(0, $replies);
    }

    /**
     *  Test several external API - functions including events.
     */
    public function test_external_functions() {
        global $USER, $DB;

        $this->resetAfterTest();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Teacher create a post, visible to all.
        $this->setUser($teacher);
        // Needed for calling the webservice without sesskey.
        $USER->ignoresesskey = true;

        // Pin to course context...
        $params = array(
            'contextid' => $coursecontext->id,
            'checked' => true,
            'commentid' => 0
        );

        $result = external_api::call_external_function('block_socialcomments_set_pinned', $params);
        $this->assertEquals(0, $result['data']['commentid']);

        // ...and check pin in database.
        $params = array(
            'itemid' => $coursecontext->id,
            'itemtype' => comments_helper::PINNED_PAGE,
            'userid' => $teacher->id
        );
        $pin = $DB->get_record('block_socialcomments_pins', $params);
        $this->assertNotFalse($pin);

        // Delete pin to course context...
        $params = array(
            'contextid' => $coursecontext->id,
            'checked' => false,
            'commentid' => 0
        );

        $result = external_api::call_external_function('block_socialcomments_set_pinned', $params);
        $this->assertEquals(0, $result['data']['commentid']);

        // ...and check pin in database.
        $params = array(
            'itemid' => $coursecontext->id,
            'itemtype' => comments_helper::PINNED_PAGE,
            'userid' => $teacher->id
        );
        $pin = $DB->get_record('block_socialcomments_pins', $params);
        $this->assertFalse($pin);

        // Create a comment for test of pinning comments and event comment_created.
        $sink = $this->redirectEvents();

        $params = array('contextid' => $coursecontext->id, 'content' => 'Comment0', 'groupid' => 0, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_comment', $params);
        $this->assertFalse($result['error']);

        $teacherscomment = $DB->get_record('block_socialcomments_cmmnts', array('userid' => $teacher->id));
        $this->assertNotFalse($teacherscomment);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\block_socialcomments\event\comment_created', $event);
        $this->assertEquals($coursecontext, $event->get_context());
        $url = new moodle_url('/course/view.php', array('id' => $course->id));
        $this->assertEquals($url, $event->get_url());

        // Pin comment...
        $params = array(
            'contextid' => $coursecontext->id,
            'checked' => true,
            'commentid' => $teacherscomment->id
        );

        $result = external_api::call_external_function('block_socialcomments_set_pinned', $params);
        $this->assertEquals($teacherscomment->id, $result['data']['commentid']);

        // ...and check pin in database.
        $params = array(
            'itemid' => $teacherscomment->id,
            'itemtype' => comments_helper::PINNED_COMMENT,
            'userid' => $teacher->id
        );
        $pin = $DB->get_record('block_socialcomments_pins', $params);
        $this->assertNotFalse($pin);

        // Subscribe to context...
        $params = array(
            'contextid' => $coursecontext->id,
            'checked' => true,
        );
        $result = external_api::call_external_function('block_socialcomments_set_subscribed', $params);

        $this->assertEquals(true, $result['data']['checked']);

        // ...and check subscription in database.
        $params = array(
            'contextid' => $coursecontext->id,
            'userid' => $teacher->id
        );
        $subscribed = $DB->get_record('block_socialcomments_subscrs', $params);
        $this->assertNotFalse($subscribed);

        // Delete subscription...
        $params = array(
            'contextid' => $coursecontext->id,
            'checked' => false,
        );
        $result = external_api::call_external_function('block_socialcomments_set_subscribed', $params);

        $this->assertEquals(false, $result['data']['checked']);

        // ... and check in database.
        $params = array(
            'contextid' => $coursecontext->id,
            'userid' => $teacher->id
        );
        $subscribed = $DB->get_record('block_socialcomments_subscrs', $params);
        $this->assertFalse($subscribed);

        // Test event for reply.
        $sink = $this->redirectEvents();

        $params = array('contextid' => $coursecontext->id, 'content' => 'Reply1', 'commentid' => $teacherscomment->id, 'id' => 0);
        $result = external_api::call_external_function('block_socialcomments_save_reply', $params);

        $teachersreply1 = $DB->get_record('block_socialcomments_replies', array('userid' => $teacher->id));
        $this->assertEquals($teachersreply1->commentid, $teacherscomment->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\block_socialcomments\event\reply_created', $event);
        $this->assertEquals($coursecontext, $event->get_context());
        $url = new moodle_url('/course/view.php', array('id' => $course->id));
        $this->assertEquals($url, $event->get_url());
    }

}
