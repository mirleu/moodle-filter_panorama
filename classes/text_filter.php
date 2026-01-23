<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    filter_panorama
 * @author     Darren Mei
 * @copyright  Copyright (c) 2020 YuJa Inc. (https://www.yuja.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_panorama;
defined('MOODLE_INTERNAL') || die();
define('AES_METHOD', 'aes-256-cbc');

class text_filter extends \core_filters\text_filter
{
    public function __construct($context, array $localconfig)
    {
        parent::__construct($context, $localconfig);
    }

    public function setup($page, $context)
    {
        try{
            global $USER, $COURSE, $PAGE, $DB;
            static $js_initialized = false;

            if ($js_initialized) {
                return;
            }

            $config = get_config('panorama');
            $courseContext = \context_course::instance($COURSE->id);
            $key = $config->key1;
            $role = '';
            $rolesLTIFormat = '';
            $roles = get_user_roles($courseContext, $USER->id);

            $ltiRoleUris = [];

            foreach ($roles as $ltiRole) {
                $shortname = $DB->get_field('role', 'shortname', ['id' => $ltiRole->roleid]);
                $ltiUri = $this->map_moodle_role_to_lti_uri($shortname);
                if ($ltiUri && !in_array($ltiUri, $ltiRoleUris)) {
                    $ltiRoleUris[] = $ltiUri;
                }
            }

            $rolesLTIFormat = implode(',', $ltiRoleUris);
            
            if(is_siteadmin()){
                $role = 'admin';
                if (!is_null($rolesLTIFormat)) {
                    $rolesLTIFormat = "$rolesLTIFormat,http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator";
                }
                
            } elseif (has_capability('moodle/course:managefiles', $courseContext, $USER->id)) {
                $role = 'instructor';
            } else {
                $role = 'student';
            }

            $serverUrl = 'UNKNOWN';
            $cdnUrl = 'UNKNOWN';
            switch ($config->environment) {
                case "Staging":
                    $serverUrl = "https://staging-panorama-api.yuja.com";
                    $cdnUrl = "https://staging-cdn-panorama.yuja.com";
                    break;
                case "Production US":
                    $serverUrl = "https://panorama-api.yuja.com";
                    $cdnUrl = "https://cdn-panorama.yuja.com";
                    break;
                case "Production CA":
                    $serverUrl = "https://panorama-api-cz.yuja.com";
                    $cdnUrl = "https://cdn-panorama.yuja.com";
                    break;
                case "Production EU":
                    $serverUrl = "https://panorama-api-ez.yuja.com";
                    $cdnUrl = "https://cdn-panorama.yuja.com";
                    break;
                case "Production AZ":
                    $serverUrl = "https://panorama-api-az.yuja.com";
                    $cdnUrl = "https://cdn-panorama.yuja.com";
                    break;
            }

            $panorama_moodle_user_hint = '';

            if (!is_null($USER)){

                $userEmail = '';
                $userFirstName = '';
                $userLastName = '';

                if (isset($USER->email)){
                    $userEmail = $USER->email;
                }

                if (isset($USER->firstname)){
                    $userFirstName = $USER->firstname;
                }

                if (isset($USER->lastname)){
                    $userLastName = $USER->lastname;
                }

                $user_data = array(
                    'role' => $role,
                    'userId' => $USER->id,
                    'email' => $userEmail,
                    'firstName' => $userFirstName,
                    'lastName' => $userLastName,
                    'ltiRoles' => $rolesLTIFormat,
                );
                
                $json_user_data = json_encode($user_data);
        
                $user_key = $this->generate_key($config->ltikey, $config->consumerkey);
        
                $panorama_moodle_user_hint = $this->encrypt($json_user_data, $user_key);    
            }

            $courseContextFilterState = $this->getContextFilterState($courseContext);
            if($courseContextFilterState == -1){
                $js_initialized = false;
                return;
            }
            else {
                $PAGE->requires->js_call_amd('filter_panorama/panorama', 'init', [$panorama_moodle_user_hint, $serverUrl, $cdnUrl, $key, $COURSE->id]);
                $js_initialized = true;
            }
            
        }
        catch(\Exception $e){
            echo $e->getMessage();
        }
    }

    public function filter($text, array $options = [])
    {
        return $text;
    }

    public function map_moodle_role_to_lti_uri($shortname) {
        switch ($shortname) {
            case 'manager':
                return 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator';
            case 'coursecreator':
                return 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#ContentDeveloper';
            case 'editingteacher':
                return 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
            case 'teacher':
                return 'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor';
            case 'student':
                return 'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner';
            case 'guest':
                return 'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Guest';
            default:
                return null;
        }
    }

    public function encrypt($user_data, $key){
        $iv_size = openssl_cipher_iv_length(AES_METHOD);
        $iv = openssl_random_pseudo_bytes($iv_size);
        $ciphertext = openssl_encrypt($user_data, AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        $ciphertext_hex = bin2hex($ciphertext);
        $iv_hex = bin2hex($iv);
        return "$iv_hex:$ciphertext_hex";
    }

    public function generate_key($ltikey, $consumerkey){
        $user_key = '';

        $i = 0;

        while($i < strlen($ltikey)){
            if($i == 32){
                break;
            }
            $user_key = $user_key.$ltikey[$i];
            $i++;
        }

        for($index = 0; $index < (32-strlen($ltikey)); $index++){
            if($index >= strlen($consumerkey)){
                $user_key = $user_key.'0';
            }else{
                $user_key = $user_key.$consumerkey[$index];
            }
           
        }
        return $user_key;
    }

    public function getContextFilterState($courseContext)
    {
        $panoramaFilterState = 1;
        $contextfilters = \filter_get_available_in_context($courseContext);
        if ($contextfilters && $contextfilters['panorama']) {
            $panoramaFilterState = $contextfilters['panorama']->localstate == 0 ? $contextfilters['panorama']->inheritedstate : $contextfilters['panorama']->localstate;
        }
        return $panoramaFilterState;
    }
}
