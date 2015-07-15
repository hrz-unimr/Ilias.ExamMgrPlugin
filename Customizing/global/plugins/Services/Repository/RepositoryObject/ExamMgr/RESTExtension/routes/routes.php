<?php 

use \RESTController\core\auth as Auth;
use \RESTController\libs as Libs;

$app->group('/examPlugin', function () use ($app) {

    $app->get('/refIdByPath/:path+', '\RESTController\libs\OAuth2Middleware::TokenAdminAuth', function ($path) use ($app) {
        global $tree;
        $data = ['looking for' => $path];

        $refId = $tree->getRootId();
        $catTitle = "root";
        foreach($path as $segment) {
            $children = $tree->getChildsByTypeFilter($refId, array('cat'));
            $child = array_filter($children, function($elm) use ($segment) {return $elm['title'] == $segment;});
            if(count($child) == 1) {
                // $child is still indexed by the keys from getChildsByTypeFilter
                // so $child[0] is most likely to fail (silently)
                $child = array_pop($child);
                $refId = $child['ref_id'];
                $catTitle = $child['title'];
            } else {
                $app->halt(404, "Could not find '$segment' in category '$catTitle'");
                return;
            }
        }
        $data["finalRefId"] = $refId;
        $app->success($data);
    });

    // authenticate will nicht
    $app->post('/putTest/:targetRefId', '\RESTController\libs\OAuth2Middleware::TokenAdminAuth', function ($targetRefId) use ($app) {
        // TODO: this piece of code is required for both assessment system (put test here)
        // and authoring system (re-import completed exam).
        // Wat do?
        require_once "./Services/QTI/classes/class.ilQTIParser.php";
        require_once "./Modules/LearningModule/classes/class.ilContObjParser.php";
        require_once "./Modules/Test/classes/class.ilTestResultsImportParser.php";
        require_once "./Modules/Test/classes/class.ilObjTest.php";

        $auth = new Auth\Util();
        $accessToken = $auth->getAccessToken();
        $user_id = $accessToken->getUserId();

        // C/P and modified from class.ilObjTestGUI.php:uploadTstObject()
        $errorCode = $_FILES["testUpload"]["error"];
        if ($errorCode > UPLOAD_ERR_OK) {
            $app->halt(400, ["msg" => "Error during file upload",
                             "php_error_code" => $errorCode,
                             "explanation" => "http://php.net/manual/en/features.file-upload.errors.php"],
                       400);  // json-status code, probably not used by client
        }

        $basedir = ilObjTest::_createImportDirectory();

        $file = pathinfo($_FILES["testUpload"]["name"]);
        $full_path = $basedir . "/" . $_FILES["testUpload"]["name"];
        ilUtil::moveUploadedFile($_FILES["testUpload"]["tmp_name"], $_FILES["testUpload"]["name"], $full_path);

        ilUtil::unzip($full_path);

        $subdir = basename($file["basename"], "." . $file["extension"]);
        ilObjTest::_setImportDirectory($basedir);
        $xml_file = ilObjTest::_getImportDirectory() . '/' . $subdir . '/' . $subdir . ".xml";
        $qti_file = ilObjTest::_getImportDirectory() . '/' . $subdir . '/' . preg_replace("/test|tst/", "qti", $subdir) . ".xml";
        $results_file = ilObjTest::_getImportDirectory() . '/' . $subdir . '/' . preg_replace("/test|tst/", "results", $subdir) . ".xml";

        if (!is_file($qti_file)) {
            ilUtil::delDir($basedir);
            $app->halt(400, ["msg" => "No valid ILIAS test export"], 400);
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
        $newObj->putInTree($targetRefId);
        // TODO: duplicated from courseRoutes
        // required for setPermission and notify

        Libs\RESTLib::loadIlUser();
        global $ilUser;
        $ilUser->setId($user_id);
        $ilUser->read();
        Libs\RESTLib::initAccessHandling();

        global $ilias;
        $ilias->account = & $ilUser;
        $newObj->setPermissions($targetRefId);
        /* This line was (in uploadTstObject):
        $newObj->notify("new",$_GET["ref_id"],$_GET["parent_non_rbac_id"],$_GET["ref_id"],$newObj->getRefId());
         parent_non_rbac_id is not passed by form, and seems never to be used anywhere.
         */
        $newObj->notify("new", $targetRefId, null, $targetRefId, $newObj->getRefId());
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
        $newObj->saveToDb();
        // (probably obsolete) check for valid upload.
        // still using old (<0.8) API
/*        $founditems = & $qtiParser->getFoundItems();
        $newObj->saveToDb();

        if (count($founditems) == 0) {
            //ilUtil::delDir($basedir);
            $response->setMessage("QTIParser found 0 items in $qti_file");
            $response->setHttpStatus("400");
            $response->setRestCode("400");
            $response->send();
            exit;
        }

        $complete = 0;
        $incomplete = 0;
        foreach ($founditems as $item) {
            if (strlen($item["type"])) {
                $complete++;
            } else {
                $incomplete++;
            }
        }

        if ($complete == 0) {
            ilUtil::delDir($basedir);
            $response->setMessage("No 'complete' items to import");
            $response->setHttpStatus("400");
            $response->setRestCode("400");
            $response->send();
            exit;
        }
*/
        // import page data
        $contParser = new ilContObjParser($newObj, $xml_file, $subdir);
        $contParser->setQuestionMapping($qtiParser->getImportMapping());
        $contParser->startParsing();

        // import test results
        if (file_exists($results_file)) {
            $results = new ilTestResultsImportParser($results_file, $newObj);
            $results->startParsing();
        }

        ilUtil::delDir(ilObjTest::_getImportDirectory());

        $newObj->updateMetaData();

        $app->success(["test_ref_id" => $newObj->getRefId(),
                       "crs_ref_id" => $targetRefId,
                       "msg" => "Imported Test {$newObj->getTitle()} in Course with Ref ID $targetRefId"]);
    });

    //////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////
    //////////////////////////////////////////////////////////////////////////

    $app->get('/clean_accounts/:test_ref_id',  '\RESTController\libs\OAuth2Middleware::TokenAdminAuth', function($test_ref_id) use($app) {
        require_once "./Modules/Test/classes/class.ilObjTest.php";
        require_once './Services/User/exceptions/class.ilUserException.php';
        // This line can be removed if https://github.com/ILIAS-eLearning/ILIAS/pull/2 is used.
        // Without this change, the test object needs an initialized $ilUser
        Libs\RESTLib::loadIlUser();
        $test = new ilObjTest($test_ref_id);
        $test->read();
        $participants = $test->getParticipants();
        // Yields array of ["name", "fullname", "login"] arrays

        $changed = 0;
        $problems = array();
        foreach($participants as $p) {
            $user_id = ilObjUser::_lookupId($p['login']);
            if(empty($user_id)) {
                continue;
            }
            $u = new ilObjUser($user_id);
            $u->read();
            $mode = $u->getAuthMode();
            // Yields a string representation of the user's auth mode.
            // Can be "ldap", "local" or "default".
            // "default" seems to mean "system wide default when the user was created".
            // The ExamMgrPlugin explicitly sets "ldap" or "local".
            if($mode == "local") {
                $login = $u->getLogin();
                // TODO: update if naming schema changes. Maybe abuse a field (e.g. "institution") from user settings?
                $new_login = preg_replace("/(\w)_(\d+)/", "\\1", $login);
                try {
                    if(!$u->updateLogin($new_login)) {
                        $problems[] = "Could not update $login to $new_login";
                        continue;
                    }
                } catch (ilUserException $e) {
                    $problems[] = $e->getMessage();
                    continue;
                }
                $u->setAuthMode("ldap");
                $u->update();
                $changed++;
            }

        }

        if($changed > 0) {
            $msg = "Changed $changed accounts from local to LDAP";
        } else {
            $msg = "Nothing changed";
        }
        if(count($problems) > 0) {
            $app->success(["msg" => $msg, "problems" => $problems]);
        } else {
            $app->success(["msg" => $msg]);
        }
    });

    $app->get('/getTest/:ref_id', '\RESTController\libs\OAuth2Middleware::TokenAdminAuth', function ($ref_id) use ($app) {
        require_once "./Modules/Test/classes/class.ilObjTest.php";

        // Get participants, because they have to be created on authoring system
        // before test import.

        // This line can be removed if https://github.com/ILIAS-eLearning/ILIAS/pull/2 is used.
        // Without this change, the test object needs an initialized $ilUser
        Libs\RESTLib::loadIlUser();
        $test = new ilObjTest($ref_id);
        $test->read();
        $participants = $test->getParticipants();
        // Yields array of ["name", "fullname", "login"] arrays
        $part_logins = array();
        foreach($participants as $p){
            $part_logins[] = $p['login'];
        }

        $xml = $test->getXMLZip();   // "create ZIP and return path"
        $app->success(['testFile' => base64_encode(file_get_contents($xml)),
                       'filename' => basename($xml),
                       'fullname' => $xml,
                       'participants' => $part_logins]);
    });
});
?>
