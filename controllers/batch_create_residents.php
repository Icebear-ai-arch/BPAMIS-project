<?php
session_start();
require_once(__DIR__ . '/../server/server.php');

// Prefer Composer only if PHP >= 8.1 to avoid vendor platform_check fatal
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (version_compare(PHP_VERSION, '8.1.0', '>=') && file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Fallback: minimal autoloader for the bundled PhpSpreadsheet folder
if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
    spl_autoload_register(function ($class) {
        $prefix = 'PhpOffice\\PhpSpreadsheet\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
        $relative = substr($class, strlen($prefix)); // e.g., "Reader\Xlsx"
        $file = __DIR__ . '/../PhpSpreadsheet/' . str_replace('\\','/',$relative) . '.php';
        if (is_file($file)) require $file;
    });
}

$hasPhpSpreadsheet = class_exists('\PhpOffice\PhpSpreadsheet\IOFactory');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
if (!isset($_FILES['batch_file']) || $_FILES['batch_file']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'message' => 'Please upload a file (.xlsx or .csv).']);
    exit;
}

$file = $_FILES['batch_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
    exit;
}

// NOTE: No explicit size limit here. If you need to increase PHP limits, edit php.ini (upload_max_filesize, post_max_size).

$origName = $file['name'];
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

function table_exists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return $res && $res->num_rows > 0;
}

function email_exists_any($conn, $email) {
    $email = trim($email);
    if ($email === '') return false;

    $tables = [];
    if (table_exists($conn, 'resident_info')) $tables[] = ['resident_info', 'email'];
    if (table_exists($conn, 'external_users')) $tables[] = ['external_users', 'email'];
    if (table_exists($conn, 'barangay_officials')) $tables[] = ['barangay_officials', 'email'];

    foreach ($tables as [$t, $col]) {
        $stmt = $conn->prepare("SELECT 1 FROM {$t} WHERE {$col} = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            if ($exists) return true;
        }
    }
    return false;
}

// Read rows into a uniform array
$rows = []; // keys: first_name, middle_name, last_name, email, house_no, purok, password(optional)

// Helper: convert Excel column letters (e.g., "AA") to 1-based index
function xlsx_col_to_index($letters) {
    $letters = strtoupper(trim((string)$letters));
    $n = 0; for ($i=0; $i<strlen($letters); $i++) { $n = $n*26 + (ord($letters[$i]) - 64); }
    return max(1, $n);
}

// NEW: convert 1-based index to Excel column letters (1->A, 27->AA)
function xlsx_index_to_col($index) {
    $index = max(1, (int)$index);
    $col = '';
    while ($index > 0) {
        $index--; $col = chr(($index % 26) + 65) . $col; $index = intdiv($index, 26);
    }
    return $col;
}

try {
    if ($ext === 'csv') {
        $fh = fopen($file['tmp_name'], 'r');
        if ($fh === false) throw new Exception('Unable to open CSV.');
        $header = fgetcsv($fh);
        if (!$header) throw new Exception('Empty CSV.');
        $map = [];
        foreach ($header as $i => $h) { $map[$i] = strtolower(trim($h)); }
        while (($data = fgetcsv($fh)) !== false) {
            $rowAssoc = ['first_name'=>'','middle_name'=>'','last_name'=>'','email'=>'','house_no'=>'','purok'=>'','password'=>'','birthdate'=>''];
            foreach ($data as $i => $val) {
                $key = $map[$i] ?? null;
                if (!$key) continue;
                if ($key === 'first name' || $key === 'firstname' || $key === 'first_name') $key = 'first_name';
                if ($key === 'middle name' || $key === 'middlename' || $key === 'middle_name') $key = 'middle_name';
                if ($key === 'last name' || $key === 'lastname' || $key === 'last_name') $key = 'last_name';
                if ($key === 'house no' || $key === 'house_no' || $key === 'house number' || $key === 'houseno') $key = 'house_no';
                if ($key === 'purok/street' || $key === 'purok' || $key === 'street') $key = 'purok';
                if ($key === 'birthdate' || $key === 'birth date' || $key === 'dob') $key = 'birthdate';
                if ($key === 'email') $key = 'email';
                if ($key === 'password') $key = 'password';
                if (isset($rowAssoc[$key])) $rowAssoc[$key] = trim((string)$val);
            }
            $rows[] = $rowAssoc;
        }
        fclose($fh);
    } elseif ($ext === 'xlsx') {
        if (!$hasPhpSpreadsheet) {
            echo json_encode(['success' => false, 'message' => 'XLSX support not available. PhpSpreadsheet not loaded. Upload CSV instead.']);
            exit;
        }
        // Require ZIP/XML for XLSX
        if (!class_exists('ZipArchive') || !extension_loaded('zip')) {
            echo json_encode(['success' => false, 'message' => 'PHP zip extension is disabled. Enable extension=zip in php.ini or upload CSV.']);
            exit;
        }
        if (!extension_loaded('xml')) {
            echo json_encode(['success' => false, 'message' => 'PHP xml extension is disabled. Enable extension=xml in php.ini or upload CSV.']);
            exit;
        }

        // Load workbook
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        if (method_exists($reader, 'setReadDataOnly')) {
            $reader->setReadDataOnly(true);
        }
        $spreadsheet = $reader->load($file['tmp_name']);   // was around your line 115 area
        $sheet = $spreadsheet->getActiveSheet();

        $highestRow = $sheet->getHighestRow();
        $highestColLetters = $sheet->getHighestColumn();
        $highestCol = xlsx_col_to_index($highestColLetters); // replaces Coordinate::columnIndexFromString (your old line 115)

        // Build header map
        $map = [];
        for ($c=1; $c <= $highestCol; $c++) {
            $col = xlsx_index_to_col($c);
            $h = strtolower(trim((string)$sheet->getCell($col.'1')->getValue()));
            $map[$c] = $h;
        }

        // Rows
        for ($r=2; $r <= $highestRow; $r++) {
            $rowAssoc = ['first_name'=>'','middle_name'=>'','last_name'=>'','email'=>'','house_no'=>'','purok'=>'','password'=>'','birthdate'=>''];
            for ($c=1; $c <= $highestCol; $c++) {
                $key = $map[$c] ?? null;
                $col = xlsx_index_to_col($c);
                $val = trim((string)$sheet->getCell($col.$r)->getValue());
                if (!$key) continue;
                if ($key === 'first name' || $key === 'firstname' || $key === 'first_name') $key = 'first_name';
                if ($key === 'middle name' || $key === 'middlename' || $key === 'middle_name') $key = 'middle_name';
                if ($key === 'last name' || $key === 'lastname' || $key === 'last_name') $key = 'last_name';
                if ($key === 'house no' || $key === 'house_no' || $key === 'house number' || $key === 'houseno') $key = 'house_no';
                if ($key === 'purok/street' || $key === 'purok' || $key === 'street') $key = 'purok';
                if ($key === 'birthdate' || $key === 'birth date' || $key === 'dob') $key = 'birthdate';
                if ($key === 'email') $key = 'email';
                if ($key === 'password') $key = 'password';
                if (isset($rowAssoc[$key])) $rowAssoc[$key] = $val;
            }
            $rows[] = $rowAssoc;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported file type. Upload .csv or .xlsx.']);
        exit;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to read file: ' . $e->getMessage()]);
    exit;
}

function gen_password($len = 10) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#%!?';
    $out = '';
    for ($i=0;$i<$len;$i++) $out .= $chars[random_int(0, strlen($chars)-1)];
    return $out;
}

$results = [
    'inserted' => 0,
    'skipped'  => 0,
    'errors'   => [], // ['row'=>N, 'email'=>..., 'reason'=>...]
    'created'  => []  // ['row'=>N, 'email'=>..., 'id'=>resident_id]
];

$seenEmails = [];
$rowNum = 1; // header row index
foreach ($rows as $r) {
    $rowNum++;

    $first = trim($r['first_name']);
    $middle = trim($r['middle_name']);
    $last = trim($r['last_name']);
    $email = trim($r['email']);
    $house_no = trim($r['house_no']);
    $purok = trim($r['purok']);
    $passPlain = $r['password'] !== '' ? $r['password'] : gen_password();
    $birthdate_raw = trim($r['birthdate'] ?? '');
    $birthdate_sql = null;
    $age_str = '';
    if ($birthdate_raw !== '') {
        // Try parse ISO Y-m-d first, otherwise fallback to strtotime
        $bd_dt = DateTime::createFromFormat('Y-m-d', $birthdate_raw);
        if (!$bd_dt) {
            $ts = strtotime($birthdate_raw);
            if ($ts === false) {
                $results['skipped']++;
                $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Invalid birthdate format'];
                continue;
            }
            $bd_dt = new DateTime(); $bd_dt->setTimestamp($ts);
        }
        $today = new DateTime('today');
        // Reject today/future and too young
        if ($bd_dt >= $today) {
            $results['skipped']++;
            $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Invalid birthdate (future)'];
            continue;
        }
        $age = $bd_dt->diff($today)->y;
        if ($age <= 6) {
            $results['skipped']++;
            $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Invalid birthdate (too young)'];
            continue;
        }
        $birthdate_sql = $bd_dt->format('Y-m-d');
        $age_str = (string)$age;
    }

    // Required fields
    if ($first === '' || $last === '' || $email === '' || $house_no === '' || $purok === '') {
        $results['skipped']++;
        $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Missing required fields'];
        continue;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $results['skipped']++;
        $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Invalid email format'];
        continue;
    }
    if (isset($seenEmails[strtolower($email)])) {
        $results['skipped']++;
        $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Duplicate email in file'];
        continue;
    }
    $seenEmails[strtolower($email)] = true;

    if (email_exists_any($conn, $email)) {
        $results['skipped']++;
        $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'Email already used'];
        continue;
    }

    $address = $house_no . ', ' . $purok . ', Barangay Panducot, Calumpit, Bulacan';
    $passHash = password_hash($passPlain, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO resident_info (first_name, middle_name, last_name, email, address, password, Birthdate, age, isVerify, isActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 1)");
    if (!$stmt) {
        $results['skipped']++;
        $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'DB prepare failed'];
        continue;
    }
    $stmt->bind_param('ssssssss', $first, $middle, $last, $email, $address, $passHash, $birthdate_sql, $age_str);
    if ($stmt->execute()) {
        $insertId = $stmt->insert_id;
        $results['inserted']++;
        $results['created'][] = ['row'=>$rowNum, 'email'=>$email, 'id'=>$insertId];
    } else {
        $results['skipped']++;
        $results['errors'][] = ['row'=>$rowNum, 'email'=>$email, 'reason'=>'DB insert failed'];
    }
    $stmt->close();
}

echo json_encode(['success'=>true, 'result'=>$results]);