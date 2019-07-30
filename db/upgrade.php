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
 * Plugin upgrade steps are defined here.
 *
 * @package   block_socialcomments
 * @copyright 2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute block_socialcomments upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_socialcomments_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2019072601) {

        $table = new xmldb_table('block_scomments_comments');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'block_socialcomments_cmmnts');
        }

        $table = new xmldb_table('block_scomments_subscripts');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'block_socialcomments_subscrs');
        }

        $table = new xmldb_table('block_scomments_pins');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'block_socialcomments_pins');
        }

        $table = new xmldb_table('block_scomments_replies');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'block_socialcomments_replies');
        }

        // Socialcomments savepoint reached.
        upgrade_plugin_savepoint(true, 2019072601, 'block', 'socialcomments');
    }
    return true;
}
