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
class check_recompletion extends \core\task\scheduled_task {

    protected $configs = array();

    protected $courses = array();

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

        // Checking incomplete courses that will recomplete with equivalents
        $sql = "SELECT ue.id, ue.userid, e.courseid, lrr.id as resetid, lrr.timereset
            FROM {user_enrolments} ue
            JOIN {enrol} e ON e.id = ue.enrolid
            JOIN {course} c ON c.id = e.courseid
            JOIN {local_recompletion_config} r ON r.course = e.courseid AND r.name = 'enable' AND r.value = '1'
            JOIN {local_recompletion_config} r2 ON r2.course = e.courseid AND r2.name = 'recompletionduration'
            JOIN {local_recompletion_config} r3 ON r3.course = e.courseid AND r3.name = 'recompletewithequivalent' AND r.value = '1'
            LEFT JOIN {course_completions} cc ON cc.userid = ue.userid AND cc.course = e.courseid
            LEFT JOIN {local_recompletion_reset} lrr ON lrr.userid = ue.userid AND lrr.courseid = e.courseid
            WHERE c.enablecompletion = ".COMPLETION_ENABLED." AND cc.timecompleted IS NULL
            AND ".$DB->sql_cast_char2int('r2.value')." > 0";
        $users = $DB->get_records_sql($sql);
        $time = time();
        foreach ($users as $user) {
            // Get course data.
            $course = $this->build_course($user->courseid);
            // Get recompletion config.
            $config = $this->build_config($user->courseid);

            // Reset based on equivalent courses if configured
            $equivalents = \local_recompletion\helper::get_course_equivalencies($course->id);
            $lastcompletion =
                    \local_recompletion\helper::get_last_equivalency_completion($user->userid, $user->courseid, $equivalents);
            $timetorecomplete = \local_recompletion\helper::recomplete_time($lastcompletion->timecompleted, $config);
            if ($timetorecomplete < $time && $user->timereset < $timetorecomplete) {
                $this->reset_user($user->userid, $course, $config);
                foreach ($equivalents as $equivalent) {
                    $eqvcourse = $this->build_course($equivalent->courseid);
                    $equivconfig = $this->build_config($equivalent->courseid);
                    $this->reset_user($user->userid, $eqvcourse, $equivconfig);
                }
                $resetrecord = new \stdClass();
                $resetrecord->userid = $user->userid;
                $resetrecord->courseid = $user->courseid;
                $resetrecord->timereset = $time;

                if ($user->resetid) {
                    $resetrecord->id = $user->resetid;
                    $DB->update_record('local_recompletion_reset', $resetrecord);
                } else {
                    $DB->insert_record('local_recompletion_reset', $resetrecord);
                }
            }
        }

        // Checking normal recompletion of completed courses
        $sql = "SELECT cc.id, cc.userid, cc.course
            FROM {course_completions} cc
            JOIN {local_recompletion_config} r ON r.course = cc.course AND r.name = 'enable' AND r.value = '1'
            JOIN {local_recompletion_config} r2 ON r2.course = cc.course AND r2.name = 'recompletionduration'
            JOIN {local_recompletion_config} r3 ON r3.course = cc.course AND r3.name = 'notificationstart'
            JOIN {course} c ON c.id = cc.course
            WHERE c.enablecompletion = ".COMPLETION_ENABLED." AND cc.timecompleted > 0 AND
            (cc.timecompleted + ".$DB->sql_cast_char2int('r2.value')." - ".$DB->sql_cast_char2int('r3.value').") < ?";
        $users = $DB->get_records_sql($sql, array(time()));

        foreach ($users as $user) {
            // Get course data.
            $course = $this->build_course($user->course);
            // Get recompletion config.
            $config = $this->build_config($user->course);
            $this->reset_user($user->userid, $course, $config);
        }

        $this->grace_period_inform_users();
        $this->remind_users();

        return true;
    }

    /**
     * Build course cache
     * @param $courseid
     * @return mixed|\stdClass
     * @throws \dml_exception
     */
    protected function build_course($courseid) {
        if (!isset($this->courses[$courseid])) {
            // Only get the course record for this course once.
            $course = get_course($courseid);
            $this->courses[$courseid] = $course;
        } else {
            $course = $this->courses[$courseid];
        }
        return $course;
    }

    /**
     * Build config cache
     * @param $courseid
     * @return mixed|object
     * @throws \dml_exception
     */
    protected function build_config($courseid) {
        global $DB;

        if (!isset($this->configs[$courseid])) {
            // Only get the recompletion config record for this course once.
            $config = $DB->get_records_menu('local_recompletion_config', array('course' => $courseid), '', 'name, value');
            $config = (object) $config;
            $this->configs[$courseid] = $config;
        } else {
            $config = $this->configs[$courseid];
        }
        return $config;
    }

    /**
     * Reset and archive completion records
     * @param \int $userid - user id
     * @param \stdClass $course - course record.
     * @param \stdClass $config - recompletion config.
     */
    protected function reset_completions($userid, $course, $config) {
        global $DB;
        $params = array('userid' => $userid, 'course' => $course->id);
        if ($config->archivecompletiondata) {
            $coursecompletions = $DB->get_records('course_completions', $params);
            $DB->insert_records('local_recompletion_cc', $coursecompletions);
            $criteriacompletions = $DB->get_records('course_completion_crit_compl', $params);
            $DB->insert_records('local_recompletion_cc_cc', $criteriacompletions);
        }
        $DB->delete_records('course_completions', $params);
        $DB->delete_records('course_completion_crit_compl', $params);

        // Archive and delete all activity completions.
        $selectsql = 'userid = ? AND coursemoduleid IN (SELECT id FROM {course_modules} WHERE course = ?)';
        if ($config->archivecompletiondata) {
            $cmc = $DB->get_records_select('course_modules_completion', $selectsql, $params);
            foreach ($cmc as $cid => $unused) {
                // Add courseid to records to help with restore process.
                $cmc[$cid]->course = $course->id;
            }
            $DB->insert_records('local_recompletion_cmc', $cmc);
        }
        $DB->delete_records_select('course_modules_completion', $selectsql, $params);
    }

    /**
     * Reset and archive scorm records.
     * @param \stdclass $userid - user id
     * @param \stdClass $course - course record.
     * @param \stdClass $config - recompletion config.
     */
    protected function reset_scorm($userid, $course, $config) {
        global $DB;

        if (empty($config->scormdata)) {
            return;
        } else if ($config->scormdata == LOCAL_RECOMPLETION_DELETE) {
            $params = array('userid' => $userid, 'course' => $course->id);
            $selectsql = 'userid = ? AND scormid IN (SELECT id FROM {scorm} WHERE course = ?)';
            if ($config->archivescormdata) {
                $scormscoestrack = $DB->get_records_select('scorm_scoes_track', $selectsql, $params);
                foreach ($scormscoestrack as $sid => $unused) {
                    // Add courseid to records to help with restore process.
                    $scormscoestrack[$sid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_sst', $scormscoestrack);
            }
            $DB->delete_records_select('scorm_scoes_track', $selectsql, $params);
            $DB->delete_records_select('scorm_aicc_session', $selectsql, $params);
        }
    }

    /**
     * Reset and archive certificate records.
     * @param \stdclass $userid - user id
     * @param \stdClass $course - course record.
     * @param \stdClass $config - recompletion config.
     */
    protected function reset_customcert($userid, $course, $config) {
        global $DB;

        if (empty($config->customcertdata)) {
            return;
        } else if ($config->archivecustomcertdata == LOCAL_RECOMPLETION_DELETE) {
            $params = array('userid' => $userid, 'course' => $course->id);
            $selectsql = 'userid = ? AND customcertid IN (SELECT id FROM {customcert} WHERE course = ?)';
            if ($config->archivecustomcertdata) {
                $issuedcustomcerts = $DB->get_records_select('customcert_issues', $selectsql, $params);
                foreach ($issuedcustomcerts as $sid => $unused) {
                    // Add courseid to records to help with restore process.
                    $issuedcustomcerts[$sid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_ccert', $issuedcustomcerts);
            }
            $DB->delete_records_select('customcert_issues', $selectsql, $params);
        }
    }

    /**
     * Reset and archive quiz records.
     * @param \int $userid - userid
     * @param \stdclass $course - course record.
     * @param \stdClass $config - recompletion config.
     */
    protected function reset_quiz($userid, $course, $config) {
        global $DB;
        if (empty($config->quizdata)) {
            return;
        } else if ($config->quizdata == LOCAL_RECOMPLETION_DELETE) {
            $params = array('userid' => $userid, 'course' => $course->id);
            $selectsql = 'userid = ? AND quiz IN (SELECT id FROM {quiz} WHERE course = ?)';
            if ($config->archivequizdata) {
                $quizattempts = $DB->get_records_select('quiz_attempts', $selectsql, $params);
                foreach ($quizattempts as $qid => $unused) {
                    // Add courseid to records to help with restore process.
                    $quizattempts[$qid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_qa', $quizattempts);

                $quizgrades = $DB->get_records_select('quiz_grades', $selectsql, $params);
                foreach ($quizgrades as $qid => $unused) {
                    // Add courseid to records to help with restore process.
                    $quizgrades[$qid]->course = $course->id;
                }
                $DB->insert_records('local_recompletion_qg', $quizgrades);
            }
            $DB->delete_records_select('quiz_attempts', $selectsql, $params);
            $DB->delete_records_select('quiz_grades', $selectsql, $params);
        } else if ($config->quizdata == LOCAL_RECOMPLETION_EXTRAATTEMPT) {
            // Get all quizzes that do not have unlimited attempts and have existing data for this user.
            $sql = "SELECT DISTINCT q.*
                      FROM {quiz} q
                      JOIN {quiz_attempts} qa ON q.id = qa.quiz
                     WHERE q.attempts > 0 AND q.course = ? AND qa.userid = ?";
            $quizzes = $DB->get_recordset_sql( $sql, array($course->id, $userid));
            foreach ($quizzes as $quiz) {
                // Get number of this users attempts.
                $attempts = \quiz_get_user_attempts($quiz->id, $userid);
                $countattempts = count($attempts);

                // Allow the user to have the same number of attempts at this quiz as they initially did.
                // EG if they can have 2 attempts, and they have 1 attempt already, allow them to have 2 more attempts.
                $nowallowed = $countattempts + $quiz->attempts;

                // Get stuff needed for the events.
                $cm = get_coursemodule_from_instance('quiz', $quiz->id);
                $context = \context_module::instance($cm->id);

                $eventparams = array(
                    'context' => $context,
                    'other' => array(
                        'quizid' => $quiz->id
                    ),
                    'relateduserid' => $userid
                );

                $conditions = array(
                    'quiz' => $quiz->id,
                    'userid' => $userid);
                if ($oldoverride = $DB->get_record('quiz_overrides', $conditions)) {
                    if ($oldoverride->attempts < $nowallowed) {
                        $oldoverride->attempts = $nowallowed;
                        $DB->update_record('quiz_overrides', $oldoverride);
                        $eventparams['objectid'] = $oldoverride->id;
                        $event = \mod_quiz\event\user_override_updated::create($eventparams);
                        $event->trigger();
                    }
                } else {
                    $data = new \stdClass();
                    $data->attempts = $nowallowed;
                    $data->quiz = $quiz->id;
                    $data->userid = $userid;
                    // Merge quiz defaults with data.
                    $keys = array('timeopen', 'timeclose', 'timelimit', 'password');
                    foreach ($keys as $key) {
                        if (!isset($data->{$key})) {
                            $data->{$key} = $quiz->{$key};
                        }
                    }
                    $newid = $DB->insert_record('quiz_overrides', $data);
                    $eventparams['objectid'] = $newid;
                    $event = \mod_quiz\event\user_override_created::create($eventparams);
                    $event->trigger();
                }
            }
        }
    }

    /**
     * Reset assign records.
     * @param \int $userid - record with user information for recompletion
     * @param \stdClass $course - course record.
     * @param \stdClass $config - recompletion config.
     */
    protected function reset_assign($userid, $course, $config) {
        global $DB;
        if (empty($config->assigndata)) {
            return '';
        } else if ($config->assigndata == LOCAL_RECOMPLETION_EXTRAATTEMPT) {
            $sql = "SELECT DISTINCT a.*
                      FROM {assign} a
                      JOIN {assign_submission} s ON a.id = s.assignment
                     WHERE a.course = ? AND s.userid = ?";
            $assigns = $DB->get_recordset_sql( $sql, array($course->id, $userid));
            $nopermissions = false;
            foreach ($assigns as $assign) {
                $cm = get_coursemodule_from_instance('assign', $assign->id);
                $context = \context_module::instance($cm->id);
                if (has_capability('mod/assign:grade', $context)) {
                    // Assign add_attempt() is protected - use reflection so we don't have to write our own.
                    $r = new \ReflectionMethod('assign', 'add_attempt');
                    $r->setAccessible(true);
                    $r->invoke(new \assign($context, $cm, $course), $userid);
                } else {
                    $nopermissions = true;
                }
            }
            if ($nopermissions) {
                return get_string('noassigngradepermission', 'local_recompletion');
            }
        }
        return '';
    }

    /**
     * Notify user of recompletion.
     * @param \int $userid - user id
     * @param \stdclass $course - record from course table.
     * @param \stdClass $config - recompletion config.
     */
    protected function notify_user($userid, $course, $config) {
        global $DB, $CFG;

        $userrecord = $DB->get_record('user', array('id' => $userid));
        if ($userrecord->suspended) {
            return;
        }
        $context = \context_course::instance($course->id);
        $from = $CFG->supportname;
        $a = new \stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->fullname = fullname($userrecord);
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$userrecord->id&course=$course->id";
        $a->link = course_get_url($course)->out();
        if (trim($config->recompletionemailbody) !== '') {
            $message = $config->recompletionemailbody;
            $key = array('{$a->coursename}', '{$a->profileurl}', '{$a->link}', '{$a->fullname}', '{$a->email}');
            $value = array($a->coursename, $a->profileurl, $a->link, fullname($userrecord), $userrecord->email);
            $message = str_replace($key, $value, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text($message, FORMAT_MOODLE, array('context' => $context,
                    'para' => false, 'newlines' => true, 'filter' => true));
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = get_string('recompletionemaildefaultbody', 'local_recompletion', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }
        if (trim($config->recompletionemailsubject) !== '') {
            $subject = $config->recompletionemailsubject;
            $keysub = array('{$a->coursename}', '{$a->fullname}');
            $valuesub = array($a->coursename, fullname($userrecord));
            $subject = str_replace($keysub, $valuesub, $subject);
        } else {
            $subject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $a);
        }
        // Directly emailing recompletion message rather than using messaging.
        $this->recompletion_email_to_user($course->id, $userrecord, $from, $subject, $messagetext, $messagehtml);
    }

    /**
     * Reset user completion.
     * @param \int $userid - id of user.
     * @param \stdClass $course - course record.
     * @param \stdClass $config - recompletion config.
     */
    public function reset_user($userid, $course, $config) {
        global $CFG;
        // Archive and delete course completion.
        $this->reset_completions($userid, $course, $config);

        // Delete current grade information.
        if ($config->deletegradedata) {
            if ($items = \grade_item::fetch_all(array('courseid' => $course->id))) {
                foreach ($items as $item) {
                    if ($grades = \grade_grade::fetch_all(array('userid' => $userid, 'itemid' => $item->id))) {
                        foreach ($grades as $grade) {
                            $grade->delete('local_recompletion');
                        }
                    }
                }
            }
        }

        // Archive and delete specific activity data.
        $this->reset_quiz($userid, $course, $config);
        $this->reset_scorm($userid, $course, $config);
        $errors = $this->reset_assign($userid, $course, $config);
        if ($plugininfo = \core_plugin_manager::instance()->get_plugin_info('mod_customcert')) {
            $this->reset_customcert($userid, $course, $config);
        }

        // Trigger completion reset event for this user.
        $context = \context_course::instance($course->id);
        $event = \local_recompletion\event\completion_reset::create(
            array(
                'objectid'      => $course->id,
                'relateduserid' => $userid,
                'courseid' => $course->id,
                'context' => $context,
            )
        );
        $event->trigger();

        $clearcache = true; // We have made some changes, clear completion cache.

        if ($clearcache) {
            // Difficult to find affected users, just purge all completion cache.
            \cache::make('core', 'completion')->purge();
            // Clear coursecompletion cache which was added in Moodle 3.2.
            if ($CFG->version >= 2016120500) {
                \cache::make('core', 'coursecompletion')->purge();
            }
        }
        return $errors;
    }

    /**
     * @return bool|void
     * @throws \dml_exception
     */
    public function remind_users() {
        global $CFG, $DB;

        $time = time();
        // Check notification hour.
        $notifylast = get_config('local_recompletion', 'notifylast');
        $expirynotifyhour = 0;
        $notifytime = usergetmidnight($time, $CFG->timezone) + ($expirynotifyhour * 3600);

        if ($notifylast > $notifytime) {
            // Notifications were already sent today.
            return;
        } else if ($time < $notifytime) {
            // Notifications will be sent after midnight.
            return;
        }

        $sql = "SELECT c.*
                  FROM {course} c
                  JOIN {local_recompletion_config} cfgenable ON cfgenable.course = c.id AND cfgenable.name = 'enable'
                  JOIN {local_recompletion_config} cfgduration ON cfgduration.course = c.id AND cfgduration.name = 'recompletionduration'
                  JOIN {local_recompletion_config} cfgemail ON cfgemail.course = c.id AND cfgemail.name = 'recompletionemailenable'
                   AND c.visible = 1
                   AND c.enablecompletion = ".COMPLETION_ENABLED."
                   AND ".$DB->sql_cast_char2int('cfgenable.value')." = 1 AND ".$DB->sql_cast_char2int('cfgduration.value')." > 0 AND ".$DB->sql_cast_char2int('cfgemail.value')." = 1";

        $courses = $DB->get_records_sql($sql);

        $bulkemail = array();

        foreach ($courses as $course) {
            // Get recompletion config.
            if (!isset($this->configs[$course->id])) {
                // Only get the recompletion config record for this course once.
                $config = $DB->get_records_menu('local_recompletion_config', array('course' => $course->id), '', 'name, value');
                $config = (object) $config;
                $this->configs[$course->id] = $config;
            } else {
                $config = $this->configs[$course->id];
            }

            $equivalents = \local_recompletion\helper::get_course_equivalencies($course->id, true);
            list($insql, $inparams) = $DB->get_in_or_equal(array_keys($equivalents));
            $params = array_merge(array($course->id), $inparams);

            $sql = "SELECT ue.id, ue.userid, MAX(cc.timecompleted) AS timecompleted
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON ue.enrolid = e.id
                  JOIN (SELECT userid, course, timecompleted 
                        FROM {course_completions}
                        UNION 
                        SELECT userid, course, timecompleted
                        FROM {local_recompletion_cc}) cc ON cc.userid = ue.userid
                 WHERE ue.status = 0 AND e.status = 0
                   AND cc.timecompleted > 0
                   AND e.courseid = ?
                   AND cc.course $insql
                   GROUP BY ue.id";

            $users = $DB->get_records_sql($sql, $params);

            foreach ($users as $userinfo) {
                // Don't send notification for same day recompletions.
                if ($config->recompletionduration < DAYSECS || $config->notificationstart < DAYSECS || $config->frequency < DAYSECS) {
                    continue;
                }

                $expirationdate = helper::get_user_course_due_date($userinfo->userid, $course->id, true, true);
                $currentday = floor($time / DAYSECS);
                $expirationday = floor($expirationdate / DAYSECS);

                $frequencyday = floor($config->frequency / DAYSECS);

                $dayssincereminderstart = floor(($time - ($expirationdate - $config->notificationstart)) / DAYSECS);

                // Haven't reached notification start yet
                if ($dayssincereminderstart < 0) {
                    continue;
                }
                if (!isset($config->bulknotification) || $config->bulknotification == 1) {
                    if (!$bulknotificationday1 = get_config('local_recompletion', 'bulknotificationday1')) {
                        $bulknotificationday1 = 1;
                    }
                    if (!$bulknotificationday2 = get_config('local_recompletion', 'bulknotificationday2')) {
                        $bulknotificationday2 = 15;
                    }
                    if ((date('j', $time) == $bulknotificationday1) || (date('j', $time) == $bulknotificationday2)) {
                        $emaildetails = new \stdClass();
                        $emaildetails->expirationdate = $expirationdate;
                        $emaildetails->id = $course->id;
                        $context = \context_course::instance($course->id);
                        $emaildetails->coursename = format_string($course->fullname, true, array('context' => $context));
                        $emaildetails->link = course_get_url($course->id)->out();
                        if (!isset($bulkemail[$userinfo->userid])) {
                            $bulkemail[$userinfo->userid] = array('outofcomp' => array(), 'comingdue' => array());
                        }
                        if ($currentday == $expirationday || $time >= $expirationdate) {
                            $bulkemail[$userinfo->userid]['outofcomp'][] = $emaildetails;
                        } else {
                            $bulkemail[$userinfo->userid]['comingdue'][] = $emaildetails;
                        }
                    }
                } else if ($currentday == $expirationday) {
                    $this->notify_user($userinfo->userid, $course, $config);
                } else if ($dayssincereminderstart % $frequencyday == 0 && $time < $expirationdate) {
                    $this->remind_user($userinfo->userid, $course, $config);
                }
            }
        }

        if (!empty($bulkemail)) {
            $this->bulk_email_users($bulkemail);
        }

        set_config('notifylast', $time, 'local_recompletion');

        return true;
    }

    /**
     * Sends out a bulk email that includes expiration information for each course that has expired or expires soon
     * @param $usercourses array of expiring and coming due courses with user id as the key
     */
    protected function bulk_email_users($usercourses) {
        global $DB, $CFG;

        foreach ($usercourses as $userid => $courses) {
            $userrecord = $DB->get_record('user', array('id' => $userid));

            $from = $CFG->supportname;
            $subject = get_string('bulknotification_emailsubject', 'local_recompletion');

            $a = new \stdClass();
            $a->name = fullname($userrecord);
            $messagetext = get_string('bulknotification_emailbody', 'local_recompletion', $a);

            $messageoutofcomp = '';
            if (!empty($courses['outofcomp'])) {
                usort($courses['outofcomp'], array($this, 'cmp_expiration'));
                $messageoutofcomp = get_string('bulknotification_outofcomp', 'local_recompletion');
                foreach ($courses['outofcomp'] as $course) {
                    $date = date_format_string($course->expirationdate, '%B %e, %Y', $userrecord->timezone);
                    $messageoutofcomp .= "<br><a href='$course->link'>$course->coursename</a> expired on $date.\n";
                }
            }
            $messagecomingdue = '';
            if (!empty($courses['comingdue'])) {
                usort($courses['comingdue'], array($this, 'cmp_expiration'));
                $messagecomingdue = get_string('bulknotification_comingdue', 'local_recompletion');
                foreach ($courses['comingdue'] as $course) {
                    $date = date_format_string($course->expirationdate, '%B %e, %Y', $userrecord->timezone);
                    $messagecomingdue .= "<br><a href='$course->link'>$course->coursename</a> will expire on $date.\n";
                }
            }
            $messagetext .= $messageoutofcomp;
            $messagetext .= "\n";
            $messagetext .= $messagecomingdue;

            $messagehtml = text_to_html($messagetext, null, false, true);

            // Directly emailing recompletion message rather than using messaging.
            $this->recompletion_email_to_user(1, $userrecord, $from, $subject, $messagetext, $messagehtml);
        }
    }

    private function cmp_expiration($a, $b) {
        return ($a->expirationdate < $b->expirationdate) ? -1: 1;
    }

    /**
     * Notify user before recompletion.
     * @param \int $userid - user id
     * @param \stdclass $course - record from course table.
     */
    protected function remind_user($userid, $course, $config) {
        global $DB, $CFG;

        $userrecord = $DB->get_record('user', array('id' => $userid));
        $context = \context_course::instance($course->id);
        $from = $CFG->supportname;
        $a = new \stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$userrecord->id&course=$course->id";
        $a->fullname = fullname($userrecord);
        $a->link = course_get_url($course)->out();
        if (trim($config->recompletionreminderbody) !== '') {
            $message = $config->recompletionreminderbody;
            $key = array('{$a->coursename}', '{$a->profileurl}', '{$a->link}', '{$a->fullname}', '{$a->email}');
            $value = array($a->coursename, $a->profileurl, $a->link, fullname($userrecord), $userrecord->email);
            $message = str_replace($key, $value, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text($message, FORMAT_MOODLE, array('context' => $context,
                    'para' => false, 'newlines' => true, 'filter' => true));
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = get_string('recompletionreminderdefaultbody', 'local_recompletion', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }
        if (trim($config->recompletionremindersubject) !== '') {
            $subject = $config->recompletionremindersubject;
            $keysub = array('{$a->coursename}', '{$a->fullname}');
            $valuesub = array($a->coursename, fullname($userrecord));
            $subject = str_replace($keysub, $valuesub, $subject);
        } else {
            $subject = get_string('recompletionreminderdefaultsubject', 'local_recompletion', $a);
        }
        // Directly emailing recompletion message rather than using messaging.
        $this->recompletion_email_to_user($course->id, $userrecord, $from, $subject, $messagetext, $messagehtml);
    }

    /**
     * Inform newly enrolled users of grace period.
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function grace_period_inform_users() {
        global $CFG, $DB;

        $sql = "SELECT DISTINCT u.*, lrg.courseid, lrg.timestart, cfggraceperion.value AS graceperiod
                  FROM {user} u
                  INNER JOIN {local_recompletion_grace} lrg ON lrg.userid = u.id
                  INNER JOIN {local_recompletion_config} cfggraceperion ON cfggraceperion.course = lrg.courseid
                         AND cfggraceperion.name = 'graceperiod'
                  INNER JOIN {enrol} e ON e.courseid = lrg.courseid
                  INNER JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = lrg.userid
                  LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = lrg.courseid
                  LEFT JOIN {local_recompletion_cc} lrcc ON lrcc.userid = u.id AND lrcc.course = lrg.courseid
                  WHERE (cc.id IS NULL OR cc.timecompleted IS NULL) AND lrcc.id IS NULL
                  AND e.enrol NOT IN ('auto', 'self')";

        $users = $DB->get_records_sql($sql);

        $courses = [];
        foreach ($users as $userrecord) {
            if (!isset($courses[$userrecord->courseid])) {
                // Only get the course record for this course once.
                $course = get_course($userrecord->courseid);
                $courses[$userrecord->courseid] = $course;
            } else {
                $course = $courses[$userrecord->courseid];
            }

            $context = \context_course::instance($course->id);
            $from = $CFG->supportname;
            $a = new \stdClass();
            $a->coursename = format_string($course->fullname, true, array('context' => $context));
            $a->fullname = fullname($userrecord);
            $a->link = course_get_url($course)->out();
            $a->graceperiod = date('F j, Y', ($userrecord->timestart + $userrecord->graceperiod));
            $messagetext = get_string('recompletiongraceperioddefaultbody', 'local_recompletion', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
            $subject = get_string('recompletiongraceperioddefaultsubject', 'local_recompletion', $a);
            $this->recompletion_email_to_user($course->id, $userrecord, $from, $subject, $messagetext, $messagehtml);
        }
        $DB->delete_records('local_recompletion_grace');
    }

    protected function recompletion_email_to_user($courseid, $userrecord, $from, $subject, $messagetext, $messagehtml) {
        global $DB;

        if (email_to_user($userrecord, $from, $subject, $messagetext, $messagehtml)) {
            // Trigger event for this user.
            $context = \context_course::instance($courseid);
            $event = \local_recompletion\event\reminder_sent::create(
                    array(
                            'objectid'      => $courseid,
                            'relateduserid' => $userrecord->id,
                            'courseid' => $courseid,
                            'context' => $context,
                    )
            );
            $event->trigger();

            if ($useremail = get_config('local_recompletion', 'recompletionthirdpartyemail')) {
                if ($thirdpartyuser = $DB->get_record('user', ['email' => $useremail])) {
                    $subject = '(THIRD PARTY) ' . $subject;
                    email_to_user($thirdpartyuser, $from, $subject, $messagetext, $messagehtml);
                }
            }
        }
    }
}
