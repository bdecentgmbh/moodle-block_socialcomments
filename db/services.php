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
 * Socialcomments external functions and service definitions.
 *
 * @package   block_socialcomments
 * @copyright 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = array(
    'block_socialcomments_save_comment' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'save_comment',
        'description' => 'Create or update a comment',
        'capabilities' => 'block/socialcomments:postcomment',
        'type' => 'write',
        'ajax' => true
    ),
    'block_socialcomments_delete_comment' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'delete_comment',
        'description' => 'Delete a comment',
        'capabilities' => 'block/socialcomments:deleteowncomments, block/socialcomments:deletecomments',
        'type' => 'write',
        'ajax' => true
    ),
    'block_socialcomments_set_pinned' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'set_pinned',
        'description' => 'Pin a item',
        'capabilities' => 'block/socialcomments:pinitems',
        'type' => 'write',
        'ajax' => true
    ),
    'block_socialcomments_set_subscribed' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'set_subscribed',
        'description' => 'Subscribe for a context',
        'capabilities' => 'block/socialcomments:subscribe',
        'type' => 'write',
        'ajax' => true
    ),
    'block_socialcomments_get_commentspage' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'get_commentspage',
        'description' => 'Get a content page with comments',
        'capabilities' => 'block/socialcomments:view',
        'type' => 'read',
        'ajax' => true
    ),
    'block_socialcomments_save_reply' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'save_reply',
        'description' => 'Create or update a reply',
        'capabilities' => 'block/socialcomments:postcomment',
        'type' => 'write',
        'ajax' => true
    ),
    'block_socialcomments_delete_reply' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'delete_reply',
        'description' => 'Delete a reply',
        'capabilities' => 'block/socialcomments:deleteownreplies, block/socialcomments:deletereplies',
        'type' => 'write',
        'ajax' => true
    ),
    'block_socialcomments_get_activity_options' => array(
        'classname' => 'block_socialcomments_external',
        'methodname' => 'get_activity_options',
        'description' => 'Get options for activity select',
        'capabilities' => 'block/socialcomments:viewreport',
        'type' => 'read',
        'ajax' => true
    ),

);
