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

namespace block_socialcomments;

use block_socialcomments\local\comment;
use block_socialcomments\local\digest;
use block_socialcomments\local\report_helper;

#[\PHPUnit\Framework\Attributes\CoversClass(external::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(comment::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(digest::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(report_helper::class)]
/**
 * Access checks for comments outside their page: replies, pins, the reports and the digest.
 *
 * @package   block_socialcomments
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_socialcomments\external
 * @covers \block_socialcomments\local\comment
 * @covers \block_socialcomments\local\digest
 * @covers \block_socialcomments\local\report_helper
 */
final class access_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Creates a comment.
     *
     * @param \context $context
     * @param \stdClass $author
     * @param array $record further fields
     * @return \stdClass
     */
    private function create_comment(\context $context, \stdClass $author, array $record = []): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('block_socialcomments')->create_comment(
            ['contextid' => $context->id, 'userid' => $author->id] + $record
        );
    }

    /**
     * A reply belongs to the page of its comment, not to the context the client sends.
     */
    public function test_reply_needs_access_to_the_comment(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $mine = $generator->create_course();
        $other = $generator->create_course();
        $student = $generator->create_and_enrol($mine, 'student');
        $author = $generator->create_and_enrol($other, 'student');
        $foreign = $this->create_comment(\context_course::instance($other->id), $author);
        $own = $this->create_comment(\context_course::instance($mine->id), $author);

        $this->setUser($student);
        $result = external::save_reply(\context_course::instance($other->id)->id, 'Hello', $own->id, 0);
        $this->assertSame([], $result['warnings']);
        $this->assertEquals(1, $DB->count_records('block_socialcomments_replies', ['commentid' => $own->id]));

        $this->expectException(\moodle_exception::class);
        external::save_reply(\context_course::instance($mine->id)->id, 'Hello', $foreign->id, 0);
    }

    /**
     * In separate groups, students can neither reply to nor pin comments of other groups.
     */
    public function test_separate_groups(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $forum = $generator->create_module('forum', ['course' => $course->id, 'groupmode' => SEPARATEGROUPS]);
        $context = \context_module::instance($forum->cmid);
        $student1 = $generator->create_and_enrol($course, 'student');
        $student2 = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $group1 = $generator->create_group(['courseid' => $course->id]);
        $group2 = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student1->id]);
        $generator->create_group_member(['groupid' => $group2->id, 'userid' => $student2->id]);
        $comment = $this->create_comment($context, $student2, ['groupid' => $group2->id]);

        $this->setUser($teacher);
        external::save_reply($context->id, 'Teachers see all groups', $comment->id, 0);
        $this->assertTrue((new comment(['id' => $comment->id], true))->can_view());

        $this->setUser($student1);
        $this->assertFalse((new comment(['id' => $comment->id], true))->can_view());
        try {
            external::set_pinned($context->id, true, $comment->id);
            $this->fail('Pinned a comment of another group');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }
        $this->expectException(\moodle_exception::class);
        external::save_reply($context->id, 'Not my group', $comment->id, 0);
    }

    /**
     * Names of sections and activities with quotes neither break nor change the report query.
     */
    public function test_report_with_quotes_in_names(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 1]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $section = get_fast_modinfo($course)->get_section_info(1);
        course_update_section($course, $section, ['name' => "Week 1: it's on"]);
        $page = $generator->create_module('page', ['course' => $course->id, 'section' => 1,
            'name' => "Teacher's notes' OR 1=1 --"]);
        $this->create_comment(\context_module::instance($page->cmid), $teacher, ['content' => 'First']);
        $this->create_comment(\context_course::instance($course->id), $teacher, ['content' => 'Second']);

        $this->setUser($teacher);
        $PAGE->set_course($course);
        $comments = report_helper::get_instance(true)->get_course_comments((object) ['courseid' => $course->id]);

        $this->assertCount(2, $comments);
        $bycontent = array_column($comments, null, 'content');
        $this->assertSame("Teacher's notes' OR 1=1 --", $bycontent['First']->activity);
        $this->assertSame("Week 1: it's on", $bycontent['First']->topicname);
        $this->assertEquals(1, $bycontent['First']->commentscount);
    }

    /**
     * The newsfeed leaves out comments on activities the user cannot see.
     */
    public function test_newsfeed_without_hidden_activities(): void {
        global $PAGE;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $visible = $generator->create_module('page', ['course' => $course->id]);
        $hidden = $generator->create_module('page', ['course' => $course->id, 'visible' => 0]);
        $this->create_comment(\context_module::instance($visible->cmid), $teacher, ['content' => 'Visible']);
        $this->create_comment(\context_module::instance($hidden->cmid), $teacher, ['content' => 'Hidden']);

        $this->setUser($student);
        $PAGE->set_course($course);
        $comments = report_helper::get_instance(true)->get_course_new_comments_and_replies($course->id, 0);
        $this->assertSame([\context_module::instance($visible->cmid)->id], array_keys($comments));

        $this->setUser($teacher);
        $comments = report_helper::get_instance(true)->get_course_new_comments_and_replies($course->id, 0);
        $this->assertCount(2, $comments);
    }

    /**
     * The digest leaves out the user's own posts and pages the user cannot see any more.
     */
    public function test_digest_respects_access(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $context = \context_module::instance($page->cmid);
        $generator->get_plugin_generator('block_socialcomments')->create_subscription([
            'contextid' => $context->id, 'userid' => $student->id, 'timelastsent' => time() - HOURSECS,
        ]);
        $this->create_comment($context, $student, ['content' => 'Mine']);
        $news = $this->create_comment($context, $teacher, ['content' => 'News']);

        $this->setUser($student);
        $data = digest::get_instance(true)->get_subscribed_new_comments_and_replies($student);
        $this->assertEquals([$news->id], array_keys($data[$course->id][$context->id]));

        set_coursemodule_visible($page->cmid, 0);
        $this->assertSame([], digest::get_instance(true)->get_subscribed_new_comments_and_replies($student));

        set_coursemodule_visible($page->cmid, 1);
        $enrol = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        $enrol->unenrol_user($instance, $student->id);
        $this->assertSame([], digest::get_instance(true)->get_subscribed_new_comments_and_replies($student));
    }
}
