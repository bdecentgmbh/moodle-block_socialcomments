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
 * Javascript controller for the socialcomments block on report page.
 *
 * @module     block_socialcomments/comments
 * @package    block_socialcomments
 * @copyright  2017 Andreas Wagner.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      3.1
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, ajax, notification) {

    var params = null; // ...contextid, subscribed, commentscount.
    /**
     * Load options for the selected topic.
     *
     * @method loadOptions
     * @param {Object} $selecttopic ID of the selected topic.
     */
    function loadOptions($selecttopic) {

        var topicindex = $selecttopic.val();

        ajax.call([
            {
                methodname: 'block_socialcomments_get_activity_options',
                args: {
                    sectionid: Number(topicindex),
                    courseid: params.courseid
                },
                done: function(response) {
                    $('#id_activityid').html(response.options);
                },
                fail: notification.exception
            }
        ], false);
    }

    /**
     * Action triggered on activity selection.
     *
     * @method onActivitySelected
     * @param {Object} $selecttopic ID of the selected topic.
     */
    function onActivitySelected($selecttopic) {

        var index = $selecttopic.val();

        if (index == 0) {
            $('#id_sectionid').val('0');
        }
    }

    return {
        init: function(initparams) {

            // Params.
            params = initparams;

            $('#id_sectionid').change(function() {
                loadOptions($(this));
            });

            $('#id_activityid').change(function() {
                onActivitySelected($(this));
            });
        }
    };
});
