<?php
require_once __DIR__ . '/render.php';

function optionValues(array $choices, $selected): array {
    $items = [];
    foreach ($choices as $value => $text) $items[] = ['value' => $value, 'text' => $text, 'selected' => (string)$selected === (string)$value ? 'selected' : ''];
    return $items;
}

// Prepare values for the single page. All markup lives in pages/regression.html.
$data = [
    'points' => json_encode($pairs, JSON_INVALID_UTF8_SUBSTITUTE), 'page' => $page, 'zoom' => $zoom,
    'axis_x' => $axisX, 'axis_y' => $axisY, 'row_count' => count($pairs), 'page_number' => $page + 1, 'page_count' => (int)ceil(count($pairs) / $pageSize),
    'previous_disabled' => $page === 0 ? 'disabled' : '', 'next_disabled' => ($page + 1) * $pageSize >= count($pairs) ? 'disabled' : '', 'add_disabled' => count($pairs) >= 5000 ? 'disabled' : '',
    'errors' => array_map(fn($error) => ['message' => $error], $errors), 'notice' => $notice,
    'has_results' => $result !== null, 'no_results' => $result === null,
    'rows' => [], 'pagination_hidden' => count($pairs) > $pageSize ? '' : 'hidden',
];
foreach ($pagePairs as $i => $pair) $data['rows'][] = ['index' => $i, 'number' => $i + 1, 'x' => $pair[0], 'y' => $pair[1], 'remove_disabled' => count($pairs) <= 1 ? 'disabled' : ''];
if ($result !== null) {
    $stats = [];
    foreach (['R-squared' => $result->getR2(), 'Points' => $result->getN(), 'RMSE' => sqrt($result->getSse() / $result->getN())] as $name => $value) $stats[] = ['label' => $name, 'value' => fmt($value)];
    $data += ['slope' => fmt($result->getSlope()), 'sign' => $result->getIntercept() >= 0 ? '+' : '−', 'intercept' => fmt(abs($result->getIntercept())),
        'predict_x' => $predictX, 'prediction' => $prediction, 'plot_status' => $plotStatus,
        'stats' => $stats, 'constant_y' => $result->getR() === null,
        'mode_options' => optionValues(['fit' => 'Regression', 'residual' => 'Residual plot'], $options['mode']), 'chart' => $chart];
} else {
    $state = [];
    foreach (['mode' => $options['mode'], 'predict_x' => $predictX] as $name => $value) $state[] = ['name' => $name, 'value' => $value];
    $data['hidden_state'] = $state;
}
echo renderPage(__DIR__ . '/pages/regression.html', $data);
