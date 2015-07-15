<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once './Services/PDFGeneration/classes/class.ilPDFGeneration.php';

/**
 * PDF generation class.
 *
 * Uses ILIAS' PDF generation facility
 * /Services/PDFGeneration/classes/class.ilPDFGeneration.php
 */
class ilExamMgrPDFGenerator {

    public function __construct(ilObjExamMgr $examObj){    
		
		$this->job = new ilPDFGenerationJob();
		$this->job->setAutoPageBreak(true)
			->setCreator('examMgr Plugin')
			->setMarginLeft('20')
			->setMarginRight('20')
			->setMarginTop('20')
			->setMarginBottom('20')
            ->setOutputMode("D");

        $this->examObj = $examObj;
    }

    /**
     * Create user lists for all runs of the exam.
     */
    public function createUserListAll() {
        global $lng;
        $runs = ilExamMgrRun::getRuns($this->examObj->getId());
        foreach($runs as $r) {
            $this->addPageForRun($r->id);
        }

        $this->job->setFilename(sprintf($lng->txt("rep_robj_xemg_printUserFilenameAll"), $this->examObj->getTitle()) . ".pdf");
        ilPDFGeneration::doJob($this->job);
    }

    /**
     * Add PDF page(s) for a single run (populate the ilPDFGenerationJob).
     *
     * @param int $runId DB id of a run.
     */
    private function addPageForRun($runId) {
        global $lng;
        $run = new ilExamMgrRun($runId);
        $run->doRead();

        $title = "{$this->examObj->getTitle()} - {$run->title}";
        $dateTime = $run->begin_ts;
        $room = (new ilExamMgrRoom($run->room))->doRead()->name;
        if($run->course > 0) {
            $courses = ilExamMgrRemoteCrs::getForExam($this->examObj->getId());
            $course = $courses[$run->course];
            $course->doRead();
            $password = $course->password;
        }

        global $ilPluginAdmin;  // todo make "getPluginDir" method and use everywhere
        $pl = $ilPluginAdmin->getPluginObject(IL_COMP_SERVICE, "Repository", "robj", "ExamMgr");
        $plugin_dir = $pl->getDirectory();

        $pdfTpl = new ilTemplate("tpl.pdfUserList.html", true, true, $plugin_dir);
        $pdfTpl->setVariable("NR_HDR", $lng->txt("rep_robj_xemg_printUserNo"));
        $pdfTpl->setVariable("LASTNAME_HDR", $lng->txt("rep_robj_xemg_last_name"));
        $pdfTpl->setVariable("FIRSTNAME_HDR", $lng->txt("rep_robj_xemg_first_name"));
        $pdfTpl->setVariable("LDAP_HDR", $lng->txt("rep_robj_xemg_printUserLDAP"));
        $pdfTpl->setVariable("ONEWAY_HDR", $lng->txt("rep_robj_xemg_printUserOneTime"));


        $pdfTpl->setVariable("TITLE", $title);
        $pdfTpl->setVariable("DATETIME", $dateTime);
        $pdfTpl->setVariable("PLACE", $room);
        if($run->course > 0) {
            $pdfTpl->setVariable("PASSWORD", $lng->txt("rep_robj_xemg_password") . ": $password");
        }
        $students = $run->getEnrolledStudents(true);
        $i = 1;
        foreach($students as $s){
            $pdfTpl->setCurrentBlock("student_row");
            if($i % 3 == 0) {
                $pdfTpl->setVariable("TR_ATTRIBS", 'class="oddRow"');
            }
            $pdfTpl->setVariable("NR", $i);
            $pdfTpl->setVariable("LASTNAME", $s->getLastName());
            $pdfTpl->setVariable("FIRSTNAME", $s->getFirstName());
            $pdfTpl->setVariable("LDAP", $s->getLDAP());
            if($run->course > 0) {
                $pdfTpl->setVariable("ONEWAY", $s->getOneWayAccount($course));
            }
            $pdfTpl->parseCurrentBlock();
            $i++;
        }
        $this->job->addPage($pdfTpl->get());
        $this->job->setFilename(sprintf($lng->txt("rep_robj_xemg_printUserFilenameOne"), $title) . ".pdf");
    }


    /**
     * Create a user list for a single run.
     *
     * @param int $runId DB id of a run.
     */
    public function createUserList($runId){    

        $this->addPageForRun($runId);
        ilPDFGeneration::doJob($this->job);
    }
}
