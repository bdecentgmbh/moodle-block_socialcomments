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
 * Filter of the comments report: the activities of the chosen section.
 *
 * @module     block_socialcomments/report
 * @copyright  2021 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Load the activities of a section into the activity menu when the section changes.
 *
 * @param {number} courseid
 */
export const init = (courseid) => {
    const section = document.getElementById('id_sectionid');
    const activity = document.getElementById('id_activityid');
    if (!section || !activity) {
        return;
    }
    section.addEventListener('change', () => {
        Ajax.call([{
            methodname: 'block_socialcomments_get_activity_options',
            args: {sectionid: Number(section.value), courseid},
        }])[0].then((response) => {
            activity.innerHTML = response.options;
            return response;
        }).catch(Notification.exception);
    });
    activity.addEventListener('change', () => {
        if (activity.value === '0') {
            section.value = '0';
        }
    });
};
