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

namespace block_socialcomments;

use moodle_exception;

/**
 * Connection to the updating service of the Deft response block for the comments of a page.
 *
 * Only load this class through {@see local\realtime}: it extends a class of block_deft, which may not be installed.
 *
 * @package   block_socialcomments
 * @copyright 2026 bdecent gmbh <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class socket extends \block_deft\socket {
    /**
     * @var string Component
     */
    protected const COMPONENT = 'block_socialcomments';

    /**
     * Comments belong to course and activity pages.
     */
    public function validate() {
        if (!in_array($this->context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE])) {
            throw new moodle_exception('invalidcontext');
        }
    }
}
