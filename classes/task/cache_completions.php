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
 * @author     Dan Marsden http://danmarsden.com
 * @copyright  2017 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_recompletion\task;

use local_recompletion\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Check for users that need to recomplete.
 *
 * @package    local_recompletion
 * @author     Dan Marsden http://danmarsden.com
 * @copyright  2017 Dan Marsden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cache_completions extends \core\task\scheduled_task {

    protected $configs = array();

    /**
     * Returns the name of this task.
     */
    public function get_name() {
        // Shown in admin screens.
        return get_string('recompletiontask', 'local_recompletion');
    }

    /**
     * Execute task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/local/recompletion/locallib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        if (!\completion_info::is_enabled_for_site()) {
            return;
        }

        // get all enabled courses
        $params = array('status' => COMPLETION_ENABLED);
        $sql = "SELECT c.id
            FROM {course} c
            WHERE c.enablecompletion = :status
            AND c.visible = 1";

        $courses = $DB->get_fieldset_sql($sql, $params);
        foreach ($courses as $course) {
            // get all of its equivalent courses
            $equivalents = \local_recompletion\helper::get_course_equivalencies($course, true);
            $params = array($course);
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($equivalents));
            $params = array_merge($params, $inparams);

            // Update the min values
            $sql = "SELECT comp.userid, cache.id as cacheid, MIN(comp.timecompleted) as timecompleted
                 FROM (SELECT *, '0' AS archived
                       FROM {course_completions} AS cc
                       UNION
                       SELECT *, '1' AS archived
                       FROM {local_recompletion_cc} AS lc) comp
             LEFT JOIN {local_recompletion_cc_cached} cache ON cache.userid = comp.userid AND cache.courseid = ?
                 WHERE comp.timecompleted > 0
                   AND comp.course $insql
                   AND (cache.id IS NULL OR comp.timecompleted < cache.originalcomp)
              GROUP BY comp.userid, cache.id";

            $newminvalues = $DB->get_records_sql($sql, $params);

            foreach($newminvalues as $newminvalue) {
                if (!empty($newminvalue->cacheid)) {
                    $cacherecord = $DB->get_record('local_recompletion_cc_cached', array('id' => $newminvalue->cacheid));
                    $cacherecord->originalcomp = $newminvalue->timecompleted;
                    $DB->update_record('local_recompletion_cc_cached', $cacherecord);
                } else {
                    $cacherecord = new \stdClass();
                    $cacherecord->userid = $newminvalue->userid;
                    $cacherecord->courseid = $course;
                    $cacherecord->originalcomp = $newminvalue->timecompleted;
                    $cacherecord->latestcomp = $newminvalue->timecompleted;
                    $DB->insert_record('local_recompletion_cc_cached', $cacherecord);
                }
            }

            // Update the max values
            $sql = "SELECT comp.userid, cache.id as cacheid, MAX(comp.timecompleted) as timecompleted
                 FROM (SELECT *, '0' AS archived
                       FROM {course_completions} AS cc
                       UNION
                       SELECT *, '1' AS archived
                       FROM {local_recompletion_cc} AS lc) comp
                  JOIN {local_recompletion_cc_cached} cache ON cache.userid = comp.userid AND cache.courseid = ?
                 WHERE comp.timecompleted > 0
                   AND comp.course $insql
                   AND comp.timecompleted > cache.latestcomp
              GROUP BY comp.userid, cache.id";

            $newmaxvalues = $DB->get_records_sql($sql, $params);

            foreach($newmaxvalues as $newmaxvalue) {
                if (!empty($newmaxvalue->cacheid)) {
                    $cacherecord = $DB->get_record('local_recompletion_cc_cached', array('id' => $newmaxvalue->cacheid));
                    $cacherecord->latestcomp = $newmaxvalue->timecompleted;
                    $DB->update_record('local_recompletion_cc_cached', $cacherecord);
                }
            }
        }

        return true;
    }


}