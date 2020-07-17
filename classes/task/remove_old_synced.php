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
 * Uemove synced items older than 30 days.
 *
 * @package    local_recompletion
 * @copyright  2020 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Remove synced items older than 30 days.
 *
 * @package    local_recompletion
 * @copyright  2020 MLC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_old_synced extends \core\task\scheduled_task {

    protected $configs = array();

    /**
     * Returns the name of this task.
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('removeoldsynced', 'local_recompletion');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $DB;

        $tables = ['local_recompletion_com'];
        $timestamp = strtotime("-30 Days");

        foreach($tables as $table) {
            $DB->delete_records_select($table, 'synced = 1 AND timesynced < ?', [$timestamp]);
        }

        return true;
    }

}
