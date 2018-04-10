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
 * Pinboard page for the socialcomments block.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(__FILE__) . '/../../../config.php');

global $CFG, $PAGE, $OUTPUT;

use block_socialcomments\local\comment;

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/socialcomments/pinboard/index.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Check access.
require_login($course);

$PAGE->set_pagelayout('incourse');

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_capability('block/socialcomments:pinitems', $context);

$PAGE->set_heading(get_string('pluginname', 'block_socialcomments'));
$PAGE->set_title(get_string('pinboard', 'block_socialcomments'));

$reporthelper = \block_socialcomments\local\report_helper::get_instance();
$pinnedcommentsdata = $reporthelper->get_course_comments_pinned($course->id);

$renderer = $PAGE->get_renderer('block_socialcomments');

$heading = $OUTPUT->pix_icon('hpinboard', '', 'block_socialcomments');
$heading .= get_string('pinboard', 'block_socialcomments');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $renderer->render_pinboard($course, $pinnedcommentsdata);
echo $OUTPUT->footer();