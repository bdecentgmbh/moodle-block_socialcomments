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

/**
 * Unit tests for blocks\socialcomments\local\comments_helper.php
 *
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments_report_testcase extends advanced_testcase {

    /**
     * Test visiblity and results on report page.
     */
    public function test_report() {
        global $DB;

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

        // Create some (yesterday) comments in different contexts.
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('block_socialcomments');

        $params = array('contextid' => $coursecontext->id, 'userid' => $teacher->id, 'timecreated' => time() - DAYSECS);
        $comment0 = $plugingenerator->create_comment($params);

        $params['groupid'] = $group1->id;
        $comment1 = $plugingenerator->create_comment($params);

        $params['groupid'] = $group2->id;
        $params['timecreated'] = time() - 2 * DAYSECS;
        $comment2 = $plugingenerator->create_comment($params);

        // Check which report items students may see.
        $this->setUser($student2);

        $reporthelper = \block_socialcomments\local\report_helper::get_instance(true);
        $filterdata = array(
            'courseid' => $course->id,
            'fromdate' => time() - 2 * DAYSECS,
            'todate' => time(),
            'content' => 'Comment',
            'author' => $teacher->lastname
        );

        $items = $reporthelper->get_course_comments((object) $filterdata);
        $this->assertCount(3, $items);

        $items = $reporthelper->get_course_new_comments_and_replies($course->id, time() - 1.5 * DAYSECS);
        $this->assertCount(2, reset($items));

        // Switch to SEPERATE GROUP MODE.
        $DB->set_field('course', 'groupmode', SEPARATEGROUPS);

        // New instance to purge kept groupmode.
        $reporthelper = \block_socialcomments\local\report_helper::get_instance(true);
        $items = $reporthelper->get_course_comments((object) $filterdata);

        $this->assertCount(2, $items);

        $items = $reporthelper->get_course_new_comments_and_replies($course->id, time() - 1.5 * DAYSECS);

        // ...comment0 is visible, comment1 not visible because of group, comment2 not visible because of timestamp.
        $comments = reset($items);
        $this->assertNotEmpty($comments[$comment0->id]);

        // ...comment2 should appear, when reply with a newet timestamp is created.
        $params = array('commentid' => $comment2->id);
        $replytocomment2 = $plugingenerator->create_reply($params);

        $items = $reporthelper->get_course_new_comments_and_replies($course->id, time() - 1.5 * DAYSECS);

        // ...comment0 is visible, comment1 not visible because of group, comment2 not visible because of timestamp.
        $comments = reset($items);
        $this->assertNotEmpty($comments[$comment2->id]);

    }

}
