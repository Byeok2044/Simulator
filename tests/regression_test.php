<?php
require_once __DIR__ . '/../api/regression.php';
require_once __DIR__ . '/../api/plot.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    [[], [], 'Enter X and Y values for at least 2 rows.'],
    [['', ''], ['', ''], 'Missing X and Y values in rows 1, 2.'],
    [[' ', "\t"], [1, 2], 'Missing X values in rows 1, 2.'],
    [[1, 2], [3, ''], 'Missing Y value in row 2.'],
    [[''], [''], 'Missing X and Y values in row 1.'],
    [[null, 1], [2, ''], 'Missing X value in row 1. Missing Y value in row 2.'],
] as [$x, $y, $expectedError]) {
    $model = new SimpleLinearRegression();
    check(!$model->train($x, $y), 'Missing input must be rejected.');
    check($model->getError() === $expectedError, 'Missing input must identify the affected fields.');
}

foreach (['text', '1e101', INF, NAN] as $invalid) {
    $model = new SimpleLinearRegression();
    check(!$model->train([0, 1], [0, $invalid]), 'Invalid numbers must still be rejected.');
    check($model->getError() === 'Use finite numbers with absolute values no larger than 1e100.', 'Retain the numeric limit message for invalid numbers.');
}
$model = new SimpleLinearRegression();
check($model->train(['0', '1'], ['0', '1']), 'Zero is a number, not missing input.');
check(!$model->train([1], [2]), 'A single valid point is insufficient.');
check($model->getError() === 'At least 2 data points are required.', 'Keep the minimum data requirement.');

foreach ([
    [[1, 2], [2, 4], 1],
    [[1, 2, 3], [6, 4, 2], -1],
] as [$x, $y, $expectedR]) {
    check($model->train($x, $y), 'Perfect fit data must train successfully.');
    check($model->getR() === $expectedR && $model->getR2() === 1, 'Perfect fits must report exact correlation and R-squared.');
}
check($model->train([1, 2, 3], [2, 4.001, 6]), 'Nearly linear data must train successfully.');
check($model->getR() < 1 && $model->getR2() < 1, 'A nearly perfect fit must retain its computed correlation and R-squared.');
check($model->train([1, 2], [4, 4]), 'Constant Y data must train successfully.');
check($model->getR() === null && $model->getR2() === null, 'Constant Y must have undefined correlation and R-squared.');

$options = ['mode' => 'fit', 'zoom' => 1, 'line' => true, 'grid' => true,
    'residuals' => false, 'axisX' => 'X', 'axisY' => 'Y'];
foreach ([
    [[100, 101, 102], [10, 11, 12]], // Start below the observed Y range.
    [[100, 101, 102], [12, 11, 10]], // Start above the observed Y range.
    [[-102, -101, -100], [10, 11, 12]],
    [[-3, 1, 4], [8, 2, -5]],
    [[0, 1, 2], [0, 0, 0]],
    [[1, 2, 3], [5, 5, 5]],
    [[1e99, 2e99, 3e99], [-1e99, 0, 1e99]],
] as [$x, $y]) {
    $model = new SimpleLinearRegression();
    check($model->train($x, $y), 'Plot test data must train successfully.');
    $pairs = array_map(fn($x, $y) => [$x, $y], $x, $y);
    foreach ([1, 2] as $zoom) {
        $plot = renderPlot($model, $pairs, array_merge($options, ['zoom' => $zoom]));
        check(preg_match('/class="reg-line" d="M110 ([^L]+)L865 ([^"]+)"/', $plot['svg'], $line) === 1, 'The fitted line must be drawn.');
        foreach ([(float)$line[1], (float)$line[2]] as $pixelY) {
            check(is_finite($pixelY) && $pixelY >= 28 && $pixelY <= 425, 'Both ends of the line must stay inside the plot.');
        }
        if ($zoom === 1) check($plot['visible'] === count($pairs), 'All data points remain visible at the default scale.');
    }
    $residual = renderPlot($model, $pairs, array_merge($options, ['mode' => 'residual']));
    check(!str_contains($residual['svg'], 'class="reg-line"'), 'Residual plots must not draw the fitted line.');
    $scatter = renderPlot($model, $pairs, array_merge($options, ['line' => false]));
    check(!str_contains($scatter['svg'], 'class="reg-line"'), 'The line can still be hidden.');
}
echo "Input validation and graph visibility checks passed.\n";
