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
 * Local recompletion settings
 *
 * @package    Local Recompletion
 * @copyright  2019 MLC
 * @author     David Saylor <david@mylearningconsultants.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_recompletion', get_string('settings', 'local_recompletion'));

    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext('local_recompletion/recompletionthirdpartyemail',
            get_string('recompletionthirdpartyemail', 'local_recompletion'),
            get_string('recompletionthirdpartyemail_desc', 'local_recompletion'), '', PARAM_EMAIL));

    $options = array();
    for ($i = 1; $i <= 31; $i++) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('local_recompletion/bulknotificationday1',
            get_string('bulknotificationday1', 'local_recompletion'),
            get_string('bulknotificationday1_desc', 'local_recompletion'), 1, $options));
    $settings->add(new admin_setting_configselect('local_recompletion/bulknotificationday2',
            get_string('bulknotificationday2', 'local_recompletion'),
            get_string('bulknotificationday2_desc', 'local_recompletion'), 15, $options));
}


