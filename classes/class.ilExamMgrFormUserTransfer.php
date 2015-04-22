<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilObjExamMgr.php";
require_once "class.ilExamMgrMailer.php";
require_once "class.ilExamMgrREST.php";

/**
 * "Transfer" or create users on assessment system.
 *
 * Students can either use a "one-way" account that is explicitly created for
 * a single run, or use their students LDAP account.
 * If one-way accounts are used, they have to be converted to LDAP accounts
 * after the test has been completed, see the {@see ilExamMgrFormExamTransfer::cleanup() cleanup()}
 * method in ilExamMgrFormTransfer.
 */
class ilExamMgrFormUserTransfer extends ilExamMgrForm {
    public function __construct(ilObjExamMgrGUI $parent) {
        parent::__construct($parent);
        global $ilCtrl, $lng;
            
        $this->setTitle($lng->txt("rep_robj_xemg_userTransferView"));
		$this->setFormAction($ilCtrl->getFormAction($parent));

        $opt1 = new ilRadioOption($lng->txt("rep_robj_xemg_allStudents"), "all");
        $opt2 = new ilRadioOption($lng->txt("rep_robj_xemg_oneRun"), "run");

        $runSelector = ilExamMgrRun::getSelectorForExam($this->plugin_obj->getId(), false);
        $opt2->addSubItem($runSelector);

        $opt3 = new ilRadioOption($lng->txt("rep_robj_xemg_specificUsers"), "custom");
        $this->userManageGUI = new ilExamMgrUserManageGUI($parent, false, 'transfer');
        $opt3->addSubItem($this->userManageGUI);

        $group = new ilRadioGroupInputGUI($lng->txt("rep_robj_xemg_transferUsers"), "users");
        $group->addOption($opt1);
        $group->addOption($opt2);
        $group->addOption($opt3);
        $group->setRequired(true);

        $this->addItem($group);

        $this->addCommandButton("createOneWayUsers", $lng->txt("rep_robj_xemg_createOneWayUsers"));
        $this->addCommandButton("enrollLDAPUsers", $lng->txt("rep_robj_xemg_enrollLDAPUsers"));
        $this->setShowTopButtons(false);
    }


    /**
     * On remote system: create one-way account and enroll this account
     * to assessment course.
     * @param ilExamMgrStudent $student student to enroll
     * @param ilExamMgrRemoteCrs $remote_crs course to enroll into
     */
    private function addUserAndEnroll(ilExamMgrStudent $student, ilExamMgrRemoteCrs $remote_crs) {
        $userData = $this->createUserData($student);
        $userData['login'] = $student->getOneWayAccount($remote_crs);
        $userData['passwd'] = $remote_crs->password;
        $userData['auth_mode'] = "local";
        $json = $this->rest->post("v1/users", $userData);
        $userId = $json['data']['id'];
        $data = array("mode" => "by_id", "usr_id" => $userId, "crs_ref_id" => $remote_crs->remote_id);
        $response = $this->rest->post("v1/courses/enroll", $data);
        if($response['code'] == 200) {
            return true;
        } else {
            return $response['msg'];
        }
    }

    /**
     * On remote system: enroll LDAP account of student to course,
     * create account first if neccessary.
     * @param ilExamMgrStudent $student student to enroll
     * @param ilExamMgrRemoteCrs $remote_crs course to enroll into
     */
    private function enrollLDAP(ilExamMgrStudent $student, ilExamMgrRemoteCrs $remote_crs) {
        $ldapId = $student->getLDAP();

        $userData = $this->createUserData($student);    // Required if account not present on accessment system.
        $data = array("mode" => "by_login", "login" => $ldapId, "crs_ref_id" => $remote_crs->remote_id, "data" => $userData);
        $response = $this->rest->post("v1/courses/enroll", $data);
        if($response['code'] == 200) {
            return true;
        } else {
            return $response['msg'];
        }
    }

    /**
     * Prepare array with user datato create user account.
     * Field names are copied from ilObjUser->assignData.
     *
     * @param ilExamMgrStudent $student
     * @return array user data according to ilObjUser->assignData.
     */
    private function createUserData(ilExamMgrStudent $student) {
        return array(
            "gender" => $student->getGender(),
            "firstname" => $student->getFirstName(),
            "lastname" => $student->getLastName(),
            "email" => "{$student->getLDAP()}@students.uni-marburg.de"
        );
    }


    /**
     * Process this form.
     * Enroll students to appropriate remote course.
     * @param bool $createOneWayUsers create one-way accounts or use LDAP?
     * @return bool true on success
     */
    public function process($createOneWayUsers) {
        global $lng;
        $this->rest = new ilExamMgrREST(false);
        $numSuccess = 0;
        $numFail = 0;
        // TODO: check reusability of "user mgmt" form (put some of the processing there?)
        if(!$this->checkInput()) {
            return false;
        }

        $users = $this->getInput("users");

        $numSuccess = 0;
        $problems = array();

        $enrollments = [];  // Will hold arrays [student, run, remote course]
        if($users == "custom" ){
            $enrollments = $this->userManageGUI->getSelection();
            $runs = ilExamMgrRun::getRuns($this->plugin_obj->getId());
            $courses = array();
            foreach($runs as $r) {
                if($r->course != -1) {
                    $c = new ilExamMgrRemoteCrs($r->course);
                    $c->doRead();
                    $courses[$r->course] = $c;
                }
            }
            array_walk($enrollments, function (&$enrollArray) use ($courses) {$enrollArray[] = $courses[$enrollArray[1]->course];});
        } else {
            if($users == "all") {
                $runs = ilExamMgrRun::getRuns($this->plugin_obj->getId());
            } else {    // add users for one run only.
                $run = new ilExamMgrRun($this->getInput("target_run"));
                $run->doRead();
                $runs=array($run);
            }
            
            foreach($runs as $run){
                if($run->course == -1) {
                    ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_noRemote"), $run->title), true);
                    continue;
                }
                $course = new ilExamMgrRemoteCrs($run->course);
                $course->doRead();
                $runStudents = $run->getEnrolledStudents();
                foreach($runStudents as $rs) {
                    $enrollments[] = [$rs, $run, $course];
                }
            }
        }

        foreach($enrollments as $e){
            list($s, $run, $course) = $e;
            if($createOneWayUsers) {
                if($s->getTransferred($run->id)['oneway']) {
                    $problems[] = sprintf($lng->txt("rep_robj_xemg_alreadyOneWay"), $s, $run);
                    continue;
                }
                $s->setTransferredOneway($run->id);
                try {
                    $ret = $this->addUserAndEnroll($s, $course);
                } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                    ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_transferUsersOneWayFail"), $e->getResponse()->json()['msg']), true);
                    return false;
                }
            } else {
                if($s->getTransferred($run->id)['ldap']) {
                    $problems[] = sprintf($lng->txt("rep_robj_xemg_alreadyLdap"), $s, $run);
                    continue;
                }
                $s->setTransferredLdap($run->id);
                try {
                    $ret = $this->enrollLDAP($s, $course);
                } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                    ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_transferUsersLDAPFail"), $e->getResponse()->json()['msg']), true);
                    return false;
                }
            }
            if($ret === true) {
                $numSuccess ++;
            } else {
                $problems[] = $ret;
            }
        }
        if($numSuccess) {
            ilUtil::sendSuccess(sprintf($lng->txt("rep_robj_xemg_transferUsersSuccess"), $numSuccess), true);
        }
        if(count($problems)>0) {
            $problem_string = implode("<br /", $problems);
            ilUtil::sendFailure(sprintf($lng->txt("rep_robj_xemg_transferUsersProblems"), $problem_string), true);
        }
        return true;
    }

}
    

