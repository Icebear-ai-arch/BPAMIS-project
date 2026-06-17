<?php
// Start a minimal session; we'll migrate to a role-specific namespace after login
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$email = trim($_POST['login_user'] ?? '');
$password = $_POST['login_pass'] ?? '';
$isAjax = isset($_POST['ajax']);

$loginPage = '../bpamis_website/login.php';

$T_BARANGAY_OFFICIALS = bpamis_table($conn, 'barangay_officials');
$T_RESIDENT_INFO = bpamis_table($conn, 'resident_info');
$T_EXTERNAL_COMPLAINANT = bpamis_table($conn, 'external_complainant');

$TB_BARANGAY_OFFICIALS = bpamis_quote_table($T_BARANGAY_OFFICIALS);
$TB_RESIDENT_INFO = bpamis_quote_table($T_RESIDENT_INFO);
$TB_EXTERNAL_COMPLAINANT = bpamis_quote_table($T_EXTERNAL_COMPLAINANT);

// basic empty check
if (empty($email) || empty($password)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please enter both email and password.']);
        exit;
    }
    echo "<script>alert('Please enter both email and password.'); window.location.href='{$loginPage}';</script>";
    exit;
}

/*
 * We'll try these queries in order: officials, residents, external complainants.
 * Use COALESCE to support both 'password' and possible alternative column names like 'resident_password'.
 */
$queries = [
    // officials
    "SELECT official_id AS id, official_username AS username, email,isActive, COALESCE(password) AS password, `Name`, Position, 'official' AS user_role
    FROM {$TB_BARANGAY_OFFICIALS}
     WHERE email = ? LIMIT 1",
    // residents
    "SELECT resident_id AS id, resident_username AS username, email, isVerify, isActive, COALESCE(password) AS password, 'resident' AS user_role
    FROM {$TB_RESIDENT_INFO}
     WHERE email = ?  LIMIT 1",
    // external complainants
    "SELECT external_complaint_id AS id, external_username AS username, email, isActive, COALESCE(password) AS password, 'external' AS user_role
    FROM {$TB_EXTERNAL_COMPLAINANT}
     WHERE email = ? LIMIT 1"
];

$user = null;
$user_type_found = null;

foreach ($queries as $query) {
    $stmt = $conn->prepare($query);
if (!$stmt) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'SQL prepare failed: ' . $conn->error
        ]);
        exit;
    }
    die("Query prepare failed: " . $conn->error);
}

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $rows = bpamis_stmt_fetch_all_assoc($stmt);

    if (count($rows) === 1) {
        $user = $rows[0];
        $user_type_found = $user['user_role'] ?? null;
        $stmt->close();
        break;
    }
    $stmt->close();
}

// If no matching user found
if (!$user) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No account found with this email.']);
        exit;
    }
    echo "<script>alert('No account found with this email.'); window.location.href='{$loginPage}';</script>";
    exit;
}

// check if is user verify
if (
    ($user_type_found === 'resident' && ( (int)($user['isVerify'] ?? 0) === 0 || (int)($user['isActive'] ?? 0) === 0 )) ||
    ($user_type_found === 'official' && (int)($user['isActive'] ?? 0) === 0) ||
    ($user_type_found === 'external' && (int)($user['isActive'] ?? 0) === 0)
) {
     // Determine specific message for residents: prefer 'not verified' message if not verified, otherwise 'inactive'
    if ($user_type_found === 'resident') {
        if ((int)($user['isVerify'] ?? 0) === 0) {
            $msg = 'Your account is not Verify yet. Please wait for it to be verify';
        } elseif ((int)($user['isActive'] ?? 0) === 0) {
            $msg = 'Your account is inactive. Please contact or go to barangay for reactivation';
        } else {
            // fallback
            $msg = 'Your account is inactive. Please contact or go to barangay for reactivation';
        }
    } else {
        $msg = 'Your account is inactive. Please contact or go to barangay for reactivation';
    }

    $registerPage = '../bpamis_website/bpamis.php';

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'redirect' => $registerPage,
            'message' => $msg
        ]);
        exit;
    }

    // Normal (non-AJAX) redirect
    echo "<script>alert('{$msg}'); window.location.href='{$registerPage}';</script>";
    exit;
}

// Check password (note: $user['password'] should contain the hashed password)
if (!isset($user['password']) || !password_verify($password, $user['password'])) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }
    echo "<script>alert('Invalid email or password.'); window.location.href='{$loginPage}';</script>";
    exit;
}

// Prepare redirect (and target session namespace)
$redirect = $loginPage; // fallback
$_target_ns = 'BPAMIS_APP';

switch ($user_type_found) {
    case 'resident':
        $redirect = "../ResidentMenu/home-resident.php";
        $_target_ns = 'BPAMIS_RESIDENT';
        break;

    case 'external':
        $redirect = "../ExternalMenu/home-external.php";
        $_target_ns = 'BPAMIS_EXTERNAL';
        break;

    case 'official':
        $roleText = strtolower($user['Position'] ?? '');
        if (stripos($roleText, 'barangay secretary') !== false) {
            $redirect = "../SecMenu/home-secretary.php";
            $_target_ns = 'BPAMIS_SEC';
        } elseif (stripos($roleText, 'lupon-hepe') !== false || stripos($roleText, 'lupon head') !== false) {
            $redirect = "../LuponHeadMenu/home-luponhead.php";
            $_target_ns = 'BPAMIS_LUPONHEAD';
        } elseif (stripos($roleText, 'lupon tagapamayapa') !== false) {
            $redirect = "../OfficialMenu/home-lupon.php";
            $_target_ns = 'BPAMIS_OFFICIAL';
        } elseif (stripos($roleText, 'barangay captain') !== false) {
            $redirect = "../OfficialMenu/home-captain.php";
            $_target_ns = 'BPAMIS_OFFICIAL';
        } else {
            $redirect = "../SecMenu/home-secretary.php";
            $_target_ns = 'BPAMIS_SEC';
        }
        break;

    default:
        $redirect = $loginPage;
}

// Migrate to role-specific session namespace and set session data
// Close any previously opened default session to allow changing the name
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
if (!headers_sent()) { session_name($_target_ns); }
session_start();

// Common session data
$_SESSION['user'] = $user['email'];
$_SESSION['username'] = $user['username'] ?? '';
$_SESSION['user_id'] = $user['id'] ?? '';
$_SESSION['role'] = $user_type_found;

// Role-specific
if ($user_type_found === 'official') {
    $_SESSION['official_id'] = $user['id'] ?? '';
    $_SESSION['official_name'] = $user['Name'] ?? '';
    $_SESSION['official_position'] = $user['Position'] ?? '';
}

// Ajax vs normal redirect
if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'redirect' => $redirect]);
    exit;
}

header("Location: $redirect");
exit;
?>
