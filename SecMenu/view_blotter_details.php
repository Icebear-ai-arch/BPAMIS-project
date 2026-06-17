<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

$blotter_id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM BLOTTER_INFO WHERE Blotter_ID = ?");
$stmt->bind_param("i", $blotter_id);
$stmt->execute();
$result = bpamis_stmt_get_result($stmt);
$blotter = $result->fetch_assoc();
$stmt->close();

// Fetch reporter name if Reported_By is an ID
$reporter_name = $blotter['Reported_By'];
if (is_numeric($blotter['Reported_By'])) {
    $stmt = $conn->prepare("SELECT CONCAT(First_Name, ' ', Last_Name) AS full_name FROM resident_info WHERE resident_id = ?");
    $stmt->bind_param("i", $blotter['Reported_By']);
    $stmt->execute();
    $res = bpamis_stmt_get_result($stmt);
    if ($row = $res->fetch_assoc()) {
        $reporter_name = $row['full_name'];
    }
    $stmt->close();
}

// -------------------- UPLOAD KASUNDUAN (AGREEMENT) FOR BLOTTER --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_kasunduan'])) {
    $posted_id = intval($_POST['blotter_id'] ?? 0);
    if ($posted_id > 0) {
        $fileArr = $_FILES['kasunduan_file'] ?? null;
        if ($fileArr && isset($fileArr['error']) && $fileArr['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','pdf'];
            $ext = strtolower(pathinfo($fileArr['name'], PATHINFO_EXTENSION));
            $size = (int)$fileArr['size'];
            if (in_array($ext, $allowed, true) && $size <= 20*1024*1024) {
                $baseUpload = __DIR__ . '/../uploads/blotter/' . $posted_id . '/';
                if (!is_dir($baseUpload)) { @mkdir($baseUpload, 0777, true); }
                $fname = 'kasunduan_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $baseUpload . $fname;
                if (move_uploaded_file($fileArr['tmp_name'], $dest)) {
                    // Ensure column exists
                    $colChk = $conn->query("SHOW COLUMNS FROM blotter_info LIKE 'Kasunduan_Path'");
                    if (!$colChk || $colChk->num_rows === 0) {
                        $conn->query("ALTER TABLE blotter_info ADD COLUMN Kasunduan_Path VARCHAR(255) NULL");
                    }
                    if ($colChk) { $colChk->close(); }

                    $rel = 'uploads/blotter/' . $posted_id . '/' . $fname;
                    $stmtUp = $conn->prepare("UPDATE blotter_info SET Kasunduan_Path = ? WHERE Blotter_ID = ?");
                    if ($stmtUp) { $stmtUp->bind_param('si', $rel, $posted_id); $stmtUp->execute(); $stmtUp->close(); }
                    header('Location: view_blotter_details.php?id=' . $posted_id . '&kasunduan=1');
                    exit;
                }
            }
        }
    }
}

// Handle validation submitted from modal: update complaint case_type and set Status='Mediation'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_case'], $_POST['blotter_id'])) {
    $decision = $_POST['validate_decision'] ?? '';
    $blotter_id = intval($_POST['blotter_id']);

    if ($decision !== 'yes') {
        echo "<script>alert('Please confirm validation by selecting Yes.');</script>";
    } else {
        $case_type = trim($_POST['case_type'] ?? '');
        // Load allowed types from CASE_TYPES (exclude 'Record Purposes') and include 'Others'
        $allowed_types = [];
        $ctChk = $conn->query("SHOW TABLES LIKE 'CASE_TYPES'");
        if ($ctChk && $ctChk->num_rows > 0) {
            $rs = $conn->query("SELECT Case_Type FROM CASE_TYPES ORDER BY Case_Type ASC");
            if ($rs) {
                while ($r = $rs->fetch_assoc()) {
                    $t = trim($r['Case_Type']);
                    if (strcasecmp($t, 'Record Purposes') === 0) continue;
                    if ($t !== '') $allowed_types[] = $t;
                }
                $rs->close();
            }
        }
        if (!in_array('Others', $allowed_types, true)) $allowed_types[] = 'Others';

        // Handle Others typed value if present
        $other_case_type = '';
        if ($case_type === 'Others') {
            $other_case_type = trim($_POST['other_case_type'] ?? '');
            if ($other_case_type === '') {
                echo "<script>alert('Please enter the case type when selecting Others.');</script>";
                // stop processing
                goto VB_END;
            } else {
                // persist into CASE_TYPES if table exists (create if needed)
                $conn->query("CREATE TABLE IF NOT EXISTS CASE_TYPES (Type_ID INT AUTO_INCREMENT PRIMARY KEY, Case_Type VARCHAR(191) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                $ins = $conn->prepare("INSERT IGNORE INTO CASE_TYPES (Case_Type) VALUES (?)");
                if ($ins) { $ins->bind_param('s', $other_case_type); $ins->execute(); $ins->close(); }
                $case_type = $other_case_type;
            }
        }

        // Validate chosen type
        if (!in_array($case_type, $allowed_types, true) && $case_type !== $other_case_type) {
            echo "<script>alert('Please choose a valid case type.');</script>";
            goto VB_END;
        }

        // Find linked complaint
        $stmt = $conn->prepare("SELECT Complaint_ID FROM blotter_info WHERE Blotter_ID = ? LIMIT 1");
        $stmt->bind_param('i', $blotter_id);
        $stmt->execute();
        $res = bpamis_stmt_get_result($stmt);
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row || empty($row['Complaint_ID'])) {
            echo "<script>alert('No linked complaint found for this blotter.');</script>";
            goto VB_END;
        }
        $complaint_id = (int)$row['Complaint_ID'];

        // Update complaint_info with new case_type and Status='Mediation'
        $u = $conn->prepare("UPDATE COMPLAINT_INFO SET case_type = ?, Status = 'Mediation' WHERE Complaint_ID = ?");
        if ($u) {
            $u->bind_param('si', $case_type, $complaint_id);
            if ($u->execute()) {
                // Notify complainant
                $cr = $conn->query("SELECT Resident_ID, External_Complainant_ID FROM COMPLAINT_INFO WHERE Complaint_ID = $complaint_id");
                if ($cr && $cr->num_rows > 0) {
                    $r = $cr->fetch_assoc();
                    $rid = $r['Resident_ID'];
                    $eid = $r['External_Complainant_ID'];
                    $title = 'Complaint Converted to Case';
                    $msg = "Your complaint has been validated as a $case_type case.";
                    $now = date('Y-m-d H:i:s');
                    $type = 'Case';
                    if (!empty($rid)) {
                        $stmt4 = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                        if ($stmt4) { $stmt4->bind_param('issss', $rid, $title, $msg, $type, $now); $stmt4->execute(); $stmt4->close(); }
                    } elseif (!empty($eid)) {
                        $stmt4 = $conn->prepare("INSERT INTO notifications (external_complaint_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
                        if ($stmt4) { $stmt4->bind_param('issss', $eid, $title, $msg, $type, $now); $stmt4->execute(); $stmt4->close(); }
                    }
                }

                header("Location: view_blotter_details.php?id={$blotter_id}&converted=1");
                exit;
            } else {
                $err = $conn->error;
                echo "<script>alert('DB error: " . addslashes($err) . "');</script>";
            }
            $u->close();
        }
    }
}
VB_END:;


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blotter Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { primary: { 50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'} }, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.08),0 4px 20px -2px rgba(6,90,143,.18)'}, animation:{'float':'float 3s ease-in-out infinite','fade-in':'fadeIn .4s ease-out'}, keyframes:{ float:{'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-10px)'}}, fadeIn:{'0%':{opacity:0},'100%':{opacity:1}} } } } };
    </script>
    <style>
        .glass{background:linear-gradient(140deg,rgba(255,255,255,.92),rgba(255,255,255,.68));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);} 
        .field-label{font-size:11px;letter-spacing:.05em;font-weight:600;text-transform:uppercase;color:#64748b;} 
        .bg-orbs:before,.bg-orbs:after{content:"";position:absolute;border-radius:9999px;filter:blur(70px);opacity:.35}
        .bg-orbs:before{width:480px;height:480px;background:linear-gradient(135deg,#7cccfd,#0c9ced);top:-160px;left:-140px}
        .bg-orbs:after{width:420px;height:420px;background:linear-gradient(135deg,#bae2fd,#7cccfd);bottom:-140px;right:-120px}
    </style>
</head>
<body class="font-sans antialiased bg-gradient-to-br from-primary-50 via-white to-primary-100 min-h-screen text-gray-800 relative overflow-x-hidden bg-orbs">
        <?php include '../includes/barangay_official_sec_nav.php'; ?>
        <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 pt-8 pb-16 animate-fade-in">
            <div class="mb-6 flex items-center gap-3">
                <a href="view_blotter.php" class="group inline-flex items-center text-sm font-medium text-primary-700 hover:text-primary-900 transition">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white/70 shadow ring-1 ring-primary-100 group-hover:bg-primary-50"><i class="fa fa-arrow-left"></i></span>
                    <span class="ml-2">Back to Blotter</span>
                </a>
            </div>
            <section class="relative glass shadow-glow rounded-2xl p-6 md:p-10 border border-white/60 ring-1 ring-primary-100/40 overflow-hidden">
                <div class="absolute inset-0 pointer-events-none">
                    <div class="absolute -top-10 -right-10 w-40 h-40 rounded-full bg-gradient-to-br from-primary-200/60 to-primary-400/40 blur-2xl opacity-40"></div>
                </div>
                <header class="relative flex flex-col md:flex-row md:items-start gap-6 mb-8">
                    <div class="flex items-center">
                        <div class="w-20 h-20 rounded-2xl flex items-center justify-center bg-primary-50 ring-4 ring-primary-100 shadow-inner">
                            <i class="fa fa-file-circle-exclamation text-3xl text-primary-600"></i>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <?php
                            // Build a display id like "{Blotter_ID}-{month}-{yy}" e.g. 2-11-25
                            $display_blotter_id = (int)($blotter['Blotter_ID'] ?? $blotter_id);
                            $date_for_id = !empty($blotter['Date_Reported']) ? $blotter['Date_Reported'] : date('Y-m-d');
                            $month_part = (int)date('n', strtotime($date_for_id));
                            $year_part = date('y', strtotime($date_for_id));
                            $formatted_blotter_ref = $display_blotter_id . '-' . $month_part . '-' . $year_part;
                        ?>
                        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Blotter Case <?= htmlspecialchars($formatted_blotter_ref) ?></span>
                            <span class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1 rounded-full bg-amber-50 text-amber-600 border border-amber-200 shadow-sm"><i class="fa fa-circle text-[8px]"></i> Blotter</span>
                        </h1>
                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm text-gray-500">
                            <span class="inline-flex items-center gap-1"><i class="fa fa-user"></i> <?= htmlspecialchars($reporter_name) ?></span>
                            <?php if(!empty($blotter['Date_Reported'])): ?>
                            <span class="inline-flex items-center gap-1"><i class="fa fa-calendar"></i> <?= date('F d, Y', strtotime($blotter['Date_Reported'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>
                <div class="space-y-8">
                    <?php if ($blotter): ?>
                    <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                        <p class="field-label mb-1">Description</p>
                        <p class="text-gray-700 leading-relaxed whitespace-pre-line"><?= nl2br(htmlspecialchars($blotter['Blotter_Description'])) ?></p>
                    </div>
                    <?php
                        // Show kasunduan / attachment for blotter if present in row
                        $blotter_attachments = [];
                        // common column names to check
                        $possibleCols = ['Kasunduan_Path','Kasunduan','Attachment_Path','Agreement_Path','File_Path','Blotter_Attachment'];
                        foreach ($possibleCols as $pc) {
                            if (isset($blotter[$pc]) && !empty($blotter[$pc])) {
                                $raw = $blotter[$pc];
                                // Normalize path
                                $clean = str_replace('..', '', $raw);
                                $clean = str_replace('\\', '/', $clean);
                                $clean = ltrim($clean, '/');
                                $encoded = implode('/', array_map('rawurlencode', explode('/', $clean)));
                                $is_image = (bool)preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $clean);
                                $is_pdf = (bool)preg_match('/\.pdf$/i', $clean);
                                $blotter_attachments[] = ['label'=>$pc, 'raw'=>$clean, 'url'=>$encoded, 'is_image'=>$is_image, 'is_pdf'=>$is_pdf];
                            }
                        }
                    ?>
                    <?php if (!empty($blotter_attachments)): ?>
                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Kasunduan / Attachment</h2>
                        <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
                            <?php foreach ($blotter_attachments as $att): ?>
                                <div class="group relative rounded-xl border bg-white/70 border-gray-200 hover:border-primary-300 hover:shadow-glow transition overflow-hidden">
                                    <div class="aspect-video w-full bg-gray-100 flex items-center justify-center overflow-hidden">
                                        <?php if ($att['is_image']): ?>
                                            <img src="../<?= htmlspecialchars($att['url']) ?>" alt="Kasunduan" class="w-full h-full object-cover object-center group-hover:scale-105 transition" />
                                        <?php elseif ($att['is_pdf']): ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-file-pdf text-3xl mb-1"></i>
                                                PDF File
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-paperclip text-3xl mb-1"></i>
                                                File
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/45 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        <div class="flex gap-2">
                                            <?php if ($att['is_image']): ?>
                                                <button type="button" onclick="previewImage('../<?= htmlspecialchars($att['url']) ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white/90 hover:bg-white text-primary-700 text-xs font-medium"><i class="fa fa-eye"></i> View</button>
                                            <?php endif; ?>
                                            <a href="../<?= htmlspecialchars($att['url']) ?>" download class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-medium"><i class="fa fa-download"></i> Download</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($blotter): ?>
                    <div class="mt-4">
                        <form method="POST" enctype="multipart/form-data" class="rounded-xl border bg-white/70 border-gray-200 p-4 shadow-sm">
                            <input type="hidden" name="blotter_id" value="<?= htmlspecialchars($blotter['Blotter_ID']) ?>" />
                            <input type="hidden" name="upload_kasunduan" value="1" />
                            <label class="field-label">Upload Kasunduan (Agreement) <span class="text-xs text-gray-400">(JPG, PNG, PDF — max 20MB)</span></label>
                            <div class="flex items-center gap-3">
                                <input type="file" name="kasunduan_file" accept=".jpg,.jpeg,.png,.pdf" class="input-base" required />
                                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium"><i class="fa fa-upload"></i> Upload</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Reported By</p>
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($reporter_name) ?></p>
                        </div>
                        <div class="group rounded-xl border bg-white/70 border-gray-200 hover:border-primary-200 transition p-4 shadow-sm">
                            <p class="field-label mb-1">Date Reported</p>
                            <p class="font-semibold text-gray-800"><?= htmlspecialchars($blotter['Date_Reported']) ?></p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                        <div class="flex flex-wrap gap-2">
                            <a href="view_blotter.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back</a>
                            <?php
                                $status_check = $conn->prepare("SELECT b.Complaint_ID, c.case_type, c.Status FROM blotter_info b LEFT JOIN complaint_info c ON b.Complaint_ID = c.Complaint_ID WHERE b.Blotter_ID = ?");
                                $status_check->bind_param("i", $blotter['Blotter_ID']);
                                $status_check->execute();
                                $status_res = bpamis_stmt_get_result($status_check);
                                $case_data = $status_res->fetch_assoc();
                                $status_check->close();
                                ?>
                                <?php
                                    // Show 'Validate / Convert to Case' when there is a linked complaint and it's not already set to Mediation
                                    $hasComplaint = ($case_data && !empty($case_data['Complaint_ID']));
                                    $currentStatus = isset($case_data['Status']) ? strtoupper(trim($case_data['Status'])) : '';
                                ?>
                                <?php if ($hasComplaint && $currentStatus !== 'MEDIATION'): ?>
                                    <button onclick="openCaseTypeModal()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition"><i class="fa fa-arrow-right"></i> Validate / Convert to Case</button>
                                <?php elseif ($hasComplaint): ?>
                                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium cursor-default opacity-90"><i class="fa fa-check"></i> Already marked as <?= htmlspecialchars($case_data['case_type'] ?? $currentStatus) ?> (<?= htmlspecialchars(ucfirst(strtolower($currentStatus))) ?>)</span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-200 text-gray-600 text-sm font-medium cursor-default opacity-90"><i class="fa fa-ban"></i> No linked complaint</span>
                                <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <div class="bg-red-50 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Blotter Report Not Found</h3>
                        <p class="text-gray-600 mb-6">The blotter report you're looking for doesn't exist or has been removed.</p>
                        <a href="view_blotter.php" class="inline-flex items-center bg-primary-500 text-white py-3 px-6 rounded-lg hover:bg-primary-600 transition shadow-sm">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to List
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
            <?php if ($blotter): ?>
                
              
            <?php else: ?>
                <!-- Error State -->
                <div class="text-center py-12">
                    <div class="bg-red-50 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-exclamation-triangle text-red-500 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Blotter Report Not Found</h3>
                    <p class="text-gray-600 mb-6">The blotter report you're looking for doesn't exist or has been removed.</p>
                    <a href="view_blotter.php" class="card-hover inline-flex items-center bg-primary-500 text-white py-3 px-6 rounded-lg hover:bg-primary-600 transition">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to List
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

        <?php include 'sidebar_.php';?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile navigation toggle
            if (typeof menuButton !== 'undefined' && typeof mobileMenu !== 'undefined') {
                menuButton.addEventListener('click', function() {
                    this.classList.toggle('active');
                    if (mobileMenu.style.transform === 'translateY(0%)') {
                        mobileMenu.style.transform = 'translateY(-100%)';
                    } else {
                        mobileMenu.style.transform = 'translateY(0%)';
                    }
                });
            }
        });
    </script>
    <?php include '../chatbot/bpamis_case_assistant.php'?>


<!-- Case Type Selection Modal -->
<div id="caseTypeModal" class="fixed inset-0 bg-gray-800/60 backdrop-blur-sm flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-xl shadow-lg w-96 p-6 relative">
        <h3 class="text-xl font-semibold text-gray-800 mb-4">Select Case Type</h3>
        <p class="text-gray-600 mb-6">Please choose what type of case this blotter will proceed to.</p>

        <?php
            // Populate available case types from CASE_TYPES table, excluding 'Record Purposes'
            $caseOptions = [];
            $ctChk = $conn->query("SHOW TABLES LIKE 'CASE_TYPES'");
            if ($ctChk && $ctChk->num_rows > 0) {
                $rs = $conn->query("SELECT Case_Type FROM CASE_TYPES WHERE Case_Type IS NOT NULL ORDER BY Case_Type ASC");
                if ($rs) {
                    while ($r = $rs->fetch_assoc()) {
                        $t = trim($r['Case_Type']);
                        if (strcasecmp($t, 'Record Purposes') === 0) continue;
                        if ($t !== '') $caseOptions[] = $t;
                    }
                    $rs->close();
                }
            }
            // Fallback options if table empty
            if (empty($caseOptions)) $caseOptions = ['Civil', 'Criminal'];
        ?>
        <form id="convertFormModal" method="POST" action="" onsubmit="return confirm('Convert this complaint into a case?');">
            <input type="hidden" name="blotter_id" value="<?= htmlspecialchars($blotter['Blotter_ID']) ?>">
            <input type="hidden" name="validate_decision" value="yes" />
            <div class="flex flex-col gap-3">
                <label class="block text-xs font-medium text-gray-600 mb-1">Case Type</label>
                <div class="relative">
                    <select name="case_type" id="modalCaseTypeSelect" required class="w-full appearance-none px-3 py-2 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 bg-white/90">
                        <option value="">Select Case Type</option>
                        <?php
                            // include the dynamic options we built earlier ($caseOptions)
                            $seen_modal = [];
                            foreach ($caseOptions as $co) {
                                if (in_array($co, $seen_modal, true)) continue;
                                $seen_modal[] = $co;
                                echo '<option value="'.htmlspecialchars($co).'">'.htmlspecialchars($co).'</option>';
                            }
                        ?>
                        <option value="Others">Others</option>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400"><i class="fa fa-caret-down"></i></div>
                </div>

                <input type="text" id="modalOtherCaseInput" name="other_case_type" placeholder="Specify other case type" class="px-3 py-2 rounded-lg border border-gray-300 bg-white/90 hidden" />

                <button id="modalConvertBtn" type="submit" name="validate_case" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow text-sm font-medium transition disabled:opacity-60 disabled:cursor-not-allowed" disabled><i class="fa fa-gavel"></i> Convert to Case</button>
            </div>
        </form>

        <button onclick="closeCaseTypeModal()" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

<script>
function openCaseTypeModal() {
    document.getElementById('caseTypeModal').classList.remove('hidden');
}
function closeCaseTypeModal() {
    document.getElementById('caseTypeModal').classList.add('hidden');
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var sel = document.getElementById('modalCaseTypeSelect');
    var other = document.getElementById('modalOtherCaseInput');
    var btn = document.getElementById('modalConvertBtn');
    if (sel) {
        sel.addEventListener('change', function(){
            var v = sel.value;
            if (!v) {
                btn.disabled = true;
                if (other) { other.classList.add('hidden'); other.value = ''; other.removeAttribute('required'); }
                return;
            }
            if (v === 'Others') {
                if (other) { other.classList.remove('hidden'); other.setAttribute('required',''); btn.disabled = other.value.trim().length === 0; }
                else btn.disabled = true;
            } else {
                if (other) { other.classList.add('hidden'); other.value = ''; other.removeAttribute('required'); }
                btn.disabled = false;
            }
        });
    }
    if (other) {
        other.addEventListener('input', function(){
            btn.disabled = other.value.trim().length === 0;
        });
    }
});
</script>
</script>
</script>

<!-- Image Preview Modal for attachments -->
<div id="imgPreviewModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-6">
    <div class="relative max-w-4xl w-full">
        <button onclick="closePreview()" class="absolute -top-4 -right-4 w-10 h-10 rounded-full bg-white text-gray-700 flex items-center justify-center shadow-lg hover:bg-primary-600 hover:text-white transition"><i class="fa fa-xmark text-lg"></i></button>
        <div class="bg-white rounded-2xl overflow-hidden shadow-glow ring-1 ring-primary-200/40">
            <img id="imgPreviewTag" src="" alt="Preview" class="w-full max-h-[80vh] object-contain bg-black" />
        </div>
    </div>
</div>

<script>
function previewImage(src){
    var modal = document.getElementById('imgPreviewModal');
    var img = document.getElementById('imgPreviewTag');
    if(!modal || !img) return;
    img.src = src;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}
function closePreview(){
    var modal = document.getElementById('imgPreviewModal');
    if(!modal) return;
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}
</script>
</body>
</html>
