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
 * Socialcomments block external API.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once("$CFG->libdir/externallib.php");

/**
 * Socialcomments block external API.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments_external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * content text is outputted in the block by format_text, so we do not need to clean
     * inputted content here and we can use PARAM_RAW.
     * (following the recommended approach as described in moodlelib.php line 117).
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function save_comment_parameters() {

        return new external_function_parameters(
            array(
            'contextid' => new external_value(PARAM_INT, 'id of context...'),
            'content' => new external_value(PARAM_RAW, 'content...'),
            'groupid' => new external_value(PARAM_INT, 'groupid, 0 for all groups'),
            'id' => new external_value(PARAM_TEXT, 'id of comment, 0 for new comment'),
            )
        );
    }

    /**
     * Save a comment.
     *
     * @param int $contextid The id of the context, when creating a new comment.
     * @param string $content
     * @param int $groupid
     * @param int $id
     * @return array of results
     */
    public static function save_comment($contextid, $content, $groupid, $id) {
        global $USER, $COURSE;

        $warnings = array();
        $arrayparams = array(
            'contextid' => $contextid,
            'content' => $content,
            'groupid' => $groupid,
            'id' => $id,
        );
        $params = self::validate_parameters(self::save_comment_parameters(), $arrayparams);

        $comment = new \block_socialcomments\local\comment($params, true);

        if ($id == 0) {
            $context = \context::instance_by_id($contextid);
        } else {
            $context = $comment->get_context();
        }

        self::validate_context($context);

        if (!$comment->can_save($id, $comment->userid, $context, $groupid)) {
            print_error('missingcapability');
        }

        $commentshelper = new \block_socialcomments\local\comments_helper($context);

        $setsubscribed = false;
        if ($id == 0) {
            // Subscribe user to context, when there is a new comment, must be before saving for digest timelastent timestamp.
            $setsubscribed = $commentshelper->set_subscribed($COURSE->id, $context->id, $USER->id, true);
        }

        $comment->save();
        $commentscount = $commentshelper->get_commentscount();

        $results = array(
            'count' => get_string('commentscount', 'block_socialcomments', $commentscount),
            'id' => $comment->id,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function save_comment_returns() {

        return new external_single_structure(
            array(
            'count' => new external_value(PARAM_TEXT, 'count of comments'),
            'id' => new external_value(PARAM_INT, 'id of comment'),
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function delete_comment_parameters() {

        return new external_function_parameters(array(
            'commentid' => new external_value(PARAM_INT, 'id of comment'),
            )
        );
    }

    /**
     * Delete a comment.
     *
     * @param int $commentid
     * @return array
     */
    public static function delete_comment($commentid) {

        $warnings = array();
        $arrayparams = array(
            'commentid' => $commentid,
        );
        $params = self::validate_parameters(self::delete_comment_parameters(), $arrayparams);

        $comment = new \block_socialcomments\local\comment(array('id' => $params['commentid']), true, MUST_EXIST);

        $context = $comment->get_context();
        self::validate_context($context);

        if (!$comment->can_delete($comment->userid, $context)) {
            print_error('missingcapability');
        }
        $comment->delete();

        $commentshelper = new \block_socialcomments\local\comments_helper($context);
        $commentscount = $commentshelper->get_commentscount();

        $results = array(
            'deletedcommentid' => $comment->id,
            'count' => $commentscount,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function delete_comment_returns() {

        return new external_single_structure(
            array(
            'deletedcommentid' => new external_value(PARAM_INT, 'id of deleted comment'),
            'count' => new external_value(PARAM_INT, 'total count of comments'),
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function set_pinned_parameters() {

        return new external_function_parameters(
            array(
            'contextid' => new external_value(PARAM_INT, 'id of context...'),
            'checked' => new external_value(PARAM_BOOL, 'pinned status of page'),
            'commentid' => new external_value(PARAM_INT, 'id of comment or zero'),
            )
        );
    }

    /**
     * Save a comment.
     *
     * @param int $contextid
     * @param boolean $checked
     * @param int $commentid
     * @return array of results
     */
    public static function set_pinned($contextid, $checked, $commentid) {
        global $USER;

        $warnings = array();
        $arrayparams = array(
            'contextid' => $contextid,
            'checked' => $checked,
            'commentid' => $commentid
        );
        $params = self::validate_parameters(self::set_pinned_parameters(), $arrayparams);

        if ($commentid == 0) {
            $context = self::get_context_from_params($params);
        } else {
            $comment = new \block_socialcomments\local\comment(array('id' => $params['commentid']), true, MUST_EXIST);
            $context = $comment->get_context();
        }
        self::validate_context($context);

        require_capability('block/socialcomments:pinitems', $context);

        $commentshelper = new \block_socialcomments\local\comments_helper($context);
        $checked = $commentshelper->set_pinned($context->id, $USER->id, $checked, $commentid);

        if ($commentid == 0) {
            $tooltip = ($checked) ? get_string('unpinpage', 'block_socialcomments') : get_string('pinpage', 'block_socialcomments');
        } else {
            $tooltip = ($checked) ? get_string('unpin', 'block_socialcomments') : get_string('pin', 'block_socialcomments');
        }

        $results = array(
            'commentid' => $commentid,
            'checked' => $checked,
            'tooltip' => $tooltip,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function set_pinned_returns() {

        return new external_single_structure(
            array(
            'commentid' => new external_value(PARAM_INT, 'id of comment'),
            'checked' => new external_value(PARAM_BOOL, 'pin state'),
            'tooltip' => new external_value(PARAM_TEXT, 'tooltip'),
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function set_subscribed_parameters() {

        return new external_function_parameters(
            array(
            'contextid' => new external_value(PARAM_INT, 'id of context'),
            'checked' => new external_value(PARAM_BOOL, 'pinned status of page'),
            )
        );
    }

    /**
     * Subscribe to a context.
     *
     * @param int $contextid the id of the context.
     * @param boolean $checked
     * @return array of results
     */
    public static function set_subscribed($contextid, $checked) {
        global $USER, $COURSE;

        $warnings = array();
        $arrayparams = array(
            'contextid' => $contextid,
            'checked' => $checked,
        );
        $params = self::validate_parameters(self::set_subscribed_parameters(), $arrayparams);

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        require_capability('block/socialcomments:subscribe', $context);

        $commentshelper = new \block_socialcomments\local\comments_helper($context);

        $checked = $commentshelper->set_subscribed($COURSE->id, $context->id, $USER->id, $checked);

        $results = array(
            'checked' => $checked,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function set_subscribed_returns() {

        return new external_single_structure(
            array(
            'checked' => new external_value(PARAM_BOOL, 'subscribed state'),
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_commentspage_parameters() {

        return new external_function_parameters(
            array(
            'contextid' => new external_value(PARAM_INT, 'id of context'),
            'pagenumber' => new external_value(PARAM_INT, 'number of page, (-1) to get the last page'),
            )
        );
    }

    /**
     * Save a comment.
     *
     * @param int $contextid
     * @param int $pagenumber
     * @return array of results
     */
    public static function get_commentspage($contextid, $pagenumber) {
        global $PAGE;

        $warnings = array();
        $arrayparams = array(
            'contextid' => $contextid,
            'pagenumber' => $pagenumber,
        );
        $params = self::validate_parameters(self::get_commentspage_parameters(), $arrayparams);

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        require_capability('block/socialcomments:view', $context);

        $renderer = $PAGE->get_renderer('block_socialcomments');
        $commentshelper = new \block_socialcomments\local\comments_helper($context);

        $contentdata = new \stdClass();
        $contentdata->comments = $commentshelper->get_comments($pagenumber);
        $commentspage = $renderer->render_comments_page($commentshelper, $contentdata);

        $results = array(
            'pagenumber' => $contentdata->comments->currentpage,
            'commentspage' => $commentspage,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_commentspage_returns() {

        return new external_single_structure(
            array(
            'pagenumber' => new external_value(PARAM_INT, 'number of returned page of commentslist'),
            'commentspage' => new external_value(PARAM_RAW, 'html page of comments'),
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function save_reply_parameters() {

        return new external_function_parameters(
            array(
            'contextid' => new external_value(PARAM_INT, 'id of context...'),
            'content' => new external_value(PARAM_RAW, 'content...'),
            'commentid' => new external_value(PARAM_INT, 'id of user...'),
            'id' => new external_value(PARAM_INT, 'id of comment, 0 for new comment'),
            )
        );
    }

    /**
     * Save a reply.
     *
     * @param int $contextid ID of the context.
     * @param string $content Content of the reply.
     * @param int $commentid
     * @param int $id
     * @return array of results
     */
    public static function save_reply($contextid, $content, $commentid, $id) {

        $warnings = array();
        $arrayparams = array(
            'contextid' => $contextid,
            'content' => $content,
            'commentid' => $commentid,
            'id' => $id,
        );
        $params = self::validate_parameters(self::save_reply_parameters(), $arrayparams);

        $reply = new \block_socialcomments\local\reply($params, true);

        if ($id == 0) {
            $context = \context::instance_by_id($contextid);
        } else {
            $context = $reply->get_context();
        }

        self::validate_context($context);

        if (!$reply->can_save($id, $reply->userid, $context)) {
            print_error('missingcapability');
        }
        $reply->save();

        $results = array(
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function save_reply_returns() {

        return new external_single_structure(
            array(
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function delete_reply_parameters() {

        return new external_function_parameters(array(
            'replyid' => new external_value(PARAM_INT, 'id of reply'),
            )
        );
    }

    /**
     * Delete a reply.
     *
     * @param int $replyid ID of the reply.
     * @return array of results
     */
    public static function delete_reply($replyid) {

        $warnings = array();
        $arrayparams = array(
            'replyid' => $replyid,
        );
        $params = self::validate_parameters(self::delete_reply_parameters(), $arrayparams);

        $reply = new \block_socialcomments\local\reply(array('id' => $params['replyid']), true, MUST_EXIST);

        // Validate context.
        $context = $reply->get_context();
        self::validate_context($context);

        if (!$reply->can_delete($reply->userid, $context)) {
            print_error('missingcapability');
        }

        $reply->delete();

        $results = array(
            'deletedreplyid' => $reply->id,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function delete_reply_returns() {

        return new external_single_structure(array(
            'deletedreplyid' => new external_value(PARAM_INT, 'id of deleted comment'),
            'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_activity_options_parameters() {

        return new external_function_parameters(array(
            'sectionid' => new external_value(PARAM_INT, 'id of section'),
            'courseid' => new external_value(PARAM_INT, 'id of courseid'),
            )
        );
    }

    /**
     * Get options for the activity select form element.
     *
     * @param int $sectionid ID of the section.
     * @param int $courseid ID of the course.
     * @return array of results
     */
    public static function get_activity_options($sectionid, $courseid) {

        $warnings = array();
        $arrayparams = array(
            'sectionid' => $sectionid,
            'courseid' => $courseid
        );

        $params = self::validate_parameters(self::get_activity_options_parameters(), $arrayparams);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        if (!has_capability('block/socialcomments:viewreport', $context)) {
            print_error('missingcapability');
        }

        $reporthelper = \block_socialcomments\local\report_helper::get_instance();
        $options = $reporthelper->get_options($params['sectionid']);

        $results = array(
            'options' => $options,
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_activity_options_returns() {

        return new external_single_structure(array(
            'options' => new external_value(PARAM_RAW, 'options for activity select element'),
            'warnings' => new external_warnings()
            )
        );
    }

}
