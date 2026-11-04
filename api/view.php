<?php
require_once __DIR__ . '/render.php';

// Prepare values for the single page. All markup lives in pages/regression.html.
$data = [
    'points' => json_encode($pairs, JSON_INVALID_UTF8_SUBSTITUTE), 'page' => $page, 'zoom' => $zoom,
    'axis_x' => $axisX, 'axis_y' => $axisY, 'row_count' => count($pairs), 'page_number' => $page + 1, 'page_count' => (int)ceil(count($pairs) / $pageSize),
    'csv_name' => $csvName, 'remove_csv_hidden' => $csvName === '' ? 'hidden' : '',
    'previous_disabled' => $page === 0 ? 'disabled' : '', 'next_disabled' => ($page + 1) * $pageSize >= count($pairs) ? 'disabled' : '', 'add_disabled' => count($pairs) >= 5000 ? 'disabled' : '',
    'errors' => array_map(fn($error) => ['message' => $error], $errors), 'notice' => $notice,
    'has_results' => $result !== null, 'no_results' => $result === null,
    'rows' => [], 'pagination_hidden' => count($pairs) > $pageSize ? '' : 'hidden',
];
foreach ($pagePairs as $i => $pair) $data['rows'][] = ['index' => $i, 'number' => $i + 1, 'x' => $pair[0], 'y' => $pair[1], 'remove_disabled' => count($pairs) <= 1 ? 'disabled' : ''];
if ($result !== null) {
    $stats = [];
    $r = $result->getR();
    $correlation = $r === null ? 'Undefined' : fmt($r) . ' (' . ($r > 0 ? 'positive' : ($r < 0 ? 'negative' : 'zero')) . ' correlation)';
    foreach (['Correlation coefficient (r)' => $correlation, 'R-squared' => fmt($result->getR2()), 'Points' => fmt($result->getN()), 'RMSE' => fmt(sqrt($result->getSse() / $result->getN()))] as $name => $value) $stats[] = ['label' => $name, 'value' => $value];
    $data += ['slope' => fmt($result->getSlope()), 'sign' => $result->getIntercept() >= 0 ? '+' : '−', 'intercept' => fmt(abs($result->getIntercept())),
        'predict_x' => $predictX, 'prediction' => $prediction, 'plot_status' => $plotStatus,
        'stats' => $stats, 'constant_y' => $result->getR() === null,
        'chart' => $chart];
} else {
    $state = [];
    $state[] = ['name' => 'predict_x', 'value' => $predictX];
    $data['hidden_state'] = $state;
}
echo renderPage(__DIR__ . '/pages/regression.html', $data);
