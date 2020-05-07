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
 * Observer
 *
 * @package    local_recompletion
 * @author     Michael Gardener <mgardener@cissq.com>
 * @copyright  2020 Michael Gardener
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * Class observer
 *
 * @package     local_recompletion
 * @copyright   2019 Michael Gardener <mgardener@cissq.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    public static function course_completed(\core\event\course_completed $event) {
        global $DB, $CFG;

        $eventdata = $event->get_record_snapshot('course_completions', $event->objectid);
        $userid = $event->relateduserid;
        $courseid = $event->courseid;

        // get all of its equivalent courses
        if (!$equivalents = helper::get_course_equivalencies($courseid, true)) {
            return true;
        }

        if (isset($equivalents[$courseid])) {
            unset($equivalents[$courseid]);
        }

        foreach ($equivalents as $equivalent) {
            if (!$autocompletewithequivalent = $DB->get_field('local_recompletion_config', 'value', ['course' => $equivalent->courseid, 'name' => 'autocompletewithequivalent'])) {
                continue;
            }
            if ($completion = $DB->get_record('course_completions', ['userid' => $userid, 'course' => $equivalent->courseid])) {
                // Update.
                $data = new \stdClass();
                $data->id = $eventdata->id;
                $data->timecompleted = $eventdata->timecompleted;
                $DB->update_record('course_completions', $data);
            } else {
                // Insert.
                $data = new \stdClass();
                $data->userid = $userid;
                $data->course = $equivalent->courseid;
                $data->timecompleted = $eventdata->timecompleted;
                $data->id = $DB->insert_record('course_completions', $data);
                \core\event\course_completed::create_from_completion($data)->trigger();
            }
        }

        // Clear coursecompletion cache which was added in Moodle 3.2.
        if ($CFG->version >= 2016120500) {
            \cache::make('core', 'coursecompletion')->purge();
        }

        return true;
    }

    /**
     * @param \core\event\user_enrolment_created $event
     *
     * @throws \dml_exception
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB;

        $data = $event->get_data();
        $sql = "SELECT ue.userid FROM {user_enrolments} ue 
                INNER JOIN {enrol} e ON e.id = ue.enrolid
                INNER JOIN {course} c ON c.id = e.courseid
                INNER JOIN {local_recompletion_config} rc ON rc.course = c.id AND rc.name = 'enable' AND rc.value = '1'
                INNER JOIN {local_recompletion_config} rc2 ON rc2.course = c.id AND rc2.name = 'graceperiod' AND rc2.value > '0'
                WHERE ue.id = ?";
        $userid = $DB->get_field_sql($sql, [$data['objectid']]);

        if ($userid) {
            $grace = (object) [
                'userid' => $data['relateduserid'],
                'courseid' => $data['courseid']
            ];
            try {
                $DB->insert_record('local_recompletion_grace', $grace);
            } catch (\dml_exception $exception) {
                // Ignore a duplicate.
            }
        }
    }
}
