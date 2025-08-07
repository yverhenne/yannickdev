<?php
/* For licensing terms, see /license.txt */

require_once __DIR__.'/../inc/global.inc.php';
api_block_anonymous_users(true);
if (api_is_student()) {
    api_not_allowed(true);
}

$studentId = isset($_GET['student']) ? (int) $_GET['student'] : 0;
if (empty($studentId)) {
    api_not_allowed(true);
}

$start = isset($_GET['start']) ? $_GET['start'] : '';
$end = isset($_GET['end']) ? $_GET['end'] : '';

if (empty($start) || empty($end)) {
    $aLastWeek = get_last_week();
    $start = date('Y-m-d', $aLastWeek[0]);
    $end = date('Y-m-d', $aLastWeek[6]);
}

$report = Tracking::generateReport('time_report', [$studentId], $start, $end);
$rows = [];
if (!empty($report)) {
    $rows = $report['rows'];
    array_unshift($rows, $report['headers']);
}

$export = isset($_GET['export']) ? $_GET['export'] : '';
if ($export === 'xls') {
    Export::arrayToXls($rows, 'time_report');
    exit;
} elseif ($export === 'pdf') {
    $params = ['filename' => 'time_report'];
    Export::export_table_pdf($rows, $params);
    exit;
}

$html = Export::convert_array_to_html($rows);

$nameTools = get_lang('TimeReport');
Display::display_header($nameTools);
$baseUrl = api_get_self().'?student='.$studentId.'&start='.$start.'&end='.$end;
echo '<div>'
    .'<a href="'.$baseUrl.'&export=pdf">'
    .Display::return_icon('pdf.png', get_lang('ExportPDF'), [], ICON_SIZE_MEDIUM)
    .'</a> '
    .'<a href="'.$baseUrl.'&export=xls">'
    .Display::return_icon('export_excel.png', get_lang('ExportAsXLS'), [], ICON_SIZE_MEDIUM)
    .'</a></div>';
echo $html;
Display::display_footer();
