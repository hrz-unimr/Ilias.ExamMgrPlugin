<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilObjExamMgr.php";
require_once "./Services/Form/classes/class.ilDateDurationInputGUI.php";
require_once "class.ilExamMgrRunsList.php";
require_once "class.ilExamMgrRun.php";
require_once "class.ilExamMgrRoom.php";

/**
 * Run creation/editing form.
 */
class ilExamMgrFormRuns extends ilExamMgrForm {

    /** Granularity of time selection */
    const MINUTE_STEPS = 5;


    /**
     * Constructor.
     *
     * @param ilExamMgrConfigGUI $parent
     * @param int|bool $editing either false or DB id of run that's being edited.
     */
    public function __construct($parent, $editing=false) {
		global $ilCtrl, $lng, $tpl;
        parent::__construct($parent);


        if($editing) {
            $this->setTitle($lng->txt("rep_robj_xemg_run_edit"));
            $hidden = new ilHiddenInputGUI('run_id');
            $hidden->setValue($editing);
            $this->addItem($hidden);
        } else {
            $this->setTitle($lng->txt("rep_robj_xemg_existing_runs"));
            $test = new ilExamMgrRunList($this->parent);
            $this->addItem($test);
            $sh = new ilFormSectionHeaderGUI();
            $sh->setTitle($lng->txt("rep_robj_xemg_create_run"));
            $this->addItem($sh);
        }

        $title = new ilTextInputGUI($lng->txt("rep_robj_xemg_run_title"), "run_title");
        $title->setRequired(true);
        $this->addItem($title);

        $start_end = new ilDateDurationInputGUI($lng->txt("rep_robj_xemg_run_time"), "run_start_end");
        $start_end->setShowTime(true);
        $start_end->setMinuteStepSize(self::MINUTE_STEPS);
        $start_end->setStartText($lng->txt("rep_robj_xemg_run_start"));
        $start_end->setEndText($lng->txt("rep_robj_xemg_run_end"));
        $this->addItem($start_end);
        // Store this input element as instance variable, getInput() does not
        // seem to work.
        $this->start_end_input = $start_end;  

        $type = new ilSelectInputGUI($lng->txt("rep_robj_xemg_run_type"), "run_type");
        $type->setOptions(ilExamMgrRun::getRunTypes());
        $this->addItem($type);

        $room = new ilSelectInputGUI($lng->txt("rep_robj_xemg_room"), "room_id");
        $rooms = ilExamMgrRoom::getAllRooms();
        $opts = array();
        foreach($rooms as $r) {
            $opts[$r->id] = $r->name;
        }
        $room->setOptions($opts);
        $this->addItem($room);
        $course = ilExamMgrRemoteCrs::getSelectorForExam($this->plugin_obj->getId(), true);
        $this->addItem($course);

        if($editing) {
            $theRun = new ilExamMgrRun($editing);
            $theRun->doRead();
            $title->setValue($theRun->title);
            $start_end->setStart(new ilDateTime($theRun->begin_ts, IL_CAL_DATETIME));
            $start_end->setEnd(new ilDateTime($theRun->end_ts, IL_CAL_DATETIME));
            $type->setValue($theRun->type);
            $room->setValue($theRun->room);
            $course->setValue($theRun->course);
            $this->addCommandButton("save_run_$editing", $lng->txt("rep_robj_xemg_run_edit"));
        } else {
            $this->addCommandButton("addRun", $lng->txt("rep_robj_xemg_run_add"));
            $date = $this->plugin_obj->getDate();
            $startTime = $this->plugin_obj->getTime();
            $endDateTime = new ilDateTime("$date $startTime", IL_CAL_DATETIME);
            $duration = round((float) $this->plugin_obj->getDuration() / self::MINUTE_STEPS) * self::MINUTE_STEPS;

            $endDateTime->increment(ilDateTime::MINUTE, $duration);

            $start_end->setStart(new ilDateTime("$date $startTime", IL_CAL_DATETIME));
            $start_end->setEnd($endDateTime);
        }
           
        $this->setFormAction($ilCtrl->getFormAction($parent));
        $this->setShowTopButtons(false);
    }

    /**
     * Save this form's data to database.
     * Can result in create or update action.
     *
     * @param int $id if given, DB id of run to update in database.
     * @return bool true on success, false on failure (form not valid).
     */
	public function process($id = null)
	{
        global $tpl, $lng, $ilCtrl;

		if ($this->checkInput())
        {
            $start = $this->start_end_input->getStart()->get(IL_CAL_DATETIME);
            $end = $this->start_end_input->getEnd()->get(IL_CAL_DATETIME);
            $type = $this->getInput("run_type");
            $title = $this->getInput("run_title");
            $room = $this->getInput("room_id");
            $course = $this->getInput("course_id");
            if(is_null($id)) {
                $theRun = new ilExamMgrRun(0, $title, $start, $end, $type, $this->plugin_obj, 0, $room, $course);
                $theRun->doCreate();
                ilUtil::sendSuccess($lng->txt("rep_robj_xemg_run_added"), true);
            } else {
                $theRun = new ilExamMgrRun($id, $title, $start, $end, $type, $this->plugin_obj, 0, $room, $course);
                $theRun->doUpdate();
                ilUtil::sendSuccess($lng->txt("rep_robj_xemg_run_changed"), true);
            }
            return true;
        } else {
            $this->setValuesByPost();
	        $tpl->setContent($this->getHTML());
            return false;
        }
    }
}
