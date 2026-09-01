<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Visual course-order traversal for completion-tracked activities.
 *
 * Fixes report_progress ordering when Moodle 5 subsections use high internal section numbers.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_completionreport;

defined('MOODLE_INTERNAL') || die();

/**
 * Activity ordering helper.
 */
class activity_order {

    /**
     * Ordered activity descriptor.
     *
     * @var object{cm:\cm_info,orderindex:int,sectionpath:string[],sectionname:string,
     *     sectionnum:int,modname:string,activityname:string,sectionkey:string}
     */

    /**
     * Return completion-tracked activities in visual course page order.
     *
     * @param \stdClass $course
     * @param int|null $userid For availability; 0 = no user context.
     * @return \stdClass[]
     */
    public static function get_ordered_activities(\stdClass $course, ?int $userid = null): array {
        $modinfo = get_fast_modinfo($course, $userid ?? 0);
        $result = [];
        $orderindex = 0;

        foreach ($modinfo->get_listed_section_info_all() as $section) {
            self::append_section($course, $modinfo, $section, $result, $orderindex, []);
        }

        return $result;
    }

    /**
     * Top-level / subsection labels for filters.
     *
     * @param \stdClass $course
     * @return array<int,string> sectionnum => label
     */
    public static function get_section_options(\stdClass $course): array {
        $modinfo = get_fast_modinfo($course);
        $options = [];

        foreach ($modinfo->get_listed_section_info_all() as $section) {
            $name = get_section_name($course, $section->section);
            if ($name !== '') {
                $options[$section->section] = $name;
            }
            self::collect_subsection_options($modinfo, $section, $options);
        }

        return $options;
    }

    /**
     * @param \stdClass $course
     * @param \course_modinfo $modinfo
     * @param \section_info $section
     * @param \stdClass[] $result
     * @param int $orderindex
     * @param string[] $sectionpath
     */
    protected static function append_section(
        \stdClass $course,
        \course_modinfo $modinfo,
        \section_info $section,
        array &$result,
        int &$orderindex,
        array $sectionpath
    ): void {
        $sectionname = get_section_name($course, $section->section);
        $path = $sectionpath;
        if ($sectionname !== '' && ($path === [] || end($path) !== $sectionname)) {
            $path[] = $sectionname;
        }

        if (empty($modinfo->sections[$section->section])) {
            return;
        }

        foreach ($modinfo->sections[$section->section] as $cmid) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            if ($cm->deletioninprogress) {
                continue;
            }

            if ($cm->modname === 'subsection') {
                $delegated = $cm->get_delegated_section_info();
                if ($delegated) {
                    $subsectionpath = array_merge($path, [$cm->name]);
                    self::append_section($course, $modinfo, $delegated, $result, $orderindex, $subsectionpath);
                }
                continue;
            }

            if ($cm->completion == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $result[] = (object) [
                'cm' => $cm,
                'orderindex' => $orderindex++,
                'sectionpath' => $path,
                'sectionname' => $sectionname,
                'sectionnum' => $section->section,
                'sectionkey' => implode(' > ', $path),
                'modname' => $cm->modname,
                'activityname' => format_string($cm->name, true, ['context' => $cm->context]),
            ];
        }
    }

    /**
     * @param \course_modinfo $modinfo
     * @param \section_info $section
     * @param array<int,string> $options
     */
    protected static function collect_subsection_options(
        \course_modinfo $modinfo,
        \section_info $section,
        array &$options
    ): void {
        if (empty($modinfo->sections[$section->section])) {
            return;
        }

        foreach ($modinfo->sections[$section->section] as $cmid) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            if ($cm->modname !== 'subsection' || $cm->deletioninprogress) {
                continue;
            }
            $delegated = $cm->get_delegated_section_info();
            if (!$delegated) {
                continue;
            }
            $label = $cm->name;
            $options[$delegated->section] = $label;
            self::collect_subsection_options($modinfo, $delegated, $options);
        }
    }
}
