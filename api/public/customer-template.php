<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    api_send_error(405, 'Method not allowed');
}

$segment = isset($_GET['segment']) && is_string($_GET['segment']) ? trim($_GET['segment']) : 'potential';
if ($segment === '') {
    $segment = 'potential';
}

$format = strtolower(is_string($_GET['format'] ?? null) ? (string) $_GET['format'] : 'excel');

$state = portal_load_state();
$registry = $state['customer_registry']['segments'] ?? [];

if (!isset($registry[$segment])) {
    $defaults = portal_default_state()['customer_registry']['segments'];
    $segmentData = $defaults[$segment] ?? reset($defaults);
    $segment = array_key_first($defaults) ?? 'potential';
} else {
    $segmentData = $registry[$segment];
}

if (!is_array($segmentData)) {
    api_send_error(404, 'Customer segment not found.');
}

$columns = $segmentData['columns'] ?? [];

if (!is_array($columns) || $columns === []) {
    $columns = portal_default_state()['customer_registry']['segments']['potential']['columns'];
}

$headers = [];
foreach ($columns as $column) {
    if (!is_array($column)) {
        continue;
    }
    $headers[] = $column['label'] ?? ($column['key'] ?? 'Column');
}

$headers[] = 'Notes';
$headers[] = 'Reminder on (YYYY-MM-DD)';

$fileSegment = preg_replace('/[^a-z0-9\-]+/i', '-', $segment) ?? 'customers';
$fileSegment = trim($fileSegment, '-') ?: 'customers';

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dakshayani-' . $fileSegment . '-template.csv"');

    $handle = fopen('php://output', 'wb');
    if ($handle === false) {
        api_send_error(500, 'Unable to initialise download stream.');
    }

    fputcsv($handle, $headers);
    fclose($handle);
    exit;
}

$worksheetName = ucwords(str_replace('-', ' ', $segment));

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="dakshayani-' . $fileSegment . '-template.xls"');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
echo '<Worksheet ss:Name="' . htmlspecialchars($worksheetName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<Table>'; 

echo '<Row>';
foreach ($headers as $headerLabel) {
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars($headerLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</Data></Cell>';
}
echo '</Row>';

echo '<Row>';
foreach ($headers as $headerLabel) {
    echo '<Cell><Data ss:Type="String">' . htmlspecialchars('Enter ' . strtolower($headerLabel), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</Data></Cell>';
}
echo '</Row>';

echo '</Table>';
echo '</Worksheet>';
echo '</Workbook>';
exit;
