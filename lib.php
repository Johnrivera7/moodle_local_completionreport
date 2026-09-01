<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Library callbacks for local_completionreport.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add report link to course navigation (Informes).
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_completionreport_extend_navigation_course($navigation, $course, $context) {
    global $CFG;

    require_once($CFG->libdir . '/completionlib.php');

    if (!has_capability('local/completionreport:view', $context)) {
        return;
    }

    $group = groups_get_course_group($course, true);
    if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
        if (!has_capability('moodle/site:accessallgroups', $context)) {
            return;
        }
    }

    $completion = new completion_info($course);
    if (!$completion->is_enabled() || !$completion->has_activities()) {
        return;
    }

    $url = new moodle_url('/local/completionreport/index.php', ['id' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'local_completionreport'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'localcompletionreport',
        new pix_icon('i/report', '')
    );
}
