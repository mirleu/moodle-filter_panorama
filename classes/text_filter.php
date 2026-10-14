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
 * Filter entry point for Moodle 4.5 and later.
 *
 * Moodle 4.5+ resolves \filter_panorama\text_filter through the class autoloader
 * and never includes filter.php. All behaviour lives in \filter_panorama\injector
 * so that the legacy entry point stays identical.
 *
 * @package    filter_panorama
 * @author     Darren Mei
 * @copyright  Copyright (c) 2020 YuJa Inc. (https://www.yuja.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_panorama;

defined('MOODLE_INTERNAL') || die();

class text_filter extends \core_filters\text_filter
{
    public function setup($page, $context)
    {
        injector::setup($page, $context);
    }

    public function filter($text, array $options = [])
    {
        return $text;
    }
}
