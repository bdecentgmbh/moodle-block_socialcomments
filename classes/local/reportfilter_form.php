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
 * Filterform for report page.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_socialcomments\local;

defined('MOODLE_INTERNAL') || die();

use html_writer;

global $CFG;

require_once($CFG->dirroot . '/lib/formslib.php');

class reportfilter_form extends \moodleform {

    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        $reporthelper = \block_socialcomments\local\report_helper::get_instance();
        $visiblesectionmenu = $reporthelper->get_visible_section_menu();

        // Topics.
        $choices = array('0' => get_string('selecttopic', 'block_socialcomments'));
        $choices += $visiblesectionmenu;
        $mform->addElement('select', 'sectionid', get_string('topic', 'block_socialcomments'), $choices);

        // Add modules later.
        $sectionid = optional_param('sectionid', 0, PARAM_INT);
        $choices = array(0 => get_string('selectactivity', 'block_socialcomments'));
        $choices += $reporthelper->get_visible_mods_menu($sectionid);

        $mform->addElement('select', 'activityid', get_string('activity', 'block_socialcomments'), $choices);

        // Daterange.
        $mform->addElement('date_selector', 'fromdate', get_string('fromdate', 'block_socialcomments'), array('optional' => true));
        $mform->addElement('date_selector', 'todate', get_string('todate', 'block_socialcomments'), array('optional' => true));

        $mform->addElement('text', 'author', get_string('author', 'block_socialcomments'));
        $mform->setType('author', PARAM_TEXT);

        $mform->addElement('text', 'content', get_string('content', 'block_socialcomments'));
        $mform->setType('content', PARAM_TEXT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', $this->_customdata['courseid']);

        // Buttons.
        $mform->addElement('submit', 'submit', get_string('search'));

        $args = array();
        $args['courseid'] = $this->_customdata['courseid'];
        $PAGE->requires->js_call_amd('block_socialcomments/report', 'init', array($args));
    }

    public function get_request_data() {

        $filterdata = new \stdClass();

        $sectionid = optional_param('sectionid', 0, PARAM_INT);
        if (!empty($sectionid)) {
            $filterdata->sectionid = $sectionid;
        }

        $activityid = optional_param('activityid', 0, PARAM_INT);
        if (!empty($activityid)) {
            $filterdata->activityid = $activityid;
        }

        $fromdate = optional_param('fromdate', 0, PARAM_INT);
        if (!empty($fromdate)) {
            $filterdata->fromdate = $fromdate;
        }

        $todate = optional_param('todate', 0, PARAM_INT);
        if (!empty($todate)) {
            $filterdata->todate = $todate;
        }

        $author = optional_param('author', '', PARAM_TEXT);
        if (!empty($author)) {
            $filterdata->author = $author;
        }

        $content = optional_param('content', '', PARAM_TEXT);
        if (!empty($content)) {
            $filterdata->content = $content;
        }

        $filterdata->courseid = required_param('courseid', PARAM_INT);

        return $filterdata;
    }

    public function get_url_params($data) {

        $params = array();

        if (!empty($data->sectionid)) {
            $params['sectionid'] = $data->sectionid;
        }

        if (!empty($data->activityid)) {
            $params['activityid'] = $data->activityid;
        }

        if (!empty($data->fromdate)) {
            $params['fromdate'] = $data->fromdate;
        }

        if (!empty($data->todate)) {
            $params['todate'] = $data->todate;
        }

        if (!empty($data->author)) {
            $params['author'] = $data->author;
        }

        if (!empty($data->content)) {
            $params['content'] = $data->content;
        }

        if (!empty($data->coursed)) {
            $params['coursed'] = $data->coursed;
        }

        return $params;
    }

}
