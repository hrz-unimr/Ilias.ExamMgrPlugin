<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once __DIR__.'/../vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

/**
 * Mailer class to send invitation mails.
 */
class ilExamMgrMailer extends PHPMailer {

    private static $patterns = array("SALUTATION", "TITLE", "DATETIME", "ROOM", "LOGIN", "SENDER");

    /**
     * Get patterns that can be replaced in a template.
     * This function should return an array of strings that can be used in a
     * template to display e.g. "{SALUTATION}", but I could not find a way to
     * include braces in the rendered output.
     * @return array of strings
     */
    public static function getTemplateSafePatterns() {
        // http://stackoverflow.com/questions/29126199/how-to-include-literal-braces-in-itx-template
        return array_map(function($str) {return "$str";}, self::$patterns);
    }

    /**
     * @return array of strings that can be used as patterns in a
     * regular expression to substitute fields.
     */
    public static function getRegexpPatterns() {
        return array_map(function($str) {return "/{{$str}}/";}, self::$patterns);
    }

    public function __construct(){
        global $lng;
        $settings = ilExamMgrPlugin::getSettings();

        $this->isSMTP();
        $this->SMTPKeepAlive = true; // SMTP connection will not close after each email sent, reduces SMTP overhead
        // $this->SMTPDebug = 2;
        // $this->Debugoutput = 'html';
        $this->Host = $settings['smtp_host'];
        $this->Port = $settings['smtp_port'];
        $this->setFrom($settings['smtp_from']);
        $this->SMTPAuth = false;

        $this->Subject = $lng->txt("rep_robj_xemg_invitationSubject");
        $this->CharSet = 'utf8';
        $this->Encoding = 'quoted-printable';

        $this->numFail = 0;
        $this->numSuccess = 0;
        $this->lastError = "";
        $this->errorStudent = null;

        $this->rooms = ilExamMgrRoom::getAllRooms();
    }

    /**
     * Set mail template.
     * @param string $t Plain text template with placeholders.
     */
    public function setTemplate($t) {
        $this->template = $t;
    }

    /**
     * Set exam/plugin-object.
     *
     * @param ilObjExamMgr $e Plugin object.
     */
    public function setExam(ilObjExamMgr $e) {
        $this->exam = $e;
    }

    /**
     * Send current template for a single student in a run.
     *
     * @param ilExamMgrStudent $student
     * @param ilExamMgrRun $run
     */
    public function sendTemplate($student, $run) {

        $patterns = self::getRegexpPatterns();
        if($student->getGender() == "m") {
            $salutation = "Sehr geehrter Herr {$student->getFullName()}";
        } else if($student->getGender() == "f") {
            $salutation = "Sehr geehrte Frau {$student->getFullName()}";
        } else {
            $salutation = "Sehr geehrte(r) {$student->getFullName()}";
        }
        $title = $this->exam->getTitle();
        $datetime = $run->begin_ts;
        $room = $this->rooms[$run->room]->name;
        $courses = ilExamMgrRemoteCrs::getForExam($this->exam->getId());
        $course = $courses[$run->course];
        
        $login = $student->getOneWayAccount($course);
        $sender = $this->From;
        $replacements = array($salutation, $title, $datetime, $room, $login, $sender);
        // The array keys *don't* matter for preg_replace.
        // It's all about the order in which the elements are added.
        // preg_replace([1=>"/a/", 2=>"/x/"], [2=>"y", 1=>"b"], "ax")
        // --> "yb".
        $mailtext = preg_replace($patterns, $replacements, $this->template);

        $this->addAddress("{$student->getLDAP()}@students.uni-marburg.de", $student->getFullName());
        $this->Body = $mailtext;
        $this->isHTML(false);
        if (!$this->send()) {
            $this->numFail++;
            $this->lastError =  $this->ErrorInfo;
            $this->errorStudent = $student->getFullName();
        } else {
            $this->numSuccess++;
        }
        $this->clearAllRecipients();
    }

}



