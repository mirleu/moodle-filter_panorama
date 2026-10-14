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

$string['filtername'] = 'Panorama for Moodle';
$string['pluginname'] = 'Panorama for Moodle';
$string['privacy:metadata'] = 'The Panorama For Moodle plugin does not collect or store any personal user data.';

// Admin tree.
$string['panorama'] = 'Panorama';
$string['settings'] = 'Panorama for Moodle Settings';

// Settings strings. The *desc names are the ones settings.php references.
$string['environment'] = 'Panorama Environment';
$string['environmentdesc'] = '';
$string['key1'] = 'Primary Identify key';
$string['key1desc'] = '';
$string['key2'] = 'Secondary Identify Key';
$string['key2desc'] = '';
$string['consumerkey'] = 'LTI Consumer Key';
$string['consumerkeydesc'] = '';
$string['ltikey'] = 'LTI Key';
$string['ltikeydesc'] = '';
$string['visualizerversion'] = 'Visualizer Version (Optional)';
$string['visualizerversiondesc'] = '';
$string['visualizerintegrity'] = 'Integrity Hash of Selected Version';
$string['visualizerintegritydesc'] = '';

// Web service error messages. These are referenced by classes/external/* and were
// previously missing from every language file, so they rendered as [[stringname]].
$string['invalidfilepath'] = 'The supplied file path is not valid.';
$string['invalidresourceid'] = 'The supplied resource ID does not match a resource in this Moodle site.';
$string['filenotfound'] = 'The file to be replaced could not be found in Moodle.';
$string['failedtoretrievecontent'] = 'The replacement content could not be retrieved from Panorama.';
$string['failedtocreatefile'] = 'The replacement file could not be created in Moodle.';
$string['failedtoupdate'] = 'The Moodle resource could not be updated with the replacement content.';
