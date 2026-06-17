<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';
$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // $conn provided by server/server.php


//$complainant_name = $conn->real_escape_string($_POST['complainant_name']); // optional: if you plan to use this later
//$respondent_name = $conn->real_escape_string($_POST['respondent_name']);   // optional: if you plan to use this later
$complaint_title = $conn->real_escape_string($_POST['complaint_title']);
$complaint_description = $conn->real_escape_string($_POST['complaint_description']);
$incident_date = $conn->real_escape_string($_POST['incident_date']);

// TEMP: Manually assign Resident_ID
$resident_id = 1;

// Status can be 'Open' by default
$status = 'Pending';

// Insert with Complaint_Title included
$sql = "INSERT INTO COMPLAINT_INFO (Complaint_Title, Complaint_Details, Date_Filed, Status)
        VALUES ('$complaint_title', '$complaint_description', '$incident_date', '$status')";


    // Execute and check
    if ($conn->query($sql) === TRUE) {
        // Get the inserted id and create a compact reference (COMP#01)
        $complaint_id = $conn->insert_id;
        $complaint_ref = 'COMP#' . str_pad((int)$complaint_id, 2, '0', STR_PAD_LEFT);
        $success = 'Complaint has been added. Reference: ' . htmlspecialchars($complaint_ref);
    } else {
        $error = 'Error: ' . $conn->error;
    }

    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Complaints</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76' }
                    },
                    animation: { 'float':'float 6s ease-in-out infinite' },
                    keyframes: { float: { '0%,100%':{ transform:'translateY(0)' }, '50%':{ transform:'translateY(-8px)' } } },
                    boxShadow: { glow:'0 0 0 1px rgba(12,156,237,0.10), 0 6px 24px -6px rgba(6,90,143,0.25)' }
                }
            }
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <style>
        .gradient-bg { background: linear-gradient(to right, #f0f7ff, #e0effe); }
        .glass { background:linear-gradient(145deg,rgba(255,255,255,.95),rgba(255,255,255,.75)); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px); }
        .input-base { width:100%; border-radius:.75rem; border:1px solid #e5e7eb; background:#fff; padding:.75rem .875rem; font-size:.95rem; transition:.2s; }
        .input-base:focus { outline:none; border-color:#36b3f9; box-shadow:0 0 0 4px rgba(12,156,237,.2); }
        .label { display:block; font-size:.9rem; font-weight:600; color:#374151; margin-bottom:.4rem; }
    </style>
    
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>

    <!-- Page Heading (mirrors secretary add complaints) -->
    <header class="relative max-w-6xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
        <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-comment-dots text-lg"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Add Complaint</span>
                    </h1>
                    <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Add your complaint details below.</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-comments text-primary-500"></i> Complaint Details</div>
                </div>
            </div>
        </div>
    </header>
    <!-- Form Card -->
    <div class="w-full mt-8 px-4 pb-14">
        <div class="w-full max-w-5xl mx-auto glass rounded-2xl border border-white/60 ring-1 ring-primary-100/60 shadow-glow p-8 md:p-10 relative overflow-hidden">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-gradient-to-br from-primary-100 to-primary-200 rounded-full opacity-70"></div>
            <div class="absolute -bottom-16 -left-16 w-56 h-56 bg-gradient-to-tr from-primary-50 to-primary-200 rounded-full opacity-60"></div>
            <div class="relative z-10">
                <?php if (!empty($success)): ?>
                    <div class="mb-6 p-4 rounded-lg border border-green-200 bg-green-50 text-green-700 flex items-start gap-3"><i class="fa-solid fa-circle-check mt-0.5 text-green-500"></i><div><strong class="block">Success!</strong><span> <?= htmlspecialchars($success) ?></span></div></div>
                <?php elseif (!empty($error)): ?>
                    <div class="mb-6 p-4 rounded-lg border border-red-200 bg-red-50 text-red-700 flex items-start gap-3"><i class="fa-solid fa-circle-exclamation mt-0.5 text-red-500"></i><div><strong class="block">Error</strong><span> <?= htmlspecialchars($error) ?></span></div></div>
                <?php endif; ?>

                <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
                    <h2 class="text-lg md:text-xl font-semibold text-gray-800 flex items-center gap-2"><i class="fa-solid fa-clipboard-list text-primary-500"></i> Complaint Details</h2>
                    <a href="view_complaints.php" class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium"><i class="fa-solid fa-arrow-left"></i> Back to List</a>
                </div>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-2">
                            <label for="complainant-name" class="label">Complainant Name</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-user"></i></span>
                                <input type="text" id="complainant-name" name="complainant_name" placeholder="Juan D. Dela Cruz" class="input-base pl-10" required>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label for="respondent-name" class="label">Respondent Name</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-user-group"></i></span>
                                <input type="text" id="respondent-name" name="respondent_name" placeholder="Maria S. Santos" class="input-base pl-10" required>
                            </div>
                        </div>
                       
                        <div class="space-y-2 md:col-span-2">
                            <label for="complaint-description" class="label">Description</label>
                            <div class="relative">
                                <textarea id="complaint-description" name="complaint_description" rows="6" placeholder="Provide a clear and detailed description of the complaint..." class="input-base p-4 resize-y" required></textarea>
                            </div>
                            <p class="text-xs text-gray-500">Include date, time, location and involved parties if known.</p>
                        </div>
                        <div class="space-y-2">
                            <label for="incident-date" class="label">Incident Date</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"><i class="fa-solid fa-calendar-day"></i></span>
                                <input type="date" id="incident-date" name="incident_date" class="input-base pl-10" required>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Status (Auto)</label>
                            <div class="px-4 py-3 rounded-lg border border-dashed border-blue-200 bg-blue-50 text-sm text-blue-700 flex items-center gap-2"><i class="fa-solid fa-circle-info"></i> Pending</div>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-gray-100 flex flex-col sm:flex-row gap-3 sm:justify-between items-center">
                        <div class="text-xs text-gray-500 flex items-start gap-2 max-w-sm"><i class="fa-solid fa-shield-halved text-blue-500 mt-0.5"></i><span>Data recorded here becomes part of the official case intake log.</span></div>
                        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                            <a href="home-captain.php" class="py-3 px-6 bg-white/70 hover:bg-white text-gray-700 border border-gray-300 font-medium rounded-lg flex items-center justify-center gap-2 transition"><i class="fa-solid fa-xmark"></i> Cancel</a>
                            <button type="submit" class="py-3 px-8 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg flex items-center justify-center gap-2 shadow-sm transition"><i class="fa-solid fa-paper-plane"></i> Submit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../chatbot/bpamis_case_assistant.php'?>
    <?php include 'sidebar_.php';?>
</body>
</html>
