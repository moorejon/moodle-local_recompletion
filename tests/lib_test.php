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
class local_recompletion_lib_testcase extends advanced_testcase {
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
        $this->assertEquals(false, $corecompletion);

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

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse->id, false);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $stringobj = new stdClass();
        $stringobj->coursename = $course->fullname;
        $defaultsubject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultsubject, $result[0]->subject);
    }

    /**
     * An incomplete course with multiple equivalent courses that are complete but expired that properly triggers
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
        $timecompleted1 = time() - (5 * DAYSECS + 2 * HOURSECS);
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

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id, false);
        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse2->id, false);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $stringobj = new stdClass();
        $stringobj->coursename = $course->fullname;
        $defaultsubject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $stringobj);
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
        $timecompleted2 = time() - (1 * DAYSECS + 2 * HOURSECS);
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

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id, false);
        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse2->id, false);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $result = $sink->get_messages();
        $this->assertEmpty($result);
    }

    /**
     * A completed course with one or more completed equivalents that properly triggers recompletion
     * notifications and expiration.
     */
    public function test_recomplete_using_equivalents() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($course);
        $this->create_course_completion($equivalentcourse1);

        $this->complete_module($course, $user);
        $this->complete_course($equivalentcourse1, $user);
        $completions = $DB->get_records('course_completions');
        $this->assertCount(1, $completions);
        $modulecompletions = $DB->get_records('course_modules_completion');
        $this->assertCount(2, $modulecompletions);


        $corecompletion1 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse1->id]);
        $timecompleted1 = time() - (5 * DAYSECS + 2 * HOURSECS);
        $corecompletion1->timecompleted = $timecompleted1;
        \local_recompletion_external::update_core_completion([(array) $corecompletion1]);

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
                'recompletionreminderbody' => '',
                'recompletewithequivalent' => 1
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
                'recompletionreminderbody' => ''
        );

        \local_recompletion_external::update_course_settings($equivalentcourse1->id, $settings);

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id, false);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $stringobj = new stdClass();
        $stringobj->coursename = $course->fullname;
        $defaultsubject = get_string('recompletionemaildefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultsubject, $messages[0]->subject);

        // Is data reset for course and its equivalent?
        $completions = $DB->get_records('course_completions');
        $this->assertCount(0, $completions);
        $modulecompletions = $DB->get_records('course_modules_completion');
        $this->assertCount(0, $modulecompletions);

        // Verify no new messages are sent on task re-execution and course isn't reset again
        $this->complete_module($course, $user);
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $nummessages = $sink->count();
        $this->assertEquals(1, $nummessages);

        // Completions test
        $modulecompletions = $DB->get_records('course_modules_completion');
        $this->assertCount(1, $modulecompletions);
    }

    /**
     * Test due date functionality
     */
    public function test_due_date_with_multiple_equivalents() {
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
        $timecompleted2 = time() - (10 * DAYSECS + 2 * HOURSECS);
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

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id, false);
        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse2->id, false);

        $duedate = $timecompleted + (3 * DAYSECS);
        $calculateddue = \local_recompletion\helper::get_user_course_due_date($user->id, $course->id, false, true);
        $this->assertEquals($duedate, $calculateddue);
    }

    /**
     * Test grace period due dates functionality
     */
    public function test_grace_period_with_multiple_equivalents() {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();

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
                'notificationstart' => 3, // 1 day.
                'frequency' => 1, // 1 day
                'recompletionremindersubject' => '',
                'recompletionreminderbody' => '',
                'graceperiod' => 3
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

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
                'recompletionreminderbody' => '',
                'graceperiod' => 3
        );
        \local_recompletion_external::update_course_settings($equivalentcourse1->id, $settings);

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
                'notificationstart' => 3, // 1 day.
                'frequency' => 1, // 1 day
                'recompletionremindersubject' => '',
                'recompletionreminderbody' => '',
                'graceperiod' => 3
        );
        \local_recompletion_external::update_course_settings($course2->id, $settings);

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id, false);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $equivalentcourse1->id, 'student');
        $this->getDataGenerator()->enrol_user($user->id, $course2->id, 'student');

        // Check the due date using grace period for the first course
        $enrolinstances = enrol_get_instances($course->id, true);
        foreach ($enrolinstances as $instance) {
            if ($instance->enrol == 'manual') {
                $enrolinstance = $instance;
                break;
            }
        }
        $timestart = $DB->get_field('user_enrolments', 'timecreated', array('userid' => $user->id, 'enrolid' => $enrolinstance->id));
        $duedate = $timestart + (3 * DAYSECS);
        $calculateddue = \local_recompletion\helper::get_user_course_due_date($user->id, $course->id, false, true);
        $this->assertEquals($duedate, $calculateddue);

        // Notification test. Three course enrollments and two should fall within notification period of the grace period
        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $messages = $sink->get_messages();
        $this->assertCount(5, $messages);

        $stringobj = new stdClass();
        $stringobj->coursename = $course->fullname;
        $defaultgracesubject = get_string('recompletiongraceperioddefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultgracesubject, $messages[0]->subject);

        $stringobj->coursename = $equivalentcourse1->fullname;
        $defaultgracesubject = get_string('recompletiongraceperioddefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultgracesubject, $messages[1]->subject);

        $stringobj->coursename = $course2->fullname;
        $defaultgracesubject = get_string('recompletiongraceperioddefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultgracesubject, $messages[2]->subject);

        $stringobj->coursename = $course->fullname;
        $defaultremindsubject = get_string('recompletionreminderdefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultremindsubject, $messages[3]->subject);

        $stringobj->coursename = $course2->fullname;
        $defaultremindsubject = get_string('recompletionreminderdefaultsubject', 'local_recompletion', $stringobj);
        $this->assertEquals($defaultremindsubject, $messages[4]->subject);

    }

    /**
     * Test grace period due dates functionality
     */
    public function test_grace_period_with_expired_completion() {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $equivalentcourse2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($equivalentcourse1);

        $this->complete_course($equivalentcourse1, $user);
        $corecompletion1 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $equivalentcourse1->id]);
        $timecompleted1 = time() - (5 * DAYSECS);
        $corecompletion1->timecompleted = $timecompleted1;
        \local_recompletion_external::update_core_completion([(array) $corecompletion1]);

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
                'recompletionreminderbody' => '',
                'graceperiod' => 3
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse1->id, false);
        \local_recompletion_external::create_course_equivalent($course->id, $equivalentcourse2->id, false);

        $duedate = $timecompleted1 + (3 * DAYSECS);
        $calculateddue = \local_recompletion\helper::get_user_course_due_date($user->id, $course->id, false, true);
        $this->assertEquals($duedate, $calculateddue);
    }

    /**
     * Test grace period due dates functionality
     */
    public function test_grace_period_with_recompletion_disabled() {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $settings = array(
                'enable' => 0,
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
                'recompletionreminderbody' => '',
                'graceperiod' => 3
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        // Make sure grace period calculates even though recompletion is disabled
        $timestart = \local_recompletion\helper::get_user_course_timestart($user->id, $course->id);
        $duedate = $timestart + ($settings['graceperiod'] * DAYSECS);
        $calculateddue = \local_recompletion\helper::get_user_course_due_date($user->id, $course->id, false, true);
        $this->assertEquals($duedate, $calculateddue);

        // Now check if grace period still calculates if a completion is present
        $this->create_course_completion($course);
        $this->complete_course($course, $user);
        $corecompletion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $timecompleted = time();
        $corecompletion->timecompleted = $timecompleted;
        \local_recompletion_external::update_core_completion([(array) $corecompletion]);
        $calculateddue = \local_recompletion\helper::get_user_course_due_date($user->id, $course->id, false, true);
        $this->assertFalse($calculateddue);
    }

    public function test_course_completion_webservice () {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['idnumber' => 'COURSE12345', 'enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user(['idnumber' => 'USER12345']);

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->create_course_completion($course);
        $this->complete_course($course, $user);
        $grade = 50;
        $this->create_grade($course, $user, $grade);

        $corecompletion = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $timecompleted = time() - (5 * DAYSECS);
        $corecompletion->timecompleted = $timecompleted;
        \local_recompletion_external::update_core_completion([(array) $corecompletion]);
        \core\event\course_completed::create_from_completion($corecompletion)->trigger();

        $result = \local_recompletion_external::get_completions();
        $this->assertCount(1, $result['completions']);
        $this->assertequals($user->idnumber, $result['completions'][0]['userid']);
        $this->assertequals($course->idnumber, $result['completions'][0]['courseid']);
        $this->assertequals($grade, $result['completions'][0]['gradefinal']);
    }

    /**
     * Test early recompletion duration functionality
     */
    public function test_early_recompletion_duration() {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($course);

        $this->complete_course($course, $user);
        $corecompletion1 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $timecompleted1 = time() - (5 * DAYSECS + 1);
        $corecompletion1->timecompleted = $timecompleted1;
        \local_recompletion_external::update_core_completion([(array) $corecompletion1]);

        $settings = array(
                'enable' => 1,
                'recompletionduration' => 10,
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
                'recompletionreminderbody' => '',
                'graceperiod' => 3,
                'earlyrecompletionduration' => 5
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);

        // Is data reset for course?
        $completions = $DB->get_records('course_completions');
        $this->assertCount(0, $completions);
        $modulecompletions = $DB->get_records('course_modules_completion');
        $this->assertCount(0, $modulecompletions);
    }

    /**
     * Test recompletion with zero duration (should not trigger)
     */
    public function test_recompletion_with_zero_duration() {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');

        $this->create_course_completion($course);

        $this->complete_course($course, $user);
        $corecompletion1 = $DB->get_record('course_completions', ['userid' => $user->id, 'course' => $course->id]);
        $timecompleted1 = time() - (5 * DAYSECS + 1);
        $corecompletion1->timecompleted = $timecompleted1;
        \local_recompletion_external::update_core_completion([(array) $corecompletion1]);

        $settings = array(
                'enable' => 1,
                'recompletionduration' => 0,
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
                'recompletionreminderbody' => '',
                'graceperiod' => 3,
                'earlyrecompletionduration' => 5
        );
        \local_recompletion_external::update_course_settings($course->id, $settings);

        $sink = $this->redirectEmails();
        $task = new local_recompletion\task\check_recompletion();
        $task->execute();

        // Notification test.
        $messages = $sink->get_messages();
        $this->assertCount(0, $messages);

        // Is data reset for course? Should be untouched
        $completions = $DB->get_records('course_completions');
        $this->assertCount(1, $completions);
        $modulecompletions = $DB->get_records('course_modules_completion');
        $this->assertCount(1, $modulecompletions);
    }

    public function test_synced_record_cleanup() {
        global $DB, $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();

        $completionrecord = new stdClass();
        $completionrecord->userid = $user->id;
        $completionrecord->courseid = $course->id;
        $thirtyonedaysago = time() - 60 * 60 * 24 * 31;
        $completionrecord->timecompleted = $thirtyonedaysago;
        $completionrecord->timesynced = $thirtyonedaysago;
        $completionrecord->synced = 0;
        $DB->insert_record('local_recompletion_com', $completionrecord);

        $completions = \local_recompletion_external::get_completions();
        $this->assertCount(1, $completions);

        $outcomprecord = new stdClass();
        $outcomprecord->userid = $user->id;
        $outcomprecord->courseid = $course->id;
        $outcomprecord->timesynced = $thirtyonedaysago;
        $outcomprecord->synced = 0;
        $DB->insert_record('local_recompletion_outcomp', $outcomprecord);

        $outofcomps = \local_recompletion_external::get_out_of_compliants();
        $this->assertCount(1, $outofcomps);

        $task = new \local_recompletion\task\remove_old_synced();
        $task->execute();

        $completions = \local_recompletion_external::get_completions();
        $this->assertCount(1, $completions['completions']);
        $outofcomps = \local_recompletion_external::get_out_of_compliants();
        $this->assertCount(1, $outofcomps['outofcompliants']);

        $ids = array('ids' => $completions['completions'][0]['id']);
        \local_recompletion_external::mark_completions_synced($ids);

        $ids = array('ids' => $outofcomps['outofcompliants'][0]['id']);
        \local_recompletion_external::mark_out_of_compliants($ids);

        $task = new \local_recompletion\task\remove_old_synced();
        $task->execute();

        $completions = \local_recompletion_external::get_completions();
        $this->assertEmpty($completions['completions']);
        $outofcomps = \local_recompletion_external::get_out_of_compliants();
        $this->assertEmpty($outofcomps['outofcompliants']);
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
    public function complete_course($course, $user, $modulecompletion = true) {
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
            $this->complete_module($course, $user);
        }
    }

    public function complete_module($course, $user) {
        // Set activity as complete.
        $completion = new \completion_info($course);
        $completion->update_state($this->cm[$course->id], COMPLETION_COMPLETE, $user->id);
    }

    public function create_grade($course, $user, $finalgrade = 50) {
        global $DB;

        $courseitem = \grade_item::fetch_course_item($course->id);

        // Create a grade to go with the grade item.
        $grade = new stdClass();
        $grade->itemid = $courseitem->id;
        $grade->userid = $user->id;
        $grade->finalgrade = $finalgrade;
        $grade->rawgrademax = $courseitem->grademax;
        $grade->rawgrademin = $courseitem->grademin;
        $grade->timecreated = time();
        $grade->timemodified = time();
        $DB->insert_record('grade_grades', $grade);
    }
}