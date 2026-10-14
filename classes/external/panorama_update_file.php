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
 * YuJa Panorama content replacement API
 * @package    filter_panorama
 * @subpackage yuja
 * @copyright  2025 YuJa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_panorama\external;

defined('MOODLE_INTERNAL') || die('Must access from moodle');

global $CFG;
require_once($CFG->dirroot. '/lib/externallib.php');
require_once($CFG->dirroot. '/lib/completionlib.php');
require_once($CFG->dirroot. '/mod/resource/lib.php');
require_once($CFG->dirroot. '/mod/resource/locallib.php');

use filter_panorama\common_helper_panorama;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_format_value;
use invalid_parameter_exception;
use context_module;
use context_system;
use moodle_exception;

/**
 * Class for updating the content of a file stored as a Moodle resource, overwriting it with the
 * content of a file fetched from Panorama.
 */
class panorama_update_file extends \external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'filepath' => new external_value(PARAM_TEXT, 'The full file path ID, e.g., "/28/mod_resource/content/0/file.ppt"',
                VALUE_REQUIRED),
            'documentid'     => new external_value(PARAM_TEXT, 'A panorama documentid for the new file content', VALUE_REQUIRED),
            'identifierkey'  => new external_value(PARAM_TEXT, 'An institution key', VALUE_REQUIRED),
            'resourceid'    => new external_value(PARAM_INT, 'The ID of the resource instance', VALUE_REQUIRED),
            'coursemodule' => new external_value(PARAM_INT, 'The ID of the course module', VALUE_REQUIRED),
        ]);
    }

    /**
     * Returns description of method return value.
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new \external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status of the operation'),
            'fileid' => new external_value(PARAM_INT, 'The ID of the replaced file'),
            'error' => new external_value(PARAM_TEXT, 'Error message if error exists'),
        ]);
    }

    /**
     * Updates a file
     * @param string $filepath Path to file in Moodle storage
     * @param string $documentid id of document containing new content
     * @param string $identifierkey identifier key for panorama
     * @param int $resourceid id of Moodle resource in DB
     */
    public static function execute($filepath, $documentid, $identifierkey, $resourceid, $coursemodule) {
        try {
            global $DB, $CFG;

            $params = self::validate_parameters(self::execute_parameters(),
                compact('filepath', 'documentid', 'identifierkey', 'resourceid', 'coursemodule'));
            self::validate_context(context_system::instance());

            $pathparts = explode('/', ltrim($params['filepath'], '/'));
            if (count($pathparts) < 5) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => get_string('invalidfilepath', 'filter_panorama'),
                ];
            }

            $contextid = $pathparts[0];
            $component = $pathparts[1];
            $filearea  = $pathparts[2];
            $itemid    = $pathparts[3];
            $filename  = array_pop($pathparts);
            $filepath  = '/' . implode('/', array_slice($pathparts, 4)) . '/';

            $resource = $DB->get_record('resource', ['id' => $params['resourceid']], '*');

            if (!$resource) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => get_string('invalidresourceid', 'filter_panorama'),
                ];
            }

            $fs = get_file_storage();
            $existingfile = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);

            if (!$existingfile || $existingfile->is_directory()) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => get_string('filenotfound', 'filter_panorama'),
                ];
            }

            try {
                $signedurl = common_helper_panorama::get_signed_url($params['documentid'], $params['identifierkey']);
            } catch (\Exception $e) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => "Error occurred: " . $e->getMessage(),
                ];
            }

            try {
                $content = file_get_contents($signedurl);
                if ($content === false) {
                    return [
                        'status' => 'failed',
                        'fileid' => 0,
                        'error' => get_string('failedtoretrievecontent', 'filter_panorama'),
                    ];
                }

                $tempfilepath = tempnam($CFG->tempdir, 'panorama_update_file');
                file_put_contents($tempfilepath, $content);
            } catch (\Exception $e) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => "Error occurred: " . $e->getMessage(),
                ];
            }

            $newfilerecord = [
                'contextid' => $existingfile->get_contextid(),
                'component' => $existingfile->get_component(),
                'filearea' => $existingfile->get_filearea(),
                'itemid' => $existingfile->get_itemid(),
                'filepath' => $existingfile->get_filepath(),
                'filename' => $existingfile->get_filename() . "." . common_helper_panorama::generate_random_string(),
                'timecreated' => time(),
                'timemodified' => time(),
            ];

            $newfile = $fs->create_file_from_pathname($newfilerecord, $tempfilepath);
            if (!$newfile) {
                return [
                'status' => 'failed',
                    'fileid' => 0,
                    'error' => get_string('failedtocreatefile', 'filter_panorama'),
                ];
            }

            $existingfile->replace_file_with($newfile);
            $newfile->delete();
            unlink($tempfilepath);

            $resource->instance = $resource->id;
            $resource->revision++;
            $resource->timemodified = time();
            $resource->coursemodule = $coursemodule;

            $result = resource_update_instance($resource, null);
            if (!$result) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => get_string('failedtoupdate', 'filter_panorama'),
                ];
            }

            return ['status' => 'success', 'fileid' => $resource->revision, 'error' => ''];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'fileid' => 0,
                'error' => "Error occurred: " . $e->getMessage(),
            ];
        }
    }
}
