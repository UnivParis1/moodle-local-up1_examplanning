<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('../../lib/accesslib.php');
require_once('../../lib/tablelib.php');
require_once('locallib.php');
require_once("$CFG->libdir/formslib.php");
require_once('research_form.php');

require_login(0, false);

$url = new moodle_url('/local/up1_examplanning/index.php');
$PAGE->set_url($url);
$context = context_user::instance($USER->id);
$PAGE->set_context($context);
echo $OUTPUT->header(); 
/**
 * vérification que l'utilisateur est un administrateur
 */
if ( is_siteadmin()) {
	$PAGE->set_heading(get_string('heading', 'local_up1_examplanning'));
	$PAGE->set_title(get_string('heading', 'local_up1_examplanning'));

	echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
	$params = array();


	$mform = new local_up1_examplanning_research_form(null, $params);
	$mform->display();
	
	$formdata = $mform->get_data();
	if ($formdata){
        if ($formdata->choice === 'apogee') {
            $resultApogee = getApogeeResult($formdata);
            by_time_addApogee($resultApogee);
            by_time_display();
        } else {
            $resultQuiz = getQuizResult($formdata, $params);
            by_time_addQuiz($resultQuiz);
            $resultAssign = getAssignResult($formdata, $params);
            by_time_addAssign($resultAssign);
            by_time_display();
            foreach ($resultAssign as $e) { unset($e->cm_id); }
            foreach ($resultQuiz as $e) { unset($e->cm_id); }
        }

		switch ($formdata->choice) {
			case 'assign':
				$table = new html_table();
				$table->id = "resultAssign";
				$table->head = array(
					get_string('assignId', 'local_up1_examplanning'), 
					get_string('courseId', 'local_up1_examplanning'),
					get_string('name', 'local_up1_examplanning'),
					get_string('nbEtudiantsInscrits', 'local_up1_examplanning'),
					get_string('nbAttemptsSubmitted', 'local_up1_examplanning'),
					get_string('allowSubmissionsFromDate', 'local_up1_examplanning'),
					get_string('dueDate', 'local_up1_examplanning'),
					get_string('courseMaxAssignAttempt', 'local_up1_examplanning'),
					get_string('nbUsersInGroups', 'local_up1_examplanning') 
				);
				$table->data = $resultAssign;
				echo html_writer::table($table);


				break;
			case 'quiz':
				$table = new html_table();
				$table->id = "resultQuiz";
				$table->head = array(
					get_string('idQuiz', 'local_up1_examplanning'), 
					get_string('courseId', 'local_up1_examplanning'),
					get_string('name', 'local_up1_examplanning'),
					get_string('nbPages', 'local_up1_examplanning'),
					get_string('nbEtudiantsInscrits', 'local_up1_examplanning'),
					get_string('nbStudentsAttempted', 'local_up1_examplanning'),
					get_string('timeOpen', 'local_up1_examplanning'),
					get_string('timeclose', 'local_up1_examplanning'),
					get_string('courseMaxQuizAttempt', 'local_up1_examplanning'),
					get_string('nbUsersInGroups', 'local_up1_examplanning') 
				);
					
				$table->data = $resultQuiz;
				echo html_writer::table($table);	
				break; 
            case 'apogee':
                echo "<script>function gotoApogee(cod_pes) { [...document.querySelectorAll('#resultApogee td:first-child')].find(e => e.textContent == cod_pes).scrollIntoView() }</script>";
				$table = new html_table();
				$table->id = "resultApogee";
				$table->head = [
					get_string('apogee_cod_pes', 'local_up1_examplanning'), 
					get_string('apogee_cod_epr', 'local_up1_examplanning'),
					get_string('name', 'local_up1_examplanning'),
					get_string('nbUsersInGroups', 'local_up1_examplanning'),
					get_string('timeOpen', 'local_up1_examplanning'),
					get_string('timeclose', 'local_up1_examplanning'),
                ];
					
				$table->data = $resultApogee;
				echo html_writer::table($table);	
				break; 
		}	
	} else if (isset($_GET["autosubmit"])) {
		echo '<script>document.querySelector("form").submit()</script>';
	}

	echo $OUTPUT->box_end();
	
}
echo $OUTPUT->footer(); 
