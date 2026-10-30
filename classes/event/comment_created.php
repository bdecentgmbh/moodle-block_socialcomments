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
 * block_socialcomments comment created event.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\event;

/**
 * block_socialcomments comment created event.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment_created extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'block_socialcomments_cmmnts';
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventcommentcreated', 'block_socialcomments');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' added the comment with id '$this->objectid' to the page with context id " .
            "'$this->contextid'.";
    }

    /**
     * Validate the data of the event.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['userid'])) {
            throw new \coding_exception('The \'userid\' value must be set in other.');
        }
    }

    /**
     * Mapping of the comment to restore logs.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'block_socialcomments_cmmnts', 'restore' => 'block_socialcomments_cmmnts'];
    }

    /**
     * Mapping of the author to restore logs.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return ['userid' => ['db' => 'user', 'restore' => 'user']];
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        $context = $this->get_context();
        if ($context) {
            return $context->get_url();
        } else {
            return null;
        }
    }
}
