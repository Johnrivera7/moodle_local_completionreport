<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Version metadata for local_completionreport.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera <https://github.com/Johnrivera7>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_completionreport';
$plugin->version   = 2026090102;
$plugin->requires  = 2024100700; // Moodle 4.5+ (subsections / Moodle 5 compatible).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.2';
