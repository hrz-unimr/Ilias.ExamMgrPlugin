<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilObjExamMgr.php";
require_once "class.ilExamMgrCoursesList.php";
require_once "class.ilExamMgrRemoteCrs.php";

/**
 * Custom HTML form button to create a random password.
 */
class ilExamMgrCreateRandomPasswordButton extends ilExamMgrFormElement {
    /**
     * Render the button.
     *
     * Just a plain button with a Javascript event (see /js/examMgr.js)
     */
    public function render() {
        global $lng;
        return '<input type="button" value="' . $lng->txt("rep_robj_xemg_createRandomPW") . '" onclick="createRandomPW()" />';
    }
}

/**
 * Class for the "Create Course in Assessment System" form.
 *
 * Provides an overview of existing courses in the Assessment System and
 * add remote course/link with existing course/unlink course buttons.
 */
class ilExamMgrFormCreateCourseRemote extends ilExamMgrForm {
    public function __construct(ilObjExamMgrGUI $parent) {
        parent::__construct($parent);
        global $ilCtrl, $lng;
            
        $this->setTitle($lng->txt("rep_robj_xemg_assessmentCourses"));
		$this->setFormAction($ilCtrl->getFormAction($parent));

        if($this->parent->object->getStatus() >= ilObjExamMgr::STATUS_REMOTE) {
            $cl = new ilExamMgrCoursesList($parent);
            $this->addItem($cl);
        }
        $ti = new ilTextInputGUI($lng->txt("rep_robj_xemg_createCourseAssessmentSuffix"), "course_title");
        $ti->setInfo($lng->txt("rep_robj_xemg_createCourseAssessmentHint"));
        $this->addItem($ti);

        $ti = new ilTextInputGUI($lng->txt("rep_robj_xemg_linkCourseAssessment"), "course_ref_id");
        $ti->setInfo($lng->txt("rep_robj_xemg_linkCourseAssessmentHint"));
        $this->addItem($ti);

        $pw = new ilTextInputGUI($lng->txt("rep_robj_xemg_password"), "course_pw");
        $pw->setRequired(true);
        $this->addItem($pw);

        $pw_btn = new ilExamMgrCreateRandomPasswordButton($parent);
        $this->addItem($pw_btn);

        $this->addCommandButton("createCourseRemote", $lng->txt("rep_robj_xemg_createLinkCourseAssessment"));
        $this->setShowTopButtons(false);
    }

    /**
     * Handle the add/link action.
     *
     * The unlink action is handled directly in the {@see ilObjExamMgrGUI::unlinkCourse() parent GUI}.
     *
     * @uses ilExamMgrREST::post() ilExamMgrREST class
     */
    public function process() {

        global $tpl, $ilCtrl, $tree, $lng;
        if(!$this->checkInput()) {
            return false;
        }
        $title = $this->getInput('course_title');
        $refId = $this->getInput('course_ref_id');
        if($title){ // Create remote course with this title
            try{
                $rest = new ilExamMgrREST();
            } catch (HandledGuzzleException $e) {
                return false;
            }

            $path = $tree->getPathFull($this->plugin_obj->getRefId());
            // Course is penultimate entry in the path, last entry is plugin object.
            $courseTitle = $path[count($path)-2]['title'];
            $path = array_splice($path, 1, -2);
            $pathAsArrayOfTitles = array_map(function($elem) {return $elem['title'];}, $path);

            try {
                $rest->setHandleExceptions(false);
                $resp = $rest->get("examPlugin/refIdByPath/".implode("/", $pathAsArrayOfTitles));
            } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                $code = $e->getResponse()->getStatusCode();
                if($code == 404) {
                    ilUtil::sendFailure("REST Error: ". $e->getResponse()->json()['msg'], true);
                } else {
                    ilUtil::sendFailure($e->getMessage(), true);
                }
                return false;
            }

            $remote_ref_id = $resp['data']['finalRefId'];

            $course_name = $courseTitle . ": " . $title;

            $data = array('ref_id' => $remote_ref_id, 'title' => $course_name, 'description' => "per REST angelegter Klausurkurs");
            $jsonresp = $rest->post("v1/courses", $data);
            if(!$jsonresp) {
                return false;
            }
            $crs = new ilExamMgrRemoteCrs(0, $jsonresp['data']['newRefId'], $this->plugin_obj->getId(), $this->getInput('course_pw'));
            $crs->writeDB();
            $this->plugin_obj->setStatus(ilObjExamMgr::STATUS_REMOTE);
            $this->plugin_obj->update();
            $ticket = $this->plugin_obj->getTicket();
            $link = $crs->getPermalink();
            $message = sprintf($lng->txt("rep_robj_xemg_assessmentCourseCreated"), $link);
            if(!empty($ticket)) {
                $ticket->addReply($message);
            }
            $message = sprintf($lng->txt("rep_robj_xemg_assessmentCourseCreatedHTML"), $link);
            $this->plugin_obj->addLogMessage($message);

            ilUtil::sendSuccess($message, true);
            return true;
        } else if($refId) { // Link with (assumed to be) existing course on remote system.
            $this->plugin_obj->setStatus(ilObjExamMgr::STATUS_REMOTE);
            $crs = new ilExamMgrRemoteCrs(0, $refId, $this->plugin_obj->getId(), $this->getInput('course_pw'));
            $crs->writeDB();
            $this->plugin_obj->update();
            $ticket = $this->plugin_obj->getTicket();
            $link = $crs->getPermalink();
            $message = sprintf($lng->txt("rep_robj_xemg_assessmentCourseLinked"), $link);
            if(!empty($ticket)) {
                $ticket->addReply($message);
            }
            $message = sprintf($lng->txt("rep_robj_xemg_assessmentCourseLinkedHTML"), $link);
            $this->plugin_obj->addLogMessage($message);

            ilUtil::sendSuccess($message, true);
            return true;
        }
    }

}
