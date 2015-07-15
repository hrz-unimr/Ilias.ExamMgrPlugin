<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

/**
 * Class to display radiobuttons next to each other.
 */
class ilHorizontalRadioGroupInputGUI extends ilRadioGroupInputGUI {
    /**
     * Render this form element.
     * @return string HTML fragment, showing the radio buttons next to each other.
     */
    public function render() {
        global $ilPluginAdmin; 
        $pl = $ilPluginAdmin->getPluginObject(IL_COMP_SERVICE, "Repository", "robj", "ExamMgr");
        $plugin_dir = $pl->getDirectory();
        $tpl = new ilTemplate("tpl.prop_radio_horiz.html", true, true, $plugin_dir);
        foreach($this->getOptions() as $option) {
            $tpl->setCurrentBlock("prop_radio_option");
            if (!$this->getDisabled()) {
                $tpl->setVariable("POST_VAR", $this->getPostVar());
            }
            $tpl->setVariable("VAL_RADIO_OPTION", $option->getValue());
            $tpl->setVariable("OP_ID", $this->getFieldId()."_".$option->getValue());
            $tpl->setVariable("FID", $this->getFieldId());
            if($this->getDisabled() or $option->getDisabled()) {
                $tpl->setVariable('DISABLED','disabled="disabled" ');
            }
            if ($option->getValue() == $this->getValue()) {
                $tpl->setVariable("CHK_RADIO_OPTION",'checked="checked"');
            }
            $tpl->setVariable("TXT_RADIO_OPTION", $option->getTitle());
            $tpl->parseCurrentBlock();
        }

        $tpl->setVariable("ID", $this->getFieldId());
        if ($this->getDisabled()) {
            $tpl->setVariable("HIDDEN_INPUT",$this->getHiddenTag($this->getPostVar(), $this->getValue()));
        }
        return $tpl->get();

    }
}
