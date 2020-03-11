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
 * Handles displaying the socialcomments block.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Socialcomments block definition class.
 *
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments extends block_base {

    /**
     * Initialise the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_socialcomments');
    }

    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('course' => true, 'mod' => true);
    }

    /**
     * Forbid the block to be added multiple times to a single page.
     *
     * @return boolean
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Allow the block to have a configuration page.
     *
     * @return boolean
     */
    public function has_config() {
        return true;
    }

    /**
     * Return the content of this block.
     *
     * @return stdClass the content
     */
    public function get_content() {
        global $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';
        if (empty($this->instance)) {
            return $this->content;
        }

        if (!has_capability('block/socialcomments:view', $PAGE->context)) {
            return $this->content;
        }

        $commentshelper = new \block_socialcomments\local\comments_helper($PAGE->context);
        $contentdata = $commentshelper->get_content_data();

        $renderer = $PAGE->get_renderer('block_socialcomments');

        $this->content = new stdClass();
        $this->content->text = $renderer->render_block_content($commentshelper, $contentdata);
        $this->content->footer = '';
        return $this->content;
    }

    /**
     * Return the attributes to set for this block.
     *
     * @return array An array of HTML attributes.
     */
    public function html_attributes() {
        $attributes = parent::html_attributes();

        if (!empty($this->config->hidepins)) {
            $attributes['class'] .= " ccomments-no-pins";
        }

        return $attributes;
    }

}
