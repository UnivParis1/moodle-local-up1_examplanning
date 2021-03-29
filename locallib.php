<?php

function getQuizResult($formdata, $params){
	global $DB, $USER;
	// $minCohort = is_int($formdata->minCohort)  ?$formdata->minCohort: 0;
    $sql = 'select q.id,c.id as courseid, name,
			count(distinct(qs.page)) as nb_pages,
			( select count(distinct(ra.userid)) as nb_etudiants_inscrits from 
				{course}  c 
				inner join {context} cont on cont.instanceid=c.id 
				inner join {role_assignments}  ra on ra.contextid=cont.id 
				where q.course=c.id
				group by c.id
			) as nb_etudiants_inscrits,
			count(distinct(qa.id)) as nb_students_attempted,
			FROM_UNIXTIME(timeopen), FROM_UNIXTIME(timeclose)
			from {quiz} q 
			left join {quiz_attempts} qa on q.id=qa.quiz 
			left join {quiz_slots} qs on qs.quizid = q.id
			inner join {course} c on c.id=q.course
			where timeopen >  :starttime		
			and timeopen <   :endtime	
			group by q.id
			order by timeopen
		';
    $quizResult  = $DB->get_records_sql($sql,array('starttime'=> $formdata->startTime, 'endtime'=>$formdata->endTime));

    foreach ($quizResult as $key => $value) {
    	$sqlGetMaxAttemptQuiz = 'select c.id,count(distinct(qa.id)) as nb_students_attempted
    						from {quiz} q 
    						inner join {course} c on c.id=q.course
							left join {quiz_attempts} qa on q.id=qa.quiz 
    						where q.course = :courseid 
    						group by q.id
    						order by nb_students_attempted desc limit 1'
    					;
		$quizMaxEpi  = $DB->get_records_sql($sqlGetMaxAttemptQuiz,array('courseid' => $value->courseid ));
		$quizResult[$key]->maxOtherAttempts= $quizMaxEpi[$value->courseid]->nb_students_attempted;


		$sqlGetGroupId = 'select  cm.availability
	   					from {course_modules} cm 
	   					inner join {modules} m on cm.module=m.id
	   					where instance= :key  
	   					and m.name= "quiz"';
	   	$idGroups=$DB->get_records_sql($sqlGetGroupId, array('key'=>$key));
	   	$nbUserInGroups= 0;
	   	if (!empty(array_key_first($idGroups))){
	   		$jsonId= (array_key_first($idGroups));				
	   		$arrayId = json_decode($jsonId);
	   		foreach ($arrayId->c as $keyGroup => $valueGroup) {
	   			if(isset($valueGroup->type) && isset($valueGroup->id) && $valueGroup->type==="group" )
	   			{
	   				$sqlCountMembersGroups = 'select count(gm.userid) as nbUsers
	   	 										from {groups_members} gm
	   	 										where groupid= :groupid ';
   					$nbMembersInGroup=$DB->get_records_sql($sqlCountMembersGroups,array('groupid' =>$valueGroup->id));
   					$nbUserInGroups += array_key_first($nbMembersInGroup);
	   			}
   			}

	   	}
	   	$quizResult[$key]->nbEtudiantsDansGroups =  ($nbUserInGroups == 0 ) ?  get_string('noGroups', 'local_up1_examplanning')  :  $nbUserInGroups;

	   	$quizResult[$key]->nb_etudiants_inscrits  = $quizResult[$key]->nb_etudiants_inscrits ." ( " . get_string('estimated', 'local_up1_examplanning')  ." : " .$quizResult[$key]->nb_etudiants_inscrits * 0.4 .")";
	   	// $quizResult[$key]->nb_etudiants_inscrits  = $quizResult[$key]->nb_etudiants_inscrits > 800 ? 500 : $quizResult[$key]->nb_etudiants_inscrits ." ( " . get_string('estimated', 'local_up1_examplanning')  ." : " .$quizResult[$key]->nb_etudiants_inscrits * 0.4 .")";
    }
  	return $quizResult;
}


function getAssignResult($formdata, $params){
	 global $DB, $USER;
	// print_r($formdata);
    $sql = 'select a.id,c.id as courseid, a.name ,
			( select count(distinct(ra.userid)) as nb_inscrits_au_cours from 
				{course}  c 
				inner join {context} cont on cont.instanceid=c.id 
				inner join {role_assignments}  ra on ra.contextid=cont.id 
				where a.course=c.id
				group by c.id
			) as nb_etudiants_inscrits,
			(select count(distinct(asu.id))
				from {assign_submission} asu 
				where  a.id=asu.assignment
				and asu.status="submitted"
			) as nb_attempts_submitted,
			FROM_UNIXTIME(allowsubmissionsfromdate), FROM_UNIXTIME(duedate)
			from {assign} a
			inner join {course} c on c.id=a.course
			where duedate >  :starttime 
			and duedate < :endtime 
			group by a.id
			order by duedate,a.name
		';

   	$assignResult  = $DB->get_records_sql($sql,array('starttime'=> $formdata->startTime, 'endtime'=>$formdata->endTime));
    foreach ($assignResult as $key => $value) {
   	
    	$sqlGetMaxAttemptassign = 'select c.id,count(distinct(asu.id)) as nb_students_attempted
							from {assign_submission} asu 
							inner join {assign} a on a.id=asu.assignment
    						inner join {course} c on c.id=a.course
    						where a.course = :courseid 
    						and asu.status="submitted"
    						group by a.id
    						order by nb_students_attempted desc limit 1'
    					;
		$assignMaxEpi  = $DB->get_records_sql($sqlGetMaxAttemptassign,array('courseid'=> $value->courseid ));
		$assignResult[$key]->maxOtherAttempts= (!empty($assignMaxEpi )) ?  $assignMaxEpi[$value->courseid]->nb_students_attempted : 0;

		$sqlGetGroupId = 'select  cm.availability
	   					from {course_modules} cm 
	   					inner join {modules} m on cm.module=m.id
	   					where instance= :key  
	   					and m.name= "assign"';
	   	$idGroups=$DB->get_records_sql($sqlGetGroupId, array('key'=>$key));
	   	$nbUserInGroups= 0;
	   	if (empty(array_key_first($idGroups))){
	   		$sqlGetGroupId = 'select  cs.availability
	   					from {course_modules} cm 
	   					inner join {course_sections} cs on cs.id=cm.section
	   					inner join {modules} m on cm.module=m.id
	   					where instance= :key  
	   					and m.name= "assign"';
	   		$idGroups=$DB->get_records_sql($sqlGetGroupId, array('key'=>$key));
	   	}
	   	if (!empty(array_key_first($idGroups))){
	   		$jsonId= (array_key_first($idGroups));				
	   		$arrayId = json_decode($jsonId);
	   			
	   		foreach ($arrayId->c as $keyGroup => $valueGroup) {
	   			if(isset($valueGroup->type) && isset($valueGroup->id) && $valueGroup->type==="group" )
	   			{
	   				$sqlCountMembersGroups = 'select count(gm.userid) as nbUsers
	   	 										from {groups_members} gm
	   	 										where groupid=:groupid';
   					$nbMembersInGroup=$DB->get_records_sql($sqlCountMembersGroups,array('groupid' =>$valueGroup->id));
   					$nbUserInGroups += array_key_first($nbMembersInGroup);
	   			}
   			}

	   	}
	   	$assignResult[$key]->nbEtudiantsDansGroups =  ($nbUserInGroups == 0 ) ? get_string('noGroups', 'local_up1_examplanning')  :  $nbUserInGroups;

	   	$assignResult[$key]->nb_etudiants_inscrits  = $assignResult[$key]->nb_etudiants_inscrits ." ( " . get_string('estimated', 'local_up1_examplanning')  ." : " .$assignResult[$key]->nb_etudiants_inscrits * 0.4 .")";
	}
	return 	$assignResult;
}

?>
