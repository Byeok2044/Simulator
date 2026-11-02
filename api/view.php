<?php
require_once __DIR__ . '/render.php';

function optionValues(array $choices, $selected): array {
    $items = [];
    foreach ($choices as $value => $text) $items[] = ['value' => $value, 'text' => $text, 'selected' => (string)$selected === (string)$value ? 'selected' : ''];
    return $items;
}

// Prepare values for the single page. All markup lives in pages/regression.html.
$data = [
    'points' => json_encode($pairs, JSON_INVALID_UTF8_SUBSTITUTE), 'page' => $page, 'zoom' => $zoom, 'csv_source' => base64_encode($csvText),
    'csv_header_checked' => $hasHeader || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' ? 'checked' : '',
    'axis_x' => $axisX, 'axis_y' => $axisY, 'row_count' => count($pairs), 'page_number' => $page + 1, 'page_count' => (int)ceil(count($pairs) / $pageSize),
    'previous_disabled' => $page === 0 ? 'disabled' : '', 'next_disabled' => ($page + 1) * $pageSize >= count($pairs) ? 'disabled' : '', 'add_disabled' => count($pairs) >= 5000 ? 'disabled' : '',
    'errors' => array_map(fn($error) => ['message' => $error], $errors), 'notice' => $notice,
    'delimiter_options' => optionValues([',' => 'Comma', ';' => 'Semicolon', 'tab' => 'Tab'], $delimiter),
    'has_csv' => (bool)$csvRows, 'has_results' => $result !== null, 'no_results' => $result === null,
    'rows' => [],
];
foreach ($pagePairs as $i => $pair) $data['rows'][] = ['index' => $i, 'number' => $i + 1, 'x' => $pair[0], 'y' => $pair[1], 'remove_disabled' => count($pairs) <= 1 ? 'disabled' : ''];
if ($csvRows) {
    $width = max(array_map('count', $csvRows));
    $choices = []; $headings = [];
    for ($i = 0; $i < $width; $i++) {
        $choices[$i] = ($i + 1) . ': ' . ($hasHeader ? ($csvRows[0][$i] ?? 'Unnamed') : 'Column ' . ($i + 1));
        $headings[] = ['text' => $choices[$i]];
    }
    $previewRows = [];
    foreach (array_slice($csvRows, (int)$hasHeader, 3) as $csvRow) {
        $cells = [];
        for ($i = 0; $i < $width; $i++) $cells[] = ['text' => $csvRow[$i] ?? 'Missing'];
        $previewRows[] = ['cells' => $cells];
    }
    $data += ['skip_checked' => $skipInvalid ? 'checked' : '', 'record_count' => count($csvRows) - (int)$hasHeader,
        'csv_x_options' => optionValues($choices, $xColumn), 'csv_y_options' => optionValues($choices, $yColumn),
        'csv_headings' => $headings, 'csv_preview_rows' => $previewRows];
}
$plotChecks = ['show_line' => ['line', 'Fitted line'], 'show_residuals' => ['residuals', 'Residuals'], 'show_grid' => ['grid', 'Grid']];
if ($result !== null) {
    $stats = [];
    foreach (['Slope' => $result->getSlope(), 'Intercept' => $result->getIntercept(), 'Correlation (r)' => $result->getR(), 'R-squared' => $result->getR2(), 'Data points' => $result->getN(), 'SSE' => $result->getSse(), 'RMSE' => sqrt($result->getSse() / $result->getN())] as $name => $value) $stats[] = ['label' => $name, 'value' => fmt($value)];
    $checks = [];
    foreach ($plotChecks as $name => [$key, $text]) $checks[] = ['name' => $name, 'text' => $text, 'checked' => $options[$key] ? 'checked' : ''];
    $data += ['slope' => fmt($result->getSlope()), 'sign' => $result->getIntercept() >= 0 ? '+' : '−', 'intercept' => fmt(abs($result->getIntercept())),
        'predict_x' => $predictX, 'prediction' => $prediction, 'plot_status' => $plotStatus,
        'zoom_in_disabled' => $zoom >= 8 ? 'disabled' : '', 'zoom_out_disabled' => $zoom <= .5 ? 'disabled' : '',
        'stats' => $stats, 'constant_y' => $result->getR() === null,
        'mode_options' => optionValues(['fit' => 'Regression', 'residual' => 'Residual plot'], $options['mode']), 'checkboxes' => $checks, 'chart' => $chart];
} else {
    $state = [];
    foreach (['plot_options' => '1', 'mode' => $options['mode'], 'predict_x' => $predictX] as $name => $value) $state[] = ['name' => $name, 'value' => $value];
    foreach ($plotChecks as $name => [$key]) if ($options[$key]) $state[] = ['name' => $name, 'value' => '1'];
    $data['hidden_state'] = $state;
}
echo renderPage(__DIR__ . '/pages/regression.html', $data);
