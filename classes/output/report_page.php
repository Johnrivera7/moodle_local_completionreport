<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Renderable report page.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_completionreport\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;

/**
 * Main report page exportable.
 */
class report_page implements renderable, templatable {

    /** @var array */
    protected $data;

    /** @var \moodle_url */
    protected $baseurl;

    /** @var bool */
    protected $exportmode;

    /**
     * @param array $data
     * @param \moodle_url $baseurl
     * @param bool $exportmode
     */
    public function __construct(array $data, \moodle_url $baseurl, bool $exportmode = false) {
        $this->data = $data;
        $this->baseurl = $baseurl;
        $this->exportmode = $exportmode;
    }

    /**
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $d = $this->data;
        $course = $d['course'];

        $csvurl = new \moodle_url($this->baseurl, ['format' => 'csv']);
        $exporturl = new \moodle_url($this->baseurl, ['format' => 'export']);

        $sectionoptions = [];
        $sectionoptions[] = [
            'value' => -1,
            'label' => get_string('allsections', 'local_completionreport'),
            'selected' => $d['sectionfilter'] < 0,
        ];
        foreach ($d['sectionoptions'] as $num => $label) {
            $sectionoptions[] = [
                'value' => $num,
                'label' => $label,
                'selected' => (int) $d['sectionfilter'] === (int) $num,
            ];
        }

        $headercells = [];
        $sectiongroups = [];
        $currentsection = null;
        $colgroup = [];

        foreach ($d['headers'] as $h) {
            $headercells[] = [
                'label' => $h['label'],
                'rotated' => !empty($h['rotated']),
                'sticky' => !empty($h['sticky']),
                'title' => !empty($h['sectionkey']) ? $h['sectionkey'] . ' — ' . ($h['modname'] ?? '') : $h['label'],
            ];

            if (!empty($h['rotated'])) {
                $skey = $h['sectionkey'] ?? '';
                if ($skey !== $currentsection) {
                    if ($currentsection !== null) {
                        $sectiongroups[count($sectiongroups) - 1]['colspan'] = $colgroup[count($colgroup) - 1];
                    }
                    $sectiongroups[] = [
                        'label' => $skey,
                        'colspan' => 1,
                    ];
                    $colgroup[] = 1;
                    $currentsection = $skey;
                } else {
                    $colgroup[count($colgroup) - 1]++;
                }
            }
        }
        if ($currentsection !== null && $sectiongroups !== []) {
            $sectiongroups[count($sectiongroups) - 1]['colspan'] = $colgroup[count($colgroup) - 1];
        }

        $tablerows = [];
        foreach ($d['rows'] as $row) {
            $cells = [];
            $cells[] = ['text' => (string) $row['n'], 'class' => 'lcr-num'];
            $cells[] = [
                'text' => $this->exportmode
                    ? s($row['fullname'])
                    : \html_writer::link($row['profileurl'], s($row['fullname'])),
                'class' => 'lcr-name',
                'raw' => !$this->exportmode,
            ];
            $cells[] = ['text' => s($row['email'])];
            $cells[] = ['text' => s($row['idnumber'])];
            $cells[] = [
                'text' => $row['progress'] . '%',
                'class' => 'lcr-progress lcr-progress--' . $row['progressclass'],
            ];
            $cells[] = ['text' => $row['completedcount'] . '/' . $row['activitytotal']];
            $cells[] = ['text' => s($row['coursecomplete'])];
            $cells[] = ['text' => s($row['lastaccess'])];

            foreach ($row['activities'] as $act) {
                $label = s($act['label']);
                if ($act['date'] !== '') {
                    $label .= '<br><small class="lcr-date">' . s($act['date']) . '</small>';
                }
                if (!$this->exportmode) {
                    $icon = $output->pix_icon(
                        'i/completion-' . $act['tracking'] . '-' . $act['iconsuffix'],
                        $act['label']
                    );
                    $label = $icon . ' <span class="lcr-complabel">' . $label . '</span>';
                }
                $cells[] = ['text' => $label, 'class' => $act['css'], 'raw' => true];
            }

            $tablerows[] = [
                'cells' => $cells,
                'searchtext' => s($row['searchtext']),
            ];
        }

        $s = $d['summary'];
        $cards = [
            ['title' => get_string('card_users', 'local_completionreport'), 'value' => $s['totalusers'], 'mod' => 'info'],
            ['title' => get_string('card_activities', 'local_completionreport'), 'value' => $s['activitycount'], 'mod' => 'info'],
            ['title' => get_string('card_notstarted', 'local_completionreport'), 'value' => $s['notstarted'], 'mod' => 'low'],
            ['title' => get_string('card_inprogress', 'local_completionreport'), 'value' => $s['inprogress'], 'mod' => 'mid'],
            ['title' => get_string('card_completed', 'local_completionreport'), 'value' => $s['completed'], 'mod' => 'high'],
        ];

        return [
            'exportmode' => $this->exportmode,
            'coursename' => format_string($course->fullname),
            'strtitle' => get_string('exporttitle', 'local_completionreport'),
            'exportdate' => userdate(time(), get_string('strftimedatetime', 'langconfig')),
            'csvurl' => $csvurl->out(false),
            'exporturl' => $exporturl->out(false),
            'backurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'baseurl' => (new \moodle_url('/local/completionreport/index.php'))->out(false),
            'courseid' => $course->id,
            'groupid' => (int) ($this->baseurl->get_param('group') ?? 0),
            'strdownload' => get_string('downloadexcel', 'local_completionreport'),
            'strexport' => get_string('exportprint', 'local_completionreport'),
            'strback' => get_string('backtocourse', 'local_completionreport'),
            'strsearch' => get_string('search', 'local_completionreport'),
            'strsummary' => get_string('summaryheading', 'local_completionreport'),
            'strtable' => get_string('tableheading', 'local_completionreport'),
            'strempty' => get_string('norecords', 'local_completionreport'),
            'orderhelp' => get_string('orderhelp', 'local_completionreport'),
            'sectionfilterlabel' => get_string('sectionfilter', 'local_completionreport'),
            'sectionoptions' => $sectionoptions,
            'cards' => $cards,
            'headers' => $headercells,
            'sectiongroups' => $sectiongroups,
            'hassectiongroups' => count($sectiongroups) > 1,
            'rows' => $tablerows,
            'rowcount' => count($tablerows),
            'stickyoffset' => count(array_filter($headercells, fn($h) => empty($h['rotated']))),
        ];
    }
}
