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

use \block_socialcomments\local\digest;
use context_course;


global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Digest test cases.
 */
class block_socialcomments_digest_test extends \advanced_testcase {

    /**
     * Set the config.
     *
     * @return void
     */
    public function setup(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();
        $this->generator = $generator;
        $this->course = $generator->create_course();
        $this->coursecontext = context_course::instance($this->course->id);

        $this->course2 = $generator->create_course();
        $this->coursecontext2 = context_course::instance($this->course2->id);

        $this->teacher = $generator->create_user();
        $generator->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $generator->enrol_user($this->teacher->id, $this->course2->id, 'editingteacher');

        $this->student1 = $generator->create_user();
        $generator->enrol_user($this->student1->id, $this->course->id, 'student');
        $generator->enrol_user($this->student1->id, $this->course2->id, 'student');

        $this->student2 = $generator->create_user();
        $generator->enrol_user($this->student2->id, $this->course->id, 'student');

        $record = array('courseid' => $this->course->id, 'name' => 'Group 1');
        $this->group1 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $this->student1->id, 'groupid' => $this->group1->id));

        $record = array('courseid' => $this->course->id, 'name' => 'Group 2');
        $this->group2 = $generator->create_group($record);
        $generator->create_group_member(array('userid' => $this->student2->id, 'groupid' => $this->group2->id));

    }

    /**
     * Test digest
     * @covers ::test_digest
     */
    public function test_digest() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);
        // Create some (yesterday) comments in different contexts.
        $this->setUser($this->teacher);
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('block_socialcomments');

        $cparams = array('contextid' => $this->coursecontext->id, 'timecreated' => time() - DAYSECS);
        $comment1 = $plugingenerator->create_comment($cparams);
        $comment2 = $plugingenerator->create_comment($cparams);

        // Check.
        $count = $DB->count_records('block_socialcomments_cmmnts');
        $this->assertEquals(2, $count);

        // Subscribe student1 to context of course1.
        $sparams = array(
            'contextid' => $this->coursecontext->id,
            'timecreated' => time() - 0.5 * DAYSECS
        );

        $sparams['userid'] = $this->student1->id;
        $plugingenerator->create_subscription($sparams);

        // Subscribe student2 to context of course1.
        $sparams['userid'] = $this->student2->id;
        $plugingenerator->create_subscription($sparams);

        // Subscribe student1 to context of course2.
        $sparams['contextid'] = $this->coursecontext2->id;
        $sparams['userid'] = $this->student1->id;
        $plugingenerator->create_subscription($sparams);

        $digest = digest::get_instance(true);
        $newcomments = $digest->get_subscribed_new_comments_and_replies($this->student1);

        // Should be emtpy, because comments were made before subscription.
        $this->assertEmpty($newcomments);

        // Now make a reply for commment 1 and a new comment3.
        $cparams['timecreated'] = time() - 0.2 * DAYSECS;
        $cparams['groupid'] = $this->group2->id;
        $comment3 = $plugingenerator->create_comment($cparams);

        // Post to other course. Just to check digest is working, when there are two courses.
        $cparams['timecreated'] = time() - 0.2 * DAYSECS;
        $cparams['groupid'] = 0;
        $cparams['contextid'] = $this->coursecontext2->id;
        $comment4 = $plugingenerator->create_comment($cparams);

        $rparams = array('commentid' => $comment1->id);
        $rparams['timecreated'] = time() - 0.2 * DAYSECS;
        $reply1 = $plugingenerator->create_reply($rparams);

        // Student1 should see comment1 because of reply.
        $digest = digest::get_instance(true);

        $newcomments = $digest->get_subscribed_new_comments_and_replies($this->student1);
        $this->assertCount(1, $newcomments[$this->course->id][$this->coursecontext->id]);

        // Student2 should see comment1 (groupid == 0) and comment 3 (group2).
        $digest = digest::get_instance(true);
        $newcomments = $digest->get_subscribed_new_comments_and_replies($this->student2);
        $this->assertCount(2, $newcomments[$this->course->id][$this->coursecontext->id]);

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
        delete_user($this->student1);
        $subscripts = $DB->get_records('block_socialcomments_subscrs', array('userid' => $this->student1->id));
        $this->assertEmpty($subscripts);
    }

    /**
     * Test visiblity and results on report page.
     * @covers \report_helper::get_instance
     */
    public function test_report() {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $DB->set_field('course', 'groupmode', NOGROUPS);
        // Setup course with block, groups and users.
        $generator = $this->getDataGenerator();

        // Create some (yesterday) comments in different contexts.
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('block_socialcomments');

        $params = array('contextid' => $this->coursecontext->id, 'userid' => $this->teacher->id,
            'timecreated' => time() - DAYSECS);
        $comment0 = $plugingenerator->create_comment($params);

        $params['groupid'] = $this->group1->id;
        $comment1 = $plugingenerator->create_comment($params);

        $params['groupid'] = $this->group2->id;
        $params['timecreated'] = time() - 2 * DAYSECS;
        $comment2 = $plugingenerator->create_comment($params);

        // Check which report items students may see.
        $this->setUser($this->student2);

        $reporthelper = \block_socialcomments\local\report_helper::get_instance(true);
        $filterdata = array(
            'courseid' => $this->course->id,
            'fromdate' => time() - 2 * DAYSECS,
            'todate' => time(),
            'content' => 'Comment',
            'author' => $this->teacher->lastname
        );

        $items = $reporthelper->get_course_comments((object) $filterdata);
        $this->assertCount(3, $items);

        $items = $reporthelper->get_course_new_comments_and_replies($this->course->id, time() - 1.5 * DAYSECS);
        $this->assertCount(2, reset($items));

        // Switch to SEPERATE GROUP MODE.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);

        // New instance to purge kept groupmode.
        $reporthelper = \block_socialcomments\local\report_helper::get_instance(true);
        $items = $reporthelper->get_course_comments((object) $filterdata);

        $this->assertCount(2, $items);

        $items = $reporthelper->get_course_new_comments_and_replies($this->course->id, time() - 1.5 * DAYSECS);

        // ...comment0 is visible, comment1 not visible because of group, comment2 not visible because of timestamp.
        $comments = reset($items);
        $this->assertNotEmpty($comments[$comment0->id]);

        // ...comment2 should appear, when reply with a newet timestamp is created.
        $params = array('commentid' => $comment2->id);
        $replytocomment2 = $plugingenerator->create_reply($params);

        $items = $reporthelper->get_course_new_comments_and_replies($this->course->id, time() - 1.5 * DAYSECS);

        // ...comment0 is visible, comment1 not visible because of group, comment2 not visible because of timestamp.
        $comments = reset($items);
        $this->assertNotEmpty($comments[$comment2->id]);

    }
}
