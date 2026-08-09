<?php
class SimpleLinearRegression {
    private $slope;
    private $intercept;
    private $r;
    private $r2;
    private $sse;
    private $n;
    private $meanX;
    private $meanY;
    private $xMin;
    private $xMax;
    private $yMin;
    private $yMax;
    private $error = null;

    public function train(array $x, array $y) {
        $n = count($x);
        if ($n !== count($y)) {
            $this->error = 'X and Y must have the same number of values.';
            return false;
        }
        if ($n < 2) {
            $this->error = 'At least 2 data points are required.';
            return false;
        }
        $x = array_map('floatval', $x);
        $y = array_map('floatval', $y);

        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        $sxx = 0; $sxy = 0; $syy = 0;
        for ($i = 0; $i < $n; $i++) {
            $dx = $x[$i] - $meanX;
            $dy = $y[$i] - $meanY;
            $sxx += $dx * $dx;
            $sxy += $dx * $dy;
            $syy += $dy * $dy;
        }
        if ($sxx == 0) {
            $this->error = 'All X values are identical, so the regression line is undefined (vertical).';
            return false;
        }

        $this->slope = $sxy / $sxx;
        $this->intercept = $meanY - $this->slope * $meanX;
        $this->r = $syy == 0 ? 0.0 : $sxy / sqrt($sxx * $syy);
        $this->r2 = $this->r * $this->r;
        $this->sse = 0;
        for ($i = 0; $i < $n; $i++) {
            $d = $y[$i] - $this->predict($x[$i]);
            $this->sse += $d * $d;
        }
        $this->n = $n;
        $this->meanX = $meanX;
        $this->meanY = $meanY;
        $this->xMin = min($x);
        $this->xMax = max($x);
        $this->yMin = min($y);
        $this->yMax = max($y);
        return true;
    }

    public function predict($x) {
        return $this->intercept + $this->slope * $x;
    }

    public function getSlope() { return $this->slope; }
    public function getIntercept() { return $this->intercept; }
    public function getR() { return $this->r; }
    public function getR2() { return $this->r2; }
    public function getSse() { return $this->sse; }
    public function getN() { return $this->n; }
    public function getMeanX() { return $this->meanX; }
    public function getMeanY() { return $this->meanY; }
    public function getXMin() { return $this->xMin; }
    public function getXMax() { return $this->xMax; }
    public function getYMin() { return $this->yMin; }
    public function getYMax() { return $this->yMax; }
    public function getError() { return $this->error; }
}

function fmt($v) {
    $s = number_format($v, 4, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' || $s === '-0' ? '0' : $s;
}

$result = null;
$errors = [];
$xSubmitted = [];
$ySubmitted = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $xRaw = $_POST['x'] ?? [];
    $yRaw = $_POST['y'] ?? [];
    $xSubmitted = is_array($xRaw) ? $xRaw : [];
    $ySubmitted = is_array($yRaw) ? $yRaw : [];

    if (!is_array($xRaw) || !is_array($yRaw)) {
        $errors[] = 'Invalid form data submitted.';
    } elseif (count($xRaw) !== count($yRaw)) {
        $errors[] = 'X and Y must have the same number of values.';
    } elseif (count($xRaw) < 2) {
        $errors[] = 'Enter at least 2 data points.';
    } else {
        $x = []; $y = []; $bad = false;
        foreach ($xRaw as $v) {
            if (!is_numeric($v)) { $bad = true; break; }
            $x[] = (float)$v;
        }
        foreach ($yRaw as $v) {
            if (!is_numeric($v)) { $bad = true; break; }
            $y[] = (float)$v;
        }
        if ($bad) {
            $errors[] = 'All X and Y values must be numbers.';
        } else {
            $reg = new SimpleLinearRegression();
            if ($reg->train($x, $y)) {
                $result = $reg;
            } else {
                $errors[] = $reg->getError();
            }
        }
    }
}

$rows = $result !== null || count($xSubmitted) > 0 ? count($xSubmitted) : 2;

$chart = '';
if ($result !== null) {
    $W = 760; $H = 460; $padL = 70; $padR = 30; $padT = 25; $padB = 55;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;

    $spanX = max($result->getXMax() - $result->getXMin(), 1e-9);
    $spanY = max($result->getYMax() - $result->getYMin(), 1e-9);
    $loX = $result->getXMin() - 0.1 * $spanX;
    $hiX = $result->getXMax() + 0.1 * $spanX;
    $loY = $result->getYMin() - 0.1 * $spanY;
    $hiY = $result->getYMax() + 0.1 * $spanY;

    $xPix = function ($x) use ($padL, $plotW, $loX, $hiX) {
        return $padL + ($x - $loX) / ($hiX - $loX) * $plotW;
    };
    $yPix = function ($y) use ($padT, $plotH, $loY, $hiY) {
        return $padT + $plotH - ($y - $loY) / ($hiY - $loY) * $plotH;
    };

    $chart = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Scatter plot with regression line" xmlns="http://www.w3.org/2000/svg">';
    $chart .= '<defs><clipPath id="plotClip"><rect x="' . $padL . '" y="' . $padT . '" width="' . $plotW . '" height="' . $plotH . '"/></clipPath></defs>';

    for ($i = 0; $i <= 6; $i++) {
        $t = $loX + ($hiX - $loX) * $i / 6;
        $px = $xPix($t);
        $chart .= '<line x1="' . fmt($px) . '" y1="' . $padT . '" x2="' . fmt($px) . '" y2="' . ($padT + $plotH) . '" class="grid"/>';
        $chart .= '<text x="' . fmt($px) . '" y="' . ($padT + $plotH + 20) . '" text-anchor="middle" class="tick">' . fmt($t) . '</text>';
    }
    for ($i = 0; $i <= 5; $i++) {
        $t = $loY + ($hiY - $loY) * $i / 5;
        $py = $yPix($t);
        $chart .= '<line x1="' . $padL . '" y1="' . fmt($py) . '" x2="' . ($padL + $plotW) . '" y2="' . fmt($py) . '" class="grid"/>';
        $chart .= '<text x="' . ($padL - 10) . '" y="' . fmt($py) . '" text-anchor="end" dominant-baseline="middle" class="tick">' . fmt($t) . '</text>';
    }

    $chart .= '<line x1="' . $padL . '" y1="' . $padT . '" x2="' . $padL . '" y2="' . ($padT + $plotH) . '" class="axis"/>';
    $chart .= '<line x1="' . $padL . '" y1="' . ($padT + $plotH) . '" x2="' . ($padL + $plotW) . '" y2="' . ($padT + $plotH) . '" class="axis"/>';
    $chart .= '<text x="' . ($padL + $plotW) . '" y="' . ($padT + $plotH + 40) . '" text-anchor="end" class="axis-label">X</text>';
    $chart .= '<text x="22" y="' . $padT . '" class="axis-label">Y</text>';

    $chart .= '<g clip-path="url(#plotClip)">';
    $chart .= '<line x1="' . fmt($xPix($loX)) . '" y1="' . fmt($yPix($result->predict($loX))) . '" x2="' . fmt($xPix($hiX)) . '" y2="' . fmt($yPix($result->predict($hiX))) . '" class="reg-line"/>';
    for ($i = 0; $i < $result->getN(); $i++) {
        $x = (float)$xSubmitted[$i];
        $y = (float)$ySubmitted[$i];
        $delay = 0.6 + min($i * 0.08, 1.2);
        $chart .= '<circle cx="' . fmt($xPix($x)) . '" cy="' . fmt($yPix($y)) . '" r="5" class="point" style="animation-delay:' . fmt($delay) . 's">';
        $chart .= '<title>(' . fmt($x) . ', ' . fmt($y) . ')</title></circle>';
    }
    $chart .= '</g></svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Linear Regression Simulator</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Consolas", "Menlo", "Courier New", monospace;
            background: #05070d;
            color: #c9d6e3;
            min-height: 100vh;
            padding: 40px 16px;
            overflow-x: hidden;
        }

        .bg-grid {
            position: fixed; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(0, 229, 255, .04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 229, 255, .04) 1px, transparent 1px);
            background-size: 44px 44px;
            animation: grid-scroll 24s linear infinite;
            pointer-events: none;
        }
        @keyframes grid-scroll { from { background-position: 0 0; } to { background-position: 0 44px; } }

        .bg-glow {
            position: fixed; z-index: 0; pointer-events: none; border-radius: 50%;
            filter: blur(90px);
            animation: glow-pulse 8s ease-in-out infinite alternate;
        }
        .bg-glow.one   { width: 480px; height: 480px; top: -140px; left: -120px; background: rgba(0, 229, 255, .55); }
        .bg-glow.two   { width: 420px; height: 420px; bottom: -140px; right: -120px; background: rgba(255, 46, 136, .45); animation-delay: 3s; }
        .bg-glow.three { width: 300px; height: 300px; top: 40%; left: 55%; background: rgba(94, 60, 255, .4); animation-delay: 5s; }
        @keyframes glow-pulse { from { transform: scale(1); opacity: .25; } to { transform: scale(1.15); opacity: .45; } }

        .scanlines {
            position: fixed; inset: 0; z-index: 1; pointer-events: none;
            background: repeating-linear-gradient(0deg, rgba(255, 255, 255, .02) 0 1px, transparent 1px 3px);
            mix-blend-mode: overlay;
        }

        .container { max-width: 960px; margin: 0 auto; position: relative; z-index: 2; animation: fade-in .8s ease-out; }
        @keyframes fade-in { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

        header { text-align: center; margin-bottom: 26px; }
        h1 {
            font-size: clamp(1.4rem, 4vw, 2.2rem);
            letter-spacing: 3px;
            color: #00e5ff;
            animation: title-glow 3s ease-in-out infinite alternate;
        }
        @keyframes title-glow {
            from { text-shadow: 0 0 14px rgba(0, 229, 255, .5), 0 0 40px rgba(0, 229, 255, .2); }
            to   { text-shadow: 0 0 22px rgba(0, 229, 255, .9), 0 0 70px rgba(0, 229, 255, .4); }
        }

        .card {
            background: rgba(10, 16, 28, .85);
            border: 1px solid rgba(0, 229, 255, .25);
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 0 22px rgba(0, 229, 255, .08), inset 0 0 30px rgba(0, 229, 255, .03);
            transition: border-color .3s, box-shadow .3s, transform .3s;
            animation: fade-up .6s backwards;
        }
        .card:hover {
            border-color: rgba(0, 229, 255, .55);
            box-shadow: 0 0 34px rgba(0, 229, 255, .18);
            transform: translateY(-2px);
        }
        @keyframes fade-up { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        .card h2 {
            font-size: 15px; letter-spacing: 2px; text-transform: uppercase;
            color: #00e5ff; margin-bottom: 16px; text-shadow: 0 0 8px rgba(0, 229, 255, .5);
        }
        .card h2::before { content: "// "; color: #ff2e88; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; padding: 8px 6px; color: #5a6b7e;
            font-size: 12px; letter-spacing: 1px; border-bottom: 1px solid rgba(0, 229, 255, .15);
        }
        td { padding: 6px; border-bottom: 1px solid rgba(0, 229, 255, .07); }
        .row-num { width: 34px; text-align: center; color: #00e5ff; font-weight: 700; }
        input[type="number"] {
            width: 100%; padding: 9px 10px;
            background: rgba(4, 8, 15, .8);
            border: 1px solid #1b3a52; border-radius: 6px;
            color: #d7e6f5; font-family: inherit; font-size: 14px;
            transition: border-color .25s, box-shadow .25s;
        }
        input[type="number"]:focus { outline: none; border-color: #00e5ff; box-shadow: 0 0 12px rgba(0, 229, 255, .35); }

        button {
            padding: 9px 16px; border: 1px solid rgba(0, 229, 255, .4); border-radius: 6px;
            background: rgba(0, 229, 255, .06); color: #00e5ff;
            font-family: inherit; font-size: 13px; letter-spacing: 1px; cursor: pointer;
            transition: all .25s;
        }
        button:hover {
            background: rgba(0, 229, 255, .15);
            box-shadow: 0 0 14px rgba(0, 229, 255, .35);
            transform: translateY(-2px);
        }
        .remove-row { border-color: rgba(255, 46, 88, .5); color: #ff4d6d; background: rgba(255, 46, 88, .07); }
        .remove-row:hover { background: rgba(255, 46, 88, .18); box-shadow: 0 0 14px rgba(255, 46, 88, .4); }
        .btn-primary {
            background: linear-gradient(135deg, #00a3ff, #00e5ff);
            color: #03101a; font-weight: 700; border: none;
            box-shadow: 0 0 18px rgba(0, 229, 255, .35);
        }
        .btn-primary:hover { box-shadow: 0 0 30px rgba(0, 229, 255, .6); background: linear-gradient(135deg, #00a3ff, #00e5ff); }
        .btn-sample { border-color: rgba(255, 46, 136, .5); color: #ff7ab8; background: rgba(255, 46, 136, .07); }
        .btn-sample:hover { background: rgba(255, 46, 136, .16); box-shadow: 0 0 14px rgba(255, 46, 136, .4); }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }

        .error-box {
            background: rgba(255, 46, 88, .08);
            border: 1px solid rgba(255, 46, 88, .5);
            color: #ff8fa3;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
            box-shadow: 0 0 18px rgba(255, 46, 88, .15);
            animation: shake .4s;
        }
        .error-box ul { margin-left: 18px; }
        @keyframes shake { 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }

        .results { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
        .stat {
            background: rgba(4, 10, 20, .7);
            border: 1px solid rgba(0, 229, 255, .2);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            animation: fade-up .5s backwards;
            transition: border-color .3s, box-shadow .3s;
        }
        .stat:hover { border-color: rgba(0, 229, 255, .6); box-shadow: 0 0 16px rgba(0, 229, 255, .25); }
        .stat .label { font-size: 10px; color: #5a6b7e; letter-spacing: 1.5px; text-transform: uppercase; }
        .stat .value {
            font-size: 20px; font-weight: 700; color: #00e5ff;
            margin-top: 5px; text-shadow: 0 0 10px rgba(0, 229, 255, .5);
        }
        .equation {
            text-align: center; font-size: 22px; font-weight: 700;
            color: #00e5ff; text-shadow: 0 0 16px rgba(0, 229, 255, .7);
            border: 1px dashed rgba(0, 229, 255, .4);
            background: rgba(0, 229, 255, .05);
            border-radius: 8px; padding: 14px; margin-bottom: 16px;
            animation: fade-up .5s backwards;
        }

        .chart-wrap { overflow-x: auto; }
        .chart-wrap svg {
            width: 100%; max-width: 760px; display: block; margin: 0 auto;
            filter: drop-shadow(0 0 24px rgba(0, 229, 255, .08));
        }
        .grid { stroke: rgba(0, 229, 255, .09); stroke-width: 1; }
        .tick { fill: #5a6b7e; font-size: 11px; font-family: inherit; }
        .axis { stroke: rgba(0, 229, 255, .45); stroke-width: 1.5; }
        .axis-label { fill: #00e5ff; font-size: 13px; font-weight: 700; font-family: inherit; }
        .point {
            fill: #00e5ff; stroke: #05070d; stroke-width: 2;
            animation: point-pop .5s ease-out backwards;
            transform-box: fill-box; transform-origin: center;
            transition: fill .2s;
        }
        .point:hover { transform: scale(2); fill: #ff2e88; }
        @keyframes point-pop { from { opacity: 0; transform: scale(0); } to { opacity: 1; transform: scale(1); } }
        .reg-line {
            stroke: #ff2e88; stroke-width: 3; stroke-linecap: round;
            filter: drop-shadow(0 0 6px rgba(255, 46, 136, .9));
            stroke-dasharray: 3000; stroke-dashoffset: 3000;
            animation: draw-line 1.8s .2s ease-out forwards;
        }
        @keyframes draw-line { to { stroke-dashoffset: 0; } }
        .empty-chart {
            text-align: center; color: #5a6b7e; padding: 60px 0;
            border: 1px dashed rgba(0, 229, 255, .25); border-radius: 8px;
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>
    <div class="bg-grid"></div>
    <div class="bg-glow one"></div>
    <div class="bg-glow two"></div>
    <div class="bg-glow three"></div>
    <div class="scanlines"></div>

<div class="container">
    <header>
        <h1>Linear Regression</h1>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="animation-delay:.15s">
        <h2>Data</h2>
        <form method="post" id="regression-form">
            <table id="data-table">
                <thead>
                    <tr><th></th><th>X</th><th>Y</th><th></th></tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < $rows; $i++): ?>
                        <tr>
                            <td class="row-num"><?php echo $i + 1; ?></td>
                            <td><input type="number" step="any" name="x[]" value="<?php echo htmlspecialchars($xSubmitted[$i] ?? ''); ?>"></td>
                            <td><input type="number" step="any" name="y[]" value="<?php echo htmlspecialchars($ySubmitted[$i] ?? ''); ?>"></td>
                            <td><button type="button" class="remove-row">Remove</button></td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            <div class="actions">
                <button type="button" id="add-row">+ Add Row</button>
                <button type="button" id="sample-data" class="btn-sample">Sample Data</button>
                <button type="submit" class="btn-primary">Run</button>
            </div>
        </form>
    </div>

    <?php if ($result !== null): ?>
        <div class="card" style="animation-delay:.25s">
            <h2>Results</h2>
            <div class="equation">y = <?php echo fmt($result->getSlope()); ?>x <?php echo $result->getIntercept() >= 0 ? '+' : '-'; ?> <?php echo fmt(abs($result->getIntercept())); ?></div>
            <div class="results">
                <div class="stat" style="animation-delay:.3s"><div class="label">Slope (b1)</div><div class="value"><?php echo fmt($result->getSlope()); ?></div></div>
                <div class="stat" style="animation-delay:.38s"><div class="label">Intercept (b0)</div><div class="value"><?php echo fmt($result->getIntercept()); ?></div></div>
                <div class="stat" style="animation-delay:.46s"><div class="label">Correlation (r)</div><div class="value"><?php echo fmt($result->getR()); ?></div></div>
                <div class="stat" style="animation-delay:.54s"><div class="label">R-squared</div><div class="value"><?php echo fmt($result->getR2()); ?></div></div>
                <div class="stat" style="animation-delay:.62s"><div class="label">Data Points</div><div class="value"><?php echo $result->getN(); ?></div></div>
                <div class="stat" style="animation-delay:.7s"><div class="label">SSE</div><div class="value"><?php echo fmt($result->getSse()); ?></div></div>
            </div>
        </div>
        <div class="card" style="animation-delay:.35s">
            <h2>Plot</h2>
            <div class="chart-wrap"><?php echo $chart; ?></div>
        </div>
    <?php else: ?>
        <div class="card" style="animation-delay:.25s">
            <h2>Plot</h2>
            <div class="empty-chart">No data yet</div>
        </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.querySelector('#data-table tbody');
    const addBtn = document.getElementById('add-row');
    const sampleBtn = document.getElementById('sample-data');

    function renumber() {
        tbody.querySelectorAll('tr').forEach(function (tr, i) {
            tr.querySelector('.row-num').textContent = i + 1;
        });
    }

    function addRow(x, y) {
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td class="row-num"></td>' +
            '<td><input type="number" step="any" name="x[]" value="' + (x ?? '') + '"></td>' +
            '<td><input type="number" step="any" name="y[]" value="' + (y ?? '') + '"></td>' +
            '<td><button type="button" class="remove-row">Remove</button></td>';
        tbody.appendChild(tr);
        renumber();
    }

    addBtn.addEventListener('click', function () {
        addRow('', '');
        tbody.querySelector('tr:last-child input').focus();
    });

    sampleBtn.addEventListener('click', function () {
        tbody.innerHTML = '';
        const sampleX = [1, 2, 3, 4, 5, 6, 7, 8];
        const sampleY = [50, 55, 60, 62, 68, 75, 78, 85];
        sampleX.forEach(function (x, i) { addRow(x, sampleY[i]); });
    });

    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-row');
        if (!btn || tbody.querySelectorAll('tr').length <= 1) return;
        btn.closest('tr').remove();
        renumber();
    });

    document.querySelectorAll('.stat .value').forEach(function (el) {
        const target = parseFloat(el.textContent);
        const decimals = (el.textContent.split('.')[1] || '').length;
        const dur = 900;
        const t0 = performance.now();
        function tick(now) {
            const p = Math.min((now - t0) / dur, 1);
            const eased = 1 - Math.pow(1 - p, 3);
            el.textContent = (target * eased).toFixed(decimals);
            if (p < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });
});
</script>
</body>
</html>
