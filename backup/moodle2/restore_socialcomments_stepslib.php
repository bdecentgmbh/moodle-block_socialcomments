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
 * Block socialcomments restore stepslib.
 *
 * @package    block_socialcomments
 * @subpackage backup-moodle2
 * @copyright  2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use block_socialcomments\local\comments_helper;

/**
 * Block socialcomments restore structure step class.
 *
 * Comments, subscriptions and page pins keep the page (course or activity) they belong to. Activities are restored
 * after the blocks of the course page, so their new contexts are only known in {@see self::after_restore()}; until
 * then, the new records point to context 0.
 *
 * @copyright  2019 Paul Steffen, EDU-Werkstatt GmbH
 * @copyright  2026 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_socialcomments_block_structure_step extends restore_structure_step {
    /**
     * Define the structure for restoring a socialcomments block.
     */
    protected function define_structure() {
        $paths = [];

        $paths[] = new restore_path_element('comment', '/block/socialcomments/comment', true);
        $paths[] = new restore_path_element('reply', '/block/socialcomments/comment/replies/reply');
        $paths[] = new restore_path_element('pin', '/block/socialcomments/pins/pin');
        $paths[] = new restore_path_element('subscription', '/block/socialcomments/subscriptions/subscription');
        return $paths;
    }

    /**
     * Restore a comment with its replies.
     *
     * @param array $data Data for comment entries.
     */
    public function process_comment($data) {
        global $DB;
        $data = (object) $data;

        // Every block of a course backs up the comments it shows, so the same comment can come more than once.
        if ($this->get_mappingid('block_socialcomments_cmmnts', $data->id) || !$this->map_user($data)) {
            return;
        }
        // A comment for one group must not become visible to all groups.
        if ($data->groupid && !$data->groupid = $this->get_mappingid('group', $data->groupid)) {
            return;
        }
        $oldid = $data->id;
        $oldcontextid = $data->contextid;
        $data->contextid = 0;
        $data->courseid = $this->get_courseid();
        $newitemid = $DB->insert_record('block_socialcomments_cmmnts', $data);
        $this->set_mapping('block_socialcomments_cmmnts', $oldid, $newitemid, false, null, $oldcontextid);

        foreach ($data->replies['reply'] ?? [] as $reply) {
            $reply = (object) $reply;
            if ($this->map_user($reply)) {
                $reply->commentid = $newitemid;
                $DB->insert_record('block_socialcomments_replies', $reply);
            }
        }
    }

    /**
     * Restore a pin of a page or a comment.
     *
     * @param array $data Data for pin entries.
     */
    public function process_pin($data) {
        global $DB;
        $data = (object) $data;

        if ($this->get_mappingid('block_socialcomments_pins', $data->id) || !$this->map_user($data)) {
            return;
        }
        $oldid = $data->id;
        $oldcontextid = null;
        if ($data->itemtype == comments_helper::PINNED_PAGE) {
            $oldcontextid = $data->itemid;
            $data->itemid = 0;
        } else if (!$data->itemid = $this->get_mappingid('block_socialcomments_cmmnts', $data->itemid)) {
            return;
        }
        $newitemid = $DB->insert_record('block_socialcomments_pins', $data);
        $this->set_mapping('block_socialcomments_pins', $oldid, $newitemid, false, null, $oldcontextid);
    }

    /**
     * Restore a subscription.
     *
     * @param array $data Data for subscription entries.
     */
    public function process_subscription($data) {
        global $DB;
        $data = (object) $data;

        if ($this->get_mappingid('block_socialcomments_subscrs', $data->id) || !$this->map_user($data)) {
            return;
        }
        $oldid = $data->id;
        $oldcontextid = $data->contextid;
        $data->contextid = 0;
        $data->courseid = $this->get_courseid();
        $newitemid = $DB->insert_record('block_socialcomments_subscrs', $data);
        $this->set_mapping('block_socialcomments_subscrs', $oldid, $newitemid, false, null, $oldcontextid);
    }

    /**
     * Map the user of a record.
     *
     * @param stdClass $data with userid
     * @return bool false when the user is not part of the restore.
     */
    protected function map_user(stdClass $data): bool {
        $data->userid = $this->get_mappingid('user', $data->userid);
        return (bool) $data->userid;
    }

    /**
     * Point the restored records to the new pages, drop those of pages that were not restored.
     */
    protected function after_restore() {
        global $DB;

        $tables = [
            'block_socialcomments_cmmnts' => 'contextid',
            'block_socialcomments_subscrs' => 'contextid',
            'block_socialcomments_pins' => 'itemid',
        ];
        foreach ($tables as $table => $field) {
            $mappings = $DB->get_records_select(
                'backup_ids_temp',
                'backupid = :backupid AND itemname = :itemname AND parentitemid IS NOT NULL',
                ['backupid' => $this->get_restoreid(), 'itemname' => $table],
                '',
                'id, newitemid, parentitemid'
            );
            foreach ($mappings as $mapping) {
                if (!$DB->record_exists($table, ['id' => $mapping->newitemid, $field => 0])) {
                    continue;
                }
                if ($contextid = $this->get_mappingid('context', $mapping->parentitemid)) {
                    $DB->set_field($table, $field, $contextid, ['id' => $mapping->newitemid]);
                } else if ($table == 'block_socialcomments_cmmnts') {
                    comments_helper::delete_comments([$mapping->newitemid]);
                } else {
                    $DB->delete_records($table, ['id' => $mapping->newitemid]);
                }
            }
        }
    }
}
