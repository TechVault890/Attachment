<?php
require_once __DIR__ . '/../models/report.php';

function report_index(): void {
    require_role('admin');
    $range = rpt_range();
    $slots = rpt_slots();
    $tot = ['vacancies' => 0, 'total' => 0, 'filled' => 0, 'open_slots' => 0];
    foreach ($slots as $s) {
        foreach (array_keys($tot) as $k) $tot[$k] += $s[$k];
    }
    view('report/index', [
        'title' => 'Reports', 'range' => $range,
        'byCounty' => rpt_by_county($range), 'byMinistry' => rpt_by_ministry($range),
        'byInstitution' => rpt_by_institution($range), 'slots' => $slots, 'tot' => $tot,
        'pipe' => rpt_pipeline(),
    ]);
}

function report_export(): void {
    require_role('admin');
    $type = (string)($_GET['type'] ?? '');
    $range = rpt_range();
    if ($range['errors']) {
        http_response_code(400);
        exit('Invalid date range.');
    }
    switch ($type) {
        case 'county':
            $head = ['County', 'Placements'];
            $rows = array_map(fn($r) => [$r['county'], $r['placements']], rpt_by_county($range));
            break;
        case 'ministry':
            $head = ['Ministry', 'Placements'];
            $rows = array_map(fn($r) => [$r['ministry'], $r['placements']], rpt_by_ministry($range));
            break;
        case 'institution':
            $head = ['Institution', 'Students', 'Verified', 'Placements'];
            $rows = array_map(fn($r) => [$r['institution'], $r['students'], $r['verified'], $r['placements']], rpt_by_institution($range));
            break;
        case 'slots':
            $head = ['Ministry', 'Vacancies', 'Total slots', 'Filled', 'Open slots', 'Fill rate (%)'];
            $rows = array_map(fn($r) => [$r['ministry'], $r['vacancies'], $r['total'], $r['filled'], $r['open_slots'], $r['rate']], rpt_slots());
            break;
        case 'placements':
            $head = ['Student', 'Reg no', 'Institution', 'Course', 'Ministry', 'Vacancy', 'Office', 'County',
                     'Start date', 'End date', 'Supervisor', 'Supervisor phone', 'Confirmed at'];
            $rows = array_map(fn($r) => [$r['full_name'], $r['reg_no'], $r['institution'], $r['course'], $r['ministry'],
                $r['title'], $r['office_location'], $r['county'], $r['start_date'], $r['end_date'],
                $r['supervisor_name'], $r['supervisor_phone'], $r['confirmed_at']], rpt_placement_rows($range));
            break;
        default:
            not_found();
    }
    audit('export_report', 'report', null, "type=$type from={$range['from']} to={$range['to']}");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $type . '-' . date('Ymd') . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel on Windows reads it correctly
    fputcsv($out, $head, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe', $row), ',', '"', '');
    }
    fclose($out);
    exit;
}
