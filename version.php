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

$plugin->release   = 'v1.9.0';
$plugin->version   = 2026093002;
// Moodle 3.9. This is the stricter of the two floors merged into this plugin:
// the filter declared 3.3, but local_replacementapipanorama declared 3.9, and a
// site needed both for Panorama to function -- so 3.9 was already the effective
// minimum. Supports 3.9 through 5.x via the dual filter entry points
// (filter.php for < 4.5, classes/text_filter.php for 4.5+).
$plugin->requires  = 2020061500;
$plugin->component = 'filter_panorama';
$plugin->maturity  = MATURITY_STABLE;
// No dependencies: local_panorama and local_replacementapipanorama are consolidated
// into this plugin as of v1.9.0. Both must be uninstalled BEFORE installing this
// version -- see moodle-plugins/README.md for the required order.
