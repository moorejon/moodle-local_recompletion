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

require_once($CFG->dirroot . '/lib/grade/grade_item.php');

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
        $user = $DB->get_record('user', ['id' => $event->relateduserid],'*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $event->courseid],'*', MUST_EXIST);

        // Store completion data and course grade.
        if ($user && $user->idnumber && $course && $course->idnumber) {
            $completion = new \stdClass();
            $completion->userid = $user->idnumber;
            $completion->courseid = $course->idnumber;
            $completion->timecompleted = $eventdata->timecompleted;
            $courseitem = \grade_item::fetch_course_item($course->id);
            if ($courseitem) {
                $grade = new \grade_grade(array('itemid' => $courseitem->id, 'userid' => $user->id));
                $finalgrade = $grade->finalgrade;
            } else {
                $finalgrade = null;
            }
            $completion->gradefinal = $finalgrade;
            $completion->timesynced = time();
            $DB->insert_record('local_recompletion_com', $completion);
        }

        // get all of its equivalent courses
        if (!$equivalents = helper::get_course_equivalencies($course->id, true)) {
            return true;
        }

        if (isset($equivalents[$course->id])) {
            unset($equivalents[$course->id]);
        }

        foreach ($equivalents as $equivalent) {
            if (!$autocompletewithequivalent = $DB->get_field('local_recompletion_config', 'value', ['course' => $equivalent->courseid, 'name' => 'autocompletewithequivalent'])) {
                continue;
            }
            if ($completion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalent->courseid])) {
                // Update.
                $data = new \stdClass();
                $data->id = $eventdata->id;
                $data->timecompleted = $eventdata->timecompleted;
                $DB->update_record('course_completions', $data);
            } else {
                // Insert.
                $data = new \stdClass();
                $data->userid = $user->id;
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
        $sql = "SELECT ue.id, GREATEST(ue.timecreated, ue.timestart) as timestart, ue.status FROM {user_enrolments} ue 
                INNER JOIN {enrol} e ON e.id = ue.enrolid
                INNER JOIN {course} c ON c.id = e.courseid
                INNER JOIN {local_recompletion_config} rc2 ON rc2.course = c.id AND rc2.name = 'graceperiod' AND rc2.value > '0'
                WHERE c.id = ?
                AND ue.userid = ?";
        $userenrolments = $DB->get_records_sql($sql, array($data['courseid'], $data['relateduserid']));

        if ($userenrolments && count($userenrolments) == 1) {
            if ($userenrolments[$data['objectid']]->status == ENROL_USER_ACTIVE) {
                $grace = (object) [
                        'userid' => $data['relateduserid'],
                        'courseid' => $data['courseid'],
                        'timestart' => $userenrolments[$data['objectid']]->timestart
                ];
                try {
                    $DB->insert_record('local_recompletion_grace', $grace);
                } catch (\dml_exception $exception) {
                    // Ignore a duplicate.
                }
            }
        }
    }
}
