<?php
// Use a single global session namespace for the application.
// Removed role-aware session_name switching to ensure the same session is available across all subpaths.

// Shared hosts often hide fatal errors (blank white page). Log them to a writable file.
// Note: Parse errors cannot be logged because PHP can't execute this file in that case.
$__bpamis_fatal_log = __DIR__ . '/../uploads/bpamis_php_fatal.log';
@ini_set('log_errors', '1');
@ini_set('error_log', $__bpamis_fatal_log);
if (!function_exists('__bpamis_register_fatal_logger')) {
    function __bpamis_register_fatal_logger($logFile)
    {
        register_shutdown_function(function () use ($logFile) {
            $err = error_get_last();
            if (!$err) {
                return;
            }
            $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($err['type'], $fatalTypes, true)) {
                return;
            }
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';
            $line = '[' . date('c') . '] ' . $uri . ' - ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
            @error_log($line);
        });
    }
}
__bpamis_register_fatal_logger($__bpamis_fatal_log);

// Defaults (works on local XAMPP). On hosting, override via environment variables.
// Supported env vars (pick one set):
// - BPAMIS_DB_HOST / BPAMIS_DB_NAME / BPAMIS_DB_USER / BPAMIS_DB_PASS
// - DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD (common convention)
$dbhost = getenv('BPAMIS_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('BPAMIS_DB_NAME') ?: getenv('DB_DATABASE') ?: 'barangay_case_management';
$dbuser = getenv('BPAMIS_DB_USER') ?: getenv('DB_USERNAME') ?: 'root';
$dbpass = getenv('BPAMIS_DB_PASS') ?: getenv('DB_PASSWORD') ?: '';

$conn = new mysqli($dbhost, $dbuser, $dbpass, $dbname);

if($conn->connect_errno){
    // Many hosts disable display_errors, so log the details server-side.
    error_log('server.php: DB connection failed (' . $conn->connect_errno . '): ' . $conn->connect_error);
    die("Connection failed.");
}

// Prefer utf8mb4 when possible
@$conn->set_charset('utf8mb4');

// DB compatibility helpers (mysqlnd-less hosts, case-sensitive table names)
require_once __DIR__ . '/../includes/db_compat.php';

// Ensure a session is available for scripts that rely on $_SESSION
// Only start the session if headers have not yet been sent. If headers are already
// sent, skip starting here and log so we can debug where output was emitted.
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        error_log('server.php: session_start skipped because headers have already been sent for ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
    }
}