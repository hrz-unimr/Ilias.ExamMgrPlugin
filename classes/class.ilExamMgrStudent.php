<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */
require_once __DIR__.'/../vendor/autoload.php';
require_once 'class.ilExamMgrExceptions.php';
require_once 'class.ilExamMgrPlugin.php';

/**
 * Data class for a student, with some (too many?) helper methods.
 */
class ilExamMgrStudent {

    private $firstName, $lastName;
    private $matriculation;
    private $id;
    private $examId;
    private $ldapId;
    private $gender;

    public function __construct($firstName, $lastName, $matriculation, $id=null, $ldapId=null, $gender='x') {
        //todo: construct from id? from examMgrObjID + runID?
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->matriculation = $matriculation;
        $this->id = $id;
        $this->examId = null;
        $this->ldapId = $ldapId;
        $this->gender = $gender;
    }

    public function getId() {
        return $this->id;
    }

    public function setFirstName($n) {
        $this->firstName = $n;
    }
    public function setLastName($n) {
        $this->lastName = $n;
    }
    public function setMatriculation($m) {
        $this->matriculation = $m;
    }
    public function getMatriculation() {
        return $this->matriculation;
    }
    public function setExamID($id) {
        $this->examId = $id;
    }
    public function getExamID() {
        return $this->examId;
    }
    public function setLDAP($id) {
        $this->ldapId = $id;
    }

    public function getLDAP() {
        return $this->ldapId;
    }

    public function __toString() {
        return "Student '{$this->firstName}' '{$this->lastName}'";
    }

    public function getFullName() {
        return $this->firstName . " " . $this->lastName;
    }

    public function getLastName() {
        return $this->lastName;
    }

    public function getFirstName() {
        return $this->firstName;
    }

    private function setGender($g) {
        $this->gender = $g;
    }
    public function getGender() {
        return $this->gender;
    }


    /**
     * Fill "id initialized" student with data from DB.
     */
    public function doRead() {
        global $ilDB;

        $res = $ilDB->query($q = "SELECT * FROM rep_robj_xemg_students ".
            " WHERE id = ".$ilDB->quote($this->getId(), "integer"));

        $row = $ilDB->fetchAssoc($res);
        $this->setFirstName($row['firstname']);
        $this->setLastName($row['lastname']);
        $this->setMatriculation($row['matriculation']);
        $this->setLDAP($row['ldapuid']);
        $this->setExamId($row['exam_obj_id']);
        $this->setGender($row['gender']);
    }


    /**
     * Save student data to DB.
     * @param int $examPluginID Object id of parent exam manager plugin object.
     * @return bool|string `true` on success, error message otherwise.
     */
    public function saveToDB($examPluginID) {
        global $ilDB;

        // ilDB->query seems to be the only possibility to prevent PHP fatal errors on duplicate keys
        // with the second parameter $a_handle_error = false.

        $id = $ilDB->nextID("rep_robj_xemg_students");    // get next sequence number (DB independent AUTO_INCREMENT)
        $this->id = $id;
        $q = "INSERT INTO rep_robj_xemg_students (id, exam_obj_id, firstname, lastname, matriculation, ldapuid, gender) ".
            "VALUES (" . $ilDB->quote($id, "integer") . ", ".
            $ilDB->quote($examPluginID, "integer").", ".
            $ilDB->quote($this->firstName, "text").", ".
            $ilDB->quote($this->lastName, "text").", ".
            $ilDB->quote($this->matriculation, "text").", ".
            $ilDB->quote($this->ldapId, "text").", ".
            $ilDB->quote($this->gender, "text").")";
        $res = $ilDB->query($q, false);
        if(MDB2::isError($res)) {
            if($res->getCode() == MDB2_ERROR_CONSTRAINT) {
                return "Trying to add duplicate student {$this->firstName} {$this->lastName}";
            } else {
                return "Database error error while adding student ${g['firstName']} ${g['lastName']}" . $res->getMessage();
            }
        }
        return true;
    }

    /**
     * Add this student to a run.
     *
     * @param int $runId Database ID of run to add to.
     * @return bool `true` if successful, `false` on error (e.g. duplicate student).
     */
    public function addToRun($runId) {
        global $ilDB;
        //todo: check if run_id belongs to exam plugin
        $res = $ilDB->query($q = "INSERT INTO rep_robj_xemg_stud_run (run_id, student_id, xferd_ldap, xferd_oneway) ".
            "VALUES (" . $ilDB->quote($runId, "integer").", ".
            $ilDB->quote($this->id, "integer").", ".
            $ilDB->quote(false, 'boolean').", ".
            $ilDB->quote(false, 'boolean').")", false);
        if(MDB2::isError($res)) {
            // TODO: report error to user
            if($res->getCode() == MDB2_ERROR_CONSTRAINT) {
                error_log("Trying to add duplicate student ${g['firstName']} ${g['lastName']}");
            } else {
                error_log("Serious error while adding student ${g['firstName']} ${g['lastName']}");
            }
            return false;
        }
        return true;
    }

    /**
     * Set the "has been transferred as LDAP account to the remote course of this run" flag.
     * @param int $runId
     */
    public function setTransferredLdap($runId) {
        $this->setTransferred($runId, "xferd_ldap");
    }

    /**
     * Set the "has been transferred as one-way acocunt to the remote course of this run" flag.
     * @param int $runId
     */
    public function setTransferredOneway($runId) {
        $this->setTransferred($runId, "xferd_oneway");
    }

    /**
     * Set the "has been transferred" flag for this student.
     * @param int $runId
     * @param string $what `"xferd_onewaw"` or `"xferd_ldap"`
     */
    private function setTransferred($runId, $what) {
        global $ilDB;
        $ilDB->query("UPDATE rep_robj_xemg_stud_run" .
            " SET $what = TRUE " .
            " WHERE run_id = " . $ilDB->quote($runId, 'integer') .
            " AND student_id = " . $ilDB->quote($this->id, 'integer'));
    }

    /**
     * Get the "has been transferred" flags for the given run.
     * @param int $runId.
     * @return array ('ldap' => boolean, 'oneway' => boolean)
     */
    public function getTransferred($runId) {
        global $ilDB;
        $res = $ilDB->query("SELECT xferd_ldap, xferd_oneway ".
            " FROM rep_robj_xemg_stud_run ".
            " WHERE run_id = " . $ilDB->quote($runId, 'integer') .
            " AND student_id = " . $ilDB->quote($this->id, 'integer'));
        $row = $ilDB->fetchAssoc($res);
        return array('ldap' => $row['xferd_ldap'], 'oneway' => $row['xferd_oneway']);
    }

    /**
     * Create unique one-way account.
     * Account name is based on student ldap account and remote course ref id.
     * Changes made here need to be reflected in the REST extension as well
     * (in the clean_accounts route, to convert one-way back to ldap).
     * @param ilExamMgrRemoteCrs $crs the remote course.
     * @return string The one-way account name for this student in the course.
     */
    public function getOneWayAccount(ilExamMgrRemoteCrs $crs) {
        return $this->getLDAP() . "_" . $crs->remote_id;
    }

    /**
     * Remove this student from a run.
     *
     * @param int $runId.
     */
    public function removeFromRun($runId) {
        global $ilDB;
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_stud_run WHERE ".
            "run_id = ".$ilDB->quote($runId, "integer"). " AND " .
            "student_id = ".$ilDB->quote($this->id, "integer"));
    }

    /**
     * Get the list of students for an exam from DB.
     *
     * @param int $exam_obj_id Object ID of exam manager plugin.
     */
    public static function getStudents($exam_obj_id) {
        global $ilDB;
        $res = $ilDB->query("SELECT * FROM rep_robj_xemg_students ".
                            "WHERE exam_obj_id=".$ilDB->quote($exam_obj_id, "integer")." ".
                            "ORDER BY lastname");
        if($ilDB->numRows($res) === 0) {
            return NULL;
        } else {
            $students = array();
            while($row = $ilDB->fetchAssoc($res)) {
                $students[] = new ilExamMgrStudent($row['firstname'], $row['lastname'], $row['matriculation'], $row['id'], $row['ldapuid']);
            }
            return $students;
        }
    }
}

