<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Check if user is logged in
if (!isset($_SESSION['official_id'])) {
    header("Location: ../login.php");
    exit();
}

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

// Get user data from session
$user_name = $_SESSION['official_name'] ?? 'User Name';
$user_email = $_SESSION['user'] ?? 'user@example.com';
$user_role = 'Barangay Lupon';
$birthday = '';

// Handle Edit Profile form submission
$profile_success = false;
$profile_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_profile'])) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $new_email = trim($_POST['new_email'] ?? '');
    $address_in = trim($_POST['address'] ?? '');
    $birthday_in = trim($_POST['birthday'] ?? '');
    $duty_days_in = trim($_POST['duty_days'] ?? '');
    $duty_time_in_in = trim($_POST['duty_time_in'] ?? '');
    $duty_time_out_in = trim($_POST['duty_time_out'] ?? '');
    $full_name = trim($first_name . ' ' . $last_name);

    // Determine the current primary email stored in DB and only change it
    // when the user provides a non-empty `new_email`.
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
        $primary_email = trim((string)$email);
    }

    if (!empty($new_email)) {
        $new_email_trim = trim($new_email);
        if (!filter_var($new_email_trim, FILTER_VALIDATE_EMAIL)) {
            $profile_error = 'Please enter a valid email address.';
        } else {
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
    if (strtolower($contact) === 'n/a') { $contact = ''; }
    // Server-side Philippine mobile validation (allow empty)
    if ($contact !== '' && !preg_match('/^(09\d{9}|\+639\d{9})$/', $contact)) {
        $profile_error = 'Please enter a valid Philippine mobile number (e.g., 09171234567 or +639171234567).';
    }

    // Validate birthday if provided (expect YYYY-MM-DD from <input type="date">)
    if ($birthday_in !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $birthday_in);
        if (!$d || $d->format('Y-m-d') !== $birthday_in) {
            $profile_error = 'Please enter a valid birthday date.';
        }
    }

    // Enforce minimum age (7 years) if birthday is valid
    if ($birthday_in !== '' && empty($profile_error)) {
        $d2 = DateTime::createFromFormat('Y-m-d', $birthday_in);
        if ($d2 && $d2->format('Y-m-d') === $birthday_in) {
            $age = (int)$d2->diff(new DateTime())->y;
            if ($age < 7) {
                $profile_error = 'You must be at least 7 years old.';
            }
        }
    }

    // Basic validation and update only if no errors
    if ($profile_error === '') {
        if ($first_name && $last_name && $email) {
            $update_query = "UPDATE barangay_officials SET Name=?, email=?, Contact_Number=?, Birthdate=? WHERE Official_ID=?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssi", $full_name, $email, $contact, $birthday_in, $user_id);
            if ($stmt->execute()) {
                $profile_success = true;
                // Update session values
                $_SESSION['official_name'] = $full_name;
                $_SESSION['user'] = $email;

                // Save address in per-user profile table
                try {
                    $conn->query("CREATE TABLE IF NOT EXISTS official_profile (\n                    Official_ID INT PRIMARY KEY,\n                    address VARCHAR(255)\n                )");
                    $stmtAddr = $conn->prepare("INSERT INTO official_profile (Official_ID, address) VALUES (?, ?) ON DUPLICATE KEY UPDATE address=VALUES(address)");
                    $stmtAddr->bind_param('is', $user_id, $address_in);
                    $stmtAddr->execute();
                } catch (Exception $e) { /* ignore */ }

                // Save duty schedule (create table if not exists, upsert per user)
                try {
                    $conn->query("CREATE TABLE IF NOT EXISTS official_schedule (\n                    Official_ID INT PRIMARY KEY,\n                    duty_days VARCHAR(100),\n                    time_in VARCHAR(10),\n                    time_out VARCHAR(10)\n                )");
                    $stmtSch = $conn->prepare("INSERT INTO official_schedule (Official_ID, duty_days, time_in, time_out) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE duty_days=VALUES(duty_days), time_in=VALUES(time_in), time_out=VALUES(time_out)");
                    $stmtSch->bind_param('isss', $user_id, $duty_days_in, $duty_time_in_in, $duty_time_out_in);
                    $stmtSch->execute();
                } catch (Exception $e) { /* ignore */ }
                // Log successful update and redirect (PRG) so updated values appear immediately
                error_log("[profilelupon] Updated Official_ID={$user_id} email={$email}");
                $_SESSION['official_name'] = $full_name;
                $_SESSION['user'] = $email;
                if ($birthday_in !== '') $birthday = $birthday_in;
                header('Location: profilelupon.php?updated=1');
                exit();
            } else {
                // Log failure details for debugging
                error_log("[profilelupon] Failed update Official_ID={$user_id} email={$email} - stmt_error=" . ($stmt->error ?? 'N/A') . " conn_error=" . ($conn->error ?? 'N/A'));
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
        // Normalize stored position and build a consistent Lupon role label
        $storedPosition = trim((string)($user_data['Position'] ?? $_SESSION['official_position'] ?? $user_role));
        $storedLower = strtolower($storedPosition);
        $isHead = false;
        // Match common indicators for a head/chair role
        if (preg_match('/\b(head|chair|chairperson|lupon head|tagapamayapa head|punong|president)\b/i', $storedPosition)) {
            $isHead = true;
        }
        $roleKind = $isHead ? 'Head' : 'Member';
        $user_role = 'Lupon Tagapamayapa - ' . $roleKind . ' [Conciliation Panel]';
        $contact_number = $user_data['Contact_Number'] ?? '+63 912 345 6789';
            // Birthday: try multiple possible column names for compatibility
            foreach (['Birthday','birthday','Date_of_Birth','Birthdate','birthdate','DOB'] as $__bd_col) {
                if (!empty($user_data[$__bd_col])) { $birthday = $user_data[$__bd_col]; break; }
            }
        
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
    // ignore load errors
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
    // ignore load errors
}

// Helper to format time to 12-hour label for display
if (!function_exists('format_time_label')) {
    function format_time_label($t) {
        $t = trim((string)$t);
        if ($t === '') return '';
        if (preg_match('/[a-zA-Z]/', $t)) return $t; // already labeled
        $dt = DateTime::createFromFormat('H:i', $t);
        if (!$dt) $dt = DateTime::createFromFormat('H:i:s', $t);
        if ($dt) return $dt->format('g:i A');
        return $t;
    }
}

// Derive start/end days for dropdowns from current duty_days (if needed later)
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

// Fetch recent activities for the current month (upcoming hearings and case status updates)
$currentMonth = date('m');
$currentYear = date('Y');

// Upcoming hearings (future dates, this month)
$hearings = [];
$hearing_query = "SELECT ml.*, ci.Case_ID 
                  FROM meeting_logs ml 
                  JOIN case_info ci ON ml.Case_ID = ci.Case_ID 
                  WHERE MONTH(ml.Hearing_Date) = ? AND YEAR(ml.Hearing_Date) = ? AND ml.Hearing_Date >= CURDATE()
                  ORDER BY ml.Hearing_Date ASC
                  LIMIT 5";
$stmt = $conn->prepare($hearing_query);
$stmt->bind_param("ss", $currentMonth, $currentYear);
$stmt->execute();
$hearing_result = bpamis_stmt_get_result($stmt);
while ($row = $hearing_result->fetch_assoc()) {
    $hearings[] = [
        'type' => 'Hearing',
        'description' => 'Upcoming hearing for case #' . $row['Case_ID'],
        'date' => $row['Hearing_Date'],
        'link' => 'home-secretary.php#calendar'
    ];
}

// Recent case status updates (this month)
$case_updates = [];
$case_query = "SELECT Case_ID, Case_Status, Date_Opened, Date_Closed 
               FROM case_info 
               WHERE (MONTH(Date_Opened) = ? AND YEAR(Date_Opened) = ?) 
                  OR (Date_Closed IS NOT NULL AND MONTH(Date_Closed) = ? AND YEAR(Date_Closed) = ?)
               ORDER BY GREATEST(IFNULL(Date_Closed, '0000-00-00'), Date_Opened) DESC
               LIMIT 5";
$stmt = $conn->prepare($case_query);
$stmt->bind_param("ssss", $currentMonth, $currentYear, $currentMonth, $currentYear);
$stmt->execute();
$case_result = bpamis_stmt_get_result($stmt);
while ($row = $case_result->fetch_assoc()) {
    $date = $row['Date_Closed'] ?: $row['Date_Opened'];
    $case_updates[] = [
        'type' => 'Status',
        'description' => 'Case #' . $row['Case_ID'] . ' status updated to ' . $row['Case_Status'],
        'date' => $date,
        'link' => 'home-secretary.php#calendar'
    ];
}

// Merge and sort by date (descending)
$all_activities = array_merge($hearings, $case_updates);
usort($all_activities, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$recent_activities = array_slice($all_activities, 0, 5);

// Avatar choices (define at the top so it's always available)
$avatar_choices = [
    '../Assets/Img/avatar1.jpg',
    '..//Assets/Img/avatar2.jpg',
];
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

        /* Floating orb background to match secretary layout */
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

        /* ===== MOBILE RESPONSIVENESS ===== */
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
            .mb-8 > .premium-card,
            .animate-fade-in > .premium-card {
                border-radius: 1rem !important;
                padding: 0 !important;
            }
            
            /* Hero section container - add top margin */
            .mb-8,
            .animate-fade-in {
                margin-top: 0.25rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Hero section - tighter padding - OVERRIDE TAILWIND */
            .premium-gradient,
            div.premium-gradient,
            .premium-card .premium-gradient {
                padding: 0.5rem !important;
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
                padding-top: 0.5rem !important;
                padding-bottom: 0.5rem !important;
                border-radius: 1rem !important;
            }
            
            /* Profile avatar - smaller on mobile - OVERRIDE TAILWIND */
            .profile-avatar,
            div.profile-avatar,
            .relative .profile-avatar {
                width: 2rem !important;
                height: 2rem !important;
                min-width: 2rem !important;
                min-height: 2rem !important;
                border-width: 1px !important;
            }
            
            .profile-avatar img,
            .profile-avatar > img {
                width: 1.75rem !important;
                height: 1.75rem !important;
                max-width: 1.75rem !important;
                max-height: 1.75rem !important;
            }
            
            /* Hero text - smaller and tighter spacing - OVERRIDE TAILWIND */
            .premium-gradient h1,
            div.premium-gradient h1 {
                font-size: 0.95rem !important;
                margin-bottom: 0.15rem !important;
                line-height: 1.2 !important;
            }
            
            .premium-gradient p,
            div.premium-gradient p {
                font-size: 0.7rem !important;
                margin-bottom: 0.15rem !important;
                line-height: 1.1 !important;
            }
            
            .premium-gradient .space-y-1,
            div.premium-gradient .space-y-1 {
                margin-top: 0.15rem !important;
            }
            
            .premium-gradient .space-y-1 > * + * {
                margin-top: 0.1rem !important;
            }
            
            /* Floating background icons - smaller */
            .premium-gradient .floating-icon,
            .premium-gradient div.floating-icon {
                width: 1rem !important;
                height: 1rem !important;
                max-width: 1rem !important;
                max-height: 1rem !important;
            }
            
            /* Align animated background to card border radius on mobile */
            .premium-gradient .absolute.inset-0,
            .premium-gradient > div.absolute {
                border-radius: 1rem !important;
                overflow: hidden;
            }
            
            /* Main content padding - OVERRIDE TAILWIND */
            main,
            main.max-w-7xl {
                padding: 0.25rem !important;
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
                padding-top: 0.25rem !important;
                padding-bottom: 0.25rem !important;
                margin-left: 0.25rem !important;
                margin-right: 0.25rem !important;
            }
            
            /* Cards - tighter padding and margins - OVERRIDE TAILWIND */
            .premium-card,
            div.premium-card {
                padding: 0.5rem !important;
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
                padding-top: 0.5rem !important;
                padding-bottom: 0.5rem !important;
                margin-bottom: 0.5rem !important;
            }
            
            /* Grid gaps */
            .grid,
            div.grid {
                gap: 0.25rem !important;
                column-gap: 0.25rem !important;
                row-gap: 0.25rem !important;
            }
            
            /* Space-y utilities */
            .space-y-3 > * + *, .space-y-4 > * + *, .space-y-6 > * + *, .space-y-8 > * + * {
                margin-top: 0.25rem !important;
            }
            
            /* Global font size - exclude sidebar */
            main p:not(#sidebar p):not(#sidebar * p), 
            main span:not(#sidebar span):not(#sidebar * span), 
            main label:not(#sidebar label):not(#sidebar * label), 
            main div:not(#sidebar):not(#sidebar *):not(.premium-gradient):not(.premium-gradient *) {
                font-size: 0.7rem !important;
            }
            
            /* Headings - exclude sidebar - STRONGER SELECTORS */
            main h2:not(#sidebar h2):not(#sidebar * h2),
            main div h2:not(#sidebar h2):not(#sidebar * h2) { 
                font-size: 0.85rem !important; 
            }
            main h3:not(#sidebar h3):not(#sidebar * h3),
            main div h3:not(#sidebar h3):not(#sidebar * h3) { 
                font-size: 0.75rem !important; 
            }
            main h4:not(#sidebar h4):not(#sidebar * h4),
            main div h4:not(#sidebar h4):not(#sidebar * h4) { 
                font-size: 0.7rem !important; 
            }
            
            /* Buttons - exclude sidebar - STRONGER SELECTORS */
            main button:not(#sidebar button):not(#sidebar * button),
            main div button:not(#sidebar button):not(#sidebar * button) {
                font-size: 0.7rem !important;
                padding: 0.3rem 0.5rem !important;
            }
            
            /* Inputs - exclude sidebar - STRONGER SELECTORS */
            main input:not(#sidebar input):not(#sidebar * input), 
            main select:not(#sidebar select):not(#sidebar * select), 
            main textarea:not(#sidebar textarea):not(#sidebar * textarea),
            main div input:not(#sidebar input):not(#sidebar * input), 
            main div select:not(#sidebar select):not(#sidebar * select), 
            main div textarea:not(#sidebar textarea):not(#sidebar * textarea) {
                font-size: 0.7rem !important;
                padding: 0.3rem 0.5rem !important;
            }
            
            /* Icons - exclude sidebar - STRONGER SELECTORS */
            main i.fas:not(#sidebar i.fas):not(#sidebar * i.fas), 
            main i.far:not(#sidebar i.far):not(#sidebar * i.far), 
            main i.fa:not(#sidebar i.fa):not(#sidebar * i.fa),
            main div i.fas:not(#sidebar i.fas):not(#sidebar * i.fas), 
            main div i.far:not(#sidebar i.far):not(#sidebar * i.far), 
            main div i.fa:not(#sidebar i.fa):not(#sidebar * i.fa) {
                font-size: 0.7rem !important;
            }
            
            /* Icon container sizes - OVERRIDE TAILWIND CLASSES */
            main .w-10, main .h-10,
            main div.w-10, main div.h-10 {
                width: 1.5rem !important;
                height: 1.5rem !important;
                min-width: 1.5rem !important;
                min-height: 1.5rem !important;
            }
            
            main .w-12, main .h-12,
            main div.w-12, main div.h-12 {
                width: 1.75rem !important;
                height: 1.75rem !important;
                min-width: 1.75rem !important;
                min-height: 1.75rem !important;
            }
            
            /* Activity items */
            .activity-item,
            div.activity-item {
                padding: 0.35rem !important;
            }
            
            /* Dynamic content */
            #dynamic-content .p-6,
            #dynamic-content div {
                padding: 0.5rem !important;
            }
            
            /* Quick Actions mobile - add top margin */
            #quick-actions-mobile {
                margin-top: 0.25rem !important;
            }
            
            /* Quick Actions buttons on mobile - add spacing */
            #quick-actions-mobile .space-y-2,
            #quick-actions-mobile .space-y-3 {
                margin-top: 0.25rem !important;
            }
            
            /* Toggle switch - smaller on mobile */
            .toggle-switch {
                width: 40px !important;
                height: 20px !important;
            }
            
            .toggle-switch::after {
                width: 16px !important;
                height: 16px !important;
            }
            
            .toggle-switch.active::after {
                transform: translateX(20px) !important;
            }
        }

        /* Extra small screens - even more compact */
        @media (max-width: 380px) {
            .premium-gradient,
            div.premium-gradient {
                padding: 0.35rem !important;
                padding-left: 0.35rem !important;
                padding-right: 0.35rem !important;
                padding-top: 0.35rem !important;
                padding-bottom: 0.35rem !important;
            }
            
            .premium-gradient h1,
            div.premium-gradient h1 {
                font-size: 0.85rem !important;
            }
            
            .premium-gradient p,
            div.premium-gradient p {
                font-size: 0.65rem !important;
            }
            
            body:not(#sidebar):not(#sidebar *), 
            main p:not(#sidebar p):not(#sidebar * p), 
            main span:not(#sidebar span):not(#sidebar * span), 
            main label:not(#sidebar label):not(#sidebar * label), 
            main div:not(#sidebar):not(#sidebar *):not(.premium-gradient):not(.premium-gradient *) {
                font-size: 0.6rem !important;
            }
            
            /* Keep sidebar font sizes normal on extra small screens */
            #sidebar, #sidebar * {
                font-size: inherit !important;
            }
            
            main h2:not(#sidebar h2):not(#sidebar * h2),
            main div h2:not(#sidebar h2):not(#sidebar * h2) { 
                font-size: 0.75rem !important; 
            }
            main h3:not(#sidebar h3):not(#sidebar * h3),
            main div h3:not(#sidebar h3):not(#sidebar * h3) { 
                font-size: 0.7rem !important; 
            }
            
            .premium-card,
            div.premium-card {
                padding: 0.35rem !important;
                padding-left: 0.35rem !important;
                padding-right: 0.35rem !important;
                padding-top: 0.35rem !important;
                padding-bottom: 0.35rem !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen relative overflow-x-hidden">
    <div class="orb one"></div>
    <div class="orb two"></div>
    <!-- Header -->
    <?php include '../includes/barangay_official_lupon_nav.php'; ?>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-2 sm:px-4 lg:px-8 py-4 sm:py-8">
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
            <div class="lg:col-span-2 space-y-6 sm:space-y-8">
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
                                <span class="text-xs sm:text-sm font-medium text-gray-600">Role</span>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 text-right max-w-[60%] break-words"><?= htmlspecialchars($user_role) ?></span>
                            </div>
                            <div class="flex items-center justify-between py-2 sm:py-3 border-b border-gray-100">
                                <span class="text-xs sm:text-sm font-medium text-gray-600">Birthday</span>
                                <span class="text-xs sm:text-sm font-semibold text-gray-900 text-right max-w-[60%] break-words"><?= htmlspecialchars($birthday ? date('M d, Y', strtotime($birthday)) : 'N/A') ?></span>
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
                                <span class="text-xs sm:text-sm font-semibold text-gray-900"><?= htmlspecialchars($duty_days) ?>, <?= htmlspecialchars(function_exists('format_time_label') ? format_time_label($duty_time_in) : $duty_time_in) ?> - <?= htmlspecialchars(function_exists('format_time_label') ? format_time_label($duty_time_out) : $duty_time_out) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                    $show_edit = isset($_GET['edit']) || !empty($profile_error) || $profile_success; 
                    $nameParts = preg_split('/\s+/', trim((string)$user_name), 2);
                    $efirst = $nameParts[0] ?? '';
                    $elast = $nameParts[1] ?? '';
                ?>
                <div id="dynamic-content" class="hidden"></div>
            </div>
            <!-- Sidebar Content -->
            <div class="space-y-6 sm:space-y-8 animate-scale-in">
                <!-- Quick Actions -->
                <div class="premium-card rounded-2xl p-4 sm:p-6">
                    <div class="flex flex-col sm:flex-row items-center sm:items-start mb-4 sm:mb-6">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-r from-green-500 to-blue-600 rounded-lg flex items-center justify-center mr-0 sm:mr-3 mb-2 sm:mb-0">
                            <i class="fas fa-cogs text-white text-base sm:text-lg"></i>
                        </div>
                        <h3 class="text-lg sm:text-xl font-bold text-gray-800">Quick Actions</h3>
                    </div>
                    <div class="space-y-2 sm:space-y-3">
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
                        <?php foreach (
                            isset($recent_activities) ? $recent_activities : [] as $activity): ?>
                            <div class="activity-item flex items-start space-x-2 sm:space-x-3 p-2 sm:p-3 rounded-lg">
                                <div class="w-2 h-2 <?= $activity['type'] === 'Hearing' ? 'bg-blue-600' : 'bg-green-600' ?> rounded-full mt-2 flex-shrink-0"></div>
                                <div class="flex-1">
                                    <p class="text-xs sm:text-sm font-medium text-gray-900"><?= htmlspecialchars($activity['description']) ?></p>
                                    <p class="text-[10px] sm:text-xs text-gray-500"><?= date('M d, Y', strtotime($activity['date'])) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-2 sm:mt-4">
                            <a href="home-secretary.php#calendar" class="text-blue-600 hover:underline font-semibold text-xs sm:text-base">View More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    </div>
      <?php include 'sidebar_lupon.php';?>
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

            // Check if there's a profile error and show the edit form
            <?php if (!empty($profile_error)): ?>
                showContent('edit-profile');
                // Show new email field if there was an error (user was trying to add email)
                setTimeout(function() {
                    const newEmailContainer = document.getElementById('new-email-container');
                    if (newEmailContainer) {
                        newEmailContainer.classList.remove('hidden');
                    }
                }, 100);
            <?php endif; ?>

            // Check if profile was successfully updated
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('updated') === '1') {
                // Show success message
                const successDiv = document.createElement('div');
                successDiv.className = 'fixed top-20 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-fade-in';
                successDiv.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Profile updated successfully!';
                document.body.appendChild(successDiv);
                
                // Remove after 3 seconds
                setTimeout(function() {
                    successDiv.style.opacity = '0';
                    successDiv.style.transition = 'opacity 0.5s';
                    setTimeout(function() {
                        successDiv.remove();
                    }, 500);
                }, 3000);

                // Clean URL
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        // Function to show content in the dynamic container
        function showContent(contentType) {
            const container = document.getElementById('dynamic-content');
            let content = '';

            switch(contentType) {
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
                                <form class="space-y-6" method="post" action="">
                                    <input type="hidden" name="edit_profile" value="1">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name</label>
                                            <input type="text" name="first_name" value="<?= htmlspecialchars($efirst) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name</label>
                                            <input type="text" name="last_name" value="<?= htmlspecialchars($elast) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Birthday</label>
                                        <input type="date" name="birthday" max="<?= date('Y-m-d', strtotime('-7 years')) ?>" value="<?= htmlspecialchars($birthday) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                        <input type="tel" name="contact" value="<?= htmlspecialchars($contact_number) ?>" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" placeholder="e.g., 09171234567 or +639171234567">
                                        <p class="text-xs text-gray-500 mt-1">Accepts 09XXXXXXXXX or +639XXXXXXXXX</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                        <textarea name="address" rows="2" class="premium-input w-full px-4 py-3 rounded-lg focus:outline-none" placeholder="Street, Barangay, City, Province"><?= htmlspecialchars($address) ?></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Duty Days</label>
                                            <div class="flex items-center space-x-2">
                                                <select name="duty_start_day" class="premium-input px-3 py-2 rounded-md">
                                                    <option value="Mon" <?= $duty_start==='Mon'?'selected':'' ?>>Mon</option>
                                                    <option value="Tue" <?= $duty_start==='Tue'?'selected':'' ?>>Tue</option>
                                                    <option value="Wed" <?= $duty_start==='Wed'?'selected':'' ?>>Wed</option>
                                                    <option value="Thu" <?= $duty_start==='Thu'?'selected':'' ?>>Thu</option>
                                                    <option value="Fri" <?= $duty_start==='Fri'?'selected':'' ?>>Fri</option>
                                                    <option value="Sat" <?= $duty_start==='Sat'?'selected':'' ?>>Sat</option>
                                                    <option value="Sun" <?= $duty_start==='Sun'?'selected':'' ?>>Sun</option>
                                                </select>
                                                <span class="text-sm text-gray-600">to</span>
                                                <select name="duty_end_day" class="premium-input px-3 py-2 rounded-md">
                                                    <option value="Mon" <?= $duty_end==='Mon'?'selected':'' ?>>Mon</option>
                                                    <option value="Tue" <?= $duty_end==='Tue'?'selected':'' ?>>Tue</option>
                                                    <option value="Wed" <?= $duty_end==='Wed'?'selected':'' ?>>Wed</option>
                                                    <option value="Thu" <?= $duty_end==='Thu'?'selected':'' ?>>Thu</option>
                                                    <option value="Fri" <?= $duty_end==='Fri'?'selected':'' ?>>Fri</option>
                                                    <option value="Sat" <?= $duty_end==='Sat'?'selected':'' ?>>Sat</option>
                                                    <option value="Sun" <?= $duty_end==='Sun'?'selected':'' ?>>Sun</option>
                                                </select>
                                            </div>
                                            <input type="hidden" name="duty_days" value="<?= htmlspecialchars($duty_days) ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Duty Time</label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <span class="block text-xs text-gray-500 mb-1">Time In</span>
                                                    <input type="time" name="duty_time_in" value="<?= htmlspecialchars($duty_time_in) ?>" class="premium-input w-full px-3 py-2 rounded-md">
                                                </div>
                                                <div>
                                                    <span class="block text-xs text-gray-500 mb-1">Time Out</span>
                                                    <input type="time" name="duty_time_out" value="<?= htmlspecialchars($duty_time_out) ?>" class="premium-input w-full px-3 py-2 rounded-md">
                                                </div>
                                            </div>
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
                                    </div>
                                    <div class="flex justify-end space-x-3 pt-4">
                                        <button type="button" onclick="hideContent()" class="px-6 py-3 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-all duration-300">Cancel</button>
                                        <button type="submit" onclick="hideContent()" class="premium-button px-6 py-3 text-white rounded-lg">Update Password</button>
                                    </div>
                                    <div id="change-password-message"></div>
                                </form>
                            </div>
                        </div>
                    `;
                    break;

               
            }

            container.innerHTML = content;
            container.classList.remove('hidden');
            
            // Scroll to the content and center it in the viewport
            setTimeout(function() {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 50);

            if (contentType === 'change-password') {
                attachChangePasswordHandler();
            }
            if (contentType === 'edit-profile') {
                attachEditProfileDutyHandlers();
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
                form.addEventListener('submit', function(e) {
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
                        if (data.success) {
                            msgDiv.className = 'mt-4 p-3 bg-green-100 text-green-800 rounded-lg text-center';
                            msgDiv.textContent = data.message;
                            form.reset();
                        } else if (data.message) {
                            msgDiv.className = 'mt-4 p-3 bg-red-100 text-red-800 rounded-lg text-center';
                            msgDiv.textContent = data.message;
                        } else {
                            msgDiv.textContent = '';
                        }
                    });
                });
            }
        }

        // Attach handlers for Edit Profile: sync duty_days and clear N/A contact on focus
        function attachEditProfileDutyHandlers() {
            const container = document.getElementById('dynamic-content');
            if (!container) return;
            const form = container.querySelector('form[method="post"]');
            if (!form) return;
            const startSel = form.querySelector('[name="duty_start_day"]');
            const endSel = form.querySelector('[name="duty_end_day"]');
            const dutyHidden = form.querySelector('[name="duty_days"]');
            const contactInput = form.querySelector('input[name="contact"]');
            const updateDuty = () => {
                if (startSel && endSel && dutyHidden) {
                    dutyHidden.value = `${startSel.value}-${endSel.value}`;
                }
            };
            if (startSel) startSel.addEventListener('change', updateDuty);
            if (endSel) endSel.addEventListener('change', updateDuty);
            updateDuty();
            if (contactInput) {
                contactInput.addEventListener('focus', () => {
                    if (contactInput.value.trim().toLowerCase() === 'n/a') contactInput.value = '';
                });
            }
        }
    </script>
</body>
</html> 