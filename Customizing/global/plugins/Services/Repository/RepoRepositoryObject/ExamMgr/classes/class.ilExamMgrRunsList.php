<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilExamMgrRun.php";

/**
 * Form element to display a table of all runs.
 */
class ilExamMgrRunList extends ilExamMgrFormElement {

    public function __construct($parent) {
        parent::__construct($parent);
    }

    /**
     * Render the element.
     *
     * Creates the neccessary HTML to display the table with runs
     * and buttons to edit or delete each run.
     *
     * @return string HTML fragment.
     */
    public function render() {
        global $tpl, $lng;
        $runListTpl = new ilTemplate("tpl.runsList.html", true, true, $this->parent->plugin_dir);

        $id = $this->plugin_obj->getId();
        $runs = ilExamMgrRun::getRuns($id);
        if(!is_null($runs)){
            $runListTpl->setVariable("TITLE_HDR", $lng->txt("rep_robj_xemg_run_title"));
            $runListTpl->setVariable("ROOM_HDR", $lng->txt("rep_robj_xemg_run_room"));
            $runListTpl->setVariable("BEGIN_HDR", $lng->txt("rep_robj_xemg_run_start"));
            $runListTpl->setVariable("END_HDR", $lng->txt("rep_robj_xemg_run_end"));
            $runListTpl->setVariable("COURSE_HDR", $lng->txt("rep_robj_xemg_course_title"));
            $runListTpl->setVariable("TYPE_HDR", $lng->txt("rep_robj_xemg_run_type"));

            $courses = ilExamMgrRemoteCrs::getForExam($id);
            $types = ilExamMgrRun::getRunTypes();
            $rooms = ilExamMgrRoom::getAllRooms();

            foreach($runs as $run) {
                $runListTpl->setCurrentBlock("runs_table_row");
                $runListTpl->setVariable("TITLE", $run->title);
                $runListTpl->setVariable("ROOM", $rooms[$run->room]->name);
                $runListTpl->setVariable("BEGIN", $run->begin_ts);
                $runListTpl->setVariable("END", $run->end_ts);
                $runListTpl->setVariable("TYPE", $types[$run->type]);
                $runListTpl->setVariable("COURSE", $courses[$run->course]->remote_title);
                $runListTpl->setVariable("EDIT_CMD", "edit_run_{$run->id}");
                $runListTpl->setVariable("EDIT_TXT", $lng->txt("rep_robj_xemg_run_edit"));
                $runListTpl->setVariable("DELETE_CMD", "delete_run_{$run->id}");
                $runListTpl->setVariable("DELETE_TXT", $lng->txt("rep_robj_xemg_run_delete"));
                $runListTpl->parseCurrentBlock();
            }
        } else {
            $runListTpl->setVariable("RUNS_HEADER", $lng->txt("rep_robj_xemg_run_no_runs"));
        }
        return $runListTpl->get();
    }
}

