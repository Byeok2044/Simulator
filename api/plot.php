<?php
function renderPlot(SimpleLinearRegression $model, array $pairs, array $options): array {
    $residual = $options['mode'] === 'residual';
    $values = array_map(fn($p) => [(float)$p[0], $residual ? (float)$p[1] - $model->predict((float)$p[0]) : (float)$p[1]], $pairs);
    $xs = array_column($values, 0); $ys = array_column($values, 1);
    $minX = min($xs); $maxX = max($xs); $minY = min($ys); $maxY = max($ys);
    if ($residual) { $minY = min($minY, 0); $maxY = max($maxY, 0); }
    $centerX = ($minX + $maxX) / 2; $centerY = ($minY + $maxY) / 2;
    $spanX = max($maxX - $minX, abs($centerX) * 1e-14, 1e-150) * 1.24 / $options['zoom'];
    $spanY = max($maxY - $minY ?: max(abs($centerY) * .2, 1), abs($centerY) * 1e-14) * 1.24 / $options['zoom'];
    $minX = $centerX - $spanX / 2; $maxX = $centerX + $spanX / 2;
    $minY = $centerY - $spanY / 2; $maxY = $centerY + $spanY / 2;
    $L = 110; $R = 35; $T = 28; $B = 75; $W = 900; $H = 500;
    $pw = $W - $L - $R; $ph = $H - $T - $B;
    $px = fn($x) => $L + ($x - $minX) / ($maxX - $minX) * $pw;
    $py = fn($y) => $T + $ph - ($y - $minY) / ($maxY - $minY) * $ph;
    $c = fn($v) => number_format($v, 3, '.', '');
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    $title = $residual ? 'Residual plot' : 'Scatter plot with linear regression';
    $yLabel = $residual ? 'Residual' : $options['axisY'];
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 500" role="img" aria-labelledby="plot-title plot-description">';
    $svg .= '<title id="plot-title">' . $title . '</title><desc id="plot-description">' . count($pairs) . ' observations. X: ' . $esc($options['axisX']) . '. Y: ' . $esc($yLabel) . '.</desc>';
    $svg .= '<style>' . file_get_contents(__DIR__ . '/assets/plot.css') . '</style>';
    $svg .= '<rect width="900" height="500" fill="#0a101c"/><defs><clipPath id="plot-clip"><rect x="110" y="28" width="755" height="397"/></clipPath></defs>';
    for ($i = 0; $i <= 5; $i++) {
        $x = $minX + ($maxX - $minX) * $i / 5; $y = $minY + ($maxY - $minY) * $i / 5;
        if ($options['grid']) $svg .= '<path class="grid" d="M' . $c($px($x)) . ' 28v397M110 ' . $c($py($y)) . 'h755"/>';
        $svg .= '<text class="tick" x="' . $c($px($x)) . '" y="449" text-anchor="middle">' . fmt($x) . '</text>';
        $svg .= '<text class="tick" x="98" y="' . $c($py($y)) . '" text-anchor="end" dominant-baseline="middle">' . fmt($y) . '</text>';
    }
    $svg .= '<path class="axis" fill="none" d="M110 28v397h755"/><text class="axis-label" x="487.5" y="484" text-anchor="middle">' . $esc($options['axisX']) . '</text><text class="axis-label" transform="translate(20 226.5) rotate(-90)" text-anchor="middle">' . $esc($yLabel) . '</text><g clip-path="url(#plot-clip)">';
    if ($residual) $svg .= '<path class="zero" d="M110 ' . $c($py(0)) . 'h755"/>';
    elseif ($options['line']) $svg .= '<path class="reg-line" d="M110 ' . $c($py($model->predict($minX))) . 'L865 ' . $c($py($model->predict($maxX))) . '"/>';
    $visible = 0;
    foreach ($values as $i => [$x, $y]) {
        $predicted = $model->predict($x); $observed = (float)$pairs[$i][1];
        if ($x >= $minX && $x <= $maxX && $y >= $minY && $y <= $maxY) $visible++;
        if (!$residual && $options['residuals']) $svg .= '<path class="residual" d="M' . $c($px($x)) . ' ' . $c($py($y)) . 'V' . $c($py($predicted)) . '"/>';
        $svg .= '<circle class="point" cx="' . $c($px($x)) . '" cy="' . $c($py($y)) . '" r="4.5" tabindex="0"><title>Point ' . ($i + 1) . ': X=' . fmt($x) . ', Y=' . fmt($observed) . ', predicted=' . fmt($predicted) . ', residual=' . fmt($observed - $predicted) . '</title></circle>';
    }
    return ['svg' => $svg . '</g></svg>', 'visible' => $visible];
}
