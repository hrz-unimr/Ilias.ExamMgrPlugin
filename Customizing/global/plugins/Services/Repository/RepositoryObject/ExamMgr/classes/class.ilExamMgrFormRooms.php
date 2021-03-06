<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilObjExamMgr.php";
require_once "./Services/Form/classes/class.ilDateDurationInputGUI.php";
require_once "class.ilExamMgrRoomsList.php";
require_once "class.ilExamMgrRoom.php";

/**
 * Room creation/editing form.
 */
class ilExamMgrFormRooms extends ilExamMgrForm {

    /**
     * Constructor.
     *
     * @param ilExamMgrConfigGUI $parent
     * @param int|bool $editing either false or DB id of run that's being edited.
     */
    public function __construct(ilExamMgrConfigGUI $parent, $editing=false) {
		global $ilCtrl, $lng, $tpl;
        parent::__construct($parent);


        if($editing) {
            $this->setTitle($lng->txt("rep_robj_xemg_room_edit"));
            $hidden = new ilHiddenInputGUI('room_id');
            $hidden->setValue($editing);
            $this->addItem($hidden);
        } else {
            $this->setTitle($lng->txt("rep_robj_xemg_existing_rooms"));
            $rooms = new ilExamMgrRoomsList($this->parent);
            $this->addItem($rooms);
            $sh = new ilFormSectionHeaderGUI();
            $sh->setTitle($lng->txt("rep_robj_xemg_create_room"));
            $this->addItem($sh);
        }

        $name = new ilTextInputGUI($lng->txt("rep_robj_xemg_room_name"), "room_name");
        $name->setRequired(true);

        $capacity = new ilNumberInputGUI($lng->txt("rep_robj_xemg_room_capacity"), "room_capacity");
        $capacity->setRequired(true);


        if($editing) {
            $theRoom = new ilExamMgrRoom($editing);
            $theRoom->doRead();
            $name->setValue($theRoom->name);
            $capacity->setValue($theRoom->capacity);
            $this->addCommandButton("save_room_$editing", $lng->txt("rep_robj_xemg_room_edit"));
        } else {
            $this->addCommandButton("addRoom", $lng->txt("rep_robj_xemg_room_add"));
        }
        $this->addItem($name);
        $this->addItem($capacity);
           
        $this->setFormAction($ilCtrl->getFormAction($parent));
        $this->setShowTopButtons(false);
    }



    /**
     * Save this form's data to database.
     * Can result in create or update action.
     *
     * @param int $id if given, DB id of room to update in database.
     * @return bool true on success, false on failure (form not valid).
     */
	public function process($id=null)
	{
        global $tpl, $lng, $ilCtrl;

		if ($this->checkInput())
        {
            $name = $this->getInput("room_name");
            $capacity = $this->getInput("room_capacity");
            if(is_null($id)) {
                $theRoom = new ilExamMgrRoom(0, $name, $capacity);
                $theRoom->doCreate();
                ilUtil::sendSuccess($lng->txt("rep_robj_xemg_room_added"), true);
            } else {
                $theRoom = new ilExamMgrRoom($id, $name, $capacity);
                $theRoom->doUpdate();
                ilUtil::sendSuccess($lng->txt("rep_robj_xemg_room_updated"), true);
            }
            return true;
        } else {
            $this->setValuesByPost();
	        $tpl->setContent($this->getHTML());
            return false;
        }
    }
}
