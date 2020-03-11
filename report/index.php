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
require(dirname(__FILE__) . '/../../../config.php');

global $CFG, $PAGE, $OUTPUT;

use block_socialcomments\local\comment;

require_once($CFG->dirroot . '/lib/tablelib.php');

$courseid = required_param('courseid', PARAM_INT);

$baseurl = new moodle_url('/blocks/socialcomments/report/index.php', array('courseid' => $courseid));
$PAGE->set_url($baseurl);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

// Check access.
require_login($course);

$perpage = optional_param('perpage', -1, PARAM_INT);
if ($perpage == -1) {
    $perpage = get_config('block_socialcomments', 'reportperpage');
}

$PAGE->set_pagelayout('incourse');

$context = context_course::instance($course->id);
$PAGE->set_context($context);

require_capability('block/socialcomments:viewreport', $context);

$formparams = array(
    'courseid' => $courseid
);

$filterform = new \block_socialcomments\local\reportfilter_form($baseurl, $formparams);

// Get data.
if ($filterdata = $filterform->get_data()) {
    // Convert for use in tableurl.
    $tableurlparams = $filterform->get_url_params($filterdata);
} else {
    // Try to catch data from url and convert to form default.
    $filterdata = $filterform->get_request_data();
    $tableurlparams = $filterform->get_url_params($filterdata);
}

$filterform->set_data($filterdata);
$baseurl->params($tableurlparams);
$baseurl->param('perpage', $perpage);

$PAGE->set_heading(get_string('pluginname', 'block_socialcomments'));
$PAGE->set_title(get_string('socialcommentsreport', 'block_socialcomments'));

$table = new flexible_table('report-table');

$download = optional_param('download', '', PARAM_ALPHA);
if ($download) {
    $table->is_downloading($download, userdate(time(), '%Y-%m-%d-%H%M%S') . '_report');
}

$table->set_attribute('cellspacing', '0');
$table->set_attribute('cellpadding', '3');
$table->set_attribute('class', 'generaltable');
$table->set_attribute('id', 'report-table');

$columns = array('topicname', 'activity', 'commentscount', 'timecreated', 'fullname', 'content', 'action');
$headers = array('topicname', 'activity', 'commentscount', 'date', 'author', 'comment', 'action');

foreach ($headers as $i => $header) {
    if (!empty($header)) {
        $headers[$i] = get_string('h' . $header, 'block_socialcomments');
    } else {
        $headers[$i] = '';
    }
}

$table->headers = $headers;
$table->define_columns($columns);
$table->define_baseurl($baseurl);

$table->no_sorting('action');
$table->sortable(true, 'topicname', SORT_DESC);

$table->pageable(true);
$table->is_downloadable(false);

$table->set_control_variables(
    array(
        TABLE_VAR_SORT => 'tsort',
        TABLE_VAR_PAGE => 'page'
    )
);

$table->setup();

$reporthelper = \block_socialcomments\local\report_helper::get_instance();
$comments = $reporthelper->get_course_comments($filterdata, $table, $perpage, $download);

$renderer = $PAGE->get_renderer('block_socialcomments');

$tabs = $reporthelper->get_tab_tree($course->id);

$heading = $OUTPUT->pix_icon('hreport', '', 'block_socialcomments');
$heading .= get_string('reportspage', 'block_socialcomments');

echo $OUTPUT->header();
echo $OUTPUT->heading($heading);
echo $OUTPUT->tabtree($tabs, 'report');

$filterform->display();

echo html_writer::start_tag('div', array('id' => 'local-impact-table-wrapper'));
$modinfo = get_fast_modinfo($course);

foreach ($comments as $comment) {

    $row = array();

    $row[] = $comment->topicname;
    $activity = $comment->activity;
    if (!$download) {
        $activity = $renderer->render_activity($comment, $reporthelper);
    }
    $row[] = $activity;

    // Number of comments.
    $row[] = $comment->commentscount;
    // Date.
    $row[] = userdate(
            $comment->timecreated,
            get_string('strftimedatefullshort', 'langconfig').' '.get_string('strftimetime', 'langconfig')
        );
    // User.
    $author = fullname($comment);
    if (!$download) {
        $url = new moodle_url('/user/profile.php', array('id' => $comment->userid));
        $author = html_writer::link($url, $author);
    }
    $row[] = $author;

    $row[] = $comment->content;

    $link = '';
    $context = context_helper::instance_by_id($comment->contextid);
    if (comment::can_delete($comment->userid, $context)) {

        $linkparams = array('id' => $comment->id, 'courseid' => $course->id, 'sesskey' => sesskey());
        $url = new moodle_url('/blocks/socialcomments/report/delete_comment.php', $linkparams);
        $link = html_writer::link($url, get_string('delete'));
    }
    $row[] = $link;

    $table->add_data($row);
}

if ($perpage > 0) {
    $baseurl->param('perpage', 0);
    $viewlink = html_writer::link($baseurl, get_string('viewall', 'block_socialcomments'));
} else {
    $viewlink = html_writer::link($baseurl, get_string('viewpaged', 'block_socialcomments'));
}

if ($download) {
    $table->finish_output();
} else {
    $table->finish_html();
    echo $viewlink;
    echo html_writer::end_div();
    echo $OUTPUT->footer();
}