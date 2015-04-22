<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "./Services/Component/classes/class.ilPluginConfigGUI.php";
require_once "./Services/Form/classes/class.ilPropertyFormGUI.php";
require_once "class.ilExamMgrFormRooms.php";

/**
 * Configuration class to handle the Administration->Plugins GUI.
 * @see http://www.ilias.de/docu/ilias.php?ref_id=42&obj_id=27031&cmd=layout&cmdClass=illmpresentationgui&cmdNode=b0&baseClass=ilLMPresentationGUI
 */
class ilExamMgrConfigGUI extends ilPluginConfigGUI
{
    /**
    * Handles all commmands, default is "configure".
    */
    function performCommand($cmd)
    {
        $pl = $this->getPluginObject();
        $pl->includeClass('class.ilExamMgrPlugin.php');
        $this->pl = $pl;

        switch ($cmd) {
        case "configure":
        case "save":
        case "addRoom":
            $this->$cmd();
            break;
        default:
            $matches = array();
            if (preg_match("/edit_room_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->editRoom($id);
                break;
            } elseif (preg_match("/save_room_(\d+)/", $cmd, $matches)) {
                $id = $matches[1];
                $this->saveRoom($id);
                break;
            }
            ilUtil::sendFailure("Unknown command in ConfigGUI: $cmd", true);
        }
    }

    /**
     * Constructor.
     * Used to inject JS and CCS, the plugin config GUI does not inherit from the plugin main GUI.
     */
	public function __construct()
	{
        global $ilPluginAdmin, $tpl;
        $pl = $ilPluginAdmin->getPluginObject(IL_COMP_SERVICE, "Repository", "robj", "ExamMgr");
        $this->plugin_dir = $pl->getDirectory();
        $tpl->addJavaScript("{$this->plugin_dir}/js/examMgr.js");
        $tpl->addCss("{$this->plugin_dir}/templates/examMgr.css");
	}

    /**
     * Add a new room to DB with submitted form data.
     */
    private function addRoom() {
        global $ilCtrl, $tpl;
        $roomsForm = new ilExamMgrFormRooms($this);
        if($roomsForm->process()) {
            $this->configure();
        } else {
            $tpl->setContent($roomsForm->getHTML());
        }
    }

    /**
     * Display form to edit selected room.
     * @param $id room id (id is DB key)
     */
    private function editRoom($id) {
        global $ilCtrl, $tpl;
        $roomsForm = new ilExamMgrFormRooms($this, $id);
        $tpl->setContent($roomsForm->getHTML());
    }

    /**
     * Save edited room to DB.
     */
    private function saveRoom($id) {
        global $ilCtrl, $tpl;
        $roomsForm = new ilExamMgrFormRooms($this, $id);
        if($roomsForm->process($id)) {
            $ilCtrl->redirect($this, "configure");
        }
    }

    /**
     * Display configuration and room management form.
     */
    private function configure()
    {
        global $tpl;
        $form = $this->initConfigurationForm();
        $roomsForm = new ilExamMgrFormRooms($this);
        $tpl->setContent($form->getHTML().$roomsForm->getHtml());
    }


    /**
     * Create and fill plugin configuration form.
     * @return ilPropertyFormGUI The configuration form filled with current values.
     */
    public function initConfigurationForm()
    {
        global $lng, $ilCtrl;

        $pl = $this->pl;

        $form = new ilPropertyFormGUI();
        $form->setTitle($pl->txt("pluginConfig"));

        // ILIAS REST + "normal" access
        // REST communication might use a different interface then web access.
        // E.g. REST between NATed VMs, web access via port forwarding from VM host.
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($pl->txt("sectionAssessmentSystem"));
        $form->addItem($sh);

        $ti = new ilTextInputGUI($pl->txt("addrAssessmentSystemREST"), "assessment_host");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("addrAssessmentSystemWeb"), "assessment_host_web");
        $ti->setInfo($pl->txt("addrAssessmentSystemWebExpl"));
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("pathAssessmentSystem"), "assessment_path");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("clientAssessmentSystem"), "assessment_client");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("apiKeyAssessmentSystem"), "assessment_apikey");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("apiSecretAssessmentSystem"), "assessment_apisecret");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $cb = new ilCheckBoxInputGUI($pl->txt("secureAssessmentSystem"), "assessment_secure");
        $cb->setOptionTitle($pl->txt("secureAssessmentSystemDescr"));
        $form->addItem($cb);

        /* not needed because "client credentials" are used
        $ti = new ilTextInputGUI($pl->txt("userAssessmentSystem"), "assessment_user");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("passAssessmentSystem"), "assessment_pass");
        $ti->setMaxLength(100);
        $form->addItem($ti);
         */

        // RT
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($pl->txt("sectionTicketSystem"));
        $form->addItem($sh);

        $cb = new ilCheckBoxInputGUI($pl->txt("disableTicketSystem"), "rt_disabled");
        $form->addItem($cb);

        $ti = new ilTextInputGUI($pl->txt("pathTicketSystem"), "rt_path");
        $ti->setInfo($pl->txt("infoPathTicketSystem"));
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("queueTicketSystem"), "rt_queue");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("userTicketSystem"), "rt_user");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $pi = new ilTextInputGUI($pl->txt("passTicketSystem"), "rt_pass");
        $pi->setMaxLength(100);
        $form->addItem($pi);

        // LDAP
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($pl->txt("ldap_server"));
        $form->addItem($sh);

        $ti = new ilTextInputGUI($pl->txt("ldap_host"), "ldap_host");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("ldap_port"), "ldap_port");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("ldap_pass"), "ldap_pass");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("ldap_binddn"), "ldap_binddn");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("ldap_basedn_stud"), "ldap_basedn_stud");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("ldap_basedn_staff"), "ldap_basedn_staff");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        // Mailing
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($pl->txt("smtp_server"));
        $form->addItem($sh);

        $ti = new ilTextInputGUI($pl->txt("smtp_host"), "smtp_host");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("smtp_port"), "smtp_port");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("smtp_from"), "smtp_from");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        // Calendar
        $sh = new ilFormSectionHeaderGUI();
        $sh->setTitle($pl->txt("calendar"));
        $form->addItem($sh);

        $ti = new ilTextInputGUI($pl->txt("calendarURL"), "cal_url");
        $ti->setInfo($pl->txt("calendarURLHint"));
        $ti->setMaxLength(1000);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("calendarUser"), "cal_user");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $ti = new ilTextInputGUI($pl->txt("calendarPass"), "cal_pass");
        $ti->setMaxLength(100);
        $form->addItem($ti);

        $settings = ilExamMgrPlugin::getSettings();
        $form->setValuesByArray($settings);
        $form->addCommandButton("save", $lng->txt("save"));
        $form->setFormAction($ilCtrl->getFormAction($this));
        return $form;
    }

    /**
     * Save settings.
     * Save form input to DB if valid, display form again.
     */
    public function save()
    {
        global $ilUtil, $tpl;

        $form = $this->initConfigurationForm();

        if ($form->checkInput())
        {
            ilExamMgrPlugin::setSettings($form);
            ilUtil::sendSuccess("Data saved", true);
            $this->configure();
        } else {
            $form->setValuesByPost();
            $tpl->setContent($form->getHtml());
        }
    }

}
