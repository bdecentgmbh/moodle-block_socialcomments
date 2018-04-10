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
 * Settings for the socialcomments block
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_configtext('block_socialcomments/commentsperpage',
        new lang_string('commentsperpage', 'block_socialcomments'),
        new lang_string('commentsperpagedesc', 'block_socialcomments'), 10, PARAM_INT));

    $settings->add(new admin_setting_configtext('block_socialcomments/limitreplies',
        new lang_string('limitreplies', 'block_socialcomments'),
        new lang_string('limitrepliesdesc', 'block_socialcomments'), 10, PARAM_INT));

    $settings->add(new admin_setting_configtext('block_socialcomments/reportperpage',
        new lang_string('reportperpage', 'block_socialcomments'),
        new lang_string('reportperpagedesc', 'block_socialcomments'), 25, PARAM_INT));

    $url = new moodle_url('/admin/tool/task/scheduledtasks.php');
    $link = html_writer::link($url, get_string('pluginname', 'tool_task'), array('target' => '_blank'));

    $settings->add(new admin_setting_configtext('block_socialcomments/userspercron',
        new lang_string('userspercron', 'block_socialcomments'),
        new lang_string('userspercrondesc', 'block_socialcomments', $link), 0, PARAM_INT));

    $choices = \block_socialcomments\local\comments_helper::get_digest_type_menu();
    $settings->add(new admin_setting_configselect('block_socialcomments/digesttype',
        new lang_string('digesttype', 'block_socialcomments'),
        new lang_string('digesttypedesc', 'block_socialcomments', $link), 1, $choices));

}