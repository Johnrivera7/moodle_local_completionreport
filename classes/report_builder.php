<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Builds the enhanced completion report dataset.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_completionreport;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

/**
 * Report data builder.
 */
class report_builder {

    /** @var \stdClass */
    protected $course;

    /** @var \context_course */
    protected $context;

    /** @var int */
    protected $groupid;

    /** @var int */
    protected $sectionfilter;

    /** @var \completion_info */
    protected $completion;

    /** @var \stdClass[] */
    protected $activities = [];

    /**
     * @param \stdClass $course
     * @param \context_course $context
     * @param int $groupid
     * @param int $sectionfilter -1 = all
     */
    public function __construct(\stdClass $course, \context_course $context, int $groupid, int $sectionfilter = -1) {
        $this->course = $course;
        $this->context = $context;
        $this->groupid = $groupid;
        $this->sectionfilter = $sectionfilter;
        $this->completion = new \completion_info($course);
        $this->activities = activity_order::get_ordered_activities($course);

        if ($sectionfilter >= 0) {
            $this->activities = array_values(array_filter(
                $this->activities,
                fn($a) => (int) $a->sectionnum === $sectionfilter
            ));
        }
    }

    /**
     * Build full report payload.
     *
     * @return array
     */
    public function build(): array {
        $activities = $this->activities;
        $activitycount = count($activities);

        $extrafields = 'u.id, u.firstname, u.lastname, u.email, u.idnumber, u.username, u.lastaccess';
        $users = $this->completion->get_tracked_users('', [], $this->groupid, $extrafields);
        \core_collator::asort_array_of_objects_by_property($users, 'lastname', \core_collator::SORT_STRING);

        $rows = [];
        $summary = [
            'totalusers' => count($users),
            'notstarted' => 0,
            'inprogress' => 0,
            'completed' => 0,
            'activitycount' => $activitycount,
        ];

        $activitystats = [];
        foreach ($activities as $act) {
            $activitystats[$act->cm->id] = [
                'name' => $act->activityname,
                'sectionkey' => $act->sectionkey,
                'completed' => 0,
                'total' => 0,
            ];
        }

        $sectionstats = [];

        $n = 0;
        foreach ($users as $user) {
            $n++;
            $progress = $this->completion->get_progress_all($user->id);
            $completedcount = 0;
            $activitycells = [];

            foreach ($activities as $act) {
                $cm = $act->cm;
                $state = $this->resolve_completion_state($progress, $cm);
                $activitycells[] = $state;
                if ($state['complete']) {
                    $completedcount++;
                    $activitystats[$cm->id]['completed']++;
                }
                $activitystats[$cm->id]['total']++;

                $skey = $act->sectionkey ?: $act->sectionname;
                if (!isset($sectionstats[$skey])) {
                    $sectionstats[$skey] = ['completed' => 0, 'total' => 0, 'label' => $skey];
                }
                $sectionstats[$skey]['total']++;
                if ($state['complete']) {
                    $sectionstats[$skey]['completed']++;
                }
            }

            $pct = $activitycount > 0 ? round(($completedcount / $activitycount) * 100) : 0;
            if ($completedcount === 0) {
                $summary['notstarted']++;
                $progressclass = 'none';
            } else if ($completedcount >= $activitycount) {
                $summary['completed']++;
                $progressclass = 'full';
            } else {
                $summary['inprogress']++;
                $progressclass = 'partial';
            }

            $coursecomplete = '';
            $coursecompletedate = '';
            if ($this->completion->is_course_complete($user->id)) {
                $coursecomplete = get_string('yes');
                $ccompletion = new \completion_completion(['course' => $this->course->id, 'userid' => $user->id]);
                if ($ccompletion->timecompleted) {
                    $coursecompletedate = userdate($ccompletion->timecompleted);
                }
            } else {
                $coursecomplete = get_string('no');
            }

            $lastaccess = $user->lastaccess
                ? userdate($user->lastaccess, get_string('strftimedatefullshort', 'langconfig'))
                : get_string('never');

            $rows[] = [
                'n' => $n,
                'userid' => $user->id,
                'fullname' => fullname($user),
                'email' => $user->email,
                'idnumber' => $user->idnumber ?? '',
                'username' => $user->username,
                'progress' => $pct,
                'progressclass' => $progressclass,
                'completedcount' => $completedcount,
                'activitytotal' => $activitycount,
                'coursecomplete' => $coursecomplete,
                'coursecompletedate' => $coursecompletedate,
                'lastaccess' => $lastaccess,
                'profileurl' => (new \moodle_url('/user/view.php', [
                    'id' => $user->id,
                    'course' => $this->course->id,
                ]))->out(false),
                'activities' => $activitycells,
                'searchtext' => strtolower(fullname($user) . ' ' . $user->email . ' ' . ($user->idnumber ?? '')),
            ];
        }

        foreach ($activitystats as &$stat) {
            $stat['rate'] = $stat['total'] > 0
                ? round(($stat['completed'] / $stat['total']) * 100)
                : 0;
        }
        unset($stat);

        $sectionchart = [];
        foreach ($sectionstats as $s) {
            $rate = $s['total'] > 0 ? round(($s['completed'] / $s['total']) * 100) : 0;
            $sectionchart[] = [
                'label' => \core_text::strlen($s['label']) > 40
                    ? \core_text::substr($s['label'], 0, 37) . '...'
                    : $s['label'],
                'rate' => $rate,
            ];
        }

        $activitychart = array_values($activitystats);
        usort($activitychart, fn($a, $b) => $b['rate'] <=> $a['rate']);
        $activitychart = array_slice($activitychart, 0, 12);

        $headers = $this->build_headers($activities);

        return [
            'course' => $this->course,
            'activities' => $activities,
            'headers' => $headers,
            'rows' => $rows,
            'summary' => $summary,
            'sectionchart' => $sectionchart,
            'activitychart' => $activitychart,
            'sectionfilter' => $this->sectionfilter,
            'sectionoptions' => activity_order::get_section_options($this->course),
            'showcoursecompletion' => (bool) $this->course->enablecompletion,
        ];
    }

    /**
     * @param array $progress
     * @param \cm_info $cm
     * @return array
     */
    protected function resolve_completion_state(array $progress, \cm_info $cm): array {
        $data = $progress[$cm->id] ?? null;
        $tracking = $cm->completion == COMPLETION_TRACKING_AUTOMATIC ? 'auto' : 'manual';
        $complete = false;
        $label = get_string('completion-n', 'completion');
        $date = '';
        $css = 'lcr-cell lcr-cell--incomplete';
        $iconsuffix = 'n';

        if ($data) {
            if ($data->completionstate == COMPLETION_COMPLETE_PASS) {
                $complete = true;
                $label = get_string('completion-pass', 'completion');
                $css = 'lcr-cell lcr-cell--pass';
                $iconsuffix = 'y';
            } else if ($data->completionstate == COMPLETION_COMPLETE_FAIL) {
                $label = get_string('completion-fail', 'completion');
                $css = 'lcr-cell lcr-cell--fail';
                $iconsuffix = 'n';
            } else if ($data->completionstate == COMPLETION_COMPLETE) {
                $complete = true;
                $label = get_string('completion-y', 'completion');
                $css = 'lcr-cell lcr-cell--complete';
                $iconsuffix = 'y';
            }

            if (!empty($data->overrideby)) {
                $css .= ' lcr-cell--override';
            }

            if (!empty($data->timemodified)) {
                $date = userdate($data->timemodified, get_string('strftimedatefullshort', 'langconfig'));
            }
        }

        return [
            'cmid' => $cm->id,
            'complete' => $complete,
            'label' => $label,
            'date' => $date,
            'css' => $css,
            'tracking' => $tracking,
            'iconsuffix' => $iconsuffix,
            'activityname' => format_string($cm->name, true, ['context' => $cm->context]),
            'modname' => $cm->modname,
            'sectionkey' => '',
            'completionexpected' => $cm->completionexpected
                ? userdate($cm->completionexpected, get_string('strftimedatefullshort', 'langconfig'))
                : '',
        ];
    }

    /**
     * @param \stdClass[] $activities
     * @return array
     */
    protected function build_headers(array $activities): array {
        $headers = [
            ['label' => get_string('col_number', 'local_completionreport'), 'sticky' => true],
            ['label' => get_string('col_fullname', 'local_completionreport'), 'sticky' => true],
            ['label' => get_string('col_email', 'local_completionreport'), 'sticky' => true],
            ['label' => get_string('col_idnumber', 'local_completionreport')],
            ['label' => get_string('col_progress', 'local_completionreport')],
            ['label' => get_string('col_completedcount', 'local_completionreport')],
            ['label' => get_string('col_coursecomplete', 'local_completionreport')],
            ['label' => get_string('col_lastaccess', 'local_completionreport')],
        ];

        foreach ($activities as $act) {
            $headers[] = [
                'label' => $act->activityname,
                'rotated' => true,
                'sectionkey' => $act->sectionkey,
                'modname' => $act->modname,
                'orderindex' => $act->orderindex,
            ];
        }

        return $headers;
    }
}
