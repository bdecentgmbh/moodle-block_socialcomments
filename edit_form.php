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
 * Defines the base class form used by blocks/edit.php to edit block instance configuration.
 * @package   block_socialcomments
 * @copyright 2022 bdecent gmbh <info@bdecent.de>
 * @copyright based on work by 2017 Andreas Wagner, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialcomments_edit_form extends block_edit_form {

    /**
     * Define the custom settings mform.
     * @param object $mform the form being built.
     */
    protected function specific_definition($mform) {

        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));
        $mform->addElement('selectyesno', 'config_hidepins', get_string('config_hidepins', 'block_socialcomments'));
        $mform->setDefault('config_hidepins', 0);
    }
}
