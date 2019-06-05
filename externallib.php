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

use local_iomad_learningpath\companypaths;

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

        $rs = $DB->get_recordset_sql($sql, array_merge($userparams, $courseparams));
        foreach ($rs as $completion) {
            $rawdata[$completion->userid][$completion->course][] = $completion->timecompleted;
        }
        $rs->close();

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



    /**
     * Returns description of update_course_settings() parameters.
     *
     * @return \external_function_parameters
     */
    public static function update_course_settings_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course id for the course to update', VALUE_REQUIRED),
                'settings' => new external_single_structure(
                    array(
                        'enable' => new external_value(PARAM_INT, 'Enable recompletion', VALUE_OPTIONAL),
                        'recompletionduration' => new external_value(PARAM_INT, 'Recompletion period', VALUE_OPTIONAL),
                        'recompletionemailenable' => new external_value(PARAM_INT, 'Send recompletion message', VALUE_OPTIONAL),
                        'notificationstart' => new external_value(PARAM_INT, 'Notification start', VALUE_OPTIONAL),
                        'frequency' => new external_value(PARAM_INT, ' Frequency ', VALUE_OPTIONAL),

                        'deletegradedata' => new external_value(PARAM_INT, 'Delete all grades for the user', VALUE_OPTIONAL),
                        'archivecompletiondata' => new external_value(PARAM_INT, 'Archive completion data', VALUE_OPTIONAL),

                        'scormdata' => new external_value(PARAM_INT, 'SCORM attempts', VALUE_OPTIONAL),
                        'archivescormdata' => new external_value(PARAM_INT, 'Archive old attempts', VALUE_OPTIONAL),

                        'quizdata' => new external_value(PARAM_INT, 'Quiz attempts', VALUE_OPTIONAL),
                        'archivequizdata' => new external_value(PARAM_INT, 'Archive old attempts', VALUE_OPTIONAL),

                        'assigndata' => new external_value(PARAM_INT, 'Assign attempts', VALUE_OPTIONAL),

                        'customcertdata' => new external_value(PARAM_INT, 'Custom certificate', VALUE_OPTIONAL),
                        'archivecustomcertdata' => new external_value(PARAM_INT, 'Archive old attempts', VALUE_OPTIONAL),

                        'recompletionemailsubject' => new external_value(PARAM_RAW, 'Recompletion message subject', VALUE_OPTIONAL),
                        'recompletionemailbody' => new external_value(PARAM_RAW, 'Recompletion message body', VALUE_OPTIONAL),

                        'recompletionremindersubject' => new external_value(PARAM_RAW, 'Recompletion reminder subject', VALUE_OPTIONAL),
                        'recompletionreminderbody' => new external_value(PARAM_RAW, 'Recompletion reminder message body', VALUE_OPTIONAL)
                    )
                )
            )
        );
    }

    /**
     * Update the course settings
     *
     * @param int $courseid the course id
     * @param stdClass $settings The list of settings (currently only pushratingstouserplans).
     * @throws moodle_exception
     */
    public static function update_course_settings($courseid, $settings) {
        global $DB;
        $params = self::validate_parameters(self::update_course_settings_parameters(), array(
            'courseid' => $courseid,
            'settings' => $settings
        ));

        $setnames = array('enable', 'recompletionduration', 'deletegradedata', 'quizdata', 'scormdata', 'archivecompletiondata',
            'archivequizdata', 'archivescormdata', 'recompletionemailenable', 'recompletionemailsubject', 'recompletionemailbody',
            'assigndata', 'customcertdata', 'archivecustomcertdata', 'notificationstart', 'frequency', 'recompletionremindersubject',
            'recompletionreminderbody');

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        $config = $DB->get_records_menu('local_recompletion_config', array('course' => $params['courseid']), '', 'name, value');
        $idmap = $DB->get_records_menu('local_recompletion_config', array('course' => $params['courseid']), '', 'name, id');

        foreach ($setnames as $name) {
            if (isset($params['settings'][$name])) {
                $value = $params['settings'][$name];
            } else {
                if ($name == 'recompletionemailsubject'
                    || $name == 'recompletionemailbody'
                    || $name == 'recompletionremindersubject'
                    || $name == 'recompletionreminderbody') {
                    $value = '';
                } else {
                    $value = 0;
                }
            }
            if (!isset($config[$name]) || $config[$name] <> $value) {
                $rc = new stdclass();
                if (isset($idmap[$name])) {
                    $rc->id = $idmap[$name];
                }
                $rc->name = $name;
                $rc->value = $value;
                $rc->course = $params['courseid'];
                if (empty($rc->id)) {
                    $DB->insert_record('local_recompletion_config', $rc);
                } else {
                    $DB->update_record('local_recompletion_config', $rc);
                }
                if ($name == 'enable' && empty($value)) {
                    // Don't overwrite any other settings when recompletion disabled.
                    break;
                }
            }
        }

        return true;
    }

    /**
     * Returns description of update_course_settings() result value.
     *
     * @return \external_value
     */
    public static function update_course_settings_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }



    /**
     * Returns description of create_completion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function create_completion_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'course' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'timecompleted' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'timeenrolled' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
                'timestarted' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
                'reaggregate' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Update the course competency settings
     *
     * @param int $userid the user id
     * @param int $course the course id
     * @param int $timecompleted
     * @param int $timeenrolled
     * @param int $timestarted
     * @param int $reaggregate
     * @throws moodle_exception
     */
    public static function create_completion($userid, $course, $timecompleted, $timeenrolled = 0, $timestarted = 0, $reaggregate = 0) {
        global $DB;
        $params = self::validate_parameters(self::create_completion_parameters(), array(
            'userid' => $userid,
            'course' => $course,
            'timecompleted' => $timecompleted,
            'timeenrolled' => $timeenrolled,
            'timestarted' => $timestarted,
            'reaggregate' => $reaggregate
        ));

        $context = context_course::instance($params['course']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        $comp = new \stdClass();
        $comp->userid = $params['userid'];
        $comp->course = $params['course'];
        $comp->timecompleted = $params['timecompleted'];
        $comp->timeenrolled = $params['timeenrolled'];
        $comp->timestarted = $params['timestarted'];
        $comp->reaggregate = $params['reaggregate'];

        if ($DB->insert_record('local_recompletion_cc', $comp)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns description of create_completion() result value.
     *
     * @return \external_value
     */
    public static function create_completion_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }



    /**
     * Returns description of update_completion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function update_completion_parameters() {
        return new external_function_parameters(
            array(
                'completions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                            'userid' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'course' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'timecompleted' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'timeenrolled' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'timestarted' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                            'reaggregate' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                        )
                    )
                )
            )
        );
    }

    /**
     * Update course recompletion
     *
     * @param array $completions list of completions
     * @throws moodle_exception
     */
    public static function update_completion($completions) {
        global $DB;
        $params = self::validate_parameters(self::update_completion_parameters(), array('completions' => $completions));

        foreach ($params['completions'] as $data) {
            if (!$recompletion = $DB->get_record('local_recompletion_cc', array('id' => $data['id']))) {
                continue;
            }

            $context = context_course::instance($recompletion->course);
            self::validate_context($context);

            if (!has_capability('local/recompletion:manage', $context)) {
                continue;
            }

            if (!$DB->update_record('local_recompletion_cc', (object)$data)) {
                throw new moodle_exception('unknowncompletion');
            }
        }
        return true;
    }

    /**
     * Returns description of update_completion() result value.
     *
     * @return \external_value
     */
    public static function update_completion_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }



    /**
     * Returns description of delete_completion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function delete_completion_parameters() {
        return new external_function_parameters(
            array(
                'completionid' => new external_value(PARAM_INT, 'ID of Recompletion record'),
            )
        );
    }

    /**
     * Delete course recompletion
     *
     * @param int $completionid the course recommpletion id
     * @throws moodle_exception
     */
    public static function delete_completion($completionid) {
        global $DB;

        // Validate params
        $params = self::validate_parameters(self::delete_completion_parameters(), ['completionid' => $completionid]);

        if (!$recompletion = $DB->get_record('local_recompletion_cc', array('id' => $params['completionid']))) {
            throw new invalid_parameter_exception("Completion with id = $completionid does not exist");
        }

        $context = context_course::instance($recompletion->course);
        self::validate_context($context);
        require_capability('local/recompletion:manage', $context);

        if ($DB->delete_records('local_recompletion_cc', array('id' => $params['completionid']))) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Returns description of delete_completion() result value.
     *
     * @return \external_value
     */
    public static function delete_completion_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }
}