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
require_once($CFG->dirroot. '/course/lib.php');

// The {$tablename}_update_instance() functions this class dispatches to live in the
// owning module's lib.php, so they are preloaded here.
//
// None of these paths is guaranteed to exist: mod_survey was dropped from Moodle core
// in the 4.5 line (returning as a downloadable plugin in 5.0), and an admin can
// uninstall any activity module. An unguarded require_once on a missing file is fatal
// at *autoload* time, which takes this class down for every caller -- including
// admin/webservice/forms.php, which runs class_exists() over every registered function
// when building the "Add functions" form for an external service. That made the three
// Panorama web services unmanageable in the admin UI on Moodle 4.5. Guard each one and
// let execute() report a missing module as a normal failure instead.
$panoramamodules = ['assign', 'book', 'data', 'folder', 'forum', 'glossary', 'label',
    'page', 'quiz', 'survey', 'wiki', 'workshop'];
foreach ($panoramamodules as $panoramamodule) {
    $panoramamodulelib = $CFG->dirroot . '/mod/' . $panoramamodule . '/lib.php';
    if (file_exists($panoramamodulelib)) {
        require_once($panoramamodulelib);
    }
}
unset($panoramamodules, $panoramamodule, $panoramamodulelib);

if (file_exists($CFG->dirroot . '/mod/lesson/locallib.php')) {
    require_once($CFG->dirroot . '/mod/lesson/locallib.php');
}

use filter_panorama\common_helper_panorama;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use external_format_value;
use external_util;
use context_module;
use context_system;

/**
 * Class for updating HTML Content in a Moodle resource, generally a page module
 * or an 'intro' section of a module.
 * Given a document ID, an HTML file is fetched from Panorama and its contents overwrite
 * the current module's HTML content.
 */
class panorama_update_html extends \external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'resourceid' => new external_value(PARAM_INT, 'Id of the resource we want to edit.'),
            'name' => new external_value(PARAM_TEXT, 'Updated name of the resource we want to edit.'),
            'documentid' => new external_value(PARAM_TEXT, 'Id of document containing new content'),
            'identifierkey' => new external_value(PARAM_TEXT, 'Identifier Key for Panorama'),
            'tablename' => new external_value(PARAM_TEXT, 'Name of the table of the resource we want to edit', VALUE_REQUIRED),
            'coursemodule' => new external_value(PARAM_INT, 'The ID of the course module', VALUE_DEFAULT, 0),
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
     * Updates page
     * @param int $resourceid resource id
     * @param string $name name to be updated
     * @param string $documentid id of document containing new content
     * @param string $identifierkey identifier key for panorama
     * @param string $tablename table of resource to be updated
     */
    public static function execute($resourceid, $name, $documentid, $identifierkey, $tablename, $coursemodule) {
        global $DB;
        try {
            self::validate_context(context_system::instance());
            $params = self::validate_parameters(self::execute_parameters(),
                compact('resourceid', 'name', 'documentid', 'identifierkey', 'tablename', 'coursemodule'));

            if ((empty($params['resourceid']) || empty($params['documentid']) ||
                empty($params['identifierkey']) || empty($params['tablename']))) {
                throw new invalid_parameter_exception('Missing parameters:');
            }

            $badtags = ['/<html[^>]*>/', '/<\/html>/', '/<body[^>]*>/', '/<\/body>/', '/<base[^>]*>/'];

             if ($tablename === 'lesson_pages') {
                if (empty($coursemodule)) {
                    throw new invalid_parameter_exception('Course module ID required for lesson_pages');
                }

                $cm = get_coursemodule_from_id('lesson', $coursemodule, 0, false, MUST_EXIST);
                $lessoncontext = context_module::instance($cm->id);
            
                self::validate_context($lessoncontext);
                require_capability('mod/lesson:edit', $lessoncontext);

                $lesson = new \lesson($DB->get_record('lesson', ['id' => $cm->instance], '*', MUST_EXIST));
                $page = \lesson_page::load($resourceid, $lesson);
                try {              
                    $signedurl = common_helper_panorama::get_signed_url($params['documentid'], $params['identifierkey']);
                    $contenttemp = file_get_contents($signedurl);
                } catch (\Exception $e) {
                    return [
                        'status' => 'failed',
                        'fileid' => 0,
                        'error' => "Error occurred: " . $e->getMessage(),
                    ];
                }

                if ($contenttemp === false) {
                    throw new \moodle_exception('Failed to retrieve content');
                }

                $content = preg_replace($badtags, '', $contenttemp);
                if ($content === null) {
                    throw new \moodle_exception('Failed to retrieve content');
                }

                $data = $page->properties();
                $data->contents_editor = [
                    'text'   => $content, 
                    'format' => FORMAT_HTML,
                    'itemid' => 0 
                ];
        
                $answers = $page->get_answers();
                $data->answer_editor = [];
                $data->jumpto = [];
                $data->score = [];

                $i = 0;
                foreach ($answers as $answer) {
                    $data->answer_editor[$i] = $answer->answer;
                    $data->jumpto[$i]        = $answer->jumpto;
                    $data->score[$i]         = $answer->score;
                    $i++;
                }

                $data->id       = $resourceid;
                $data->lessonid = $lesson->id;
                $data->qtype    = $page->qtype;
                
               try {
                    $page->update($data, $lessoncontext);
                } catch (\Exception $e) {
                    return [
                        'status' => 'failed',
                        'fileid' => $resourceid,
                        'error' => "Update error: " . $e->getMessage(),
                    ];
                }

                return [
                    'status' => 'success',
                    'fileid' => $resourceid,
                    'error'  => '',
                ];
            }

            // Determine which field to edit and the name of the DB update function.
            $contentfieldname = 'content';
            $updatefunction = '';
            switch ($tablename) {
                case 'assign':
                case 'forum': // Maps to Announcement.
                case 'book':
                case 'data':
                case 'folder':
                case 'glossary':
                case 'label':
                case 'quiz':
                case 'survey':
                case 'wiki':
                case 'workshop':
                    $contentfieldname = 'intro';
                    $updatefunction = $tablename . '_update_instance';
                    break;
                case 'forum_posts': // Maps to Discussion Topic.
                case 'forum_discussions': // Maps to Discussion Topic.
                case 'discussion':
                case 'discussion-topic': // Maps to Forum Topic.
                    $contentfieldname = 'message';
                    $updatefunction = 'forum_update_post';
                    break;
                case 'page':
                default:
                    $contentfieldname = 'content';
                    $updatefunction = $tablename . '_update_instance';
                    break;
            }

            $resource = $DB->get_record("$tablename", ['id' => $resourceid]);
            if (!$resource) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => get_string('invalidresourceid', 'filter_panorama'),
                ];
            }
            $resolvedcoursemodule = self::resolve_coursemodule_id($tablename, (int) $resourceid, (int) $coursemodule);
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
                // For page, this should be a string of the HTML content of the file.
                $contenttemp = file_get_contents($signedurl);
                if ($contenttemp === false) {
                    return [
                        'status' => 'failed',
                        'fileid' => 0,
                        'error' => get_string('failedtoretrievecontent', 'filter_panorama'),
                    ];
                }
                $content = preg_replace($badtags, '', $contenttemp);
                if ($content === null) {
                    return [
                        'status' => 'failed',
                        'fileid' => 0,
                        'error' => get_string('failedtoretrievecontent', 'filter_panorama'),
                    ];
                }
                $resource->{$contentfieldname} = $content;
                $formatfield = $contentfieldname . 'format';
                if (property_exists($resource, $formatfield)) {
                    $resource->{$formatfield} = FORMAT_HTML;
                }
                if ($tablename === 'page') {
                    $resource->page = [
                        'text' => $content,
                        'format' => FORMAT_HTML,
                        'itemid' => 0,
                    ];
                }
                $resource->instance = $resource->id;
                if (property_exists($resource, 'revision')) {
                    $resource->revision++;
                }
                $resource->timemodified = time();

                if (!empty($resolvedcoursemodule)) {
                    $resource->coursemodule = $resolvedcoursemodule;
                }
                
                // The module owning $tablename may not be installed on this site, in which
                // case its lib.php was skipped above and the update function is undefined.
                // Calling it would be a fatal, so report it as a normal failure instead.
                if (!function_exists($updatefunction)) {
                    return [
                        'status' => 'failed',
                        'fileid' => 0,
                        'error' => "Cannot update '$tablename': $updatefunction() is unavailable. "
                            . "The module that provides it is not installed on this site.",
                    ];
                }

                $result = $updatefunction($resource, null);
                if (!$result) {
                    return [
                        'status' => 'failed',
                        'fileid' => 0,
                        'error' => get_string('failedtoupdate', 'filter_panorama'),
                    ];
                }
            } catch (\Exception $e) {
                return [
                    'status' => 'failed',
                    'fileid' => 0,
                    'error' => "Error occurred: " . $e->getMessage(),
                ];
            }
            return ['status' => 'success', 'fileid' => $resourceid, 'error' => "Table: $tablename, id: $resourceid"];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'fileid' => 0,
                'error' => "Error occurred: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Determines the appropriate course module id for the provided table and instance.
     *
     * @param string $tablename Table name the resource belongs to
     * @param int $instanceid Instance id within the table
     * @param int $coursemodule Provided course module id from the request
     * @return int
     */
    private static function resolve_coursemodule_id($tablename, $instanceid, $coursemodule) {
        if (!empty($coursemodule)) {
            return (int) $coursemodule;
        }

        $supportedmodules = [
            'assign',
            'forum',
            'book',
            'data',
            'folder',
            'glossary',
            'label',
            'page',
            'quiz',
            'survey',
            'wiki',
            'workshop',
        ];

        if (!in_array($tablename, $supportedmodules, true)) {
            return 0;
        }

        try {
            $cm = get_coursemodule_from_instance($tablename, $instanceid, 0, false, IGNORE_MISSING);
            if ($cm && !empty($cm->id)) {
                return (int) $cm->id;
            }
        } catch (\Exception $e) {
            // Ignore failures and fall back to zero.
        }

        return 0;
    }
}
