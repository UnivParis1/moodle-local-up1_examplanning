<?php
/**
 * @package    local
 * @subpackage up1_notificationcourse
 * @copyright  2012-2016 Silecs {@link http://www.silecs.info/societe}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
//It must be included from a Moodle page

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');
}

require_once($CFG->libdir.'/formslib.php');

class local_up1_examplanning_research_form extends moodleform {
    public function definition() {

        $mform =& $this->_form;
        $params = $this->_customdata;

        $choiceArray = array(
        'assign' =>  get_string('assign', 'local_up1_examplanning'),
        'quiz' => get_string('quiz', 'local_up1_examplanning')
        );
        $mform->addElement('select', 'choice', get_string('chooseExamType', 'local_up1_examplanning'), $choiceArray);

        $opts = array(
            'minute' => 0,
            'hour' => 23,
            'day' => 0,
            'timezone' => 1
        );

        $mform->addElement('date_selector', 'startTime', get_string('from'));
        $mform->addElement('date_selector', 'endTime', get_string('to'),$opts);
        $mform->setDefault('endTime', time() + 3600 * 24);

        $this->add_action_buttons(true,  get_string('submit', 'local_up1_examplanning'));


    }
}
