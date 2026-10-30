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

namespace block_socialcomments\privacy;

use block_socialcomments\local\comments_helper;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
/**
 * Tests for the privacy provider of block_socialcomments.
 *
 * @package   block_socialcomments
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \block_socialcomments\privacy\provider
 */
final class provider_test extends provider_testcase {
    /** @var \stdClass the user whose data is exported or deleted. */
    private $student;

    /** @var \stdClass another user with data in the same places. */
    private $other;

    /** @var \context_course */
    private $coursecontext;

    /** @var \context_module */
    private $modcontext;

    /** @var \context_module a page the student has no data in. */
    private $othercontext;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $this->coursecontext = \context_course::instance($course->id);
        $this->modcontext = \context_module::instance($generator->create_module('page', ['course' => $course->id])->cmid);
        $this->othercontext = \context_module::instance($generator->create_module('page', ['course' => $course->id])->cmid);
        $this->student = $generator->create_and_enrol($course, 'student');
        $this->other = $generator->create_and_enrol($course, 'student');

        // Both users post, reply, pin and subscribe on the activity page; the student also comments on the course.
        foreach ([$this->student, $this->other] as $user) {
            $comment = $this->comment($this->modcontext, $user);
            $this->reply($comment, $user === $this->student ? $this->other : $this->student);
            $this->pin(comments_helper::PINNED_PAGE, $this->modcontext->id, $user);
            $this->pin(comments_helper::PINNED_COMMENT, $comment->id, $user);
            $this->subscribe($this->modcontext, $user);
        }
        $this->comment($this->coursecontext, $this->student);
        $this->comment($this->othercontext, $this->other);
    }

    /**
     * Creates a comment.
     *
     * @param \context $context
     * @param \stdClass $user
     * @return \stdClass
     */
    private function comment(\context $context, \stdClass $user): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('block_socialcomments')->create_comment([
            'contextid' => $context->id,
            'userid' => $user->id,
            'content' => 'Comment of ' . $user->username,
        ]);
    }

    /**
     * Creates a reply.
     *
     * @param \stdClass $comment
     * @param \stdClass $user
     * @return \stdClass
     */
    private function reply(\stdClass $comment, \stdClass $user): \stdClass {
        return $this->getDataGenerator()->get_plugin_generator('block_socialcomments')->create_reply([
            'commentid' => $comment->id,
            'userid' => $user->id,
            'content' => 'Reply of ' . $user->username,
        ]);
    }

    /**
     * Pins a page or a comment.
     *
     * @param int $itemtype
     * @param int $itemid
     * @param \stdClass $user
     */
    private function pin(int $itemtype, int $itemid, \stdClass $user): void {
        global $DB;
        $DB->insert_record('block_socialcomments_pins', (object) [
            'itemtype' => $itemtype, 'itemid' => $itemid, 'userid' => $user->id, 'timecreated' => time(),
        ]);
    }

    /**
     * Subscribes a user to a page.
     *
     * @param \context $context
     * @param \stdClass $user
     */
    private function subscribe(\context $context, \stdClass $user): void {
        $this->getDataGenerator()->get_plugin_generator('block_socialcomments')->create_subscription([
            'contextid' => $context->id,
            'userid' => $user->id,
        ]);
    }

    /**
     * Counts the rows of a user in every table.
     *
     * @param \stdClass $user
     * @return array
     */
    private function count_rows(\stdClass $user): array {
        global $DB;
        return [
            'comments' => $DB->count_records('block_socialcomments_cmmnts', ['userid' => $user->id]),
            'replies' => $DB->count_records('block_socialcomments_replies', ['userid' => $user->id]),
            'pins' => $DB->count_records('block_socialcomments_pins', ['userid' => $user->id]),
            'subscriptions' => $DB->count_records('block_socialcomments_subscrs', ['userid' => $user->id]),
        ];
    }

    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('block_socialcomments'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertEquals([
            'block_socialcomments_cmmnts',
            'block_socialcomments_replies',
            'block_socialcomments_subscrs',
            'block_socialcomments_pins',
            'core_message',
            'deft',
        ], array_values($names));
    }

    public function test_get_contexts_for_userid(): void {
        $contextids = provider::get_contexts_for_userid($this->student->id)->get_contextids();
        $this->assertEqualsCanonicalizing([$this->coursecontext->id, $this->modcontext->id], $contextids);
    }

    public function test_get_users_in_context(): void {
        $userlist = new userlist($this->modcontext, 'block_socialcomments');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->student->id, $this->other->id], $userlist->get_userids());

        $userlist = new userlist($this->othercontext, 'block_socialcomments');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->other->id], $userlist->get_userids());
    }

    public function test_export_user_data(): void {
        $this->export_context_data_for_user($this->student->id, $this->modcontext, 'block_socialcomments');
        $data = writer::with_context($this->modcontext)->get_data([get_string('pluginname', 'block_socialcomments')]);

        $comments = $data->{get_string('privacy:commentspath', 'block_socialcomments')};
        $this->assertCount(1, $comments);
        $this->assertStringContainsString('Comment of ' . $this->student->username, $comments[0]['content']);
        $replies = $data->{get_string('privacy:repliespath', 'block_socialcomments')};
        $this->assertCount(1, $replies);
        $this->assertStringContainsString('Comment of ' . $this->other->username, $replies[0]['inreplyto']);
        $this->assertCount(2, $data->{get_string('privacy:pinspath', 'block_socialcomments')});
        $this->assertNotEmpty($data->{get_string('privacy:subscriptionspath', 'block_socialcomments')});

        // Nothing of the other user's page lands in the export.
        $this->assertFalse(writer::with_context($this->othercontext)->has_any_data());
    }

    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context($this->modcontext);

        $this->assertSame(['comments' => 1, 'replies' => 0, 'pins' => 0, 'subscriptions' => 0], $this->count_rows($this->student));
        $this->assertSame(['comments' => 1, 'replies' => 0, 'pins' => 0, 'subscriptions' => 0], $this->count_rows($this->other));
    }

    public function test_delete_data_for_user(): void {
        $contextlist = new approved_contextlist($this->student, 'block_socialcomments', [$this->modcontext->id]);
        provider::delete_data_for_user($contextlist);

        // Only the course comment of the student is left.
        $this->assertSame(['comments' => 1, 'replies' => 0, 'pins' => 0, 'subscriptions' => 0], $this->count_rows($this->student));
        // The other user keeps everything but the reply to the student's (deleted) comment.
        $this->assertSame(['comments' => 2, 'replies' => 0, 'pins' => 2, 'subscriptions' => 1], $this->count_rows($this->other));
    }

    public function test_delete_data_for_users(): void {
        $userlist = new approved_userlist($this->coursecontext, 'block_socialcomments', [$this->student->id]);
        provider::delete_data_for_users($userlist);

        $this->assertSame(['comments' => 1, 'replies' => 1, 'pins' => 2, 'subscriptions' => 1], $this->count_rows($this->student));
        $this->assertSame(['comments' => 2, 'replies' => 1, 'pins' => 2, 'subscriptions' => 1], $this->count_rows($this->other));
    }
}
