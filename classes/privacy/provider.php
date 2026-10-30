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
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\privacy;

use block_socialcomments\local\comments_helper;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem for block_socialcomments.
 *
 * Comments, subscriptions and page pins belong to the context of the page (course or activity) they were made on,
 * replies and comment pins to the context of their comment.
 *
 * @package   block_socialcomments
 * @copyright 2019 Paul Steffen, EDU-Werkstatt GmbH
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_socialcomments_cmmnts', [
            'contextid' => 'privacy:metadata:block_socialcomments_cmmnts:contextid',
            'content' => 'privacy:metadata:block_socialcomments_cmmnts:content',
            'userid' => 'privacy:metadata:block_socialcomments_cmmnts:userid',
            'groupid' => 'privacy:metadata:block_socialcomments_cmmnts:groupid',
            'courseid' => 'privacy:metadata:block_socialcomments_cmmnts:courseid',
            'timecreated' => 'privacy:metadata:block_socialcomments_cmmnts:timecreated',
            'timemodified' => 'privacy:metadata:block_socialcomments_cmmnts:timemodified',
        ], 'privacy:metadata:block_socialcomments_cmmnts');
        $collection->add_database_table('block_socialcomments_replies', [
            'commentid' => 'privacy:metadata:block_socialcomments_replies:commentid',
            'content' => 'privacy:metadata:block_socialcomments_replies:content',
            'userid' => 'privacy:metadata:block_socialcomments_replies:userid',
            'timecreated' => 'privacy:metadata:block_socialcomments_replies:timecreated',
            'timemodified' => 'privacy:metadata:block_socialcomments_replies:timemodified',
        ], 'privacy:metadata:block_socialcomments_replies');
        $collection->add_database_table('block_socialcomments_subscrs', [
            'courseid' => 'privacy:metadata:block_socialcomments_subscrs:courseid',
            'contextid' => 'privacy:metadata:block_socialcomments_subscrs:contextid',
            'userid' => 'privacy:metadata:block_socialcomments_subscrs:userid',
            'timelastsent' => 'privacy:metadata:block_socialcomments_subscrs:timelastsent',
            'timemodified' => 'privacy:metadata:block_socialcomments_subscrs:timemodified',
        ], 'privacy:metadata:block_socialcomments_subscrs');
        $collection->add_database_table('block_socialcomments_pins', [
            'itemtype' => 'privacy:metadata:block_socialcomments_pins:itemtype',
            'itemid' => 'privacy:metadata:block_socialcomments_pins:itemid',
            'userid' => 'privacy:metadata:block_socialcomments_pins:userid',
            'timecreated' => 'privacy:metadata:block_socialcomments_pins:timecreated',
        ], 'privacy:metadata:block_socialcomments_pins');
        $collection->add_subsystem_link('core_message', [], 'privacy:metadata:core_message');
        $collection->add_external_location_link('deft', [
            'contextid' => 'privacy:metadata:deft:contextid',
        ], 'privacy:metadata:deft');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $params = [
            'userid' => $userid,
            'page' => comments_helper::PINNED_PAGE,
            'comment' => comments_helper::PINNED_COMMENT,
        ];
        $contextlist->add_from_sql("SELECT contextid FROM {block_socialcomments_cmmnts} WHERE userid = :userid", $params);
        $contextlist->add_from_sql("SELECT c.contextid
                                      FROM {block_socialcomments_replies} r
                                      JOIN {block_socialcomments_cmmnts} c ON c.id = r.commentid
                                     WHERE r.userid = :userid", $params);
        $contextlist->add_from_sql("SELECT contextid FROM {block_socialcomments_subscrs} WHERE userid = :userid", $params);
        $contextlist->add_from_sql("SELECT itemid
                                      FROM {block_socialcomments_pins}
                                     WHERE userid = :userid AND itemtype = :page", $params);
        $contextlist->add_from_sql("SELECT c.contextid
                                      FROM {block_socialcomments_pins} p
                                      JOIN {block_socialcomments_cmmnts} c ON c.id = p.itemid
                                     WHERE p.userid = :userid AND p.itemtype = :comment", $params);
        return $contextlist;
    }

    /**
     * Get the list of users within a specific context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $params = [
            'contextid' => $userlist->get_context()->id,
            'page' => comments_helper::PINNED_PAGE,
            'comment' => comments_helper::PINNED_COMMENT,
        ];
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {block_socialcomments_cmmnts} WHERE contextid = :contextid",
            $params
        );
        $userlist->add_from_sql('userid', "SELECT r.userid
                                             FROM {block_socialcomments_replies} r
                                             JOIN {block_socialcomments_cmmnts} c ON c.id = r.commentid
                                            WHERE c.contextid = :contextid", $params);
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {block_socialcomments_subscrs} WHERE contextid = :contextid",
            $params
        );
        $userlist->add_from_sql('userid', "SELECT userid
                                             FROM {block_socialcomments_pins}
                                            WHERE itemtype = :page AND itemid = :contextid", $params);
        $userlist->add_from_sql('userid', "SELECT p.userid
                                             FROM {block_socialcomments_pins} p
                                             JOIN {block_socialcomments_cmmnts} c ON c.id = p.itemid
                                            WHERE p.itemtype = :comment AND c.contextid = :contextid", $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $params = ['userid' => $userid, 'contextid' => $context->id];
            $data = [];

            $comments = $DB->get_records('block_socialcomments_cmmnts', $params, 'timecreated, id');
            if ($comments) {
                $data[get_string('privacy:commentspath', 'block_socialcomments')] = array_values(array_map(
                    fn($comment) => self::export_post($comment, $context),
                    $comments
                ));
            }

            $replies = $DB->get_records_sql("SELECT r.*, c.content AS commentcontent, c.format AS commentformat
                                               FROM {block_socialcomments_replies} r
                                               JOIN {block_socialcomments_cmmnts} c ON c.id = r.commentid
                                              WHERE r.userid = :userid AND c.contextid = :contextid
                                           ORDER BY r.timecreated, r.id", $params);
            if ($replies) {
                $data[get_string('privacy:repliespath', 'block_socialcomments')] = array_values(array_map(
                    fn($reply) => self::export_post($reply, $context) + [
                        'inreplyto' => format_text($reply->commentcontent, $reply->commentformat, ['context' => $context]),
                    ],
                    $replies
                ));
            }

            $pins = $DB->get_records_sql("SELECT p.id, p.itemtype, p.timecreated, c.content, c.format
                                            FROM {block_socialcomments_pins} p
                                       LEFT JOIN {block_socialcomments_cmmnts} c
                                                 ON p.itemtype = :comment AND c.id = p.itemid
                                           WHERE p.userid = :userid
                                                 AND ((p.itemtype = :page AND p.itemid = :pagecontextid)
                                                      OR c.contextid = :contextid)
                                        ORDER BY p.timecreated, p.id", $params + [
                'page' => comments_helper::PINNED_PAGE,
                'comment' => comments_helper::PINNED_COMMENT,
                'pagecontextid' => $context->id,
            ]);
            if ($pins) {
                $data[get_string('privacy:pinspath', 'block_socialcomments')] = array_values(array_map(fn($pin) => [
                    'pinned' => $pin->itemtype == comments_helper::PINNED_PAGE
                        ? get_string('privacy:pinnedpage', 'block_socialcomments')
                        : format_text($pin->content, $pin->format, ['context' => $context]),
                    'timecreated' => $pin->timecreated ? transform::datetime($pin->timecreated) : '-',
                ], $pins));
            }

            if ($subscription = $DB->get_record('block_socialcomments_subscrs', $params)) {
                $data[get_string('privacy:subscriptionspath', 'block_socialcomments')] = [
                    'timecreated' => transform::datetime($subscription->timecreated),
                    'timelastsent' => transform::datetime($subscription->timelastsent),
                ];
            }

            if ($data) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'block_socialcomments')],
                    (object) $data
                );
            }
        }
    }

    /**
     * Prepare a comment or a reply for the export.
     *
     * @param \stdClass $post
     * @param \context $context
     * @return array
     */
    protected static function export_post(\stdClass $post, \context $context): array {
        return [
            'content' => format_text($post->content, $post->format, ['context' => $context]),
            'timecreated' => transform::datetime($post->timecreated),
            'timemodified' => transform::datetime($post->timemodified),
        ];
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        self::delete_data($context);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            self::delete_data($context, [$contextlist->get_user()->id]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($userids = $userlist->get_userids()) {
            self::delete_data($userlist->get_context(), $userids);
        }
    }

    /**
     * Delete the data of some or all users in a context.
     *
     * Comments are deleted with their replies, so the replies of other users to them go too.
     *
     * @param \context $context
     * @param int[]|null $userids null for all users
     */
    protected static function delete_data(\context $context, ?array $userids = null) {
        global $DB;

        $params = ['contextid' => $context->id];
        $usersql = '';
        if ($userids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $usersql = " AND userid $insql";
            $params += $inparams;
        }

        // Comments with their replies and pins.
        $commentids = $DB->get_fieldset_select('block_socialcomments_cmmnts', 'id', "contextid = :contextid $usersql", $params);
        comments_helper::delete_comments($commentids);

        // Own replies and comment pins on the other comments of this context.
        $commentsql = "SELECT id FROM {block_socialcomments_cmmnts} WHERE contextid = :contextid";
        $DB->delete_records_select('block_socialcomments_replies', "commentid IN ($commentsql) $usersql", $params);
        $DB->delete_records_select(
            'block_socialcomments_pins',
            "itemtype = :comment AND itemid IN ($commentsql) $usersql",
            $params + ['comment' => comments_helper::PINNED_COMMENT]
        );

        // Page pins and subscriptions.
        $DB->delete_records_select(
            'block_socialcomments_pins',
            "itemtype = :page AND itemid = :contextid $usersql",
            $params + ['page' => comments_helper::PINNED_PAGE]
        );
        $DB->delete_records_select('block_socialcomments_subscrs', "contextid = :contextid $usersql", $params);
    }
}
