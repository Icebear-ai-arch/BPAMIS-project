
<?php
// controllers/ocr_handler.php
// OCR.Space wrapper (no local monthly counter). Reads key from c:\xampp\htdocs\BPAMIS_01\ocr.env
// ocr.env example:
// OCR_SPACE_API_KEY=your_real_key_here

function read_env($key, $default = null) {
    // Try OS env first, then controllers/ocr.env, then project-root/ocr.env
    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') return $fromEnv;

    $paths = [
        __DIR__ . '/ocr.env',     // supports: c:\xampp\htdocs\BPAMIS_01\controllers\ocr.env
        __DIR__ . '/../ocr.env',  // supports: c:\xampp\htdocs\BPAMIS_01\ocr.env
    ];
    foreach ($paths as $envPath) {
        if (!file_exists($envPath)) continue;
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $k = trim(substr($line, 0, $pos));
            $v = trim(substr($line, $pos + 1));
            if ($k === $key) return $v;
        }
    }
    return $default;
}

/*
  Adjustable knobs:
  - OCR_HTTP_CONNECT_TIMEOUT: time to establish connection (seconds)
  - OCR_HTTP_TIMEOUT: total time allowed for the request (seconds)
  - OCR_ENGINE: 2 is more accurate than 1 (slightly slower). Free tier supports 1/2.
  - OCR_MAX_CHARS: trim parsed text for downstream matching to avoid over-processing.
*/
const OCR_HTTP_CONNECT_TIMEOUT = 15;  // editable
const OCR_HTTP_TIMEOUT         = 45;  // editable — good for a PH National ID
const OCR_ENGINE               = 2;   // editable (1 or 2)
const OCR_MAX_CHARS            = 4000; // editable — enough for ID text

function map_ocr_error($httpCode, $exitCode, $errMsg) {
    $m = strtolower((string)$errMsg);
    if ($httpCode === 403) return ['code'=>'access_denied','reason'=>'Access denied or invalid API key.'];
    if ($httpCode === 429 || strpos($m,'daily limit')!==false || strpos($m,'too many')!==false || strpos($m,'exceed')!==false || strpos($m,'limit')!==false)
        return ['code'=>'rate_limit','reason'=>'Daily limit reached. Please try again tomorrow or use a paid plan.'];
    if (strpos($m,'file failed validation')!==false || strpos($m,'not a valid image')!==false)
        return ['code'=>'file_invalid','reason'=>'File failed validation. Upload a valid image file.'];
    if (strpos($m,'too large')!==false || strpos($m,'maximum file size')!==false)
        return ['code'=>'file_too_large','reason'=>'Image cannot be parsed. Too large.'];
    if (strpos($m,'unsupported')!==false || strpos($m,'format')!==false)
        return ['code'=>'unsupported_format','reason'=>'Unsupported image format.'];
    if ($exitCode === 2 || strpos($m,'text not found')!==false)
        return ['code'=>'text_not_found','reason'=>'No readable text found in the image.'];
    if ($exitCode === 3 || strpos($m,'cannot be parsed')!==false || strpos($m,'parse')!==false)
        return ['code'=>'parse_error','reason'=>'Image cannot be parsed. Too large, corrupted, or poor quality.'];
    if ($exitCode === 4 || strpos($m,'timeout')!==false)
        return ['code'=>'timeout','reason'=>'OCR timed out. Try again later.'];
    return ['code'=>'provider_error','reason'=> ($errMsg ?: 'OCR provider reported an error.')];
}

function ocr_space_file($file_path, $language = 'eng') {
    $api_key = read_env('OCR_SPACE_API_KEY');
    if (!$api_key) {
        return ['success' => false, 'code'=>'missing_key', 'reason'=>'OCR API key missing. Add OCR_SPACE_API_KEY to ocr.env', 'message'=>'OCR API key missing. Add OCR_SPACE_API_KEY to ocr.env'];
    }

    if (!is_file($file_path)) {
        return ['success' => false, 'code'=>'file_missing', 'reason'=>'File not found on server.', 'message' => 'File not found: ' . $file_path];
    }

    $url = 'https://api.ocr.space/parse/image';

    $postfields = [
        'apikey'              => $api_key,
        'language'            => $language,
        'isOverlayRequired'   => 'false',
        'OCREngine'           => OCR_ENGINE,
        'detectOrientation'   => 'true',
        'scale'               => 'true',
        'file'                => new CURLFile($file_path)
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postfields,
        CURLOPT_CONNECTTIMEOUT => OCR_HTTP_CONNECT_TIMEOUT,
        CURLOPT_TIMEOUT        => OCR_HTTP_TIMEOUT,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json']
    ]);

    $resp     = curl_exec($ch);
    $err      = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['success' => false, 'code'=>'network_error', 'reason'=>'Network/cURL error.', 'message' => ($err ?: 'unknown'), 'http_code' => $httpCode];
    }

    if ($httpCode === 403 || $httpCode === 429) {
        $mapped = map_ocr_error($httpCode, null, '');
        return ['success' => false, 'http_code'=>$httpCode] + $mapped;
    }

    $json = json_decode($resp, true);
    if (!is_array($json)) {
        return ['success' => false, 'code'=>'bad_json', 'reason'=>'Invalid JSON from OCR provider.', 'message' => $resp, 'http_code' => $httpCode];
    }

    $exitCode = isset($json['OCRExitCode']) ? (int)$json['OCRExitCode'] : null;
    $errored  = !empty($json['IsErroredOnProcessing']);
    $errMsg   = '';
    if (!empty($json['ErrorMessage'])) {
        $errMsg = is_array($json['ErrorMessage']) ? implode('; ', $json['ErrorMessage']) : (string)$json['ErrorMessage'];
    } elseif (!empty($json['ErrorMessageDetails'])) {
        $errMsg = (string)$json['ErrorMessageDetails'];
    }

    if ($errored || ($exitCode !== null && $exitCode !== 1)) {
        $mapped = map_ocr_error($httpCode, $exitCode, $errMsg);
        return ['success'=>false, 'http_code'=>$httpCode, 'exit_code'=>$exitCode, 'raw_response'=>$json] + $mapped;
    }

    if (!isset($json['ParsedResults'][0]['ParsedText'])) {
        $mapped = map_ocr_error($httpCode, $exitCode, 'OCR failed to extract text');
        return ['success'=>false, 'http_code'=>$httpCode, 'exit_code'=>$exitCode, 'raw_response'=>$json] + $mapped;
    }

    $parsed = trim((string)$json['ParsedResults'][0]['ParsedText']);
    if (strlen($parsed) > OCR_MAX_CHARS) $parsed = substr($parsed, 0, OCR_MAX_CHARS);

    return [
        'success'      => true,
        'parsed_text'  => $parsed,
        'http_code'    => $httpCode,
        'exit_code'    => $exitCode,
        'raw_response' => $json
    ];
}

/*
Notes for tuning (National ID baseline):
- Typical text length extracted: ~400–1,200 characters. OCR_MAX_CHARS=4000 is safe. Edit OCR_MAX_CHARS above to change.
- Average processing time on free OCR.Space for a single ID image: ~2–8 seconds depending on quality.
- Timeouts:
  - OCR_HTTP_CONNECT_TIMEOUT (default 15s) — time to establish connection.
  - OCR_HTTP_TIMEOUT (default 45s) — total request time budget. Increase if your images are large or the provider is slow.
- Accuracy vs speed:
  - OCR_ENGINE=2 is more accurate for IDs; set to 1 if you want slightly faster responses.
*/