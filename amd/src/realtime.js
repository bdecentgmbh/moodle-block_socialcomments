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
 * Real-time updates of the comments through the updating service of the Deft response block.
 *
 * Only loaded when block_deft is installed and its service is set up.
 *
 * @module     block_socialcomments/realtime
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Log from 'core/log';
import Notification from 'core/notification';
import SocketBase from 'block_deft/socket';
import {CHANGED} from 'block_socialcomments/comments';
import {debounce} from 'core/utils';

/**
 * Connection that renews its token through the block, not through block_deft (which expects a block context).
 */
class Socket extends SocketBase {
    /**
     * Renew the token.
     *
     * @param {number} contextid Context of the page
     */
    renewToken(contextid) {
        Ajax.call([{
            methodname: 'block_socialcomments_renew_token',
            args: {contextid},
            done: (response) => {
                Log.debug('Social comments: reconnecting');
                this.connect(contextid, response.token);
            },
            fail: Notification.exception,
        }]);
    }
}

/**
 * Listen to changes of the comments of a page.
 *
 * @param {string} uniqid Id of the block content
 * @param {number} contextid Context of the page
 * @param {string} token Token of the updating service
 * @param {number} throttle Minimum time between two updates in ms
 */
export const init = (uniqid, contextid, token, throttle) => {
    const root = document.getElementById(uniqid);
    if (!root) {
        return;
    }
    const changed = debounce(() => root.dispatchEvent(new CustomEvent(CHANGED)), throttle || 100);
    let connected = false;
    new Socket(contextid, token).subscribe((e) => {
        // The first connection needs no update; a reconnection may have missed some.
        if (e.type === 'message' || connected) {
            changed();
        }
        connected = true;
    });
};
