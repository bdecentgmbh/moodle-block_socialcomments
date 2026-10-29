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
 * Block socialcomments backup stepslib.
 *
 * @package    block_socialcomments
 * @subpackage backup-moodle2
 * @copyright  2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_socialcomments\local\comments_helper;

/**
 * Block socialcomments backup structure step class.
 *
 * @copyright  2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_socialcomments_block_structure_step extends backup_block_structure_step {
    /**
     *  Define the complete structure for backup.
     */
    protected function define_structure() {
        global $DB;

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('users');

        $comments = new backup_nested_element('socialcomments');
        $replies = new backup_nested_element('replies');
        $pins = new backup_nested_element('pins');
        $subscriptions = new backup_nested_element('subscriptions');

        // Define each element separated.
        $comment = new backup_nested_element('comment', ['id'], [
            'contextid',
            'component',
            'commentarea',
            'itemid',
            'content',
            'format',
            'userid',
            'groupid',
            'courseid',
            'timecreated',
            'timemodified',
        ]);

        $reply = new backup_nested_element('reply', ['id'], [
            'commentid',
            'content',
            'format',
            'userid',
            'timecreated',
            'timemodified',
        ]);

        $pin = new backup_nested_element('pin', ['id'], [
            'itemtype',
            'itemid',
            'userid',
            'timecreated',
        ]);

        $subscription = new backup_nested_element('subscription', ['id'], [
            'courseid',
            'contextid',
            'userid',
            'timelastsent',
            'timecreated',
            'timemodified',
        ]);

        // Build the tree.
        $comments->add_child($comment);
        $comment->add_child($replies);
        $comments->add_child($pins);
        $comments->add_child($subscriptions);

        $replies->add_child($reply);
        $pins->add_child($pin);
        $subscriptions->add_child($subscription);

        // Comments belong to the page (course or activity) they were posted on, not to the block. A block on the
        // course page backs up the comments of the whole course, a block on an activity page those of its activity.
        // The restore skips what another block of the same backup restored already.
        if ($userinfo) {
            $page = context::instance_by_id($this->task->get_contextid())->get_parent_context();
            if ($page->contextlevel == CONTEXT_COURSE) {
                $pages = 'SELECT id FROM {context} WHERE id = ? OR ' . $DB->sql_like('path', '?');
                $pageparams = [backup_helper::is_sqlparam($page->id), backup_helper::is_sqlparam($page->path . '/%')];
            } else {
                $pages = 'SELECT id FROM {context} WHERE id = ?';
                $pageparams = [backup_helper::is_sqlparam($page->id)];
            }

            $comment->set_source_sql("SELECT * FROM {block_socialcomments_cmmnts} WHERE contextid IN ($pages)", $pageparams);
            $reply->set_source_table('block_socialcomments_replies', ['commentid' => '../../id']);
            $subscription->set_source_sql(
                "SELECT * FROM {block_socialcomments_subscrs} WHERE contextid IN ($pages)",
                $pageparams
            );
            $pin->set_source_sql(
                "SELECT p.*
                   FROM {block_socialcomments_pins} p
                   JOIN {block_socialcomments_cmmnts} c ON c.id = p.itemid
                  WHERE p.itemtype = ? AND c.contextid IN ($pages)
                  UNION
                 SELECT p.*
                   FROM {block_socialcomments_pins} p
                  WHERE p.itemtype = ? AND p.itemid IN ($pages)",
                array_merge(
                    [backup_helper::is_sqlparam(comments_helper::PINNED_COMMENT)],
                    $pageparams,
                    [backup_helper::is_sqlparam(comments_helper::PINNED_PAGE)],
                    $pageparams
                )
            );
        }

        // Define id annotations.
        $comment->annotate_ids('user', 'userid');
        $comment->annotate_ids('group', 'groupid');
        $reply->annotate_ids('user', 'userid');
        $pin->annotate_ids('user', 'userid');
        $subscription->annotate_ids('user', 'userid');

        // No file annotations defined.

        // Return the root element (socialcomments), wrapped into standard activity structure.
        return $this->prepare_block_structure($comments);
    }
}
