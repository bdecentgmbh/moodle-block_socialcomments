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
 * Specialised backup task for the socialcomments block.
 *
 * @package    block_socialcomments
 * @subpackage backup-moodle2
 * @copyright  2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/backup_socialcomments_stepslib.php');

/**
 * Class implementing the backup tasks.
 *
 * @copyright  2019 Paul Steffen, EDU-Werkstatt GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_socialcomments_block_task extends backup_block_task {

    /**
     * Define (add) particular settings that each block can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps that each block can have.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_socialcomments_block_structure_step('socialcomments_structure', 'socialcomments.xml'));
    }

    /**
     * Define one array() of fileareas that each block controls.
     */
    public function get_fileareas() {
        return array(); // No associated fileareas.
    }

    /**
     * Define one array() of configdata attributes
     * that need to be processed by the contenttransformer.
     */
    public function get_configdata_encoded_attributes() {
        return array(); // No special handling of configdata.
    }

    /**
     * Encode URLs in content into a transportable form.
     *
     * @param string $content
     * @return string
     */
    static public function encode_content_links($content) {
        return $content; // No special encoding of links required.
    }
}
