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

use block_socialcomments\local\comments_helper;

#[\PHPUnit\Framework\Attributes\CoversClass(comments_helper::class)]
/**
 * Tests for the clean-up when pages are deleted, and for the dates of posts.
 *
 * @package   block_socialcomments
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_socialcomments\local\comments_helper
 */
final class comments_helper_test extends \advanced_testcase {
    #[\Override]
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Adds a comment with a reply, a pin of each kind and a subscription to a page.
     *
     * @param \context $context
     * @param \stdClass $user
     */
    private function add_data(\context $context, \stdClass $user): void {
        global $DB;
        $generator = $this->getDataGenerator()->get_plugin_generator('block_socialcomments');
        $comment = $generator->create_comment(['contextid' => $context->id, 'userid' => $user->id]);
        $generator->create_reply(['commentid' => $comment->id, 'userid' => $user->id]);
        $generator->create_subscription(['contextid' => $context->id, 'userid' => $user->id]);
        foreach ([comments_helper::PINNED_PAGE => $context->id, comments_helper::PINNED_COMMENT => $comment->id] as $type => $id) {
            $DB->insert_record('block_socialcomments_pins', (object) [
                'itemtype' => $type, 'itemid' => $id, 'userid' => $user->id, 'timecreated' => time(),
            ]);
        }
    }

    /**
     * Counts all rows of the plugin.
     *
     * @return int[]
     */
    private function count_rows(): array {
        global $DB;
        return array_map(
            fn($table) => $DB->count_records('block_socialcomments_' . $table),
            ['cmmnts', 'replies', 'pins', 'subscrs']
        );
    }

    /**
     * Deleting an activity deletes its comments, replies, pins and subscriptions, and nothing else.
     */
    public function test_activity_deleted(): void {
        $this->resetAfterTest();
        set_config('coursebinenable', 0, 'tool_recyclebin');
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->add_data(\context_module::instance($page->cmid), $user);
        $this->add_data(\context_course::instance($course->id), $user);

        if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
            \core_courseformat\formatactions::cm($course)->delete($page->cmid);
        } else {
            course_delete_module($page->cmid);
        }
        $this->assertSame([1, 1, 2, 1], $this->count_rows());

        delete_course($course, false);
        $this->assertSame([0, 0, 0, 0], $this->count_rows());
    }

    /**
     * Times of today in minutes and hours, earlier ones in calendar days, weeks, months and years.
     *
     * @param string $time
     * @param string $expected
     * @dataProvider relative_time_provider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('relative_time_provider')]
    public function test_format_relative_time(string $time, string $expected): void {
        $this->resetAfterTest();
        $this->setTimezone('Europe/Berlin');
        $now = strtotime('2026-10-29 14:30:00 Europe/Berlin');
        $this->assertSame($expected, comments_helper::format_relative_time(strtotime($time . ' Europe/Berlin'), $now));
    }

    /**
     * Times before 2026-10-29 14:30 and how long ago they were.
     *
     * @return array
     */
    public static function relative_time_provider(): array {
        return [
            ['2026-10-29 14:29:30', 'Just now'],
            ['2026-10-29 14:29:00', '1 minute ago'],
            ['2026-10-29 14:25:00', '5 minutes ago'],
            ['2026-10-29 13:20:00', '1 hour ago'],
            ['2026-10-29 00:10:00', '14 hours ago'],
            ['2026-10-28 23:59:00', 'Yesterday'],
            ['2026-10-28 08:00:00', 'Yesterday'],
            ['2026-10-23 16:00:00', '6 days ago'],
            ['2026-10-22 16:00:00', '1 week ago'],
            ['2026-10-08 09:00:00', '3 weeks ago'],
            ['2026-09-20 09:00:00', '1 month ago'],
            ['2025-12-24 18:00:00', '10 months ago'],
            ['2024-05-01 12:00:00', '2 years ago'],
        ];
    }

    /**
     * The date as shown follows the setting; the exact date is always there for the tooltip.
     */
    public function test_format_time(): void {
        $this->resetAfterTest();
        $time = time() - 5 * MINSECS;
        $this->assertSame('5 minutes ago', comments_helper::format_time($time)['display']);
        set_config('dateformat', 'absolute', 'block_socialcomments');
        $formatted = comments_helper::format_time($time);
        $this->assertStringNotContainsString('ago', $formatted['display']);
        $this->assertSame(userdate($time, get_string('strftimedatetime', 'langconfig')), $formatted['exact']);
        $this->assertSame(date(DATE_ATOM, $time), $formatted['iso']);
    }
}
