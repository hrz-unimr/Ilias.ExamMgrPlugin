<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

/**
 * Data class for a remote course on the assessment system.
 */
class ilExamMgrRemoteCrs {

    /** @var array Cache for remote courses, avoid repeated REST requests to get remote name. */
    private static $_cache; 

    /**
     * Constructor.
     *
     * @param int $id Local DB id.
     * @param int $remote_id Remote Ref id.
     * @param int $exam_obj_id Local object id of exam manager plugin.
     * @param string $password Password for one-way accounts.
     * @param string $remote_title Title of remote course.
     */
    public function __construct($id=0, $remote_id=0, $exam_obj_id=0, $password="", $remote_title="") {
        $this->id = $id;
        $this->remote_id = $remote_id;
        $this->exam_obj_id = $exam_obj_id;
        $this->password = $password;
        $this->remote_title = $remote_title;
    }

    public function __toString() {
        return "Remote Course (db {$this->id}, remote {$this->remote_id}, {$this->remote_title})";
    }

    /**
     * Store information about remote course in local DB.
     */
    public function writeDB(){
        global $ilDB;

        $this->id = $ilDB->nextID("rep_robj_xemg_rem_crs");
        $ilDB->manipulate($q = "INSERT INTO rep_robj_xemg_rem_crs (id, exam_obj_id, remote_crs_ref_id, password) VALUES (".
                $ilDB->quote($this->id, "integer") .", ".
                $ilDB->quote($this->exam_obj_id, "integer") .", ".
                $ilDB->quote($this->remote_id, "integer") .", ".
                $ilDB->quote($this->password, "text") .")");
    }

    /**
     * Read information about a remote course from local DB.
     *
     * This objects' id is used for the DB query, must therefore be set.
     *
     * @param bool $readTitle If true, make a REST request to determine the course's title
     * on the remote system.
     */
    public function doRead($readTitle=false) {
        global $ilDB;
        if($readTitle) {
            $rest = new ilExamMgrREST();
        }
        $res = $ilDB->query("SELECT * FROM rep_robj_xemg_rem_crs WHERE id = ".$ilDB->quote($this->id, "integer"));
        while($row = $ilDB->fetchAssoc($res)) {
            if($readTitle) {
                $data = $rest->get("v1/courses/{$row['remote_crs_ref_id']}");
                $title = $data['courseinfo']['title'];
            } else {
                $title = "n/a";
            }
            $this->remote_id = $row['remote_crs_ref_id'];
            $this->exam_obj_id = $row['exam_obj_id'];
            $this->password = $row['password'];
            $this->remote_title = $title;
        }
        return $this;   // fluent interface!
    }

    /**
     * Get a permanent link to this course on the remote system.
     */
    public function getPermalink() {
        return ilExamMgrPlugin::createPermaLink($this->remote_id, "crs");
    }

    /**
     * Delete a remote course with a DB id.
     * @param int $id DB id of course to delete.
     */
    public static function deleteDb($id) {
        global $ilDB;
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_rem_crs WHERE id = ".$ilDB->quote($id, "integer"));
    }

    /**
     * Get a list of all remote courses for an exam.
     *
     * @param int $examObjId DB id of plugin object.
     * @return array Array of ilExamMgrRemoteCrs objects.
     */
    public static function getForExam($examObjId) {
        if(empty(self::$_cache)) {
            self::$_cache = array();
        }
        if (isset(self::$_cache[$examObjId])) {
            return self::$_cache[$examObjId];
        } else {
            global $ilDB;
            try {
                $rest = new ilExamMgrREST();
            } catch (HandledGuzzleException $e) {
                return array();
            }
            $res = $ilDB->query("SELECT * FROM rep_robj_xemg_rem_crs WHERE exam_obj_id = ".$ilDB->quote($examObjId, "integer"));
            $courses = array();
            while($row = $ilDB->fetchAssoc($res)) {
                try {
                    $data = $rest->get("v1/courses/{$row['remote_crs_ref_id']}");
                    $title = $data['courseinfo']['title'];
                    $courses[$row['id']] = new ilExamMgrRemoteCrs($row['id'], $row['remote_crs_ref_id'], $row['exam_obj_id'], $row['password'], $title);
                } catch (\GuzzleHttp\Exception\BadResponseException $e) {
                    if($e->hasResponse()) {
                        $status = $e->getResponse()->getStatusCode();
                        if($status ==  401) {
                            ilUtil::sendFailure("Not authorized to GET v1/courses/\$id", true);
                        } else {
                            ilUtil::sendFailure("GETting v1/courses/\$id failed<br />".$e->getMessage(), true);
                        }
                    }
                    return;
                }
                    
            }
            self::$_cache[$examObjId] = $courses;
            return $courses;
        }
    }

    /**
     * Get a GUI selector (select box) for all remote courses of an exam.
     *
     * @param int $examObjId DB id of plugin object
     * @param bool $allowNone Include 'No Course' as option?
     * @return ilSelectInputGUI Select box object, form element name is "course_id".
     */
    public static function getSelectorForExam($examObjId, $allowNone=false) {
        global $lng;
        $courseSelector = new ilSelectInputGUI($lng->txt("rep_robj_xemg_courseSelection"), "course_id");
        $courses = self::getForExam($examObjId);
        $options = array();
        foreach($courses as $c) {
            $options[$c->id] = $c->remote_title;
        }
        if($allowNone) {
            $options[-1] = $lng->txt("rep_robj_xemg_noCourse");
        }
        $courseSelector->setOptions($options);
        return $courseSelector;
    }

}

