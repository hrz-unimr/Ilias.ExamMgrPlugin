<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrUserManageGUI.php";

/**
 * User management form.
 *
 * Add users to runs or remove users from runs.
 */
class ilExamMgrFormUserManage extends ilExamMgrForm {

    public function __construct(ilObjExamMgrGUI $parent) {
        parent::__construct($parent);
        global $ilCtrl, $lng, $lng;

        $this->setTitle($lng->txt("rep_robj_xemg_userManageView"));

        $userManage = new ilExamMgrUserManageGUI($parent);
        $this->addItem($userManage);

        $this->addItem(ilExamMgrRun::getSelectorForExam($this->plugin_obj->getId(), false));

        $this->addCommandButton("manageUsers", $lng->txt("rep_robj_xemg_updateAssignments"));
        $this->setFormAction($ilCtrl->getFormAction($parent));
    }

    /**
     * Process the form.
     *
     * Users can be removed from runs and added to a run with a single submission
     * of this form.
     */
    public function process() {
        if($this->checkInput()) {
            $stud_ids = $this->getInput("checked_students");
            $target = $this->getInput("target_run");
            if(!is_null($stud_ids)) {
                foreach($stud_ids as $s) {
                    (new ilExamMgrStudent("first", "last", "matr", $s))->addToRun($target);
                }
            }

            $runs = ilExamMgrRun::getRuns($this->plugin_obj->getId());
            foreach($runs as $run) {
                if(isset($_REQUEST["run_id_{$run->id}"])) {
                    $toRemove = $_REQUEST["run_id_{$run->id}"];
                    foreach($toRemove as $s) {
                        (new ilExamMgrStudent("first", "last", "matr", $s))->removeFromRun($run->id);
                    }
                } else {
                    // noone to remove
                }
            }

        }
    }


}

