<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";

/**
 * Form element to manage user-run-assignments.
 */
class ilExamMgrUserManageGUI extends ilExamMgrFormElement {

    /**
     * Constructor.
     * @param ilObjExamMgrGUI $parent
     * @param bool $enableAdd Include column to add students to run?
     * @param string $action Action for selected (student, run) tuples, delete or invite
     */
    public function __construct(ilObjExamMgrGUI $parent, $enableAdd=true, $action="delete") {
        parent::__construct($parent);
        $this->enableAdd = $enableAdd;
        $this->action = $action;
        $this->readOnly = false;
    }

    public function setReadOnly($ro) {
        $this->readOnly = $ro;
    }

    /**
     * Render this form element.
     * @return string HTML fragment.
     */
    public function render() {
        global $tpl, $ilCtrl, $lng;
        // Decisions, decisions: fetch all runs/rooms, or use SQL join?
        $allStudents = ilExamMgrStudent::getStudents($this->plugin_obj->getId());
        $allRuns = ilExamMgrRun::getRuns($this->plugin_obj->getId());
        $allRooms = ilExamMgrRoom::getAllRooms();
        $studentMgmtTpl = new ilTemplate("tpl.userManagement.html", true, true, $this->parent->plugin_dir);
        $studentMgmtTpl->setVariable("USER_MANAGEMENT", "User Management");
        $studentMgmtTpl->setVariable("ALL_STUDENTS", $lng->txt("rep_robj_xemg_allStudents"));
        $studentMgmtTpl->setVariable("NUM_STUD", $lng->txt("rep_robj_xemg_numAllStudents") ." ". count($allStudents));
        $studentMgmtTpl->setVariable("COLSPAN_FIRST", ($this->readOnly || !$this->enableAdd) ? "1" : "2");
        if(!$this->readOnly && $this->enableAdd){
            $studentMgmtTpl->setCurrentBlock("add_action_header");   // No substitutions made, have to "touch" to include in parsed result.
            // $add_action = '<img class="xemg_check" src="./templates/default/images/icon_ok.svg" alt="add" />';
            $add_action = $lng->txt("rep_robj_xemg_addToRun");
            $studentMgmtTpl->setVariable("ADD_TO_RUN", $add_action);
            $studentMgmtTpl->parseCurrentBlock();
        }
        switch($this->action) {
        case 'delete':
            // $run_action = '<img class="xemg_check" src="./templates/default/images/icon_not_ok.svg" alt="entfernen" />';
            $run_action = $lng->txt("rep_robj_xemg_deleteFromRun");
            break;
        case 'invite':
            // $run_action = '<img class="xemg_check" src="./templates/default/images/icon_nwss.svg" alt="einladen" />';
            $run_action = $lng->txt("rep_robj_xemg_inviteToRun");
            break;
        case 'transfer':
            // $run_action = '<img class="xemg_check" src="./templates/default/images/icon_nwss.svg" alt="transfer" />';
            $run_action = $lng->txt("rep_robj_xemg_transferForRun");
            break;
        }
        foreach($allRuns as $r) {
            $studentMgmtTpl->setCurrentBlock("run_header");
            $studentMgmtTpl->setVariable("COLSPAN_RUN", $this->readOnly ? "1" : "2");
            $studentMgmtTpl->setVariable("RUN_TITLE", $r->title);
            $studentMgmtTpl->parseCurrentBlock();

            $studentMgmtTpl->setCurrentBlock("expl_header");
            $studentMgmtTpl->setVariable("TIME", $r->begin_ts);
            $studentMgmtTpl->setVariable("ROOM", $allRooms[$r->room]->name);
            $studentMgmtTpl->setVariable("NUM_RUN", $r->num);
            $studentMgmtTpl->setVariable("ROOM_CAPACITY", $allRooms[$r->room]->capacity);
            if(!$this->readOnly) {
                $studentMgmtTpl->setCurrentBlock("run_action_header");
                $studentMgmtTpl->setVariable("ACTION", $run_action);
                $studentMgmtTpl->parseCurrentBlock();
                $studentMgmtTpl->setCurrentBlock("expl_header");
            }
            $studentMgmtTpl->parseCurrentBlock();
        }

        $studentNo = 1;
        if($allStudents) {
            foreach($allStudents as $s) {
                $studentMgmtTpl->setCurrentBlock("table_row");
                $studentMgmtTpl->setVariable("STUDENT_NAME", $s->getFullName());
                $studentMgmtTpl->setVariable("STUDENT_NO", $studentNo++);

                if(!$this->readOnly && $this->enableAdd) {
                    $studentMgmtTpl->setCurrentBlock("add_to_run_selector");
                    $studentMgmtTpl->setVariable("FROM_TITLE", $lng->txt("rep_robj_xemg_fromTitle"));
                    $studentMgmtTpl->setVariable("TO_TITLE", $lng->txt("rep_robj_xemg_toTitle"));
                    $studentMgmtTpl->setVariable("NAME", "checked_students");
                    $studentMgmtTpl->setVariable("STUDENT_ID", $s->getId());
                    $studentMgmtTpl->parseCurrentBlock();
                    $studentMgmtTpl->setCurrentBlock("table_row");
                }
                
                $participation = ilExamMgrRun::getRunsIDsForStudent($s);
                $yes = '<img class="xemg_check" src="./templates/default/images/icon_checked.svg" alt="nimmt teil" />';
                // TODO: display remote account status? (has ldap, has one-way, has none)
                $studentMgmtTpl->setCurrentBlock("student_assignment");
                foreach($allRuns as $r) {
                    if(in_array($r->id, $participation)) {
                        $studentMgmtTpl->setVariable("PARTICIPATES", $yes);
                        if(!$this->readOnly) {
                            $studentMgmtTpl->setCurrentBlock("action_selector");
                            $studentMgmtTpl->setVariable("FROM_TITLE", $lng->txt("rep_robj_xemg_fromTitle"));
                            $studentMgmtTpl->setVariable("TO_TITLE", $lng->txt("rep_robj_xemg_toTitle"));
                            $studentMgmtTpl->setVariable("NAME", "run_id_{$r->id}");
                            $studentMgmtTpl->setVariable("STUDENT_ID", $s->getId());
                            $studentMgmtTpl->parseCurrentBlock();
                            $studentMgmtTpl->setCurrentBlock("student_assignment");
                        }
                    } else {
                        $studentMgmtTpl->setVariable("PARTICIPATES", "");
                        if(!$this->readOnly) {
                            $studentMgmtTpl->setCurrentBlock("empty_action_selector");
                            $studentMgmtTpl->touchBlock("empty_action_selector");
                            $studentMgmtTpl->parseCurrentBlock();
                            $studentMgmtTpl->setCurrentBlock("student_assignment");
                        }
                    }
                    $studentMgmtTpl->parseCurrentBlock();
                }
                $studentMgmtTpl->setCurrentBlock("table_row");
                $studentMgmtTpl->parseCurrentBlock();
            }
        }
        return $studentMgmtTpl->get();
    }

    /**
     * Get selected student/run combinations.
     * @param bool $enforceRemote If true, complain about and exclude runs
     * that are not yet linked with a remote course.
     * @return array array of (array of (ilExamMgrStudent, ilExamMgrRun), ...)
     */
    public function getSelection($enforceRemote=false) {
        global $lng;
        $selection = array();
        $runs = ilExamMgrRun::getRuns($this->plugin_obj->getId());
        foreach($runs as $run) {
            if(isset($_REQUEST["run_id_{$run->id}"])) {
                if($enforceRemote && $run->course == -1) {
                    ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_noRemote"), $run->title), true);
                    continue;
                }
                $student_ids = $_REQUEST["run_id_{$run->id}"];
                foreach($student_ids as $s_id) {
                    $s = new ilExamMgrStudent("first", "last", "matr", $s_id);
                    $s->doRead();
                    $selection[] = array($s, $run);
                }
            } else {
                // noone selected in this run
            }
        }
        return $selection;
    }

}

