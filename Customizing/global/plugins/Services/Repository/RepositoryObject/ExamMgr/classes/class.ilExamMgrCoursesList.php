<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *  See class.ilExamMgrPlugin.php for details.
 */

require_once "class.ilExamMgrForm.php";
require_once "class.ilExamMgrRemoteCrs.php";

/**
 * Custom form element to display a table of remote courses that are linked
 * to an exam.
 */
class ilExamMgrCoursesList extends ilExamMgrFormElement {

    public function __construct(ilObjExamMgrGUI $parent) {
        parent::__construct($parent);
    }

    /**
     * Get form element title.
     *
     * Will be displayed next to the form element.
     * @return string Localized title
     */
    public function getTitle(){
        global $lng;
        return $lng->txt("rep_robj_xemg_linked_courses");
    }

    /**
     * Create the HTML to display this element.
     *
     * @return string HTML fragment.
     */
    public function render() {
        global $tpl, $lng;
        $courseListTpl = new ilTemplate("tpl.coursesList.html", true, true, $this->parent->plugin_dir);

        $courses = ilExamMgrRemoteCrs::getForExam($this->plugin_obj->getId());
        if(count($courses)) {
            $courseListTpl->setVariable("TITLE_HDR", $lng->txt("rep_robj_xemg_course_title"));
            $courseListTpl->setVariable("PASSWORD_HDR", $lng->txt("rep_robj_xemg_password"));

            foreach($courses as $crs) {
                $courseListTpl->setCurrentBlock("courses_table_row");
                $courseListTpl->setVariable("CRS_TITLE", $crs->remote_title);
                $courseListTpl->setVariable("PASSWORD", $crs->password);
                $courseListTpl->setVariable("CRS_HREF", $crs->getPermalink());
                $courseListTpl->setVariable("UNLINK_CMD", "unlink_course_{$crs->id}");
                $courseListTpl->setVariable("UNLINK_TXT", $lng->txt("rep_robj_xemg_course_unlink"));
                $courseListTpl->parseCurrentBlock();
            }
        } else {
            $courseListTpl->setVariable("COURSES_HEADER", $lng->txt("rep_robj_xemg_no_linked_courses"));
        }
        return $courseListTpl->get();
    }
}

