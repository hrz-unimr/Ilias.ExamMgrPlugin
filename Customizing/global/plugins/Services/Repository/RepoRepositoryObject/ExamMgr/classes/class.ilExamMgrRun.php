<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

/**
 * Data class for a run.
 *
 * A run is taken by several students who take the exam. There can be multiple runs 
 * per exam, for example in different rooms, or the same exam is taken by two groups
 * in the same room in direct succession.
 */
class ilExamMgrRun {

    /**
     * Constructor.
     *
     * Create a new run with the given settings.
     *
     * @param int $id Local DB id.
     * @param string $title Title of the run.
     * @param string $begin_ts Start of run, timestamp with format Y-m-d H:i:s {@see IL_CAL_DATETIME}.
     * @param string $end_ts End of run, timestamp with format Y-m-d H:i:s {@see IL_CAL_DATETIME}..
     * @param int $type Type of run, index of array returned by {@see getRunTypes()}.
     * @param ilObjExamMgr $plugin_obj Parent plugin object.
     * @param int $num Number of participating students.
     * @param int $room Local DB id of the room for this run.
     * @param int $course Local DB id of the remote course this run is about to take place in.
     */
    public function __construct($id=0, $title='', $begin_ts='', $end_ts='', $type='', $plugin_obj=NULL, $num=0, $room=0, $course=0) {
        error_log($begin_ts);
        $this->title = $title;
        $this->begin_ts = $begin_ts;
        $this->end_ts = $end_ts;
        $this->type = $type;
        $this->plugin_obj = $plugin_obj;
        $this->id = $id;
        $this->num = $num;
        $this->room = $room;
        $this->course = $course;
    }

    public function __toString() {
        return "Run {$this->title} from {$this->begin_ts} till {$this->end_ts}, type " . self::getRunTypes()[$this->type];
    }

    /**
     * Get supported run types.
     */
    public static function getRunTypes() {
        global $lng;
        return array($lng->txt("rep_robj_xemg_runParallel"),
            $lng->txt("rep_robj_xemg_runSuccessive"),
            $lng->txt("rep_robj_xemg_runInspection"));
    }

    /**
     * Create DB entry for this run.
     */
    public function doCreate(){
        global $ilDB;

        $id = $ilDB->nextID("rep_robj_xemg_runs");    // get next sequence number (DB independent AUTO_INCREMENT)
        $ilDB->manipulate($ins = "INSERT INTO rep_robj_xemg_runs".
                                 " (id, obj_id, begin_ts, end_ts, type, title, room, course) VALUES".
                                 " ( ".
                                 $ilDB->quote($id, 'integer') . ", " .
                                 $ilDB->quote($this->plugin_obj->getId(), 'integer') . ", " .
                                 $ilDB->quote($this->begin_ts, 'timestamp') . ", " .
                                 $ilDB->quote($this->end_ts, 'timestamp') . ", " .
                                 $ilDB->quote($this->type, 'integer') . ", " .
                                 $ilDB->quote($this->title, 'text') . ", ".
                                 $ilDB->quote($this->room, 'integer') . ", ".
                                 $ilDB->quote($this->course, 'integer') .
                                 ")");
    }

    /**
     * Read information for this run from DB, based on run id.
     */
    public function doRead() {
        global $ilDB;

        $res = $ilDB->query($q = "SELECT * FROM rep_robj_xemg_runs ".
            "WHERE id=".$ilDB->quote($this->id, "integer"));
        $row = $ilDB->fetchAssoc($res);

        $this->title = $row['title'];
        $this->begin_ts = $row['begin_ts'];
        $this->end_ts = $row['end_ts'];
        $this->type = $row['type'];
        $this->room = $row['room'];
        $this->course = $row['course'];
    }

    /**
     * Update this run in DB.
     */
    public function doUpdate() {
        global $ilDB;

        $ilDB->manipulate($ins = "UPDATE rep_robj_xemg_runs".
            " SET title = " . $ilDB->quote($this->title, 'text') . ", ".
            " begin_ts = " . $ilDB->quote($this->begin_ts, 'timestamp') . ", ".
            " end_ts = " . $ilDB->quote($this->end_ts, 'timestamp') . ", ".
            " type = " . $ilDB->quote($this->type, 'integer') . ", ".
            " room = " . $ilDB->quote($this->room, 'integer') . ", ".
            " course = " . $ilDB->quote($this->course, 'integer') .
            " WHERE id = " . $ilDB->quote($this->id, 'integer'));
    }

    /**
     * Delete this run.
     *
     * Simulates "ON DELETE CASCADE" for students participating in this run.
     */
    public function doDelete() {
        global $ilDB;
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_stud_run where run_id = ".$ilDB->quote($this->id, 'integer'));
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_runs WHERE id = ".$ilDB->quote($this->id, 'integer'));
    }

    /**
     * Get list of all students that are participating in this run.
     *
     * @param bool $sort Sort by last name, first name?
     * @return array Array of ilExamMgrStudent objects.
     */
    public function getEnrolledStudents($sort=false) {
        global $ilDB;

        if($sort) {
            $q = "SELECT s.firstname, s.lastname, s.id, s.gender, s.matriculation, s.ldapuid" .
                 " FROM `rep_robj_xemg_stud_run` as sr" .
                 " JOIN `rep_robj_xemg_students` as s" .
                 " ON s.id = sr.student_id" .
                 " WHERE run_id=". $ilDB->quote($this->id, 'integer') .
                 " ORDER BY s.lastname, s.firstname";
            $res = $ilDB->query($q);
            $students = array();
            while($row = $ilDB->fetchAssoc($res)) {
                $s = new ilExamMgrStudent($row['firstname'], $row['lastname'], $row['matriculation'], $row['student_id'], $row['ldapuid'], $row['gender']);
                $students[] = $s;
            }
        } else {
            $q = "SELECT student_id from rep_robj_xemg_stud_run WHERE run_id = ".$ilDB->quote($this->id, 'integer');
            $res = $ilDB->query($q);
            $students = array();
            while($row = $ilDB->fetchAssoc($res)) {
                $s = new ilExamMgrStudent("", "", "", $row['student_id']);
                $s->doRead();
                $students[] = $s;
            }
        }
        return $students;
    }

    /**
     * Get all runs for a given exam.
     *
     * @param int $obj_id Object id of exam manager plugin.
     * @return array Array of ilExamMgrRun objects.
     */
    public static function getRuns($obj_id) {
        global $ilDB;

        $res = $ilDB->query("SELECT *, COUNT(*) as cnt FROM rep_robj_xemg_runs as r ".
        "LEFT JOIN `rep_robj_xemg_stud_run` AS `sr` ON r.id = sr.run_id ".
                                    "WHERE obj_id=".$ilDB->quote($obj_id, "integer")." ".
                                    "GROUP BY r.id ".
                                    "ORDER BY begin_ts, r.id ");    // TODO order by id neccessary?!
        if($ilDB->numRows($res) === 0) {
            return NULL;
        } else {
            $runs = array();
            while($row = $ilDB->fetchAssoc($res)) {
                if(is_null($row['student_id'])) {
                    $num = 0;
                } else {
                    $num = $row['cnt'];
                }
                $runs[] = new ilExamMgrRun($row['id'], $row['title'], $row['begin_ts'], $row['end_ts'], $row['type'], $obj_id, $num, $row['room'], $row['course']);
            }
            return $runs;
        }
    }

    /**
     * Get select box for all runs of an exam.
     *
     * @param int $examObjId Object id of exam.
     * @param bool $allowNone Include a "no run" entry in the select box?
     * @return ilSelectInputGUI, form element name is "target_run".
     */
    public static function getSelectorForExam($examObjId, $allowNone=true) {
        global $lng;

        $runSelector = new ilSelectInputGUI($lng->txt("rep_robj_xemg_targetRun"), "target_run");
        $runs = self::getRuns($examObjId);
        if($allowNone) {
            $opts = array(0 => $lng->txt("rep_robj_xemg_noRunYet"));
        }
        foreach($runs as $run) {
            $opts[$run->id] = $run->title;
        }

        $runSelector->setOptions($opts);
        return $runSelector;
    }

    /**
     * Get all runs (id only) in which a student participates.
     *
     * @param ilExamMgrStudent $student
     * @return array Array of integers: DB ids of runs the student participates in.
     */
    public static function getRunsIDsForStudent($student) {
        global $ilDB;
        $res = $ilDB->query($q = "SELECT * FROM rep_robj_xemg_stud_run as sr ".
                            "JOIN rep_robj_xemg_runs as r ".
                            "ON r.id = sr.run_id ".
                            "WHERE student_id=".$ilDB->quote($student->getId(), "integer")." ".
                            "ORDER BY begin_ts");
        $ids = array();
        while($row = $ilDB->fetchAssoc($res)) {
            $ids[] = $row['run_id'];
        }
        return $ids;
    }

}

