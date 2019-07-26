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
 * Report page for the socialcomments block.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../../config.php');

global $DB;

use block_socialcomments\local\comment;

$id = required_param('id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_login($courseid);
$comment = new comment(array('id' => $id), true, MUST_EXIST);

$delete = optional_param('delete', '', PARAM_ALPHANUM);
$context = context_helper::instance_by_id($comment->contextid);

if (!comment::can_delete($comment->userid, $context)) {
    // Can not delete frontpage or don't have permission to delete the course.
    print_error('cannotdeletecourse');
}

$PAGE->set_url('/blocks/socialcomments/delete_comment.php', array('id' => $id));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

$redirecturl = new moodle_url('/blocks/socialcomments/report/index.php', array('courseid' => $course->id));

// Check if we've got confirmation.
if ($delete === md5($comment->timemodified)) {
    // We do - time to delete the course.
    require_sesskey();
    $comment->delete();

    redirect($redirecturl, get_string('commentdeleted', 'block_socialcomments'));
}

$params = array(
    'id' => $comment->id,
    'courseid' => $course->id,
    'delete' => md5($comment->timemodified)
);

$continueurl = new moodle_url('/blocks/socialcomments/report/delete_comment.php', $params);
$continuebutton = new single_button($continueurl, get_string('delete'), 'post');

$renderer = $PAGE->get_renderer('block_socialcomments');

$PAGE->set_title($SITE->shortname);
$PAGE->set_heading(get_string('pluginname', 'block_socialcomments'));

echo $OUTPUT->header();

$message = get_string('deletecheck', 'block_socialcomments');

$author = \core_user::get_user($comment->userid);
$post = $renderer->render_post($author, $comment, array());

$message .= html_writer::div($post, 'ccomment-post');

echo $OUTPUT->confirm($message, $continuebutton, $redirecturl);
echo $OUTPUT->footer();
exit;
