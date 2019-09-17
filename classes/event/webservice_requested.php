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
 * Plugin event classes are defined here.
 *
 * @package     local_recompletion
 * @copyright   2019 Michael Gardener <mgardener@cissq.com>>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The webservice_requested event class.
 *
 * @package    local_recompletion
 * @copyright  2019 Michael Gardener <mgardener@cissq.com>>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservice_requested extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        //$this->data['objecttable'] = 'local_recompletion_config';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('webservicerequest', 'local_recompletion');
    }

    /**
     * Returns non-localised event description with id's for admin use only.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->relateduserid' was received a reminder regarding the course with id '$this->courseid'";
    }


}
