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

class comment extends basepost {

    public $id = 0;
    public $contextid = 0;
    public $component = 'block_socialcomments';
    public $commentarea = 'page_comments';
    public $itemid = 0;
    public $content = '';
    public $format = 0;
    public $userid = 0;
    public $courseid = 0;
    public $groupid = 0;

    /**
     * Create a comment.
     *
     * @param array $attrs parameter for creating a comment indexed by attriute names.
     * @param boolean $fetch try to fetch attribute values from database first, attrs['id'] is needed.
     * @param int $strictness ignore or force comment exists in database.
     */
    public function __construct($attrs = array(), $fetch = false, $strictness = IGNORE_MISSING) {
        global $DB;

        if ($fetch && !empty($attrs['id'])) {

            if ($dbattrs = $DB->get_record('block_socialcomments_cmmnts', array('id' => $attrs['id']), '*', $strictness)) {

                // Load new content, if available.
                if (isset($attrs['content'])) {
                    $dbattrs->content = $attrs['content'];
                }

                $attrs = (array) $dbattrs;
            }
        }

        parent::__construct($attrs, $fetch, $strictness);
    }

    /**
     * Get the context this comment is posted.
     *
     * @return \context
     */
    public function get_context() {

        $context = \context::instance_by_id($this->contextid, MUST_EXIST);
        return $context;
    }

    /**
     * Get the group mode of module or course.
     *
     * @global object $COURSE
     * @return int one of the groupmode constants NOGROUPS, SEPARATEGROUPS, VISIBLEGROUPS
     */
    public static function get_group_mode($context, $course, $cm) {
        global $CFG;

        if ($context instanceof \context_course) {
            return $course->groupmode;
        }

        // We are in module context.
        require_once($CFG->libdir . '/grouplib.php');
        return groups_get_activity_groupmode($cm);
    }

    /**
     * Get all the groups this user might see or post for.
     */
    public static function get_accessible_groups($context) {
        global $USER;

        list($unused, $course, $cm) = get_context_info_array($context->id);

        if (self::get_group_mode($context, $course, $cm) != SEPARATEGROUPS) {
            return array(0 => (object) array('id' => 0, 'name' => get_string('allgroups', 'block_socialcomments')));
        }

        if (has_capability('moodle/site:accessallgroups', $context)) {
            $visiblegroups[0] = (object) array('id' => 0, 'name' => get_string('allgroups', 'block_socialcomments'));
            $visiblegroups += groups_get_all_groups($course->id, 0);
        } else {
            $visiblegroups = groups_get_all_groups($course->id, $USER->id);
        }

        return $visiblegroups;
    }

    /**
     * Check, whether the visiiblity on the context is restricted by group.
     *
     * @param \context $context
     * @return boolean|array false when no restriction, array of groupids when restricted.
     */
    public static function is_restricted_to_groupids($context, $user = null) {
        global $USER;

        if (!$user) {
            $user = $USER;
        }

        if (has_capability('moodle/site:accessallgroups', $context, $user)) {
            return false;
        }
        // Get group mode.
        list($unused, $course, $cm) = get_context_info_array($context->id);
        $groupmode = self::get_group_mode($context, $course, $cm);

        if ($groupmode != SEPARATEGROUPS) {
            return false;
        }
        // SEPARATEGROUPS, so check user.
        $visiblegroups = groups_get_all_groups($course->id, $user->id);
        // Allow groupid == 0 (means visible for all groups).
        $visiblegroups[0] = 1;

        return array_keys($visiblegroups);
    }

    /**
     * Create a SQL snippet to restric visibility of comments to groups.
     *
     * @param context $context
     * @return array SQL-Snippet and params.
     */
    public static function get_group_restriction_sql($context) {
        global $DB;

        if (!$groupids = self::is_restricted_to_groupids($context)) {
            return array('', array());
        }

        list($ingroupstr, $ingroupparam) = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED);

        return array(' AND groupid ' . $ingroupstr, $ingroupparam);
    }

    /**
     * Checks if the user is allowed to post a comment to a specific group.
     *
     * Note: When we are in seperate group mode and the user is not in at least one
     * group, user os not allowed to post!
     *
     * @param context $context the context, the user might post.
     * @param array $groups the user can access.
     * @param int $posttogroupid if set check whether the user can post to this group.
     * @return boolean true if user can create a comment.
     */
    public static function can_create($context, $groups = null, $posttogroupid = -1) {

        if (!has_capability('block/socialcomments:postcomments', $context)) {
            return false;
        }

        if (!$groups) {
            $groups = self::get_accessible_groups($context);
        }

        if (count($groups) == 0) {
            return false;
        }

        if (($posttogroupid > -1) && (!in_array($posttogroupid, array_keys($groups)))) {
            return false;
        }

        return true;
    }

    /**
     * Checks, whether this user can delete the comment.
     * Note: declared a static for easy use in loops.
     *
     * @param int $authorid id of user, who has created the comment.
     * @param \context $context
     * @return boolean
     */
    public static function can_delete($authorid, $context) {
        global $USER;

        if (($USER->id == $authorid) && (has_capability('block/socialcomments:deleteowncomments', $context))) {
            return true;
        }

        return has_capability('block/socialcomments:deletecomments', $context);
    }

    /**
     * Delete this comment and all related items.
     */
    public function delete() {
        global $DB;

        $DB->delete_records('block_socialcomments_cmmnts', array('id' => $this->id));
        $DB->delete_records('block_socialcomments_replies', array('commentid' => $this->id));
        $DB->delete_records('block_socialcomments_pins', array(
            'itemid' => $this->id,
            'itemtype' => comments_helper::PINNED_COMMENT)
        );
    }

    public function fire_event_created() {

        $event = \block_socialcomments\event\comment_created::create(
                array(
                    'contextid' => $this->contextid,
                    'objectid' => $this->id,
                    'other' => array(
                        'userid' => $this->userid
                    )
                )
        );
        $event->trigger();
    }

    /**
     * Create or update this post.
     *
     * @return \block_socialcomments\local\comment
     */
    public function save() {
        global $DB, $USER;

        // Course id is needed for proper cleanup, when course is deleted.
        if ($this->contextid > 0) {
            list($unused, $course, $cm) = get_context_info_array($this->contextid);
            $this->courseid = $course->id;
        } else {
            $this->courseid = SITEID;
        }

        $this->timemodified = time();

        if ($this->id > 0) {
            $DB->update_record('block_socialcomments_cmmnts', $this);
        } else {
            $this->userid = $USER->id;
            $this->timecreated = $this->timemodified;
            $this->id = $DB->insert_record('block_socialcomments_cmmnts', $this);

            $this->fire_event_created();
        }
        return $this;
    }
}
