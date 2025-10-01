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


defined('MOODLE_INTERNAL') || die();
define('AES_METHOD', 'aes-256-cbc');

class filter_panorama extends moodle_text_filter {

    public function __construct($context, array $localconfig) {
        parent::__construct($context, $localconfig);
    }

    public function setup($page, $context) {
        try{
            global $USER, $COURSE, $PAGE, $DB;
            static $jsinitialized = false;

            if ($jsinitialized) {
                return;
            }

            $config = get_config('panorama');
            $coursecontext = context_course::instance($COURSE->id);
            $key = $config->key1;
            $role = '';
            $rolesltiformat = '';
            $roles = get_user_roles($coursecontext, $USER->id);

            $ltiroleuris = [];

            foreach ($roles as $ltirole) {
                $shortname = $DB->get_field('role', 'shortname', ['id' => $ltirole->roleid]);
                $ltiuri = $this->map_moodle_role_to_lti_uri($shortname);
                if ($ltiuri && !in_array($ltiuri, $ltiroleuris)) {
                    $ltiroleuris[] = $ltiuri;
                }
            }

            $rolesltiformat = implode(',', $ltiroleuris);

            if(is_siteadmin()){
                $role = 'admin';
                if (!is_null($rolesltiformat)) {
                    $rolesltiformat = "$rolesltiformat,http://purl.imsglobal.org/vocab/lis/v2/institution/person#Administrator";
                }

            } else if (has_capability('moodle/course:managefiles', $coursecontext, $USER->id)) {
                $role = 'instructor';
            } else {
                $role = 'student';
            }

            $serverurl = 'UNKNOWN';
            $cdnurl = 'UNKNOWN';
            switch ($config->environment) {
                case "Staging":
                    $serverurl = "https://staging-panorama-api.yuja.com";
                    $cdnurl = "https://staging-cdn-panorama.yuja.com";
                    break;
                case "Production US":
                    $serverurl = "https://panorama-api.yuja.com";
                    $cdnurl = "https://cdn-panorama.yuja.com";
                    break;
                case "Production CA":
                    $serverurl = "https://panorama-api-cz.yuja.com";
                    $cdnurl = "https://cdn-panorama.yuja.com";
                    break;
                case "Production EU":
                    $serverurl = "https://panorama-api-ez.yuja.com";
                    $cdnurl = "https://cdn-panorama.yuja.com";
                    break;
                case "Production AZ":
                    $serverurl = "https://panorama-api-az.yuja.com";
                    $cdnurl = "https://cdn-panorama.yuja.com";
                    break;
            }

            $panoramamoodleuserhint = '';

            if (!is_null($USER)){

                $useremail = '';
                $userfirstname = '';
                $userlastname = '';

                if (isset($USER->email)){
                    $useremail = $USER->email;
                }

                if (isset($USER->firstname)){
                    $userfirstname = $USER->firstname;
                }

                if (isset($USER->lastname)){
                    $userlastname = $USER->lastname;
                }

                $userdata = [
                    'role' => $role,
                    'userId' => $USER->id,
                    'email' => $useremail,
                    'firstName' => $userfirstname,
                    'lastName' => $userlastname,
                    'ltiRoles' => $rolesltiformat,
                ];

                $jsonuserdata = json_encode($userdata);

                $userkey = $this->generate_key($config->ltikey, $config->consumerkey);

                $panoramamoodleuserhint = $this->encrypt($jsonuserdata, $userkey);
            }

            $coursecontextfilterstate = $this->getContextFilterState($coursecontext);
            if($coursecontextfilterstate == -1){
                $jsinitialized = false;
                return;
            }
            else {
                $PAGE->requires->js_call_amd('filter_panorama/panorama', 'init', [$panoramamoodleuserhint, $serverurl, $cdnurl, $key, $COURSE->id, $config->visualizerversion, $config->visualizerintegrity]);
                $jsinitialized = true;
            }

        }
        catch(Exception $e){
            echo $e->getMessage();
        }
    }

    public function filter($text, array $options = []) {
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

    public function encrypt($userdata, $key) {
        $ivsize = openssl_cipher_iv_length(AES_METHOD);
        $iv = openssl_random_pseudo_bytes($ivsize);
        $ciphertext = openssl_encrypt($userdata, AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        $ciphertexthex = bin2hex($ciphertext);
        $ivhex = bin2hex($iv);
        return "$ivhex:$ciphertexthex";
    }

    public function generate_key($ltikey, $consumerkey) {
        $userkey = '';

        $i = 0;

        while($i < strlen($ltikey)){
            if($i == 32){
                break;
            }
            $userkey = $userkey.$ltikey[$i];
            $i++;
        }

        for($index = 0; $index < (32 - strlen($ltikey)); $index++){
            if($index >= strlen($consumerkey)){
                $userkey = $userkey.'0';
            }else{
                $userkey = $userkey.$consumerkey[$index];
            }

        }
        return $userkey;
    }

    public function getcontextfilterstate($coursecontext) {
        $panoramafilterstate = 1;
        $contextfilters = filter_get_available_in_context($coursecontext);
        if ($contextfilters && $contextfilters['panorama']) {
            $panoramafilterstate = $contextfilters['panorama']->localstate == 0 ? $contextfilters['panorama']->inheritedstate : $contextfilters['panorama']->localstate;
        }
        return $panoramafilterstate;
    }
}
