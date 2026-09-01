<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Enhanced activity completion report with visual course order.
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->libdir . '/completionlib.php');

use local_completionreport\report_builder;
use local_completionreport\exporter;
use local_completionreport\output\report_page;

$id = required_param('id', PARAM_INT);
$format = optional_param('format', '', PARAM_ALPHA);
$sectionfilter = optional_param('section', -1, PARAM_INT);

$course = get_course($id);
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/completionreport:view', $context);

$group = groups_get_course_group($course, true);
if ($group === 0 && $course->groupmode == SEPARATEGROUPS) {
    require_capability('moodle/site:accessallgroups', $context);
}

$completion = new completion_info($course);
if (!$completion->is_enabled() || !$completion->has_activities()) {
    throw new moodle_exception('err_noactivities', 'completion');
}

$url = new moodle_url('/local/completionreport/index.php', ['id' => $course->id]);
if ($group) {
    $url->param('group', $group);
}
if ($sectionfilter >= 0) {
    $url->param('section', $sectionfilter);
}

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'local_completionreport'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->css('/local/completionreport/styles.css');

$builder = new report_builder($course, $context, (int) $group, $sectionfilter);
$data = $builder->build();

if ($format === 'csv') {
    $short = preg_replace('/[^a-z0-9_-]+/i', '_', $course->shortname);
    exporter::csv($data, 'completionreport_' . $short);
}

$exportmode = ($format === 'export');
if ($exportmode) {
    $PAGE->set_pagelayout('popup');
}

if (!$exportmode) {
    $PAGE->requires->js_call_amd('local_completionreport/report', 'init');
}

echo $OUTPUT->header();

if (!$exportmode) {
    echo html_writer::div(get_string('groupfilterhelp', 'local_completionreport'), 'lcr-help alert alert-secondary');
    groups_print_course_menu($course, $url);

    $s = $data['summary'];
    $CFG->chart_colorset = ['#b81d13', '#efb700', '#008450', '#146eb4'];

    echo html_writer::start_div('lcr-charts-grid');

    $serie = new core_chart_series(get_string('chart_progress', 'local_completionreport'), [
        $s['notstarted'],
        $s['inprogress'],
        $s['completed'],
    ]);
    $chart = new core_chart_pie();
    $chart->set_doughnut(true);
    $chart->set_title(get_string('chart_progress', 'local_completionreport'));
    $chart->add_series($serie);
    $chart->set_labels([
        get_string('card_notstarted', 'local_completionreport'),
        get_string('card_inprogress', 'local_completionreport'),
        get_string('card_completed', 'local_completionreport'),
    ]);
    echo html_writer::div($OUTPUT->render($chart), 'lcr-chart-card');

    if (!empty($data['sectionchart'])) {
        $labels = array_column($data['sectionchart'], 'label');
        $values = array_column($data['sectionchart'], 'rate');
        $serie2 = new core_chart_series(get_string('chart_sections', 'local_completionreport'), $values);
        $chart2 = new core_chart_bar();
        $chart2->set_title(get_string('chart_sections', 'local_completionreport'));
        $chart2->add_series($serie2);
        $chart2->set_labels($labels);
        $chart2->get_xaxis(0, true)->set_label(get_string('chart_axis_section', 'local_completionreport'));
        $chart2->get_yaxis(0, true)->set_label(get_string('chart_axis_percent', 'local_completionreport'));
        echo html_writer::div($OUTPUT->render($chart2), 'lcr-chart-card lcr-chart-card--wide');
    }

    if (!empty($data['activitychart'])) {
        $labels3 = array_map(
            fn($a) => core_text::strlen($a['name']) > 28 ? core_text::substr($a['name'], 0, 25) . '...' : $a['name'],
            $data['activitychart']
        );
        $values3 = array_column($data['activitychart'], 'rate');
        $serie3 = new core_chart_series(get_string('chart_activities', 'local_completionreport'), $values3);
        $chart3 = new core_chart_bar();
        $chart3->set_title(get_string('chart_activities', 'local_completionreport'));
        $chart3->add_series($serie3);
        $chart3->set_labels($labels3);
        $chart3->get_yaxis(0, true)->set_label(get_string('chart_axis_percent', 'local_completionreport'));
        echo html_writer::div($OUTPUT->render($chart3), 'lcr-chart-card lcr-chart-card--wide');
    }

    echo html_writer::end_div();
}

$page = new report_page($data, $url, $exportmode);
echo $OUTPUT->render_from_template('local_completionreport/report_page', $page->export_for_template($OUTPUT));

echo $OUTPUT->footer();
