<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "./Services/Repository/classes/class.ilObjectPluginGUI.php";
require_once "./Modules/Course/classes/class.ilObjCourse.php";
require_once "Services/Form/classes/class.ilPropertyFormGUI.php";
require_once "class.ilObjExamMgrAccess.php";
require_once "class.ilExamMgrFormBasic.php";
require_once "class.ilExamMgrFormRuns.php";
require_once "class.ilExamMgrFormMailing.php";
require_once "class.ilExamMgrFormUserUpload.php";
require_once "class.ilExamMgrFormUserTransfer.php";
require_once "class.ilExamMgrFormUserManage.php";
require_once "class.ilExamMgrFormPrinting.php";
require_once "class.ilExamMgrFormExamTransfer.php";
require_once "class.ilExamMgrFormCreateCourseLocal.php";
require_once "class.ilExamMgrFormCreateCourseRemote.php";
require_once "class.ilTreeNode.php";
require_once "class.ilExamMgrStudentParser.php";

/**
 * Main user interface class.
 *
 * All actions of the plugin are started via HTTP requests that are handled by
 * {@see \ilObjExamMgrGUI::performCommand() the `performCommand` method of this class}. It performs
 * access checking and then calls the desired method(s).
 *
 * The user interface comprises multiple tabs: one for the client to see the current status of the
 * plugin (scheduled runs, enrolled students, ...) and several tabs for admins to fulfill management
 * tasks.
 *
 * The ilCtrl_* annotations are copied from the example Plugin,
 * {@link http://www.ilias.de/docu/ilias.php?ref_id=42&obj_id=29962&cmd=layout&cmdClass=illmpresentationgui&cmdNode=b0&baseClass=ilLMPresentationGUI ILIAS dev documentation}
 * @ilCtrl_isCalledBy ilObjExamMgrGUI: ilRepositoryGUI, ilAdministrationGUI, ilObjPluginDispatchGUI
 * @ilCtrl_Calls ilObjExamMgrGUI: ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI
 * @ilCtrl_Calls ilObjExamMgrGUI: ilCommonActionDispatcherGUI
 * @author Jasper Olbrich <olbrich@hrz.uni-marburg.de>
 */
class ilObjExamMgrGUI extends ilObjectPluginGUI
{
    /**
     * Initialisation, globally inject CSS and JS into the template.
     */
    protected function afterConstructor()
    {
        global $ilPluginAdmin, $tpl;
        $pl = $ilPluginAdmin->getPluginObject(IL_COMP_SERVICE, "Repository", "robj", "ExamMgr");
        $this->plugin_dir = $pl->getDirectory();
        $tpl->addJavaScript("{$this->plugin_dir}/js/examMgr.js");
        $tpl->addCss("{$this->plugin_dir}/templates/examMgr.css");
    }

    /**
     * Get plugin "type", abbreviation.
     * @return string "xemg", the short name of the plugin.
     */
    final public function getType()
    {
        return "xemg";
    }


    /**
     * Handles all commmands of this class, centralizes permission checks
     * @param string $cmd the command to perform.
     */
    public function performCommand($cmd)
    {
        switch ($cmd)
        {
        case "showClientView":      // Read-only view for client after request has been created.
        case "showBasicsEdit":      // Form to add additional data, usable only once during creation of new object.
        case "saveBasicsEdit":      // Process form, save data to database, create ticket.
            $this->checkPermission('read');
            $this->$cmd();
            break;
        case "showAdminView":       // General admin view to edit settings, import users, interaction with assessment system, ...
        case "showUserAddView":
        case "showUserManageView":
        case "createUserListPDF":
        case "showUserTransferView":
        case "showMailingView":
        case "sendMail":    // TODO: don't take round trip here for form submit actions?
        case "createCourseLocal":        // Create course locally, move exam manager object there.
        case "uploadUsers":
        case "manageUsers":
        case "createOneWayUsers":
        case "enrollLDAPUsers":
        case "createTestLink":
        case "addRun":
            $this->checkPermission('write');
            $this->$cmd();
            break;
        case "createCourseRemote":        // Create course in assessment system via REST.
            $this->$cmd();
            break;
        case "doRepoAutoComplete":  // AJAX-autocomplete for target course
            ilExamMgrFormCreateCourseLocal::doRepoAutoComplete();
            break;
        case "doNameAutoComplete":  // AJAX-autocomplete for target course
            ilExamMgrFormBasic::doNameAutoComplete();
            break;
        default:                    // handle special cases: {delete|edit|save}_run_$id
                                    // unlink_course_$id
            $matches = array();
            if(preg_match("/delete_run_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->deleteRun($id);
                break;
            } elseif (preg_match("/edit_run_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->editRun($id);
                break;
            } elseif (preg_match("/save_run_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->saveRun($id);
                break;
            } elseif (preg_match("/unlink_course_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->unlinkCourse($id);
                break;
            } elseif (preg_match("/fetch_test_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->fetchTest($id);
                break;
            } elseif (preg_match("/cleanup_test_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->cleanupTest($id);
                break;
            } elseif (preg_match("/unlink_test_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->unlinkTest($id);
                break;
            }
            error_log("unknown command $cmd");
        }
    }

    /**
     * Define command to execute after creation of this object.
     *
     * ILIAS first asks for a title and a description for a new object, this data is stored
     * in the db, then the command returned by this method is executed.
     * Here, it is used to get further data from the client.
     *
     * @return string "showBasicsEdit"
     */
    public function getAfterCreationCmd()
    {
        return "showBasicsEdit";
    }

    /**
     * Define command to execute when the object is clicked on in a list view.
     *
     * Depending on the current user's role, can be either the client or admin view.
     *
     * @return string
     */
    public function getStandardCmd()
    {
        global $ilUser;

        if(ilObjExamMgrAccess::isAdmin($ilUser->getID())){
            return "showAdminView";
        } else {
            return "showClientView";
        }
    }


    /**
     * Set up tabs. Depending on the user's role and the plugin object's status,
     * not all tabs are accessible.
     * */
    protected function setTabs()
    {
        global $ilTabs, $ilCtrl, $ilAccess, $ilUser;

        if(ilObjExamMgrAccess::isAdmin($ilUser->getID())){
            // Always show tabs for basic management and user adding
            $ilTabs->addTab("admin", $this->txt("adminView"), $ilCtrl->getLinkTarget($this, "showAdminView"));
            $ilTabs->addTab("userAdd", $this->txt("userAddView"), $ilCtrl->getLinkTarget($this, "showUserAddView"));
            if(ilExamMgrRun::getRuns($this->object->getId()) != null &&
                count(ilExamMgrStudent::getStudents($this->object->getId()))>0) {
                // Tabs for user management and mailing are shown only if there are users and runs.
                $ilTabs->addTab("userManage", $this->txt("userManageView"), $ilCtrl->getLinkTarget($this, "showUserManageView"));
                $ilTabs->addTab("userTransfer", $this->txt("userTransferView"), $ilCtrl->getLinkTarget($this, "showUserTransferView"));
                $ilTabs->addTab("mailing", $this->txt("mailingView"), $ilCtrl->getLinkTarget($this, "showMailingView"));
            }
        }

        // If the client did not yet submit the basic data, show the form.
        if ($this->object->getStatus() == ilObjExamMgr::STATUS_NEW ) {
            $ilTabs->addTab("clientEdit", $this->txt("clientEdit"), $ilCtrl->getLinkTarget($this, "showBasicsEdit"));
        }

        // Always show "read only" tab for client.
        $ilTabs->addTab("client", $this->txt("clientView"), $ilCtrl->getLinkTarget($this, "showClientView"));


        // standard info screen tab
        $this->addInfoTab();
        // standard epermission tab
        $this->addPermissionTab();
    }


    //////////////////////////////////////////////////////////////
    //                        Tabs                              //
    //////////////////////////////////////////////////////////////

    /**
     * Client view directly after creation of the object (with just title and
     * description. Ask for further details.
     */
    private function showBasicsEdit()
    {
        global $tpl, $ilTabs;
        $ilTabs->activateTab("basicsEdit");
        $form = new ilExamMgrFormBasic($this);
        $form->fill();
        $tpl->setContent($form->getHTML());
    }

    /**
     * General client view. Read-only overview of the exam's status.
     */
    private function showClientView()
    {
        global $tpl, $ilTabs;

        $ilTabs->activateTab("client");

        $this->clientViewTpl = new ilTemplate("tpl.courseAdminView.html", true, true, $this->plugin_dir);

        $this->clientViewTpl->setVariable("STATUS_LBL", $this->txt("status"));
        $status_str = ilObjExamMgr::getStatusI18n($this->object->getStatus());
        $this->clientViewTpl->setVariable("STATUS_VAL", $this->txt($status_str));

        $this->clientViewTpl->setVariable("OVERVIEW", $this->txt("course_adm_overview"));

        $this->addOverviewRow($this->txt('title'), $this->object->getExamTitle());
        $this->addOverviewRow($this->txt('date'), $this->object->getDate());
        $this->addOverviewRow($this->txt('time'), $this->object->getTime());
        $this->addOverviewRow($this->txt('examNumStudents'), $this->object->getNumStudents());

        $this->clientViewTpl->setVariable("LOG", $this->txt("course_adm_log"));
        $this->printLog();

        $this->clientViewTpl->setVariable("USER_OVERVIEW", $this->txt("user_list_overview"));
        $userMgmt = new ilExamMgrUserManageGUI($this, false);
        $userMgmt->setReadOnly(true);
        $this->clientViewTpl->setVariable("USER_LIST", $userMgmt->render());

        $tpl->setContent($this->clientViewTpl->get());
    }

    /**
     * Administration view (main tab).
     */
    private function showAdminView()
    {
        global $tpl, $ilTabs, $ilCtrl;
        $ilTabs->activateTab("admin");

        $html = ""; // Collector for all required forms.

        $ticket_host = ilExamMgrPlugin::getSetting("rt_path");
        $html .= "Links zu <a href=\"$ticket_host/Ticket/Display.html?id={$this->object->getTicketId()}\">Ticket</a>";

        $basicForm = new ilExamMgrFormBasic($this, true);
        $basicForm->fill(true);
        $html .= $basicForm->getHTML();

        $runForm = new ilExamMgrFormRuns($this);
        $html .= $runForm->getHTML();

        // Local (authoring course) must be created only once per plugin object.
        if($this->object->getStatus() == ilObjExamMgr::STATUS_REQUESTED) {
            $createLocalForm = new ilExamMgrFormCreateCourseLocal($this);
            $html .= $createLocalForm->getHTML();
        }

        // Remote (assessment course) must be created only if local course is created first.
        // Multiple remote courses are possible.
        if($this->object->getStatus() >= ilObjExamMgr::STATUS_LOCAL) {
            $createRemoteForm = new ilExamMgrFormCreateCourseRemote($this);
            $html .= $createRemoteForm->getHTML();
        }

        // Transfer of exams makes sense only if there is a remote course and an exam object here.
        if($this->object->getStatus() >= ilObjExamMgr::STATUS_REMOTE) {
            $exportForm = new ilExamMgrFormExamTransfer($this);
            if($exportForm->hasLocalExam()){    // TODO: handle case when there's no local but remote exam?
                $html .= $exportForm->getHTML();
            }
        }
        $tpl->setContent($html);
    }

    /**
     * User import view (add via C/P of plain text).
     */
    private function showUserAddView()
    {
        global $tpl, $ilTabs;
        $ilTabs->activateTab("userAdd");

        $userUploadForm = new ilExamMgrFormUserUpload($this);
        $tpl->setContent($userUploadForm->getHTML());
    }

    /**
     * User management view (move to or between runs).
     */
    private function showUserManageView() {
        global $tpl, $ilTabs;
        $ilTabs->activateTab("userManage");

        $printForm = new ilExamMgrFormPrinting($this);
        $manageForm = new ilExamMgrFormUserManage($this);

        $tpl->setContent($printForm->getHTML() . $manageForm->getHTML());
    }

    /**
     * View to transfer users to/create on assessment system.
     */
    private function showUserTransferView() {
        global $tpl, $ilTabs;
        $ilTabs->activateTab("userTransfer");

        $transferForm = new ilExamMgrFormUserTransfer($this);
        $tpl->setContent($transferForm->getHTML());
    }

    /**
     * Mailing view.
     */
    private function showMailingView() {
        global $tpl, $ilTabs;
        $ilTabs->activateTab("mailing");

        $sendMailForm = new IlExamMgrFormMailing($this);
        $tpl->setContent($sendMailForm->getHTML());
    }


    //////////////////////////////////////////////////////////////
    //                 Handling of submitted forms              //
    //////////////////////////////////////////////////////////////

    /**
     * Save or update basic data.
     */
    private function saveBasicsEdit()
    {
        $this->form = new ilExamMgrFormBasic($this);
        $this->form->save();
        // Form is responsible for redirect, depending on where the control flow came from.
    }

    /**
     * Add new run to this exam.
     */
    private function addRun() {
        global $ilCtrl;

        $runForm = new IlExamMgrFormRuns($this);
        if($runForm->process()) {
            $ilCtrl->redirect($this, "showAdminView");
        }
    }

    /**
     * Delete run from this exam.
     */
    private function deleteRun($id) {
        global $ilCtrl, $lng;

        $theRun = new ilExamMgrRun($id);
        $theRun->doDelete();
        ilUtil::sendSuccess($lng->txt("rep_robj_xemg_run_deleted"), true);
        $ilCtrl->redirect($this, 'showAdminView');
    }

    /**
     * Begin editing a run.
     */
    private function editRun($id) {
        global $ilCtrl, $tpl, $ilTabs;

        $ilTabs->activateTab("admin");
        $run = new ilExamMgrFormRuns($this, $id);
        $tpl->setContent($run->getHTML());
    }

    /**
     * Save a run after editing.
     */
    private function saveRun($id) {
        global $ilCtrl;

        $runForm = new ilExamMgrFormRuns($this, $id);
        if($runForm->process($id)) {
            $ilCtrl->redirect($this, "showAdminView");
        }
    }

    /**
     * Create a new course in the local repository and move the plugin object there.
     */
    private function createCourseLocal() {
        global $ilCtrl, $tpl, $ilTabs;
        $form = new ilExamMgrFormCreateCourseLocal($this);
        if($form->createAndMove()){
            $ilCtrl->redirect($this, 'showAdminView');
        } else {
            $ilTabs->activateTab("admin");
            $form->setValuesByPost();
            $tpl->setContent($form->getHTML());
        }
    }

    /**
     * Create course in remote (assessment) system via REST.
     */
    private function createCourseRemote() {
        global $ilCtrl;
        $form = new ilExamMgrFormCreateCourseRemote($this);
        $form->process();  // This can fail, but not due to wrong user input,
                           // so no need to display the form again.
        $ilCtrl->redirect($this, 'showAdminView');
    }

    /**
     * Unlink a remote course from this exam.
     *
     * @param int $id The DB id of the remote course to remove.
     */
    private function unlinkCourse($id) {
        global $ilCtrl;
        ilExamMgrRemoteCrs::deleteDb($id);
        ilUtil::sendSuccess("Unlinked remote course", true);
        $ilCtrl->redirect($this, 'showAdminView');
    }

    /**
     * Create a link to a test on the assessment system.
     *
     * Can be either an export/import from the authoring system or just a link
     * via ref ID to an existing test.
     */
    private function createTestLink() {
        global $ilCtrl;
        $form = new ilExamMgrFormExamTransfer($this);
        $form->createLink();
        $ilCtrl->redirect($this, "showAdminView");
    }

    /**
     * Fetch completed test (including results) from assessment system.
     *
     * @param int @id Local DB id of linked test.
     */
    private function fetchTest($id) {
        global $ilCtrl;
        $form = new ilExamMgrFormExamTransfer($this);
        $form->fetch($id);
        $ilCtrl->redirect($this, 'showAdminView');
    }

    /**
     * Convert any existing one-way accounts on the assessment system for the given
     * test to LDAP accounts.
     *
     * @param int $id Local DB id of linked test.
     */
    private function cleanupTest($id) {
        global $ilCtrl;
        $form = new ilExamMgrFormExamTransfer($this);
        $form->cleanup($id);
        $ilCtrl->redirect($this, 'showAdminView');
    }

    /**
     * Remove link to given test. Does *not* touch the assessment system at all.
     *
     * @param int $id Local DB id of linked test.
     */
    private function unlinkTest($id) {
        global $ilCtrl;
        $form = new ilExamMgrFormExamTransfer($this);
        $form->unlink($id);
        $ilCtrl->redirect($this, 'showAdminView');
    }


    /**
     * Add users to this examination
     */
    private function uploadUsers() {
        global $ilCtrl, $ilTabs, $tpl;
        $userUploadForm = new ilExamMgrFormUserUpload($this);
        $parser = new ilExamMgrStudentParser();
        if($userUploadForm->process($parser)) {
            $ilCtrl->redirect($this, 'showUserAddView');
        } else {
            $ilTabs->activateTab("userAdd");
            $userUploadForm->setValuesByPost();
            $userUploadForm->fillWithProblems();
            $tpl->setContent($userUploadForm->getHTML());
        }
    }

    /**
     * Change assignment of users to runs.
     */
    private function manageUsers() {
        global $ilCtrl;
        $form = new ilExamMgrFormUserManage($this);
        $form->process();
        $ilCtrl->redirect($this, 'showUserManageView');
    }

    /**
     * Create one-way users on assessment system.
     */
    private function createOneWayUsers() {
        global $ilCtrl, $ilTabs, $tpl;
        $form = new ilExamMgrFormUserTransfer($this);
        if($form->process(true)) {
            $ilCtrl->redirect($this, 'showUserTransferView');
        } else {
            $ilTabs->activateTab("userTransfer");
            $form->setValuesByPost();
            $tpl->setContent($form->getHTML());
        }

    }

    /**
     * Enroll/Create LDAP users on accessment system.
     */
    private function enrollLDAPUsers() {
        global $ilCtrl;
        $form = new ilExamMgrFormUserTransfer($this);
        $form->process(false);
        $ilCtrl->redirect($this, 'showUserTransferView');
    }

    /**
     * Create PDF lists of enrolled students.
     */
    private function createUserListPDF() {
        global $tpl, $ilTabs;
        $form = new ilExamMgrFormPrinting($this);
        if(!$form->process()) {
            $ilTabs->activateTab("userManage");
            $form->setValuesByPost();
            $tpl->setContent($form->getHTML());
        }

    }


    /**
     * Send invitation mail.
     */
    private function sendMail() {
        global $ilCtrl, $ilTabs, $tpl;
        $form = new ilExamMgrFormMailing($this);
        if($form->process()) {
            $ilCtrl->redirect($this, "showMailingView");
        } else {
            $ilTabs->activateTab("mailing");
            $form->setValuesByPost();
            $tpl->setContent($form->getHTML());
        }
    }


    // Helper methods to fill the client view.
    /**
     * Set a property/value in the client view template.
     *
     * @param string $prop
     * @param string $val
     */
    private function addOverviewRow($prop, $val){
        $this->clientViewTpl->setCurrentBlock("overview_table_row");
        $this->clientViewTpl->setVariable("PROPERTY", $prop);
        $this->clientViewTpl->setVariable("VALUE", $val);
        $this->clientViewTpl->parseCurrentBlock();
    }

    /**
     * Print the log messages to the client view template.
     */
    private function printLog() {
        $log = $this->object->getLogMessages();

        foreach($log as $l){
            $this->clientViewTpl->setCurrentBlock("log_table_row");
            $this->clientViewTpl->setVariable("TIMESTAMP", $l['timestamp']);
            $this->clientViewTpl->setVariable("USER", $l['username']);
            $this->clientViewTpl->setVariable("ENTRY", $l['entry']);
            $this->clientViewTpl->parseCurrentBlock();
        }
    }
}
