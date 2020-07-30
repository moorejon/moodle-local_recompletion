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
 * Edit course completion settings - the form definition.
 *
 * @package     local_recompletion
 * @copyright   2017 Dan Marsden
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Defines the course completion settings form.
 *
 * @copyright   2017 Dan Marsden
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_recompletion_recompletion_form extends moodleform {

    /**
     * Defines the form fields.
     */
    public function definition() {

        $mform = $this->_form;
        $course = $this->_customdata['course'];

        $mform->addElement('checkbox', 'enable', get_string('enablerecompletion', 'local_recompletion'));
        $mform->addHelpButton('enable', 'enablerecompletion', 'local_recompletion');

        $options = array('optional' => false, 'defaultunit' => 86400);
        $mform->addElement('duration', 'recompletionduration', get_string('recompletionrange', 'local_recompletion'), $options);
        $mform->addHelpButton('recompletionduration', 'recompletionrange', 'local_recompletion');
        $mform->disabledIf('recompletionduration', 'enable', 'notchecked');
        $mform->addElement('checkbox', 'recompletionemailenable', get_string('recompletionemailenable', 'local_recompletion'));
        $mform->setDefault('recompletionemailenable', 1);
        $mform->addHelpButton('recompletionemailenable', 'recompletionemailenable', 'local_recompletion');
        $mform->disabledIf('recompletionemailenable', 'enable', 'notchecked');

        $mform->addElement('checkbox', 'bulknotification', get_string('bulknotification', 'local_recompletion'));
        $mform->setDefault('bulknotification', 1);
        $mform->disabledIf('bulknotification', 'enable', 'notchecked');

        $mform->addElement('duration', 'notificationstart', get_string('notificationstart', 'local_recompletion'), $options);
        $mform->addHelpButton('notificationstart', 'notificationstart', 'local_recompletion');
        $mform->disabledIf('notificationstart', 'enable', 'notchecked');

        $mform->addElement('duration', 'frequency', get_string('frequency', 'local_recompletion'), $options);
        $mform->addHelpButton('frequency', 'frequency', 'local_recompletion');
        $mform->disabledIf('frequency', 'enable', 'notchecked');

        $mform->addElement('duration', 'graceperiod', get_string('graceperiod', 'local_recompletion'), $options);
        $mform->addHelpButton('graceperiod', 'graceperiod', 'local_recompletion');
        $mform->disabledIf('graceperiod', 'enable', 'notchecked');
        $mform->setDefault('graceperiod', 30 * DAYSECS);

        $mform->addElement('checkbox', 'autocompletewithequivalent', get_string('autocompletewithequivalent', 'local_recompletion'));
        $mform->setDefault('autocompletewithequivalent', 0);
        $mform->disabledIf('autocompletewithequivalent', 'enable', 'notchecked');

        // Email Notification settings.
        $mform->addElement('header', 'emailheader', get_string('emailrecompletiontitle', 'local_recompletion'));
        $mform->setExpanded('emailheader', false);
        $mform->addElement('text', 'recompletionemailsubject', get_string('recompletionemailsubject', 'local_recompletion'),
                'size = "80"');
        $mform->setType('recompletionemailsubject', PARAM_RAW);
        $mform->addHelpButton('recompletionemailsubject', 'recompletionemailsubject', 'local_recompletion');
        $mform->disabledIf('recompletionemailsubject', 'enable', 'notchecked');
        $mform->disabledIf('recompletionemailsubject', 'recompletionemailenable', 'notchecked');
        $options = array('cols' => '60', 'rows' => '8');
        $mform->addElement('textarea', 'recompletionemailbody', get_string('recompletionemailbody', 'local_recompletion'),
                $options);
        $mform->addHelpButton('recompletionemailbody', 'recompletionemailbody', 'local_recompletion');
        $mform->disabledIf('recompletionemailbody', 'enable', 'notchecked');
        $mform->disabledIf('recompletionemailbody', 'recompletionemailenable', 'notchecked');

        // Reminder email setting.
        $mform->addElement('text', 'recompletionremindersubject', get_string('recompletionremindersubject', 'local_recompletion'),
            'size = "80"');
        $mform->setType('recompletionremindersubject', PARAM_RAW);
        $mform->addHelpButton('recompletionremindersubject', 'recompletionremindersubject', 'local_recompletion');
        $mform->disabledIf('recompletionremindersubject', 'enable', 'notchecked');
        $mform->disabledIf('recompletionremindersubject', 'recompletionemailenable', 'notchecked');
        $options = array('cols' => '60', 'rows' => '8');
        $mform->addElement('textarea', 'recompletionreminderbody', get_string('recompletionreminderbody', 'local_recompletion'),
            $options);
        $mform->addHelpButton('recompletionreminderbody', 'recompletionreminderbody', 'local_recompletion');
        $mform->disabledIf('recompletionreminderbody', 'enable', 'notchecked');
        $mform->disabledIf('recompletionreminderbody', 'recompletionemailenable', 'notchecked');

        // Advanced recompletion settings.
        // Delete data section.
        $mform->addElement('header', 'advancedheader', get_string('advancedrecompletiontitle', 'local_recompletion'));
        $mform->setExpanded('advancedheader', false);

        $mform->addElement('checkbox', 'deletegradedata', get_string('deletegradedata', 'local_recompletion'));
        $mform->setDefault('deletegradedata', 1);
        $mform->addHelpButton('deletegradedata', 'deletegradedata', 'local_recompletion');

        $mform->addElement('checkbox', 'archivecompletiondata', get_string('archivecompletiondata', 'local_recompletion'));
        $mform->setDefault('archivecompletiondata', 1);
        $mform->addHelpButton('archivecompletiondata', 'archivecompletiondata', 'local_recompletion');

        $cba = array();
        $cba[] = $mform->createElement('radio', 'scormdata', '',
            get_string('donothing', 'local_recompletion'), LOCAL_RECOMPLETION_NOTHING);
        $cba[] = $mform->createElement('radio', 'scormdata', '',
            get_string('delete', 'local_recompletion'), LOCAL_RECOMPLETION_DELETE);

        $mform->addGroup($cba, 'scorm', get_string('scormattempts', 'local_recompletion'), array(' '), false);
        $mform->addHelpButton('scorm', 'scormattempts', 'local_recompletion');

        $mform->addElement('checkbox', 'archivescormdata',
            get_string('archive', 'local_recompletion'));

        $cba = array();
        $cba[] = $mform->createElement('radio', 'quizdata', '',
            get_string('donothing', 'local_recompletion'), LOCAL_RECOMPLETION_NOTHING);
        $cba[] = $mform->createElement('radio', 'quizdata', '',
            get_string('delete', 'local_recompletion'), LOCAL_RECOMPLETION_DELETE);
        $cba[] = $mform->createElement('radio', 'quizdata', '',
            get_string('extraattempt', 'local_recompletion'), LOCAL_RECOMPLETION_EXTRAATTEMPT);

        $mform->addGroup($cba, 'quiz', get_string('quizattempts', 'local_recompletion'), array(' '), false);
        $mform->addHelpButton('quiz', 'quizattempts', 'local_recompletion');

        $mform->addElement('checkbox', 'archivequizdata',
            get_string('archive', 'local_recompletion'));

        $cba = array();
        $cba[] = $mform->createElement('radio', 'assigndata', '',
            get_string('donothing', 'local_recompletion'), LOCAL_RECOMPLETION_NOTHING);
        $cba[] = $mform->createElement('radio', 'assigndata', '',
            get_string('extraattempt', 'local_recompletion'), LOCAL_RECOMPLETION_EXTRAATTEMPT);

        $mform->addGroup($cba, 'assign', get_string('assignattempts', 'local_recompletion'), array(' '), false);
        $mform->addHelpButton('assign', 'assignattempts', 'local_recompletion');

        $mform->disabledIf('scormdata', 'enable', 'notchecked');
        $mform->disabledIf('deletegradedata', 'enable', 'notchecked');
        $mform->disabledIf('quizdata', 'enable', 'notchecked');
        $mform->disabledIf('archivecompletiondata', 'enable', 'notchecked');
        $mform->disabledIf('archivequizdata', 'enable', 'notchecked');
        $mform->disabledIf('archivescormdata', 'enable', 'notchecked');
        $mform->disabledIf('assigndata', 'enable', 'notchecked');
        $mform->hideIf('archivequizdata', 'quizdata', 'noteq', LOCAL_RECOMPLETION_DELETE);
        $mform->hideIf('archivescormdata', 'scormdata', 'notchecked');

        // Customcert.
        if ($plugininfo = \core_plugin_manager::instance()->get_plugin_info('mod_customcert')) {
            $cba = array();
            $cba[] = $mform->createElement('radio', 'customcertdata', '',
                get_string('donothing', 'local_recompletion'), LOCAL_RECOMPLETION_NOTHING);
            $cba[] = $mform->createElement('radio', 'customcertdata', '',
                get_string('delete', 'local_recompletion'), LOCAL_RECOMPLETION_DELETE);

            $mform->addGroup($cba, 'customcert', get_string('customcert', 'local_recompletion'), array(' '), false);
            $mform->addHelpButton('customcert', 'customcert', 'local_recompletion');

            $mform->addElement('checkbox', 'archivecustomcertdata',
                get_string('archive', 'local_recompletion'));

            $mform->disabledIf('customcertdata', 'enable', 'notchecked');
            $mform->disabledIf('archivecustomcertdata', 'enable', 'notchecked');
            $mform->hideIf('archivecustomcertdata', 'customcertdata', 'notchecked');
        }

        // Add common action buttons.
        $this->add_action_buttons();

        // Add hidden fields.
        $mform->addElement('hidden', 'course', $course->id);
        $mform->setType('course', PARAM_INT);
    }

    /**
     * Form validation.
     */
    public function validation($data, $files) {

        $errors = array();

        if (isset($data['enable'])) {
            if ($data['recompletionduration'] < $data['notificationstart']) {
                $errors['notificationstart'] = get_string('errorgreaterperiod', 'local_recompletion');
            }
            if ($data['recompletionduration'] < $data['frequency']) {
                $errors['frequency'] = get_string('errorgreaterperiod', 'local_recompletion');
            }
        }

        return $errors;
    }
}
