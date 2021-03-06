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
 * Used to check for out of compliants.
 *
 * @package    local_recompletion
 * @author     Michael Gardener <mgardener@cissq.co,>
 * @copyright  2020 Michael Gardener
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion\task;

use local_recompletion\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Check for out of compliants.
 *
 * @package    local_recompletion
 * @author     Michael Gardener <mgardener@cissq.co,>
 * @copyright  2020 Michael Gardener
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class out_of_compliance extends \core\task\scheduled_task {

    protected $configs = array();

    /**
     * Returns the name of this task.
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('outofcompliancerecords', 'local_recompletion');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');
        require_once($CFG->libdir . '/completionlib.php');

        if (!\completion_info::is_enabled_for_site()) {
            return;
        }

        // Get all enabled courses.
        $sql = "SELECT c.id
                  FROM {course} c
                  JOIN {local_recompletion_config} cfgenable ON cfgenable.course = c.id AND cfgenable.name = 'enable'
                  JOIN {local_recompletion_config} cfgduration ON cfgduration.course = c.id AND cfgduration.name = 'recompletionduration'                
                   AND c.visible = 1
                   AND c.enablecompletion = ".COMPLETION_ENABLED."
                   AND ".$DB->sql_cast_char2int('cfgenable.value')." = 1 
                   AND ".$DB->sql_cast_char2int('cfgduration.value')." > 0";


        $courses = $DB->get_fieldset_sql($sql);

        foreach ($courses as $courseid) {
            $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
            $users = get_enrolled_users(\context_course::instance($courseid), '', 0, 'u.*', null, 0, 0, true);

            if (empty($users)) {
                continue;
            }

            if (empty($course->idnumber)) {
                continue;
            }

            $config = $DB->get_records_menu('local_recompletion_config', array('course' => $courseid), '', 'name, value');
            $config = (object) $config;

            // Get all of its equivalent courses.
            $equivalents = \local_recompletion\helper::get_course_equivalencies($courseid, true);

            list($courseinsql, $courseinparams) = $DB->get_in_or_equal(array_keys($equivalents));
            list($userinsql, $userinparams) = $DB->get_in_or_equal(array_keys($users));

            $params = array_merge($courseinparams, $userinparams);

            // Find last completion.
            $sql = "SELECT u.id as userid, MAX(comp.timecompleted) as timecompleted
                 FROM {user} u
                 LEFT JOIN (SELECT *, '0' AS archived
                       FROM {course_completions} AS cc
                       UNION
                       SELECT *, '1' AS archived
                       FROM {local_recompletion_cc} AS lc) comp ON comp.userid = u.id AND comp.course $courseinsql
                 WHERE u.id $userinsql
              GROUP BY u.id";

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $completion) {
                $graceperiod = 0;
                if (!empty($config->graceperiod)) {
                    if (empty($completion->timecompleted)) {
                        $timestart = helper::get_user_course_timestart($completion->userid, $courseid);
                        if ($timestart) {
                            $graceperiod = $timestart + $config->graceperiod;
                        }
                    }
                }
                $user = $users[$completion->userid];

                if (empty($user->idnumber)) {
                    continue;
                }

                $outofcompliant = false;
                if ((empty($completion->timecompleted) && (empty($graceperiod) || $graceperiod <= time())) ||
                        (!empty($completion->timecompleted) && ($completion->timecompleted + $config->recompletionduration) <= time())) {
                    $outofcompliant = true;
                }
                if ($outofcompliant) {
                    $rec = new \stdClass();
                    $rec->userid = $user->idnumber;
                    $rec->courseid = $course->idnumber;
                    $rec->timesynced = time();

                    $DB->insert_record('local_recompletion_outcomp', $rec);
                }
            }
        }

        return true;
    }


}