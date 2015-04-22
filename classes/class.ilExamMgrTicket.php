<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once __DIR__.'/../vendor/autoload.php';
require_once 'class.ilExamMgrPlugin.php';
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

/**
 * Interface for a trouble ticket system ticket.
 */
interface RTTicket {
    /**
     * Create a new ticket.
     *
     * @param array $ccList List of email addresses for the CC field.
     * @param string $message Plain text message for the ticket.
     * @return bool|int `false` on error, ticket ID on success.
     */
    public function createTicket($ccList, $message);
    /**
     * Add a reply to a ticket.
     *
     * @param string $reply Plain text reply message.
     * @return bool depending on success.
     */
    public function addReply($reply);
    /**
     * Add a comment to a ticket.
     *
     * @param string $comment Plain text comment message.
     * @return bool depending on success.
     */
    public function addComment($comment);
    /**
     * Change ticket subject.
     *
     * @param string $newSubj New subject of the ticket.
     * @return bool depending on success.
     */
    public function changeSubject($newSubj);
    /**
     * Add new CC entry.
     * 
     * @param string $newCC Email address for new CC.
     * @return bool depending on success.
     */
    public function addCC($newCC);
    /**
     * Get CC entries.
     *
     * @return array List of current entries in the CC field.
     */
    public function getCC();
    /**
     * Set CC list of ticket.
     *
     * @param array $ccList list of email addresses for the CC field.
     * @return bool depending on success.
     */
    public function setCC($ccList);
}

/**
 * Dummy class that does nothing but implements the RTTicket interface.
 *
 * Usefull for development purposes if the plugin must not talk to the ticket system.
 */
class ilExamMgrDummyTicket implements RTTicket {
    /** no-op */
    public function createTicket($ccList, $message) {}
    /** no-op */
    public function addReply($reply) {}
    /** no-op */
    public function addComment($comment) {}
    /** no-op */
    public function changeSubject($newSubj) {}
    /** no-op */
    public function addCC($newCC) {}
    /** no-op */
    public function getCC() {}
    /** no-op */
    public function setCC($ccList) {}
}

/**
 * RT ticket handling class (via REST).
 */
class ilExamMgrTicket implements RTTicket
{

    public function __construct(ilObjExamMgr $examObj){
        $this->examObj = $examObj;
        $this->rt_user = ilExamMgrPlugin::getSetting('rt_user');
        $this->rt_pass = ilExamMgrPlugin::getSetting('rt_pass');
        $this->rt_path = ilExamMgrPlugin::getSetting('rt_path');
        $this->rt_queue = ilExamMgrPlugin::getSetting('rt_queue');
        $this->disabled = ilExamMgrPlugin::getSetting('rt_disabled');
    }

    public function createTicket($ccList, $message) {
        global $lng, $ilUser, $ilPluginAdmin;
        if($this->disabled) {
            return false;
        }
        if($this->rt_user == '' || $this->rt_pass == '') {
            ilUtil::sendFailure($lng->txt('rep_robj_xemg_noRTUser'), true);
            return false;
        }
        $title = $this->examObj->getTitle();
        $date = $this->examObj->getDate();
        $time = $this->examObj->getTime();
        $num = $this->examObj->getNumStudents();
        $pl = $ilPluginAdmin->getPluginObject(IL_COMP_SERVICE, "Repository", "robj", "ExamMgr");
        $plugin_dir = $pl->getDirectory();
        $template = new ilTemplate("tpl.ticket.txt", true, true, $plugin_dir);
        $template->setVariable("TITEL", $title);
        $template->setVariable("DATUM", $date);
        $template->setVariable("UHRZEIT", $time);
        $template->setVariable("ANZAHL", $num);
        if(!is_null($message)) {
            $template->setVariable("NACHRICHT", $message);
        }
        $text = $template->get();

        $ccStr = implode(",", $ccList);

        $endpoint = "$this->rt_path/REST/1.0/ticket/new";
        $data = array('id' => 'ticket/new',
                      'Queue' => $this->rt_queue,
                      'Requestor' => $ilUser->getEmail(),
                      'Subject' => sprintf($lng->txt("rep_robj_xemg_ticket_subject"), $title, $date),
                      'Text' => $text,
                      'Cc' => $ccStr);

        $rt_data = $this->formatRTData($data);

        $response = $this->send($endpoint, $rt_data);
        if(!$response) {
            return false;
        }
        /* Response body after successful creation:
         * RT/4.0.7 200 Ok
         * 
         * # Ticket 153092 created.
         */ 
        $bodyparts = explode("\n", $response->getBody());
        $lineparts = explode(" ", $bodyparts[2]);
        $ticketId = $lineparts[2];
        $this->examObj->setTicketId($ticketId);
        $this->examObj->doUpdate();
        ilUtil::sendInfo(sprintf($lng->txt('rep_robj_xemg_RTSuccess'), $ticketId), true); // only one success/info/failure possible.
        
        return $ticketId;
    }



    public function addReply($comment) {
        if($this->disabled) {
            return true;
        }
        $id = $this->examObj->getTicketId();
        if(empty($id)) {
            return;
        }
        $endpoint = "{$this->rt_path}/REST/1.0/ticket/$id/comment";
        $data = array('id' => $id,
                      'Action' => 'correspond',
                      'Text' => $comment);
        $rt_data = $this->formatRTData($data);

        return (bool) $this->send($endpoint, $rt_data);
    }

    public function addComment($comment) {
        if($this->disabled) {
            return true;
        }
        $id = $this->examObj->getTicketId();
        if(empty($id)) {
            return;
        }
        $endpoint = "{$this->rt_path}/REST/1.0/ticket/$id/comment";
        $data = array('id' => $id,
                      'Action' => 'comment',
                      'Text' => $comment);
        $rt_data = $this->formatRTData($data);

        return (bool) $this->send($endpoint, $rt_data);
    }

    public function changeSubject($newSubj) {
        if($this->disabled) {
            return true;
        }
        $id = $this->examObj->getTicketId();
        $endpoint = "{$this->rt_path}/REST/1.0/ticket/$id/edit";
        $data = array('id' => $id,
                      'Subject' => $newSubj);
        $rt_data = $this->formatRTData($data);

        return (bool) $this->send($endpoint, $rt_data);
    }

    public function addCC($newCC) {
        if($this->disabled) {
            return true;
        }
        $current = $this->getCC();
        if( in_array($newCC, $current) ) {
            return true;
        }
        $current[] = $newCC;
        return $this->setCC($current);
    }

    public function setCC($ccList){
        $id = $this->examObj->getTicketId();
        $endpoint = "{$this->rt_path}/REST/1.0/ticket/$id/edit";
        $ccString = implode(", ", $ccList);
        $data = array('id' => $id,
                      'CC' => $ccString);
        $rt_data = $this->formatRTData($data);

        return (bool) $this->send($endpoint, $rt_data);
    }

    public function getCC() {
        if($this->disabled) {
            return array();
        }
        $id = $this->examObj->getTicketId();
        $endpoint = "{$this->rt_path}/REST/1.0/ticket/$id/show";
        $rt_resp = $this->send($endpoint);
        $lines = explode("\n", $rt_resp->getBody());
        foreach($lines as $line) {
            if(substr($line, 0, 3) == "Cc:") {
                return array_map(trim, explode(",", substr($line, 4)));
            }
        }
    }


    /**
     * Send data to RT.
     */
    private function send($endpoint, $data="") {
        try {
            $client = new GuzzleHttp\Client();
            $body = array('user' => $this->rt_user,
                          'pass' => $this->rt_pass);
            if($data != "") {
                $body['content'] = $data;
            }
            $response = $client->post($endpoint, ['body' => $body]);
        } catch (ClientException $e) { 
            error_log($e->getRequest());
            error_log($e->getResponse());
            return false;
        }
        $body = $response->getBody();
        $lines = preg_split("/\r\n|\n|\r/", $body);

        // On some errors (e.g. missing permissions), RT sends HTTP status 200,
        // and the first line of the body looks like
        // RT/x.y.z 400 Bad Request
        if(strpos($lines[0], "400") !== false) {
            ilUtil::sendFailure(implode("<br />", $lines), true);
            return false;
        }
  
        // TODO: Return response body as string?
        return $response;
    }

    /**
     * Format a key/value array according to RT requirements.
     *
     * @param array $data.
     */
    private function formatRTData($data) {
        $rt_data = '';
        foreach ($data as $k => $v) {
            if($k == 'Text') {
                $v = $this->formatRTBody($v);
                $rt_data .= $k .': '. $v ."\n";
            } else {
                $rt_data .= $k .': '. $v ."\n";
            }
        }
        return $rt_data;
    }

    /**
     * Format a (long) text according to RT requirements.
     * (I.e.: indent all but the first line)
     *
     * @param string $text.
     */
    private function formatRTBody($text) {
        $first = true;
        $collected = "";
        foreach(preg_split("/(\r\n|\n|\r)/", $text) as $line) {
            if($first) {
                $collected .= $line . "\n";
                $first = false;
                continue;
            }

            $collected .= " " . $line . "\n";
        }
        return $collected;
    }
}


