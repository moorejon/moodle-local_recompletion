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

        $filter = array();
        $userparams = array();
        $courseparams = array();
        $rawdata = array();
        $return = array();

        if (!$params['userids'] && !$params['courseids']) {
            return $return;
        }

        if ($params['userids']) {
            list($userwhere, $userparams) = $DB->get_in_or_equal($params['userids'], SQL_PARAMS_NAMED, 'user');
            $filter[] = "comp.userid {$userwhere}";
        }

        if ($params['courseids']) {
            list($coursewhere, $courseparams) = $DB->get_in_or_equal($params['courseids'], SQL_PARAMS_NAMED, 'cor');
            $filter[] = "comp.course {$coursewhere}";
        }
        $sql = "SELECT comp.*
                  FROM (SELECT * FROM {course_completions} cc WHERE cc.timecompleted > 0 
                  UNION SELECT * FROM {local_recompletion_cc} lr) comp
                  WHERE ".implode(' AND ', $filter)."                 
               ORDER BY comp.timecompleted {$params['sort']}";

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
                        'recompletionduration' => new external_value(PARAM_INT, 'Recompletion period in days', VALUE_OPTIONAL),
                        'recompletionemailenable' => new external_value(PARAM_INT, 'Send recompletion message', VALUE_OPTIONAL),
                        'bulknotification' => new external_value(PARAM_INT, 'Enable bulk notification', VALUE_OPTIONAL),
                        'notificationstart' => new external_value(PARAM_INT, 'Notification start in days prior', VALUE_OPTIONAL),
                        'frequency' => new external_value(PARAM_INT, ' Frequency in days', VALUE_OPTIONAL),

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
                        'recompletionreminderbody' => new external_value(PARAM_RAW, 'Recompletion reminder message body', VALUE_OPTIONAL),
                        'autocompletewithequivalent' => new external_value(PARAM_INT, 'Auto complete with equivalent courses', VALUE_OPTIONAL),
                        'graceperiod' => new external_value(PARAM_INT, 'Grace period in days', VALUE_OPTIONAL)
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
            'assigndata', 'customcertdata', 'archivecustomcertdata', 'bulknotification',  'notificationstart', 'frequency', 'recompletionremindersubject',
            'recompletionreminderbody', 'autocompletewithequivalent', 'graceperiod');

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        $config = $DB->get_records_menu('local_recompletion_config', array('course' => $params['courseid']), '', 'name, value');
        $idmap = $DB->get_records_menu('local_recompletion_config', array('course' => $params['courseid']), '', 'name, id');

        $daybasedvariables = array('recompletionduration', 'notificationstart', 'frequency', 'graceperiod');
        foreach ($setnames as $name) {
            if (isset($params['settings'][$name])) {
                $value = $params['settings'][$name];
            } else {
                $value = null;
            }
            if (!isset($config[$name]) || (!is_null($value) && $config[$name] <> $value)) {
                if (in_array($name, $daybasedvariables)) {
                    $value = $value * 86400;
                }
                if (is_null($value)) {
                    if ($name == 'recompletionemailsubject'
                            || $name == 'recompletionemailbody'
                            || $name == 'recompletionremindersubject'
                            || $name == 'recompletionreminderbody') {
                        $value = '';
                    } else {
                        $value = 0;
                    }
                }
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
     * Create recompletion
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


    /**
     * Returns description of get_course_settings() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_course_settings_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
            )
        );
    }

    /**
     * Get course settings
     *
     * @param int $courseid the course id
     * @throws moodle_exception
     */
    public static function get_course_settings($courseid) {
        global $DB;

        // Validate params
        $params = self::validate_parameters(self::get_course_settings_parameters(), ['courseid' => $courseid]);

        $setnames = array('enable', 'recompletionduration', 'deletegradedata', 'quizdata', 'scormdata', 'archivecompletiondata',
            'archivequizdata', 'archivescormdata', 'recompletionemailenable', 'recompletionemailsubject', 'recompletionemailbody',
            'assigndata', 'customcertdata', 'archivecustomcertdata', 'bulknotification',  'notificationstart', 'frequency', 'recompletionremindersubject',
            'recompletionreminderbody', 'autocompletewithequivalent');

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        $settings = array();
        foreach ($setnames as $setname) {
            $settingvalue = $DB->get_field('local_recompletion_config', 'value', array('course' => $params['courseid'], 'name' => $setname));
            if ($settingvalue !== false) {
                $settings[$setname] = $settingvalue;
            }
        }

        return $settings;
    }

    /**
     * Returns description of get_course_settings() result value.
     *
     * @return \external_value
     */
    public static function get_course_settings_returns() {
        return new external_single_structure(
            array(
                'enable' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'recompletionduration' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'deletegradedata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'quizdata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'scormdata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'archivecompletiondata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'archivequizdata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'archivescormdata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'recompletionemailenable' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'recompletionemailsubject' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'recompletionemailbody' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'assigndata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'customcertdata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'archivecustomcertdata' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'bulknotification' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'notificationstart' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'frequency' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
                'recompletionremindersubject' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'recompletionreminderbody' => new external_value(PARAM_RAW, '', VALUE_OPTIONAL),
                'autocompletewithequivalent' => new external_value(PARAM_INT, '', VALUE_OPTIONAL),
            )
        );
    }


    /**
     * Returns description of get_recompletions() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_recompletions_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Get course recompletions
     *
     * @param int $courseid the course id
     * @throws moodle_exception
     */
    public static function get_recompletions($courseid, $userid = 0) {
        global $DB;

        // Validate params
        $params = self::validate_parameters(self::get_recompletions_parameters(), ['courseid' => $courseid, 'userid' => $userid]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        if ($params['userid']) {
            $rs = $DB->get_recordset('local_recompletion_cc', array('course' => $params['courseid'], 'userid' => $params['userid']));
        } else {
            $rs = $DB->get_recordset('local_recompletion_cc', array('course' => $params['courseid']));
        }

        $return = array('completions' => array());

        foreach ($rs as $recompletion) {
            $return['completions'][] = (array) $recompletion;
        }

        $rs->close();

        return $return;
    }

    /**
     * Returns description of get_recompletions() result value.
     *
     * @return \external_value
     */
    public static function get_recompletions_returns() {
        return new external_single_structure(
            array(
                'completions'   => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Record ID'),
                            'userid' => new external_value(PARAM_INT, 'User ID'),
                            'course' => new external_value(PARAM_INT, 'Course ID'),
                            'timeenrolled' => new external_value(PARAM_INT, 'Timestamp for course enrolment'),
                            'timestarted' => new external_value(PARAM_INT, 'Timestamp for course star'),
                            'timecompleted' => new external_value(PARAM_INT, 'Timestamp for course completetion'),
                            'reaggregate' => new external_value(PARAM_INT, 'Timestamp for course reaggregate')
                        )
                    )
                )
            )
        );
    }


    /**
     * Returns description of create_core_completion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function create_core_completion_parameters() {
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
     * Create course completion
     *
     * @param int $userid the user id
     * @param int $course the course id
     * @param int $timecompleted
     * @param int $timeenrolled
     * @param int $timestarted
     * @param int $reaggregate
     * @throws moodle_exception
     */
    public static function create_core_completion($userid, $course, $timecompleted, $timeenrolled = 0, $timestarted = 0, $reaggregate = 0) {
        global $DB;
        $params = self::validate_parameters(self::create_core_completion_parameters(), array(
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

        if ($comp->id = $DB->insert_record('course_completions', $comp)) {
            \core\event\course_completed::create_from_completion($comp)->trigger();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns description of create_core_completion() result value.
     *
     * @return \external_value
     */
    public static function create_core_completion_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }

    /**
     * Returns description of get_core_course_completions() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_core_course_completions_parameters() {
        return new external_function_parameters(
            array(
                'course' => new external_value(PARAM_INT, 'User ID', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'Course ID', VALUE_DEFAULT, 0)
            )
        );
    }

    /**
     * Get core course completions
     *
     * @param int $userid the user id
     * @param int $course the course id
     * @throws moodle_exception
     */
    public static function get_core_course_completions($course, $userid = 0) {
        global $DB;
        $params = self::validate_parameters(self::get_core_course_completions_parameters(), array(
            'course' => $course,
            'userid' => $userid
        ));

        $context = context_course::instance($params['course']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        if ($params['userid']) {
            $rs = $DB->get_recordset('course_completions', array('course' => $params['course'], 'userid' => $params['userid']));
        } else {
            $rs = $DB->get_recordset('course_completions', array('course' => $params['course']));
        }

        $return = array('completions' => array());

        foreach ($rs as $completion) {
            $return['completions'][] = (array) $completion;
        }

        $rs->close();

        return $return;
    }

    /**
     * Returns description of get_core_course_completions() result value.
     *
     * @return \external_value
     */
    public static function get_core_course_completions_returns() {
        return new external_single_structure(
            array(
                'completions'   => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Record ID'),
                            'userid' => new external_value(PARAM_INT, 'User ID'),
                            'course' => new external_value(PARAM_INT, 'Course ID'),
                            'timeenrolled' => new external_value(PARAM_INT, 'Timestamp for course enrolment'),
                            'timestarted' => new external_value(PARAM_INT, 'Timestamp for course star'),
                            'timecompleted' => new external_value(PARAM_INT, 'Timestamp for course completetion'),
                            'reaggregate' => new external_value(PARAM_INT, 'Timestamp for course reaggregate')
                        )
                    )
                )
            )
        );
    }


    /**
     * Returns description of update_core_completion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function update_core_completion_parameters() {
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
     * Update course completion
     *
     * @param array $completions list of completions
     * @throws moodle_exception
     */
    public static function update_core_completion($completions) {
        global $DB;
        $params = self::validate_parameters(self::update_core_completion_parameters(), array('completions' => $completions));

        foreach ($params['completions'] as $data) {
            if (!$corecompletion = $DB->get_record('course_completions', array('id' => $data['id']))) {
                continue;
            }

            $context = context_course::instance($corecompletion->course);
            self::validate_context($context);

            if (!has_capability('local/recompletion:manage', $context)) {
                continue;
            }

            if (!$DB->update_record('course_completions', (object)$data)) {
                throw new moodle_exception('unknowncompletion');
            }
        }
        return true;
    }

    /**
     * Returns description of update_core_completion() result value.
     *
     * @return \external_value
     */
    public static function update_core_completion_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }


    /**
     * Returns description of delete_core_completion() parameters.
     *
     * @return \external_function_parameters
     */
    public static function delete_core_completion_parameters() {
        return new external_function_parameters(
            array(
                'completionid' => new external_value(PARAM_INT, 'ID of Recompletion record'),
            )
        );
    }

    /**
     * Delete course completion
     *
     * @param int $completionid the course recommpletion id
     * @throws moodle_exception
     */
    public static function delete_core_completion($completionid) {
        global $DB;

        // Validate params
        $params = self::validate_parameters(self::delete_core_completion_parameters(), ['completionid' => $completionid]);

        if (!$corecompletion = $DB->get_record('course_completions', array('id' => $params['completionid']))) {
            throw new invalid_parameter_exception("Completion with id = $completionid does not exist");
        }

        $context = context_course::instance($corecompletion->course);
        self::validate_context($context);
        require_capability('local/recompletion:manage', $context);

        if ($DB->delete_records('course_completions', array('id' => $params['completionid']))) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Returns description of delete_core_completion() result value.
     *
     * @return \external_value
     */
    public static function delete_core_completion_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }


    /**
     * Returns description of create_course_equivalent() parameters.
     *
     * @return \external_function_parameters
     */
    public static function create_course_equivalent_parameters() {
        return new external_function_parameters(
            array(
                'courseoneid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'coursetwoid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'unidirectional' => new external_value(PARAM_BOOL, '', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Create course equivalent
     *
     * @param int $courseoneid the course one id
     * @param int $coursetwoid the course two id
     */
    public static function create_course_equivalent($courseoneid, $coursetwoid, $unidirectional) {
        global $DB;
        $params = self::validate_parameters(self::create_course_equivalent_parameters(), array(
            'courseoneid' => $courseoneid,
            'coursetwoid' => $coursetwoid,
            'unidirectional' => $unidirectional,
        ));

        $courseone = $DB->get_record('course', array('id' => $params['courseoneid']), "*", MUST_EXIST);
        $coursetwo = $DB->get_record('course', array('id' => $params['coursetwoid']), "*", MUST_EXIST);

        $context = context_course::instance($params['courseoneid']);
        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        if ($equivalents = \local_recompletion\helper::get_course_equivalencies($params['courseoneid'])) {
            if (in_array($params['coursetwoid'], array_keys($equivalents))) {
                return true;
            }
        }

        $obj = new \stdClass();
        $obj->courseoneid = $params['courseoneid'];
        $obj->coursetwoid = $params['coursetwoid'];
        $obj->unidirectional = $params['unidirectional'];

        if ($DB->insert_record('local_recompletion_equiv', $obj)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns description of create_course_equivalent() result value.
     *
     * @return \external_value
     */
    public static function create_course_equivalent_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }




    /**
     * Returns description of delete_course_equivalent() parameters.
     *
     * @return \external_function_parameters
     */
    public static function delete_course_equivalent_parameters() {
        return new external_function_parameters(
            array(
                'courseoneid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
                'coursetwoid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Delete course equivalent
     *
     * @param int $courseoneid the course one id
     * @param int $coursetwoid the course two id
     * @throws moodle_exception
     */
    public static function delete_course_equivalent($courseoneid, $coursetwoid) {
        global $DB;

        $params = self::validate_parameters(self::create_course_equivalent_parameters(), array(
            'courseoneid' => $courseoneid,
            'coursetwoid' => $coursetwoid,
        ));

        $context = context_course::instance($params['courseoneid']);

        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        $return1  = $DB->delete_records('local_recompletion_equiv', array('courseoneid' => $params['courseoneid'], 'coursetwoid' => $params['coursetwoid']));
        $return2  =  $DB->delete_records('local_recompletion_equiv', array('coursetwoid' => $params['courseoneid'], 'courseoneid' => $params['coursetwoid']));

        if ($return1 || $return2) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns description of delete_course_equivalent() result value.
     *
     * @return \external_value
     */
    public static function delete_course_equivalent_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }

    /**
     * Returns description of get_course_equivalencies() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_course_equivalencies_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, '', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Get course equivalencies
     *
     * @param int $courseid the course id
     * @throws moodle_exception
     */
    public static function get_course_equivalencies($courseid) {
        global $DB;

        $params = self::validate_parameters(self::get_course_equivalencies_parameters(), array(
            'courseid' => $courseid
        ));

        $context = context_course::instance($params['courseid']);

        self::validate_context($context);

        if (!has_capability('local/recompletion:manage', $context)) {
            return false;
        }

        if ($equivalents = \local_recompletion\helper::get_course_equivalencies($params['courseid'])) {
            if (in_array($params['coursetwoid'], array_keys($equivalents))) {
                return true;
            }
        }
        $return = array();
        foreach ($equivalents as $equivalent) {
            if ($course = $DB->get_record('course', array('id' => $equivalent->courseid))) {
                $return[] = [
                    'id' => $course->id,
                    'fullname' => $course->fullname,
                    'shortname' => $course->shortname
                ];
            }
        }

        return $return;
    }

    /**
     * Returns description of get_course_equivalencies() result value.
     *
     * @return \external_value
     */
    public static function get_course_equivalencies_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'Course ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'Course fullname'),
                    'shortname' => new external_value(PARAM_TEXT, 'Course shortname')
                )
            )
        );
    }

    /**
     * Returns description of get_out_of_compliants() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_out_of_compliants_parameters() {
        return new external_function_parameters(
            array(
                'synced' => new external_value(PARAM_INT, 'Synced', VALUE_DEFAULT, 0),
                'limit' => new external_value(PARAM_INT, 'Synced', VALUE_DEFAULT, 1000)
            )
        );
    }

    /**
     * Get course recompletions
     *
     * @param int $synced Synced or not
     * @param int $limit Number of record limit: Zero means no limit
     * @throws moodle_exception
     */
    public static function get_out_of_compliants($synced = 0, $limit = 1000) {
        global $DB;

        // Validate params
        $params = self::validate_parameters(self::get_out_of_compliants_parameters(),
            ['synced' => $synced, 'limit' => $limit]);

        $context = context_system::instance();
        self::validate_context($context);

        $rs = $DB->get_recordset('local_recompletion_outcomp', array('synced' => $params['synced']), '', '*', 0, $params['limit']);

        $return = array('outofcompliants' => array());

        foreach ($rs as $rec) {
            $return['outofcompliants'][] = (array) $rec;
        }

        $rs->close();

        return $return;
    }

    /**
     * Returns description of get_out_of_compliants() result value.
     *
     * @return \external_value
     */
    public static function get_out_of_compliants_returns() {
        return new external_single_structure(
            array(
                'outofcompliants'   => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Record ID'),
                            'userid' => new external_value(PARAM_TEXT, 'User Idnumber'),
                            'courseid' => new external_value(PARAM_TEXT, 'Course Idnumber'),
                            'timesynced' => new external_value(PARAM_INT, 'Timestamp for sync'),
                            'synced' => new external_value(PARAM_INT, 'Is it synced')
                        )
                    )
                )
            )
        );
    }


    /**
     * Returns description of mark_out_of_compliants_parameters() parameters.
     *
     * @return \external_function_parameters
     */
    public static function mark_out_of_compliants_parameters() {
        return new external_function_parameters(
            array(
                'ids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Record IDs'), 'An array of IDs', VALUE_DEFAULT, array()
                )
            )
        );
    }

    /**
     * Get Course completions
     *
     * @param array $ids An array of record IDs
     * @return array of course completions
     * @throws moodle_exception
     */
    public static function mark_out_of_compliants($ids) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(
            self::mark_out_of_compliants_parameters(),
            array('ids' => $ids)
        );

        if (!$params['ids']) {
            return false;
        }

        list($insql, $params) = $DB->get_in_or_equal($params['ids'], SQL_PARAMS_NAMED, 'rec');

        $sql = "SELECT o.*
                  FROM {local_recompletion_outcomp} o
                  WHERE o.id {$insql}";

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $data) {
            $rec = new \stdClass();
            $rec->id = $data->id;
            $rec->synced = 1;

            if (!$DB->update_record('local_recompletion_outcomp', $rec)) {
                return false;
            }
        }
        $rs->close();

        return true;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function mark_out_of_compliants_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }

    /**
     * Returns description of get_user_compliance_rate_parameters() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_user_compliance_rate_parameters() {
        return new external_function_parameters(
            array(
                'userid' => new external_value(PARAM_INT, '', VALUE_REQUIRED),
            )
        );
    }

    /**
     * Get Course completions
     *
     * @param array $userid User ID
     * @return array of course completions
     * @throws moodle_exception
     */
    public static function get_user_compliance_rate($userid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(
            self::get_user_compliance_rate_parameters(),
            array('userid' => $userid)
        );

        $return = [
            'complete' => 0,
            'comingdue' => 0,
            'expired' => 0,
        ];

        if ($courses = enrol_get_all_users_courses($params['userid'], true)) {
            $now = time();
            foreach ($courses as $course) {
                $completionenabled = $DB->get_field('course', 'enablecompletion', array('id' => $course->id));
                if (!$completionenabled) {
                    continue;
                }
                $handler = \core_course\customfield\course_handler::get_handler('core_course', 'course');
                $datas = $handler->get_instance_data($course->id, true);
                foreach ($datas as $data) {
                    if ($data->get_field()->get('shortname') == 'course_tied_to_compliance') {
                        $tiedtocompliance = $data->get_value();
                        break;
                    }
                }
                if (!$tiedtocompliance) {
                    continue;
                }
                $completion = $DB->get_record('local_recompletion_cc_cached', ['userid' => $params['userid'], 'courseid' => $course->id]);
                $duedate = \local_recompletion\helper::get_user_course_due_date($params['userid'], $course->id, true, true);
                $notificationstart = \local_recompletion\helper::get_user_course_notificationstart_date($params['userid'], $course->id, true);
                if ($duedate) {
                    if ($now >= $duedate) {
                        $return['expired']++;
                    } else if (($notificationstart && $now >= $notificationstart) || empty($completion)) {
                        $return['comingdue']++;
                    } else {
                        $return['complete']++;
                    }
                } else if ($completion) {
                    $return['complete']++;
                } else {
                    $return['expired']++;
                }
            }
        }

        return $return;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_user_compliance_rate_returns() {
        return new external_single_structure(
            array(
                'complete' => new external_value(PARAM_INT, 'The count of courses that are complete.'),
                'comingdue' => new external_value(PARAM_INT, 'The count of courses that are coming due.'),
                'expired' => new external_value(PARAM_INT, 'The count of courses that are expired.'),
            )
        );
    }

    /**
     * Returns description of get_completions() parameters.
     *
     * @return \external_function_parameters
     */
    public static function get_completions_parameters() {
        return new external_function_parameters(
                [
                        'synced' => new external_value(PARAM_INT, 'Synced', VALUE_DEFAULT, 0),
                        'limit' => new external_value(PARAM_INT, 'Limit', VALUE_DEFAULT, 1000)
                ]
        );
    }

    /**
     * Get course recompletions
     *
     * @param int $synced Synced or not
     * @param int $limit Number of record limit: Zero means no limit
     * @throws moodle_exception
     */
    public static function get_completions($synced = 0, $limit = 1000) {
        global $DB;

        // Validate params
        $params = self::validate_parameters(self::get_completions_parameters(),
                ['synced' => $synced, 'limit' => $limit]);

        $context = context_system::instance();
        self::validate_context($context);

        $rs = $DB->get_recordset('local_recompletion_com', ['synced' => $params['synced']], '', '*', 0, $params['limit']);

        $return = ['completions' => []];

        foreach ($rs as $rec) {
            $return['completions'][] = (array) $rec;
        }

        $rs->close();

        return $return;
    }

    /**
     * Returns description of get_completions() result value.
     *
     * @return \external_value
     */
    public static function get_completions_returns() {
        return new external_single_structure(
                [
                        'completions'   => new external_multiple_structure(
                                new external_single_structure(
                                        [
                                                'id' => new external_value(PARAM_INT, 'Record ID'),
                                                'userid' => new external_value(PARAM_TEXT, 'User Idnumber'),
                                                'courseid' => new external_value(PARAM_TEXT, 'Course Idnumber'),
                                                'gradefinal' => new external_value(PARAM_FLOAT, 'Course grade'),
                                                'timecompleted' => new external_value(PARAM_INT, 'Course completion timestamp'),
                                                'timesynced' => new external_value(PARAM_INT, 'Timestamp for sync'),
                                                'synced' => new external_value(PARAM_INT, 'Is it synced')
                                        ]
                                )
                        )
                ]
        );
    }


    /**
     * Returns description of mark_out_of_compliants_parameters() parameters.
     *
     * @return \external_function_parameters
     */
    public static function mark_completions_synced_parameters() {
        return new external_function_parameters(
                [
                        'ids' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Record IDs'), 'An array of IDs', VALUE_DEFAULT, []
                        )
                ]
        );
    }

    /**
     * Get Course completions
     *
     * @param array $ids An array of record IDs
     * @return bool
     * @throws moodle_exception
     */
    public static function mark_completions_synced($ids) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');

        $params = self::validate_parameters(
                self::mark_completions_synced_parameters(),
                ['ids' => $ids]
        );

        if (!$params['ids']) {
            return false;
        }

        list($insql, $params) = $DB->get_in_or_equal($params['ids'], SQL_PARAMS_NAMED, 'rec');

        $sql = "SELECT o.*
                  FROM {local_recompletion_com} o
                  WHERE o.id {$insql}";

        $rs = $DB->get_recordset_sql($sql, $params);
        $synctime = time();

        foreach ($rs as $data) {
            $rec = new \stdClass();
            $rec->id = $data->id;
            $rec->synced = 1;
            $rec->timesynced = $synctime;

            if (!$DB->update_record('local_recompletion_com', $rec)) {
                return false;
            }
        }
        $rs->close();

        return true;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function mark_completions_synced_returns() {
        return new external_value(PARAM_BOOL, 'True if the update was successful.');
    }
}
