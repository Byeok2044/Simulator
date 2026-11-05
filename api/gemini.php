<?php
function generateExplanation(SimpleLinearRegression $result): string {
    $envFile = dirname(__DIR__) . '/.env';
    $settings = @parse_ini_file($envFile, false, INI_SCANNER_RAW) ?: [];
    $setting = static function (string $name, string $default = '') use ($settings): string {
        $value = getenv($name);
        return trim($value === false ? ($settings[$name] ?? $default) : $value);
    };
    $key = $setting('GEMINI_API_KEY');
    $model = $setting('GEMINI_MODEL', 'gemini-3.5-flash-lite');
    if ($key === '') {
        throw new RuntimeException('Set GEMINI_API_KEY in the server settings to generate an explanation.');
    }
    if (!preg_match('/\A[A-Za-z0-9_.-]+\z/', $key) || !preg_match('/\Agemini-[a-z0-9.-]+\z/', $model)) {
        throw new RuntimeException('Check GEMINI_API_KEY and GEMINI_MODEL in the server settings.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Enable the PHP cURL extension to generate an explanation.');
    }

    $slope = $result->getSlope();
    $intercept = $result->getIntercept();
    $equation = 'Y = ' . fmt($slope) . 'X' . ($intercept < 0 ? ' - ' : ' + ') . fmt(abs($intercept));
    $summary = json_encode([
        'equation' => $equation,
        'slope' => $slope,
        'correlation' => $result->getR(),
        'r_squared' => $result->getR2(),
        'rmse' => sqrt($result->getSse() / $result->getN()),
        'sample_size' => $result->getN(),
    ]);
    $prompt = <<<'PROMPT'
Explain these simple linear regression results to a beginner in one short paragraph of plain text.
Describe the equation and the predicted change in Y per one-unit increase in X. Interpret the correlation,
R-squared, and RMSE in context and mention the sample size. A null metric is undefined for constant Y.
Use only the supplied statistics. Avoid causal claims and avoid judging RMSE without a known scale.
PROMPT;
    $body = json_encode([
        'contents' => [['parts' => [['text' => $prompt . "\n\n" . $summary]]]],
        'generationConfig' => ['maxOutputTokens' => 4096],
    ]);

    $request = curl_init("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent");
    curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);

    if ($status === 429) {
        throw new RuntimeException('Gemini has reached its usage limit. Try again later.');
    }
    if (in_array($status, [400, 401, 403, 404], true)) {
        throw new RuntimeException('Gemini rejected the request. Check your API key and model settings.');
    }
    if (!is_string($response) || $status !== 200) {
        throw new RuntimeException('Could not reach Gemini. Please try again.');
    }

    $data = json_decode($response, true);
    $candidate = $data['candidates'][0] ?? [];
    $text = [];
    foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
        if (empty($part['thought']) && is_string($part['text'] ?? null)) {
            $text[] = $part['text'];
        }
    }
    $explanation = trim(implode('', $text));
    if ($explanation === '' || ($candidate['finishReason'] ?? '') !== 'STOP') {
        throw new RuntimeException('Gemini did not return a complete explanation. Please try again.');
    }
    return $explanation;
}
