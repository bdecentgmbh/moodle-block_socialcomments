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

global $CFG;

require_once($CFG->dirroot . '/lib/messagelib.php');

class digest {

    private $grouprestriction = array();
    private $timelastsent = array();
    private $digesttype = 0;

    /**
     * Get an instance of an object of this class. Create as a singleton.
     *
     * @staticvar report_helper $reporthelper
     * @param boolean $forcenewinstance true, when a new instance should be created.
     * @return report_helper
     */
    public static function get_instance($forcenewinstance = false) {
        static $digest;

        if (isset($digest) && !$forcenewinstance) {
            return $digest;
        }

        $digest = new digest();
        return $digest;
    }

    private function __construct() {
        $digesttype = get_config('block_socialcomments', 'digesttype');
        $this->digesttype = $digesttype;
    }

    /**
     * Check, whether the context restricts visibility by group for given user.
     *
     * @param object $user
     * @param context $context
     * @return boolean|array false, when there is no restriction, array of groupids otherwise
     */
    private function is_restricted_to_groupids($context, $user) {

        if (isset($this->grouprestriction[$context->id])) {
            return $this->grouprestriction[$context->id];
        }

        $this->grouprestriction[$context->id] = comment::is_restricted_to_groupids($context, $user);
        return $this->grouprestriction[$context->id];
    }

    /**
     * Get all new comments and replies since the last subscription senttime.
     * If a new reply is found, its comments is retrieved.
     *
     * @param int $courseid int id of course
     * @param int $timesince unix timesramp
     * @return array, the list of new comments/replies indexed by courseid, contextid, commentid.
     */
    public function get_subscribed_new_comments_and_replies($user) {
        global $DB;

        $authorfields = get_all_user_name_fields(true, 'u');
        $authorpicturefields = \user_picture::fields('u');

        // Get new replies.
        $sql = "SELECT r.id as postid, r.commentid, r.content, r.timecreated, r.userid,
                $authorfields, $authorpicturefields, bc.contextid as postcontextid
                FROM {block_socialcomments_replies} r
                JOIN {user} u ON r.userid = u.id
                JOIN {block_socialcomments_cmmnts} bc ON bc.id = r.commentid
                JOIN {block_socialcomments_subscrs} sub ON sub.contextid = bc.contextid AND r.timemodified >= sub.timelastsent
                WHERE sub.userid = ? ";

        $orderby = 'ORDER BY r.timecreated DESC';

        $replies = $DB->get_records_sql($sql . $orderby, array($user->id));

        $neededcommentsid = array();
        $repliesgroupedbycomments = array();

        foreach ($replies as $reply) {

            if (!isset($repliesgroupedbycomments[$reply->commentid])) {
                $repliesgroupedbycomments[$reply->commentid] = array();
            }

            $repliesgroupedbycomments[$reply->commentid][$reply->postid] = $reply;
            $neededcommentsid[$reply->commentid] = $reply->commentid;
        }

        // Get new comments or needed comments.
        $sql = "SELECT bc.id as postid, bc.content, bc.timecreated, bc.userid,
                u.id, $authorfields, $authorpicturefields, bc.contextid as postcontextid,
                bc.groupid, sub.courseid
                FROM {block_socialcomments_cmmnts} bc
                JOIN {user} u ON bc.userid = u.id
                JOIN {block_socialcomments_subscrs} sub ON sub.contextid = bc.contextid ";

        $params = array('userid' => $user->id);

        $orcond = " (bc.timemodified >= sub.timelastsent)";
        // Add comments that are needed for replies.
        if (!empty($neededcommentsid)) {
            list($incommentids, $incommentidparam) = $DB->get_in_or_equal($neededcommentsid, SQL_PARAMS_NAMED);
            $orcond .= " OR (bc.id {$incommentids}) ";
            $params += $incommentidparam;
        }

        $where = "WHERE (sub.userid = :userid) AND ($orcond)";
        $orderby = 'ORDER BY bc.timecreated ASC';

        $comments = $DB->get_records_sql($sql . $where . $orderby, $params);

        if (count($comments) == 0) {
            return array();
        }

        // Group by course and context and add replies.
        $groupeddata = array();
        foreach ($comments as $cmt) {

            $context = \context_helper::instance_by_id($cmt->postcontextid);
            $restrictedtogroups = $this->is_restricted_to_groupids($context, $user);

            if (($restrictedtogroups) && (!in_array($cmt->groupid, $restrictedtogroups))) {
                continue;
            }

            if (!isset($groupeddata[$cmt->courseid])) {
                $groupeddata[$cmt->courseid] = array();
                $this->timelastsent[$cmt->courseid] = array();
            }

            if (!isset($groupeddata[$cmt->courseid][$cmt->postcontextid])) {
                $groupeddata[$cmt->courseid][$cmt->postcontextid] = array();
            }

            $commentdata = new \stdClass();
            $commentdata->comment = $cmt;
            $commentdata->replies = array();

            if (isset($repliesgroupedbycomments[$cmt->postid])) {
                $commentdata->replies = $repliesgroupedbycomments[$cmt->postid];
            }

            $groupeddata[$cmt->courseid][$cmt->postcontextid][$cmt->postid] = $commentdata;

            // Note the actual time for later use.
            // Set this here to skip the time, during access is restricted by group.
            $this->timelastsent[$cmt->courseid][$cmt->postcontextid] = time();
        }

        return $groupeddata;
    }

    /**
     * Render the commentsdata for digest.
     *
     * @param array $commentsdata data containing comments and replies indexed by course id.
     * @return string
     */
    public function render_digest_messagetext($commentsdata) {
        global $DB, $PAGE;

        $messagetext = '';

        $renderer = $PAGE->get_renderer('block_socialcomments');

        // Render new data for each course.
        foreach ($commentsdata as $courseid => $contextcomments) {

            if (!$course = $DB->get_record('course', array('id' => $courseid))) {
                continue;
            }
            $messagetext .= $renderer->render_digest($course, $contextcomments);
        }

        return $messagetext;
    }

    protected function get_css_styles() {
        global $CFG;

        $css = file_get_contents($CFG->dirroot.'/blocks/socialcomments/styles.css');
        return \html_writer::tag('style', $css);
    }

    protected function send_message($userto, $messagetext) {

        $message = new \core\message\message();
        $message->courseid  = SITEID;
        $message->component = 'block_socialcomments';
        $message->name = 'digest';
        $message->userfrom = \core_user::get_user(\core_user::NOREPLY_USER);
        $message->userto = $userto;
        $message->subject = get_string('digestsubject', 'block_socialcomments');
        $message->fullmessage = html_to_text($messagetext, 80, false);
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $message->fullmessagehtml = $this->get_css_styles().$messagetext;
        $message->notification = 1;

        $messageid = message_send($message);

        return $messageid;
    }

    /**
     * Send out a digest for a user.
     *
     * @param object $user
     * @return boolean true, when successfully sent.
     */
    public function send_digest_for_user($user) {
        global $DB, $PAGE;

        $newcommentsdata = $this->get_subscribed_new_comments_and_replies($user);

        if (empty($newcommentsdata)) {
            return true;
        }

        if ($this->digesttype == comments_helper::DIGEST_SITE) {

            $messagetext = $this->render_digest_messagetext($newcommentsdata);

            $messageid = $this->send_message($user, $messagetext);
            // Note the time.
            if ($messageid) {

                foreach ($this->timelastsent as $courseid => $contextids) {

                    foreach ($contextids as $contextid => $time) {
                        $params = array('userid' => $user->id, 'contextid' => $contextid);
                        $DB->set_field('block_socialcomments_subscrs', 'timelastsent', $time, $params);
                    }
                }
            }
            return $messageid;
        }

        $result = true;
        if ($this->digesttype == comments_helper::DIGEST_COURSE) {

            $renderer = $PAGE->get_renderer('block_socialcomments');

            // Render new data for each course.
            foreach ($newcommentsdata as $courseid => $contextcomments) {

                if (!$course = $DB->get_record('course', array('id' => $courseid))) {
                    continue;
                }
                $messagetext = $renderer->render_digest($course, $contextcomments);
                $messageid = $this->send_message($user, $messagetext);

                if ($messageid && isset($this->timelastsent[$courseid])) {

                    foreach ($this->timelastsent[$courseid] as $contextid => $time) {
                        $params = array('userid' => $user->id, 'contextid' => $contextid);
                        $DB->set_field('block_socialcomments_subscrs', 'timelastsent', $time, $params);
                    }
                }
            }

            $result = ($result && ($messageid > 0));
        }

        return $result;
    }

    /**
     *
     * @return boolean
     */
    public static function cron() {
        global $DB;

        $result = true;

        $limit = 0;
        $userspercron = get_config('block_socialcomments', 'userspercron');

        if ($userspercron > 0) {
            $limit = $userspercron;
        }

        // Get the users, that have subscriptions
        // (ordered by timelastent ASC to process the long waiting users first.
        $sql = "SELECT s.userid, MIN(s.timelastsent) as mintime
                FROM {block_socialcomments_subscrs} s
                GROUP BY s.userid
                ORDER BY mintime ASC ";

        $userids = $DB->get_records_sql($sql, array(), 0, $limit);
        if (!$userids) {
            return $result;
        }

        $userids = array_keys($userids);

        foreach ($userids as $userid) {

            $user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0));

            if (!$user) {
                continue;
            }

            cron_setup_user($user);

            $digest = self::get_instance(true);
            $result = ($result && $digest->send_digest_for_user($user));
        }
        cron_setup_user();

        return $result;
    }

}
