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
 * Used to clear the recompletion cache
 *
 * @package    local_recompletion
 * @author     David Saylor
 * @copyright  2021 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion\task;

use local_recompletion\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Clear the recompletion cache
 *
 * @package    local_recompletion
 * @author     David Saylor
 * @copyright  2021 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reset_completion_cache extends \core\task\scheduled_task {

    protected $configs = array();

    /**
     * Returns the name of this task.
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('resetcompletioncache', 'local_recompletion');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB;

        $DB->delete_records('local_recompletion_cc_cached');

        return true;
    }


}