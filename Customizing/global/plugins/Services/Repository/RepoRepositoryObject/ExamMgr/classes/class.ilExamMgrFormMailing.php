<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilObjExamMgr.php";
require_once "class.ilExamMgrMailer.php";

/**
 * Class for the invitaion mail form.
 */
class ilExamMgrFormMailing extends ilExamMgrForm {
    public function __construct(ilObjExamMgrGUI $parent) {
        parent::__construct($parent);
        global $ilCtrl, $lng;
            
        $this->setTitle($lng->txt("rep_robj_xemg_mailingView"));
		$this->setFormAction($ilCtrl->getFormAction($parent));

        $opt1 = new ilRadioOption($lng->txt("rep_robj_xemg_allStudents"), "all");
        $opt2 = new ilRadioOption($lng->txt("rep_robj_xemg_oneRun"), "run");

        $runSelector = ilExamMgrRun::getSelectorForExam($this->plugin_obj->getId(), false);
        $opt2->addSubItem($runSelector);

        $opt3 = new ilRadioOption($lng->txt("rep_robj_xemg_specificUsers"), "custom");
        $this->userManageGUI = new ilExamMgrUserManageGUI($parent, false, 'invite');
        $opt3->addSubItem($this->userManageGUI);

        $group = new ilRadioGroupInputGUI($lng->txt("rep_robj_xemg_mailRecipients"), "recipients");
        $group->addOption($opt1);
        $group->addOption($opt2);
        $group->addOption($opt3);
        $group->setRequired(true);

        $this->addItem($group);

        $ta = new ilTextAreaInputGUI($lng->txt("rep_robj_xemg_mailTemplate"), "template");
        $template = file_get_contents(__DIR__ . "/../templates/default/tpl.invitationMail.txt");    // bypass template engine
        $ta->setValue($template);
        $ta->setInfo(sprintf($lng->txt("rep_robj_xemg_mailTemplateHint"), implode(", ", ilExamMgrMailer::getTemplateSafePatterns())));
        $ta->setRows(15);
        $ta->setRequired(true);
        $this->addItem($ta);

        $this->addCommandButton("sendMail", $lng->txt("rep_robj_xemg_sendMail"));
        $this->setShowTopButtons(false);
    }

    /**
     * Send mail for all selected "student in run" combinations.
     *
     * @return bool Depending on success
     */
    public function process() {
        global $lng;

        $numSuccess = 0;
        $numFail = 0;
        // TODO: only send mails for future events.
        if($this->checkInput()) {
            $mailer = new ilExamMgrMailer();
            if(!$mailer->smtpConnect()){
                ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_mailNoSMTP"), $mailer->Host, $mailer->Port));
                return false;
            }

            $template = $this->getInput("template");
            $mailer->setTemplate($template);
            $mailer->setExam($this->plugin_obj);

            $recipients = $this->getInput("recipients");
            if($recipients == "custom" ){
                $selection = $this->userManageGUI->getSelection(true);
            } else { // get by run --> get runs and then get students for each run
                $ticket = $this->plugin_obj->getTicket();
                if($recipients == "all") {
                    $runs = ilExamMgrRun::getRuns($this->plugin_obj->getId());
                    $ticket->addReply($lng->txt("rep_robj_xemg_invitationAll"));
                } else {
                    $run = new ilExamMgrRun($this->getInput("target_run"));
                    $run->doRead();
                    $runs = array($run);
                    $ticket->addReply(sprintf($lng->txt("rep_robj_xemg_invitationRun"), $run->title));
                }

                $selection = array();
                foreach($runs as $run){
                    if($run->course == -1) {
                        ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_noRemote"), $run->title), true);
                        continue;
                    }
                    $students = $run->getEnrolledStudents();
                    foreach($students as $s) {
                        $selection[] = [$s, $run];
                    }
                }
            }
            $problems = array();
            foreach($selection as $sel) {
                list($s, $run) = $sel;
                $transferred = $s->getTransferred($run->id);
                if(!($transferred['ldap']) && !($transferred['oneway'])) {
                    $problems[] = sprintf($lng->txt("rep_robj_xemg_noRemoteAccount"), $s) ." ". $lng->txt("rep_robj_xemg_noMailSent");
                    continue;
                }
                $mailer->sendTemplate($s, $run);
            }
            $mailer->smtpClose();

            if($mailer->numSuccess) {
                ilUtil::sendSuccess(sprintf($lng->txt("rep_robj_xemg_mailSentSuccess"), $mailer->numSuccess, $mailer->Host, $mailer->Port), true);
            }
            if($mailer->numFail) {
                $problems[] = sprintf($lng->txt("rep_robj_xemg_mailSentFailure"), $mailer->numFail, $mailer->lastError, $mailer->errorStudent);
            }
            if(count($problems) > 0) {
                ilUtil::sendFailure(implode("<br />", $problems), true);
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

}
    

