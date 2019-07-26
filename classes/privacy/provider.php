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
 * Privacy Subsystem implementation for block_socialcomments.
 *
 * @package   block_socialcomments
 * @copyright 2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use \block_socialcomments\local\comments_helper;

/**
 * Privacy Subsystem for block block_socialcomments.
 *
 * @package   block_socialcomments
 * @copyright 2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    // This plugin does store course related comments entered by users.
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'block_socialcomments_cmmnts',
             [
                'contextid' => 'privacy:metadata:block_socialcomments_cmmnts:contextid',
                'content' => 'privacy:metadata:block_socialcomments_cmmnts:content',
                'userid' => 'privacy:metadata:block_socialcomments_cmmnts:userid',
                'groupid' => 'privacy:metadata:block_socialcomments_cmmnts:groupid',
                'courseid' => 'privacy:metadata:block_socialcomments_cmmnts:courseid',
                'timemodified' => 'privacy:metadata:block_socialcomments_cmmnts:timemodified',
             ],
            'privacy:metadata:block_socialcomments_cmmnts'
        );
        $collection->add_database_table(
            'block_socialcomments_subscrs',
             [
                'courseid' => 'privacy:metadata:block_socialcomments_subscrs:courseid',
                'contextid' => 'privacy:metadata:block_socialcomments_subscrs:contextid',
                'userid' => 'privacy:metadata:block_socialcomments_subscrs:userid',
                'timelastsent' => 'privacy:metadata:block_socialcomments_subscrs:timelastsent',
                'timemodified' => 'privacy:metadata:block_socialcomments_subscrs:timemodified',
             ],
            'privacy:metadata:block_socialcomments_subscrs'
        );
        $collection->add_database_table(
            'block_socialcomments_pins',
             [
                'itemtype' => 'privacy:metadata:block_socialcomments_pins:itemtype',
                'itemid' => 'privacy:metadata:block_socialcomments_pins:itemid',
                'userid' => 'privacy:metadata:block_socialcomments_pins:userid',
             ],
            'privacy:metadata:block_socialcomments_pins'
        );
        $collection->add_database_table(
            'block_socialcomments_replies',
             [
                'commentid' => 'privacy:metadata:block_socialcomments_replies:commentid',
                'content' => 'privacy:metadata:block_socialcomments_replies:content',
                'userid' => 'privacy:metadata:block_socialcomments_replies:userid',
                'timemodified' => 'privacy:metadata:block_socialcomments_replies:timemodified',
             ],
            'privacy:metadata:block_socialcomments_replies'
        );
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist  The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();
        $params = [
            'userid' => $userid
        ];

        // Get context by comments.
        $sql = "SELECT c.id
                FROM {context} c
                INNER JOIN {block_socialcomments_cmmnts} s ON s.contextid = c.id
                WHERE (s.userid = :userid)
                GROUP BY id";
        $contextlist->add_from_sql($sql, $params);

        // Get context by replies.
        $sql = "SELECT c.id
                FROM {context} c
                INNER JOIN mdl_block_socialcomments_cmmnts s ON s.contextid = c.id
                INNER JOIN mdl_block_socialcomments_replies r ON r.commentid = s.id
                WHERE (r.userid = :userid)
                GROUP BY id";
        $contextlist->add_from_sql($sql, $params);

        // Get context by subscriptions.
        $sql = "SELECT c.id
                FROM {context} c
                INNER JOIN {block_socialcomments_subscrs} s ON s.contextid = c.id
                WHERE (s.userid = :userid)
                GROUP BY id";
        $contextlist->add_from_sql($sql, $params);

        // Get context by pins.
        $sql = "SELECT c.id
                FROM {context} c
                INNER JOIN {block_socialcomments_cmmnts} s ON s.contextid = c.id
                INNER JOIN {block_socialcomments_pins} p ON p.itemid = s.id
                WHERE (p.userid = :userid_comment)
                AND (p.itemtype = :pin_type_comment)
                UNION
                SELECT c.id
                FROM {context} c
                INNER JOIN  {block_socialcomments_pins} p ON p.itemid = c.id
                WHERE (p.userid = :userid_page)
                AND (p.itemtype = :pin_type_page)";
        $params = [
          'userid_comment' => $userid,
          'pin_type_comment' => comments_helper::PINNED_COMMENT,
          'userid_page' => $userid,
          'pin_type_page' => comments_helper::PINNED_PAGE,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        $params = [
            'contextid' => $context->id,
        ];

        // Get userlist by comments.
        $sql = "SELECT userid
            FROM {block_socialcomments_cmmnts}
            WHERE contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Get userlist by replies.
        $sql = "SELECT r.userid
            FROM {block_socialcomments_replies} r
            INNER JOIN {block_socialcomments_cmmnts} sc ON sc.id = r.commentid
            WHERE contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Get userlist by subscriptions.
        $sql = "SELECT userid
            FROM {block_socialcomments_subscrs}
            WHERE contextid = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);

        // Get userlist by pins.
        $params = [
            'comment_contextid' => $context->id,
            'page_contextid' => $context->id,
            'pin_type_comment' => comments_helper::PINNED_COMMENT,
            'pin_type_page' => comments_helper::PINNED_PAGE,
        ];
        $sql = "SELECT p.*
            FROM {block_socialcomments_pins} p
            JOIN {block_socialcomments_cmmnts} c
            ON p.itemid = c.id
            AND p.itemtype = :pin_type_comment
            AND c.courseid = :comment_contextid
            UNION
            SELECT p.* FROM {block_socialcomments_pins} p
            WHERE (p.itemid = :page_contextid)
            AND (p.itemtype = :pin_type_page)";
        $userlist->add_from_sql('userid', $sql, $params);
        return $userlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts, using the supplied exporter instance.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        $context = $contextlist->current();
        $user = \core_user::get_user($contextlist->get_user()->id);

        static::export_comments($user->id, $context);
        static::export_replies($user->id, $context);
        static::export_pins($user->id, $context);
        static::export_subscriptions($user->id, $context);
    }

    /**
     * Export all socialcomments comments for the specified user.
     *
     * @param int $userid The user ID.
     * @param \context $context The user context.
     */
    protected static function export_comments(int $userid, \context $context) {
        global $DB;
        $sql = "SELECT sc.id, sc.content, sc.contextid, sc.userid,
                  sc.timecreated, sc.timemodified
                FROM {block_socialcomments_cmmnts} sc WHERE
                  sc.userid = :userid";
        $params = ['userid' => $userid];
        $records = $DB->get_records_sql($sql, $params);
        if (!empty($records)) {
            $comments = (object) array_map(function($record) use($context) {
                return [
                        'content' => format_string($record->content),
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified)
                ];
            }, $records);
            writer::with_context($context)->export_data([get_string('privacy:commentspath',
                    'block_socialcomments')], $comments);
        }
    }

    /**
     * Export all socialcomments replies for the specified user.
     *
     * @param int $userid The user ID.
     * @param \context $context The user context.
     */
    protected static function export_replies(int $userid, \context $context) {
        global $DB;
        $sql = "SELECT r.id, r.content, r.userid, r.timecreated, r.timemodified, sc.contextid
                FROM {block_socialcomments_replies} r
                INNER JOIN {block_socialcomments_cmmnts} sc ON sc.id = r.commentid
                WHERE (r.userid = :userid)";
        $params = ['userid' => $userid];
        $records = $DB->get_records_sql($sql, $params);
        if (!empty($records)) {
            $replies = (object) array_map(function($record) use($context) {
                return [
                        'content' => format_string($record->content),
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified)
                ];
            }, $records);
            writer::with_context($context)->export_data([get_string('privacy:repliespath',
                    'block_socialcomments')], $replies);
        }
    }

    /**
     * Export all socialcomments subscriptions for the specified user.
     *
     * @param int $userid The user ID.
     * @param \context $context The user context.
     */
    protected static function export_subscriptions(int $userid, \context $context) {
        global $DB;
        $sql = "SELECT s.courseid, s.timelastsent, s.timecreated, s.timemodified
                FROM {block_socialcomments_subscrs} s
                WHERE (s.userid = :userid)";
        $params = ['userid' => $userid];
        $records = $DB->get_records_sql($sql, $params);
        if (!empty($records)) {
            $subscriptions = (object) array_map(function($record) use($context) {
                global $DB;
                $course = $DB->get_record('course', ['id' => $record->courseid]);
                return [
                        'course' => format_string($course->fullname),
                        'timelastsent' => transform::datetime($record->timelastsent),
                        'timecreated' => transform::datetime($record->timecreated),
                        'timemodified' => transform::datetime($record->timemodified)
                ];
            }, $records);
            writer::with_context($context)->export_data([get_string('privacy:subscriptionspath',
                    'block_socialcomments')], $subscriptions);
        }
    }

    /**
     * Export all socialcomments pins for the specified user.
     *
     * @param int $userid The user ID.
     * @param \context $context The user context.
     */
    protected static function export_pins(int $userid, \context $context) {
        global $DB;
        $sql = "SELECT p.id, p.itemtype, p.itemid, p.timecreated, c.contextid
                FROM {block_socialcomments_pins} p
                JOIN {block_socialcomments_cmmnts} c
                ON p.itemid = c.id
                WHERE (p.userid = :userid_comment)
                AND (p.itemtype = :pin_type_comment)
                UNION
                SELECT p.id, p.itemtype, p.itemid, p.timecreated, p.itemid AS contextid
                FROM {block_socialcomments_pins} p
                WHERE (p.userid = :userid_page)
                AND (p.itemtype = :pin_type_page)";
        $params = [
            'userid_comment' => $userid,
            'pin_type_comment' => comments_helper::PINNED_COMMENT,
            'userid_page' => $userid,
            'pin_type_page' => comments_helper::PINNED_PAGE,
        ];
        $records = $DB->get_records_sql($sql, $params);
        if (!empty($records)) {
            $pins = (object) array_map(function($record) use($context) {
                return [
                        'type' => format_string($record->itemtype),
                        'timecreated' => transform::datetime($record->timecreated),
                ];
            }, $records);
            writer::with_context($context)->export_data([get_string('privacy:pinspath',
                    'block_socialcomments')], $pins);
        }
    }

    /**
     * Delete all data depending on comments in the specified context.
     * If a user ID is specified, delete only data depending on this users comments.
     *
     * @param \context $context Course context.
     * @param int $userid ID of the user.
     */
    protected static function delete_all_comment_dependant_data(\context $context, int $userid = null) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $conditions = ['contextid' => $context->id];
        if ($userid !== null) {
            $conditions['userid'] = $userid;
        }

        $comments = $DB->get_records('block_socialcomments_cmmnts', $conditions);
        foreach ($comments as $comment) {
            $DB->delete_records('block_socialcomments_replies', ['commentid' => $comment->id]);
            $DB->delete_records('block_socialcomments_pins', [
                'itemid' => $comment->id,
                'itemtype' => comments_helper::PINNED_COMMENT
            ]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        // Check thet this is a course context.
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        // Delete all replies and pins related to comments from the specified context.
        self::delete_all_comment_dependant_data($context);
        $DB->delete_records('block_socialcomments_cmmnts', ['contextid' => $context->id]);
        $DB->delete_records('block_socialcomments_subscrs', ['contextid' => $context->id]);
        $DB->delete_records('block_socialcomments_pins', [
            'itemid' => $context->id,
            'itemtype' => comments_helper::PINNED_PAGE,
        ]);
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        // An approved_contextlist is given and user data related to that user should either be completely deleted,
        // or overwritten if a structure needs to be maintained. This will be called when a user has requested the
        // right to be forgotten. All attempts should be made to delete this data where practical while still
        // allowing the plugin to be used by other users.
        global $DB;
        // Prepare SQL to gather all completed IDs.
        $context = $userlist->get_context();

        if ($context instanceof \context_course) {
            $userids = $userlist->get_userids();
            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

            foreach ($userids as $userid) {
                self::delete_all_comment_dependant_data($context, $userid);

            }
            $DB->delete_records_select(
                'block_socialcomments_cmmnts',
                "userid $insql",
                $inparams
            );
            $DB->delete_records_select(
                'block_socialcomments_replies',
                "userid $insql",
                $inparams
            );
            $DB->delete_records_select(
                'block_socialcomments_subscrs',
                "userid $insql",
                $inparams
            );
            $DB->delete_records_select(
                'block_socialcomments_pins',
                "userid $insql",
                $inparams
            );
        }
    }

    /**
     * Delete all user data for the specified user.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = \core_user::get_user($contextlist->get_user()->id);
        foreach ($contextlist as $context) {
            if ($context->contextlevel == CONTEXT_COURSE) {
                self::delete_all_comment_dependant_data($context, $user->id);

                $conditions = ['contextid' => $context->id];
                $comments = $DB->get_records('block_socialcomments_cmmnts', $conditions);
                foreach ($comments as $comment) {
                    $DB->delete_records('block_socialcomments_replies', [
                      'commentid' => $comment->id,
                      'userid' => $user->id,
                    ]);
                    $DB->delete_records('block_socialcomments_pins', [
                        'itemid' => $comment->id,
                        'itemtype' => comments_helper::PINNED_COMMENT,
                        'userid' => $user->id,
                    ]);
                }

                $DB->delete_records('block_socialcomments_cmmnts', ['contextid' => $context->id, 'userid' => $user->id]);
                $DB->delete_records('block_socialcomments_subscrs', ['contextid' => $context->id, 'userid' => $user->id]);
                $DB->delete_records('block_socialcomments_pins', [
                    'itemid' => $context->id,
                    'itemtype' => comments_helper::PINNED_PAGE,
                ]);
            }
        }
    }
}
