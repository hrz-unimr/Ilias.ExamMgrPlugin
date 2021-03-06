<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilObjExamMgr.php";
require_once "./Modules/Test/classes/class.ilObjTest.php";
require_once "class.ilExamMgrREST.php";
require_once "class.ilExamMgrRemoteCrs.php";
require_once "class.ilExamMgrTestsList.php";

/**
 * Class for the handling of (remote) tests.
 */
class ilExamMgrFormExamTransfer extends ilExamMgrForm {
    public function __construct(ilObjExamMgrGUI $parent) {
        parent::__construct($parent);
        global $ilCtrl, $lng, $tree;

        $pluginRefId = $this->parent->object->getRefId();
        $parentRefId = $tree->getParentId($pluginRefId);
        $nodeData = $tree->getNodeData($parentRefId);
        $this->subtree = $tree->getSubTree($nodeData, false, array('tst'));

        $opts = array();
        foreach($this->subtree as $testId) {
            $test = new ilObjTest($testId);
            $test->read();
            $opts[$testId] = $test->getTitle();
        }
//        $opts[-1] = $lng->txt("rep_robj_xemg_test_linked_no_title");

        $remote_crs_titles = ilExamMgrRemoteCrs::getForExam($this->plugin_obj->getId());
        $tests = new ilExamMgrTestsList($parent, $opts, $remote_crs_titles);
        $this->addItem($tests);

        $copyTest = new ilRadioOption($lng->txt("rep_robj_xemg_transferExamCopy"), "copy_test");

        $testSelector = new ilSelectInputGUI($lng->txt("rep_robj_xemg_testSelection"), "test_id");
        $testSelector->setOptions($opts);
        $copyTest->addSubItem($testSelector);


        $linkTest = new ilRadioOption($lng->txt("rep_robj_xemg_transferExamLink"), "link_test");
        $remoteId = new ilNumberInputGUI($lng->txt("rep_robj_xemg_transferExamRemoteID"), "remote_ref_id");
        $remoteId->setRequired(true);
        $linkTest->addSubItem($remoteId);

        $radioGroup = new ilRadioGroupInputGUI($lng->txt("rep_robj_xemg_transferExamSource"), "test_source");
        $radioGroup->addOption($copyTest);
        $radioGroup->addOption($linkTest);

        $this->addItem($radioGroup);

        $this->addItem(ilExamMgrRemoteCrs::getSelectorForExam($this->plugin_obj->getId()));

        $this->setTitle($lng->txt("rep_robj_xemg_transferExam"));
        $this->setDescription($lng->txt("rep_robj_xemg_transferExamExpl"));
		$this->setFormAction($ilCtrl->getFormAction($parent));


        $this->addCommandButton("createTestLink", $lng->txt("rep_robj_xemg_transferExam"));
        $this->setShowTopButtons(false);
    }

    /**
     * Determine whether or not this exam has a local exam that can be transferred.
     * @return bool `true` iff so.
     */
    public function hasLocalExam() {
        return count($this->subtree) > 0;
    }

    /**
     * Central entry point to create a link to a test.
     *
     * Depending on the submitted form data, this can be either a import/export action,
     * or just the creation of a local DB entry to link an existing test on the
     * assessment system.
     *
     * @return bool result of action (`true` iff successfull)
     */
    public function createLink() {
        if(!$this->checkInput()){
            return false;
        }

        $remote_crs = new ilExamMgrRemoteCrs($this->getInput('course_id'));
        $remote_crs->doRead();

        if($this->getInput("test_source") == "copy_test") {
            return $this->copyTest($remote_crs);
        } else {
            return $this->linkTest($remote_crs);
        }
    }

    /**
     * Create a link to an existing test on the assessment system via the test's ref ID.
     *
     * @param ilExamMgrRemoteCrs $remote_crs
     * @return bool true
     */
    private function linkTest($remote_crs) {
        global $ilDB, $lng;
        $id = $ilDB->nextID("rep_robj_xemg_tests");    // get next sequence number (DB independent AUTO_INCREMENT)
        $remoteRefId = $this->getInput("remote_ref_id");
        $ilDB->manipulate($ins = "INSERT INTO rep_robj_xemg_tests".
                                 " (id, local_ref_id, remote_ref_id, remote_crs_ref_id, exam_obj_id) VALUES".
                                 " ( ".
                                 $ilDB->quote($id, 'integer') . ", " .
                                 $ilDB->quote(-1, 'integer') . ", " .
                                 $ilDB->quote($remoteRefId, 'integer') . ", " .
                                 $ilDB->quote($remote_crs->remote_id, 'integer') . ", " .
                                 $ilDB->quote($this->plugin_obj->getId(), 'integer') .")"); 
        $message = $lng->txt("rep_robj_xemg_linkExamSuccess");
        ilUtil::sendSuccess($message, true);
        return true;
    }



    /**
     * Copy test object from authoring to assessment system.
     *
     * Done via zip/xml-export, REST transfer, and import on assessment system.
     * Target test object ID are passed via form elements.
     *
     * @param ilExamMgrRemoteCrs $remote_crs
     * @return bool Depending on success.
     */
    private function copyTest($remote_crs) {

        global $lng, $ilDB;
        
        $testRefId = $this->getInput("test_id");
        $theTest = new ilObjTest($testRefId);
        $xml = $theTest->getXMLZip();   // "create ZIP and return path"

        try {
            $rest = new ilExamMgrREST();
        } catch (HandledGuzzleException $e ) {
            return false;
        }
        $response = $rest->post("examPlugin/putTest/{$remote_crs->remote_id}", null, array("testUpload" => $xml));

        if(!$response) {
            return false;
        }

        $id = $ilDB->nextID("rep_robj_xemg_tests");    // get next sequence number (DB independent AUTO_INCREMENT)
        $ilDB->manipulate($ins = "INSERT INTO rep_robj_xemg_tests".
                                 " (id, local_ref_id, remote_ref_id, remote_crs_ref_id, exam_obj_id) VALUES".
                                 " ( ".
                                 $ilDB->quote($id, 'integer') . ", " .
                                 $ilDB->quote($testRefId, 'integer') . ", " .
                                 $ilDB->quote($response['test_ref_id'], 'integer') . ", " .
                                 $ilDB->quote($response['crs_ref_id'], 'integer') . ", " .
                                 $ilDB->quote($this->plugin_obj->getId(), 'integer') .")"); 
        $message = $lng->txt("rep_robj_xemg_transferExamSuccess");
        ilUtil::sendSuccess($message, true);
        $this->plugin_obj->getTicket()->addReply($message);
        return true;
    }

    /**
     * Remove a test from the list of known tests. The actual test object on the
     * assessment system is not altered in any way, this has to be done by hand
     * (risk of losing test results etc.)
     *
     * @param int $id Database ID of test to unlink.
     */
    public function unlink($id) {
        global $ilDB, $lng;
        $res = $ilDB->query("SELECT remote_ref_id from rep_robj_xemg_tests WHERE id = ".$ilDB->quote($id, "integer"));
        $row = $ilDB->fetchAssoc($res);
        $remoteRefId = $row['remote_ref_id'];
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_tests WHERE id = ".$ilDB->quote($id, "integer"));
        ilUtil::sendInfo(sprintf($lng->txt("rep_robj_xemg_test_unlink_msg"), ilExamMgrPlugin::createPermaLink($remoteRefId, "tst")), true);
    }

    /**
     * Convert any one-way accounts that might have been used to complete the test
     * to LDAP accounts on the assessment system.
     *
     * @param int $id Database ID of test to unlink.
     */
    public function cleanup($id) {
        global $ilDB;
        $res = $ilDB->query("SELECT remote_ref_id FROM rep_robj_xemg_tests where id = ".$ilDB->quote($id, 'integer'));
        $remote_ref_id = $ilDB->fetchAssoc($res)['remote_ref_id'];

        try {
            $rest = new ilExamMgrREST();
        } catch (HandledGuzzleException $e) {
            return false;
        }
        $response = $rest->get("examPlugin/clean_accounts/$remote_ref_id");
        if($response) {
            ilUtil::sendSuccess("Successfully converted exam participants' accounts to LDAP", true);
        }
    }

    /**
     * Fetch test with results from assessment system.
     * Test will be renamed to prevent name clashes.
     *
     * @param int $id Database ID of test to unlink.
     */
    public function fetch($id) {
        global $lng, $ilDB, $tree;

        $res = $ilDB->query("SELECT remote_ref_id FROM rep_robj_xemg_tests where id = ".$ilDB->quote($id, 'integer'));
        $remote_ref_id = $ilDB->fetchAssoc($res)['remote_ref_id'];

        try {
            $rest = new ilExamMgrREST();
        } catch (HandledGuzzleException $e) {
            return false;
        }
        $response = $rest->get("examPlugin/getTest/$remote_ref_id");

        if(!$response) {
            return false;
        }

        // Create user accouts before import to allow proper linking
        // TODO: Fetch LDAP attributes?
        $logins = $response['participants'];

        global $rbacreview, $rbacadmin;
        $user_role_array = $rbacreview->getRolesByFilter($rbacreview::FILTER_ALL, 0, 'User');
        $user_role_id = $user_role_array[0]['obj_id'];
        foreach($logins as $l){
            $user_id = ilObjUser::getUserIdByLogin($l);
            if($user_id > 0) {  // User exists here
                continue;
            }
            // lookup if $l is valid ldap login?
            // lookup ldap anyways to get user data?

            $new_user = new ilObjUser();

            $user_data = array();
            $user_data['login'] = $l;
            $user_data['passwd_type'] = IL_PASSWD_MD5;
            $user_data['auth_mode'] = "ldap";
            
            $user_data['time_limit_unlimited'] = 1;
            $new_user->assignData($user_data);
            // Need this for entry in object_data
            // $new_user->setTitle($new_user->getFullname());
            // $new_user->setDescription($new_user->getEmail());

            $new_user->setLastPasswordChangeToNow();
            $new_user->create();
            $new_user->setActive(false);
            $new_user->saveAsNew();

            $rbacadmin->assignUser($user_role_id,$new_user->getId());
        }

        require_once "./Services/QTI/classes/class.ilQTIParser.php";
        require_once "./Modules/LearningModule/classes/class.ilContObjParser.php";
        require_once "./Modules/Test/classes/class.ilTestResultsImportParser.php";
        require_once "./Modules/Test/classes/class.ilObjTest.php";

        $basedir = ilObjTest::_createImportDirectory();
        $filename = $response['filename'];  // Have to use ILIAS' own naming schema.
        $target = $basedir . "/" . $filename;
        file_put_contents($target, base64_decode($response['testFile']));

        ilUtil::unzip($target);

        $subdir = basename($filename, ".zip");

        ilObjTest::_setImportDirectory($basedir);
        $xml_file = ilObjTest::_getImportDirectory() . '/' . $subdir . '/' . $subdir . ".xml";
        $qti_file = ilObjTest::_getImportDirectory() . '/' . $subdir . '/' . preg_replace("/test|tst/", "qti", $subdir) . ".xml";
        $results_file = ilObjTest::_getImportDirectory() . '/' . $subdir . '/' . preg_replace("/test|tst/", "results", $subdir) . ".xml";

        if (!is_file($qti_file)) {
            ilUtil::delDir($basedir);
            return;
        } 
        /* In the original code, the QTIParser is invoked twice:
         * once here, before the ilObjTest is created, to check if the upload
         * is valid, and then a second time for the actual import.
         * Assume that the upload is valid and do it only once.
         */

        $newObj = new ilObjTest(0, true);
        $newObj->setType("tst");
        $newObj->setTitle("dummy");
        $newObj->setDescription("test import");
        $newObj->create(true);
        $newObj->createReference();
        $local_tree = $tree->getPathFull($this->plugin_obj->getRefId());
        $parent_ref_id = $local_tree[count($local_tree) - 2]['ref_id'];
        $newObj->putInTree($parent_ref_id);
        // TODO: duplicated from courseRoutes
        // required for setPermission and notify
        global $ilUser;
        global $ilias;
        $ilias->account = & $ilUser;
        $newObj->setPermissions($parent_ref_id);
        /* This line was (in uploadTstObject):
        $newObj->notify("new",$_GET["ref_id"],$_GET["parent_non_rbac_id"],$_GET["ref_id"],$newObj->getRefId());
         parent_non_rbac_id is not passed by form, and seems never to be used anywhere.
         */
        $newObj->notify("new", $parent_ref_id, null, $parent_ref_id, $newObj->getRefId());
        $newObj->mark_schema->flush();
        // start parsing of QTI files

        $qpl_id = $newObj->id;  // always create new question pool
        // last parameter is $_POST['ident'], an array of selected questions from the intermediate GUI
        // TODO: need 2 parsing steps -.-?
        $qtiParser = new ilQTIParser($qti_file, IL_MO_VERIFY_QTI, 0, "");
        $qtiParser->startParsing();
        $founditems = & $qtiParser->getFoundItems();
        $idents = [];
        foreach($founditems as $fi){
            $idents[] = $fi['ident'];
        }
        $qtiParser = new ilQTIParser($qti_file, IL_MO_PARSE_QTI, $qpl_id, $idents);
        $qtiParser->setTestObject($newObj);
        $qtiParser->startParsing();
        $newObj->setTitle($newObj->getTitle() . " Re-Import");
        $newObj->saveToDb();
        // import page data
        $contParser = new ilContObjParser($newObj, $xml_file, $subdir);
        $contParser->setQuestionMapping($qtiParser->getImportMapping());
        $contParser->startParsing();

        // import test results
        if (file_exists($results_file)) {
            $results = new ilTestResultsImportParser($results_file, $newObj);
            $results->startParsing();
        } 
        $newObj->setTitle($newObj->getTitle() . " Re-Import");
        ilUtil::delDir(ilObjTest::_getImportDirectory());

        $newObj->updateMetaData();
        $newObj->update();

        ilUtil::sendSuccess("Successfully imported Exam + Results", true);
    }
}
    

