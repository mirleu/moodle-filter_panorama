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
 * Panorama admin settings.
 *
 * IMPORTANT: every setting below is stored under the config plugin name 'panorama',
 * NOT 'filter_panorama'. That is how these settings were always stored, back when
 * they lived in local_panorama, and it is what makes moving this file a no-op for
 * existing sites -- the values in config_plugins are untouched and are still read
 * via get_config('panorama') by \filter_panorama\injector and
 * \filter_panorama\common_helper_panorama. Do not rename these keys.
 *
 * @package    filter_panorama
 * @author     Darren Mei
 * @copyright  Copyright (c) 2020 YuJa Inc. (https://www.yuja.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $PAGE;

if ($hassiteconfig) {
	if (!$ADMIN->locate('panorama')) {
		$ADMIN->add('modules', new admin_category('panorama', new lang_string('panorama', 'filter_panorama')));
	}

	$panoramasettings = new admin_settingpage('panoramasettings', new lang_string('settings', 'filter_panorama'));

	$panoramasettings->add(new admin_setting_configpasswordunmask(
		'panorama/key1',
		new lang_string('key1', 'filter_panorama'),
		new lang_string('key1desc', 'filter_panorama'),
		'',
		PARAM_TEXT
	));

	$panoramasettings->add(new admin_setting_configpasswordunmask(
		'panorama/key2',
		new lang_string('key2', 'filter_panorama'),
		new lang_string('key2desc', 'filter_panorama'),
		'',
		PARAM_TEXT
	));

	$panoramasettings->add(new admin_setting_configpasswordunmask(
		'panorama/consumerkey',
		new lang_string('consumerkey', 'filter_panorama'),
		new lang_string('consumerkeydesc', 'filter_panorama'),
		'',
		PARAM_TEXT
	));

	$panoramasettings->add(new admin_setting_configpasswordunmask(
		'panorama/ltikey',
		new lang_string('ltikey', 'filter_panorama'),
		new lang_string('ltikeydesc', 'filter_panorama'),
		'',
		PARAM_TEXT
	));

	$environment_setting = new admin_setting_configselect(
		'panorama/environment',
		new lang_string('environment', 'filter_panorama'),
		new lang_string('environmentdesc', 'filter_panorama'),
		'Production US',
		array('Staging' => 'Staging', 'Production US' => 'Production US', 'Production CA' => 'Production CA', 'Production EU' => 'Production EU', 'Production AZ' => 'Production AZ')
	);

	if (method_exists($environment_setting, 'set_lockable')) {
		$environment_setting->set_lockable(true);
	}


	$panoramasettings->add($environment_setting);

	$ADMIN->add('panorama', $panoramasettings);
}

// Moodle pre-creates a $settings page for any plugin that ships settings.php and adds
// it under the plugin type's category after this file is included. We build our own
// category above to keep the admin path customers already know
// (Plugins > Panorama > Panorama for Moodle Settings), so discard the page Moodle
// handed us to avoid an empty duplicate appearing under Plugins > Filters.
$settings = null;
