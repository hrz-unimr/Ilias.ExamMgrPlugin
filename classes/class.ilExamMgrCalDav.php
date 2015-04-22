<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once __DIR__ . "/../vendor/autoload.php";
require_once "class.ilObjExamMgr.php";

/**
 * Minimalistic CalDAV class to create a simple event in a CalDAV accessible
 * calendar.
 * @see http://sabre.io/dav/
 */
class ilExamMgrCalDav {
    public function __construct(ilObjExamMgr $examObj) {
        $this->examObj = $examObj;
    }

    /**
     * Create a calendar event for the exam. Uses plugin's settings to
     * access the CalDAV server.
     */
    public function createEvent() {
        global $lng;
        $vcalendar = new Sabre\VObject\Component\VCalendar();

        $start = new DateTime($this->examObj->getDate.' '.$this->examObj->getTime(), new \DateTimeZone('Europe/Berlin'));
        if($this->examObj->getDuration() == 0) {    // Assume default duration of 90 min if none given.
            $period = new DateInterval("PT90M");
        } else {
            $period = new DateInterval("PT{$this->examObj->getDuration()}M");
        }
        $end = clone $start;
        $end->add($period);
        $vcalendar->add('VEVENT', [
            'SUMMARY' => $lng->txt("rep_robj_xemg_eAssessment") . ' ' . $this->examObj->getTitle(),
            'DTSTART' => $start,
            'DTEND' => $end,
            'UID' => uniqid("examMgrPlugin@", true)
        ]);

        $settings = ilExamMgrPlugin::getSettings();
        $davSettings = array(
            'baseUri' => $settings['cal_url'],
            'userName' => $settings['cal_user'],
            'password' => $settings['cal_pass']
        );

        $client = new \Sabre\DAV\Client($davSettings);

        $headers = ["If-None-Match" => "*",     // Prevent accidential overwriting/updating
                    "Content-Type" => "text/calendar"];

        $evt_name = substr(str_shuffle(str_repeat("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789", 5)), 0, 30) . ".ics";
        $attempt = 1;
        do {
            $response = $client->request("PUT", $evt_name, $vcalendar->serialize(), $headers);
            $attempt++;
        } while($response['statusCode'] != 201 && $attempt < 5);
        if($attempt == 5) {
            ilUtil::sendFailure("Could not create calendar entry");
        }
    }
}
