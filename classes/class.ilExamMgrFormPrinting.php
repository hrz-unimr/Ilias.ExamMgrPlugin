<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";

/**
 * Generation of PDF documents.
 */
class ilExamMgrFormPrinting extends ilExamMgrForm {

    public function __construct(ilObjExamMgrGUI $parent) {
		global $ilCtrl, $lng, $tpl;
        parent::__construct($parent);

        $this->setTitle($lng->txt("rep_robj_xemg_printingListTitle"));
        
        $optAllRuns = new ilRadioOption($lng->txt("rep_robj_xemg_printingAll"), "all");
        $optOneRun = new ilRadioOption($lng->txt("rep_robj_xemg_printingOne"), "one");
        $optOneRun->addSubItem(ilExamMgrRun::getSelectorForExam($this->plugin_obj->getId(), false));

        $group = new ilRadioGroupInputGUI($lng->txt("rep_robj_xemg_printingFor"), "print_for");
        $group->addOption($optAllRuns);
        $group->addOption($optOneRun);

        $group->setRequired(true);

        $this->addItem($group);

		$this->setFormAction($ilCtrl->getFormAction($parent));
        $this->setPreventDoubleSubmission(false);
        $this->addCommandButton("createUserListPDF", $lng->txt("rep_robj_xemg_printingSubmit"));

    }

    /**
     * Start generation of the desired PDF document.
     * Actual work is done by {@see ilExamMgrPDFGenerator PDF Generator class}.
     * Stops the php interpreter because only the PDF file must be sent.
     */
    public function process() {
        if(!$this->checkInput()) {
            return false;
        }
        require_once "class.ilExamMgrPDFGenerator.php";
        $pdfGen = new ilExamMgrPDFGenerator($this->plugin_obj);
        if($this->getInput("print_for") == "all") {
            $pdfGen->createUserListAll();
        } else {
            $pdfGen->createUserList($this->getInput("target_run"));
        }
        exit;
    }
}
 
