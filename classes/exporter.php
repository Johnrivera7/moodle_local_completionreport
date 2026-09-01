<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Export helpers (CSV / Excel-compatible).
 *
 * @package    local_completionreport
 * @copyright  2026 John Rivera
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_completionreport;

defined('MOODLE_INTERNAL') || die();

/**
 * Exports report data.
 */
class exporter {

    /**
     * Stream CSV download (Excel-compatible with UTF-8 BOM).
     *
     * @param array $data
     * @param string $filename
     */
    public static function csv(array $data, string $filename): void {
        $filename = clean_filename($filename);

        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Content-Type: text/csv; charset=UTF-8');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');

        $meta = [
            get_string('export_course', 'local_completionreport'),
            format_string($data['course']->fullname),
        ];
        fputcsv($out, $meta);
        fputcsv($out, [
            get_string('export_date', 'local_completionreport'),
            userdate(time(), get_string('strftimedatetime', 'langconfig')),
        ]);
        fputcsv($out, []);

        $row1 = [get_string('col_sectionpath', 'local_completionreport')];
        $row2 = [get_string('col_activitytype', 'local_completionreport')];
        $row3 = [get_string('col_order', 'local_completionreport')];

        foreach ($data['headers'] as $h) {
            if (empty($h['rotated'])) {
                $row1[] = '';
                $row2[] = '';
                $row3[] = '';
                continue;
            }
            $row1[] = $h['sectionkey'] ?? '';
            $row2[] = $h['modname'] ?? '';
            $row3[] = isset($h['orderindex']) ? ((int) $h['orderindex'] + 1) : '';
        }
        fputcsv($out, $row1);
        fputcsv($out, $row2);
        fputcsv($out, $row3);

        $headers = array_map(static fn($h) => $h['label'], $data['headers']);
        fputcsv($out, $headers);

        foreach ($data['rows'] as $row) {
            fputcsv($out, self::flat_row($row));
        }

        fclose($out);
        exit;
    }

    /**
     * @param array $row
     * @return array
     */
    protected static function flat_row(array $row): array {
        $line = [
            $row['n'],
            $row['fullname'],
            $row['email'],
            $row['idnumber'],
            $row['progress'] . '%',
            $row['completedcount'] . '/' . $row['activitytotal'],
            $row['coursecomplete'],
            $row['lastaccess'],
        ];

        foreach ($row['activities'] as $act) {
            $cell = $act['label'];
            if ($act['date'] !== '') {
                $cell .= ' (' . $act['date'] . ')';
            }
            $line[] = $cell;
        }

        return $line;
    }
}
