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

defined('MOODLE_INTERNAL') || die();

use \block_socialcomments\local\comments_helper;

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
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('users');

        $comments = new backup_nested_element('socialcomments');
        $replies = new backup_nested_element('replies');
        $pins = new backup_nested_element('pins');
        $subscriptions = new backup_nested_element('subscriptions');

        // Define each element separated.
        $comment = new backup_nested_element('comment', array('id'), array(
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
            'timemodified'
        ));

        $reply = new backup_nested_element('reply', array('id'), array(
            'commentid',
            'content',
            'format',
            'userid',
            'timecreated',
            'timemodified'
        ));

        $pin = new backup_nested_element('pin', array('id'), array(
            'itemtype',
            'itemid',
            'userid',
            'timecreated',
        ));

        $subscription = new backup_nested_element('subscription', array('id'), array(
            'courseid',
            'contextid',
            'userid',
            'timelastsent',
            'timecreated',
            'timemodified'
        ));

        // Build the tree.
        $comments->add_child($comment);
        $comment->add_child($replies);
        $comments->add_child($pins);
        $comments->add_child($subscriptions);

        $replies->add_child($reply);
        $pins->add_child($pin);
        $subscriptions->add_child($subscription);

        // Define sources.

        // All the elements only happen if we are including user info.
        if ($userinfo) {
            $comment->set_source_sql('SELECT *
                                       FROM {block_socialcomments_cmmnts}
                                       WHERE courseid = ?', array(backup::VAR_COURSEID));
            $reply->set_source_table('block_socialcomments_replies', array('commentid' => '../../id'));
            $subscription->set_source_sql('SELECT *
                                       FROM {block_socialcomments_subscrs}
                                       WHERE courseid = ?', array(backup::VAR_COURSEID));

            // Since the socialcomments block works with the course context in place of the block context,
            // we use the value from $contextid instead of backup::VAR_CONTEXTID.
            $courseid = $this->get_courseid();
            $contextid = context_course::instance($courseid)->id;
            $pin->set_source_sql('SELECT p.*
                                  FROM {block_socialcomments_pins} p
                                  JOIN {block_socialcomments_cmmnts} c
                                  ON p.itemid = c.id
                                  AND p.itemtype = '.comments_helper::PINNED_COMMENT.'
                                  AND c.courseid = ?
                                  UNION
                                  SELECT p.* FROM {block_socialcomments_pins} p
                                  WHERE (p.itemid = '.$contextid.')
                                  AND (p.itemtype = '.comments_helper::PINNED_PAGE.')',
                                  array(
                                      backup::VAR_COURSEID,
                                  ));
        }

        // Define id annotations.
        $comment->annotate_ids('user', 'userid');
        $comment->annotate_ids('group', 'groupid');

        // No file annotations defined.

        // Return the root element (socialcomments), wrapped into standard activity structure.
        return $this->prepare_block_structure($comments);
    }
}
