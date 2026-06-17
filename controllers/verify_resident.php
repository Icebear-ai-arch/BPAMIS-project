<?php
include '../server/server.php';
include 'ocr_handler.php';

header('Content-Type: application/json; charset=utf-8');

$id = intval($_POST['id'] ?? 0);
$useOCR = intval($_POST['useOCR'] ?? $_POST['useocr'] ?? 1);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid resident ID']);
    exit;
}

$stmt = $conn->prepare("SELECT Resident_ID, First_Name, Middle_Name, Last_Name, Address, email, valid_id_path FROM resident_info WHERE Resident_ID = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare error']);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Resident not found']);
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$first = trim($row['First_Name'] ?? '');
$middle = trim($row['Middle_Name'] ?? '');
$last = trim($row['Last_Name'] ?? '');
$fullName = trim($first . ' ' . ($middle ? $middle . ' ' : '') . $last);
$address = trim($row['Address'] ?? '');
$validIdPath = trim($row['valid_id_path'] ?? '');

if (empty($validIdPath)) {
    echo json_encode(['success' => false, 'message' => 'No valid ID uploaded for this resident']);
    exit;
}

// Resolve possible file system paths
$attempts = [];
$baseDir = realpath(__DIR__ . '/..');
$attempts[] = $baseDir . DIRECTORY_SEPARATOR . ltrim($validIdPath, '/\\');
$attempts[] = $baseDir . DIRECTORY_SEPARATOR . 'uploads_id' . DIRECTORY_SEPARATOR . basename($validIdPath);
$attempts[] = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . ltrim($validIdPath, '/\\');

$foundPath = null;
foreach ($attempts as $p) {
    $p = str_replace(['/', '\\\\'], DIRECTORY_SEPARATOR, $p);
    if (file_exists($p)) { $foundPath = $p; break; }
}
if (!$foundPath) {
    $legacy = __DIR__ . '/../' . $validIdPath;
    if (file_exists($legacy)) $foundPath = $legacy;
}
if (!$foundPath) {
    echo json_encode(['success' => false, 'message' => 'Valid ID file not found on server', 'attempts' => $attempts]);
    exit;
}

$webUrl = '../' . ltrim(str_replace('\\', '/', $validIdPath), '/');

if (!$useOCR) {
    echo json_encode([
        'success' => true,
        'message' => 'OCR skipped by secretary',
        'useOCR' => false,
        'resident' => ['id' => $id, 'name' => $fullName, 'address' => $address, 'email' => $row['email'] ?? ''],
        'image_url' => $webUrl
    ]);
    exit;
}

// Run OCR
$ocr = ocr_space_file($foundPath);

if (!$ocr['success']) {
    echo json_encode(['success' => false, 'message' => ($ocr['message'] ?? 'OCR service error'), 'image_url' => $webUrl]);
    exit;
}

// Normalize and fuzzy-match
$full_parsed = trim($ocr['parsed_text'] ?? '');
$words = preg_split('/\s+/', $full_parsed);
if (count($words) > 200) { // keep more text for robust matching
    $words = array_slice($words, 0, 200);
}
$parsedRaw = trim(implode(' ', $words));
$parsed = strtolower(preg_replace('/\s+/', ' ', $parsedRaw));

// Helpers
$normalize = function($s, $isAddress = false) {
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    if ($isAddress) {
        // unify barangay synonyms
        $s = preg_replace('/\bbrgy\.?\b/', 'barangay', $s);
        $s = str_replace('bgy', 'barangay', $s);
        $s = str_replace('st', 'street', $s);
    }
    return $s;
};
$contains = function($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
};
$levSim = function($a, $b){
    $la = strlen($a); $lb = strlen($b);
    if ($la === 0 && $lb === 0) return 1.0;
    $dist = levenshtein($a, $b);
    $max = max($la, $lb);
    return $max > 0 ? (1.0 - ($dist / $max)) : 0.0;
};

// Prepare resident data
$firstName = trim($first);
$lastName  = trim($last);
$addrFull  = trim($address);

// Extract house number (before first comma)
$houseNo = '';
if ($addrFull !== '') {
    $beforeComma = explode(',', $addrFull)[0] ?? '';
    $houseNo = trim($beforeComma);
}
// numeric fragment (fallback for OCR)
$houseDigits = preg_replace('/[^0-9]/', '', $houseNo);

// Extract barangay name from address (expects "... Barangay <Name>, City ...")
$barangayName = '';
$addrNorm = $normalize($addrFull, true);
if (preg_match('/barangay\s+([a-z0-9\- ]{2,})/i', $addrNorm, $m)) {
    $barangayName = trim(explode(',', $m[1])[0]);
    // limit to first 3 words of barangay
    $barangayName = implode(' ', array_slice(explode(' ', $barangayName), 0, 3));
}

// Normalize parsed text
$parsedNorm = $normalize($parsed, true);

// Build required checks
$firstFound = false;
$lastFound  = false;
$houseFound = false;
$brgyFound  = false;

// First name: exact token or high similarity small window
if ($firstName !== '') {
    $fn = $normalize($firstName, false);
    $firstFound = $contains($parsedNorm, ' '.$fn.' ') || $contains($parsedNorm, $fn.' ') || $contains($parsedNorm, ' '.$fn) || $levSim($fn, $parsedNorm) > 0.55;
}

// Last name
if ($lastName !== '') {
    $ln = $normalize($lastName, false);
    $lastFound = $contains($parsedNorm, ' '.$ln.' ') || $contains($parsedNorm, $ln.' ') || $contains($parsedNorm, ' '.$ln) || $levSim($ln, $parsedNorm) > 0.55;
}

// House number: try full token, then digits-only fallback
if ($houseNo !== '') {
    $hn = $normalize($houseNo, false);
    $houseFound = $contains($parsedNorm, $hn);
    if (!$houseFound && $houseDigits !== '') {
        $houseFound = $contains($parsedNorm, $houseDigits);
    }
}

// Barangay name: allow "brgy/barangay <name>"
if ($barangayName !== '') {
    $bn = $normalize($barangayName, true);
    // look for "barangay <name>" anywhere
    $brgyFound = $contains($parsedNorm, 'barangay '.$bn) || $contains($parsedNorm, $bn.' barangay') || $contains($parsedNorm, $bn);
}

// Final decision (updated): only first name, last name, and barangay must match. House number no longer required.
$verification_pass = ($firstFound && $lastFound && $brgyFound);

echo json_encode([
    'success' => (bool)$verification_pass,
    'message' => $verification_pass ? 'OCR matched required details.' : 'OCR finished; required details did not sufficiently match.',
    'resident' => ['id' => $id, 'first_name' => $firstName, 'last_name' => $lastName, 'address' => $address, 'email' => $row['email'] ?? ''],
    'image_url' => $webUrl,
    'matches' => [
        'first_name' => (bool)$firstFound,
        'last_name' => (bool)$lastFound,
        'barangay' => (bool)$brgyFound,
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
?>