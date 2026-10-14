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
 * Filter post install hook.
 *
 * Moodle installs every filter plugin in the "Disabled" state, which would leave
 * Panorama silently doing nothing until an admin found
 * Plugins > Filters > Manage filters. Switch it on instead, the same way core
 * Moodle's own filters do (see filter/mathjaxloader/db/install.php and
 * filter/activitynames/db/install.php).
 *
 * Notes for anyone changing this:
 *  - The first argument is the bare filter name, NOT the component. Passing
 *    'filter_panorama' or 'filter/panorama' either throws a coding_exception or
 *    trips a legacy-format warning inside filter_set_global_state().
 *  - $move = 1 only normalises sortorder. A new filter_active row is inserted at
 *    sortorder 99999, and filter ordering is irrelevant to this plugin anyway
 *    because filter() is a deliberate no-op -- the filter API is used purely as a
 *    per-page JS injection hook.
 *  - Moodle runs this on FRESH INSTALL ONLY. Upgrades take the db/upgrade.php
 *    branch in upgrade_plugins(), so existing sites are deliberately left alone
 *    and keep whatever filter state their admin chose.
 *
 * @package    filter_panorama
 * @copyright  Copyright (c) 2026 YuJa Inc. (https://www.yuja.com/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_filter_panorama_install() {
    global $CFG;
    require_once("$CFG->libdir/filterlib.php");

    filter_set_global_state('panorama', TEXTFILTER_ON, 1);
}
