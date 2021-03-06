<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "./Services/Repository/classes/class.ilObjectPluginListGUI.php";
require_once "class.ilObjExamMgrAccess.php";

/**
* ListGUI implementation for ExamManager object plugin. This one
* handles the presentation in container items (categories, courses, ...)
* together with the corresponfing ...Access class.
*
* PLEASE do not create instances of larger classes here. Use the
* ...Access class to get DB data and keep it small.
*
*/
class ilObjExamMgrListGUI extends ilObjectPluginListGUI
{
	
	/**
	* Init type, has to match the $id given in plugin.php
	*/
	function initType()
	{
		$this->setType("xemg");
	}
	
	/**
	* Get name of GUI class handling the commands
	*/
	function getGuiClass()
	{
		return "ilObjExamMgrGUI";
	}
	
	/**
     * Return available commands in list views. If the "default" key is true,
     * the containing entry will be used if the user clicks on the object's link,
     * the other entries are included in the drop down menu.
     *
     * @return: array   array of arrays:
     *                  "permission" => read/write/?
     *                  "cmd" => will be used in ilObjExamMgrGUI's "performCommand" method
     *                  "txt" => label
     *                  "default" => true/false
	*/
	function initCommands()
    {
        global $ilUser;
		$cmds = array
		(
			array(
				"permission" => "read",
                "cmd" => "showClientView",
                "txt" => $this->txt("clientView"),
				"default" => false),
			array(
				"permission" => "write",
				"cmd" => "showAdminView",
				"txt" => $this->txt("adminView"),
				"default" => false),
        );

        if(ilObjExamMgrAccess::isAdmin($ilUser->getID())){
            $cmds[1]['default'] = true;
        } else {
            $cmds[0]['default'] = true;
        }
        return $cmds;


	}

	/**
     * Get properties to display in list views.
     * Data from here will be displayed as "property: value" below the object's
     * title and description.
     * 
     * @return	array		array of property arrays:
     *						"alert" (boolean) => display as an alert property (usually in red)
     *						"property" (string) => property name
     *						"value" (string) => property value
     */
	function getProperties()
	{
		$props = array();
		
        $stats = ilObjExamMgrAccess::getStatsByRefId($this->ref_id);
        $props[] = array('property' => $this->txt('date'), 'value' => $stats['exam_date']);
        if(isset($stats['num_students']) && $stats['num_students'] > 0) {
            $props[] = array('property' => $this->txt('examNumStudents'), 'value' => $stats['num_students']);
        }

		return $props;
	}
}

