<?php

function maxOtherQuizAttempts($courseid, $instanceid) {
    global $DB;
    $query = 'select count(distinct(qa.id)) as nb_students_attempted
            from {quiz} q 
            inner join {course} c on c.id=q.course
            left join {quiz_attempts} qa on q.id=qa.quiz 
            where q.course = :courseid 
            and q.id <> :instanceid
            group by q.id
            order by nb_students_attempted desc limit 1'
            ;
    $r = $DB->get_record_sql($query, ['courseid'=> $courseid, 'instanceid' => $instanceid]);
    return $r->nb_students_attempted;
}

function maxOtherAssignAttempts($courseid, $instanceid) {
    global $DB;
    $query = 'select count(distinct(asu.id)) as nb_students_attempted
            from {assign_submission} asu 
            inner join {assign} a on a.id=asu.assignment
            inner join {course} c on c.id=a.course
            where a.course = :courseid 
            and asu.status="submitted"
            and a.id <> :instanceid
            group by a.id
            order by nb_students_attempted desc limit 1'
                    ;
    $r = $DB->get_record_sql($query, ['courseid'=> $courseid, 'instanceid' => $instanceid]);
    return $r->nb_students_attempted;
}

function nbUserInGroups($module_name, $instanceid) {
    global $DB;

    $sqlGetGroupId = 'select  cm.availability
                    from {course_modules} cm 
                    inner join {modules} m on cm.module=m.id
                    where instance= :key  
                    and m.name= :module_name';
    $availability = $DB->get_record_sql($sqlGetGroupId, ['key'=>$instanceid, 'module_name' => $module_name])->availability;
    if ($availability) {
        $checkId = json_decode($availability);
        foreach ($checkId->c as $keyGroup => $valueGroup) {
            if (isset($valueGroup->type) && !isset($valueGroup->id) && $valueGroup->type==="group")
                $availability=null; 
        }
    }
    if (!$availability) {
        $sqlGetGroupId = 'select  cs.availability
                        from {course_modules} cm 
                        inner join {course_sections} cs on cs.id=cm.section
                        inner join {modules} m on cm.module=m.id
                        where instance= :key  
                        and m.name= :module_name';
        $availability = $DB->get_record_sql($sqlGetGroupId, ['key'=>$instanceid, 'module_name' => $module_name])->availability;
    }
    if (!$availability) return null;
        
    $arrayId = json_decode($availability);
    $nb = 0;
    foreach ($arrayId->c as $keyGroup => $valueGroup) {
        if (isset($valueGroup->type) && isset($valueGroup->id) && $valueGroup->type==="group")
        {
            $count = 'select count(gm.userid) as nbusers
                                        from {groups_members} gm
                                        where groupid= :groupid ';
            $nb += $DB->count_records_sql($count,['groupid' =>$valueGroup->id]);
        }
    }
    return $nb;
}

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
			FROM_UNIXTIME(timeopen) as timeopen, FROM_UNIXTIME(timeclose) as timeclose,
			cm.id as cm_id
			from {quiz} q 
			left join {quiz_attempts} qa on q.id=qa.quiz 
			left join {quiz_slots} qs on qs.quizid = q.id
			inner join {course} c on c.id=q.course
                        left join {course_modules} cm on cm.course = c.id and cm.instance = q.id
			where timeopen >  :starttime		
			and timeopen <   :endtime	
			group by q.id
			order by timeopen
		';
    $quizResult = $DB->get_records_sql($sql,['starttime'=> $formdata->startTime, 'endtime'=>$formdata->endTime]);

    foreach ($quizResult as $key => $value) {
		$value->maxOtherAttempts= maxOtherQuizAttempts($value->courseid, $key);
        $value->nbEtudiantsDansGroups = nbUserInGroups('quiz', $key);
        //$nbUserInGroups == 0 ? get_string('noGroups', 'local_up1_examplanning') : $nbUserInGroups;
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
			FROM_UNIXTIME(allowsubmissionsfromdate) as timeopen, FROM_UNIXTIME(duedate) as timeclose,
			cm.id as cm_id
			from {assign} a
			inner join {course} c on c.id=a.course
                        left join {course_modules} cm on cm.course = c.id and cm.instance = a.id
			where duedate >  :starttime 
			and duedate < :endtime 
			group by a.id
			order by duedate,a.name
		';

   	$assignResult = $DB->get_records_sql($sql, ['starttime'=> $formdata->startTime, 'endtime'=>$formdata->endTime]);
    foreach ($assignResult as $key => $value) {
		$value->maxOtherAttempts = maxOtherAssignAttempts($value->courseid, $key);
        $value->nbEtudiantsDansGroups = nbUserInGroups('assign', $key);
	}
	return 	$assignResult;
}

$time_precision = 10;
$min_to_display = 700;

function to_date_h($s) {
    global $time_precision;
    if (!preg_match('/^(\d\d\d\d\-\d\d-\d\d) (\d\d):(\d\d):\d\d$/', $s, $m)) return null;
    list($_discard, $date, $h, $m) = $m;
    return [$date, $h * 60 + intval($m / $time_precision) * $time_precision];
}

function format_minutes($minutes) {
    return sprintf("%02d:%02d", $minutes / 60, $minutes % 60);
}

$by_time = [];
function by_time_may_add($dt, $cat, $link, $r, $ratio) {
    global $by_time;
    if (!isset($by_time[$dt])) $by_time[$dt] = [ 'courses' => [], 'l' => [] ];
    $e = &$by_time[$dt];
    $nbKinds = ['nbEtudiantsDansGroups' => '100_precis', 'maxOtherAttempts' => '80_estimation', 'nb_etudiants_inscrits' => '30_max'];
    foreach ($nbKinds as $field => $nbKind) {
        if ($r->$field) {
            if ($field === 'maxOtherAttempts' && $r->maxOtherAttempts > $r->nb_etudiants_inscrits) {
                //echo "skipping weird maxOtherAttempts<br>\n";
                continue;
            }
            if ($field === 'nb_etudiants_inscrits') {
                $index = @$e['courses'][$r->courseid];
                if (isset($index)) {
                        $prev = &$e['l'][$index];
                        $prev[3][] = $link;
                        break;
                } else {
                    $e['courses'][$r->courseid] = count($e['l']);
                }
            }
            $e['l'][] = [ $cat, $r->$field * $ratio, $nbKind, [$link] ];
            break;
        }
    }
}

function by_time_addQuiz($quizResult) {
    foreach ($quizResult as $r) {
        list($date, $start) = to_date_h($r->timeopen);
        list($date_, $end) = to_date_h($r->timeclose);
        if ($date !== $date_ || $end - $start > 5 * 60) continue; // ignore
    
        $link = "/mod/quiz/view.php?id=" . $r->cm_id;
    
        $dt = $date . ' ' . format_minutes($start); 
        by_time_may_add($dt, 'start_quiz', $link, $r, 1); 
    
        global $time_precision;
        if ($r->nb_pages > 1) { 
            for ($i = $start + $time_precision; $i < $end; $i += $time_precision) { 
                $dt = $date . ' ' . format_minutes($i); 
                by_time_may_add($dt, 'next_quiz', $link, $r, 0.9); 
            } 
        } else { 
            if ($end > $start + 2 * $time_precision) { 
                foreach ([0, 1, 2] as $i) { 
                    $dt = $date . ' ' . format_minutes($end - $i * $time_precision); 
                    by_time_may_add($dt, 'next_quiz', $link, $r, 1); 
                } 
            } 
        } 
    }
}

function by_time_addAssign($assignResult) {
    foreach ($assignResult as $r) {
        list($date, $start) = to_date_h($r->timeopen);
        list($date_, $end) = to_date_h($r->timeclose);
        if ($date !== $date_ || $end - $start > 5 * 60) continue; // ignore
    
        $link = "/mod/assign/view.php?id=" . $r->cm_id;
    
        $dt = $date . ' ' . format_minutes($start); 
        by_time_may_add($dt, 'start_assign', $link, $r, 1); 
    
        global $time_precision;
        if ($end > $start + 2 * $time_precision) { 
            foreach ([0, 1, 2] as $i) { 
                $dt = $date . ' ' . format_minutes($end - $i * $time_precision); 
                by_time_may_add($dt, 'end_assign', $link, $r, 1); 
            } 
        } 
    }
}

function by_time_addApogee($list) {
    $j = 0;
    foreach ($list as $r) {
        list($date, $start) = to_date_h($r->timeopen);
        list($date_, $end) = to_date_h($r->timeclose);
        if ($date !== $date_ || $end - $start > 5 * 60) continue; // ignore
    
        $link = "javascript:gotoApogee(" . $r->cod_pes . ")";
    
        $dt = $date . ' ' . format_minutes($start); 
        by_time_may_add($dt, 'start_assign', $link, $r, 1); 
    
        global $time_precision;
        if ($end > $start + 2 * $time_precision) { 
            foreach ([0, 1, 2] as $i) { 
                $dt = $date . ' ' . format_minutes($end - $i * $time_precision); 
                by_time_may_add($dt, 'end_assign', $link, $r, 1); 
            } 
        } 
        //if ($j++ >10 ) { global $by_time; echo json_encode($by_time);  break; }
    }
}

function by_time_format_nb($nb, $nbKind, $nbMax) {
    $val = (1 - $nbKind / 100) * 255;
    $color = $nb > 1000 ? "rgb(" . intval(intval($nb) / 3000 * 255) . ", $val, $val)" : "rgb($val, $val, $val)";
    return "<span style='color: $color'>" . intval($nb) . ($nbMax && $nbKind <= 80 ? "-" . intval($nbMax) : '') . "</span>";
}

function by_time_summarize($e) {
    $nb = $nbMax = 0;
    foreach ($e['l'] as $one) {
        $nbMax += $one[1];
        $nb += $one[1] * $one[2] / 100;
    }
    global $min_to_display;
    if ($nbMax < $min_to_display) return null;
    return by_time_format_nb($nb, intval($nb / $nbMax * 100), $nbMax);
}

function by_time_details($cat, $l) {
    $details = [];
    foreach ($l as $one) {
       if ($cat === $one[0]) {
         $text = by_time_format_nb($one[1], $one[2]);
         $s = '';
         foreach ($one[3] as $link) {
                $target = preg_match('/^javascript:/', $link) ? "" : "target='_blank'";
                $s .= "<a $target href='$link'>$text</a>";
                $text = 'M';
         }
         $details[] = $s;
       }
    }
    return implode("+", $details);
}

function by_time_display() {
    global $by_time;

    $today = strftime('%Y-%m-%d %H:%M');
    ksort($by_time);

echo <<<EOS
    <style>
    .by_time th {
       padding: 0 1rem;
    }
    .by_time td:first-child, .by_time td:nth-child(2) {
        font-size: 134%;
        white-space: nowrap;
    }
    .by_time .debug {
        font-size: 70%;
    }
    .by_time td {
        text-align: right;
        padding: 0.2rem 0.5rem;
    }
    .by_time .precis { color: #000; }
    .by_time .estimation { color: #444; }
    .by_time .max    { color: #aaa; font-style: italic; }
    .by_time .before {
        color: gray;
    }
    </style>
EOS;
    echo "<table class='by_time'>";
    echo "<tr><th>tranche horaire</th><th>total</th><th>début assign</th><th>fin assign</th><th>début quiz</th><th>suite quiz</th></tr>\n";

    $date_class = 'before';
    foreach ($by_time as $dt => $e) {
        if ($date_class && $dt >= $today) {
            $date_class = '';
            echo "<tr style='border: 1px dashed'></tr>";
        }

        $nb = by_time_summarize($e);
        if ($nb) {
                echo "<tr class='$date_class'><td class='$date_class'>$dt</td><td>$nb</td>" . 
                       "<td class='debug'>" . by_time_details('start_assign', $e['l']) . "</td>" . 
                       "<td class='debug'>" . by_time_details('end_assign', $e['l']) . "</td>" . 
                       "<td class='debug'>" . by_time_details('start_quiz', $e['l']) . "</td>" . 
                       "<td class='debug'>" . by_time_details('next_quiz', $e['l']) . "</td>" . 
                        "</tr>";
        }
    }
    echo "</table>";
}
?>

