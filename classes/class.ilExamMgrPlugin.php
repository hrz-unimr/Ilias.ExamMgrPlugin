<?php
/**
 *  examManager -- ILIAS Plugin for the administration of e-assessments
 *  Copyright (C) 2015 Jasper Olbrich (olbrich <at> hrz.uni-marburg.de)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Jasper Olbrich (olbrich@hrz.uni-marburg.de)
 */

require_once "./Services/Repository/classes/class.ilRepositoryObjectPlugin.php";

/**
 * Plugin Class.
 */
class ilExamMgrPlugin extends ilRepositoryObjectPlugin
{
    /**
     * Names and types of plugin's settings.
     */
    private static $settings = array(
        'assessment_host' => 'text',
        'assessment_host_web' => 'text',
        'assessment_secure' => 'boolean',
        'assessment_path' => 'text',
        'assessment_client' => 'text',
        'assessment_apikey' => 'text',
        'assessment_apisecret' => 'pass',
//        'assessment_user' => 'text',
//        'assessment_pass' => 'pass',
        'rt_enabled' => 'boolean',
        'rt_path' => 'text',
        'rt_queue' => 'text',
        'rt_user' => 'text',
        'rt_pass' => 'pass',
        'ldap_host' => 'text',
        'ldap_port' => 'integer',
        'ldap_encryption' => 'text',
        'ldap_pass' => 'text',
        'ldap_binddn' => 'text',
        'ldap_basedn_stud' => 'text',
        'ldap_basedn_staff' => 'text',
        'smtp_host' => 'text',
        'smtp_port' => 'integer',
        'smtp_from' => 'text',
        'cal_url' => 'text',
        'cal_user' => 'text',
        'cal_pass' => 'pass',
        'cal_enabled' => 'boolean'
    );

    /**
     * Get the plugin's full name.
     * Is different from {@see ilObjExamMgr::initType() the plugin type}.
     */
    function getPluginName()
    {
        return "ExamMgr";
    }

    /**
     * Password handling for db storage.
     * Uses base64 encoding to obfuscate passwords.
     * Not secure at all, but prevents "looking over the shoulder attacks".
     *
     * @param string $setting Name of the setting, used to look up the type.
     * @param string $value Value of the setting
     * @param bool $encode Whether to encode (plaintext->password) or decode.
     * @return string Converted value.
     */
    private static function handle_pass($setting, $value, $encode=true) {
        if(self::$settings[$setting] == 'pass') {
            if($encode) {
                return base64_encode($value);
            } else {
                return base64_decode($value);
            }
        } else {
            return $value;
        }
    }


    /**
     * Get all plugin settings.
     *
     * @return array Array with "setting" => "value" entries.
     */
    public static function getSettings() {
        global $ilDB;

        $settings = array();

        $db_result = $ilDB->query('SELECT setting, value from rep_robj_xemg_settings');
        while ($rec = $ilDB->fetchAssoc($db_result))
        {
            $settings[$rec['setting']] = self::handle_pass($rec['setting'], $rec['value'], $encode=false);

        }
        return $settings;
    }

    /**
     * Get a single setting.
     *
     * @param string $setting Name of setting.
     * @return string Value of setting.
     */
    public static function getSetting($setting) {
        global $ilDB;
        $db_result = $ilDB->query('SELECT value from rep_robj_xemg_settings WHERE setting = '.$ilDB->quote($setting, 'text'));
        if ($rec = $ilDB->fetchAssoc($db_result))
        {
            return self::handle_pass($setting, $rec['value'], $encode=false);
        }

        return null;
    }


    /**
     * Store settings from a submitted form.
     *
     * @param ilPropertyFormGUI $form Form submitted from {@see ilExamMgrConfigGUI configuration GUI}.
     */
    public function setSettings($form){
        global $ilDB;
            foreach(self::$settings as $name => $type){
                $value = self::handle_pass($name, $form->getInput($name), $encode=true);
                if($type == 'pass') {
                    $type = 'text'; // treat as normal text for DB quotation.
                } 
                // Unchecked checkboxes don't get sent at all.
                // $value will be ''
                $ilDB->manipulate("UPDATE rep_robj_xemg_settings ".
                    "SET value=" . $ilDB->quote($value, $type).
                    " WHERE setting=". $ilDB->quote($name, "text"));
            }
    }

    /**
     * Create a permanent link to an object on the assessment system with
     * known refId and type.
     *
     * @param int $refId RefId of target object on assessment system
     * @param string $type Type of target object, abbreviation (crs, tst, ...)
     * @return string Permanent link as URL.
     */
    public static function createPermaLink($refId, $type) {
        $host = self::getSetting("assessment_host_web");
        $path = self::getSetting("assessment_path");
        $ilias_client = self::getSetting("assessment_client");
        $secure = self::getSetting("assessment_secure");
        $url_prefix = "http" . ($secure ? "s" : "") . "://$host/$path/";
        return "{$url_prefix}goto.php?target={$type}_{$refId}&client_id=$ilias_client";
    }


}
