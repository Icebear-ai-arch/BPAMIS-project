<?php
// External Submit Complaints (self-processing) - adds record to COMPLAINT_INFO like resident version
include '../controllers/session_control.php';
include '../server/server.php';
// Redirect if not logged in (external user)
if(!isset($_SESSION['external_id']) && !isset($_SESSION['user_id'])){ header('Location: ../bpamis_website/login.php'); exit; }
$external_id = $_SESSION['external_id'] ?? $_SESSION['user_id'];

include_once '../server/server.php'; // provides $conn

// Helper for cleanup
function clean_name($n){ return preg_replace('/[^A-Za-z0-9_.-]/','_', $n); }

$external_name = '';
if(isset($conn)){
    if($stmt = $conn->prepare("SELECT First_Name, Middle_Name, Last_Name FROM external_complainant WHERE External_Complaint_ID = ? LIMIT 1")){
        $stmt->bind_param('i',$external_id);
        $stmt->execute();
        $rs = bpamis_stmt_get_result($stmt);
        if($rw = $rs->fetch_assoc()){
            $parts = array_filter([$rw['First_Name']??'', $rw['Middle_Name']??'', $rw['Last_Name']??'']);
            $external_name = trim(implode(' ', $parts));
        }
        $stmt->close();
    }
}

// Expose external complainant DOB for client-side underage confirmation (if available)
$external_dob = '';
if(isset($conn) && $conn){
    if($stmtDobExt = $conn->prepare("SELECT COALESCE(Birthdate, birthdate, '') AS dob FROM external_complainant WHERE External_Complaint_ID = ? LIMIT 1")){
        $stmtDobExt->bind_param('i', $external_id);
        $stmtDobExt->execute();
        $resDobExt = bpamis_stmt_get_result($stmtDobExt);
        if($rdbx = $resDobExt->fetch_assoc()){ $external_dob = trim((string)$rdbx['dob']); }
        $stmtDobExt->close();
    }
}
// Normalize external DOB to ISO for reliable client-side parsing
$external_dob_iso = '';
if($external_dob !== ''){
    try {
        $dx = new DateTime($external_dob);
        $external_dob_iso = $dx->format('Y-m-d');
    } catch (Exception $e) {
        $tryx = DateTime::createFromFormat('Y-m-d', $external_dob);
        if($tryx instanceof DateTime) $external_dob_iso = $tryx->format('Y-m-d');
    }
}

// Process form submission
$insert_success = false; $error_message=''; $complaint_id = null; $respondent_names=[];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $complainant_name = trim($_POST['complainant_name'] ?? '');
    $respondent_raw   = $_POST['respondent_name'] ?? '';
    $incident_date    = trim($_POST['incident_date'] ?? '');
    $incident_time    = trim($_POST['incident_time'] ?? ''); // stored? (not in schema snippet) - ignored for now
    $description      = trim($_POST['complaint_description'] ?? '');

    // Parse Tagify JSON or comma list
    if(is_string($respondent_raw) && strlen($respondent_raw)){
        $trimmed = ltrim($respondent_raw);
        if(str_starts_with($trimmed,'[')){
            $decoded = json_decode($respondent_raw,true);
            if(is_array($decoded)){
                foreach($decoded as $d){ if(!empty($d['value'])) $respondent_names[] = trim($d['value']); }
            }
        } else {
            $respondent_names = array_filter(array_map('trim', preg_split('/\s*,\s*/',$respondent_raw)));
        }
    }

    if($description==='' || empty($respondent_names)){
        $error_message = 'Please provide required fields (respondent name(s), description).';
    } else {
        // Server-side under-18 check: block unless user confirmed via client-side modal
        

        // Derive a simple title from description
        $complaint_title = mb_substr($description,0,60);
        if($complaint_title==='') $complaint_title = 'Complaint '.date('Y-m-d H:i');

        // Use Asia/Manila date for Date_Filed (submission date)
        try{
            $dtNow = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $date_filed = $dtNow->format('Y-m-d');
        } catch (Exception $e) {
            $date_filed = date('Y-m-d');
        }

        // Attempt to resolve first respondent ID if it matches an existing resident (optional)
        $main_respondent_id = null;
        if(isset($conn)){
            if($res = $conn->query("SHOW COLUMNS FROM resident_info LIKE 'Resident_ID'")){
                // Build map only if table exists
                if($mapRs = $conn->query("SELECT Resident_ID, First_Name, Middle_Name, Last_Name FROM resident_info")){
                    $nameMap=[]; while($row=$mapRs->fetch_assoc()){ $parts=array_filter([$row['First_Name']??'', $row['Middle_Name']??'', $row['Last_Name']??'']); $nameMap[strtolower(preg_replace('/\s+/',' ',trim(implode(' ',$parts))))] = (int)$row['Resident_ID']; }
                    foreach($respondent_names as $nm){ $k=strtolower(preg_replace('/\s+/',' ',trim($nm))); if(isset($nameMap[$k])){ $main_respondent_id = $nameMap[$k]; break; } }
                }
            }
        }

        // Handle attachments (multi, 20MB limit)
        $attachment_path = null; $MAX_FILE_BYTES = 20*1024*1024; $stored=[]; $oversized=[];
        if(!empty($_FILES['complaint_attachment']['name'][0])){
            foreach($_FILES['complaint_attachment']['name'] as $i=>$name){
                if($_FILES['complaint_attachment']['error'][$i]===UPLOAD_ERR_OK){
                    if($_FILES['complaint_attachment']['size'][$i] > $MAX_FILE_BYTES){ $oversized[] = $name; }
                }
            }
            if(empty($oversized)){
                $uploadDir = __DIR__.'/../uploads/'; if(!is_dir($uploadDir)) @mkdir($uploadDir,0777,true);
                foreach($_FILES['complaint_attachment']['name'] as $i=>$name){
                    if($_FILES['complaint_attachment']['error'][$i]===UPLOAD_ERR_OK){
                        $safe = time().'_'.clean_name($name);
                        $target = $uploadDir.$safe;
                        if(move_uploaded_file($_FILES['complaint_attachment']['tmp_name'][$i], $target)){
                            $stored[] = 'uploads/'.$safe;
                        }
                    }
                }
                if($stored) $attachment_path = implode(';',$stored);
            } else {
                $error_message = 'The following files exceed 20MB: '.htmlspecialchars(implode(', ',$oversized));
            }
        }

        if($error_message===''){
            $status='Pending';
            // Insert into COMPLAINT_INFO using external user mapped into Resident_ID if schema requires; if an External_ID column exists, adjust as needed.
            if(isset($conn)){
                // Prefer external_complainant_id column; fallback to Resident_ID if external column not present
                $useExternalCol = false;
                if($meta = $conn->query("SHOW COLUMNS FROM COMPLAINT_INFO LIKE 'external_complainant_id'")){
                    if($meta->num_rows>0) $useExternalCol = true; $meta->close();
                }
                if($useExternalCol){
                    if($attachment_path){
                        $stmt = $conn->prepare("INSERT INTO COMPLAINT_INFO (external_complainant_id, Respondent_ID, Complaint_Title, Complaint_Details, incident_date, incident_time, Date_Filed, Status, Attachment_Path) VALUES (?,?,?,?,?,?,?,?,?)");
                        $stmt->bind_param('iisssssss', $external_id, $main_respondent_id, $complaint_title, $description, $incident_date, $incident_time, $date_filed, $status, $attachment_path);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO COMPLAINT_INFO (external_complainant_id, Respondent_ID, Complaint_Title, Complaint_Details, incident_date, incident_time, Date_Filed, Status) VALUES (?,?,?,?,?,?,?,?)");
                        $stmt->bind_param('iissssss', $external_id, $main_respondent_id, $complaint_title, $description, $incident_date, $incident_time, $date_filed, $status);
                    }
                } else { // fallback original Resident_ID behavior
                    if($attachment_path){
                        $stmt = $conn->prepare("INSERT INTO COMPLAINT_INFO (Resident_ID, Respondent_ID, Complaint_Title, Complaint_Details, incident_date, incident_time, Date_Filed, Status, Attachment_Path) VALUES (?,?,?,?,?,?,?,?,?)");
                        $stmt->bind_param('iisssssss', $external_id, $main_respondent_id, $complaint_title, $description, $incident_date, $incident_time, $date_filed, $status, $attachment_path);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO COMPLAINT_INFO (Resident_ID, Respondent_ID, Complaint_Title, Complaint_Details, incident_date, incident_time, Date_Filed, Status) VALUES (?,?,?,?,?,?,?,?)");
                        $stmt->bind_param('iissssss', $external_id, $main_respondent_id, $complaint_title, $description, $incident_date, $incident_time, $date_filed, $status);
                    }
                }
                if ($stmt && $stmt->execute()) {
    $complaint_id = $stmt->insert_id;
    $insert_success = true;
    // Create a compact complaint reference for display (COMP#01)
    $complaint_ref = 'COMP#' . str_pad((int)$complaint_id, 2, '0', STR_PAD_LEFT);

    // ✅ Notify Secretary about new external complaint
    try {
        $now = date('Y-m-d H:i:s');
    $notifTitle = 'New Complaint Submitted';
    $notifType = 'Complaint';
    // Use the human-friendly reference in notifications
    $ref = isset($complaint_ref) ? $complaint_ref : ('COMP-'.str_pad((int)$complaint_id, 3, '0', STR_PAD_LEFT));
    $complainant = $external_name ?: 'External Complainant';
    $notifMsg = "$ref submitted by " . $complainant;
        if ($ins = $conn->prepare("INSERT INTO notifications (title, message, type, is_read, created_at, related_id) VALUES (?, ?, ?, 0, ?, ?)") ) {
            $ins->bind_param('ssssi', $notifTitle, $notifMsg, $notifType, $now, $complaint_id);
            $ins->execute();
            $ins->close();
        }
    } catch (Exception $e) {
        // ignore non-fatal notification failures
    }

    // ✅ Insert additional respondents (if any)
    if ($complaint_id && count($respondent_names) > 1) {
        $now = date('Y-m-d H:i:s');
        $notifTitle = 'You have been listed as a Respondent';
        $notifType = 'Complaint';

        // Loop through other respondents (excluding the main)
        for ($i = 1; $i < count($respondent_names); $i++) {
            $full = trim($respondent_names[$i]);
            if (!$full) continue;

            // Split name
            $parts = preg_split('/\s+/', $full);
            $fname = $conn->real_escape_string($parts[0]);
            $lname = $conn->real_escape_string(end($parts));

            // Find or create resident record
            $r = $conn->query("SELECT Resident_ID FROM RESIDENT_INFO WHERE First_Name='$fname' AND Last_Name='$lname' LIMIT 1");
            if ($r && $r->num_rows > 0) {
                $rid = $r->fetch_assoc()['Resident_ID'];
            } else {
                $conn->query("INSERT INTO RESIDENT_INFO (First_Name, Last_Name) VALUES ('$fname', '$lname')");
                $rid = $conn->insert_id;
            }

            // ✅ Insert into COMPLAINT_RESPONDENTS
            $conn->query("INSERT INTO COMPLAINT_RESPONDENTS (Complaint_ID, Respondent_ID) VALUES ($complaint_id, $rid)");

            // ✅ Send notification to respondent (optional)
            $msg = "You have been added as a respondent in Complaint #$complaint_id.";
            $stmtN = $conn->prepare("INSERT INTO notifications (resident_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, ?)");
            $stmtN->bind_param('issss', $rid, $notifTitle, $msg, $notifType, $now);
            $stmtN->execute();
            $stmtN->close();
        }
    }
} else {
    $error_message = 'Failed to save complaint.' . ($stmt ? ' ' . $stmt->error : '');
}

                if($stmt) $stmt->close();
            } else {
                $error_message = 'Database connection unavailable.';
            }
        }
    }
}
?>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Complaint</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
    <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />

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
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
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
        .form-input:focus {
            border-color: #0c9ced;
            box-shadow: 0 0 0 3px rgba(12, 156, 237, 0.1);
            outline: none;
        }
        
    </style>

            <head>
                <meta charset="UTF-8" />
                <meta name="viewport" content="width=device-width, initial-scale=1.0" />
                <title>Submit Complaint (External)</title>
                <script src="https://cdn.tailwindcss.com"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css">
                <script src="https://cdn.jsdelivr.net/npm/@yaireo/tagify"></script>
                <script>
                    tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'} }, animation:{'float':'float 8s ease-in-out infinite','fade-in':'fadeIn .45s ease-out'}, keyframes:{ float:{'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-14px)'}}, fadeIn:{'0%':{opacity:0,transform:'translateY(6px)'},'100%':{opacity:1,transform:'translateY(0)'}} } } } };
                </script>
                <style>
                    .glass { background: linear-gradient(135deg, rgba(255,255,255,0.85), rgba(255,255,255,0.65)); backdrop-filter: blur(12px) saturate(140%); -webkit-backdrop-filter: blur(12px) saturate(140%); }
                    .form-input:focus { border-color:#0c9ced; box-shadow:0 0 0 3px rgba(12,156,237,0.15); outline:none; }
                    /* Make Tagify wrapper match standard input (pl-10 pr-3 py-3 rounded-lg border) */
                    .tagify {
                        border: 1px solid #e5e7eb; /* gray-200 */
                        border-radius: 0.5rem; /* rounded-lg */
                        background:#ffffff;
                        min-height: 3rem; /* matches py-3 vertical space */
                        padding: 0.75rem 0.75rem 0.75rem 2.5rem; /* top/right/bottom/left -> pl-10 pr-3 py-3 */
                        display: flex;
                        align-items: center;
                        font-size: 0.875rem; /* text-sm */
                        line-height:1.25rem;
                        transition: box-shadow .15s, border-color .15s;
                    }
                    .tagify:focus-within {
                        border-color:#0c9ced; /* primary-500 */
                        box-shadow:0 0 0 3px rgba(12,156,237,0.15);
                    }
                    /* Remove internal extra gaps */
                    .tagify__input { margin:0; padding:0; }
                    /* Ensure placeholder styling consistent */
                    .tagify__input::placeholder { color:#9ca3af; /* gray-400 */ }
                    /* Hide default tagify border when empty to rely on our custom border */
                    .tagify__tag { margin-top:0; }
                </style>
            </head>
            <body class="bg-gray-50 font-sans relative overflow-x-hidden">
                <?php include_once('../includes/external_nav.php'); ?>
                <!-- Orbs Background -->
                <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
                    <div class="absolute -top-44 -left-44 w-[500px] h-[500px] bg-blue-200/40 blur-3xl rounded-full animate-[float_14s_ease-in-out_infinite]"></div>
                    <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] bg-cyan-200/40 blur-[160px] rounded-full animate-[float_18s_ease-in-out_infinite]"></div>
                    <div class="absolute -bottom-64 left-1/3 w-[520px] h-[520px] bg-indigo-200/30 blur-3xl rounded-full animate-[float_16s_ease-in-out_infinite]"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px] rounded-full"></div>
                </div>

                <!-- Hero -->
                <header class="relative max-w-screen-2xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
                    <div class="relative glass rounded-2xl shadow-sm border border-white/60 ring-1 ring-primary-100/40 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
                        <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
                        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                            <div>
                                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                                    <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-file-circle-plus text-lg"></i></span>
                                    <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Submit Complaint</span>
                                </h1>
                                <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">File a new complaint for barangay records. Provide necessary details for accurate processing.</p>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-500 flex-wrap">
                                <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                                <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-user-pen text-primary-500"></i> External Complainant</div>
                            </div>
                        </div>
                    </div>
                    <!-- Underage confirmation modal for external complainants -->
                    <div id="underage-modal" role="dialog" aria-modal="true" aria-labelledby="underage-title" class="fixed inset-0 hidden z-50 flex items-center justify-center">
                        <div class="absolute inset-0 bg-black/40"></div>
                        <div class="relative z-10 mx-auto max-w-lg w-[92%] sm:w-full">
                            <div class="relative rounded-2xl p-6 md:p-7 border border-white/60 shadow-md overflow-hidden bg-white">
                                <div class="flex items-start justify-between gap-4 mb-4">
                                    <div>
                                        <h3 id="underage-title" class="text-lg font-semibold text-sky-900">You are under the age of 18</h3>
                                        <p class="text-xs text-sky-700/80 mt-2">According to the Katarungang Pambarangay Law, minors must be represented by a parent, legal guardian, or a person exercising parental authority. Please ensure that your complaint is submitted with your guardian's information and consent. Wait for the barangay secretary notice for you to go to the Barangay with your guardian if needed.</p>
                                        <p class="text-sm text-gray-700 mt-4 font-medium">Please confirm to submit your message. It will still be submitted when you confirm.</p>
                                    </div>
                                    <button type="button" id="underage-close" class="p-2 rounded-lg bg-white border border-gray-200 text-sky-700 hover:text-sky-900 hover:bg-gray-50" aria-label="Close">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                <div class="mt-4 flex justify-end gap-2">
                                    <button type="button" id="underage-cancel-btn" class="px-3 py-2 rounded-md bg-white border border-gray-200 text-sky-700 hover:bg-gray-50">Cancel</button>
                                    <button type="button" id="underage-confirm-btn" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700">Confirm and Submit</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- Form Card -->
                <div class="w-full mt-8 px-4 pb-20">
                    <div class="w-full max-w-7xl mx-auto bg-white/95 backdrop-blur-sm rounded-2xl border border-gray-100 shadow-md p-8 md:p-10 relative overflow-hidden">
                        <div class="absolute -top-10 -right-10 w-40 h-40 bg-gradient-to-br from-blue-100 to-cyan-100 rounded-full opacity-70"></div>
                        <div class="absolute -bottom-16 -left-16 w-56 h-56 bg-gradient-to-tr from-blue-50 to-cyan-100 rounded-full opacity-60"></div>
                        <div class="relative z-10">
                            <?php if($insert_success): ?>
                                <div class="mb-6 p-4 rounded-lg bg-green-50 border border-green-200 text-green-700 flex items-start gap-3">
                                    <i class="fa fa-check-circle mt-0.5"></i>
                                    <div>
                                        <div class="font-medium">Complaint submitted successfully.</div>
                                        <div class="text-sm mt-1">Reference ID: <span class="font-semibold"><?= isset($complaint_ref) ? htmlspecialchars($complaint_ref) : ('COMP-'.str_pad($complaint_id,3,'0',STR_PAD_LEFT)) ?></span>. <a href="view_complaints.php" class="underline hover:text-green-800">View your complaints</a>.</div>
                                    </div>
                                </div>
                            <?php elseif($error_message): ?>
                                <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 text-red-700 flex items-start gap-3"><i class="fa fa-exclamation-triangle mt-0.5"></i><div><?= htmlspecialchars($error_message) ?></div></div>
                            <?php endif; ?>
                            <form action="submit_complaints.php" method="POST" enctype="multipart/form-data" class="space-y-10" id="externalComplaintForm">
                                <input type="hidden" id="complaint-title" value="" />
                                <input type="hidden" name="underage_confirmed" id="underage_confirmed" value="<?= isset($_POST['underage_confirmed']) && $_POST['underage_confirmed']=='1' ? '1' : '0' ?>" />
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700">Complainant Name</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-user"></i></span>
                                            <input type="text" value="<?= htmlspecialchars($external_name) ?>" disabled class="w-full pl-10 pr-3 py-3 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 focus:outline-none" />
                                            <input type="hidden" name="complainant_name" value="<?= htmlspecialchars($external_name) ?>" />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="respondent-name" class="block text-sm font-medium text-gray-700">Respondent Name(s) <span class="text-red-500">*</span></label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 text-gray-400"><i class="fa-solid fa-user-group"></i></span>
                                            <input type="text" id="respondent-name" name="respondent_name" required class="w-full pl-10 pr-3 py-3 rounded-lg border border-gray-200 bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition form-input" placeholder="Type and select respondent names">
                                        </div>
                                        <p class="text-xs text-gray-500 italic">Use full names (First Middle Last). Add one or more respondents.</p>
                                    </div>
                                    
                                    <div class="space-y-2">
                                        <label for="incident-date" class="block text-sm font-medium text-gray-700">Incident Date</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-calendar-day"></i></span>
                                            <input type="date" id="incident-date" name="incident_date" max="<?= date('Y-m-d') ?>" class="w-full pl-10 pr-3 py-3 rounded-lg border border-gray-200 bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition form-input">
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label for="incident-time" class="block text-sm font-medium text-gray-700">Incident Time (Optional)</label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-clock"></i></span>
                                            <input type="time" id="incident-time" name="incident_time" class="w-full pl-10 pr-3 py-3 rounded-lg border border-gray-200 bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition form-input">
                                        </div>
                                    </div>
                                    <div class="space-y-2 md:col-span-2">
                                        <div class="flex items-center justify-between">
                                            <label for="complaint-description" class="block text-sm font-medium text-gray-700">Description <span class="text-red-500">*</span></label>
                                            <button type="button" id="open-tips" class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-md bg-white/80 backdrop-blur border border-gray-200 text-primary-700 hover:text-primary-800 hover:bg-white shadow-sm transition pointer-events-auto" title="Tips in writing complaint" aria-haspopup="dialog" aria-controls="tips-modal" role="button" tabindex="0">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 text-primary-600 ring-1 ring-white/60"><i class="fa fa-lightbulb text-sm"></i></span>
                                                <span class="hidden sm:inline text-[11px] font-semibold">Tips</span>
                                            </button>
                                        </div>
                                        <div class="relative">
                                            <textarea id="complaint-description" name="complaint_description" rows="6" placeholder="Provide a clear and detailed description..." class="w-full p-4 rounded-lg border border-gray-200 bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400 transition form-input resize-y" required></textarea>
                                        </div>
                                        <p class="text-xs text-gray-500">Include date, time, location and involved parties if known.</p>
                                    </div>
                                    <input type="hidden" name="out_of_scope" id="out_of_scope" value="0" />
                                    <div class="space-y-2">
                                        <label class="block text-sm font-medium text-gray-700">Status (Auto)</label>
                                        <div class="px-4 py-3 rounded-lg border border-dashed border-blue-200 bg-blue-50 text-sm text-blue-700 flex items-center gap-2"><i class="fa-solid fa-circle-info"></i> Pending</div>
                                    </div>
                                </div>

                                <!-- Attachments (Enhanced) -->
                                <div class="space-y-3">
                                    <label for="complaint-attachment" class="block text-sm font-medium text-gray-700">Attachments <span class="text-gray-400 font-normal">(Optional)</span></label>
                                    <div class="relative">
                                        <label for="complaint-attachment" id="dropZone" class="flex flex-col justify-center items-center w-full h-40 bg-gradient-to-br from-gray-50 to-white rounded-xl border border-dashed border-gray-300 cursor-pointer hover:border-primary-300 hover:bg-primary-50/40 transition group">
                                            <div class="flex flex-col justify-center items-center pt-4 pb-5 pointer-events-none" id="dropInner">
                                                <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3 group-hover:text-primary-500 transition"></i>
                                                <p class="text-sm text-gray-600"><span class="font-medium">Browse</span> or drag & drop files</p>
                                                <p class="text-xs text-gray-400">Images / PDF (max 20MB each)</p>
                                            </div>
                                            <input id="complaint-attachment" type="file" name="complaint_attachment[]" class="hidden" multiple />
                                        </label>
                                    </div>
                                    <div id="fileErrors" class="hidden rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-xs text-red-600"></div>
                                    <div id="attachmentsPreview" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"></div>
                                    <p class="text-xs text-gray-500">You can upload multiple evidence files. Hover a file to preview or remove.</p>
                                </div>

                                <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row gap-3 sm:justify-between items-center">
                                    <div class="text-xs text-gray-500 flex items-start gap-2 max-w-sm"><i class="fa-solid fa-shield-halved text-blue-500 mt-0.5"></i><span>Data recorded here becomes part of the official barangay intake record and is handled confidentially.</span></div>
                                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                        <a href="home-external.php" class="py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg flex items-center justify-center gap-2 transition"><i class="fa-solid fa-xmark"></i> Cancel</a>
                                        <button id="submitBtn" type="submit" <?= $insert_success? 'disabled':''; ?> class="py-3 px-8 <?= $insert_success? 'bg-blue-400 cursor-not-allowed disabled:opacity-70':'bg-blue-400 cursor-not-allowed disabled:opacity-70'; ?> text-white font-medium rounded-lg flex items-center justify-center gap-2 shadow-sm transition"><i class="fa-solid fa-paper-plane"></i> Submit Complaint</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <script>
                // Tagify for respondents (optional multi names)
                // Tagify for respondents (auto-suggest from resident_info)
                        const respondentInput = document.getElementById('respondent-name');
                        if (respondentInput) {
                            const tagify = new Tagify(respondentInput, {
                                duplicates: false,
                                dropdown: {
                                    enabled: 0,          // show suggestions on typing
                                    maxItems: 10,        // max items to show
                                    closeOnSelect: false // allow multiple selections
                                },
                                whitelist: []            // will be filled from DB
                            });

                            // Fetch resident names for Tagify suggestions
                            fetch('get_residents.php')
                                .then(res => res.json())
                                .then(data => {
                                    tagify.settings.whitelist = data; // load residents
                                })
                                .catch(err => console.error('Failed to load resident list:', err));
                        }

                // Enhanced file handling & form gating
                document.addEventListener('DOMContentLoaded',()=>{
                    const inputFile=document.getElementById('complaint-attachment');
                    const dropZone=document.getElementById('dropZone');
                    const preview=document.getElementById('attachmentsPreview');
                    const fileErrors=document.getElementById('fileErrors');
                    const submitBtn=document.getElementById('submitBtn');
                    const desc=document.getElementById('complaint-description');
                    const date=document.getElementById('incident-date');
                    const MAX_SIZE=20*1024*1024; // 20MB per file
                    const respondent=document.getElementById('respondent-name');
                    function hasRespondent(){
                        if(!respondent) return false;
                        const v=respondent.value.trim();
                        if(!v) return false;
                        if(v.startsWith('[')){
                            try { const arr=JSON.parse(v); return Array.isArray(arr) && arr.some(it=> (it.value||'').trim().length>0); } catch { return false; }
                        }
                        return v.length>0;
                    }
                    function refreshSubmit(){
                        const okDesc = desc.value.trim().length>0;
                        // Disallow future dates (use server date for consistency)
                        function isFutureDate(dStr){ if(!dStr) return false; return dStr > '<?= date('Y-m-d') ?>'; }
                        const okDate = date.value !== '' && !isFutureDate(date.value);
                        if(isFutureDate(date.value)){
                            date.classList.add('ring-2','ring-red-300');
                        } else {
                            date.classList.remove('ring-2','ring-red-300');
                        }
                        const okResp = hasRespondent();
                        if(okDesc && okDate && okResp){
                            submitBtn.disabled=false; submitBtn.classList.remove('bg-blue-400','cursor-not-allowed'); submitBtn.classList.add('bg-blue-600','hover:bg-blue-700');
                        } else {
                            submitBtn.disabled=true; submitBtn.classList.add('bg-blue-400','cursor-not-allowed'); submitBtn.classList.remove('bg-blue-600','hover:bg-blue-700');
                        }
                    }
                    desc.addEventListener('input',refreshSubmit); date.addEventListener('change',refreshSubmit); respondent.addEventListener('input',refreshSubmit); refreshSubmit();

                    function bytesToSize(b){ const u=['B','KB','MB','GB']; let i=0; let v=b; while(v>=1024&&i<u.length-1){ v/=1024;i++; } return v.toFixed(1)+' '+u[i]; }
                    function clearErrors(){ fileErrors.classList.add('hidden'); fileErrors.textContent=''; }
                    function showError(msg){ fileErrors.textContent=msg; fileErrors.classList.remove('hidden'); }
                    function rebuildFileList(keep){ const dt=new DataTransfer(); keep.forEach(f=>dt.items.add(f)); inputFile.files=dt.files; }
                    let objectUrls=[];
                    function renderPreviews(){
                        // Revoke previous URLs
                        objectUrls.forEach(u=>URL.revokeObjectURL(u));
                        objectUrls=[];
                        preview.innerHTML='';
                        const files=[...inputFile.files];
                        if(!files.length){ return; }
                        files.forEach((f,idx)=>{
                            const ext=f.name.split('.').pop().toLowerCase();
                            const isImg=['png','jpg','jpeg','gif','webp','bmp'].includes(ext);
                            const isPdf=ext==='pdf';
                            const url=(isImg||isPdf)? URL.createObjectURL(f):'';
                            if(url) objectUrls.push(url); else objectUrls.push(null);
                            let inner='';
                            if(isImg){
                                inner = `<img src='${url}' alt='${f.name}' class='w-full h-24 object-cover rounded-md border' />`;
                            } else if(isPdf){
                                inner = `<div class="flex flex-col items-center justify-center gap-2 p-3 rounded-md border bg-white h-24"><i class=\"fa fa-file-pdf text-red-500 text-2xl\"></i><span class=\"text-[11px] text-center line-clamp-2\" title='${f.name}'>${f.name}</span></div>`;
                            } else {
                                inner = `<div class=\"flex flex-col items-center justify-center gap-2 p-3 rounded-md border bg-white h-24\"><i class=\"fa fa-file text-gray-500 text-2xl\"></i><span class=\"text-[11px] text-center line-clamp-2\" title='${f.name}'>${f.name}</span></div>`;
                            }
                            const wrap=document.createElement('div');
                            wrap.className='relative group';
                            wrap.innerHTML = inner + `\n<div class=\"absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-3 rounded-md\">\n  ${(isImg||isPdf)?`<button type=\"button\" data-action=\"view\" data-index=\"${idx}\" class=\"p-2 rounded-full bg-white/90 text-gray-700 hover:bg-white shadow\" title=\"View\"><i class=\"fa fa-eye\"></i></button>`:''}\n  <button type=\"button\" data-action=\"remove\" data-index=\"${idx}\" class=\"p-2 rounded-full bg-white/90 text-red-600 hover:bg-white shadow\" title=\"Remove\"><i class=\"fa fa-trash\"></i></button>\n</div>`;
                            preview.appendChild(wrap);
                        });
                        // Display grid styling similar to resident version
                        preview.classList.add('grid');
                        preview.classList.remove('flex');
                        preview.classList.add('grid-cols-2','md:grid-cols-3','gap-3');
                    }
                    function handleFiles(sel){
                        // Combine existing files and incoming files but avoid duplicates.
                        // Key files by name_size_lastModified (lastModified may be 0 in some cases).
                        clearErrors();
                        const current = [...inputFile.files];
                        const incoming = [...sel];
                        const rejected = [];

                        const seen = new Map();

                        // Add current files first
                        current.forEach(f => {
                            const key = `${f.name}_${f.size}_${f.lastModified || 0}`;
                            seen.set(key, f);
                        });

                        // Process incoming files (replace/skip duplicates)
                        incoming.forEach(f => {
                            if (f.size > MAX_SIZE) {
                                rejected.push(`${f.name} (${bytesToSize(f.size)})`);
                            } else {
                                const key = `${f.name}_${f.size}_${f.lastModified || 0}`;
                                seen.set(key, f);
                            }
                        });

                        if (rejected.length) {
                            showError('Removed (exceeds 20MB each): ' + rejected.join(', '));
                        }

                        // Build final unique list preserving insertion order (current then incoming overrides)
                        const keep = Array.from(seen.values());
                        const dt = new DataTransfer();
                        keep.forEach(f => dt.items.add(f));
                        inputFile.files = dt.files;
                        renderPreviews();
                    }
                    if(inputFile){
                        inputFile.addEventListener('change',e=>{ handleFiles(e.target.files); });
                        ;['dragenter','dragover','dragleave','drop'].forEach(ev=> dropZone.addEventListener(ev,e=>{e.preventDefault();e.stopPropagation();},false));
                        ;['dragenter','dragover'].forEach(ev=> dropZone.addEventListener(ev,()=> dropZone.classList.add('border-primary-300','bg-primary-50/50'),false));
                        ;['dragleave','drop'].forEach(ev=> dropZone.addEventListener(ev,()=> dropZone.classList.remove('border-primary-300','bg-primary-50/50'),false));
                        dropZone.addEventListener('drop',e=>{ handleFiles(e.dataTransfer.files); });
                        preview.addEventListener('click',e=>{ const btn=e.target.closest('button[data-action]'); if(!btn) return; const action=btn.getAttribute('data-action'); const idx=parseInt(btn.getAttribute('data-index')); if(Number.isNaN(idx)) return; if(action==='view'){ const url=objectUrls[idx]; if(url) window.open(url,'_blank'); } else if(action==='remove'){ const files=[...inputFile.files]; files.splice(idx,1); rebuildFileList(files); renderPreviews(); } });
                    }
                });
                </script>
                </script>
                
                <script>
                    document.addEventListener('DOMContentLoaded',()=>{
                        const form=document.getElementById('externalComplaintForm');
                        const modal=document.getElementById('scope-modal');
                        const cancelBtn=document.getElementById('cancel-submit');
                        const proceedBtn=document.getElementById('proceed-submit');
                        let allowed=false;

                        // Safe submit handler: if checkComplaintScope is not defined, assume IN_SCOPE.
                        form.addEventListener('submit',async (e)=>{
                            if(allowed) return;
                            e.preventDefault();
                            const desc=document.getElementById('complaint-description').value.trim();
                            const titleInput=document.getElementById('complaint-title');
                            titleInput.value = desc.substring(0,40) || 'Complaint';
                            const title=titleInput.value.trim();

                            // Call scope checker if available, otherwise default to IN_SCOPE.
                            let result = 'IN_SCOPE';
                            try{
                                if(typeof checkComplaintScope === 'function'){
                                    result = await checkComplaintScope(title,desc);
                                } else {
                                    // No remote/local checker present; assume in scope.
                                    result = 'IN_SCOPE';
                                }
                            }catch(err){
                                console.error('checkComplaintScope failed:', err);
                                result = 'IN_SCOPE';
                            }

                            if(result === 'OUT_OF_SCOPE'){
                                // If a modal exists, show it; otherwise fall back to a confirm dialog to let the user proceed.
                                if(modal){
                                    modal.classList.remove('hidden'); modal.classList.add('flex');
                                    document.getElementById('out_of_scope').value='1';
                                } else {
                                    if(!confirm('This complaint appears to be out of scope. Do you want to continue submitting as out-of-scope?')){
                                        return;
                                    }
                                    document.getElementById('out_of_scope').value='1';
                                    allowed = true; form.submit();
                                }
                            } else {
                                document.getElementById('out_of_scope').value='0';
                                allowed=true; form.submit();
                            }
                        });

                        // Modal fallback handlers (if modal exists)
                        if(cancelBtn){
                            cancelBtn.addEventListener('click',()=>{ if(modal){ modal.classList.add('hidden'); modal.classList.remove('flex'); } });
                        }
                        if(proceedBtn){
                            proceedBtn.addEventListener('click',()=>{ allowed=true; if(modal){ modal.classList.add('hidden'); modal.classList.remove('flex'); } form.submit(); });
                        }
                    });
                </script>

                <!-- Tips Modal -->
                <div id="tips-modal" role="dialog" aria-modal="true" aria-labelledby="tips-title" class="fixed inset-0 hidden z-50 flex items-center justify-center">
                    <div id="tips-overlay" class="absolute inset-0 bg-black/40"></div>
                    <div class="relative z-10 mx-auto max-w-lg w-[92%] sm:w-full">
                        <div class="relative rounded-2xl p-6 md:p-7 border border-white/60 shadow-[0_18px_50px_-12px_rgba(14,116,144,0.25)] overflow-hidden bg-gradient-to-br from-blue-50 via-white to-cyan-50">
                            <div class="absolute -top-16 -right-16 w-56 h-56 bg-gradient-to-br from-primary-200/60 to-primary-400/40 rounded-full blur-3xl"></div>
                            <div class="absolute -bottom-20 -left-20 w-64 h-64 bg-gradient-to-tr from-white/50 to-primary-100/50 rounded-full blur-3xl"></div>
                            <div class="relative z-10">
                                <div class="flex items-start justify-between gap-4 mb-4">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-primary-100 text-primary-600 ring-1 ring-white/60 shadow-inner"><i class="fa-solid fa-lightbulb"></i></span>
                                        <div>
                                            <h3 id="tips-title" class="text-lg font-semibold text-sky-900">Tips for a good complaint</h3>
                                            <p class="text-xs text-sky-700/80">Write clearly and stick to the facts.</p>
                                        </div>
                                    </div>
                                    <button type="button" id="close-tips" class="p-2 rounded-lg bg-white border border-white/60 text-sky-700 hover:text-sky-900 hover:bg-white shadow-sm pointer-events-auto" aria-label="Close tips" role="button" tabindex="0">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                                <div id="tips-stepper" class="space-y-4">
                                    <div class="flex items-center justify-between gap-3 mb-4">
                                        <div class="flex items-center w-full">
                                            <div class="flex items-center justify-between w-full">
                                                <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="0" role="button" tabindex="0">
                                                    <div class="w-10 h-10 rounded-full bg-sky-600 text-white flex items-center justify-center transition-all">1</div>
                                                    <div class="mt-2 text-xs text-sky-800">State facts</div>
                                                </div>
                                                <div class="flex-1 h-0.5 bg-sky-200 mx-2"></div>
                                                <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="1" role="button" tabindex="0">
                                                    <div class="w-10 h-10 rounded-full bg-sky-200 text-sky-800 flex items-center justify-center transition-all">2</div>
                                                    <div class="mt-2 text-xs text-sky-800">Be specific</div>
                                                </div>
                                                <div class="flex-1 h-0.5 bg-sky-200 mx-2"></div>
                                                <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="2" role="button" tabindex="0">
                                                    <div class="w-10 h-10 rounded-full bg-sky-200 text-sky-800 flex items-center justify-center transition-all">3</div>
                                                    <div class="mt-2 text-xs text-sky-800">Describe evidence</div>
                                                </div>
                                                <div class="flex-1 h-0.5 bg-sky-200 mx-2"></div>
                                                <div class="step flex flex-col items-center text-center pointer-events-auto cursor-pointer" data-step="3" role="button" tabindex="0">
                                                    <div class="w-10 h-10 rounded-full bg-sky-200 text-sky-800 flex items-center justify-center transition-all">4</div>
                                                    <div class="mt-2 text-xs text-sky-800">State impact</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="tips-contents" class="bg-white/80 p-4 rounded-lg border border-white/60">
                                        <div class="tip-pane" data-step="0">
                                            <h4 class="font-semibold text-sky-900 mb-2">State the facts chronologically</h4>
                                            <p class="text-sm text-sky-800">Write what happened, when and where it happened, and who was involved. Stick to objective facts and the sequence of events.</p>
                                        </div>
                                        <div class="tip-pane hidden" data-step="1">
                                            <h4 class="font-semibold text-sky-900 mb-2">Be specific</h4>
                                            <p class="text-sm text-sky-800">Include exact dates, approximate times, locations, and full names if you know them. Small details help investigators follow up.</p>
                                        </div>
                                        <div class="tip-pane hidden" data-step="2">
                                            <h4 class="font-semibold text-sky-900 mb-2">Describe evidence</h4>
                                            <p class="text-sm text-sky-800">List any photos, messages, receipts, or witnesses. Attach files using the Attachments panel so evidence is preserved.</p>
                                        </div>
                                        <div class="tip-pane hidden" data-step="3">
                                            <h4 class="font-semibold text-sky-900 mb-2">State the impact</h4>
                                            <p class="text-sm text-sky-800">Explain briefly how the incident affected you (harm, loss, inconvenience) and what outcome you are seeking, if any.</p>
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end gap-2">
                                        <button type="button" id="tips-prev" class="px-3 py-2 rounded-md bg-white border border-gray-200 text-sky-700 hover:bg-gray-50 pointer-events-auto cursor-pointer" role="button" tabindex="0">Previous</button>
                                        <button type="button" id="tips-next" class="px-3 py-2 rounded-md bg-sky-600 text-white hover:bg-sky-700 pointer-events-auto cursor-pointer" role="button" tabindex="0">Next</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                // Tips modal stepper logic
                document.addEventListener('DOMContentLoaded', () => {
                    const tipsModal = document.getElementById('tips-modal');
                    const tipsOverlay = document.getElementById('tips-overlay');
                    const openTips = document.getElementById('open-tips');
                    const closeTips = document.getElementById('close-tips');
                    const steps = Array.from(document.querySelectorAll('#tips-stepper .step'));
                    const panes = Array.from(document.querySelectorAll('#tips-contents .tip-pane'));
                    const prevBtn = document.getElementById('tips-prev');
                    const nextBtn = document.getElementById('tips-next');
                    let currentStepIndex = 0;

                    function hideTips(){ if(tipsModal) tipsModal.classList.add('hidden'); }
                    function showTips(){ if(tipsModal) tipsModal.classList.remove('hidden'); }

                    function showStepAtIndex(i){
                        if(i < 0) i = 0;
                        if(i >= steps.length) i = steps.length - 1;
                        currentStepIndex = i;

                        steps.forEach((s,sn)=>{
                            const circle = s.querySelector('.w-10');
                            if(sn <= currentStepIndex){
                                circle.classList.remove('bg-sky-200','text-sky-800');
                                circle.classList.add('bg-sky-600','text-white');
                            } else {
                                circle.classList.remove('bg-sky-600','text-white');
                                circle.classList.add('bg-sky-200','text-sky-800');
                            }
                        });

                        panes.forEach(p=> p.classList.add('hidden'));
                        const active = panes.find(p=> parseInt(p.getAttribute('data-step')) === currentStepIndex);
                        if(active) active.classList.remove('hidden');

                        if(prevBtn) {
                            prevBtn.disabled = (currentStepIndex === 0);
                            if(currentStepIndex === 0) {
                                prevBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            } else {
                                prevBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                            }
                        }

                        if(nextBtn){
                            if(currentStepIndex === steps.length - 1){
                                nextBtn.textContent = 'Close';
                            } else {
                                nextBtn.textContent = 'Next';
                            }
                        }
                    }

                    steps.forEach((s, i)=> {
                        s.addEventListener('click', ()=> showStepAtIndex(i));
                    });

                    if(prevBtn) {
                        prevBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if(currentStepIndex > 0) showStepAtIndex(currentStepIndex - 1);
                        });
                    }

                    if(nextBtn) {
                        nextBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if(currentStepIndex === steps.length - 1){
                                hideTips();
                            } else {
                                showStepAtIndex(currentStepIndex + 1);
                            }
                        });
                    }

                    openTips?.addEventListener('click', ()=> {
                        showTips();
                        setTimeout(()=> showStepAtIndex(0), 60);
                    });
                    closeTips?.addEventListener('click', hideTips);
                    tipsOverlay?.addEventListener('click', hideTips);
                });
                </script>

                <?php include '../chatbot/bpamis_case_assistant.php'; ?>
                <script>
                // Underage modal + submit-button click interception for external form
                document.addEventListener('DOMContentLoaded', () => {
                    const externalDob = <?php echo json_encode($external_dob_iso ?? ''); ?>;
                    function calcAge(dobStr){
                        if(!dobStr) return null;
                        const parts = dobStr.split('-');
                        if(parts.length !== 3) return null;
                        const d = new Date(dobStr);
                        if(isNaN(d)) return null;
                        const now = new Date();
                        let age = now.getFullYear() - d.getFullYear();
                        const m = now.getMonth() - d.getMonth();
                        if(m < 0 || (m === 0 && now.getDate() < d.getDate())) age--;
                        return age;
                    }
                    const form = document.getElementById('externalComplaintForm');
                    const modal = document.getElementById('underage-modal');
                    const confirmBtn = document.getElementById('underage-confirm-btn');
                    const cancelBtn = document.getElementById('underage-cancel-btn');
                    const closeBtn = document.getElementById('underage-close');
                    const underageInput = document.getElementById('underage_confirmed');
                    const submitBtn = document.getElementById('submitBtn');
                    if(!form) return;

                    if(submitBtn){
                        submitBtn.addEventListener('click', function(e){
                            if(submitBtn.dataset.confirming === '1'){
                                submitBtn.dataset.confirming = '0';
                                return;
                            }
                            if(underageInput && underageInput.value === '1') return;
                            const age = calcAge(externalDob);
                            if(age !== null && age < 18){
                                e.preventDefault();
                                e.stopImmediatePropagation();
                                if(modal) modal.classList.remove('hidden');
                            }
                        }, true);
                    }

                    function hide(){ if(modal) modal.classList.add('hidden'); }
                    cancelBtn?.addEventListener('click', hide);
                    closeBtn?.addEventListener('click', hide);
                    confirmBtn?.addEventListener('click', ()=>{
                        if(underageInput) underageInput.value = '1';
                        hide();
                        if(submitBtn){ submitBtn.dataset.confirming = '1'; submitBtn.click(); }
                        else { form.submit(); }
                    });
                });
                </script>
            </body>
            </html>
