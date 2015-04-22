<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilExamMgrRun.php";

/**
 * Form element to display a table of all rooms.
 */
class ilExamMgrRoomsList extends ilExamMgrFormElement {

    public function __construct($parent) {
        parent::__construct($parent);
    }

    /**
     * Render the element.
     *
     * Creates the neccessary HTML to display the table with rooms
     * and buttons to edit each room.
     *
     * @return string HTML fragment.
     */
    public function render() {
        global $tpl, $lng;
        $roomsListTpl = new ilTemplate("tpl.roomsList.html", true, true, $this->parent->pl->getDirectory());
        $tpl->addCss("{$this->parent->plugin_dir}/templates/examMgr.css");
        $tpl->addJavaScript("{$this->parent->pl->getDirectory()}/ExamMgr/js/examMgr.js");

        $rooms = ilExamMgrRoom::getAllRooms();
        if(count($rooms) > 0){
            $roomsListTpl->setVariable("NAME_HDR", $lng->txt("rep_robj_xemg_room_name"));
            $roomsListTpl->setVariable("CAPACITY_HDR", $lng->txt("rep_robj_xemg_room_capacity"));
            foreach($rooms as $room) {
                $roomsListTpl->setCurrentBlock("rooms_table_row");
                $roomsListTpl->setVariable("NAME", $room->name);
                $roomsListTpl->setVariable("CAPACITY", $room->capacity);
                $roomsListTpl->setVariable("EDIT_CMD", "edit_room_{$room->id}");
                $roomsListTpl->setVariable("EDIT_TXT", $lng->txt("rep_robj_xemg_room_edit"));
                $roomsListTpl->parseCurrentBlock();
            }
        } else {
            $roomsListTpl->setVariable("ROOMS_HEADER", $lng->txt("rep_robj_xemg_room_no_rooms"));
        }
            
        return $roomsListTpl->get();
    }
}

