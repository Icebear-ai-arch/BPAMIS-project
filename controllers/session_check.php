<?php
// controllers/session_check.php
// Returns JSON indicating whether any role-based session is currently active.

header('Content-Type: application/json');
// Prevent caching
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$possible_names = ['BPAMIS_RESIDENT','BPAMIS_EXTERNAL','BPAMIS_SEC','BPAMIS_OFFICIAL','BPAMIS_LUPONHEAD','BPAMIS_APP','PHPSESSID'];

function bpamis_is_logged_in() {
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['official_id']) || !empty($_SESSION['user'])) {
        return true;
    }
    return false;
}

function bpamis_role_redirect() {
    $role = strtolower($_SESSION['role'] ?? '');
    $redirect = '/BPAMIS/bpamis_website/bpamis.php';
    if ($role === 'resident') return '../ResidentMenu/home-resident.php';
    if ($role === 'external') return '../ExternalMenu/home-external.php';
    if ($role === 'official') {
        $pos = strtolower($_SESSION['official_position'] ?? '');
        if (strpos($pos, 'barangay secretary') !== false) return '../SecMenu/home-secretary.php';
        if (strpos($pos, 'lupon-hepe') !== false || strpos($pos, 'lupon head') !== false) return '../LuponHeadMenu/home-luponhead.php';
        if (strpos($pos, 'lupon tagapamayapa') !== false) return '../OfficialMenu/home-lupon.php';
        if (strpos($pos, 'barangay captain') !== false) return '../OfficialMenu/home-captain.php';
        return '../SecMenu/home-secretary.php';
    }
    return $redirect;
}

$result = ['logged_in' => false];

// Try to adopt any visible role-based session
foreach ($possible_names as $sname) {
    if (!empty($_COOKIE[$sname])) {
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        session_name($sname);
        @session_start();
        if (bpamis_is_logged_in()) {
            $result['logged_in'] = true;
            $result['redirect'] = bpamis_role_redirect();
            // Add display name for convenience
            $result['user'] = $_SESSION['username'] ?? $_SESSION['official_name'] ?? $_SESSION['user'] ?? '';
            echo json_encode($result);
            // Close before exit
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            exit;
        }
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
    }
}

echo json_encode($result);
exit;
?>
