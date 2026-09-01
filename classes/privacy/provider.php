<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy provider.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_completionreport\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy implementation.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
