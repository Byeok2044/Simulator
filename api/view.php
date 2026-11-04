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
foreach ($pagePairs as $i => $pair) $data['rows'][] = ['index' => $i, 'number' => $i + 1, 'x' => $pair[0], 'y' => $pair[1], 'remove_disabled' => count($pairs) <= 2 ? 'disabled' : ''];
if ($result !== null) {
    $stats = [];
    $r = $result->getR();
    $correlation = $r === null ? 'Undefined' : fmt($r) . ' (' . ($r > 0 ? 'positive' : ($r < 0 ? 'negative' : 'zero')) . ' correlation)';
    foreach (['Correlation coefficient (r)' => $correlation, 'R-squared' => fmt($result->getR2()), 'Points' => fmt($result->getN()), 'RMSE' => fmt(sqrt($result->getSse() / $result->getN()))] as $name => $value) $stats[] = ['label' => $name, 'value' => $value];
        // Computation table (display only)
    $meanX = $result->getMeanX(); $meanY = $result->getMeanY();
    $calcLimit = 200; $calcRows = [];
    $sumX = 0.0; $sumY = 0.0; $sumXY = 0.0; $sumXX = 0.0;
    $clean = fn($d, $a, $b) => abs($d) <= 1e-12 * max(abs($a), abs($b)) ? 0.0 : $d;
    foreach ($pairs as $i => $pair) {
        $x = (float)$pair[0]; $y = (float)$pair[1];
        $dx = $clean($x - $meanX, $x, $meanX); $dy = $clean($y - $meanY, $y, $meanY);
        $sumX += $x; $sumY += $y; $sumXY += $dx * $dy; $sumXX += $dx * $dx;
        if ($i < $calcLimit) $calcRows[] = [
            'c_x' => fmt($x), 'c_y' => fmt($y), 'c_dx' => fmt($dx), 'c_dy' => fmt($dy),
            'c_prod' => fmt($dx * $dy), 'c_sq' => fmt($dx * $dx), 'c_pred' => fmt($result->predict($x)),
        ];
    }
    $data += ['slope' => fmt($result->getSlope()), 'sign' => $result->getIntercept() >= 0 ? '+' : '−', 'intercept' => fmt(abs($result->getIntercept())),
        'predict_x' => $predictX, 'prediction' => $prediction, 'prediction_class' => $predictionError ? 'is-error' : '', 'plot_status' => $plotStatus,
        'stats' => $stats, 'constant_y' => $result->getR() === null,
        'calc_rows' => $calcRows, 'calc_truncated' => count($pairs) > $calcLimit, 'calc_shown' => min(count($pairs), $calcLimit),
        'sum_x' => fmt($sumX), 'sum_y' => fmt($sumY), 'sum_xy' => fmt($sumXY), 'sum_xx' => fmt($sumXX),
        'mean_x' => fmt($meanX), 'mean_y' => fmt($meanY), 'calc_a' => fmt($result->getIntercept()),
        'chart' => $chart];
} else {
    $state = [];
    $state[] = ['name' => 'predict_x', 'value' => $predictX];
    $data['hidden_state'] = $state;
}
echo renderPage(__DIR__ . '/pages/regression.html', $data);
