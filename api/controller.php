<?php
require_once __DIR__ . '/regression.php';
require_once __DIR__ . '/csv.php';
require_once __DIR__ . '/plot.php';

function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function postText(string $key, string $default = ''): string { return is_string($_POST[$key] ?? null) ? $_POST[$key] : $default; }
function label(string $value): string {
    $value = trim($value);
    return $value === '' ? 'Value' : (preg_match('//u', $value) === 1 ? implode('', array_slice(preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY), 0, 80)) : 'Value');
}

$errors = []; $notice = ''; $result = null; $chart = ''; $plotStatus = ''; $prediction = '';
$pairs = [['', ''], ['', '']]; $page = 0; $pageSize = 100;
$action = postText('action', 'run');
$axisX = label(postText('axis_x', 'X')); $axisY = label(postText('axis_y', 'Y'));
$csvName = trim(postText('csv_name')) === '' ? '' : label(basename(str_replace('\\', '/', postText('csv_name'))));
$zoom = 1;
$options = ['mode' => 'fit', 'zoom' => $zoom,
    'line' => true, 'grid' => true,
    'residuals' => false, 'axisX' => $axisX, 'axisY' => $axisY];
$predictX = postText('predict_x');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) throw new InvalidArgumentException('The submitted data exceeds the server upload limit. Choose a smaller file.');
        $payload = postText('points', '[["",""],["",""]]');
        if (strlen($payload) > 2 * 1024 * 1024) throw new InvalidArgumentException('The dataset is too large.');
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || !array_is_list($decoded) || count($decoded) < 1 || count($decoded) > 5000) throw new InvalidArgumentException('Invalid dataset. Use 1 to 5,000 rows.');
        foreach ($decoded as $pair) {
            if (!is_array($pair) || !array_is_list($pair) || count($pair) !== 2 || !is_scalar($pair[0]) || !is_scalar($pair[1]) || strlen((string)$pair[0]) > 160 || strlen((string)$pair[1]) > 160) throw new InvalidArgumentException('Each row must contain two numeric values.');
        }
        $pairs = $decoded;
        $page = max(0, min((int)postText('page', '0'), (int)floor((count($pairs) - 1) / $pageSize)));
        // Only one page is editable per request, staying below PHP's form-field limit.
        $edits = $_POST['edit'] ?? [];
        if (!is_array($edits) || count($edits) > $pageSize) throw new InvalidArgumentException('Invalid edited rows.');
        foreach ($edits as $index => $pair) {
            if (!ctype_digit((string)$index) || $index < $page * $pageSize || $index >= min(count($pairs), ($page + 1) * $pageSize) || !is_array($pair) || count($pair) !== 2 || !isset($pair[0], $pair[1]) || !is_string($pair[0]) || !is_string($pair[1]) || strlen($pair[0]) > 160 || strlen($pair[1]) > 160) throw new InvalidArgumentException('Invalid edited row.');
            $pairs[$index] = [$pair[0], $pair[1]];
        }
        if ($action === 'import_csv') {
            $upload = $_FILES['csv_file'] ?? null;
            if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new InvalidArgumentException('Choose a CSV file first.');
            if (($upload['error'] ?? -1) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('CSV upload failed. Choose a file no larger than 2 MB.');
            if (!is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('Invalid CSV upload.');
            if (filesize($upload['tmp_name']) > 2 * 1024 * 1024) throw new InvalidArgumentException('Choose a CSV file no larger than 2 MB.');
            $import = importCsvText(file_get_contents($upload['tmp_name']));
            $pairs = $import['pairs']; $page = 0;
            $axisX = label($import['axisX']); $axisY = label($import['axisY']);
            $csvName = label(basename(str_replace('\\', '/', is_string($upload['name'] ?? null) ? $upload['name'] : 'CSV file')));
            $notice = 'Imported ' . count($pairs) . ' points.';
        } elseif ($action === 'remove_csv') {
            if ($csvName !== '') {
                $pairs = [['', ''], ['', '']]; $page = 0; $predictX = '';
                $axisX = 'X'; $axisY = 'Y';
            }
            $csvName = '';
            $notice = 'CSV removed.';
        } elseif ($action === 'clear') {
            $pairs = [['', ''], ['', '']]; $page = 0; $zoom = 1; $predictX = '';
            $csvName = ''; $axisX = 'X'; $axisY = 'Y';
            $notice = 'Rows cleared.';
        } elseif ($action === 'sample') {
            $pairs = array_map(fn($y, $i) => [$i + 1, $y], [50, 55, 60, 62, 68, 75, 78, 85], range(0, 7)); $page = 0;
            $csvName = ''; $axisX = 'X'; $axisY = 'Y';
        } elseif ($action === 'add') {
            if (count($pairs) >= 5000) throw new InvalidArgumentException('Use at most 5,000 rows.');
            $pairs[] = ['', '']; $page = (int)floor((count($pairs) - 1) / $pageSize);
        } elseif (str_starts_with($action, 'remove_')) {
            $index = substr($action, 7);
            if (ctype_digit($index) && isset($pairs[(int)$index]) && count($pairs) > 1) array_splice($pairs, (int)$index, 1);
        } elseif ($action === 'previous') $page = max(0, $page - 1);
        elseif ($action === 'next') $page++;

        if (in_array($action, ['import_csv', 'run', 'predict', 'download_plot'], true)) {
            $reg = new SimpleLinearRegression();
            if (!$reg->train(array_column($pairs, 0), array_column($pairs, 1))) throw new InvalidArgumentException($reg->getError());
            $result = $reg;
            $options['axisX'] = $axisX; $options['axisY'] = $axisY;
            $plot = renderPlot($reg, $pairs, $options); $chart = $plot['svg'];
            $plotStatus = count($pairs) . ' points · ' . $plot['visible'] . ' in view';
            if ($action === 'download_plot') {
                header('Content-Type: image/svg+xml; charset=UTF-8'); header('Content-Disposition: attachment; filename="regression-plot.svg"');
                echo $chart; exit;
            }
            if ($predictX !== '') {
                if (!validNumber($predictX) || !is_finite($reg->predict((float)$predictX))) $prediction = 'Enter a finite X value with magnitude at most 1e100.';
                else $prediction = $axisY . ' = ' . fmt($reg->predict((float)$predictX)) . (((float)$predictX < $reg->getXMin() || (float)$predictX > $reg->getXMax()) ? ' (outside observed X range)' : '');
            } elseif ($action === 'predict') $prediction = 'Enter an X value to predict Y.';
        }
    } catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
}
$page = max(0, min($page, (int)floor((count($pairs) - 1) / $pageSize)));
$pagePairs = array_slice($pairs, $page * $pageSize, $pageSize, true);
