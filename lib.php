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
 * Library file for block socialcomments.
 *
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * This function extends the navigation with the report items.
 * Please note that students must have the capability moodle/site:viewreports
 * to see this item.
 *
 * @param navigation_node $navigation The navigation node to extend
 * @param stdClass $course The course to object for the report
 * @param stdClass $context The context of the course
 */
function block_socialcomments_extend_navigation_course($navigation, $course, $context) {

    $reportnode = $navigation->get('coursereports');

    if ($reportnode && has_capability('block/socialcomments:viewreport', $context)) {
        $url = new moodle_url('/blocks/socialcomments/report/newsfeed.php', array('courseid' => $course->id));
        $reportnode->add(get_string('socialcommentsreport', 'block_socialcomments'), $url,
            navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }

    if ($reportnode && has_capability('block/socialcomments:pinitems', $context)) {
        $url = new moodle_url('/blocks/socialcomments/pinboard/index.php', array('courseid' => $course->id));
        $reportnode->add(get_string('pinboard', 'block_socialcomments'), $url,
            navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

/**
 * Get user name fields
 */
function block_socialcomments_get_all_user_name_fields() {
    $userfieldsapi = \core_user\fields::for_name();
    $userfields = array_values($userfieldsapi->get_sql('u')->mappings);
    $userfields = implode(",", $userfields);
    return $userfields;
}

/**
 * Get the user picture fields.
 */
function block_socialcomments_get_userpicture_fields() {
    $userfieldsapi = \core_user\fields::for_name()->with_userpic()->including(...([]));
    $userpicturefields = array_values($userfieldsapi->get_sql('u')->mappings);
    $userpicturefields = implode(",", $userpicturefields);
    return $userpicturefields;
}
