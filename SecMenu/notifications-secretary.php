<?php
include '../controllers/session_control.php';
include '../server/server.php';

// 🔹 LEGACY FUNCTION (kept for compatibility, can be removed if not used)
function checkDeadlines($conn, $table, $type, $idColumn, $daysBefore = 3) {
    $today = date('Y-m-d');
    $sql = "SELECT $idColumn AS id, Deadline 
            FROM $table 
            WHERE Deadline IS NOT NULL
              AND DATE(Deadline) = DATE_ADD('$today', INTERVAL $daysBefore DAY)";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $relatedId = $row['id'];
            $checkSql = "SELECT 1 FROM notifications 
                         WHERE related_id = '$relatedId' 
                           AND type = '$type' LIMIT 1";
            $checkResult = $conn->query($checkSql);

            if ($checkResult && $checkResult->num_rows === 0) {
                $message = "$type is high priority! Deadline on {$row['Deadline']}.";
                $insertSql = "INSERT INTO notifications (type, message, related_id, created_at) 
                              VALUES ('$type', '$message', '$relatedId', NOW())";
                $conn->query($insertSql);
            }
        }
    }
}

// 🔹 FETCH UNVERIFIED ACCOUNTS
$unverifiedAccounts = [];
 $sql = "SELECT Resident_ID, First_Name, Middle_Name, Last_Name, Address, email, valid_id_path 
         FROM resident_info 
         WHERE isVerify = 0 
         ORDER BY Resident_ID DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $unverifiedAccounts[] = $row;
    }
}

$sql = "SELECT n.* FROM notifications n
          LEFT JOIN notifications_trash t ON (t.notification_id = n.notification_id OR t.notification_id = n.notification_id)
          WHERE t.notification_id IS NULL
          AND LOWER(COALESCE(n.title, '')) <> 'barangay notice: you have been named as respondent'
          ORDER BY n.created_at DESC";
$result = $conn->query($sql);

$notifications = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// 🔹 COUNTS

// 🔹 FILTER OUT ADMIN/ASSIGNMENT NOTIFICATIONS FOR SECRETARY VIEW
// Exclude notifications with title "New Case Assigned" or the exact
// message "A new case #<n> has been assigned to you in the Conciliation stage.".
$notifications = array_values(array_filter($notifications, function($n){
    $msg = trim($n['message'] ?? '');
    $title = trim($n['title'] ?? '');
    if (preg_match('/^A new case #\d+ has been assigned to you in the Conciliation stage\.$/i', $msg)) return false;
    if (strcasecmp($title, 'New Case Assigned') === 0) return false;
    if (strcasecmp($title, 'Barangay Notice: You Have Been Named as Respondent') === 0) return false;
    return true;
}));

// 🔹 COUNTS (after filtering)
$allCount = count($notifications);
$unreadCount = count(array_filter($notifications, fn($n)=> ((int)($n['is_read_secretary'] ?? $n['is_read'] ?? 0)) === 0));
$hearingCount = count(array_filter($notifications, fn($n)=> strcasecmp($n['type'] ?? '', 'Hearing')===0));
$caseCount = count(array_filter($notifications, fn($n)=> strcasecmp($n['type'] ?? '', 'Case')===0));
$complaintCount = count(array_filter($notifications, fn($n)=> strcasecmp($n['type'] ?? '', 'Complaint')===0));
$unverifiedCount = count($unverifiedAccounts);

// 🔹 HELPER: RELATIVE TIME
function notif_relative_time($datetime) {
    if(!$datetime) return '';
    $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    $units = [31536000=>'year',2592000=>'month',604800=>'week',86400=>'day',3600=>'hour',60=>'min'];
    foreach($units as $sec=>$label){
        if($diff >= $sec){
            $val = floor($diff / $sec);
            return $val.' '.$label.($val>1 && $label!=='min'?'s':'').' ago';
        }
    }
    return 'just now';
}

// 🔹 UNIFIED DEADLINE CHECK (with same-day duplicate prevention)
function checkDeadlines3Days($conn, $table, $idCol, $typeLabel, $deadlineColumn, $hasPriorityCol = false) {
    $dt = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $dt->modify('+3 days');
    $targetDate = $dt->format('Y-m-d');

    $selectSql = "SELECT $idCol AS id, Case_ID, $deadlineColumn AS deadline 
                  FROM $table 
                  WHERE $deadlineColumn IS NOT NULL AND DATE($deadlineColumn) = ?";
    if (!$stmt = $conn->prepare($selectSql)) {
        error_log("Prep SELECT failed for $table: " . $conn->error);
        return;
    }

    $stmt->bind_param('s', $targetDate);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);

    while ($row = $res->fetch_assoc()) {
        $id = (int)$row['id'];
        $caseId = $row['Case_ID'] ?? $id;
        $deadline = $row['deadline'];

        // ✅ Avoid duplicates for same type + related_id + same date
        $dupSql = "SELECT 1 FROM notifications 
                   WHERE type = ? AND related_id = ? 
                   AND DATE(created_at) = CURDATE() 
                   LIMIT 1";
        if (!$dup = $conn->prepare($dupSql)) {
            error_log("Prep DUP failed: " . $conn->error);
            continue;
        }
        $dup->bind_param('si', $typeLabel, $id);
        $dup->execute();
        $dupRes = bpamis_stmt_get_result($dup);

        if ($dupRes && $dupRes->num_rows === 0) {
            $title = "$typeLabel Approaching";
            $message = "$table $typeLabel for Case #$caseId is due on $deadline.";

            $insertSql = "INSERT INTO notifications (type, title, message, created_at, is_read, related_id, isPriority)
                          VALUES (?, ?, ?, NOW(), 0, ?, 1)";
            if ($ins = $conn->prepare($insertSql)) {
                $ins->bind_param('sssi', $typeLabel, $title, $message, $id);
                $ins->execute();
                $ins->close();
            }

            if ($hasPriorityCol) {
                $updateSql = "UPDATE $table SET isPriority = 1 WHERE $idCol = ?";
                if ($upd = $conn->prepare($updateSql)) {
                    $upd->bind_param('i', $id);
                    $upd->execute();
                    $upd->close();
                }
            }
        }
        $dup->close();
    }
    $stmt->close();
}

// 🔹 RUN DEADLINE CHECKS (3 days before deadlines)
checkDeadlines3Days($conn, 'mediation_info', 'Mediation_ID', 'Mediation Deadline', 'Deadline', false);
checkDeadlines3Days($conn, 'conciliation', 'Conciliation_ID', 'Conciliation Deadline', 'Deadline', false);
checkDeadlines3Days($conn, 'arbitration', 'Arbitration_ID', 'Arbitration Deadline', 'Deadline', false);
checkDeadlines3Days($conn, 'case_info', 'Case_ID', 'Case Deadline', 'Case_Deadline', false);
checkDeadlines3Days($conn, 'case_info', 'Case_ID', 'Deadline Overdue', 'Deadline_Overdue', false);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <!-- Proper Font Awesome CSS (icons were not showing because only JS was loaded) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Optional JS (kept if any dynamic FA functionality is needed) -->
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                        'pulse-subtle': 'pulse-subtle 2s infinite',
                        'bell-ring': 'bell-ring 1s ease-in-out',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        },
                        'pulse-subtle': {
                            '0%, 100%': { opacity: 1 },
                            '50%': { opacity: 0.8 }
                        },
                        'bell-ring': {
                            '0%, 100%': { transform: 'rotate(0)' },
                            '20%, 60%': { transform: 'rotate(8deg)' },
                            '40%, 80%': { transform: 'rotate(-8deg)' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .notification-card {
            transition: all 0.2s ease;
        }
        .notification-card:hover {
            background-color: #f9fafc;
        }
        .unread-indicator {
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #0c9ced;
            top: 22px;
            right: 22px;
        }
        
        /* Notification item styles */
        .notification-item {
            transition: all 0.2s ease;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-dot {
            transition: all 0.2s ease;
        }
        .notification-item:hover .notification-dot {
            transform: scale(1.2);
        }
        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }
        
        /* Empty state animation */
        .empty-icon-container {
            animation: float 4s ease-in-out infinite;
        }

        /* Slide-out on delete animation */
        .s-notif-card {
            transition: transform .28s ease, opacity .28s ease, box-shadow .28s ease;
            will-change: transform, opacity;
        }
        .s-notif-card.slide-out-left {
            transform: translateX(-24px);
            opacity: 0;
        }

        /* Fullscreen image preview */
        .img-modal { background: rgba(0,0,0,.65); }
        .img-viewport { max-width: 92vw; max-height: 86vh; }
        
        /* Mobile / small-screen hero & filter adjustments */
        @media (max-width:640px) {
            /* Verify Modal Mobile Optimization */
            #verifyModal { padding: 0.5rem !important; }
            
            #verifyModal > div { 
                max-width: 100% !important; 
                border-radius: 1rem !important;
                max-height: 80vh !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
            }

            /* Modal Header */
            #verifyModal .bg-gradient-to-r { 
                padding: 0.75rem !important;
                flex-shrink: 0 !important;
            }

            #verifyModal .w-12 { 
                width: 2rem !important; 
                height: 2rem !important; 
                font-size: 0.875rem !important; 
            }

            #verifyModal .bg-gradient-to-r h3 { 
                font-size: 1rem !important; 
                line-height: 1.3; 
            }

            #verifyModal .bg-gradient-to-r p { 
                font-size: 0.7rem !important; 
                margin-top: 0.125rem !important; 
            }

            #verifyModal .bg-gradient-to-r .gap-3 { 
                gap: 0.5rem !important; 
            }

            /* Modal Content */
            #verifyModal .p-6 { 
                padding: 0.75rem !important;
                overflow-y: auto !important;
                flex: 1 1 auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            /* Scrollbar styling for modal content */
            #verifyModal .p-6::-webkit-scrollbar {
                width: 4px !important;
            }

            #verifyModal .p-6::-webkit-scrollbar-track {
                background: rgba(229, 231, 235, 0.5) !important;
            }

            #verifyModal .p-6::-webkit-scrollbar-thumb {
                background: rgba(107, 114, 128, 0.5) !important;
                border-radius: 2px !important;
            }

            #verifyModal .p-6::-webkit-scrollbar-thumb:hover {
                background: rgba(107, 114, 128, 0.7) !important;
            }

            /* Grid to Single Column */
            #verifyModal .grid-cols-1 { 
                grid-template-columns: 1fr !important; 
                gap: 0.75rem !important; 
            }

            #verifyModal .gap-5 { 
                gap: 0.75rem !important; 
            }

            #verifyModal .mb-5 { 
                margin-bottom: 0.75rem !important; 
            }

            /* Info Cards */
            #verifyModal .bg-gradient-to-br { 
                padding: 0.75rem !important; 
                border-radius: 0.75rem !important; 
            }

            #verifyModal h4 { 
                font-size: 0.8rem !important; 
                margin-bottom: 0.5rem !important; 
            }

            #verifyModal h4 i { 
                font-size: 0.7rem !important; 
            }

            #verifyModal .space-y-3 { 
                gap: 0.5rem !important; 
            }

            #verifyModal .space-y-2\.5 > div { 
                margin-top: 0.375rem !important; 
            }

            #verifyModal .space-y-2\.5 > div:first-child { 
                margin-top: 0 !important; 
            }

            /* Personal Info Fields */
            #verifyModal .flex.items-start span { 
                font-size: 0.7rem !important; 
                line-height: 1.3; 
            }

            #verifyModal .w-28 { 
                width: 5rem !important; 
                font-size: 9px !important; 
            }

            /* ID Preview Section */
            #vm-view-full { 
                font-size: 9px !important; 
                padding: 0.375rem 0.5rem !important; 
                border-radius: 0.5rem !important; 
            }

            #vm-view-full i { 
                font-size: 9px !important; 
            }

            #vm-validid-wrap { 
                height: 8rem !important; 
                padding: 0.5rem !important; 
                border-radius: 0.75rem !important; 
            }

            #vm-validid-img { 
                max-height: 7rem !important; 
            }

            #vm-validid-no { 
                font-size: 9px !important; 
            }

            #vm-validid-no i { 
                font-size: 1.5rem !important; 
                margin-bottom: 0.25rem !important; 
            }

            /* OCR Toggle */
            #verifyModal .bg-white.rounded-lg { 
                padding: 0.5rem !important; 
                margin-top: 0.5rem !important; 
                border-radius: 0.5rem !important; 
            }

            #verifyModal label { 
                gap: 0.5rem !important; 
            }

            #verifyModal label span.text-sm { 
                font-size: 0.7rem !important; 
            }

            #verifyModal label p.text-xs { 
                font-size: 9px !important; 
                margin-top: 0.125rem !important; 
                line-height: 1.3; 
            }

            #verifyModal input[type="checkbox"] { 
                width: 0.875rem !important; 
                height: 0.875rem !important; 
            }

            /* Status Message */
            #vm-status { 
                margin-bottom: 0.75rem !important; 
                padding: 0.5rem !important; 
                font-size: 0.7rem !important; 
                border-radius: 0.75rem !important; 
            }

            #vm-status i { 
                font-size: 0.7rem !important; 
            }

            /* Action Buttons */
            #verifyModal .flex.items-center.justify-end { 
                padding-top: 0.75rem !important; 
                gap: 0.5rem !important; 
                flex-direction: column !important; 
            }

            #vm-cancel,
            #vm-ok { 
                width: 100% !important; 
                font-size: 0.7rem !important; 
                padding: 0.5rem 0.75rem !important; 
                border-radius: 0.75rem !important; 
                justify-content: center; 
            }

            #vm-cancel i,
            #vm-ok i { 
                font-size: 0.65rem !important; 
            }

            /* Decorative Elements */
            #verifyModal .absolute.w-32,
            #verifyModal .absolute.w-24 { 
                display: none !important; 
            }

            /* Confirm Invalid Modal Mobile Optimization */
            #confirmInvalidModal { padding: 0.5rem !important; }
            
            #confirmInvalidModal > div { 
                max-width: 100% !important; 
                border-radius: 1rem !important;
                max-height: 80vh !important;
                display: flex !important;
                flex-direction: column !important;
                overflow: hidden !important;
            }

            #confirmInvalidModal .bg-gradient-to-r { 
                padding: 0.75rem !important;
                flex-shrink: 0 !important;
            }

            #confirmInvalidModal .w-10 { 
                width: 2rem !important; 
                height: 2rem !important; 
                font-size: 0.875rem !important; 
            }

            #confirmInvalidModal h3 { 
                font-size: 0.95rem !important; 
            }

            #confirmInvalidModal .text-xs { 
                font-size: 9px !important; 
            }

            #confirmInvalidModal .p-5 { 
                padding: 0.75rem !important;
                overflow-y: auto !important;
                flex: 1 1 auto !important;
                -webkit-overflow-scrolling: touch !important;
            }

            /* Scrollbar styling for confirm invalid modal */
            #confirmInvalidModal .p-5::-webkit-scrollbar {
                width: 4px !important;
            }

            #confirmInvalidModal .p-5::-webkit-scrollbar-track {
                background: rgba(229, 231, 235, 0.5) !important;
            }

            #confirmInvalidModal .p-5::-webkit-scrollbar-thumb {
                background: rgba(107, 114, 128, 0.5) !important;
                border-radius: 2px !important;
            }

            #confirmInvalidModal .p-5::-webkit-scrollbar-thumb:hover {
                background: rgba(107, 114, 128, 0.7) !important;
            }

            #confirmInvalidModal .bg-red-50 { 
                padding: 0.75rem !important; 
                margin-bottom: 0.75rem !important; 
                border-radius: 0.75rem !important; 
            }

            #confirmInvalidModal .bg-red-50 p { 
                font-size: 0.7rem !important; 
                line-height: 1.3; 
            }

            #confirmInvalidModal .bg-red-50 p.text-xs { 
                font-size: 9px !important; 
            }

            #confirmInvalidModal .flex.items-center.justify-end { 
                flex-direction: column !important; 
                gap: 0.5rem !important; 
            }

            #cim-cancel,
            #cim-ok { 
                width: 100% !important; 
                font-size: 0.7rem !important; 
                padding: 0.5rem 0.75rem !important; 
                border-radius: 0.75rem !important; 
                justify-content: center; 
            }

            #cim-cancel i,
            #cim-ok i { 
                font-size: 0.65rem !important; 
            }

            #confirmInvalidModal .absolute { 
                display: none !important; 
            }

            /* Make hero tighter on mobile */
            .gradient-bg { padding: 0.6rem !important; }
            /* Reduce outer top margin for notification sections on mobile (hero, filters, list) */
            .w-full.mt-6, .w-full.mt-8 { margin-top: 0.3rem !important; }
            .gradient-bg .relative.z-10 { gap: 6px; }
            .gradient-bg h1 { font-size: 1.05rem !important; }
            .gradient-bg h1 span.font-semibold { font-size: 1.05rem !important; }
            .gradient-bg p { font-size: 0.86rem !important; line-height: 1.2 !important; margin-top: 0.25rem; }

            /* Counters */
            #heroAllCount, #heroUnreadCount, #heroUnverifiedCount { font-size: 0.85rem !important; }
            .max-w-2xl { max-width: 100% !important; }
            /* Allow the right column to shrink more on small screens */
            .min-w-\[250px\] { min-width: 0 !important; }

            /* On mobile hide the counters grid but keep the small "Overview summary" text visible */
            .min-w-\[250px\] .grid.grid-cols-3 { display: none !important; }
            .min-w-\[250px\] .text-\[11px\] { display: none !important; text-align: center; }

            /* Filters card padding & control sizes */
            .relative.bg-white\/90 { padding: 0.65rem !important; }
            #searchInput, #monthFilter, #yearFilter, #sortOrder { font-size: 0.82rem !important; padding: 0.45rem 0.6rem !important; }
            /* Ensure the search input keeps enough left padding so the placeholder clears the search icon */
            #searchInput { padding-left: 2.75rem !important; }
            .s-chip { font-size: 0.72rem !important; padding: 0.28rem 0.5rem !important; }
            #markAllReadBtn { padding: 0.36rem 0.5rem !important; font-size: 0.72rem !important; }
            .grid.grid-cols-3 [class*="text-[10px]"] {font-size: 8px !important;}

            /* Make overview counters smaller and tighter on mobile */
            #heroAllCount, #heroUnreadCount, #heroUnverifiedCount { font-size: 0.82rem !important; }
            .grid.grid-cols-3 > div { padding: 0.35rem !important; }

            /* Chips row: make horizontally scrollable on small screens without affecting layout */
            .filters-chips-row { gap: 0.25rem; }
                #notifChips {
                    -webkit-overflow-scrolling: touch;
                    overflow-x: auto; /* allow horizontal scrolling on narrow viewports */
                    -ms-overflow-style: none; /* hide IE/Edge scrollbar */
                    scrollbar-width: none; /* hide Firefox scrollbar */
                    white-space: nowrap;
                    display: flex;
                    gap: 0.2rem;
                    padding-bottom: 4px; /* easier touch target for scroll */
                }

                /* Hide WebKit scrollbar while still allowing scroll */
                #notifChips::-webkit-scrollbar { height: 0; }

            #notifChips .s-chip {
                display: inline-flex;
                white-space: nowrap;
                margin-right: 0.18rem;
                padding: 0.26rem 0.46rem !important;
                font-size: 0.6rem !important;
                flex: 0 0 auto; /* prevent chips from shrinking/wrapping */
            }
            /* Ensure Mark All Read sits at the far right */
            .filters-chips-row { display:flex; align-items:center; justify-content:space-between; }

                /* Layout tweak: put the search input above the compact controls (month/year/sort/reset)
                   and make those controls form a single non-wrapping row on small screens. This
                   prevents horizontal scrolling while keeping controls usable. */
                .grid.grid-cols-1.md\:grid-cols-12 { display: flex; flex-wrap: nowrap; gap: 0.4rem; align-items: center; overflow-x: hidden; }
                .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-5 { order: -1; flex: 1 1 100%; min-width: 0; }
                /* Make the filter controls stretch to fill the entire row evenly on small screens */
                .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-2,
                .grid.grid-cols-1.md\:grid-cols-12 > .md\:col-span-1 { flex: 1 1 0; min-width: 0; }

                /* Make the select controls compact so they fit in one row */
                #monthFilter, #yearFilter, #sortOrder, #resetFilters { font-size: 0.78rem !important; padding: 0.36rem 0.5rem !important; }

                /* Add left padding for the search input so the placeholder/text doesn't sit flush */
                #searchInput { padding-left: 0.9rem !important; }

            /* Slightly tone down decorative absolute elements so hero feels lighter */
            .gradient-bg > .absolute { transform: scale(0.86); opacity: 0.75; }
        }

        @media (max-width:380px) {
            .gradient-bg { padding: 0.45rem !important; }
            .gradient-bg h1 { font-size: 0.98rem !important; }
            .gradient-bg p { font-size: 0.78rem !important; }
            #heroAllCount, #heroUnreadCount, #heroUnverifiedCount { font-size: 0.78rem !important; }
            .relative.bg-white\/90 { padding: 0.45rem !important; }
            #searchInput, #monthFilter, #yearFilter, #sortOrder { font-size: 0.72rem !important; padding: 0.36rem 0.5rem !important; }
            /* Slightly smaller left padding on extra-small screens but still clear the icon */
            #searchInput { padding-left: 2.25rem !important; }
            .s-chip { font-size: 0.62rem !important; padding: 0.2rem 0.38rem !important; }
            #markAllReadBtn { padding: 0.28rem 0.4rem !important; font-size: 0.64rem !important; }
            #notifChips .s-chip { margin-right: 0.28rem; }
            .gradient-bg > .absolute { transform: scale(0.78); opacity: 0.7; }
        }

        /* Notification List Mobile Optimization */
        @media (max-width: 640px) {
            /* Notification Cards */
            .s-notif-card {
                padding: 0.75rem !important;
                gap: 0.5rem !important;
            }

            /* Icon container */
            .s-notif-card .shrink-0.w-8 {
                width: 1.75rem !important;
                height: 1.75rem !important;
            }

            .s-notif-card .shrink-0.w-8 i {
                font-size: 0.7rem !important;
            }

            /* Unread indicator dot */
            .s-notif-card .absolute.top-3.right-3 {
                top: 0.5rem !important;
                right: 0.5rem !important;
                width: 0.5rem !important;
                height: 0.5rem !important;
            }

            /* Title */
            .s-notif-card h3 {
                font-size: 0.75rem !important;
                line-height: 1.2 !important;
            }

            /* Type badges */
            .s-notif-card .px-2.py-0\.5 {
                padding: 0.2rem 0.4rem !important;
                font-size: 8px !important;
            }

            .s-notif-card .px-2.py-0\.5 i {
                font-size: 7px !important;
            }

            /* Message text */
            .s-notif-card p.text-xs {
                font-size: 0.7rem !important;
                line-height: 1.3 !important;
                margin-top: 0.35rem !important;
            }

            /* DateTime and relative time */
            .s-notif-card .text-\[11px\] {
                font-size: 9px !important;
            }

            .s-notif-card .text-\[11px\] i {
                font-size: 8px !important;
            }

            /* Action buttons */
            .s-notif-card .px-3.py-1\.5 {
                padding: 0.35rem 0.5rem !important;
                font-size: 9px !important;
            }

            .s-notif-card .px-3.py-1\.5 i {
                font-size: 8px !important;
            }

            /* Gap between icon and content */
            .s-notif-card .flex.items-start.gap-4 {
                gap: 0.65rem !important;
            }

            /* Gap between title elements */
            .s-notif-card .flex.flex-wrap.items-center.gap-2 {
                gap: 0.35rem !important;
                margin-bottom: 0.25rem !important;
            }

            /* Bottom action row */
            .s-notif-card .mt-3.flex.items-center.justify-between {
                margin-top: 0.5rem !important;
                gap: 0.5rem !important;
                flex-direction: column !important;
                align-items: flex-start !important;
            }

            /* Make action buttons full width on mobile */
            .s-notif-card .flex.gap-2 {
                width: 100%;
                gap: 0.4rem !important;
            }

            .s-notif-card .flex.gap-2 a,
            .s-notif-card .flex.gap-2 button {
                flex: 1;
                justify-content: center;
            }

            /* Space between notification cards */
            #notificationList {
                gap: 0.65rem !important;
            }

            /* Icon container for unverified accounts */
            .s-notif-card .shrink-0.w-10 {
                width: 1.75rem !important;
                height: 1.75rem !important;
            }

            .s-notif-card .shrink-0.w-10 i {
                font-size: 0.7rem !important;
            }

            /* Unverified account buttons - match regular notification button sizes */
            .s-notif-card[data-base="unverified"] .flex.gap-2 {
                width: 100% !important;
                display: flex !important;
            }

            .s-notif-card[data-base="unverified"] .flex.gap-2 form {
                flex: 1 !important;
                display: flex !important;
            }

            .s-notif-card[data-base="unverified"] .open-verify-modal,
            .s-notif-card[data-base="unverified"] form button {
                flex: 1 !important;
                justify-content: center !important;
                padding: 0.35rem 0.5rem !important;
                font-size: 9px !important;
                width: 100% !important;
                height: 100% !important;
            }

            .s-notif-card[data-base="unverified"] .open-verify-modal i,
            .s-notif-card[data-base="unverified"] form button i {
                font-size: 8px !important;
            }
        }

        @media (max-width: 380px) {
            .s-notif-card {
                padding: 0.6rem !important;
            }

            .s-notif-card h3 {
                font-size: 0.7rem !important;
            }

            .s-notif-card p.text-xs {
                font-size: 0.65rem !important;
            }

            .s-notif-card .text-\[11px\] {
                font-size: 8px !important;
            }

            .s-notif-card .px-3.py-1\.5 {
                padding: 0.3rem 0.45rem !important;
                font-size: 8px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
<?php include_once ('../includes/barangay_official_sec_nav.php'); ?>
    <!-- Global Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-[float_14s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>
    <!-- Premium Hero -->
    <div class="w-full mt-6 px-4">
        <div class="relative gradient-bg max-w-screen-2xl mx-auto rounded-2xl shadow-sm p-8 md:p-10 overflow-hidden">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-100 rounded-full -mr-24 -mt-24 opacity-70 animate-[float_8s_ease-in-out_infinite]"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-primary-200 rounded-full -ml-14 -mb-14 opacity-60 animate-[float_6s_ease-in-out_infinite]"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-to-br from-primary-50 via-white to-primary-100 opacity-30 blur-3xl rounded-full pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-8">
                <div class="max-w-2xl">
                    <h1 class="text-3xl md:text-4xl font-light text-primary-900 tracking-tight">Secretary <span class="font-semibold">Notifications</span></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed">Monitor all system and case lifecycle events including deadlines, hearings, complaints, and account verification. Use refined filters to focus on what matters now.</p>
                    <div class="mt-5 flex flex-wrap gap-3 text-xs text-primary-700/80 font-medium">
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-bell text-primary-500"></i> Real-time</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation text-primary-500"></i> Deadlines</span>
                        <span class="px-3 py-1.5 rounded-full bg-white/70 backdrop-blur border border-primary-100 shadow-sm flex items-center gap-1"><i class="fa-solid fa-user-check text-primary-500"></i> Verification</span>
                    </div>
                </div>

                <div class="flex flex-col gap-3 min-w-[250px]">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-blue-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-blue-600 font-semibold">All</span><span id="heroAllCount" class="mt-1 text-lg font-semibold text-blue-700"><?= $allCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-amber-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-amber-600 font-semibold">Unread</span><span id="heroUnreadCount" class="mt-1 text-lg font-semibold text-amber-700"><?= $unreadCount ?></span></div>
                        <div class="flex flex-col items-center bg-white/80 backdrop-blur rounded-xl px-3 py-3 border border-indigo-100 shadow-sm"><span class="text-[10px] uppercase tracking-wide text-indigo-600 font-semibold">Unverified</span><span id="heroUnverifiedCount" class="mt-1 text-lg font-semibold text-indigo-700"><?= $unverifiedCount ?></span></div>
                    </div>
                    <div class="text-[11px] text-primary-700/70 text-center">Overview summary</div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Advanced Filters Card -->
    <div class="w-full mt-8 px-4">
        <div class="max-w-screen-2xl mx-auto">
            <div class="relative bg-white/90 backdrop-blur-sm border border-gray-100 rounded-2xl shadow-sm p-6 md:p-7 overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-gradient-to-br from-primary-50 to-primary-100 rounded-full opacity-70"></div>
                <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-gradient-to-tr from-primary-50 to-primary-100 rounded-full opacity-60"></div>
                <div class="relative z-10 space-y-6">
                    <div class="flex items-center justify-between gap-4 text-primary-700/80 text-sm font-medium">
                        <div class="text-[11px] inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50/70 border border-primary-100"><i class="fa-solid fa-sliders text-primary-500"></i> Refine Notifications</div>
                        <div class="shrink-0">
                            <button id="markAllReadBtn" class="group relative inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-r from-primary-500 to-primary-600 text-white text-xs font-semibold shadow-sm hover:shadow-md transition-all">
                                <i class="fa-solid fa-check-double"></i>
                                <span>Mark All Read</span>
                            </button>
                        </div>
                    </div>
                    <!-- Chips row (button moved up to the refine row) -->
                    <div class="filters-chips-row">
                        <div class="flex flex-nowrap gap-2 overflow-x-auto" id="notifChips">
                        <button type="button" data-filter="" class="s-chip active px-3 py-1.5 text-xs font-medium rounded-full bg-primary-600 text-white shadow-sm">All</button>
                        <button type="button" data-filter="unread" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-amber-50 text-amber-600 border border-amber-100 hover:bg-amber-100 transition">Unread</button>
                        <button type="button" data-filter="hearing" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-purple-50 text-purple-600 border border-purple-100 hover:bg-purple-100 transition">Hearings</button>
                        <button type="button" data-filter="complaint" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-cyan-50 text-cyan-600 border border-cyan-100 hover:bg-cyan-100 transition">Complaints</button>
                        <button type="button" data-filter="case" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-green-50 text-green-600 border border-green-100 hover:bg-green-100 transition">Cases</button>
                        <button type="button" data-filter="unverified" class="s-chip px-3 py-1.5 text-xs font-medium rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100 hover:bg-indigo-100 transition">Unverified</button>
                        </div>
                    </div>

                    <!-- Search row: moved out of the grid so it occupies its own line on small screens -->
                    <div class="w-full">
                        <div class="md:col-span-5 relative group">
                            <input id="searchInput" type="text" placeholder="Search notifications..." class="w-full pl-11 pr-4 py-3 rounded-xl border border-gray-200/80 bg-white/70 focus:ring-2 focus:ring-primary-200 focus:border-primary-400 placeholder:text-gray-400 text-sm transition" />
                            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-primary-400 group-focus-within:text-primary-500 transition"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <div class="md:col-span-2 relative">
                            <select id="monthFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Months</option>
                                <?php foreach(range(1,12) as $m): $mn=date('F',mktime(0,0,0,$m,1)); ?>
                                    <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>"><?= $mn ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-2 relative">
                            <select id="yearFilter" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="">All Years</option>
                                <?php $cy=date('Y'); for($y=$cy;$y>=$cy-5;$y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-2 relative">
                            <select id="sortOrder" class="w-full pl-3 pr-8 py-3 rounded-xl border border-gray-200 bg-white/70 text-sm focus:ring-2 focus:ring-primary-200 focus:border-primary-400 appearance-none">
                                <option value="desc">Newest First</option>
                                <option value="asc">Oldest First</option>
                            </select>
                            <i class="fa-solid fa-caret-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-primary-400"></i>
                        </div>
                        <div class="md:col-span-1 flex">
                            <button id="resetFilters" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-3 rounded-xl border border-primary-100 bg-primary-50/60 text-primary-600 text-sm font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-rotate-left"></i><span class="hidden xl:inline">Reset</span></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Unified Notifications Section -->
    <div id="notificationSection" class="w-full mt-8 px-4 pb-24">
        <div class="max-w-screen-2xl mx-auto">
            <div id="notificationList" class="space-y-4">
                <?php if(!empty($notifications)): foreach($notifications as $row):
                    // Skip notifications with the exact assignment text for Conciliation stage
                    $rawMessage = trim($row['message'] ?? '');
                    if (preg_match('/^A new case #\d+ has been assigned to you in the Conciliation stage\.$/i', $rawMessage)) continue;
                    $rawType = $row['type'];
                    $baseType = strtolower($rawType);
                    $priorityFlag = isset($row['isPriority']) ? (int)$row['isPriority'] : 0;
                    $group = $baseType; // deadline grouping removed
                    $icon='fa-bell'; $iconWrap='bg-gray-100 text-gray-600';
                    switch(true){
                        case $rawType==='Hearing': $icon='fa-calendar-alt'; $iconWrap='bg-purple-50 text-purple-600'; break;
                        case $rawType==='Complaint' && $priorityFlag===0: $icon='fa-file-alt'; $iconWrap='bg-cyan-50 text-cyan-600'; break;
                        case $rawType==='Complaint' && $priorityFlag===1: $icon='fa-exclamation-triangle'; $iconWrap='bg-red-100 text-red-600'; break;
                        case $rawType==='Case': $icon='fa-gavel'; $iconWrap='bg-green-50 text-green-600'; break;
                        case $rawType==='Unverified': $icon='fa-user-circle'; $iconWrap='bg-indigo-50 text-indigo-600'; break;
                    }
                    // Use secretary-specific unread flag if available
                    $isUnread = ((int)($row['is_read_secretary'] ?? $row['is_read'] ?? 0))===0; $createdRaw=$row['created_at']; $createdDisp=date('M j, Y g:i A', strtotime($createdRaw));
                    $searchStr = strtolower(($row['title']??'').' '.($row['message']??''));
                ?>
                <div class="s-notif-card relative group bg-white/85 backdrop-blur rounded-xl border border-gray-100 p-5 flex flex-col gap-3 hover:-translate-y-[2px] hover:shadow-md transition-all" data-type="<?=htmlspecialchars($group)?>" data-base="<?=htmlspecialchars($baseType)?>" data-date="<?= date('Y-m-d H:i:s', strtotime($createdRaw)) ?>" data-unread="<?=$isUnread? '1':'0'?>" data-search="<?= htmlspecialchars($searchStr) ?>">
                    <?php if($isUnread): ?><span class="absolute top-3 right-3 inline-flex w-2.5 h-2.5 rounded-full bg-amber-500 shadow animate-pulse-subtle"></span><?php endif; ?>
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center <?=$iconWrap?> shadow-sm"><i class="fa-solid <?=$icon?> text-sm"></i></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-sm font-medium text-gray-800 leading-snug line-clamp-2" title="<?= htmlspecialchars($row['title'] ?: 'Notification') ?>"><?= htmlspecialchars($row['title'] ?: 'Notification') ?></h3>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-[10px] font-semibold tracking-wide uppercase text-gray-600"><i class="fa-solid <?=$icon?>"></i><?= htmlspecialchars($rawType) ?></span>
                                <?php if($priorityFlag===1): ?><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-[10px] font-semibold tracking-wide text-red-600"><i class="fa-solid fa-exclamation-triangle"></i>Priority</span><?php endif; ?>
                            </div>
                            <?php $__msg = preg_replace('/^(\s*)Your\b/', '$1The', $row['message'] ?? '', 1); ?>
                            <p class="mt-1 text-xs text-gray-600 line-clamp-3" title="<?= htmlspecialchars($__msg) ?>"><?= htmlspecialchars($__msg) ?></p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-[11px] text-gray-500 font-medium flex items-center gap-1"><i class="fa-regular fa-clock"></i> <?= $createdDisp ?> <span class="hidden sm:inline">• <?= notif_relative_time($row['created_at']) ?></span></span>
                                <div class="flex gap-2">
                                    <a href="view_notification.php?id=<?= $row['notification_id'] ?>" data-id="<?= $row['notification_id'] ?>" class="btn-view-notif inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-50 text-primary-600 text-[11px] font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-eye"></i> View</a>
                                    <button type="button" class="btn-delete-notif inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-600 text-[11px] font-medium hover:bg-red-100 transition" data-id="<?= $row['notification_id'] ?>"><i class="fa-solid fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>

                <!-- Synthetic Unverified Accounts (actionable) -->
                <?php if(!empty($unverifiedAccounts)): foreach($unverifiedAccounts as $account):
                    $name = htmlspecialchars($account['First_Name'].' '.$account['Last_Name']);
                    $email = htmlspecialchars($account['email']);
                    $search = strtolower($name.' '.$email.' unverified account');
                    $accId = (int)$account['Resident_ID'];
                    $accFirst = htmlspecialchars($account['First_Name']);
                    $accMiddle = htmlspecialchars($account['Middle_Name'] ?? '');
                    $accLast = htmlspecialchars($account['Last_Name']);
                    $accEmail = htmlspecialchars($account['email']);
                    $accAddress = htmlspecialchars($account['Address'] ?? '');
                    $accValidId = htmlspecialchars($account['valid_id_path'] ?? '');
                ?>
                <div class="s-notif-card relative group bg-white/85 backdrop-blur rounded-xl border border-indigo-100 p-5 flex flex-col gap-3 hover:-translate-y-[2px] hover:shadow-md transition-all" data-type="unverified" data-base="unverified" data-date="1970-01-01 00:00:00" data-unread="0" data-search="<?= $search ?>">
                    <span class="absolute top-3 right-3 inline-flex w-2.5 h-2.5 rounded-full bg-orange-500 shadow"></span>
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-10 h-10 rounded-full flex items-center justify-center bg-indigo-50 text-indigo-600 shadow-sm"><i class="fa-solid fa-user-circle text-sm"></i></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-sm font-medium text-gray-800 leading-snug">Unverified Account</h3>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-50 text-[10px] font-semibold tracking-wide text-indigo-600"><i class="fa-solid fa-user"></i>Pending</span>
                            </div>
                            <p class="mt-1 text-xs text-gray-600"><?=$name?> (<?=$email?>)</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-[11px] text-gray-500 font-medium">Awaiting verification</span>
                                <div class="flex gap-2">
                                    <!-- Open Confirm Modal (client-side) -->
                                    <button type="button"
                                        class="open-verify-modal inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-green-50 text-green-600 text-[11px] font-medium hover:bg-green-100 transition"
                                        data-id="<?= $accId ?>"
                                        data-first="<?= $accFirst ?>"
                                        data-middle="<?= $accMiddle ?>"
                                        data-last="<?= $accLast ?>"
                                        data-email="<?= $accEmail ?>"
                                        data-address="<?= $accAddress ?>"
                                        data-validid="<?= $accValidId ?>">
                                        <i class="fa-solid fa-check"></i> Verify
                                    </button>

                                    <form method="POST" action="../controllers/account_verification.php" class="inline" onsubmit="return confirm('Remove this unverified account?');">
                                        <input type="hidden" name="id" value="<?= $account['Resident_ID'] ?>" />
                                        <button name="remove" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-600 text-[11px] font-medium hover:bg-red-100 transition"><i class="fa-solid fa-user-xmark"></i>Remove</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
                <?php if(empty($notifications) && empty($unverifiedAccounts)): ?>
                    <div class="py-16 text-center text-gray-500 text-sm">No notifications or pending accounts.</div>
                <?php endif; ?>
            </div>
            <div id="noResults" class="hidden mt-10 text-center text-gray-500 text-sm">No notifications match your filters.</div>
            <div class="mt-10 flex justify-center">
                <a href="home-secretary.php" class="inline-flex items-center gap-2 px-4 py-2 text-gray-500 hover:text-gray-700 text-sm font-medium transition"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>

        </div>
    
    <!-- Legacy empty state removed (replaced by dynamic noResults) -->
<?php include 'sidebar_.php';?>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
    // Prevent duplicate initialization if this script block is included twice
    if (window.__notifSecInit) return; window.__notifSecInit = true;
    // Helper: safe JSON parse (global)
    window.readJsonSafe = async function(resp){
        try {
            const ct = (resp.headers && resp.headers.get('content-type')) || '';
            if (ct.includes('application/json')) return await resp.json();
            const text = await resp.text();
            console.error('Non-JSON response', resp.status, text);
            return { success:false, message:'Non-JSON response', raw:text, status: resp.status };
        } catch (e){
            console.error('JSON parse error', e);
            return { success:false, message:'Response parse error' };
        }
    };

    // 🔹 FETCH UNVERIFIED ACCOUNTS
    let unverifiedAccounts = <?php echo json_encode($unverifiedAccounts); ?>;

    // 🔹 NOTIFICATION MARKING
    document.getElementById('markAllReadBtn').addEventListener('click', function() {
        fetch('../controllers/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ scope: 'secretary' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.s-notif-card').forEach(card => {
                    card.dataset.unread = '0';
                    const dot = card.querySelector('.animate-pulse-subtle');
                    if (dot) dot.remove();
                });
                // Also remove unread dots (amber/orange) on synthetic unverified cards
                document.querySelectorAll('.s-notif-card [class*="bg-amber-500"], .s-notif-card [class*="bg-orange-500"]').forEach(dot=> dot.remove());
                // Try to hide navbar notification badge/count for secretary
                try {
                    // Hide any numeric badge near the bell icon in the nav
                    document.querySelectorAll('nav .fa-bell').forEach(icon=>{
                        const container = icon.closest('a,div,button') || icon.parentElement;
                        if (!container) return;
                        container.querySelectorAll('span').forEach(sp=>{
                            if (/^\d+$/.test((sp.textContent||'').trim())) {
                                sp.style.display = 'none';
                            }
                        });
                    });
                } catch(e) { /* noop */ }
                // Update hero counters after marking all read
                updateHeroCounts();
                // Ensure full consistency across navbar and counts after backend update
                setTimeout(()=>{ window.location.reload(); }, 300);
            }
        })
        .catch(error => console.error('Error marking notifications as read:', error));
    });

    // NOTE: older Promise-based delegated delete handler removed to avoid duplicate
    // requests and conflicting behavior. The consolidated async/await handler
    // further below uses the shared `readJsonSafe` helper and checks `response.ok`.

    // 🔹 FILTERS AND SEARCH
    const chips = document.querySelectorAll('#notifChips .s-chip');
    const searchInput = document.getElementById('searchInput');
    const monthFilter = document.getElementById('monthFilter');
    const yearFilter = document.getElementById('yearFilter');
    const sortOrder = document.getElementById('sortOrder');
    const resetBtn = document.getElementById('resetFilters');
    const cards = [...document.querySelectorAll('.s-notif-card')];
    const noResults = document.getElementById('noResults');
    let filterOverride = '';

    // --- Persist synthetic unverified read state (browser-local) ---
    const UNVERIFIED_LS_KEY = 'bpamis_unverified_read_ids';
    let unverifiedReadIds;
    try { unverifiedReadIds = new Set((JSON.parse(localStorage.getItem(UNVERIFIED_LS_KEY) || '[]')||[]).map(String)); }
    catch { unverifiedReadIds = new Set(); }
    function persistUnverifiedReadIds(){
        try { localStorage.setItem(UNVERIFIED_LS_KEY, JSON.stringify(Array.from(unverifiedReadIds))); } catch {}
    }
    // Apply persisted read state to synthetic unverified cards on load
    document.querySelectorAll('.s-notif-card[data-base="unverified"]').forEach(card=>{
        const btn = card.querySelector('.open-verify-modal');
        const rid = btn?.dataset?.id ? String(btn.dataset.id) : '';
        if (rid && unverifiedReadIds.has(rid)) {
            card.dataset.unread = '0';
            const dot = card.querySelector('.animate-pulse-subtle, [class*="bg-amber-500"], [class*="bg-orange-500"]');
            if (dot) dot.remove();
        }
    });

    // Helper to recompute and update hero counts from current DOM state
    function updateHeroCounts(){
        const cardsAll = document.querySelectorAll('.s-notif-card');
        // All count: total visible notifications
        const heroAll = document.getElementById('heroAllCount');
        if (heroAll) heroAll.textContent = cardsAll.length.toString();
        
        // Unread: exclude synthetic unverified to reflect DB state
        const unreadCards = Array.from(cardsAll).filter(c=> c.dataset.unread==='1' && c.dataset.base !== 'unverified');
        const heroUnread = document.getElementById('heroUnreadCount');
        if (heroUnread) heroUnread.textContent = unreadCards.length.toString();

        // Unverified: count synthetic unverified cards (data-base="unverified")
        const unverifiedCards = Array.from(cardsAll).filter(c=> c.dataset.base === 'unverified');
        const heroUnverified = document.getElementById('heroUnverifiedCount');
        if (heroUnverified) heroUnverified.textContent = unverifiedCards.length.toString();
        
        // Update navbar badge if it exists - reduce count for deleted unread notifications
        try {
            const navBadges = document.querySelectorAll('nav .fa-bell + .badge, nav .notification-badge, nav [class*="badge"]');
            navBadges.forEach(badge => {
                if (badge.textContent && parseInt(badge.textContent) > 0) {
                    badge.textContent = unreadCards.length.toString();
                    if (unreadCards.length === 0) {
                        badge.style.display = 'none';
                    }
                }
            });
        } catch(e) { /* noop */ }
    }
    function applyFilters(){
        const q=(searchInput.value||'').toLowerCase(); const m=monthFilter.value; const y=yearFilter.value; let shown=0;
        cards.forEach(c=>{ const type=c.dataset.type||''; const base=c.dataset.base||''; const unread=c.dataset.unread==='1'; const dateRaw=c.dataset.date||''; const text=c.dataset.search||''; let show=true;
            if(filterOverride){
                if(filterOverride==='unread') show=unread; else show = type===filterOverride || base===filterOverride;
            }
            if(q) show = show && text.includes(q);
            if((m||y) && dateRaw && dateRaw!=='1970-01-01 00:00:00') { const d=new Date(dateRaw.replace(' ','T')); const M=('0'+(d.getMonth()+1)).slice(-2); const Y=d.getFullYear().toString(); if(m) show=show && M===m; if(y) show=show && Y===y; }
            c.style.display=show?'':'none'; if(show) shown++; });
        // Sorting only real dated notifications
        const list=document.getElementById('notificationList');
        const visible=cards.filter(c=>c.style.display!=="none").sort((a,b)=>{ const da=new Date(a.dataset.date.replace(' ','T')); const db=new Date(b.dataset.date.replace(' ','T')); return sortOrder.value==='asc'? da-db : db-da; });
        visible.forEach(el=>list.appendChild(el));
        noResults.classList.toggle('hidden', shown>0);
    }
    chips.forEach(ch=> ch.addEventListener('click',()=>{ chips.forEach(c=>c.classList.remove('active','bg-primary-600','text-white','shadow')); ch.classList.add('active','bg-primary-600','text-white','shadow'); filterOverride=(ch.dataset.filter||''); applyFilters(); }));
    [searchInput,monthFilter,yearFilter,sortOrder].forEach(el=> el.addEventListener('input',applyFilters));
    monthFilter.addEventListener('change',applyFilters); yearFilter.addEventListener('change',applyFilters); sortOrder.addEventListener('change',applyFilters);
    resetBtn.addEventListener('click',()=>{ searchInput.value=''; monthFilter.value=''; yearFilter.value=''; sortOrder.value='desc'; filterOverride=''; chips.forEach((c,i)=>{ c.classList.remove('active','bg-primary-600','text-white','shadow'); if(i===0){ c.classList.add('active','bg-primary-600','text-white','shadow'); } }); applyFilters(); });
    // Mark all read
    const markAll=document.getElementById('markAllReadBtn');
    markAll.addEventListener('click',()=>{
        fetch('../controllers/mark_all_notifications_read.php',{
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({scope:'secretary'})
        }).then(r=>r.json()).then(d=>{ if(d.success){
            cards.forEach(c=>{ if(c.dataset.unread==='1'){ c.dataset.unread='0'; const dot=c.querySelector('.animate-pulse-subtle'); if(dot) dot.remove(); } });
            // Also remove unread dots (amber/orange) on synthetic unverified cards
            document.querySelectorAll('.s-notif-card [class*="bg-amber-500"], .s-notif-card [class*="bg-orange-500"]').forEach(dot=> dot.remove());
            // Persist all current synthetic unverified as read
            document.querySelectorAll('.s-notif-card[data-base="unverified"] .open-verify-modal').forEach(btn=>{
                const rid = btn?.dataset?.id ? String(btn.dataset.id) : '';
                if (rid) unverifiedReadIds.add(rid);
            });
            persistUnverifiedReadIds();
            // Try to hide navbar notification badge/count for secretary
            try {
                // Hide any numeric badge near the bell icon in the nav
                document.querySelectorAll('nav .fa-bell').forEach(icon=>{
                    const container = icon.closest('a,div,button') || icon.parentElement;
                    if (!container) return;
                    container.querySelectorAll('span').forEach(sp=>{
                        if (/^\d+$/.test((sp.textContent||'').trim())) {
                            sp.style.display = 'none';
                    }
                });
            });
            } catch(e) { /* noop */ }
            // Update hero counters after marking all read
            updateHeroCounts();
            // Ensure full consistency across navbar and counts after backend update
            setTimeout(()=>{ window.location.reload(); }, 300);
        }}).catch(e=> console.warn('Failed to mark all read:', e));
    });
    // Initial hero counts sync
    updateHeroCounts();
    applyFilters();

    // Live updates: poll server for new notifications and prepend without reload
    (function setupLiveNotifications(){
        const listEl = document.getElementById('notificationList');
        const heroAll = document.getElementById('heroAllCount');
        const heroUnread = document.getElementById('heroUnreadCount');

        function getExistingIds(){
            const ids = new Set();
            listEl.querySelectorAll('.s-notif-card .btn-delete-notif').forEach(btn=>{
                const id = btn.getAttribute('data-id');
                if (id) ids.add(String(id));
            });
            return ids;
        }

        function iconMeta(type, isPriority){
            const t = (type||'').toLowerCase();
            if (t === 'hearing') return { icon:'fa-calendar-alt', wrap:'bg-purple-50 text-purple-600' };
            if (t === 'case') return { icon:'fa-gavel', wrap:'bg-green-50 text-green-600' };
            if (t === 'complaint' && isPriority) return { icon:'fa-exclamation-triangle', wrap:'bg-red-100 text-red-600' };
            if (t === 'complaint') return { icon:'fa-file-alt', wrap:'bg-cyan-50 text-cyan-600' };
            return { icon:'fa-bell', wrap:'bg-gray-100 text-gray-600' };
        }

        function escapeHtml(s){
            if (s === null || s === undefined) return '';
            return String(s)
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;')
                .replace(/'/g,'&#039;');
        }

        function formatDateTime(dt){
            try {
                const d = new Date(dt);
                return d.toLocaleString(undefined, { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
            } catch { return escapeHtml(dt||''); }
        }

        function buildCardHTML(n){
            const isUnread = !n.is_read;
            const createdDisp = formatDateTime(n.created_at);
            const searchStr = (String(n.title||'')+' '+String(n.message||'')).toLowerCase();
            const baseType = (n.type||'').toLowerCase();
            const { icon, wrap } = iconMeta(n.type, n.isPriority===1);
            const priorityBadge = (n.type==='Complaint' && n.isPriority===1)
                ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-[10px] font-semibold tracking-wide text-red-600"><i class="fa-solid fa-exclamation-triangle"></i>Priority</span>'
                : '';
            const unreadDot = isUnread ? '<span class="absolute top-3 right-3 inline-flex w-2.5 h-2.5 rounded-full bg-amber-500 shadow animate-pulse-subtle"></span>' : '';
            const relDateAttr = (n.created_at||'').replace('T',' ').slice(0,19);
            const title = escapeHtml(n.title || 'Notification');
            const msg = escapeHtml(n.message || '');
            const typeLbl = escapeHtml(n.type || '');
            const id = String(n.notification_id);
            return `
                <div class="s-notif-card relative group bg-white/85 backdrop-blur rounded-xl border border-gray-100 p-5 flex flex-col gap-3 hover:-translate-y-[2px] hover:shadow-md transition-all" data-type="${baseType}" data-base="${baseType}" data-date="${relDateAttr}" data-unread="${isUnread?'1':'0'}" data-search="${escapeHtml(searchStr)}">
                    ${unreadDot}
                    <div class="flex items-start gap-4">
                        <div class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${wrap} shadow-sm"><i class="fa-solid ${icon} text-sm"></i></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h3 class="text-sm font-medium text-gray-800 leading-snug line-clamp-2" title="${title}">${title}</h3>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-[10px] font-semibold tracking-wide uppercase text-gray-600"><i class="fa-solid ${icon}"></i>${typeLbl}</span>
                                ${priorityBadge}
                            </div>
                            <p class="mt-1 text-xs text-gray-600 line-clamp-3" title="${msg}">${msg}</p>
                            <div class="mt-3 flex items-center justify-between">
                                <span class="text-[11px] text-gray-500 font-medium flex items-center gap-1"><i class="fa-regular fa-clock"></i> ${createdDisp}</span>
                                <div class="flex gap-2">
                                    <a href="view_notification.php?id=${id}" data-id="${id}" class="btn-view-notif inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary-50 text-primary-600 text-[11px] font-medium hover:bg-primary-100 transition"><i class="fa-solid fa-eye"></i> View</a>
                                    <button type="button" class="btn-delete-notif inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-50 text-red-600 text-[11px] font-medium hover:bg-red-100 transition" data-id="${id}"><i class="fa-solid fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
        }

        async function fetchAndPrepend(){
            try {
                const res = await fetch('../controllers/notifications_list.php?exclude_trash=1', { cache: 'no-store', credentials: 'same-origin' });
                if (!res.ok) return;
                const data = await res.json();
                const list = Array.isArray(data.notifications) ? data.notifications : [];
                if (!list.length) return;
                const existing = getExistingIds();
                const toAdd = list.filter(n => !existing.has(String(n.notification_id)));
                if (!toAdd.length) return;
                // Prepend newest first (list is already newest first)
                const frag = document.createDocumentFragment();
                const nodes = [];
                toAdd.forEach(n => {
                    // Skip notifications that are "New Case Assigned" (server-side filtered)
                    // or the specific Conciliation assignment message so live updates match the view
                    const t = (n.title || '').toString().toLowerCase();
                    const m = (n.message || '').toString();
                    if (t === 'new case assigned') return;
                    if (t === 'barangay notice: you have been named as respondent') return;
                    if (/^A new case #\d+ has been assigned to you in the Conciliation stage\.$/i.test(m)) return;
                    const temp = document.createElement('div');
                    temp.innerHTML = buildCardHTML(n).trim();
                    const card = temp.firstElementChild;
                    nodes.push(card);
                });
                // Insert in reverse so the newest remains on top visually after multiple appends
                for (let i = nodes.length - 1; i >= 0; i--) {
                    listEl.prepend(nodes[i]);
                    // brief highlight
                    nodes[i].classList.add('ring-2','ring-primary-200');
                    setTimeout(()=> nodes[i].classList.remove('ring-2','ring-primary-200'), 1200);
                }
                // Sync cards array and counts, then re-apply filters
                try { cards.length = 0; cards.push(...document.querySelectorAll('.s-notif-card')); } catch(e) {}
                updateHeroCounts();
                applyFilters();
                // Update hero All count explicitly to reflect DOM
                if (heroAll) heroAll.textContent = document.querySelectorAll('.s-notif-card').length.toString();
                if (heroUnread) heroUnread.textContent = Array.from(document.querySelectorAll('.s-notif-card')).filter(c=> c.dataset.unread==='1' && c.dataset.base!=='unverified').length.toString();
            } catch (e) {
                // silent fail; will retry on next tick
                console.warn('Live notifications fetch failed', e);
            }
        }
        // Initial fetch + interval
        fetchAndPrepend();
        setInterval(fetchAndPrepend, 15000);
    })();

    // Delete a single notification (delegated)
    document.getElementById('notificationList').addEventListener('click', async (e)=>{
        const btn = e.target.closest('.btn-delete-notif');
        if (!btn) return;
        const id = btn.getAttribute('data-id');
        if (!id) return;
        const card = btn.closest('.s-notif-card');
        const confirmed = window.confirm('Delete this notification? This action cannot be undone.');
        if (!confirmed) return;
        try {
            const res = await fetch('../controllers/delete_notification.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id, 10) })
            });
            const data = await readJsonSafe(res);
            if (data && data.success) {
                if (card) {
                    // Check if this was an unread notification before removing it
                    const wasUnread = card.dataset.unread === '1';
                    
                    // Animate slide out then remove
                    card.style.pointerEvents = 'none';
                    card.classList.add('slide-out-left');
                    const finalizeRemoval = () => {
                        card.remove();
                        const idx = cards.indexOf(card);
                        if (idx > -1) cards.splice(idx, 1);
                        
                        // Update all counts after removal
                        updateHeroCounts();
                        
                        // If this was an unread notification, update navbar badge immediately
                        if (wasUnread) {
                            try {
                                const navBadges = document.querySelectorAll('nav .fa-bell + span, nav .notification-badge, nav [class*="badge"]');
                                navBadges.forEach(badge => {
                                    if (badge.textContent && parseInt(badge.textContent) > 0) {
                                        const currentCount = parseInt(badge.textContent);
                                        const newCount = Math.max(0, currentCount - 1);
                                        badge.textContent = newCount.toString();
                                        if (newCount === 0) {
                                            badge.style.display = 'none';
                                        }
                                    }
                                });
                            } catch(e) { console.warn('Failed to update navbar badge:', e); }
                        }
                        
                        // Apply filters to ensure unread filter doesn't show deleted items
                        applyFilters();
                        
                        const remaining = document.querySelectorAll('.s-notif-card').length;
                        noResults.classList.toggle('hidden', remaining > 0);
                    };
                    let removed = false;
                    const onEnd = (ev) => {
                        if (removed) return;
                        if (ev.propertyName === 'transform' || ev.propertyName === 'opacity') {
                            removed = true;
                            card.removeEventListener('transitionend', onEnd);
                            finalizeRemoval();
                        }
                    };
                    card.addEventListener('transitionend', onEnd);
                    // Fallback in case transitionend doesn't fire
                    setTimeout(() => { if (!removed) { finalizeRemoval(); } }, 400);
                }
            } else {
                // Show helpful diagnostic information returned by server when available
                let details = '';
                try {
                    if (data) {
                        const dbErr = data.db_error || null;
                        const attempts = data.attempts || null;
                        if (dbErr) details += '\nDB error: ' + dbErr;
                        if (attempts) details += '\nAttempts: ' + JSON.stringify(attempts);
                    }
                } catch (e) { details = '';} 
                const msg = 'Failed to delete notification.' + (details ? details : '');
                alert(msg);
                console.warn('Delete response details:', data);
            }
        } catch (err) {
            console.warn('Delete failed', err);
            alert('An error occurred while deleting.');
        }
    });
    // Delegated handler: mark single notification read when 'View' clicked
    document.getElementById('notificationList').addEventListener('click', async (e)=>{
        const v = e.target.closest('.btn-view-notif');
        if (!v) return;
        e.preventDefault();
        const id = v.getAttribute('data-id');
        const card = v.closest('.s-notif-card');
        try {
            const res = await fetch('../controllers/mark_notification_read.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: parseInt(id,10), scope: 'secretary' })
            });
            // optimistic UI update regardless of response (server will enforce ownership)
            if (card) {
                card.dataset.unread = '0';
                const dot = card.querySelector('.animate-pulse-subtle, .absolute.top-3.right-3');
                if (dot) dot.remove();
            }
            const hero = document.getElementById('heroUnreadCount');
            if (hero) { const cur = parseInt((hero.textContent||'0').replace(/[^0-9-]/g,''))||0; const next = Math.max(0, cur - 1); hero.textContent = String(next); }
        } catch (err) {
            // ignore network errors and still navigate
            console.warn('Mark read failed', err);
        }
        // finally navigate to view page
        const href = v.getAttribute('href') || ('view_notification.php?id='+encodeURIComponent(id));
        window.location = href;
    });
    // Verify modal logic
    const modal = document.getElementById('verifyModal');
    const vmName = document.getElementById('vm-name'); // legacy
    const vmFirst = document.getElementById('vm-first');
    const vmLast = document.getElementById('vm-last');
    const vmMiddle = document.getElementById('vm-middle');
    const vmEmail = document.getElementById('vm-email');
    const vmAddress = document.getElementById('vm-address');
    const vmValidImg = document.getElementById('vm-validid-img');
    const vmValidNo = document.getElementById('vm-validid-no');
    const vmOcr = document.getElementById('vm-ocr-toggle');
    const vmStatus = document.getElementById('vm-status');
    const vmOk = document.getElementById('vm-ok');
    const vmCancel = document.getElementById('vm-cancel');
    const vmViewFull = document.getElementById('vm-view-full');

    // Fullscreen modal
    const imgModal = document.getElementById('imgPreviewModal');
    const imgPreview = document.getElementById('imgPreview');
    const imgClose = document.getElementById('imgClose');

    let currentId = 0;
    // Guard to prevent double submission/email sends
    let __verifyingInProgress = false;

    function openImgModal() {
        if (!vmValidImg.src) return;
        imgPreview.src = vmValidImg.src;
        imgModal.classList.remove('hidden'); imgModal.classList.add('flex');
    }
    function closeImgModal() {
        imgModal.classList.add('hidden'); imgModal.classList.remove('flex');
        imgPreview.src = '';
    }

    vmViewFull.addEventListener('click', openImgModal);
    vmValidImg.addEventListener('click', openImgModal);
    imgClose.addEventListener('click', closeImgModal);
    imgModal.addEventListener('click', (e)=>{ if(e.target === imgModal) closeImgModal(); });

    function openModal(button){
        currentId = button.dataset.id || 0;
        const first = button.dataset.first || '';
        const last  = button.dataset.last  || '';
        vmFirst.textContent = first || '—';
        vmLast.textContent  = last || '—';
        // keep legacy "Name" for compatibility
        if (vmName) vmName.textContent = (first + (last ? (' ' + last) : '')).trim();
        vmMiddle.textContent = button.dataset.middle || '—';
        vmEmail.textContent = button.dataset.email || '—';
        vmAddress.textContent = button.dataset.address || '—';
        const validPath = button.dataset.validid || '';
        if(validPath && validPath !== ''){
            vmValidImg.src = '../' + validPath;
            vmValidImg.classList.remove('hidden');
            vmValidNo.classList.add('hidden');
        } else {
            vmValidImg.src = '';
            vmValidImg.classList.add('hidden');
            vmValidNo.classList.remove('hidden');
        }
        vmStatus.classList.add('hidden');
        vmStatus.textContent = '';
        vmOcr.checked = true;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        // Mark synthetic unverified as read when opened & persist
        const card = button.closest('.s-notif-card');
        if (card && card.dataset.base === 'unverified') {
            if (card.dataset.unread === '1') {
                card.dataset.unread = '0';
                const dot = card.querySelector('.animate-pulse-subtle, [class*="bg-amber-500"], [class*="bg-orange-500"]');
                if (dot) dot.remove();
                const rid = String(currentId || '');
                if (rid) { unverifiedReadIds.add(rid); persistUnverifiedReadIds(); }
                updateHeroCounts();
            }
        }
    }

    function closeModal(){
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentId = 0;
    }

    document.querySelectorAll('.open-verify-modal').forEach(btn=>{
        btn.addEventListener('click', ()=> openModal(btn));
    });

    vmCancel.addEventListener('click', (e)=>{
        e.preventDefault();
        closeModal();
    });

    modal.addEventListener('click', (e)=>{
        if(e.target === modal) closeModal();
    });

    vmOk.addEventListener('click', async (e)=>{
        e.preventDefault();
        if(!currentId) return;
        if (__verifyingInProgress) return; // avoid duplicate sends when multiple listeners exist
        __verifyingInProgress = true;
        vmOk.disabled = true;
        vmCancel.disabled = true;
        vmStatus.classList.remove('hidden');
        vmStatus.textContent = 'Processing...';

        try {
            if(vmOcr.checked){
                const ocrResp = await fetch('../controllers/verify_resident.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ id: currentId, useOCR: 1 })
                });
                const ocrJson = await readJsonSafe(ocrResp);

                if(ocrResp.ok && ocrJson && ocrJson.success){
                    vmStatus.textContent = 'OCR matched. Verifying account...';
                    const verifyResp = await fetch('../controllers/account_verification.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ id: currentId, verify: 1, ajax: 1 })
                    });
                    const verifyJson = await readJsonSafe(verifyResp);
                    // Only trust explicit JSON success; do not infer from HTML/text
                    const verifiedOk = verifyResp.ok && (verifyJson && (verifyJson.success === true || verifyJson.success === 'true'));
                    if(verifiedOk){
                        // If backend had a soft probe warning, inform the user but still treat as verified
                        vmStatus.textContent = 'User verified. Email sent.';
                        setTimeout(()=> window.location.reload(), 900);
                        return;
                    } else {
                        console.error('Verify error', verifyResp.status, verifyJson);
                        // Standardize invalid-email handling: show message and do not open modal
                        if (verifyJson && verifyJson.email_invalid) {
                            vmStatus.textContent = 'Email is not valid. Account not verified.';
                            vmOk.disabled = false;
                            vmCancel.disabled = false;
                            return;
                        }
                        vmStatus.textContent = 'Verification failed. Please try again.';
                    }
                } else if (ocrResp.ok && ocrJson && ocrJson.matches){
                    // OCR ran but required fields didn’t all match
                    console.warn('OCR mismatch details', ocrJson.matches);
                    vmStatus.innerHTML = 'Details did not match.<br>'
                      + 'First: ' + (ocrJson.matches.first_name ? '✓' : '✗') + ' • '
                      + 'Last: ' + (ocrJson.matches.last_name ? '✓' : '✗') + ' • '
                      + 'Barangay: ' + (ocrJson.matches.barangay ? '✓' : '✗');
                    // Ask confirmation to notify+remove
                    const confirmInvalid = document.getElementById('confirmInvalidModal');
                    if (confirmInvalid) openConfirmInvalid();
                } else {
                    console.error('OCR scan failed', {
                        httpStatus: ocrResp.status,
                        code: ocrJson.code || null,
                        reason: ocrJson.reason || ocrJson.message || null,
                        exitCode: ocrJson.exit_code || null,
                        provider: ocrJson.raw_response || null
                    });
                    // Generic UI message only
                    vmStatus.textContent = 'Failed to scan ID via OCR. Please verify manually.';
                }
            } else {
                vmStatus.textContent = 'OCR skipped. Verifying account...';
                const verifyResp = await fetch('../controllers/account_verification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ id: currentId, verify: 1, ajax: 1 })
                });
                const verifyJson = await readJsonSafe(verifyResp);
                // Only trust explicit JSON success; do not infer from HTML/text
                const verifiedOk = verifyResp.ok && (verifyJson && (verifyJson.success === true || verifyJson.success === 'true'));
                if(verifiedOk){
                    vmStatus.textContent = 'User verified. Email sent.';
                    setTimeout(()=> window.location.reload(), 900);
                } else {
                    console.error('Manual verify error', verifyResp.status, verifyJson);
                    if (verifyJson && verifyJson.email_invalid) {
                        vmStatus.textContent = 'Email is not valid. Account not verified.';
                        vmOk.disabled = false;
                        vmCancel.disabled = false;
                        return;
                    }
                    vmStatus.textContent = 'Verification failed. Please try again.';
                }
            }
        } catch (err){
            console.error('Request error', err);
            vmStatus.textContent = 'Request failed. Please try again.';
        } finally {
            __verifyingInProgress = false;
            vmOk.disabled = false;
            vmCancel.disabled = false;
        }
    });

    // Confirm Invalid modal
    const confirmInvalid = document.getElementById('confirmInvalidModal');
    const cimCancel = document.getElementById('cim-cancel');
    const cimOk = document.getElementById('cim-ok');

    function openConfirmInvalid(){
        if (!confirmInvalid) return;
        // only open if currently hidden to avoid double-popup when scripts are duplicated
        if (confirmInvalid.classList.contains('hidden')){
            confirmInvalid.classList.remove('hidden');
            confirmInvalid.classList.add('flex');
        }
    }
    function closeConfirmInvalid(){ confirmInvalid.classList.add('hidden'); confirmInvalid.classList.remove('flex'); }

    cimCancel?.addEventListener('click', (e)=>{ e.preventDefault(); closeConfirmInvalid(); });

    async function sendInvalidThenDelete(residentId){
        vmStatus.textContent = 'Notifying user and removing account...';
        const notifyResp = await fetch('../controllers/account_verification.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ id: residentId, notify_invalid: 1, ajax: 1 })
        });
        const notifyJson = await notifyResp.json();
        if(notifyJson && notifyJson.success){
            const removeResp = await fetch('../controllers/account_verification.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ id: residentId, remove: 1 })
            });
            if (removeResp.ok) {
                vmStatus.textContent = 'Invalid email sent. Account deleted.';
                setTimeout(()=> window.location.reload(), 1000);
            } else {
                vmStatus.textContent = 'Email sent, but failed to delete account.';
            }
        } else {
            vmStatus.textContent = 'Failed to send invalid notification.';
        }
    }

    cimOk?.addEventListener('click', async (e)=>{
        e.preventDefault();
        closeConfirmInvalid();
        await sendInvalidThenDelete(currentId);
    });

    // --- New: Confirm Invalid Email (email doesn't exist) handlers ---
    const confirmInvalidEmail = document.getElementById('confirmInvalidEmailModal');
    const cieCancel = document.getElementById('cie-cancel');
    const cieOk = document.getElementById('cie-ok');

    function openConfirmInvalidEmail(){
        if (!confirmInvalidEmail) return;
        if (confirmInvalidEmail.classList.contains('hidden')){
            confirmInvalidEmail.classList.remove('hidden');
            confirmInvalidEmail.classList.add('flex');
        }
    }
    function closeConfirmInvalidEmail(){ if(confirmInvalidEmail){ confirmInvalidEmail.classList.add('hidden'); confirmInvalidEmail.classList.remove('flex'); } }

    cieCancel?.addEventListener('click', (e)=>{ e.preventDefault(); closeConfirmInvalidEmail(); });

    async function removeAccountAjax(residentId){
        vmStatus.textContent = 'Removing account...';
        const removeResp = await fetch('../controllers/account_verification.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({ id: residentId, remove: 1, ajax: 1 })
        });
        try {
            const j = await removeResp.json();
            if (j && j.success) {
                vmStatus.textContent = 'Account removed.';
                setTimeout(()=> window.location.reload(), 900);
            } else {
                vmStatus.textContent = 'Failed to remove account.';
            }
        } catch (e) {
            vmStatus.textContent = 'Failed to remove account.';
        }
    }

    cieOk?.addEventListener('click', async (e)=>{
        e.preventDefault();
        closeConfirmInvalidEmail();
        await removeAccountAjax(currentId);
    });
});
    </script>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>

<!-- Verify Confirmation Modal -->
<div id="verifyModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl overflow-hidden transform transition-all">
        <!-- Premium Header -->
        <div class="bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-5 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-white opacity-5 rounded-full -mr-16 -mt-16"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 bg-white opacity-5 rounded-full -ml-12 -mb-12"></div>
            <div class="relative z-10 flex items-center gap-3">
                <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center shadow-lg">
                    <i class="fas fa-user-check text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">Account Verification</h3>
                    <p class="text-primary-100 text-sm mt-0.5">Review resident details before confirming</p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6">
            <!-- Resident Details Section -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                <!-- Left Column: Personal Info -->
                <div class="space-y-3">
                    <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 rounded-xl p-4 border border-blue-200">
                        <h4 class="text-sm font-semibold text-primary-900 mb-3 flex items-center gap-2">
                            <i class="fas fa-user text-primary-600"></i>
                            Personal Information
                        </h4>
                        <div class="space-y-2.5 text-sm">
                            <div class="flex items-start">
                                <span class="text-gray-600 font-medium w-28">First Name:</span>
                                <span id="vm-first" class="text-gray-900 font-semibold flex-1">—</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-gray-600 font-medium w-28">Middle Name:</span>
                                <span id="vm-middle" class="text-gray-900 font-semibold flex-1">—</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-gray-600 font-medium w-28">Last Name:</span>
                                <span id="vm-last" class="text-gray-900 font-semibold flex-1">—</span>
                            </div>
                            <div class="flex items-start pt-2 border-t border-blue-200">
                                <span class="text-gray-600 font-medium w-28">Email:</span>
                                <span id="vm-email" class="text-primary-700 font-semibold flex-1 break-all">—</span>
                            </div>
                            <div class="flex items-start">
                                <span class="text-gray-600 font-medium w-28">Address:</span>
                                <span id="vm-address" class="text-gray-900 font-semibold flex-1">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Valid ID -->
                <div class="space-y-3">
                    <div class="bg-gradient-to-br from-green-50 to-green-100/50 rounded-xl p-4 border border-green-200">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-semibold text-green-900 flex items-center gap-2">
                                <i class="fas fa-id-card text-green-600"></i>
                                Valid ID Document
                            </h4>
                            <button id="vm-view-full" type="button" class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg bg-white hover:bg-green-50 text-green-700 border border-green-300 shadow-sm transition-all">
                                <i class="fa-solid fa-expand"></i>
                                <span>Fullscreen</span>
                            </button>
                        </div>
                        <div id="vm-validid-wrap" class="border-2 border-dashed border-green-300 rounded-xl p-3 h-40 flex items-center justify-center overflow-hidden bg-white/80 backdrop-blur-sm">
                            <img id="vm-validid-img" src="" alt="Valid ID" class="max-h-36 object-contain hidden cursor-zoom-in hover:scale-105 transition-transform" />
                            <div id="vm-validid-no" class="text-center">
                                <i class="fas fa-image text-gray-300 text-3xl mb-2"></i>
                                <p class="text-xs text-gray-500">No ID uploaded</p>
                            </div>
                        </div>
                        
                        <!-- OCR Toggle -->
                        <div class="mt-3 bg-white rounded-lg p-3 border border-green-200">
                            <label class="inline-flex items-start gap-2.5 cursor-pointer group">
                                <input type="checkbox" id="vm-ocr-toggle" checked class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-2 focus:ring-primary-500">
                                <div class="flex-1">
                                    <span class="text-sm font-medium text-gray-900 group-hover:text-primary-700">Enable OCR Verification</span>
                                    <p class="text-xs text-gray-500 mt-0.5">Automatically verify First Name, Last Name, and Barangay</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Message -->
            <div id="vm-status" class="hidden mb-4 p-4 rounded-xl bg-blue-50 border border-blue-200 text-sm text-blue-800">
                <div class="flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-blue-600"></i>
                    <span class="font-medium">Processing...</span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-200">
                <button id="vm-cancel" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium shadow-sm transition-all">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button id="vm-ok" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white font-semibold shadow-lg hover:shadow-xl transition-all">
                    <i class="fas fa-check-circle"></i>
                    <span>Verify Account</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fullscreen Image Modal -->
<div id="imgPreviewModal" class="img-modal fixed inset-0 hidden items-center justify-center z-50">
    <div class="relative bg-black/60 rounded-lg p-2">
        <button id="imgClose" class="absolute -top-10 right-0 text-white text-xl"><i class="fa-solid fa-xmark"></i></button>
        <img id="imgPreview" src="" alt="ID Preview" class="img-viewport object-contain rounded shadow-lg">
    </div>
</div>

<!-- NEW: Confirm Invalid + Remove Modal -->
<div id="confirmInvalidModal" class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm hidden items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
        <!-- Premium Header -->
        <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white opacity-5 rounded-full -mr-12 -mt-12"></div>
            <div class="relative z-10 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-white">Invalid ID Notification</h3>
                    <p class="text-red-100 text-xs mt-0.5">OCR verification failed</p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-5">
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-red-600 mt-0.5"></i>
                    <div>
                        <p class="text-sm text-red-900 font-medium mb-1">OCR did not match the required details</p>
                        <p class="text-xs text-red-700">The system will notify the user via email to submit a valid ID and remove this pending account from the system.</p>
                        <p class="text-xs text-red-700">If this is a mistake please disable OCR and manually verify the user.</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <button id="cim-cancel" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium shadow-sm transition-all">
                    <i class="fas fa-times"></i>
                    <span>Cancel</span>
                </button>
                <button id="cim-ok" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-semibold shadow-lg hover:shadow-xl transition-all">
                    <i class="fas fa-paper-plane"></i>
                    <span>Send & Remove</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    // Prevent duplicate initialization from second script block
    if (window.__notifSecInit) return;
    const modal = document.getElementById('verifyModal');
    const vmName = document.getElementById('vm-name'); // legacy
    const vmFirst = document.getElementById('vm-first');
    const vmLast = document.getElementById('vm-last');
    const vmMiddle = document.getElementById('vm-middle');
    const vmEmail = document.getElementById('vm-email');
    const vmAddress = document.getElementById('vm-address');
    const vmValidImg = document.getElementById('vm-validid-img');
    const vmValidNo = document.getElementById('vm-validid-no');
    const vmOcr = document.getElementById('vm-ocr-toggle');
    const vmStatus = document.getElementById('vm-status');
    const vmOk = document.getElementById('vm-ok');
    const vmCancel = document.getElementById('vm-cancel');
    const vmViewFull = document.getElementById('vm-view-full');

    // Fullscreen modal
    const imgModal = document.getElementById('imgPreviewModal');
    const imgPreview = document.getElementById('imgPreview');
    const imgClose = document.getElementById('imgClose');

    let currentId = 0;

    function openImgModal() {
        if (!vmValidImg.src) return;
        imgPreview.src = vmValidImg.src;
        imgModal.classList.remove('hidden'); imgModal.classList.add('flex');
    }
    function closeImgModal() {
        imgModal.classList.add('hidden'); imgModal.classList.remove('flex');
        imgPreview.src = '';
    }

    vmViewFull.addEventListener('click', openImgModal);
    vmValidImg.addEventListener('click', openImgModal);
    imgClose.addEventListener('click', closeImgModal);
    imgModal.addEventListener('click', (e)=>{ if(e.target === imgModal) closeImgModal(); });

    function openModal(button){
        currentId = button.dataset.id || 0;
        const first = button.dataset.first || '';
        const last  = button.dataset.last  || '';
        vmFirst.textContent = first || '—';
        vmLast.textContent  = last || '—';
        // keep legacy "Name" for compatibility
        if (vmName) vmName.textContent = (first + (last ? (' ' + last) : '')).trim();
        vmMiddle.textContent = button.dataset.middle || '—';
        vmEmail.textContent = button.dataset.email || '—';
        vmAddress.textContent = button.dataset.address || '—';
        const validPath = button.dataset.validid || '';
        if(validPath && validPath !== ''){
            vmValidImg.src = '../' + validPath;
            vmValidImg.classList.remove('hidden');
            vmValidNo.classList.add('hidden');
        } else {
            vmValidImg.src = '';
            vmValidImg.classList.add('hidden');
            vmValidNo.classList.remove('hidden');
        }
        vmStatus.classList.add('hidden');
        vmStatus.textContent = '';
        vmOcr.checked = true;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal(){
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        currentId = 0;
    }

    document.querySelectorAll('.open-verify-modal').forEach(btn=>{
        btn.addEventListener('click', ()=> openModal(btn));
    });

    vmCancel.addEventListener('click', (e)=>{
        e.preventDefault();
        closeModal();
    });

    modal.addEventListener('click', (e)=>{
        if(e.target === modal) closeModal();
    });

    vmOk.addEventListener('click', async (e)=>{
        e.preventDefault();
        if(!currentId) return;
        vmOk.disabled = true;
        vmCancel.disabled = true;
        vmStatus.classList.remove('hidden');
        vmStatus.textContent = 'Processing...';

        try {
            if(vmOcr.checked){
                const ocrResp = await fetch('../controllers/verify_resident.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ id: currentId, useOCR: 1 })
                });
                const ocrJson = await readJsonSafe(ocrResp);

                if(ocrResp.ok && ocrJson && ocrJson.success){
                    vmStatus.textContent = 'OCR matched. Verifying account...';
                    const verifyResp = await fetch('../controllers/account_verification.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({ id: currentId, verify: 1, ajax: 1 })
                    });
                    const verifyJson = await readJsonSafe(verifyResp);
                    const verifiedOk = verifyResp.ok && (
                        (verifyJson && (verifyJson.success === true || verifyJson.success === 'true')) ||
                        (verifyJson && typeof verifyJson.raw === 'string' && /success|verified|email sent/i.test(verifyJson.raw))
                    );
                    if(verifiedOk){
                        vmStatus.textContent = 'User verified. Email sent.';
                        setTimeout(()=> window.location.reload(), 900);
                        return;
                    } else {
                        console.error('Verify error', verifyResp.status, verifyJson);
                        if (verifyJson && verifyJson.email_invalid) {
                            vmStatus.textContent = 'Email does not exist or is invalid. User was NOT verified.';
                            const confirmEmail = document.getElementById('confirmInvalidEmailModal');
                            if (confirmEmail) openConfirmInvalidEmail();
                            vmOk.disabled = false;
                            vmCancel.disabled = false;
                            return;
                        }
                        vmStatus.textContent = 'Verification failed. Please try again.';
                    }
                } else if (ocrResp.ok && ocrJson && ocrJson.matches){
                    // OCR ran but required fields didn’t all match
                    console.warn('OCR mismatch details', ocrJson.matches);
                    vmStatus.innerHTML = 'Details did not match.<br>'
                      + 'First: ' + (ocrJson.matches.first_name ? '✓' : '✗') + ' • '
                      + 'Last: ' + (ocrJson.matches.last_name ? '✓' : '✗') + ' • '
                      + 'Barangay: ' + (ocrJson.matches.barangay ? '✓' : '✗');
                    // Ask confirmation to notify+remove
                    const confirmInvalid = document.getElementById('confirmInvalidModal');
                    if (confirmInvalid) openConfirmInvalid();
                } else {
                    console.error('OCR scan failed', {
                        httpStatus: ocrResp.status,
                        code: ocrJson.code || null,
                        reason: ocrJson.reason || ocrJson.message || null,
                        exitCode: ocrJson.exit_code || null,
                        provider: ocrJson.raw_response || null
                    });
                    // Generic UI message only
                    vmStatus.textContent = 'Failed to scan ID via OCR. Please verify manually.';
                }
            } else {
                vmStatus.textContent = 'OCR skipped. Verifying account...';
                const verifyResp = await fetch('../controllers/account_verification.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({ id: currentId, verify: 1, ajax: 1 })
                });
                const verifyJson = await readJsonSafe(verifyResp);
                const verifiedOk = verifyResp.ok && (
                    (verifyJson && (verifyJson.success === true || verifyJson.success === 'true')) ||
                    (verifyJson && typeof verifyJson.raw === 'string' && /success|verified|email sent/i.test(verifyJson.raw))
                );
                if(verifiedOk){
                    vmStatus.textContent = 'User verified. Email sent.';
                    setTimeout(()=> window.location.reload(), 900);
                } else {
                    console.error('Manual verify error', verifyResp.status, verifyJson);
                    if (verifyJson && verifyJson.email_invalid) {
                        vmStatus.textContent = 'Email does not exist or is invalid. User was NOT verified.';
                        const confirmEmail = document.getElementById('confirmInvalidEmailModal');
                        if (confirmEmail) openConfirmInvalidEmail();
                        vmOk.disabled = false;
                        vmCancel.disabled = false;
                        return;
                    }
                    vmStatus.textContent = 'Verification failed. Please try again.';
                }
            }
        } catch (err){
            console.error('Request error', err);
            vmStatus.textContent = 'Request failed. Please try again.';
        } finally {
            vmOk.disabled = false;
            vmCancel.disabled = false;
        }
    });

    // Removed invalid-email modal and related handlers per request: invalid emails will now only show a message
    // and will NOT offer a modal-based removal flow.
});
</script>