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

/* * Generator for the block.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class block_socialcomments_generator extends component_generator_base {

    private $countcomment = 0;
    private $countreplies = 0;

    public function create_comment($record = null) {
        global $DB, $USER;

        $this->countcomment++;

        if (!isset($record['contextid'])) {
            throw new moodle_exception('errormissingcontextid', 'block_socialcomments');
        }

        if (!isset($record['component'])) {
            $record['component'] = 'block_socialcomments';
        }

        if (!isset($record['commentarea'])) {
            $record['commentarea'] = 'page_comments';
        }

        if (!isset($record['itemid'])) {
            $record['itemid'] = 0;
        }

        if (!isset($record['content'])) {
            $record['content'] = 'Comment ' . $this->countcomment;
        }

        if (!isset($record['format'])) {
            $record['format'] = 0;
        }

        if (!isset($record['userid'])) {
            $record['userid'] = $USER->id;
        }

        if (!isset($record['courseid'])) {
            list($unused, $course, $cm) = get_context_info_array($record['contextid']);
            $record['courseid'] = $course->id;
        }

        if (!isset($record['groupid'])) {
            $record['groupid'] = 0;
        }

        if (!isset($record['timecreated'])) {
            $record['timecreated'] = time();
        }

        if (!isset($record['timemodified'])) {
            $record['timemodified'] = $record['timecreated'];
        }

        $comment = (object) $record;
        $id = $DB->insert_record('block_socialcomments_cmmnts', $comment);

        return $DB->get_record('block_socialcomments_cmmnts', array('id' => $id));
    }

    public function create_reply($record = null) {
        global $DB, $USER;

        $this->countreplies++;

        if (!isset($record['commentid'])) {
            throw new moodle_exception('errormissingcommentid', 'block_socialcomments');
        }

        if (!isset($record['content'])) {
            $record['content'] = 'Reply ' . $this->countreplies;
        }

        if (!isset($record['format'])) {
            $record['format'] = 0;
        }

        if (!isset($record['userid'])) {
            $record['userid'] = $USER->id;
        }

        if (!isset($record['timecreated'])) {
            $record['timecreated'] = time();
        }

        if (!isset($record['timemodified'])) {
            $record['timemodified'] = $record['timecreated'];
        }

        $comment = (object) $record;
        $id = $DB->insert_record('block_socialcomments_replies', $comment);

        return $DB->get_record('block_socialcomments_replies', array('id' => $id));
    }

    public function create_subscription($record = null) {
        global $DB, $USER;

        if (!isset($record['contextid'])) {
            throw new moodle_exception('errormissingcontextid', 'block_socialcomments');
        }

        if (!isset($record['courseid'])) {
            list($unused, $course, $cm) = get_context_info_array($record['contextid']);
            $record['courseid'] = $course->id;
        }

        if (!isset($record['userid'])) {
            $record['userid'] = $USER->id;
        }

        if (!isset($record['timecreated'])) {
            $record['timecreated'] = time();
        }

        if (!isset($record['timemodified'])) {
            $record['timemodified'] = $record['timecreated'];
        }

        if (!isset($record['timelastsent'])) {
            $record['timelastsent'] = $record['timecreated'];
        }

        $subscription = (object) $record;
        $id = $DB->insert_record('block_socialcomments_subscrs', $subscription);

        return $DB->get_record('block_socialcomments_subscrs', array('id' => $id));
    }

}
