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
 * Web service declarations
 *
 * @package    local_recompletion
 * @copyright  2019 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class local_recompletion_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_course_completions_parameters() {
        return new external_function_parameters(
            array(
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'), 'An array of user IDs', VALUE_DEFAULT, array()
                ),
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user ID'), 'An array of course IDs', VALUE_DEFAULT, array()
                ),
                'sort' => new external_value(PARAM_ALPHA, 'The direction of the order: \'ASC\' or \'DESC\'', VALUE_DEFAULT, 'ASC'),
                'limit' => new external_value(PARAM_INT, 'Number of records to return', VALUE_DEFAULT, 0)

            )
        );
    }

    /**
     * Get Course completions
     *
     * @param array $userids An array of user IDs
     * @param array $courseids An array of course IDs
     * @param string $sort The direction of the order.
     * @param int $limit Number of records to return.
     * @return array of course completions
     * @throws moodle_exception
     */
    public static function get_course_completions($userids, $courseids, $sort = 'ASC', $limit = 0) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(
            self::get_course_completions_parameters(),
            array('userids' => $userids, 'courseids' => $courseids, 'sort' => $sort, 'limit' => $limit)
        );

        list($userwhere, $userparams) = $DB->get_in_or_equal($params['userids'], SQL_PARAMS_NAMED, 'user');
        list($coursewhere, $courseparams) = $DB->get_in_or_equal($params['courseids'], SQL_PARAMS_NAMED, 'cor');

        $sql = "SELECT comp.*
                  FROM (SELECT * FROM {course_completions} cc WHERE cc.timecompleted > 0 
                  UNION SELECT * FROM {local_recompletion_cc} lr) comp
                  WHERE comp.userid {$userwhere} 
                    AND comp.course {$coursewhere}
               ORDER BY comp.timecompleted {$params['sort']}";

        $rawdata = array();
        $return = array();
        if ($completions = $DB->get_records_sql($sql, array_merge($userparams, $courseparams))) {
            foreach ($completions as $completion) {
                $rawdata[$completion->userid][$completion->course][] = $completion->timecompleted;
            }
        }

        foreach ($rawdata as $userid => $courses) {

            $userdata = array();
            $userdata['userid'] = $userid;

            foreach ($courses as $courseid => $course) {
                if (!has_capability('local/recompletion:manage', context_course::instance($courseid))) {
                    $userdata = null;
                    break;
                }
                $coursedata = array();
                $coursedata['courseid'] = $courseid;

                $counter = 0;
                foreach ($course as $timecompleted) {
                    $counter++;
                    if ($params['limit'] && $params['limit'] < $counter) {
                        break;
                    }
                    $completiondata = array();
                    $completiondata['timecompleted'] = $timecompleted;
                    $coursedata['completions'][] = $completiondata;
                }

                if (!empty($userdata)) {
                    $userdata['courses'][] = $coursedata;
                }
            }

            $return[] = $userdata;
        }

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_course_completions_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'userid' => new external_value(PARAM_INT, 'ID of the user'),
                    'courses'   => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'courseid' => new external_value(PARAM_INT,   'Course ID'),
                                'completions'   => new external_multiple_structure(
                                    new external_single_structure(
                                        array(
                                            'timecompleted' => new external_value(PARAM_INT,   'Timestamp for course completetion')
                                        )
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );
    }
}
