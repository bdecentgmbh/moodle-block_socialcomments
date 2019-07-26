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

use \block_socialcomments\local\digest;

global $CFG;
require_once($CFG->libdir . '/externallib.php');

class block_socialcomments_digest_testcase extends advanced_testcase {

    public function test_digest() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $coursecontext = context_course::instance($course->id);

        $course2 = $generator->create_course();
        $coursecontext2 = context_course::instance($course2->id);

        $teacher = $generator->create_user();
        $generator->enrol_user($teacher->id, $course->id, 'editingteacher');
        $generator->enrol_user($teacher->id, $course2->id, 'editingteacher');

        $student1 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student1->id, $course2->id, 'student');

        $student2 = $generator->create_user();
        $generator->enrol_user($student2->id, $course->id, 'student');

        $record = array('courseid' => $course->id, 'name' => 'Group 1');
        $group1 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $student1->id, 'groupid' => $group1->id));

        $record = array('courseid' => $course->id, 'name' => 'Group 2');
        $group2 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $student2->id, 'groupid' => $group2->id));

        // We are in no separate mode.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);

        // Create some (yesterday) comments in different contexts.
        $this->setUser($teacher);
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('block_socialcomments');

        $cparams = array('contextid' => $coursecontext->id, 'timecreated' => time() - DAYSECS);
        $comment1 = $plugingenerator->create_comment($cparams);
        $comment2 = $plugingenerator->create_comment($cparams);

        // Check.
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(2, $count);

        // Subscribe student1 to context of course1.
        $sparams = array(
            'contextid' => $coursecontext->id,
            'timecreated' => time() - 0.5 * DAYSECS
        );

        $sparams['userid'] = $student1->id;
        $plugingenerator->create_subscription($sparams);

        // Subscribe student2 to context of course1.
        $sparams['userid'] = $student2->id;
        $plugingenerator->create_subscription($sparams);

        // Subscribe student1 to context of course2.
        $sparams['contextid'] = $coursecontext2->id;
        $sparams['userid'] = $student1->id;
        $plugingenerator->create_subscription($sparams);

        $digest = digest::get_instance(true);
        $newcomments = $digest->get_subscribed_new_comments_and_replies($student1);

        // Should be emtpy, because comments were made before subscription.
        $this->assertEmpty($newcomments);

        // Now make a reply for commment 1 and a new comment3.
        $cparams['timecreated'] = time() - 0.2 * DAYSECS;
        $cparams['groupid'] = $group2->id;
        $comment3 = $plugingenerator->create_comment($cparams);

        // Post to other course. Just to check digest is working, when there are two courses.
        $cparams['timecreated'] = time() - 0.2 * DAYSECS;
        $cparams['groupid'] = 0;
        $cparams['contextid'] = $coursecontext2->id;
        $comment4 = $plugingenerator->create_comment($cparams);

        $rparams = array('commentid' => $comment1->id);
        $rparams['timecreated'] = time() - 0.2 * DAYSECS;
        $reply1 = $plugingenerator->create_reply($rparams);

        // Student1 should see comment1 because of reply.
        $digest = digest::get_instance(true);

        $newcomments = $digest->get_subscribed_new_comments_and_replies($student1);
        $this->assertCount(1, $newcomments[$course->id][$coursecontext->id]);

        // Student2 should see comment1 (groupid == 0) and comment 3 (group2).
        $digest = digest::get_instance(true);
        $newcomments = $digest->get_subscribed_new_comments_and_replies($student2);
        $this->assertCount(2, $newcomments[$course->id][$coursecontext->id]);

        set_config('digesttype', 1, 'block_socialcomments');

        // Test event for reply.
        $sink = $this->redirectMessages();
        digest::cron();
        $messages = $sink->get_messages();

        $this->assertCount(2, $messages);

        // Running cron again will send no more messages.
        $sink = $this->redirectMessages();
        digest::cron();
        $messages = $sink->get_messages();

        $this->assertCount(0, $messages);

        // Check delete events.
        delete_user($student1);
        $subscripts = $DB->get_records('block_socialcomments_subscrs', array('userid' => $student1->id));
        $this->assertEmpty($subscripts);

        delete_course($course);
        delete_course($course2);
        $comments = $DB->get_records('block_socialcomments_cmmnts');

        $this->assertEmpty($comments);

        $replies = $DB->get_records('block_socialcomments_replies');
        $this->assertEmpty($comments);

        $pins = $DB->get_records('block_socialcomments_pins');
        $this->assertEmpty($comments);
    }

}
