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
defined('MOODLE_INTERNAL') || die();


class block_socialcomments extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_socialcomments');
    }

    public function applicable_formats() {
        return array('course' => true, 'mod' => true);
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return true;
    }

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


    public function html_attributes() {
        $attributes = parent::html_attributes();

        if (!empty($this->config->hidepins)) {
            $attributes['class'] .= " ccomments-no-pins";
        }

        return $attributes;
    }

}
