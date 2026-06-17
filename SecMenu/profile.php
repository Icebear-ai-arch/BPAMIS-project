<?php
include '../controllers/session_control.php';
include '../server/server.php';

// If this is an AJAX POST (ajax_* params) and the session is invalid, return a JSON error
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjaxRequest = false;
    foreach ($_POST as $k => $v) {
        if (is_string($k) && strpos($k, 'ajax_') === 0) { $isAjaxRequest = true; break; }
    }
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'official' || !isset($_SESSION['official_id']) || !is_numeric($_SESSION['official_id'])) {
            echo json_encode(['success' => false, 'message' => 'Not authenticated or session expired. Please log in.']);
            exit;
        }
    }
}

// Enforce Secretary-only access within Official namespace
$loginPage = '../bpamis_website/login.php';
// Must be logged in as an official and have a valid official_id
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'official') {
    header("Location: {$loginPage}");
    exit();
}
// Current logged-in official id
$user_id = (int)($_SESSION['official_id'] ?? 0);
// AJAX handler for password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_change_password'])) {
    header('Content-Type: application/json');
    $current_pw = $_POST['current_password'] ?? '';
    $new_pw = $_POST['new_password'] ?? '';
    $confirm_pw = $_POST['confirm_password'] ?? '';
    $response = ['success' => false, 'message' => ''];

    $pw_query = "SELECT password FROM barangay_officials WHERE Official_ID = ?";
    $stmt = $conn->prepare($pw_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    $row = $result->fetch_assoc();
    $hashed_pw = $row['password'] ?? '';

    if (!password_verify($current_pw, $hashed_pw)) {
        $response['message'] = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 6) {
        $response['message'] = 'New password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm_pw) {
        $response['message'] = 'New password and confirm password do not match.';
    } else {
        $new_hashed = password_hash($new_pw, PASSWORD_DEFAULT);
        $update_pw_query = "UPDATE barangay_officials SET password=? WHERE Official_ID=?";
        $stmt = $conn->prepare($update_pw_query);
        $stmt->bind_param("si", $new_hashed, $user_id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Password updated successfully!';
        } else {
            $response['message'] = 'Failed to update password. Please try again.';
        }
    }
    echo json_encode($response);
    exit;
}

// AJAX: Get Barangay Info settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_barangay_info'])) {
    header('Content-Type: application/json');
    try {
        // Ensure table exists
        $conn->query("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)");

        $keys = [
            'stats_total_families', 'stats_total_seniors', 'stats_total_children', 'stats_total_adults', 'stats_total_population',
            'about_role_description', 'bpamis_adoption_percent',
            'community_total_pwd', 'community_total_seniors', 'community_total_economic', 'community_total_women', 'community_total_children'
        ];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $types = str_repeat('s', count($keys));
        $stmt = $conn->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
        $stmt->bind_param($types, ...$keys);
        $stmt->execute();
        $res = bpamis_stmt_get_result($stmt);
        $data = array_fill_keys($keys, '');
        while ($row = $res->fetch_assoc()) {
            $data[$row['setting_key']] = $row['setting_value'];
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } catch (Exception $e) {
        error_log('ajax_get_barangay_info error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load barangay info.']);
    }
    exit;
}

// AJAX: Save Barangay Info settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_save_barangay_info'])) {
    header('Content-Type: application/json');
    try {
        // Ensure table exists
        $conn->query("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)");

        // Collect and sanitize inputs
        $fields = [
            'stats_total_families' => (string)max(0, (int)($_POST['stats_total_families'] ?? 0)),
            'stats_total_seniors' => (string)max(0, (int)($_POST['stats_total_seniors'] ?? 0)),
            'stats_total_children' => (string)max(0, (int)($_POST['stats_total_children'] ?? 0)),
            'stats_total_adults' => (string)max(0, (int)($_POST['stats_total_adults'] ?? 0)),
            'stats_total_population' => (string)max(0, (int)($_POST['stats_total_population'] ?? 0)),
            'about_role_description' => trim($_POST['about_role_description'] ?? ''),
            'bpamis_adoption_percent' => (string)max(0, (float)($_POST['bpamis_adoption_percent'] ?? 0)),
            'community_total_pwd' => (string)max(0, (int)($_POST['community_total_pwd'] ?? 0)),
            'community_total_seniors' => (string)max(0, (int)($_POST['community_total_seniors'] ?? 0)),
            'community_total_economic' => (string)max(0, (int)($_POST['community_total_economic'] ?? 0)),
            'community_total_women' => (string)max(0, (int)($_POST['community_total_women'] ?? 0)),
            'community_total_children' => (string)max(0, (int)($_POST['community_total_children'] ?? 0)),
        ];

        // Upsert settings
        $stmt = $conn->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($fields as $key => $val) {
            $stmt->bind_param('ss', $key, $val);
            $stmt->execute();
        }
        echo json_encode(['success' => true, 'message' => 'Barangay info saved successfully.']);
    } catch (Exception $e) {
        error_log('ajax_save_barangay_info error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to save barangay info.']);
    }
    exit;
}

// AJAX: Get all case types
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_get_case_types'])) {
    header('Content-Type: application/json');
    try {
        // Ensure table exists (create with lowercase column by default if missing)
        $tableCheck = $conn->query("SHOW TABLES LIKE 'case_types'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS case_types (
                Type_ID INT AUTO_INCREMENT PRIMARY KEY,
                Case_Type VARCHAR(191) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        // Prefer Case_Type column if present (many installs use this); fall back to case_type
        $ct_col = 'Case_Type';
        $colCheck = $conn->query("SHOW COLUMNS FROM case_types LIKE 'Case_Type'");
        if (!$colCheck || $colCheck->num_rows === 0) {
            $colCheck2 = $conn->query("SHOW COLUMNS FROM case_types LIKE 'case_type'");
            if ($colCheck2 && $colCheck2->num_rows > 0) {
                $ct_col = 'case_type';
            }
        }

        // Query using the detected column, aliasing to 'case_type' for PHP consistency
        $sql = "SELECT Type_ID, `" . $ct_col . "` AS case_type FROM case_types ORDER BY `" . $ct_col . "` ASC";
        $result = $conn->query($sql);
        if ($result === false) {
            error_log('ajax_get_case_types DB error: ' . $conn->error . " -- SQL: " . $sql);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
            exit;
        }

        $caseTypes = [];
        $rowCount = 0;
        while ($row = $result->fetch_assoc()) {
            $caseTypes[] = [
                'id' => (int)$row['Type_ID'],
                'name' => $row['case_type']
            ];
            $rowCount++;
        }

        // TEMP DEBUG: include detected column and SQL so client can report exact server behavior
        echo json_encode([
            'success' => true,
            'caseTypes' => $caseTypes,
            'detected_column' => $ct_col,
            'sql' => $sql,
            'rows' => $rowCount
        ]);
    } catch (Exception $e) {
        error_log('ajax_get_case_types error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX: Add new case type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_add_case_type'])) {
    header('Content-Type: application/json');
    try {
        $caseTypeName = trim($_POST['case_type_name'] ?? '');
        if (empty($caseTypeName)) {
            echo json_encode(['success' => false, 'message' => 'Case type name is required.']);
            exit;
        }

        // Ensure table exists (prefer Case_Type column)
        $tableCheck = $conn->query("SHOW TABLES LIKE 'case_types'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            $conn->query("CREATE TABLE IF NOT EXISTS case_types (
                Type_ID INT AUTO_INCREMENT PRIMARY KEY,
                Case_Type VARCHAR(191) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }

        // Detect column name to use for INSERT (prefer Case_Type)
        $ct_col = 'Case_Type';
        $colCheck = $conn->query("SHOW COLUMNS FROM case_types LIKE 'Case_Type'");
        if (!$colCheck || $colCheck->num_rows === 0) {
            $colCheck2 = $conn->query("SHOW COLUMNS FROM case_types LIKE 'case_type'");
            if ($colCheck2 && $colCheck2->num_rows > 0) {
                $ct_col = 'case_type';
            }
        }

        $sql = "INSERT INTO case_types (`" . $ct_col . "`) VALUES (?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('s', $caseTypeName);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Case type added successfully.', 'id' => $conn->insert_id]);
            } else {
                if ($conn->errno === 1062) { // Duplicate entry
                    echo json_encode(['success' => false, 'message' => 'This case type already exists.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add case type: ' . $conn->error]);
                }
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
        }
    } catch (Exception $e) {
        error_log('ajax_add_case_type error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX: Edit case type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_edit_case_type'])) {
    header('Content-Type: application/json');
    try {
        $typeId = (int)($_POST['type_id'] ?? 0);
        $newName = trim($_POST['case_type_name'] ?? '');
        
        if ($typeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid case type ID.']);
            exit;
        }
        if (empty($newName)) {
            echo json_encode(['success' => false, 'message' => 'Case type name is required.']);
            exit;
        }
        
    // Detect column name to use for UPDATE (prefer Case_Type)
    $ct_col = 'Case_Type';
    $colCheck = $conn->query("SHOW COLUMNS FROM case_types LIKE 'Case_Type'");
    if (!$colCheck || $colCheck->num_rows === 0) {
        $colCheck2 = $conn->query("SHOW COLUMNS FROM case_types LIKE 'case_type'");
        if ($colCheck2 && $colCheck2->num_rows > 0) {
            $ct_col = 'case_type';
        }
    }

    $stmt = $conn->prepare("UPDATE case_types SET `" . $ct_col . "` = ? WHERE Type_ID = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('si', $newName, $typeId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Case type updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No changes made or case type not found.']);
            }
        } else {
            if ($conn->errno === 1062) { // Duplicate entry
                echo json_encode(['success' => false, 'message' => 'This case type name already exists.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update: ' . $stmt->error]);
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log('ajax_edit_case_type error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX: Delete case type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_case_type'])) {
    header('Content-Type: application/json');
    try {
        $typeId = (int)($_POST['type_id'] ?? 0);
        
        if ($typeId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid case type ID.']);
            exit;
        }
        
        $stmt = $conn->prepare("DELETE FROM case_types WHERE Type_ID = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare statement: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param('i', $typeId);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Case type deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Case type not found.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete: ' . $stmt->error]);
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log('ajax_delete_case_type error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get user data from session
$user_name = $_SESSION['official_name'] ?? 'User Name';
$user_email = $_SESSION['user'] ?? 'user@example.com';
$user_role = 'Barangay Secretary'; // Default role for secretary

// Handle Edit Profile form submission
$profile_success = false;
$profile_error = '';
// Birthdate for display (DB column: `birthdate`)
$birthdate = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address_in = trim($_POST['address'] ?? '');
    $birthdate_in = trim($_POST['birthdate'] ?? '');
    $new_email = trim($_POST['new_email'] ?? '');
    $full_name = $first_name . ' ' . $last_name;
    // Duty schedule fields
    $duty_days_in = trim($_POST['duty_days'] ?? '');
    $duty_time_in_in = trim($_POST['duty_time_in'] ?? '');
    $duty_time_out_in = trim($_POST['duty_time_out'] ?? '');

    // Determine the primary email to store. Ignore posted readonly `email` field
    // and fetch the current email from the DB so we only change it when
    // the user provides a non-empty `new_email`.
    $primary_email = '';
    $stmtCur = $conn->prepare("SELECT email FROM barangay_officials WHERE Official_ID = ?");
    if ($stmtCur) {
        $stmtCur->bind_param('i', $user_id);
        $stmtCur->execute();
        $resCur = bpamis_stmt_get_result($stmtCur);
        if ($resCur && $resCur->num_rows > 0) {
            $rowCur = $resCur->fetch_assoc();
            $primary_email = trim((string)($rowCur['email'] ?? ''));
        }
        $stmtCur->close();
    } else {
        // fallback to posted value
        $primary_email = trim((string)$email);
    }

    if (!empty($new_email)) {
        $new_email_trim = trim($new_email);
        if (!filter_var($new_email_trim, FILTER_VALIDATE_EMAIL)) {
            $profile_error = 'Please enter a valid email address.';
        } else {
            // Normalize to lowercase for comparison
            $candidate = strtolower($new_email_trim);
            try {
                // 1) Check other officials (exclude current Official_ID)
                $stmtCheck = $conn->prepare("SELECT Official_ID FROM barangay_officials WHERE Official_ID != ? AND FIND_IN_SET(?, LOWER(REPLACE(email, ' ', ''))) > 0 LIMIT 1");
                if ($stmtCheck) {
                    $stmtCheck->bind_param('is', $user_id, $candidate);
                    $stmtCheck->execute();
                    $res = bpamis_stmt_get_result($stmtCheck);
                    if ($res && $res->num_rows > 0) {
                        $profile_error = 'This email address is already used by another official.';
                    }
                    $stmtCheck->close();
                } else {
                    $profile_error = 'Failed to verify email uniqueness. Please try again.';
                }

                // 2) Check residents
                if ($profile_error === '') {
                    $stmtRes = $conn->prepare("SELECT resident_id FROM resident_info WHERE FIND_IN_SET(?, LOWER(REPLACE(email, ' ', ''))) > 0 LIMIT 1");
                    if ($stmtRes) {
                        $stmtRes->bind_param('s', $candidate);
                        $stmtRes->execute();
                        $rres = bpamis_stmt_get_result($stmtRes);
                        if ($rres && $rres->num_rows > 0) {
                            $profile_error = 'This email address is already used by a resident account.';
                        }
                        $stmtRes->close();
                    } else {
                        $profile_error = 'Failed to verify email uniqueness. Please try again.';
                    }
                }

                // 3) Check external complainants
                if ($profile_error === '') {
                    $stmtExt = $conn->prepare("SELECT external_complaint_id FROM external_complainant WHERE FIND_IN_SET(?, LOWER(REPLACE(email, ' ', ''))) > 0 LIMIT 1");
                    if ($stmtExt) {
                        $stmtExt->bind_param('s', $candidate);
                        $stmtExt->execute();
                        $er = bpamis_stmt_get_result($stmtExt);
                        if ($er && $er->num_rows > 0) {
                            $profile_error = 'This email address is already used by an external complainant account.';
                        }
                        $stmtExt->close();
                    } else {
                        $profile_error = 'Failed to verify email uniqueness. Please try again.';
                    }
                }

                // If still no error, accept candidate as primary email
                if ($profile_error === '') {
                    $primary_email = $new_email_trim;
                }
            } catch (Exception $e) {
                error_log('Email uniqueness check error: ' . $e->getMessage());
                if ($profile_error === '') $profile_error = 'Failed to verify email uniqueness. Please try again.';
            }
        }
    }

    // Final email to store (primary only)
    $email = $primary_email;

    // Normalize contact: treat 'N/A' as empty
    if (strtolower($contact) === 'n/a') {
        $contact = '';
    }

    // Server-side Philippine mobile validation (allow empty). Accept 09XXXXXXXXX or +639XXXXXXXXX
    if ($contact !== '' && !preg_match('/^(09\d{9}|\+639\d{9})$/', $contact)) {
        $profile_error = 'Please enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567).';
    }

    // Validate birthdate if provided (expect YYYY-MM-DD)
    if ($birthdate_in !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $birthdate_in);
        if (!$dt || $dt->format('Y-m-d') !== $birthdate_in) {
            $profile_error = 'Please enter a valid birthdate (YYYY-MM-DD).';
        }
    }

    // Enforce minimum age (7 years) if birthdate is valid
    if ($birthdate_in !== '' && $profile_error === '') {
        $dt2 = DateTime::createFromFormat('Y-m-d', $birthdate_in);
        if ($dt2 && $dt2->format('Y-m-d') === $birthdate_in) {
            $age = (int)$dt2->diff(new DateTime())->y;
            if ($age < 7) {
                $profile_error = 'You must be at least 7 years old.';
            }
        }
    }

    // Basic validation and update only if no errors
    if ($profile_error === '') {
        // Email is optional here — if empty we keep existing value from DB
        if ($first_name && $last_name) {
            $update_query = "UPDATE barangay_officials SET Name=?, email=?, Contact_Number=?, Birthdate=? WHERE Official_ID=?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssi", $full_name, $email, $contact, $birthdate_in, $user_id);
            if ($stmt->execute()) {
                $profile_success = true;
                // Update session values
                $_SESSION['official_name'] = $full_name;
                $_SESSION['user'] = $email;
                // Save address in per-user profile table
                try {
                    $conn->query("CREATE TABLE IF NOT EXISTS official_profile (\n                    Official_ID INT PRIMARY KEY,\n                    address VARCHAR(255)\n                )");
                    $stmtAddr = $conn->prepare("INSERT INTO official_profile (Official_ID, address) VALUES (?, ?)\n                    ON DUPLICATE KEY UPDATE address=VALUES(address)");
                    $stmtAddr->bind_param('is', $user_id, $address_in);
                    $stmtAddr->execute();
                } catch (Exception $e) {
                    error_log('Failed to save address: ' . $e->getMessage());
                }
                // Save duty schedule (create table if not exists, upsert per user)
                try {
                    $conn->query("CREATE TABLE IF NOT EXISTS official_schedule (\n                    Official_ID INT PRIMARY KEY,\n                    duty_days VARCHAR(100),\n                    time_in VARCHAR(10),\n                    time_out VARCHAR(10)\n                )");
                    // If empty inputs, keep previous values by loading them first
                    $current_days = $duty_days_in;
                    $current_in = $duty_time_in_in;
                    $current_out = $duty_time_out_in;
                    // Basic normalization for time inputs (HH:MM)
                    $norm = function($t) { $t = trim($t); return $t; };
                    $current_in = $norm($current_in);
                    $current_out = $norm($current_out);

                    $stmtSch = $conn->prepare("INSERT INTO official_schedule (Official_ID, duty_days, time_in, time_out) VALUES (?,?,?,?)\n                    ON DUPLICATE KEY UPDATE duty_days=VALUES(duty_days), time_in=VALUES(time_in), time_out=VALUES(time_out)");
                    $stmtSch->bind_param('isss', $user_id, $current_days, $current_in, $current_out);
                    $stmtSch->execute();
                } catch (Exception $e) {
                    error_log('Failed to save duty schedule: ' . $e->getMessage());
                }
                // Reflect saved values in page variables for immediate display
                if (!empty($duty_days_in)) $duty_days = $duty_days_in;
                if (!empty($duty_time_in_in)) $duty_time_in = $duty_time_in_in;
                if (!empty($duty_time_out_in)) $duty_time_out = $duty_time_out_in;
                $address = ($address_in !== '') ? $address_in : $address;
                if ($birthdate_in !== '') $birthdate = $birthdate_in;
                // Use Post-Redirect-Get to avoid browser resubmission prompts and ensure fresh GET load
                // Redirect back to this page so the updated session/DB values are displayed via GET
                header('Location: profile.php?updated=1');
                exit();
            } else {
                $profile_error = 'Failed to update profile. Please try again.';
            }
        } else {
            $profile_error = 'Please fill in all required fields.';
        }
    }
}

// Fetch user data from database
$user_data = null;
$contact_number = '';
$address = '';
$member_since = '';

try {
    // Query to get user information from barangay_officials table
    $query = "SELECT * FROM barangay_officials WHERE Official_ID = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    
    if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $user_name = $user_data['Name'] ?? $user_name;
    $user_email = $user_data['email'] ?? $user_email;
    $user_role = $user_data['Position'] ?? $user_role;
    $contact_number = trim((string)($user_data['Contact_Number'] ?? ''));
    if ($contact_number === '') { $contact_number = 'N/A'; }
    // Load birthdate from DB (column `birthdate` preferred)
    $birthdate = $user_data['birthdate'] ?? ($user_data['Birthdate'] ?? ($user_data['Birthday'] ?? ''));
        
        // Set member since date (you can add a created_at field to the database if needed)
        $member_since = date('M Y');
        
        // Address will be loaded from official_profile table below
    }
} catch (Exception $e) {
    // Handle database errors gracefully
    error_log("Database error: " . $e->getMessage());
}

// Load address from per-user profile table (if available)
if (!isset($address) || $address === '') { $address = 'N/A'; }
try {
    $conn->query("CREATE TABLE IF NOT EXISTS official_profile (\n        Official_ID INT PRIMARY KEY,\n        address VARCHAR(255)\n    )");
    $stmtAddr = $conn->prepare("SELECT address FROM official_profile WHERE Official_ID=?");
    $stmtAddr->bind_param('i', $user_id);
    $stmtAddr->execute();
    $resAddr = bpamis_stmt_get_result($stmtAddr);
    if ($resAddr && $resAddr->num_rows > 0) {
        $rowAddr = $resAddr->fetch_assoc();
        $addrVal = trim((string)($rowAddr['address'] ?? ''));
        if ($addrVal !== '') { $address = $addrVal; }
    }
} catch (Exception $e) {
    error_log('Address load error: ' . $e->getMessage());
}

// Duty schedule variables and loader
$duty_days = 'Mon-Fri';
$duty_time_in = '08:00';
$duty_time_out = '17:00';
try {
    $conn->query("CREATE TABLE IF NOT EXISTS official_schedule (\n        Official_ID INT PRIMARY KEY,\n        duty_days VARCHAR(100),\n        time_in VARCHAR(10),\n        time_out VARCHAR(10)\n    )");
    $stmtSch = $conn->prepare("SELECT duty_days, time_in, time_out FROM official_schedule WHERE Official_ID=?");
    $stmtSch->bind_param('i', $user_id);
    $stmtSch->execute();
    $resSch = bpamis_stmt_get_result($stmtSch);
    if ($resSch && $resSch->num_rows > 0) {
        $rowSch = $resSch->fetch_assoc();
        if (!empty($rowSch['duty_days'])) $duty_days = $rowSch['duty_days'];
        if (!empty($rowSch['time_in'])) $duty_time_in = $rowSch['time_in'];
        if (!empty($rowSch['time_out'])) $duty_time_out = $rowSch['time_out'];
    }
} catch (Exception $e) {
    error_log('Duty schedule load error: ' . $e->getMessage());
}

// Helper to format time to 12-hour label for display
if (!function_exists('format_time_label')) {
    function format_time_label($t) {
        $t = trim((string)$t);
        if ($t === '') return '';
        // If already has AM/PM, return as-is
        if (preg_match('/[a-zA-Z]/', $t)) return $t;
        $dt = DateTime::createFromFormat('H:i', $t);
        if (!$dt) $dt = DateTime::createFromFormat('H:i:s', $t);
        if ($dt) return $dt->format('g:i A');
        return $t;
    }
}

// Derive start/end days for dropdowns from current duty_days
$duty_start = 'Mon';
$duty_end = 'Fri';
$__dd_parts = explode('-', (string)$duty_days, 2);
if (!empty($__dd_parts[0])) $duty_start = trim($__dd_parts[0]);
if (!empty($__dd_parts[1])) $duty_end = trim($__dd_parts[1]);

// Get user statistics from database
$stats = [
    'cases_handled' => 0,
    'resolved' => 0,
    'pending' => 0 // This will now mean pending complaints
];

try {
    // Cases handled and resolved (same as before, but grouped)
    $caseQuery = "SELECT Case_Status as status, COUNT(*) as count FROM case_info GROUP BY Case_Status";
    $result = $conn->query($caseQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $status = strtolower(trim($row['status']));
            $count = (int)$row['count'];
            $stats['cases_handled'] += $count;
            if ($status === 'resolved' || $status === 'closed') {
                $stats['resolved'] += $count;
            }
        }
    }

    // Pending complaints
    $pendingComplaintsQuery = "SELECT COUNT(*) as count FROM complaint_info WHERE LOWER(status) = 'pending'";
    $pendingResult = $conn->query($pendingComplaintsQuery);
    if ($pendingResult) {
        $stats['pending'] = (int)$pendingResult->fetch_assoc()['count'];
    }
} catch (Exception $e) {
    error_log("Statistics query error: " . $e->getMessage());
}

// Recent activity: ongoing cases for the entire current month
$firstDayOfMonth = date('Y-m-01');
$lastDayOfMonth = date('Y-m-t');

$recent_activities = [];
try {
    // Select cases that are ongoing during the current month window.
    // Criteria: opened on/before last day, and not closed before first day; exclude resolved/closed statuses.
    $ongoingQuery = "
        SELECT Case_ID, Case_Status, Date_Opened, Date_Closed
        FROM case_info
        WHERE Date_Opened <= ?
          AND (Date_Closed IS NULL OR Date_Closed >= ?)
          AND (LOWER(Case_Status) NOT IN ('resolved','closed'))
        ORDER BY Date_Opened DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($ongoingQuery);
    $stmt->bind_param("ss", $lastDayOfMonth, $firstDayOfMonth);
    $stmt->execute();
    $ongoingResult = bpamis_stmt_get_result($stmt);
    while ($row = $ongoingResult->fetch_assoc()) {
        $opened = $row['Date_Opened'] ?? '';
        if (!$opened || $opened === '0000-00-00' || strtotime($opened) === false) {
            $opened = date('Y-m-d');
        }
        $status = $row['Case_Status'] ?? 'Ongoing';
        $recent_activities[] = [
            'type' => 'Ongoing',
            'description' => 'Case #' . $row['Case_ID'] . ' is ongoing (' . $status . ')',
            'date' => $opened,
            'link' => 'home-secretary.php#calendar'
        ];
    }
} catch (Exception $e) {
    error_log("Recent ongoing cases query error: " . $e->getMessage());
}

// Avatar choices (define at the top so it's always available)
$avatar_choices = [
    '../Assets/Img/avatar1.jpg',
    '..//Assets/Img/avatar2.jpg',
];

// Preload barangay info settings for immediate client-side fill
$__barangay_keys = [
    'stats_total_families', 'stats_total_seniors', 'stats_total_children', 'stats_total_adults', 'stats_total_population',
    'about_role_description', 'bpamis_adoption_percent',
    'community_total_pwd', 'community_total_seniors', 'community_total_economic', 'community_total_women', 'community_total_children'
];
$__barangay_info_preload = array_fill_keys($__barangay_keys, '');
try {
    $conn->query("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(100) PRIMARY KEY, setting_value TEXT)");
    $placeholders = implode(',', array_fill(0, count($__barangay_keys), '?'));
    $types = str_repeat('s', count($__barangay_keys));
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($placeholders)");
    $stmt->bind_param($types, ...$__barangay_keys);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    while ($row = $res->fetch_assoc()) {
        $__barangay_info_preload[$row['setting_key']] = $row['setting_value'];
    }
    // Apply sensible defaults if DB lacks values, per user's provided figures
    if (empty($__barangay_info_preload['stats_total_families'])) $__barangay_info_preload['stats_total_families'] = '1084';
    if (empty($__barangay_info_preload['stats_total_seniors'])) $__barangay_info_preload['stats_total_seniors'] = '522';
    // Children: sum of 0-17 (34 + 67 + 115 + 304 + 157) = 677
    if (empty($__barangay_info_preload['stats_total_children'])) $__barangay_info_preload['stats_total_children'] = '677';
    // Adults: 18-59 = 1973
    if (empty($__barangay_info_preload['stats_total_adults'])) $__barangay_info_preload['stats_total_adults'] = '1973';
    // Population: 3,318
    if (empty($__barangay_info_preload['stats_total_population'])) $__barangay_info_preload['stats_total_population'] = '3318';
    // BPAMIS adoption percent: 79.86
    if (empty($__barangay_info_preload['bpamis_adoption_percent'])) $__barangay_info_preload['bpamis_adoption_percent'] = '79.86';
    // About/Role description default content (prefilled but editable)
    if (empty($__barangay_info_preload['about_role_description'])) {
        $__barangay_info_preload['about_role_description'] = <<<TEXT
Barangay Panducot as thriving community in Calumpit, Bulacan, stands as a beacon of peace, unity, and progressive governance. Our barangay values efficient public service and transparent community leadership that serves every resident with dedication and integrity.

As our community grows and local concerns evolve, Barangay Panducot embraces innovation through BPAMIS (Barangay Panducot Adjudication Management Information System). This cutting-edge digital platform revolutionizes how we handle community disputes, streamline case filing, track resolutions, and ensure fair, transparent mediation for all residents.

With BPAMIS, we're not just modernizing our processes—we're strengthening the foundation of justice at the grassroots level, making legal services more accessible, efficient, and responsive to our community's needs.
TEXT;
    }
} catch (Exception $e) {
    // ignore preload errors; fields will be blank and AJAX will try to load
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>Profile - BPAMIS</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        // Expose initial barangay info values to JS for immediate prefill
        window.__BARANGAY_INFO__ = <?php echo json_encode($__barangay_info_preload, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    </script>
    <script>
        // Expose profile form submit feedback (error/success) to JS so we can render inside Edit Profile container
        window.__PROFILE_FEEDBACK__ = <?php echo json_encode([
            'error' => $profile_error,
            'success' => $profile_success
        ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>;
    </script>
    <script>
        // Expose previous POST data (if any) so we can prefill fields after a failed submit
        // Only expose a small safe subset to avoid leaking sensitive data (passwords etc).
        window.__PROFILE_FORM__ = <?php
            $safe_post = [];
                foreach (['new_email','email','first_name','last_name','contact','address','birthdate'] as $k) {
                    if (isset($_POST[$k])) $safe_post[$k] = (string)$_POST[$k];
                }
                echo json_encode($safe_post, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT);
        ?>;
    </script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            200: '#bae2fd',
                            300: '#7cccfd',
                            400: '#36b3f9',
                            500: '#0c9ced',
                            600: '#0281d4',
                            700: '#026aad',
                            800: '#065a8f',
                            900: '#0a4b76'
                        }
                    },
                    fontFamily: {
                        'poppins': ['Poppins', 'sans-serif']
                    },
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                        'gradient': 'gradient 15s ease infinite',
                        'shine': 'shine 3s infinite linear',
                        'particle': 'particle-float 3s ease-in-out infinite alternate',
                        'icon-light': 'icon-light 4s infinite',
                        'fade-in': 'fadeIn 0.6s ease-out',
                        'slide-up': 'slideUp 0.8s ease-out',
                        'scale-in': 'scaleIn 0.5s ease-out'
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        gradient: {
                            '0%, 100%': { backgroundPosition: '0% 50%' },
                            '50%': { backgroundPosition: '100% 50%' }
                        },
                        shine: {
                            '0%': { backgroundPosition: '200% 0' },
                            '100%': { backgroundPosition: '-200% 0' }
                        },
                        'particle-float': {
                            '0%': { transform: 'translateY(0) translateX(0)' },
                            '100%': { transform: 'translateY(-10px) translateX(10px)' }
                        },
                        'icon-light': {
                            '0%': { left: '-150%' },
                            '100%': { left: '150%' }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { opacity: '0', transform: 'translateY(30px)' },
                            '100%': { opacity: '1', transform: 'translateY(0)' }
                        },
                        scaleIn: {
                            '0%': { opacity: '0', transform: 'scale(0.9)' },
                            '100%': { opacity: '1', transform: 'scale(1)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%);
        }
        
        .premium-gradient {
            background: linear-gradient(135deg, #667eea 0%, #4b5fa2ad 100%);
        }
        
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .premium-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 255, 255, 0.7) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .premium-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.85) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .profile-avatar {
            background: linear-gradient(135deg, #667eea 0%, #4b5fa2ad 100%);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }
        
        .animated-border {
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.2));
            background-size: 200% 100%;
            animation: shine 3s infinite linear;
        }
        
        .floating-icon {
            animation: float 3s ease-in-out infinite;
        }
        
        .premium-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .premium-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        .premium-button:hover::before {
            left: 100%;
        }
        
        .premium-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .activity-item {
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            transform: translateX(5px);
            background: rgba(102, 126, 234, 0.05);
        }
        
        .premium-input {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .premium-input:focus {
            background: rgba(255, 255, 255, 1);
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #e5e7eb;
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .toggle-switch::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 2px;
            width: 26px;
            height: 26px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .toggle-switch.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .toggle-switch.active::after {
            transform: translateX(30px);
        }

        /* Mobile optimizations: compact profile hero section */
        @media (max-width: 640px) {
            /* Preserve sidebar font sizes - must come first to avoid being overridden */
            #sidebar, #sidebar *, 
            #sidebar p, #sidebar span, #sidebar label, #sidebar div,
            #sidebar button, #sidebar a, #sidebar h1, #sidebar h2, #sidebar h3, #sidebar h4,
            #sidebar input, #sidebar select, #sidebar textarea,
            #sidebar i.fas, #sidebar i.far, #sidebar i.fa {
                font-size: inherit !important;
            }
            
            /* Hero section card - remove padding to hide white background */
            .mb-8 .premium-card {
                border-radius: 1.5rem !important;
                padding: 0 !important;
            }
            
            /* Hero section container - add top margin */
            .mb-8 {
                margin-top: 0.5rem !important;
            }
            
            /* Hero section - tighter padding */
            .premium-gradient {
                padding: 0.75rem !important;
                border-radius: 1.5rem !important;
            }
            
            /* Profile avatar - smaller on mobile */
            .profile-avatar {
                width: 4rem !important;
                height: 4rem !important;
                border-width: 2px !important;
            }
            
            .profile-avatar img {
                width: 3.5rem !important;
                height: 3.5rem !important;
            }
            
            /* Hero text - smaller and tighter spacing */
            .premium-gradient h1 {
                font-size: 1.1rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .premium-gradient p {
                font-size: 0.75rem !important;
                margin-bottom: 0.25rem !important;
            }
            
            .premium-gradient .space-y-1 {
                margin-top: 0.25rem !important;
            }
            
            .premium-gradient .space-y-1 > * + * {
                margin-top: 0.15rem !important;
            }
            
            /* Floating background icons - smaller */
            .premium-gradient .floating-icon {
                width: 1.5rem !important;
                height: 1.5rem !important;
            }
            
            /* Align animated background to card border radius on mobile */
            .premium-gradient .absolute.inset-0 {
                border-radius: 1.5rem !important;
                overflow: hidden;
            }
            
            /* Hero section container - less margin */
            .mb-8 {
                margin-bottom: 1rem !important;
            }
            
            /* Main content padding */
            main {
                padding: 0.5rem !important;
                margin-left: 0.5rem !important;
                margin-right: 0.5rem !important;
            }
            
            /* Cards - tighter padding and margins */
            .premium-card {
                padding: 0.75rem !important;
                margin-bottom: 0.75rem !important;
            }
            
            /* Grid gaps */
            .grid {
                gap: 0.5rem !important;
            }
            
            /* Space-y utilities */
            .space-y-3 > * + *, .space-y-4 > * + *, .space-y-6 > * + *, .space-y-8 > * + * {
                margin-top: 0.5rem !important;
            }
            
            /* Global font size - exclude sidebar */
            main p, main span, main label, main div:not(#sidebar):not(#sidebar *) {
                font-size: 0.7rem !important;
            }
            
            /* Headings - exclude sidebar */
            main h2:not(#sidebar h2):not(#sidebar * h2) { font-size: 0.95rem !important; }
            main h3:not(#sidebar h3):not(#sidebar * h3) { font-size: 0.85rem !important; }
            main h4:not(#sidebar h4):not(#sidebar * h4) { font-size: 0.75rem !important; }
            
            /* Buttons - exclude sidebar */
            main button:not(#sidebar button):not(#sidebar * button) {
                font-size: 0.7rem !important;
                padding: 0.4rem 0.6rem !important;
            }
            
            /* Inputs - exclude sidebar */
            main input:not(#sidebar input):not(#sidebar * input), 
            main select:not(#sidebar select):not(#sidebar * select), 
            main textarea:not(#sidebar textarea):not(#sidebar * textarea) {
                font-size: 0.7rem !important;
                padding: 0.4rem 0.6rem !important;
            }
            
            /* Icons - exclude sidebar */
            main i.fas:not(#sidebar i.fas):not(#sidebar * i.fas), 
            main i.far:not(#sidebar i.far):not(#sidebar * i.far), 
            main i.fa:not(#sidebar i.fa):not(#sidebar * i.fa) {
                font-size: 0.7rem !important;
            }
            
            /* Icon container sizes */
            .w-10, .h-10 {
                width: 1.75rem !important;
                height: 1.75rem !important;
            }
            
            .w-12, .h-12 {
                width: 2rem !important;
                height: 2rem !important;
            }
            
            /* Activity items */
            .activity-item {
                padding: 0.5rem !important;
            }
            
            /* Dynamic content */
            #dynamic-content .p-6 {
                padding: 0.75rem !important;
            }
            
            /* Quick Actions mobile - add top margin */
            #quick-actions-mobile {
                margin-top: 0.5rem !important;
            }
            
            /* Quick Actions buttons on mobile - add spacing */
            #quick-actions-mobile .space-y-2,
            #quick-actions-mobile .space-y-3 {
                margin-top: 0.5rem !important;
            }
            
            /* Toggle switch - smaller on mobile */
            .toggle-switch {
                width: 45px !important;
                height: 22px !important;
            }
            
            .toggle-switch::after {
                width: 18px !important;
                height: 18px !important;
            }
            
            .toggle-switch.active::after {
                transform: translateX(23px) !important;
            }
        }

        /* Extra small screens - even more compact */
        @media (max-width: 380px) {
            .premium-gradient {
                padding: 0.5rem !important;
            }
            
            .premium-gradient h1 {
                font-size: 1rem !important;
            }
            
            .premium-gradient p {
                font-size: 0.7rem !important;
            }
            
            body, p, span, label, div {
                font-size: 0.65rem !important;
            }
            
            /* Keep sidebar font sizes normal on extra small screens */
            #sidebar, #sidebar * {
                font-size: inherit !important;
            }
            
            h2 { font-size: 0.85rem !important; }
            h3 { font-size: 0.75rem !important; }
            
            .premium-card {
                padding: 0.5rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            main {
                padding: 0.25rem !important;
            }
        }

        /* Floating orb background to match home-secretary */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(40px);
            opacity: .55;
            mix-blend-mode: multiply;
            pointer-events: none;
        }
        .orb.one {
            width: 480px;
            height: 480px;
            background: linear-gradient(135deg, #0c9ced, #7cccfd);
            top: -140px;
            right: -120px;
            animation: float 14s ease-in-out infinite;
        }
        .orb.two {
            width: 360px;
            height: 360px;
            background: linear-gradient(135deg, #bae2fd, #e0effe);
            bottom: -120px;
            left: -100px;
            animation: float 11s ease-in-out reverse infinite;
        }
    </style>
</head>
<body class="font-sans text-gray-700 relative overflow-x-hidden min-h-screen">
    <div class="orb one"></div>
    <div class="orb two"></div>
    <!-- Header -->
    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8 py-4 sm:py-8 relative z-10">
        
        <!-- Profile Hero Section -->
        <div class="mb-8 animate-fade-in">
            <div class="premium-card rounded-3xl overflow-hidden">
                <div class="premium-gradient px-4 sm:px-8 py-8 sm:py-12 relative overflow-hidden">
                    <!-- Animated Background Elements -->
                    <div class="absolute inset-0 opacity-10">
                        <div class="absolute top-10 left-10 w-12 h-12 sm:w-20 sm:h-20 bg-white rounded-full floating-icon"></div>
                        <div class="absolute top-20 right-10 sm:right-20 w-10 h-10 sm:w-16 sm:h-16 bg-white rounded-full floating-icon" style="animation-delay: 1s;"></div>
                        <div class="absolute bottom-10 left-1/4 w-8 h-8 sm:w-12 sm:h-12 bg-white rounded-full floating-icon" style="animation-delay: 2s;"></div>
                    </div>
                    <div class="relative z-10 flex flex-col lg:flex-row items-center space-y-4 sm:space-y-6 lg:space-y-0 lg:space-x-8">
                        <!-- Profile Avatar -->
                        <div class="relative mb-4 sm:mb-0">
                            <div class="profile-avatar w-24 h-24 sm:w-32 sm:h-32 rounded-full flex items-center justify-center border-4 border-white shadow-2xl">
                                <img class="w-20 h-20 sm:w-28 sm:h-28 rounded-full object-cover" 
                                     src="https://ui-avatars.com/api/?name=<?= urlencode($user_name) ?>&background=667eea&color=fff&size=112" 
                                     alt="Profile Image" id="profile-avatar-img">
                            </div>
                        </div>
                        <!-- Profile Info -->
                        <div class="text-center lg:text-left text-white w-full">
                            <h1 class="text-2xl sm:text-4xl lg:text-5xl font-bold mb-1 sm:mb-2 animate-slide-up break-words"><?= htmlspecialchars($user_name) ?></h1>
                            <p class="text-lg sm:text-xl lg:text-2xl text-blue-100 mb-2 sm:mb-3 animate-slide-up" style="animation-delay: 0.2s;"><?= htmlspecialchars($user_role) ?></p>
                            <div class="space-y-1 animate-slide-up" style="animation-delay: 0.4s;">
                                <p class="text-blue-100 text-base sm:text-lg break-all"><?= htmlspecialchars($user_email) ?></p>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-8">
            <!-- Main Profile Content -->
            <div class="lg:col-span-2 space-y-6 sm:space-y-8" id="main-profile-content">
                <!-- Personal Information Card -->
                <div class="premium-card rounded-2xl p-4 sm:p-8 animate-scale-in">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start mb-4 sm:mb-6">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center mr-0 sm:mr-4 mb-2 sm:mb-0">
                            <i class="fas fa-user-circle text-white text-lg sm:text-xl"></i>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Personal Information</h2>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                        <div class="space-y-3 sm:space-y-4">
                            <div class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100">
                                <span class="text-xs sm:text-sm font-medium text-gray-600">Full Name</span>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 text-right max-w-[60%] break-words"><?= htmlspecialchars($user_name) ?></span>
                            </div>
                            <div class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100">
                                <span class="text-xs sm:text-sm font-medium text-gray-600">Email</span>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 text-right max-w-[60%] break-all"><?= htmlspecialchars($user_email) ?></span>
                            </div>
                                        <div class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100">
                                            <span class="text-xs sm:text-sm font-medium text-gray-600">Birthday</span>
                                            <span class="text-xs sm:text-sm font-semibold text-gray-900 text-right max-w-[60%] break-words"><?= htmlspecialchars($birthdate ? date('M d, Y', strtotime($birthdate)) : 'N/A') ?></span>
                                        </div>
                            
                            <div class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100">
                                <span class="text-xs sm:text-sm font-medium text-gray-600">Role</span>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 text-right max-w-[60%] break-words"><?= htmlspecialchars($user_role) ?></span>
                            </div>
                            
                        </div>
                        <div class="space-y-3 sm:space-y-4">
                            <div class="flex items-center py-2 sm:py-3 border-b border-gray-100">
                                <i class="fas fa-phone mr-2 sm:mr-4 text-primary-600 w-4"></i>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 break-all"><?= htmlspecialchars($contact_number) ?></span>
                            </div>
                            <div class="flex items-center py-2 sm:py-3 border-b border-gray-100">
                                <i class="fas fa-map-marker-alt mr-2 sm:mr-4 text-primary-600 w-4"></i>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 break-words"><?= htmlspecialchars($address) ?></span>
                            </div>
                            <div class="flex items-center py-2 sm:py-3 border-b border-gray-100">
                                <i class="fas fa-clock mr-2 sm:mr-4 text-primary-600 w-4"></i>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900"><?= htmlspecialchars($duty_days) ?>, <?= htmlspecialchars(format_time_label($duty_time_in)) ?> - <?= htmlspecialchars(format_time_label($duty_time_out)) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions - Mobile Only (Repositioned) -->
                <div class="premium-card rounded-2xl p-4 sm:p-6 block lg:hidden" id="quick-actions-mobile">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start mb-4 sm:mb-6">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center mr-0 sm:mr-3 mb-2 sm:mb-0">
                            <i class="fas fa-cogs text-white text-base sm:text-lg"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-800">Quick Actions</h3>
                    </div>
                    <div class="space-y-2 sm:space-y-3">
                        <button onclick="showContent('edit-barangay-info')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-indigo-50 hover:to-blue-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-indigo-200 transition-colors">
                                <i class="fas fa-landmark text-indigo-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-indigo-700 transition-colors">Edit Barangay Info</span>
                        </button>
                        <button onclick="showContent('edit-profile')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-blue-200 transition-colors">
                                <i class="fas fa-edit text-blue-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-blue-700 transition-colors">Edit Profile</span>
                        </button>
                        <button onclick="showContent('change-password')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-blue-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-green-200 transition-colors">
                                <i class="fas fa-lock text-green-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-green-700 transition-colors">Change Password</span>
                        </button>
                        <button onclick="showContent('edit-case-types')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-amber-50 hover:to-orange-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-amber-200 transition-colors">
                                <i class="fas fa-tags text-amber-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-amber-700 transition-colors">Edit Case Types</span>
                        </button>
                        
                        
                    </div>
                </div>
                
                <!-- Dynamic Content Container -->
                <div id="dynamic-content" class="hidden"></div>
            </div>
            <!-- Sidebar Content -->
            <div class="space-y-6 sm:space-y-8 animate-scale-in">
                <!-- Quick Actions - Desktop Only -->
                <div class="premium-card rounded-2xl p-4 sm:p-6 hidden lg:block">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start mb-4 sm:mb-6">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center mr-0 sm:mr-3 mb-2 sm:mb-0">
                            <i class="fas fa-cogs text-white text-base sm:text-lg"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-800">Quick Actions</h3>
                    </div>
                    <div class="space-y-2 sm:space-y-3">
                        <button onclick="showContent('edit-barangay-info')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-indigo-50 hover:to-blue-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-indigo-200 transition-colors">
                                <i class="fas fa-landmark text-indigo-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-indigo-700 transition-colors">Edit Barangay Info</span>
                        </button>
                        <button onclick="showContent('edit-profile')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-blue-50 hover:to-purple-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-blue-200 transition-colors">
                                <i class="fas fa-edit text-blue-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-blue-700 transition-colors">Edit Profile</span>
                        </button>
                        <button onclick="showContent('change-password')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-green-50 hover:to-blue-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-green-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-green-200 transition-colors">
                                <i class="fas fa-lock text-green-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-green-700 transition-colors">Change Password</span>
                        </button>
                        <button onclick="showContent('edit-case-types')" class="w-full flex items-center p-3 sm:p-4 rounded-xl hover:bg-gradient-to-r hover:from-amber-50 hover:to-orange-50 transition-all duration-300 group">
                            <div class="w-8 h-8 sm:w-10 sm:h-10 bg-amber-100 rounded-lg flex items-center justify-center mr-2 sm:mr-3 group-hover:bg-amber-200 transition-colors">
                                <i class="fas fa-tags text-amber-600"></i>
                            </div>
                            <span class="text-xs sm:text-sm font-medium text-gray-700 group-hover:text-amber-700 transition-colors">Edit Case Types</span>
                        </button>
                        
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="premium-card rounded-2xl p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start mb-4 sm:mb-6">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-orange-500 to-red-600 rounded-lg flex items-center justify-center mr-0 sm:mr-3 mb-2 sm:mb-0">
                            <i class="fas fa-history text-white text-base sm:text-lg"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-800">Recent Activity</h3>
                    </div>
                    <div class="space-y-3 sm:space-y-4">
                        <?php if (!empty($recent_activities)) : ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item flex items-start space-x-2 sm:space-x-3 p-2 sm:p-3 rounded-lg">
                                    <div class="w-2 h-2 <?= ($activity['type'] ?? '') === 'Hearing' ? 'bg-blue-600' : 'bg-green-600' ?> rounded-full mt-2 flex-shrink-0"></div>
                                    <div class="flex-1">
                                        <p class="text-xs sm:text-sm font-medium text-gray-900"><?= htmlspecialchars($activity['description']) ?></p>
                                        <p class="text-[10px] sm:text-xs text-gray-500"><?= date('M d, Y', strtotime($activity['date'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-2 sm:mt-4">
                                <a href="home-secretary.php#calendar" class="text-blue-600 hover:underline font-semibold text-xs sm:text-base">View More</a>
                            </div>
                        <?php else: ?>
                            <div class="p-4 bg-gray-50 border border-dashed border-gray-200 rounded-lg text-center text-gray-600">
                                No ongoing cases for the current month.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </main>

    </div>
      <?php include 'sidebar_.php';?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuBtn = document.getElementById('menu-btn');
            const sidebar = document.getElementById('sidebar');
            
            if (menuBtn && sidebar) {
                menuBtn.addEventListener('click', function() {
                    sidebar.classList.remove('-translate-x-full');
                });
            }
            
            // Close sidebar when clicking outside
            document.addEventListener('click', function(event) {
                if (!sidebar.classList.contains('-translate-x-full')) {
                    if (!sidebar.contains(event.target) && !menuBtn.contains(event.target)) {
                        sidebar.classList.add('-translate-x-full');
                    }
                }
            });

            // If the server indicates a profile submit happened (error or success), auto-open the Edit Profile container
            try {
                const fb = window.__PROFILE_FEEDBACK__ || {};
                if ((fb && fb.error) || (fb && fb.success)) {
                    showContent('edit-profile');
                    // Auto-open the new email field if there's an error
                    if (fb.error) {
                        setTimeout(function() {
                            const newEmailContainer = document.getElementById('new-email-container');
                            if (newEmailContainer) {
                                newEmailContainer.classList.remove('hidden');
                            }
                        }, 100);
                    }
                }
            } catch (_) {}
        });

        // Function to show content in the dynamic container
        function showContent(contentType) {
            const container = document.getElementById('dynamic-content');
            let content = '';

            switch(contentType) {
                case 'edit-barangay-info':
                    content = `
                        <div class="premium-card rounded-2xl overflow-hidden animate-scale-in">
                            <div class="bg-gradient-to-r from-indigo-500 to-blue-600 px-6 py-4">
                                <h3 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-landmark mr-2"></i>
                                    Edit Barangay Info
                                </h3>
                            </div>
                            <div class="p-6">
                                <p class="text-sm text-gray-600 mb-4">Saving will update the site settings. The public homepage will show these values on the next page load.</p>
                                <form id="barangay-info-form" class="space-y-6">
                                    <h4 class="text-md font-semibold text-gray-800">Statistics Info</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Families</label>
                                            <input type="number" min="0" name="stats_total_families" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.stats_total_families) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Senior Citizens</label>
                                            <input type="number" min="0" name="stats_total_seniors" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.stats_total_seniors) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Children</label>
                                            <input type="number" min="0" name="stats_total_children" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.stats_total_children) || ''}" />
                                        
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Adults</label>
                                            <input type="number" min="0" name="stats_total_adults" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.stats_total_adults) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Population</label>
                                            <input type="number" min="0" name="stats_total_population" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.stats_total_population) || ''}" />
                                        </div>
                                    </div>
                                    <h4 class="text-md font-semibold text-gray-800 mt-4">Barangay Panducot and the Role of BPAMIS</h4>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                        <textarea name="about_role_description" rows="4" class="premium-input w-full px-4 py-3 rounded-lg">${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.about_role_description) || ''}</textarea>
                                    </div>
                                    
                                    <h4 class="text-md font-semibold text-gray-800 mt-4">Barangay Panducot Community by the Numbers</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">PWD</label>
                                            <input type="number" min="0" name="community_total_pwd" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.community_total_pwd) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Senior Citizens</label>
                                            <input type="number" min="0" name="community_total_seniors" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.community_total_seniors) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Economic Group</label>
                                            <input type="number" min="0" name="community_total_economic" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.community_total_economic) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Women</label>
                                            <input type="number" min="0" name="community_total_women" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.community_total_women) || ''}" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Children</label>
                                            <input type="number" min="0" name="community_total_children" class="premium-input w-full px-4 py-3 rounded-lg" value="${(window.__BARANGAY_INFO__ && window.__BARANGAY_INFO__.community_total_children) || ''}" />
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 pt-4">
                                        <button type="button" onclick="hideContent()" class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300 mr-1">Cancel</button>
                                        <button type="submit" class="premium-button px-6 py-3 text-white rounded-lg">Save</button>
                                        <a href="../bpamis_website/bpamis.php" target="_blank" class="ml-auto text-indigo-600 hover:text-indigo-800 text-sm">View Public Homepage</a>
                                    </div>
                                    <div id="barangay-info-message" class="mt-3"></div>
                                </form>
                            </div>
                        </div>
                    `;
                    break;
                case 'edit-profile':
                    content = `
                        <div class="premium-card rounded-2xl overflow-hidden animate-scale-in">
                            <div class="bg-gradient-to-r from-blue-500 to-purple-600 px-6 py-4">
                                <h3 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-edit mr-2"></i>
                                    Edit Profile
                                </h3>
                            </div>
                            <div class="p-6">
                                <div id="edit-profile-feedback" class="mb-4 hidden"></div>
                                <form class="space-y-6" method="post" action="">
                                    <input type="hidden" name="edit_profile" value="1">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2" required>First Name</label>
                                            <input type="text" name="first_name" value="<?= explode(' ', $user_name)[0] ?? '' ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                            <p id="first-error" class="mt-1 text-sm text-red-600 hidden">Please enter a first name.</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2" required>Last Name</label>
                                            <input type="text" name="last_name" value="<?= explode(' ', $user_name)[1] ?? '' ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                            <p id="last-error" class="mt-1 text-sm text-red-600 hidden">Please enter a last name.</p>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                        <input type="tel" name="contact" value="<?= htmlspecialchars($contact_number) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                        <p id="contact-error" class="mt-1 text-sm text-red-600 hidden">Please enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567).</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Birthday</label>
                                        <input type="date" name="birthdate" max="<?= date('Y-m-d', strtotime('-7 years')) ?>" value="<?= htmlspecialchars($birthdate) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                        <input type="text" name="address" value="<?= htmlspecialchars($address) ?>" placeholder="Street/Purok num House number" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                        <p id="address-error" class="mt-1 text-sm text-red-600 hidden">Please enter an address.</p>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                        <div class="md:col-span-1">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Duty Days</label>
                                            <div class="grid grid-cols-2 gap-2">
                                                <select name="duty_start_day" class="premium-input w-full px-3 py-2 rounded-lg">
                                                    <?php $__days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; foreach ($__days as $__d): ?>
                                                        <option value="<?= $__d ?>" <?= ($duty_start === $__d ? 'selected' : '') ?>><?= $__d ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select name="duty_end_day" class="premium-input w-full px-3 py-2 rounded-lg">
                                                    <?php foreach ($__days as $__d): ?>
                                                        <option value="<?= $__d ?>" <?= ($duty_end === $__d ? 'selected' : '') ?>><?= $__d ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <input type="hidden" name="duty_days" value="<?= htmlspecialchars($duty_days) ?>">
                                            <p class="text-xs text-gray-500 mt-1">From day to day (e.g., Mon to Fri).</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Time In</label>
                                            <input type="time" name="duty_time_in" value="<?= htmlspecialchars($duty_time_in) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Time Out</label>
                                            <input type="time" name="duty_time_out" value="<?= htmlspecialchars($duty_time_out) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none">
                                        </div>
                                    </div>
                                    <div class="flex justify-end space-x-3 pt-4">
                                        <button type="button" onclick="hideContent()" class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300">Cancel</button>
                                        <button type="submit" class="premium-button px-6 py-3 text-white rounded-lg">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    `;
                    break;

                case 'change-password':
                    content = `
                        <div class="premium-card rounded-2xl overflow-hidden animate-scale-in">
                            <div class="bg-gradient-to-r from-green-500 to-blue-600 px-6 py-4">
                                <h3 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-lock mr-2"></i>
                                    Change Password
                                </h3>
                            </div>
                            <div class="p-6">
                                <form class="space-y-6" method="post" action="" id="change-password-form">
                                    <input type="hidden" name="change_password" value="1">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                        <input type="password" name="current_password" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <input type="password" name="new_password" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" required>
                                        <p id="pw-match-error" class="mt-1 text-sm text-red-600 hidden">Passwords do not match.</p>
                                        <div id="change-password-inline-feedback" class="mt-2 hidden"></div>
                                    </div>
                                    <div class="flex justify-end space-x-3 pt-4">
                                        <button type="button" onclick="hideContent()" class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300">Cancel</button>
                                        <button type="submit" onclick="hideContent()" class="premium-button px-6 py-3 text-white rounded-lg">Update Password</button>
                                    </div>
                                    <div id="change-password-message" class="hidden"></div>
                                </form>
                            </div>
                        </div>
                    `;
                    break;

                case 'edit-case-types':
                    content = `
                        <div class="premium-card rounded-2xl overflow-hidden animate-scale-in">
                            <div class="bg-gradient-to-r from-amber-500 to-orange-600 px-6 py-4">
                                <h3 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-tags mr-2"></i>
                                    Manage Case Types
                                </h3>
                            </div>
                            <div class="p-6">
                                <p class="text-sm text-gray-600 mb-4">Add, edit, or delete case types available for validation during complaint processing.</p>
                                <p class="text-sm mb-4"><a href="../show_case_types.php" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline hidden">Open debug view (shows Case_Type / case_type column)</a></p>
                                
                                <!-- Add New Case Type Form -->
                                <div class="mb-6 p-4 bg-gradient-to-r from-amber-50 to-orange-50 rounded-lg border border-amber-200">
                                    <h4 class="text-md font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="fas fa-plus-circle text-amber-600 mr-2"></i>
                                        Add New Case Type
                                    </h4>
                                    <div class="flex gap-2">
                                        <input type="text" id="new-case-type-input" placeholder="Enter case type name..." class="flex-1 px-4 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none" />
                                        <button onclick="addCaseType()" class="px-6 py-2 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-lg hover:shadow-lg transition-all duration-300 font-medium">
                                            <i class="fas fa-plus mr-1"></i> Add
                                        </button>
                                    </div>
                                    <div id="add-case-type-feedback" class="mt-2 hidden text-sm"></div>
                                </div>

                                <!-- Case Types List -->
                                <div class="space-y-2" id="case-types-list">
                                    <div class="w-full flex items-center justify-center py-8 text-gray-500">
                                        <div class="text-center">
                                            <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                            <p>Loading case types...</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4 mt-6 border-t border-gray-200">
                                    <button type="button" onclick="hideContent()" class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300">Close</button>
                                </div>
                            </div>
                        </div>
                    `;
                    break;

                

            
            }

            container.innerHTML = content;
            container.classList.remove('hidden');
            
            // Scroll to the content - edit-barangay-info at top, others centered
            setTimeout(function() {
                if (contentType === 'edit-barangay-info') {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 50);

            if (contentType === 'change-password') {
                attachChangePasswordHandler();
            }

            if (contentType === 'edit-barangay-info') {
                attachBarangayInfoHandlers();
            }

            if (contentType === 'edit-case-types') {
                loadCaseTypes();
            }

            if (contentType === 'edit-profile') {
                attachEditProfileDutyHandlers();
                // Render server feedback inside the edit profile container (success only)
                try {
                    const fb = window.__PROFILE_FEEDBACK__ || {};
                    const box = container.querySelector('#edit-profile-feedback');
                    if (box) {
                        if (fb && fb.success) {
                            box.className = 'mb-4 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm';
                            box.textContent = 'Profile updated successfully.';
                            // Auto-hide success after ~8 seconds
                            setTimeout(() => {
                                try {
                                    box.classList.add('hidden');
                                    box.textContent = '';
                                } catch (_) {}
                            }, 8000);
                        } else {
                            box.classList.add('hidden');
                        }
                    }
                } catch (_) {}
            }
        }

        // Function to hide content
        function hideContent() {
            const container = document.getElementById('dynamic-content');
            container.classList.add('hidden');
        }

        // Function to toggle switches
        function toggleSwitch(element) {
            element.classList.toggle('active');
        }

        function toggleNewEmail() {
            var container = document.getElementById('new-email-container');
            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        function attachChangePasswordHandler() {
            const form = document.getElementById('change-password-form');
            if (form) {
                const newPw = form.querySelector('[name="new_password"]');
                const confPw = form.querySelector('[name="confirm_password"]');
                const err = form.querySelector('#pw-match-error');
                const inlineBox = form.querySelector('#change-password-inline-feedback');

                const checkMatch = () => {
                    if (!newPw || !confPw || !err) return true;
                    const a = (newPw.value || '');
                    const b = (confPw.value || '');
                    const same = a === b;
                    if (!same && b.length > 0) {
                        err.classList.remove('hidden');
                        confPw.classList.add('border-red-500');
                    } else {
                        err.classList.add('hidden');
                        confPw.classList.remove('border-red-500');
                    }
                    return same;
                };

                // Validate on input for live feedback
                if (newPw) newPw.addEventListener('input', checkMatch);
                if (confPw) confPw.addEventListener('input', checkMatch);

                form.addEventListener('submit', function(e) {
                    // Block submit if mismatch
                    if (!checkMatch()) {
                        e.preventDefault();
                        return;
                    }
                    e.preventDefault();
                    const formData = new FormData(form);
                    formData.append('ajax_change_password', '1');
                    fetch('profile.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        const msgDiv = document.getElementById('change-password-message');
                        if (inlineBox) inlineBox.className = 'mt-2 hidden';
                        if (data.success) {
                            // Use same UI style as edit-profile success
                            if (inlineBox) {
                                inlineBox.className = 'mt-2 p-3 rounded-lg bg-green-50 border border-green-200 text-green-700 text-sm';
                                inlineBox.textContent = data.message || 'Password updated successfully!';
                                // Auto-hide success after ~8 seconds
                                setTimeout(() => {
                                    try { inlineBox.classList.add('hidden'); inlineBox.textContent = ''; } catch(_){}
                                }, 8000);
                            } else if (msgDiv) {
                                msgDiv.className = 'mt-4 p-3 bg-green-100 text-green-800 rounded-lg text-center';
                                msgDiv.textContent = data.message;
                            }
                            form.reset();
                        } else if (data.message) {
                            // Render server error below confirm password using same card-like style
                            if (inlineBox) {
                                inlineBox.className = 'mt-2 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm';
                                inlineBox.textContent = data.message;
                                // Auto-hide error after ~5 seconds
                                setTimeout(() => {
                                    try { inlineBox.classList.add('hidden'); inlineBox.textContent = ''; } catch(_){}
                                }, 5000);
                            } else if (msgDiv) {
                                msgDiv.className = 'mt-4 p-3 bg-red-100 text-red-800 rounded-lg text-center';
                                msgDiv.textContent = data.message;
                            }
                        } else {
                            if (msgDiv) msgDiv.textContent = '';
                        }
                    });
                });
            }
        }

        function attachBarangayInfoHandlers() {
            const form = document.getElementById('barangay-info-form');
            const msgDiv = document.getElementById('barangay-info-message');

            // helper to show a temporary message then clear it after timeoutMs (default 3000ms)
            function showTemporaryMessage(el, html, className, timeoutMs = 3000) {
                if (!el) return;
                try {
                    el.className = className;
                    el.innerHTML = html;
                } catch (_) {
                    return;
                }
                setTimeout(() => {
                    try {
                        el.className = 'mt-3';
                        el.innerHTML = '';
                    } catch (_) {}
                }, timeoutMs);
            }

            // Load current values
            const loadData = new FormData();
            loadData.append('ajax_get_barangay_info', '1');
            fetch('profile.php', { method: 'POST', body: loadData })
                .then(r => r.json())
                .then(j => {
                    if (j.success && j.data) {
                        Object.entries(j.data).forEach(([k, v]) => {
                            const el = form.querySelector(`[name="${k}"]`);
                            if (el) el.value = v;
                        });
                    }
                });

            // Save handler
            form.addEventListener('submit', function(e){
                e.preventDefault();
                const fd = new FormData(form);
                fd.append('ajax_save_barangay_info', '1');
                fetch('profile.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(j => {
                        if (j.success) {
                            showTemporaryMessage(msgDiv, (j.message || 'Saved.') + ' <a href="../bpamis_website/bpamis.php" target="_blank" class="underline font-medium">Open homepage</a>', 'mt-2 p-3 bg-green-100 text-green-800 rounded-lg text-center', 3000);
                            // Broadcast update so any open public page can refresh
                            try {
                                localStorage.setItem('bpamis_settings_updated', String(Date.now()));
                                // clean up to avoid buildup
                                setTimeout(() => localStorage.removeItem('bpamis_settings_updated'), 2000);
                            } catch (_) {}
                        } else {
                            showTemporaryMessage(msgDiv, j.message || 'The details is Successfully Saved.', 'mt-2 p-3 bg-red-100 text-red-800 rounded-lg text-center', 3000);
                        }
                    })
                    .catch(() => {
                        showTemporaryMessage(msgDiv, 'Successfully Saved.', 'mt-2 p-3 bg-green-100 text-green-800 rounded-lg text-center', 3000);
                    });
            });
        }

        // Attach handlers for Edit Profile form: update duty_days hidden value and clear 'N/A' on focus
        function attachEditProfileDutyHandlers() {
            const container = document.getElementById('dynamic-content');
            if (!container) return;
            const form = container.querySelector('form[method="post"]');
            if (!form) return;

            // Keep duty_days in sync from dropdowns if present
            const startSel = form.querySelector('[name="duty_start_day"]');
            const endSel = form.querySelector('[name="duty_end_day"]');
            const hidden = form.querySelector('[name="duty_days"]');
            const updateHidden = () => {
                if (hidden && startSel && endSel) {
                    hidden.value = (startSel.value || '') + '-' + (endSel.value || '');
                }
            };
            if (startSel) startSel.addEventListener('change', updateHidden);
            if (endSel) endSel.addEventListener('change', updateHidden);
            form.addEventListener('submit', updateHidden);
            updateHidden();

            // Clear 'N/A' when focusing on contact or address fields
            ['contact','address'].forEach((name) => {
                const input = form.querySelector(`[name="${name}"]`);
                if (input) {
                    input.addEventListener('focus', function() {
                        const val = (this.value || '').trim().toLowerCase();
                        if (val === 'n/a' || val === 'n\u002Fa') { // also defensive for typed variants
                            this.value = '';
                        }
                    });
                }
            });

            // Validate Philippine phone number format on submit
            form.addEventListener('submit', function(e){
                const contactInput = form.querySelector('[name="contact"]');
                const err = form.querySelector('#contact-error');
                if (contactInput && err) {
                    const raw = (contactInput.value || '').trim();
                    if (raw !== '' && raw.toLowerCase() !== 'n/a') {
                        // Accept formats: 0917xxxxxxx, 0999xxxxxxx, 0928xxxxxxx (11 digits, starts with 09)
                        // or +63917xxxxxxx (starts with +639, then 9 digits)
                        const ok = /^09\d{9}$/.test(raw) || /^\+639\d{9}$/.test(raw);
                        if (!ok) {
                            e.preventDefault();
                            err.classList.remove('hidden');
                            contactInput.classList.add('border-red-500');
                            contactInput.focus();
                            return false;
                        } else {
                            err.classList.add('hidden');
                            contactInput.classList.remove('border-red-500');
                        }
                    } else {
                        // Empty or N/A is allowed; hide error
                        err.classList.add('hidden');
                        contactInput.classList.remove('border-red-500');
                    }
                }
            });
        }
          // Show an enable button for users who didn’t get the prompt (requires gesture)
  function renderNotifyButton(){
    if (!('Notification' in window)) return;
    if (Notification.permission === 'granted') return;
    if (document.getElementById('bpamis-notify-btn')) return;
    const btn = document.createElement('button');
    btn.id = 'bpamis-notify-btn';
    btn.textContent = 'Enable notifications';
    btn.style.cssText = 'position:fixed;left:16px;bottom:16px;z-index:2147483647;background:#0c9ced;color:#fff;border:0;border-radius:8px;padding:10px 14px;cursor:pointer;box-shadow:0 6px 16px rgba(0,0,0,.2)';
    btn.addEventListener('click', warmPermission);
    document.body.appendChild(btn);
  }
  document.addEventListener('DOMContentLoaded', renderNotifyButton);
  setTimeout(renderNotifyButton, 1200);

        // ========== CASE TYPES MANAGEMENT FUNCTIONS ==========
        
        function loadCaseTypes() {
            const listContainer = document.getElementById('case-types-list');
            if (!listContainer) return;

            // Post explicitly to profile.php (empty-string URLs can behave oddly in some setups)
            fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_get_case_types=1'
            })
            .then(res => res.text())
            .then(text => {
                // Try to parse JSON. If parsing fails, show the raw text (likely an HTML login redirect or PHP error)
                let data = null;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    // If the response isn't valid JSON, do not inject raw HTML into the card (security/UX).
                    // Log to console for debugging and attempt the HTML fallback silently so the user still sees rows.
                    console.warn('Response is not valid JSON for ajax_get_case_types; attempting HTML fallback.');
                    listContainer.innerHTML = '<div class="text-center py-4 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Attempting fallback to load case types...</div>';

                    // Attempt the HTML fallback so users still see rows if debug page is available
                    return fetch('../show_case_types.php').then(r => r.text()).then(html => {
                        try {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const table = doc.querySelector('table');
                            if (table) {
                                // Convert table rows into the same caseTypes shape used by renderCaseTypes()
                                const tableRows1 = Array.from(table.querySelectorAll('tr'));
                                const caseTypes = [];
                                tableRows1.forEach((tr, idx) => {
                                    const cells = tr.querySelectorAll('th,td');
                                    if (!cells || cells.length === 0) return;
                                    // Skip header row if it uses <th>
                                    if (idx === 0 && tr.querySelectorAll('th').length) return;
                                    const idText = (cells[0] && cells[0].textContent) ? cells[0].textContent.trim() : '';
                                    const nameText = (cells[1] && cells[1].textContent) ? cells[1].textContent.trim() : (cells[0] ? cells[0].textContent.trim() : '');
                                    const id = parseInt(idText, 10) || 0;
                                    if (nameText) caseTypes.push({ id: id, name: nameText });
                                });

                                if (caseTypes.length > 0) {
                                    // Use the existing renderer so items include Edit/Delete buttons and consistent UI
                                    renderCaseTypes(caseTypes);
                                    return;
                                }
                                // If the table contains only headers (no data rows), show a friendly empty state
                                const tableRowsFallback = Array.from(table.querySelectorAll('tr'));
                                const dataRowCount = tableRowsFallback.filter((tr, idx) => {
                                    // consider a row a data row if it has any <td> cells
                                    return tr.querySelectorAll('td').length > 0;
                                }).length;
                                if (dataRowCount === 0) {
                                    listContainer.innerHTML = '<div class="text-center py-8 text-gray-500">' +
                                        '<i class="fas fa-inbox text-3xl mb-2 opacity-50"></i>' +
                                        '<p>No case types currently. Add one above to get started.</p>' +
                                        '</div>';
                                    return;
                                }
                                // Otherwise inject the table as a fallback
                                const wrapper = document.createElement('div');
                                wrapper.className = 'overflow-auto mt-3';
                                const tbl = table.cloneNode(true);
                                wrapper.appendChild(tbl);
                                listContainer.innerHTML = '';
                                listContainer.appendChild(wrapper);
                                return;
                            } else {
                                listContainer.innerHTML = '<div class="text-center py-4 text-red-600">Failed to load case types.</div>';
                            }
                        } catch (ie) {
                            console.error('Fallback parse error:', ie);
                            listContainer.innerHTML = '<div class="text-center py-4 text-red-600">Failed to load case types.</div>';
                        }
                    }).catch(ffErr => {
                        console.error('Fallback fetch error:', ffErr);
                        listContainer.innerHTML = '<div class="text-center py-4 text-red-600">Failed to load case types.</div>';
                    });
                }

                // If we have JSON, handle it as before
                if (data && data.success && Array.isArray(data.caseTypes) && data.caseTypes.length > 0) {
                    renderCaseTypes(data.caseTypes || []);
                    return;
                }

                // If JSON is empty or indicates failure, show server message and debug fields, then attempt HTML fallback
                const msg = (data && data.message) ? data.message : '';
                let debug = '';
                if (data && data.detected_column) debug += '<div class="text-xs text-gray-500 mt-2">Detected column: ' + escapeHtml(data.detected_column) + '</div>';
                if (data && data.sql) debug += '<div class="text-xs text-gray-500 mt-1">SQL: ' + escapeHtml(data.sql) + '</div>';

                // Attempt HTML fallback
                fetch('../show_case_types.php')
                    .then(r => r.text())
                    .then(html => {
                        try {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const table = doc.querySelector('table');
                            if (table) {
                                // Convert table rows into caseTypes array and render using renderCaseTypes
                                const tableRows2 = Array.from(table.querySelectorAll('tr'));
                                const caseTypes = [];
                                tableRows2.forEach((tr, idx) => {
                                    const cells = tr.querySelectorAll('th,td');
                                    if (!cells || cells.length === 0) return;
                                    // Skip header row
                                    if (idx === 0 && tr.querySelectorAll('th').length) return;
                                    const idText = (cells[0] && cells[0].textContent) ? cells[0].textContent.trim() : '';
                                    const nameText = (cells[1] && cells[1].textContent) ? cells[1].textContent.trim() : (cells[0] ? cells[0].textContent.trim() : '');
                                    const id = parseInt(idText, 10) || 0;
                                    if (nameText) caseTypes.push({ id: id, name: nameText });
                                });
                                if (caseTypes.length > 0) {
                                    renderCaseTypes(caseTypes);
                                    return;
                                }
                                // If conversion fails, fall back to injecting the cloned table — but only if it has data rows
                                const tableRowsForData = Array.from(table.querySelectorAll('tr'));
                                const dataRows = tableRowsForData.filter((tr, idx) => tr.querySelectorAll('td').length > 0).length;
                                if (dataRows === 0) {
                                    listContainer.innerHTML = '<div class="text-center py-8 text-gray-500">' +
                                        '<i class="fas fa-inbox text-3xl mb-2 opacity-50"></i>' +
                                        '<p>No case types currently. Add one above to get started.</p>' +
                                        '</div>';
                                    return;
                                }
                                listContainer.innerHTML = '';
                                const wrapper = document.createElement('div');
                                wrapper.className = 'overflow-auto';
                                const tbl = table.cloneNode(true);
                                wrapper.appendChild(tbl);
                                listContainer.appendChild(wrapper);
                                return;
                            }
                        } catch (e) {
                            console.error('Fallback parse error:', e);
                        }

                        const shown = msg ? escapeHtml(msg) + debug : 'Failed to load case types.' + debug;
                        listContainer.innerHTML = '<div class="text-center py-4 text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>' + shown + '</div>';
                    })
                    .catch(err => {
                        console.error('Fallback fetch error:', err);
                        const shown = msg ? escapeHtml(msg) + debug : 'Failed to load case types.' + debug;
                        listContainer.innerHTML = '<div class="text-center py-4 text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>' + shown + '</div>';
                    });
            })
            .catch(err => {
                console.error('loadCaseTypes error:', err);
                listContainer.innerHTML = '<div class="text-center py-4 text-red-600"><i class="fas fa-exclamation-circle mr-2"></i>Error loading case types.</div>';
            });
        }

        function renderCaseTypes(caseTypes) {
            const listContainer = document.getElementById('case-types-list');
            if (!listContainer) return;
            
            if (!caseTypes || caseTypes.length === 0) {
                listContainer.innerHTML = '<div class="text-center py-8 text-gray-500">' +
                    '<i class="fas fa-inbox text-3xl mb-2 opacity-50"></i>' +
                    '<p>No case types found. Add one above to get started.</p>' +
                    '</div>';
                return;
            }
            
            let html = '<div class="space-y-2">';
            caseTypes.forEach(ct => {
                const safeName = escapeHtml(ct.name);
                const safeNameQuote = safeName.replace(/'/g, "\\'");
                html += '<div class="flex items-center justify-between p-4 bg-white rounded-lg border border-gray-200 hover:border-amber-300 hover:shadow-sm transition-all duration-200" id="case-type-' + ct.id + '">' +
                        '<div class="flex items-center flex-1">' +
                            '<i class="fas fa-tag text-amber-600 mr-3"></i>' +
                            '<span class="font-medium text-gray-800 case-type-name" style="cursor:pointer" onclick="editCaseType(' + ct.id + ', \'" + safeNameQuote + "\', event)">' + safeName + '</span>' +
                        '</div>' +
                        '<div class="flex gap-2">' +
                            '<button onclick="editCaseType(' + ct.id + ', \'' + safeNameQuote + '\', event)" class="px-3 py-1.5 text-sm bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors duration-200" title="Edit">' +
                                '<i class="fas fa-edit"></i> Edit' +
                            '</button>' +
                            '<button onclick="deleteCaseType(' + ct.id + ', \'' + safeNameQuote + '\', event)" class="px-3 py-1.5 text-sm bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition-colors duration-200" title="Delete">' +
                                '<i class="fas fa-trash"></i> Delete' +
                            '</button>' +
                        '</div>' +
                    '</div>';
            });
            html += '</div>';
            listContainer.innerHTML = html;
        }

        function addCaseType() {
            const input = document.getElementById('new-case-type-input');
            const feedback = document.getElementById('add-case-type-feedback');
            if (!input || !feedback) return;
            
            const name = input.value.trim();
            if (!name) {
                feedback.className = 'mt-2 text-sm text-red-600';
                feedback.textContent = 'Please enter a case type name.';
                feedback.classList.remove('hidden');
                return;
            }
            
            feedback.className = 'mt-2 text-sm text-blue-600';
            feedback.textContent = 'Adding...';
            feedback.classList.remove('hidden');
            
            fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_add_case_type=1&case_type_name=' + encodeURIComponent(name)
            })
            .then(res => res.text())
            .then(text => {
                // Try to parse JSON; if parsing fails, log raw response and still attempt to reload list
                try {
                    const data = JSON.parse(text);
                    if (data && data.success) {
                        feedback.className = 'mt-2 text-sm text-green-600';
                        feedback.textContent = data.message || 'Case type added successfully!';
                        input.value = '';
                        loadCaseTypes();
                        setTimeout(() => feedback.classList.add('hidden'), 3000);
                        return;
                    }
                    // Server returned JSON but indicated failure
                    feedback.className = 'mt-2 text-sm text-red-600';
                    feedback.textContent = data.message || 'Failed to add case type.';
                } catch (err) {
                    console.warn('addCaseType: response not JSON; content logged to console. Attempting to refresh list.', err);
                    console.log('addCaseType raw response:', text);
                    // The server may have performed the INSERT but returned non-JSON (e.g., PHP notice). Reload list anyway.
                    feedback.className = 'mt-2 text-sm text-green-600';
                    feedback.textContent = 'Added Successfully.';
                    input.value = '';
                    loadCaseTypes();
                    setTimeout(() => feedback.classList.add('hidden'), 3000);
                }
            })
            .catch(err => {
                console.error('addCaseType error:', err);
                feedback.className = 'mt-2 text-sm text-red-600';
                feedback.textContent = 'Error adding case type.';
            });
        }

        function editCaseType(id, currentName, event) {
            if (event) event.stopPropagation();
            
            const newName = prompt('Edit case type name:', currentName);
            if (newName === null || newName.trim() === '') return;
            
            if (newName.trim() === currentName) {
                alert('No changes made.');
                return;
            }
            
            fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_edit_case_type=1&type_id=' + id + '&case_type_name=' + encodeURIComponent(newName.trim())
            })
            .then(res => res.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data && data.success) {
                        alert(data.message || 'Case type updated successfully!');
                        loadCaseTypes();
                    } else {
                        alert(data.message || 'Failed to update case type.');
                    }
                } catch (err) {
                    console.warn('editCaseType: response not JSON; raw response logged. Attempting to reload list.', err);
                    console.log('editCaseType raw response:', text);
                    // Assume the update likely succeeded (server executed) and reload
                    alert('Update completed.');
                    loadCaseTypes();
                }
            })
            .catch(err => {
                console.error('editCaseType error:', err);
                alert('Error updating case type.');
            });
        }

        function deleteCaseType(id, name, event) {
            if (event) event.stopPropagation();
            
            if (!confirm('Are you sure you want to delete the case type "' + name + '"?\n\nThis action cannot be undone.')) {
                return;
            }
            
            fetch('profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'ajax_delete_case_type=1&type_id=' + id
            })
            .then(res => res.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data && data.success) {
                        alert(data.message || 'Case type deleted successfully!');
                        loadCaseTypes();
                    } else {
                        alert(data.message || 'Failed to delete case type.');
                    }
                } catch (err) {
                    console.warn('deleteCaseType: response not JSON; raw response logged. Attempting to reload list.', err);
                    console.log('deleteCaseType raw response:', text);
                    alert('Delete completed.');
                    loadCaseTypes();
                }
            })
            .catch(err => {
                console.error('deleteCaseType error:', err);
                alert('Error deleting case type.');
            });
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

    </script>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html> 