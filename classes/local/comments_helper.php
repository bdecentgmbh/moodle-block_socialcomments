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
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\local;

defined('MOODLE_INTERNAL') || die;

class comments_helper {

    const PINNED_PAGE = 10;
    const PINNED_COMMENT = 20;
    const LASTPAGE = -1;

    const DIGEST_SITE = 1;
    const DIGEST_COURSE = 2;

    protected $context = null;
    protected $course = null;
    protected $cm = null;
    protected $user = null;
    protected $groupmode = null;
    protected $groupsql;

    /**
     * Create an object of the helper for a context.
     *
     * @param context $pagecontext course or modul context.
     */
    public function __construct($pagecontext) {
        global $USER;

        list($context, $course, $cm) = get_context_info_array($pagecontext->id);

        $this->context = $context;
        $this->course = $course;
        $this->cm = $cm;
        $this->user = $USER;
    }

    /**
     * Get all the data needed for the form to post comments.
     *
     * @return object formdata
     */
    protected function get_formdata() {
        global $DB;

        $params = array(
            'itemtype' => self::PINNED_PAGE,
            'userid' => $this->user->id,
            'itemid' => $this->context->id
        );

        $formdata = new \stdClass();
        $formdata->pagepinned = $DB->record_exists('block_socialcomments_pins', $params);

        return $formdata;
    }

    /**
     * Get the cont of comments for a context.
     *
     * @return int count of comments.
     */
    public function get_commentscount() {
        global $DB;

        list($andingroups, $params) = $this->get_groups_restriction_sql();

        $params['contextid'] = $this->context->id;

        $sql = "SELECT COUNT(id)
                 FROM {block_socialcomments_cmmnts}
                 WHERE contextid = :contextid {$andingroups}";

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Create a SQL snippet to restric visibility of comments to groups.
     *
     * @param context $context
     * @return array SQL-Snippet and params.
     */
    protected function get_groups_restriction_sql() {

        if (isset($this->groupsql)) {
            return $this->groupsql;
        }

        $this->groupsql = comment::get_group_restriction_sql($this->context);
        return $this->groupsql;
    }

    /**
     * Get all comments for this context.
     *
     * @param int page page starting from 0 for normal pages, -1 for the last page.
     */
    public function get_comments($page) {
        global $DB;

        $perpage = get_config('block_socialcomments', 'commentsperpage');
        $perpage = (!$perpage) ? 10 : $perpage;

        $commentsdata = new \stdClass();

        $countparams = array(
            'contextid' => $this->context->id,
            'userid' => $this->user->id
        );

        $commentsdata->subscribed = $DB->count_records('block_socialcomments_subscrs', $countparams);
        $commentsdata->count = $this->get_commentscount();
        $commentsdata->minpage = 0;
        $commentsdata->maxpage = 0;
        $commentsdata->currentpage = 0;

        if ($commentsdata->count == 0) {
            $commentsdata->comments = array();
            return $commentsdata;
        }

        // Params.
        list($andingroups, $params) = $this->get_groups_restriction_sql();
        $params['contextid'] = $this->context->id;
        $params['itemtype'] = self::PINNED_COMMENT;
        $params['userid'] = $this->user->id;

        $userfields = get_all_user_name_fields(true, 'u');
        $userpicturefields = \user_picture::fields('u');

        $sql = "SELECT bc.id as postid, bc.content, bc.timecreated, p.itemtype as pinned,
                bc.userid, $userfields, $userpicturefields
                FROM {block_socialcomments_cmmnts} bc
                JOIN {user} u ON bc.userid = u.id
                LEFT JOIN {block_socialcomments_pins} p ON p.itemid = bc.id AND p.itemtype = :itemtype AND p.userid = :userid
                WHERE bc.contextid = :contextid {$andingroups} ORDER by bc.timecreated ASC ";

        // Check page range.
        $commentsdata->maxpage = ceil($commentsdata->count / $perpage);

        // Get the page number for the lastpage.
        if ($page == self::LASTPAGE) {
            $page = $commentsdata->maxpage - 1;
        }

        // Just in case there is a call outside the range.
        $page = max($commentsdata->minpage, $page);
        $page = min($commentsdata->maxpage, $page);
        $commentsdata->currentpage = $page;

        $commentsdata->posts = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        // We only add replies for the visible comments.
        $commentsdata->posts = $this->add_replies($commentsdata->posts);

        return $commentsdata;
    }

    /**
     * Add replies to given comments.
     *
     * @param array $posts
     * @return array post with added replies.
     */
    protected function add_replies($posts) {
        global $DB;

        if (!$posts) {
            return array();
        }

        foreach ($posts as $post) {
            $post->countreplies = 0;
            $post->replies = array();
        }

        $postids = array_keys($posts);
        list($instr, $params) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED);

        $sql = "SELECT commentid, count(*)
                FROM {block_socialcomments_replies}
                WHERE commentid {$instr}
                GROUP BY commentid";

        $replycounts = $DB->get_records_sql_menu($sql, $params);

        if (!$replycounts) {
            return $posts;
        }

        $limitreplies = get_config('block_socialcomments', 'limitreplies');

        $userfields = get_all_user_name_fields(true, 'u');
        $userpicturefields = \user_picture::fields('u');

        $sql = "SELECT r.id as postid, r.content, r.timecreated, r.userid,
                $userfields, $userpicturefields
                FROM {block_socialcomments_replies} r
                JOIN {user} u ON r.userid = u.id
                WHERE r.commentid = ?
                ORDER by r.timecreated ASC";

        foreach ($posts as $post) {

            if (!empty($replycounts[$post->postid])) {

                $post->countreplies = $replycounts[$post->postid];
                $post->replies = $DB->get_records_sql($sql, array($post->postid), 0, $limitreplies);
            }
        }

        return $posts;
    }

    /**
     * Get all data to render the block content.
     *
     * @param int $page number of the page to load.
     * @return \stdClass
     */
    public function get_content_data($page = 0) {

        $data = new \stdClass();

        // Get the data for the top of the block (i. e. input form).
        $data->formdata = $this->get_formdata();
        $data->comments = $this->get_comments($page);

        return $data;
    }

    /**
     * Check, whether pinning items is supported
     *
     * @return boolean true, when pinning is supported
     */
    public function can_pin() {
        return has_capability('block/socialcomments:pinitems', $this->context);
    }

    /**
     * Check, whether subscribing is supported
     *
     * @return boolean true, when subscribing is supported
     */
    public function can_subscribe() {
        return has_capability('block/socialcomments:subscribe', $this->context);
    }

    public function get_context() {
        return $this->context;
    }

    public function get_user() {
        return $this->user;
    }

    /**
     * Store the pinned status in database.
     *
     * @param int $contextid id of context (course or activity), must be validated before.
     * @param int $userid id of user
     * @param boolean $checked pinned or not
     * @param int $commentid id 0 then pin context.
     * @return boolean true if is pinned, false if not pinned
     */
    public function set_pinned($contextid, $userid, $checked, $commentid) {
        global $DB;

        $params = array(
            'userid' => $userid,
            'itemtype' => self::PINNED_COMMENT,
            'itemid' => $commentid
        );

        // Probably in page.
        if ($commentid == 0) {
            $params['itemtype'] = self::PINNED_PAGE;
            $params['itemid'] = $contextid;
        }

        if (!$checked) {
            $DB->delete_records('block_socialcomments_pins', $params);
            return false;
        }

        // Item already pinned.
        if ($exists = $DB->get_record('block_socialcomments_pins', $params)) {
            return true;
        }

        $pin = (object) $params;
        $pin->timecreated = time();
        $DB->insert_record('block_socialcomments_pins', $pin);

        return true;
    }

    /**
     * Store the subscription state in database.
     *
     * @param int $courseid
     * @param int $contextid
     * @param int $userid id of user (it should be already checked that user exists).
     * @param boolean $checked value to be set (yes for subscription)
     * @return boolean new state of subscription
     */
    public static function set_subscribed($courseid, $contextid, $userid, $checked) {
        global $DB;

        $params = array(
            'userid' => $userid,
            'contextid' => $contextid
        );

        if (!$checked) {
            $DB->delete_records('block_socialcomments_subscrs', $params);
            return false;
        }

        // Already subscribed?
        if ($exists = $DB->get_record('block_socialcomments_subscrs', $params)) {
            return true;
        }

        $subscript = (object) $params;
        $subscript->courseid = $courseid;
        $subscript->timecreated = time();
        $subscript->timemodified = $subscript->timecreated;
        // Next cronjob will send changes starting from this time.
        $subscript->timelastsent = $subscript->timecreated;
        $DB->insert_record('block_socialcomments_subscrs', $subscript);

        return true;
    }

    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;

        $eventdata = $event->get_data();
        $courseid = $eventdata['objectid'];

        $sql = "SELECT bc.id
                FROM {block_socialcomments_cmmnts} bc
                WHERE courseid = ? ";

        $commentids = $DB->get_records_sql($sql, array($courseid));

        // Delete comments.
        foreach ($commentids as $commentid => $unused) {
            $comment = new comment(array('id' => $commentid));
            $comment->delete();
        }

        // Delete subscriptions.
        $DB->delete_records('block_socialcomments_subscrs', array('courseid' => $courseid));
    }

    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;

        $eventdata = $event->get_data();
        $userid = $eventdata['objectid'];

        $DB->delete_records('block_socialcomments_subscrs', array('userid' => $userid));
        $DB->delete_records('block_socialcomments_pins', array('userid' => $userid));
    }

    public static function get_digest_type_menu() {

        $choices = array(
            self::DIGEST_SITE => get_string('digestpersite', 'block_socialcomments'),
            self::DIGEST_COURSE => get_string('digestpercourse', 'block_socialcomments')
        );

        return $choices;
    }

}
