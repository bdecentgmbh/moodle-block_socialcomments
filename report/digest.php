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
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(dirname(__FILE__) . '/../../../config.php');

global $CFG, $PAGE, $OUTPUT, $USER, $DB;

use block_socialcomments\local\comment;

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/socialcomments/report/digest.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Check access.
require_login($course);

$PAGE->set_pagelayout('incourse');

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_capability('block/socialcomments:viewreport', $context);

$PAGE->set_heading(get_string('pluginname', 'block_socialcomments'));
$PAGE->set_title(get_string('digestsubject', 'block_socialcomments'));

$reporturl = new moodle_url('/blocks/socialcomments/report/index.php', array('courseid' => $courseid));
\navigation_node::override_active_url($reporturl);

$timesince = $USER->lastlogin;
$digesthelper = \block_socialcomments\local\digest::get_instance();
$newcomments = $digesthelper->get_subscribed_new_comments_and_replies($USER);

$renderer = $PAGE->get_renderer('block_socialcomments');

$heading = get_string('digestsubject', 'block_socialcomments');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $digesthelper->render_digest_messagetext($newcomments);
echo $OUTPUT->footer();