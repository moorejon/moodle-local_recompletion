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

/**
 * Helper.
 *
 * @package    local_recompletion
 * @author     Michael Gardener <mgardener@cissq.com>
 * @copyright  2019 Michael Gardener
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {

    public static function get_course_equivalents($courseid) {
        global $DB;

        $sql = "SELECT DISTINCT eqv.courseid
                  FROM (SELECT eq1.coursetwoid courseid 
                          FROM {local_recompletion_equiv} eq1
                         WHERE eq1.courseoneid = ?
                         UNION
                        SELECT eq1.courseoneid courseid
                          FROM {local_recompletion_equiv} eq1
                         WHERE eq1.coursetwoid = ?
                       ) AS eqv
                  JOIN {course} c 
                    ON eqv.courseid = c.id";

        return $DB->get_records_sql($sql, array($courseid, $courseid));
    }

}