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

namespace block_socialcomments\local;

use block_socialcomments\socket;

/**
 * Real-time updates of the comments through the updating service of the Deft response block (block_deft).
 *
 * The service only tells the open pages that something changed; they then load the comments with the permissions of
 * their user. Nothing of the comments goes to the service.
 *
 * @package   block_socialcomments
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class realtime {
    /**
     * Whether comments update in real time: the setting is on, block_deft is installed and uses the Deft service.
     *
     * @return bool
     */
    public static function enabled(): bool {
        global $DB;
        static $registered = null;

        if (
            !get_config('block_socialcomments', 'realtime')
            || !class_exists(\block_deft\socket::class)
            || get_config('block_deft', 'enableupdating') != 1
        ) {
            return false;
        }
        // The site must be registered with the service ("Activate" in the settings of block_deft).
        $registered = $registered ?? (bool) $DB->get_field_select('lti_types', 'clientid', "tooldomain = 'deftly.us'");
        return $registered;
    }

    /**
     * The token for a page to listen to changes of its comments.
     *
     * @param \context $context of the course or activity.
     * @return string|null null when real-time updates are off or the service is not available.
     */
    public static function get_token(\context $context): ?string {
        if (!self::enabled()) {
            return null;
        }
        try {
            return (new socket($context))->get_token() ?: null;
        } catch (\moodle_exception $e) {
            return null;
        }
    }

    /**
     * Tell the open pages that the comments of a page changed.
     *
     * @param \context $context of the course or activity.
     */
    public static function notify(\context $context): void {
        if (!self::enabled()) {
            return;
        }
        try {
            (new socket($context))->dispatch();
        } catch (\moodle_exception $e) {
            // Without the service, the comments show up on the next page load.
            debugging('Social comments: the Deft service is not available: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
