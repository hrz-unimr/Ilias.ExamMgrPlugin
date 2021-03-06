<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilHorizontalRadioGroupInputGUI.php";
require_once 'class.ilExamMgrStudent.php';

/**
 * Form to add new users to an exam.
 */
class ilExamMGrFormUserUpload extends ilExamMgrForm {

    public function __construct(ilObjExamMgrGUI $parent) {
		global $ilCtrl, $lng;
        parent::__construct($parent);

        $this->setTitle($lng->txt("rep_robj_xemg_uploadUsers"));

        $rgMatriculation = new ilHorizontalRadioGroupInputGUI($lng->txt("rep_robj_xemg_user_import_col_matr"), "matriculation_col");
        $opt_3_1 = new ilRadioOption("1", "1");
        $rgMatriculation->addOption($opt_3_1);
        $opt_3_2 = new ilRadioOption("2", "2");
        $rgMatriculation->addOption($opt_3_2);
        $opt_3_3 = new ilRadioOption("3", "3");
        $rgMatriculation->addOption($opt_3_3);
        $opt_3_none = new ilRadioOption("n/a", "0");
        $rgMatriculation->addOption($opt_3_none);
        $rgMatriculation->setValue("0");
        $rgMatriculation->setRequired(true);
        $this->addItem($rgMatriculation);

        $rgLastName = new ilHorizontalRadioGroupInputGUI($lng->txt("rep_robj_xemg_user_import_col_last"), "last_name_col");
        $opt_2_1 = new ilRadioOption("1", "1");
        $rgLastName->addOption($opt_2_1);
        $opt_2_2 = new ilRadioOption("2", "2");
        $rgLastName->addOption($opt_2_2);
        $opt_2_3 = new ilRadioOption("3", "3");
        $rgLastName->addOption($opt_2_3);
        $opt_2_none = new ilRadioOption("n/a", "0");
        $rgLastName->addOption($opt_2_none);
        $rgLastName->setValue("0");
        $rgLastName->setRequired(true);
        $this->addItem($rgLastName);

        $rgFirstName = new ilHorizontalRadioGroupInputGUI($lng->txt("rep_robj_xemg_user_import_col_first"), "first_name_col");
        $opt_1_1 = new ilRadioOption("1", "1");
        $rgFirstName->addOption($opt_1_1);
        $opt_1_2 = new ilRadioOption("2", "2");
        $rgFirstName->addOption($opt_1_2);
        $opt_1_3 = new ilRadioOption("3", "3");
        $rgFirstName->addOption($opt_1_3);
        $opt_1_none = new ilRadioOption("n/a", "0");
        $rgFirstName->addOption($opt_1_none);
        $rgFirstName->setValue("0");
        $rgFirstName->setRequired(true);
        $this->addItem($rgFirstName);

        $rgStudentId = new ilHorizontalRadioGroupInputGUI($lng->txt("rep_robj_xemg_user_import_col_ldap"), "acc_name_col");
        $opt_1_1 = new ilRadioOption("1", "1");
        $rgStudentId->addOption($opt_1_1);
        $opt_1_2 = new ilRadioOption("2", "2");
        $rgStudentId->addOption($opt_1_2);
        $opt_1_3 = new ilRadioOption("3", "3");
        $rgStudentId->addOption($opt_1_3);
        $opt_1_none = new ilRadioOption("n/a", "0");
        $rgStudentId->addOption($opt_1_none);
        $rgStudentId->setValue("0");
        $rgStudentId->setRequired(true);
        $this->addItem($rgStudentId);

        $this->addItem(ilExamMgrRun::getSelectorForExam($this->plugin_obj->getId()));

        $textarea = new ilTextAreaInputGUI($lng->txt("rep_robj_xemg_user_import_list"), "userImportList");
        $textarea->setRequired(true);
        $textarea->setRows(10);
        $textarea->setInfo($lng->txt("rep_robj_xemg_user_import_hint"));
        $this->addItem($textarea);
        $this->textarea = $textarea;

		$this->setFormAction($ilCtrl->getFormAction($parent));
        $this->addCommandButton("uploadUsers", $lng->txt("rep_robj_xemg_uploadUsers"));
        $this->setShowTopButtons(false);
    }

    /**
     * Process the form.
     *
     * @param ilExamMgrStudentParser $parser Parser from pasted plain text to {@see ilExamMgrStudent student objects}.
     * @return bool depending on success.
     */
    public function process($parser) {
        global $lng;

        if(!$this->checkInput()) {
            return false;
        }
        $text = $this->getInput("userImportList");
        $mat_col = (int) $this->getInput("matriculation_col");
        $last_name_col = (int) $this->getInput("last_name_col");
        $first_name_col = (int) $this->getInput("first_name_col");
        $acc_name_col = (int) $this->getInput("acc_name_col");
        $target_run = (int) $this->getInput("target_run");

        try {
            list($good, $problems) = $parser->parseText($text, $mat_col, $first_name_col, $last_name_col, $acc_name_col);
            $this->problems = $problems;
        } catch (Exception $e) {
            ilUtil::sendFailure($e->getMessage(), true);
            return false;
        }

        $actuallyGood = 0;
        if(count($good) > 0) {
            foreach($good as $g) {
                $s = new ilExamMgrStudent($g[0], $g[1], $g[2], $g[3], $g[4], $g[5]);
                if(($error = $s->saveToDB($this->plugin_obj->getId())) !== true) {
                    $this->problems[] = "#" . $error;
                    continue;
                }
                $actuallyGood++;
                if($target_run != 0) {
                    $s->addToRun($target_run);
                }
            }
            if($actuallyGood > 0) {
                ilUtil::sendSuccess(sprintf($lng->txt("rep_robj_xemg_user_import_success"), $actuallyGood), true);
            }
        }

        if(count($this->problems) > 0) {
            ilUtil::sendFailure($lng->txt("rep_robj_xemg_user_import_fail"), true);
            return false;
        }
        return true;
    }

    /**
     * Fill the form's text area with error students.
     *
     * E.g. student not found, or duplicate name.
     */
    public function fillWithProblems() {
        $this->textarea->setValue(implode("\n", $this->problems));
    }
}
