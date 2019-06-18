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
 * Web service functions for recompletion plugin.
 *
 * @package    local_recompletion
 * @copyright  2019 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$functions = array(
    'local_recompletion_get_course_completions' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'get_course_completions',
        'description'   => 'Returns course completions',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_update_course_settings' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'update_course_settings',
        'description'   => 'Update the course recompletion settings',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_create_completion' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'create_completion',
        'description'   => 'Create the course recompletion',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_update_completion' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'update_completion',
        'description'   => 'Update the course recompletion',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_delete_completion' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'delete_completion',
        'description'   => 'Delete the course recompletion',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_get_course_settings' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'get_course_settings',
        'description'   => 'Get course recompletion settings',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_get_recompletions' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'get_recompletions',
        'description'   => 'Get course recompletions',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_create_core_completion' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'create_core_completion',
        'description'   => 'Create the course recompletion',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_update_core_completion' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'update_core_completion',
        'description'   => 'Update the course recompletion',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_delete_core_completion' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'delete_core_completion',
        'description'   => 'Delete the course recompletion',
        'type'          => 'write',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
    'local_recompletion_get_core_course_completions' => array(
        'classname'     => 'local_recompletion_external',
        'methodname'    => 'get_core_course_completions',
        'description'   => 'Get records from the core course completion table',
        'type'          => 'read',
        'services'      => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
        'ajax'          => true,
    ),
);