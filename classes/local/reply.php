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
 * Contains class block_socialcomments\local\reply.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\local;

defined('MOODLE_INTERNAL') || die;

class reply extends basepost {

    public $id = 0;
    public $commentid = 0;
    public $content = '';
    public $format = 0;
    public $userid = 0;

    /**
     * Create a reply.
     *
     * @param array $attrs parameter for creating a reply indexed by attriute names.
     * @param boolean $fetch try to fetch attribute values from database first, attrs['id'] is needed.
     * @param int $strictness ignore or force comment exists in database.
     */
    public function __construct($attrs = array(), $fetch = false, $strictness = IGNORE_MISSING) {
        global $DB;

        if ($fetch && !empty($attrs['id'])) {

            if ($dbattrs = $DB->get_record('block_socialcomments_replies', array('id' => $attrs['id']), '*', $strictness)) {

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
     * Get the context this repy is posted.
     *
     * @return \context
     */
    public function get_context() {
        global $DB;

        $comment = $DB->get_record('block_socialcomments_cmmnts', array('id' => $this->commentid), '*', MUST_EXIST);
        $context = \context::instance_by_id($comment->contextid, MUST_EXIST);

        return $context;
    }

    /**
     * Checks, whether this user can delete the reply.
     * Note: declared a static for easy use in loops.
     *
     * @param int $authorid id of user, who has created the comment.
     * @param \context $context
     * @return boolean
     */
    public static function can_delete($authorid, $context) {
        global $USER;

        if (($USER->id == $authorid) && (has_capability('block/socialcomments:deleteownreplies', $context))) {
            return true;
        }

        return has_capability('block/socialcomments:deletereplies', $context);
    }

    /**
     * Delete this reply.
     */
    public function delete() {
        global $DB;
        $DB->delete_records('block_socialcomments_replies', array('id' => $this->id));
    }

    public function fire_event_created() {

        $event = \block_socialcomments\event\reply_created::create(
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
     * @return \block_socialcomments\local\reply
     */
    public function save() {
        global $DB, $USER;

        $this->timemodified = time();

        if ($this->id > 0) {
            $DB->update_record('block_socialcomments_replies', $this);
        } else {
            $this->userid = $USER->id;
            $this->timecreated = $this->timemodified;
            $this->id = $DB->insert_record('block_socialcomments_replies', $this);

            $this->fire_event_created();
        }
        return $this;
    }
}
