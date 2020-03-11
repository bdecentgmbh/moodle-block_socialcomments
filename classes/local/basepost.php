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
 * Contains class block_socialcomments\local\basepost.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\local;

defined('MOODLE_INTERNAL') || die;

/**
 * Abstract base class for comments and replies.
 *
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class basepost {

    /**
     * Create a object of this class.
     *
     * @param array $attrs parameter for creating a reply indexed by attriute names.
     * @param boolean $fetch try to fetch attribute values from database first, attrs['id'] is needed.
     * @param int $strictness ignore or force comment exists in database.
     */
    public function __construct($attrs = array(), $fetch = false, $strictness = IGNORE_MISSING) {
        global $USER;

        foreach ($attrs as $key => $value) {
            $this->$key = $value;
        }

        if (empty($this->userid)) {
            $this->userid = $USER->id;
        }
    }

    /**
     * Get the context this repy is posted.
     *
     * @return \context
     */
    abstract public function get_context();

    /**
     * Checks, whether this user can create a post.
     *
     * @param \context $context
     * @return boolean
     */
    public static function can_create($context, $groups = null, $posttogroupid = -1) {
        return has_capability('block/socialcomments:postcomments', $context);
    }

    /**
     * Checks, whether this user can edit the post.
     * Note: declared a static for easy use in loops.
     *
     * @param int $authorid id of user, who has created the comment.
     * @param \context $context
     * @return boolean
     */
    public static function can_edit($authorid, $context) {
        global $USER;

        if (($USER->id == $authorid) && (has_capability('block/socialcomments:postcomments', $context))) {
            return true;
        }

        return false;
    }

    /**
     * Checks, whether this user can edit the post.
     * Note: declared a static for easy use in loops.
     *
     * @param int $id id of comment (0 for new comment).
     * @param int $authorid id of user, who has created the comment.
     * @param \context $context we rely on this context, so be sure to set comments context!
     * @return boolean
     */
    public static function can_save($id, $authorid, $context, $groupid = -1) {
        if ($id == 0) {
            return static::can_create($context, null, $groupid);
        } else {
            return static::can_edit($authorid, $context);
        }
    }

    /**
     * Checks, whether this user can delete the post.
     * Note: declared a static for easy use in loops.
     *
     * @param int $authorid id of user, who has created the comment.
     * @param \context $context
     * @return boolean
     */
    public static function can_delete($authorid, $context) {
        global $USER;

        $candelete = (($USER->id == $authorid) && (has_capability('block/socialcomments:postcomments', $context)));
        if ($candelete) {
            return true;
        }

        return false;
    }

    /**
     * Delete this post.
     */
    abstract public function delete();

    /**
     * Create or update this post.
     */
    abstract public function save();

}
