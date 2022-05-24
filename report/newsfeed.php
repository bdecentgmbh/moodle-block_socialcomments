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
 * Newsfeed page for the socialcomments block.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(__FILE__) . '/../../../config.php');

global $CFG, $PAGE, $OUTPUT;

use block_socialcomments\local\comment;

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/socialcomments/report/newsfeed.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Check access.
require_login($course);

$PAGE->set_pagelayout('incourse');

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_capability('block/socialcomments:viewreport', $context);

$PAGE->set_heading(get_string('pluginname', 'block_socialcomments'));
$PAGE->set_title(get_string('newsfeed', 'block_socialcomments'));

$reporturl = new moodle_url('/blocks/socialcomments/report/index.php', array('courseid' => $courseid));
\navigation_node::override_active_url($reporturl);

$timesince = $USER->lastlogin;
$reporthelper = \block_socialcomments\local\report_helper::get_instance();
$newcomments = $reporthelper->get_course_new_comments_and_replies($course->id, $timesince);

$renderer = $PAGE->get_renderer('block_socialcomments');
$tabs = $reporthelper->get_tab_tree($course->id);

$heading = $OUTPUT->pix_icon('hreport', '', 'block_socialcomments');
$heading .= get_string('reportspage', 'block_socialcomments');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $OUTPUT->tabtree($tabs, 'newsfeed');
echo $renderer->render_new_comments_tab($course, $newcomments, $timesince);
echo $OUTPUT->footer();
