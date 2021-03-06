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
 * Used to check for users that need to recomple.
 *
 * @package    local_recompletion
 * @author     Michael Gardener <mgardener@cissq.com>
 * @copyright  2019 Michael Gardener
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

/**
 * Helper.
 *
 * @package    local_recompletion
 * @author     Michael Gardener <mgardener@cissq.com>
 * @copyright  2019 Michael Gardener
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    /**
     * @param $courseid
     * @return array
     * @throws \dml_exception
     */
    public static function get_course_equivalencies($courseid, $includeself = false) {
        global $DB;

        $params = array($courseid, $courseid);

        $includeselfsql = '';
        if ($includeself) {
            $includeselfsql = 'UNION SELECT ? courseid';
            $params[] = $courseid;
        }
        $sql = "SELECT DISTINCT eqv.courseid
                  FROM (SELECT eq1.coursetwoid courseid 
                          FROM {local_recompletion_equiv} eq1
                         WHERE eq1.courseoneid = ?
                         UNION
                        SELECT eq1.courseoneid courseid
                          FROM {local_recompletion_equiv} eq1
                         WHERE eq1.coursetwoid = ? AND eq1.unidirectional = 0
                         $includeselfsql
                       ) AS eqv
                  JOIN {course} c 
                    ON eqv.courseid = c.id";

        return $DB->get_records_sql($sql, $params);
    }

    public static function get_last_equivalency_completion($userid, $courseid, $equivalencies) {
        global $DB;
        if (is_array($equivalencies)) {
            $courseids = array_keys($equivalencies);
        } else {
            $courseids = array();
        }
        $courseids[] = $courseid;

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cor');

        $params['userid'] = $userid;

        $sql = "SELECT cc.course, cc.timecompleted
                  FROM (
                  SELECT course, userid, timecompleted FROM {course_completions}
                  UNION 
                  SELECT course, userid, timecompleted FROM {local_recompletion_cc}
                  ) cc
                  JOIN {course} c ON c.id = cc.course
                 WHERE c.enablecompletion = ".COMPLETION_ENABLED."
                   AND timecompleted > 0
                   AND cc.userid = :userid
                   AND cc.course $insql
                   ORDER BY cc.timecompleted DESC";

        return $DB->get_record_sql($sql, $params, IGNORE_MULTIPLE);
    }

    public static function get_user_course_due_date($userid, $courseid, $usecachedvalues = false, $graceperiod = true) {
        global $DB;

        static $duedates = array();
        if (!PHPUNIT_TEST && isset($duedates[$courseid][$userid])) {
            return $duedates[$courseid][$userid];
        }

        $config = $DB->get_records_menu('local_recompletion_config', array('course' => $courseid), '', 'name, value');
        $duedate = false;
        $equivalents = \local_recompletion\helper::get_course_equivalencies($courseid);
        $completiondatetime =
                \local_recompletion\helper::get_last_equivalency_completion($userid, $courseid, $equivalents);
        if (isset($config['enable']) && $config['enable']) {
            if (isset($config['recompletionduration']) && $config['recompletionduration']) {
                if (!$usecachedvalues || !$cache = $DB->get_record('local_recompletion_cc_cached', ['userid'=>$userid, 'courseid'=>$courseid])) {
                    if ($completiondatetime) {
                        $duedate = (int) $completiondatetime->timecompleted + (int) $config['recompletionduration'];
                    }
                } else {
                    $duedate = (int)$cache->latestcomp + (int)$config['recompletionduration'];
                }
            }
        }
        if ($graceperiod && !empty($config['graceperiod']) && empty($completiondatetime)) {
            $timestart = self::get_user_course_timestart($userid, $courseid);
            $graceperiodtime = 0;
            if ($timestart) {
                $graceperiodtime = $timestart + $config['graceperiod'];
            }
            if (empty($duedate) && !empty($graceperiodtime)) {
                $duedate = $graceperiodtime;
            }
        }

        $duedates[$courseid][$userid] = $duedate;

        return $duedate;
    }

    public static function get_user_course_notificationstart_date($userid, $courseid, $usecachedvalues = false) {
        global $DB;

        $config = $DB->get_records_menu('local_recompletion_config', array('course' => $courseid), '', 'name, value');
        if (isset($config['enable']) && $config['enable'] && isset($config['notificationstart']) && $config['notificationstart']) {
            $duedate = self::get_user_course_due_date($userid, $courseid, $usecachedvalues);
            if ($duedate) {
                return $duedate - (int) $config['notificationstart'];
            }
        }

        return false;
    }

    public static function get_user_course_timestart($userid, $courseid) {
        global $DB;

        $sql = "SELECT GREATEST(ue.timecreated, ue.timestart) FROM {user_enrolments} ue
                            JOIN {enrol} e ON ue.enrolid = e.id
                            WHERE e.courseid = :courseid
                            AND ue.userid = :userid";
        $params = ['courseid' => $courseid, 'userid' => $userid];
        $timestart = $DB->get_field_sql($sql, $params);

        return $timestart;
    }

    public static function recomplete_time($timecompleted, $config) {
        return ($timecompleted + $config->recompletionduration - $config->notificationstart);
    }
}