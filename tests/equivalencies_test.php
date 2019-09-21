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
 * Tests for local_recompletion.
 *
 * @package    local_recompletion
 * @category   test
 * @copyright  2019 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/completion/criteria/completion_criteria.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');
require_once($CFG->dirroot . '/completion/criteria/completion_criteria_role.php');
require_once($CFG->dirroot . '/local/recompletion/externallib.php');

/**
 * Class equivalencies_testcase
 */
class local_recompletion_equivalencies_testcase extends advanced_testcase {
    /** @var array of The course module objects */
    public $cm;

    /**
     * Test setup.
     */
    public function setUp() {
        $this->resetAfterTest();
    }

    /**
     * A completed course with no equivalents being complete and triggering recompletion notifications
     * and also expiration..
     */
    public function test_completed_course_no_equivalents() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $this->create_course_completion($course);
        $this->complete_course($course, $user);

        $corecompletion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $timecompleted = time() - (3 * DAYSECS + 2 * HOURSECS);
        $corecompletion->timecompleted = $timecompleted;

        \local_recompletion_external::update_core_completion([(array) $corecompletion]);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 3, // 3 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''
        );

        \local_recompletion_external::update_course_settings($course->id, $settings);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $defaultsubject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $course->fullname);
        $defaultsubject = str_replace('{$a->coursename}', $course->fullname, $defaultsubject);
        $this->assertEquals($defaultsubject, $result[0]->subject);

        // Check reset.
        $corecompletion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $this->assertEquals(null, $corecompletion);

        // Archived completion data.
        $recompletion = $DB->get_record('local_recompletion_cc', ['userid' => $user->id, 'course' => $course->id]);
        $this->assertEquals($timecompleted, $recompletion->timecompleted);
    }

    /**
     * An incomplete course with an equivalent course that is complete that triggers recompletion
     * otifications and expiration.
     */
    public function test_incomplete_course_with_an_equivalent() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($course);
        $this->create_course_completion($equivalentcourse);

        $this->complete_course($equivalentcourse, $user);

        $corecompletion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse->id]);
        $timecompleted = time() - (3 * DAYSECS + 2 * HOURSECS);
        $corecompletion->timecompleted = $timecompleted;

        \local_recompletion_external::update_core_completion([(array) $corecompletion]);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 3, // 3 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 5, // 5 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''
        );
        \local_recompletion_external::update_course_settings($equivalentcourse->id, $settings);

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse->id);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $defaultsubject = get_string('recompletionreminderdefaultsubject', 'local_recompletion', $course->fullname);
        $defaultsubject = str_replace('{$a->coursename}', $equivalentcourse->fullname, $defaultsubject);
        $this->assertEquals($defaultsubject, $result[0]->subject);
    }

    /**
     * An incomplete course with multiple equivalent courses that are complete that properly triggers
     * recompletion notifications and expiration.
     */
    public function test_incomplete_course_with_multiple_equivalents() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($course);
        $this->create_course_completion($equivalentcourse1);
        $this->create_course_completion($equivalentcourse2);

        $this->complete_course($equivalentcourse1, $user);
        $this->complete_course($equivalentcourse2, $user);

        $corecompletion1 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse1->id]);
        $timecompleted1 = time() - (1 * DAYSECS + 2 * HOURSECS);
        $corecompletion1->timecompleted = $timecompleted1;
        \local_recompletion_external::update_core_completion([(array) $corecompletion1]);

        $corecompletion2 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse2->id]);
        $timecompleted2 = time() - (3 * DAYSECS + 2 * HOURSECS);
        $corecompletion2->timecompleted = $timecompleted2;
        \local_recompletion_external::update_core_completion([(array) $corecompletion2]);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 3, // 3 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 10, // 5 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''        );

        \local_recompletion_external::update_course_settings($equivalentcourse1->id, $settings);
        \local_recompletion_external::update_course_settings($equivalentcourse2->id, $settings);

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id);
        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse2->id);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $defaultsubject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $course->fullname);
        $defaultsubject = str_replace('{$a->coursename}', $course->fullname, $defaultsubject);
        $this->assertEquals($defaultsubject, $result[0]->subject);
    }

    /**
     * A completed course with one or more completed equivalents that properly triggers recompletion
     * notifications and expiration.
     */
    public function test_complete_course_with_multiple_equivalents() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($course);
        $this->create_course_completion($equivalentcourse1);
        $this->create_course_completion($equivalentcourse2);

        $this->complete_course($course, $user);
        $this->complete_course($equivalentcourse1, $user);
        $this->complete_course($equivalentcourse2, $user);

        $corecompletion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $timecompleted = time() - (3 * DAYSECS + 2 * HOURSECS);
        $corecompletion->timecompleted = $timecompleted;
        \local_recompletion_external::update_core_completion([(array) $corecompletion]);

        $corecompletion1 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse1->id]);
        $timecompleted1 = time() - (5 * DAYSECS + 2 * HOURSECS);
        $corecompletion1->timecompleted = $timecompleted1;
        \local_recompletion_external::update_core_completion([(array) $corecompletion1]);

        $corecompletion2 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse2->id]);
        $timecompleted2 = time() - (7 * DAYSECS + 2 * HOURSECS);
        $corecompletion2->timecompleted = $timecompleted2;
        \local_recompletion_external::update_core_completion([(array) $corecompletion2]);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 3, // 3 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        $settings = array(
            'enable' => 1,
            'recompletionduration' => 10, // 10 days.
            'deletegradedata' => 1,
            'quizdata' => 1,
            'scormdata' => 0,
            'archivecompletiondata' => 1,
            'archivequizdata' => 1,
            'archivescormdata' => 1,
            'recompletionemailenable' => 1,
            'recompletionemailsubject' => '',
            'recompletionemailbody' => '',
            'assigndata' => 1,
            'customcertdata' => 1,
            'archivecustomcertdata' => 1,
            'bulknotification' => 0,
            'notificationstart' => 1, // 1 day.
            'frequency' => 1, // 1 day
            'recompletionremindersubject' => '',
            'recompletionreminderbody' => ''        );

        \local_recompletion_external::update_course_settings($equivalentcourse1->id, $settings);
        \local_recompletion_external::update_course_settings($equivalentcourse2->id, $settings);

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id);
        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse2->id);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $defaultsubject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $course->fullname);
        $defaultsubject = str_replace('{$a->coursename}', $course->fullname, $defaultsubject);
        $this->assertEquals($defaultsubject, $result[0]->subject);
    }

    /**
     * Create completion information.
     */
    public function create_course_completion($course) {
        $this->resetAfterTest();

        $coursecontext = context_course::instance($course->id);

        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'completion' => 1]);
        $modulecontext = context_module::instance($assign->cmid);
        $cm = get_coursemodule_from_id('assign', $assign->cmid);

        // Set completion rules.
        $completion = new \completion_info($course);

        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [
                $cm->id => 1
            ]
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_role' => [3 => 3]
        ];
        $criterion = new \completion_criteria_role();
        $criterion->update_config($criteriadata);

        // Handle overall aggregation.
        $aggdata = array(
            'course'        => $course->id,
            'criteriatype'  => COMPLETION_CRITERIA_TYPE_ACTIVITY
        );
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod(COMPLETION_AGGREGATION_ALL);
        $aggregation->save();
        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_ROLE;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod(COMPLETION_AGGREGATION_ANY);
        $aggregation->save();

        // Set variables for access in tests.
        $this->cm[$course->id] = $cm;
    }

    /**
     * Complete some of the course completion criteria.
     *
     * @param  stdClass $user The user object
     * @param  bool $modulecompletion If true will complete the activity module completion thing.
     */
    public function complete_course($course, $user, $modulecompletion = true, $time=null) {
        global $DB;
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $completion = new \completion_info($course);
        $criteriacompletions = $completion->get_completions($user->id, COMPLETION_CRITERIA_TYPE_ROLE);
        $criteria = completion_criteria::factory(['id' => 3, 'criteriatype' => COMPLETION_CRITERIA_TYPE_ROLE]);
        foreach ($criteriacompletions as $ccompletion) {
            $criteria->complete($ccompletion);
        }
        if ($modulecompletion) {
            // Set activity as complete.
            $completion->update_state($this->cm[$course->id], COMPLETION_COMPLETE, $user->id);
        }
    }
}