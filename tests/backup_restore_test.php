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
use block_socialcomments\local\comments_helper;
use block_socialcomments\local\reply;

#[\PHPUnit\Framework\Attributes\CoversClass(\backup_socialcomments_block_structure_step::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\restore_socialcomments_block_structure_step::class)]
/**
 * Tests for the backup and restore of comments with the block.
 *
 * @package   block_socialcomments
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \backup_socialcomments_block_structure_step
 * @covers \restore_socialcomments_block_structure_step
 */
final class backup_restore_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Backs up a course with users and restores it into a new course.
     *
     * @param \stdClass $course
     * @param bool $logs whether to include the logs
     * @return int id of the new course
     */
    private function backup_and_restore(\stdClass $course, bool $logs = false): int {
        global $CFG, $USER;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->get_plan()->get_setting('logs')->set_value($logs);
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $dir = 'socialcomments_' . random_string(8);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $CFG->tempdir . '/backup/' . $dir);
        $newcourseid = \restore_dbops::create_new_course('Copy', 'copy', $course->category);
        $rc = new \restore_controller(
            $dir,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('users')->set_value(true);
        $rc->get_plan()->get_setting('logs')->set_value($logs);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
        return $newcourseid;
    }

    /**
     * Comments come back once, on their own page, with replies, pins and subscriptions.
     */
    public function test_backup_restore(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('block_socialcomments');

        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id, 'name' => 'Reading']);
        $coursecontext = \context_course::instance($course->id);
        $modcontext = \context_module::instance($page->cmid);

        // One block on the course page and one on the activity page: both back up the activity's comments.
        $plugin->create_instance(['parentcontextid' => $coursecontext->id, 'pagetypepattern' => 'course-view-*']);
        $plugin->create_instance(['parentcontextid' => $modcontext->id, 'pagetypepattern' => 'mod-page-*']);

        $plugin->create_comment(['contextid' => $coursecontext->id, 'userid' => $teacher->id, 'content' => 'On the course']);
        $comment = $plugin->create_comment(['contextid' => $modcontext->id, 'userid' => $student->id,
            'content' => 'On the reading']);
        $plugin->create_reply(['commentid' => $comment->id, 'userid' => $teacher->id]);
        $plugin->create_subscription(['contextid' => $modcontext->id, 'userid' => $student->id]);
        $pins = [comments_helper::PINNED_PAGE => $modcontext->id, comments_helper::PINNED_COMMENT => $comment->id];
        foreach ($pins as $type => $id) {
            $DB->insert_record('block_socialcomments_pins', (object) [
                'itemtype' => $type, 'itemid' => $id, 'userid' => $student->id, 'timecreated' => time(),
            ]);
        }

        $newcourseid = $this->backup_and_restore($course);

        $newcoursecontext = \context_course::instance($newcourseid);
        $newcm = get_coursemodule_from_instance('page', $DB->get_field('page', 'id', ['course' => $newcourseid]));
        $newmodcontext = \context_module::instance($newcm->id);
        $restored = $DB->get_records_menu('block_socialcomments_cmmnts', ['courseid' => $newcourseid], '', 'content, contextid');
        $this->assertEquals(['On the course' => $newcoursecontext->id, 'On the reading' => $newmodcontext->id], $restored);

        $newcomment = $DB->get_record('block_socialcomments_cmmnts', ['contextid' => $newmodcontext->id]);
        $this->assertEquals(1, $DB->count_records('block_socialcomments_replies', ['commentid' => $newcomment->id]));
        $this->assertTrue($DB->record_exists(
            'block_socialcomments_subscrs',
            ['contextid' => $newmodcontext->id, 'userid' => $student->id, 'courseid' => $newcourseid]
        ));
        $this->assertTrue($DB->record_exists(
            'block_socialcomments_pins',
            ['itemtype' => comments_helper::PINNED_PAGE, 'itemid' => $newmodcontext->id, 'userid' => $student->id]
        ));
        $this->assertTrue($DB->record_exists(
            'block_socialcomments_pins',
            ['itemtype' => comments_helper::PINNED_COMMENT, 'itemid' => $newcomment->id, 'userid' => $student->id]
        ));

        // Nothing points to a page that was not restored.
        $this->assertFalse($DB->record_exists('block_socialcomments_cmmnts', ['contextid' => 0]));
        $this->assertFalse($DB->record_exists('block_socialcomments_subscrs', ['contextid' => 0]));
        $this->assertFalse($DB->record_exists('block_socialcomments_pins', ['itemid' => 0]));
    }

    /**
     * Restored logs point to the restored comments and replies.
     */
    public function test_restore_logs(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);
        $this->setAdminUser();
        $generator = $this->getDataGenerator();
        $plugin = $generator->get_plugin_generator('block_socialcomments');

        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $plugin->create_instance(['parentcontextid' => \context_course::instance($course->id)->id, 'pagetypepattern' => '*']);
        $context = \context_module::instance($page->cmid);
        $comment = $plugin->create_comment(['contextid' => $context->id, 'userid' => $student->id]);
        $reply = $plugin->create_reply(['commentid' => $comment->id, 'userid' => $student->id]);
        // Logs of users who are not in the backup are not restored.
        $this->setUser($student);
        (new comment(['id' => $comment->id], true))->fire_event_created();
        // A reply gets its page from the comment when saved.
        (new reply((array) $reply + ['contextid' => $context->id]))->fire_event_created();
        $this->setAdminUser();

        $newcourseid = $this->backup_and_restore($course, true);

        $newcommentid = $DB->get_field('block_socialcomments_cmmnts', 'id', ['courseid' => $newcourseid]);
        $newreplyid = $DB->get_field('block_socialcomments_replies', 'id', ['commentid' => $newcommentid]);
        $logs = $DB->get_records_menu(
            'logstore_standard_log',
            ['courseid' => $newcourseid, 'component' => 'block_socialcomments'],
            '',
            'eventname, objectid'
        );
        $this->assertEquals([
            '\\block_socialcomments\\event\\comment_created' => $newcommentid,
            '\\block_socialcomments\\event\\reply_created' => $newreplyid,
        ], $logs);
    }
}
