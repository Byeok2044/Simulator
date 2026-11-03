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
        $this->error = null;
        $n = count($x);
        if ($n !== count($y)) {
            $this->error = 'X and Y must have the same number of values.';
            return false;
        }
        if ($n === 0) {
            $this->error = 'No number entered.';
            return false;
        }
        foreach (array_merge($x, $y) as $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $this->error = 'No number entered.';
                return false;
            }
        }
        if ($n < 2) {
            $this->error = 'At least 2 data points are required.';
            return false;
        }
        foreach (array_merge($x, $y) as $value) {
            if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > 1e100) {
                $this->error = 'Use finite numbers with absolute values no larger than 1e100.';
                return false;
            }
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
            $this->error = 'X values are identical or too close to distinguish numerically. Use distinct X values or rescale them.';
            return false;
        }

        $this->slope = $sxy / $sxx;
        $this->intercept = $meanY - $this->slope * $meanX;
        if (!is_finite($this->slope) || !is_finite($this->intercept)) {
            $this->error = 'This data exceeds numerical precision. Rescale your X and Y values.';
            return false;
        }
        $this->r = $syy == 0 ? null : max(-1, min(1, ($sxy / sqrt($sxx)) / sqrt($syy)));
        $this->r2 = $this->r === null ? null : $this->r * $this->r;
        $this->sse = 0;
        for ($i = 0; $i < $n; $i++) {
            $d = $y[$i] - $this->predict($x[$i]);
            $this->sse += $d * $d;
        }
        if (!is_finite($this->sse)) {
            $this->error = 'This data exceeds numerical precision. Rescale your X and Y values.';
            return false;
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
    if ($v === null) return 'N/A';
    if ($v != 0 && (abs($v) < 0.0001 || abs($v) >= 1e7)) return sprintf('%.4g', $v);
    $s = number_format($v, 4, '.', '');
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' || $s === '-0' ? '0' : $s;
}

