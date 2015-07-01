<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "./Services/Repository/classes/class.ilObjectPlugin.php";
require_once "class.ilExamMgrTicket.php";


/**
 * Class for DB persistence stuff.
 */

class ilObjExamMgr extends ilObjectPlugin
{

    const STATUS_NEW = 0;
    const STATUS_REQUESTED = 1;
    const STATUS_LOCAL = 2;
    const STATUS_REMOTE = 3;


    /**
    * Constructor
    *
    * @access   public
    */
    function __construct($a_ref_id = 0)
    {
        parent::__construct($a_ref_id);
        $this->department = "";
        $this->institute = "";
        $this->duration = 0;    // in minutes
        $this->status = 0;
        $this->numStudents = 0;
        $this->setTicketId(null);
        $this->remoteCourses = null;
    }


    /**
    * Set the type of this object. Must probably match the $id variable in plugin.php.
    */
    final function initType()
    {
        $this->setType("xemg");
    }

    /**
     * Create object.
     * Called after first "Title+Description" form, so not much data here yet.
     * AKA no data at all, because title and description are in different table
    */
    function doCreate()
    {
        global $ilDB;
        // dieses Objekt in DB anlegen
        $ilDB->manipulate("INSERT INTO rep_robj_xemg_data ".
            "(obj_id, exam_title) VALUES (".
            $ilDB->quote($this->getId(), "integer").", ".
            $ilDB->quote($this->getTitle(), "text").
            ")");
    }

    /**
    * Read data from db
    */
    function doRead()
    {
        global $ilDB;

        // anhand von this->getId() aus DB erstellen
        $set = $ilDB->query("SELECT * FROM rep_robj_xemg_data ".
            " WHERE obj_id = ".$ilDB->quote($this->getId(), "integer")
        );
        while ($rec = $ilDB->fetchAssoc($set))  // todo while is kind of wronk
        {
            $this->setExamTitle($rec["exam_title"]);
            $this->setDate($rec["exam_date"]);
            $this->setTime($rec["exam_time"]);
            $this->setDuration($rec["duration"]);
            $this->setNumStudents($rec['num_students']);
            $this->setStatus($rec['status']);
            $this->setDepartment($rec['department']);
            $this->setInstitute($rec['institute']);
            $this->setTicketId($rec['ticket_id']);
        }
    }

    /**
    * Update data
    */
    function doUpdate()
    {
        global $ilDB;

        $ilDB->manipulate($up = "UPDATE rep_robj_xemg_data SET ".
            " exam_title = ".$ilDB->quote($this->getExamTitle(), "text"). ", ".
            " exam_date = ".$ilDB->quote($this->getDate(), "date"). ", ".
            " exam_time = ".$ilDB->quote($this->getTime(), "time"). ", ".
            " duration = ".$ilDB->quote($this->getDuration(), "integer"). ", ".
            " num_students = ".$ilDB->quote($this->getNumStudents(), "integer"). ", ".
            " status = ".$ilDB->quote($this->getStatus(), "integer"). ", ".
            " department = ".$ilDB->quote($this->getDepartment(), "text"). ", ".
            " institute = ".$ilDB->quote($this->getInstitute(), "text"). ", ".
            " ticket_id = ".$ilDB->quote($this->getTicketId(), "integer").
            " WHERE obj_id = ".$ilDB->quote($this->getId(), "integer")
            );
    }

    /**
    * Delete data from db
    */
    function doDelete()
    {
        global $ilDB;
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_log ".
                          "WHERE exam_obj_id = ".$ilDB->quote($this->getId(), "integer"));
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_rem_crs ".
                          "WHERE exam_obj_id = ".$ilDB->quote($this->getId(), "integer"));
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_students ".
                          "WHERE exam_obj_id = ".$ilDB->quote($this->getId(), "integer"));
        $ilDB->manipulate("DELETE FROM rep_robj_xemg_data ".
                          "WHERE obj_id = ".$ilDB->quote($this->getId(), "integer"));

    }

    /**
    * Do Cloning
    */
    function doClone($a_target_id,$a_copy_id,$new_obj)
    {
        global $ilDB;
        // in new_obj die Werte von diesem Obj setzen.
        // am ende wird
        //$new_obj->update();
        //aufgerufen. ?
    }

    /**
     * Add a message to the internal log of the plugin.
     *
     * @param string $message.
     */
    public function addLogMessage($message) {
        global $ilUser, $ilDB;
        $ilDB->manipulate($m="INSERT INTO rep_robj_xemg_log ".
            "(exam_obj_id, timestamp, username, entry) VALUES (".
            $ilDB->quote($this->getID(), "integer").",".
            $ilDB->quote(date("Y-m-d H:i:s"), "timestamp").",".
            $ilDB->quote($ilUser->getLogin(), "text").",".
            $ilDB->quote($message, "text"). ")");
    }

    /**
     * Get internal log messages for the plugin.
     *
     * @return array List of log entries, each entry is an array with "timestamp", "username" and "entry" fields.
     */
    public function getLogMessages() {
        global $ilDB;
        $set = $ilDB->query("SELECT exam_obj_id, timestamp, username, entry FROM rep_robj_xemg_log ".
            " WHERE exam_obj_id = ".$ilDB->quote($this->getID(), "integer")." ORDER BY timestamp");

        $log = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $log[] = $rec;
        }
        return $log;
    }

    /**
     * Get i18n key for object's status.
     *
     * @param int $status Plugin object status.
     * @return string i18n key to be used with $lng.
     */
    public static function getStatusI18n($status) {
        switch ($status) {
        case self::STATUS_REQUESTED: return "status_requested"; break;
        case self::STATUS_LOCAL: return "status_course_local"; break;
        case self::STATUS_REMOTE: return "status_course_remote"; break;
        }
        return "unknown_status_$status";
    }

    /**
     * Get organizator accounts for this exam.
     *
     * Organizator accounts = everyone who should get access to the exam on the
     * authoring system but did not create the plugin object.
     *
     * @return array List of organizators, as array with "name", "ldap_uid", "email" entries.
     */
    public function getOrgas() {
    // TODO: create object or use array?
        global $ilDB;
        $set = $ilDB->query("SELECT name, ldap_uid, email FROM rep_robj_xemg_orga ".
            " WHERE exam_id = ".$ilDB->quote($this->getID(), "integer")." ORDER BY email");

        $orgas = array();

        while ($rec = $ilDB->fetchAssoc($set)){
            $orgas[] = $rec;
        }
        return $orgas;
    }

    /**
     * Add organizator to this exam.
     * @param $fullName.
     * @param $email.
     * @param $uid.
     * @return bool `true` if successful, `false` if trying to add duplicate
     */
    public function addOrga($fullName, $email, $uid) {
        global $ilDB;
        $set = $ilDB->query($q = "SELECT email FROM rep_robj_xemg_orga " .
            " WHERE exam_id = ".$ilDB->quote($this->getID(), "integer") .
            " AND email = ".$ilDB->quote($email));
        if($set->numRows() >= 1) {  // ">" should never happen
            return false;
        } else {
            $ilDB->manipulate("INSERT INTO rep_robj_xemg_orga (exam_id, name, email, ldap_uid) VALUES (".
                              $ilDB->quote($this->getID(), "integer") . ", " .
                              $ilDB->quote($fullName, "text") . ", " .
                              $ilDB->quote($email, "text") . ", ".
                              $ilDB->quote($uid, "text") . " )");
            return true;
        }
    }

    /**
     * Remove organizators by email address.
     * @param array $emails List of email addresses to remove from DB.
     */
    public function removeOrgas($emails) {
        global $ilDB;

        foreach($emails as $email) {
            $ilDB->manipulate("DELETE FROM rep_robj_xemg_orga WHERE email = ".$ilDB->quote($email, "text"));
        }
    }

    /**
     * Definition of global naming schema for exam courses.
     *
     * @return string Title for the course for this exam on the assessment system.
     */
    public function getCourseTitle() {
        return $this->getDate() . " " . $this->getExamTitle();
    }

    // Getter/Setter
    // Attention: get/setTitle, get/setDescription are in super class, data for these attributes are stored in the table object_data!

    // Exam title can be different from plugin object's title.
    public function setExamTitle($t) {
        $this->examTitle = $t;
    }
    public function getExamTitle() {
        return $this->examTitle;
    }

    public function setDate($ed) {
        $this->examDate = $ed;
    }

    public function getDate(){
        return $this->examDate;
    }

    public function setTime($et) {
        $this->examTime = $et;
    }

    public function getTime() {
        return $this->examTime;
    }

    public function getDuration() {
        return $this->duration;
    }

    public function setDuration($d) {
        $this->duration = $d;
    }

    public function getNumStudents(){
        return $this->numStudents;
    }

    public function setNumStudents($n){
        $this->numStudents = $n;
    }

    public function setStatus($s) {
        $this->status = $s;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getDepartment() {
        return $this->department;
    }

    public function setDepartment($d) {
        $this->department = $d;
    }

    public function getInstitute() {
        return $this->institute;
    }

    public function setInstitute($i) {
        $this->institute = $i;
    }

    /**
     * Create a Ticket.
     * Client will be the creator of the plugin object,
     * more people can be added as CC.
     * @param array $cclist Array of email addresses to be added as ticket CC.
     * @param $message Plain text message for the ticket
     */
    public function createTicket($ccList=NULL, $message=NULL) {
        $t = new ilExamMgrTicket($this);
        $res = $t->createTicket($ccList, $message);
        if($res) {
            $this->setTicketId($res);
            $this->doUpdate();
        }
    }

    /**
     * Get ticket for this exam.
     *
     * Since the usage of the ticket system can be changed, it is possible that there is no
     * ticket id for an exam; then a {@see ilExamMgrDummyTicket} is returned. Or there is a
     * ticket id, but the ticket system is disabled right now; then the {@see ilExamMgrTicket}
     * class is responsible to ignore all actions.
     *
     * @return RTTicket Either a "real" or a dummy ticket object.
     */
    public function getTicket() {
        if(is_null($this->getTicketId())) {
            return new ilExamMgrDummyTicket();
        } else {
            return new ilExamMgrTicket($this);
        }
    }

    public function setTicketId($id) {
        $this->ticketId = $id;
    }

    public function getTicketId() {
        return $this->ticketId;
    }

}
