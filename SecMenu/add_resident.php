
<?php
require_once(__DIR__ . '/../server/server.php');
include '../controllers/session_control.php';
// Fetch residents (id, name, email, address)
$residents = [];
if (isset($conn)) {
    // Alias columns to lowercase keys to match existing usage in the template
    $sql = "SELECT Resident_ID AS resident_id, First_Name AS first_name, Middle_Name AS middle_name, Last_Name AS last_name, email, Address AS address FROM resident_info ORDER BY Last_Name ASC, First_Name ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = bpamis_stmt_get_result($stmt);
        if ($result) {
            $residents = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Add Resident • Admin</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76' }
                    },
                    fontFamily: { poppins: ['Poppins','sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family:'Poppins',sans-serif; background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%); }
        .premium-card { background: linear-gradient(135deg, rgba(255,255,255,.96) 0%, rgba(255,255,255,.9) 100%); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,.35); box-shadow: 0 12px 36px rgba(0,0,0,.08); }
        .premium-gradient { background: linear-gradient(135deg, #667eea 0%, #36b3f9 100%); }
        .table-head { background: linear-gradient(180deg, #eef5ff, #e8f2ff); }
        .badge { display:inline-flex; align-items:center; padding: .25rem .5rem; border-radius: 999px; font-size:.72rem; font-weight:600; }
        .orb { position:absolute; border-radius:50%; filter:blur(40px); opacity:.5; mix-blend-mode:multiply; pointer-events:none; }
        .orb.one { width: 380px; height: 380px; background: linear-gradient(135deg, #0c9ced, #7cccfd); top:-120px; right:-120px; }
        .orb.two { width: 260px; height: 260px; background: linear-gradient(135deg, #bae2fd, #e0effe); bottom:-80px; left:-100px; }

        /* Mobile Responsive Styles */
        @media (max-width: 640px) {
            /* Container and Body */
            .container { padding-left: 0.75rem; padding-right: 0.75rem; }
            body { font-size: 0.7rem; }

            /* Hero Header Compression */
            section.container .premium-gradient { padding: 0.75rem !important; border-radius: 1rem !important; }
            section.container .premium-gradient h1 { font-size: 1.125rem !important; line-height: 1.3; }
            section.container .premium-gradient p { font-size: 0.7rem !important; margin-top: 0.25rem !important; }
            section.container .premium-gradient .w-10 { width: 2rem !important; height: 2rem !important; font-size: 0.875rem; }
            section.container .premium-gradient .gap-3 { gap: 0.5rem !important; }

            /* Orb Size Reduction */
            .orb.one { width: 200px; height: 200px; top: -60px; right: -60px; }
            .orb.two { width: 150px; height: 150px; bottom: -40px; left: -50px; }

            /* Form Card */
            .premium-card { padding: 0.75rem !important; border-radius: 1rem !important; margin-bottom: 0.75rem !important; }

            /* Form Grid - Single Column */
            #residentForm .grid { grid-template-columns: 1fr !important; gap: 0.5rem !important; }
            #residentForm .space-y-5 { gap: 0.5rem !important; }

            /* Form Labels */
            #residentForm label { font-size: 9px !important; margin-bottom: 0.25rem !important; font-weight: 500; }

            /* Form Inputs */
            #residentForm input[type="text"],
            #residentForm input[type="email"],
            #residentForm input[type="password"] {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
                padding-right: 2.5rem !important;
                border-radius: 0.5rem !important;
            }

            /* Readonly Field */
            #residentForm input[readonly] {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
            }

            /* Password Toggle Button */
            #residentForm button[type="button"]:not(#cancelBtn) {
                top: 2rem !important;
                right: 0.625rem !important;
                font-size: 0.7rem !important;
            }

            /* Password Mismatch Message */
            #passwordMismatchMsg { font-size: 9px !important; margin-top: 0.25rem !important; }

            /* Form Buttons - Stack Vertically */
            #residentForm .flex.items-center.justify-end {
                flex-direction: column !important;
                gap: 0.5rem !important;
                padding-top: 0.5rem !important;
            }

            #residentForm button[type="button"],
            #residentForm button[type="submit"] {
                width: 100% !important;
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
                border-radius: 0.5rem !important;
            }

            /* Batch Upload Card */
            .premium-card h2 { font-size: 1rem !important; font-weight: 600; }
            
            .premium-card .flex.items-center.justify-between {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.5rem !important;
                margin-bottom: 0.75rem !important;
            }

            /* Batch Upload Header */
            .premium-card .w-10.h-10 { width: 2rem !important; height: 2rem !important; font-size: 0.875rem; }
            .premium-card h2 + p { font-size: 9px !important; }

            /* Download Template Button */
            #dlTemplate {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
                width: 100%;
            }

            #dlTemplate span { display: inline; }

            /* Batch Form */
            #batchForm .space-y-4 { gap: 0.75rem !important; }
            
            #batchForm .bg-gradient-to-br {
                padding: 0.75rem !important;
            }

            #batchForm label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }

            #batchForm input[type="file"] {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
            }

            #batchUploadBtn {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
                width: 100%;
            }

            #batchForm .text-xs {
                font-size: 9px !important;
                line-height: 1.4;
            }

            #batchForm .bg-blue-50 {
                padding: 0.5rem !important;
            }

            #batchForm .bg-blue-50 p {
                font-size: 9px !important;
            }

            /* Batch Results */
            #batchResult { margin-top: 0.75rem !important; }
            #batchResult .bg-white { padding: 0.75rem !important; }
            #batchResult h3 { font-size: 0.8rem !important; }
            
            #batchSummary,
            #batchErrors,
            #batchCreated {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
            }

            #batchErrors div,
            #batchCreated div {
                font-size: 0.7rem !important;
            }

            #batchErrors .font-semibold,
            #batchCreated .font-semibold {
                font-size: 0.75rem !important;
            }

            /* Search Input */
            #resident-search {
                width: 100% !important;
                max-width: 100% !important;
                font-size: 0.7rem !important;
                padding: 0.5rem 2.25rem 0.5rem 0.625rem !important;
                border-radius: 0.5rem !important;
            }

            #resident-search + i {
                right: 0.625rem !important;
                font-size: 0.7rem !important;
            }

            /* Table Wrapper - Horizontal Scroll */
            .overflow-x-auto { border-radius: 0.75rem !important; }

            /* Table Compression */
            table { font-size: 0.7rem !important; }
            
            thead th {
                font-size: 9px !important;
                padding: 0.5rem 0.625rem !important;
                white-space: nowrap;
            }

            tbody td {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.625rem !important;
                white-space: nowrap;
            }

            /* Empty State Message */
            tbody td[colspan] {
                padding: 1rem !important;
                font-size: 0.7rem !important;
            }

            /* Spacing Reductions */
            .mt-6 { margin-top: 0.75rem !important; }
            .mb-8 { margin-bottom: 0.75rem !important; }
            .py-6 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
            .mb-4 { margin-bottom: 0.5rem !important; }
            .pt-2 { padding-top: 0.5rem !important; }
            .mt-4 { margin-top: 0.75rem !important; }
            .mt-2 { margin-top: 0.5rem !important; }

            /* Icon Sizes */
            .fas, .far, .fab { font-size: inherit; }
        }
    </style>
</head>
<body class="bg-white font-sans">
    <?php include_once('../includes/barangay_official_sec_nav.php'); ?>

    <!-- Hero Header -->
    <section class="container mx-auto px-4 mt-6">
        <div class="premium-gradient rounded-2xl p-6 text-white relative overflow-hidden">
            <div class="orb one"></div>
            <div class="orb two"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shadow"><i class="fas fa-house-user"></i></div>
                    <h1 class="text-2xl md:text-3xl font-bold">Add Resident Account</h1>
                </div>
                <p class="text-blue-100 mt-1">Create a resident and review existing residents below.</p>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-6">
        <!-- Form Card -->
        <div class="premium-card rounded-2xl p-6 mb-8">
            <form id="residentForm" method="POST" action="../controllers/create_resident.php" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="reg_fname" id="reg_fname" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Juan" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                        <input type="text" name="reg_mname" id="reg_mname" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Santos" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="reg_lname" id="reg_lname" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Dela Cruz" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                        <input type="date" name="reg_birthdate" id="reg_birthdate" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Birthdate" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                        <input type="text" name="reg_age" id="reg_age" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700" placeholder="Age" readonly />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">House No.</label>
                        <input type="text" name="reg_house_no" id="reg_house_no" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="123-A" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purok/Street</label>
                        <input type="text" name="reg_purok" id="reg_purok" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Purok 1" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barangay / City</label>
                        <input type="text" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700" value="Barangay Panducot, Calumpit, Bulacan" readonly disabled />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="reg_email" id="reg_email" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="user@example.com" required />
                    </div>
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="reg_pass" id="reg_pass" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="••••••••" required />
                        <button type="button" class="absolute right-3 top-9 text-gray-400 hover:text-gray-600" onclick="togglePassword('reg_pass', this)" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="reg_pass_confirm" id="reg_pass_confirm" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="••••••••" required />
                        <button type="button" class="absolute right-3 top-9 text-gray-400 hover:text-gray-600" onclick="togglePassword('reg_pass_confirm', this)" aria-label="Show password"><i class="fas fa-eye"></i></button>
                        <p id="passwordMismatchMsg" class="text-xs text-red-600 mt-1 hidden">Passwords do not match.</p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="cancelBtn" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"><i class="fas fa-xmark mr-2"></i>Cancel</button>
                    <button id="submitBtn" type="submit" name="Signup" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>
                </div>
            </form>
        </div>

        <!-- NEW: Batch Upload Card -->
        <div class="premium-card rounded-2xl p-6 mb-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white shadow-lg">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Batch Upload Residents</h2>
                        <p class="text-xs text-gray-500 mt-0.5">Upload multiple residents at once using CSV or Excel</p>
                    </div>
                </div>
                <button id="dlTemplate" type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-gradient-to-r from-primary-600 to-primary-700 text-white text-sm font-medium shadow-md hover:shadow-lg hover:from-primary-700 hover:to-primary-800 transition-all duration-200">
                    <i class="fas fa-file-download"></i>
                    <span>Download Template</span>
                </button>
            </div>

            <form id="batchForm" class="space-y-4" enctype="multipart/form-data">
                <div class="bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-dashed border-gray-300 rounded-xl p-6 hover:border-primary-400 transition-colors duration-200">
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                        <div class="flex-1 w-full">
                            <label for="batchFile" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-paperclip mr-1 text-primary-600"></i>Select File
                            </label>
                            <div class="relative">
                                <input type="file" id="batchFile" name="batch_file" accept=".csv,.xlsx" class="block w-full text-sm text-gray-700 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-600 file:text-white hover:file:bg-primary-700 file:cursor-pointer cursor-pointer bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-300" />
                            </div>
                        </div>
                        <button id="batchUploadBtn" type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold shadow-md hover:shadow-lg hover:from-green-700 hover:to-green-800 transition-all duration-200 whitespace-nowrap">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Upload File</span>
                        </button>
                    </div>
                    
                    <div class="mt-4 flex items-start gap-2 text-xs text-gray-600 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                        <div>
                            <p class="font-medium text-blue-900 mb-1">Accepted formats: CSV (.csv) or Excel (.xlsx)</p>
                            <p class="text-blue-700">Required columns: <span class="font-semibold">First Name, Middle Name, Last Name, Email, House No, Purok</span></p>
                            <p class="text-blue-700 mt-0.5">Optional column: <span class="font-semibold">Password</span> (auto-generated if not provided)</p>
                        </div>
                    </div>
                </div>
            </form>

            <div id="batchResult" class="mt-5 hidden">
                <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-chart-bar text-primary-600"></i>
                        <h3 class="font-semibold text-gray-900">Upload Results</h3>
                    </div>
                    
                    <div id="batchSummary" class="text-sm font-medium text-gray-800 mb-3 p-3 bg-gray-50 rounded-lg"></div>
                    
                    <div id="batchErrors" class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3 mb-3 hidden"></div>
                    
                    <div id="batchCreated" class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg p-3 hidden"></div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="premium-card rounded-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Resident Accounts</h2>
                <div class="relative">
                    <input id="resident-search" type="text" placeholder="Search name, email, or address..." class="w-64 max-w-full pl-3 pr-10 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" aria-hidden="true"></i>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse overflow-hidden rounded-xl">
                    <thead class="table-head">
                        <tr>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Name</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Email</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Address</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Action</th>
                        </tr>
                    </thead>
                    <tbody id="res-users-body" class="divide-y divide-gray-100">
                        <?php if (!empty($residents)): ?>
                            <?php foreach ($residents as $r): 
                                $name = trim(($r['first_name'] ?? '') . ' ' . ($r['middle_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
                                $search = strtolower(trim($name . ' ' . ($r['email'] ?? '') . ' ' . ($r['address'] ?? '')));
                                $rid = (int)($r['resident_id'] ?? 0);
                            ?>
                            <tr class="odd:bg-white even:bg-blue-50/30 hover:bg-primary-50 transition-colors cursor-pointer" data-search-text="<?= htmlspecialchars($search) ?>" onclick="location.href='view_account.php?type=resident&id=<?= $rid ?>'">
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($name) ?></td>
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($r['email'] ?? '') ?></td>
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($r['address'] ?? '') ?></td>
                                <td class="p-3 text-gray-800">
                                    <a href="view_account.php?type=resident&id=<?= $rid ?>" title="View account" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-primary-700 hover:bg-primary-50" onclick="event.stopPropagation();">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="p-6 text-center text-gray-500">No resident accounts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'sidebar_.php'; ?>
    <script>
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.classList.replace('fa-eye','fa-eye-slash'); }
            else { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
        }

        // Enable submit only when passwords match and non-empty
        const password = document.getElementById('reg_pass');
        const confirmPassword = document.getElementById('reg_pass_confirm');
        const mismatchMsg = document.getElementById('passwordMismatchMsg');
        const submitBtn = document.getElementById('submitBtn');
        function validatePasswords() {
            const hasBoth = password.value.length > 0 && confirmPassword.value.length > 0;
            const matches = password.value === confirmPassword.value;
            if (hasBoth && !matches) {
                mismatchMsg?.classList?.remove('hidden');
                submitBtn.disabled = true;
            } else {
                mismatchMsg?.classList?.add('hidden');
                submitBtn.disabled = !(hasBoth && matches);
            }
        }
        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
        validatePasswords();

        // Cancel: reset form and validation state
        const cancelBtn = document.getElementById('cancelBtn');
        cancelBtn?.addEventListener('click', () => {
            const form = document.getElementById('residentForm');
            form?.reset();
            mismatchMsg?.classList?.add('hidden');
            submitBtn.disabled = true;
        });

        // Client-side table search
        const searchInput = document.getElementById('resident-search');
        function updateFilter() {
            const q = (searchInput?.value || '').toLowerCase().trim();
            document.querySelectorAll('#res-users-body [data-search-text]')?.forEach(row => {
                const txt = (row.getAttribute('data-search-text') || '').toLowerCase();
                row.style.display = txt.includes(q) ? '' : 'none';
            });
        }
        searchInput?.addEventListener('input', updateFilter);
        updateFilter();

        // Batch upload JS
        const batchForm = document.getElementById('batchForm');
        const batchFile = document.getElementById('batchFile');
        const batchBtn  = document.getElementById('batchUploadBtn');
        const batchRes  = document.getElementById('batchResult');
        const batchSummary = document.getElementById('batchSummary');
        const batchErrors  = document.getElementById('batchErrors');
        const batchCreated = document.getElementById('batchCreated');
        const dlTemplateBtn = document.getElementById('dlTemplate');

        dlTemplateBtn?.addEventListener('click', ()=>{
            const headers = ['First Name','Middle Name','Last Name','Email','House No','Purok','Password'];
            const sample  = ['Juan','Santos','Dela Cruz','juan@example.com','123-A','Purok 1','']; 
            const csv = [headers.join(','), sample.join(',')].join('\r\n');
            const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url; a.download = 'resident_batch_template.csv';
            document.body.appendChild(a); a.click(); a.remove();
            URL.revokeObjectURL(url);
        });

        batchForm?.addEventListener('submit', async (e)=>{
            e.preventDefault();
            if (!batchFile?.files?.length) { alert('Select a .csv or .xlsx file.'); return; }
            batchBtn.disabled = true;
            batchSummary.textContent = 'Uploading...';
            batchErrors.textContent = '';
            batchErrors.classList.add('hidden');
            batchCreated.textContent = '';
            batchCreated.classList.add('hidden');
            batchRes.classList.remove('hidden');

            try {
                const fd = new FormData();
                fd.append('batch_file', batchFile.files[0]);

                const resp = await fetch('../controllers/batch_create_residents.php', { method:'POST', body: fd });
                const ct = resp.headers.get('content-type') || '';
                const json = ct.includes('application/json') ? await resp.json() : { success:false, message:'Unexpected response', raw: await resp.text() };

                if (!resp.ok || !json.success) {
                    console.error('Batch upload failed:', json);
                    batchSummary.textContent = 'Batch upload failed.';
                    return;
                }

                const r = json.result || {};
                batchSummary.innerHTML = `<i class="fas fa-check-circle text-green-600 mr-2"></i>Upload Complete! Inserted: <strong>${r.inserted || 0}</strong>, Skipped: <strong>${r.skipped || 0}</strong>`;
                
                // Errors list
                if (Array.isArray(r.errors) && r.errors.length) {
                    const lines = r.errors.map(e => `<div class="flex items-start gap-2 mb-1"><i class="fas fa-exclamation-circle mt-0.5"></i><span>Row ${e.row}: ${e.email || '(no email)'} — ${e.reason}</span></div>`);
                    batchErrors.innerHTML = '<div class="font-semibold mb-2"><i class="fas fa-times-circle mr-1"></i>Errors:</div>' + lines.join('');
                    batchErrors.classList.remove('hidden');
                } else {
                    batchErrors.classList.add('hidden');
                }
                
                // Created list (optional)
                if (Array.isArray(r.created) && r.created.length) {
                    const items = r.created.map(c => `<div class="flex items-start gap-2 mb-1"><i class="fas fa-check-circle mt-0.5"></i><span>Row ${c.row}: ${c.email} </span></div>`);
                    batchCreated.innerHTML = '<div class="font-semibold mb-2"><i class="fas fa-user-check mr-1"></i>Successfully Created:</div>' + items.join('');
                    batchCreated.classList.remove('hidden');
                } else {
                    batchCreated.classList.add('hidden');
                }
            } catch (err) {
                console.error('Batch upload error:', err);
                batchSummary.innerHTML = '<i class="fas fa-exclamation-triangle text-red-600 mr-2"></i>Batch upload failed.';
            } finally {
                batchBtn.disabled = false;
            }
        });
        // Birthdate / Age handling for admin add resident
        const resBirth = document.getElementById('reg_birthdate');
        const resAge = document.getElementById('reg_age');
        function computeAgeFromDateString(datestr) {
            if (!datestr) return null;
            const parts = datestr.split('-');
            if (parts.length !== 3) return null;
            const y = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10) - 1;
            const d = parseInt(parts[2], 10);
            const bd = new Date(y, m, d);
            if (isNaN(bd.getTime())) return null;
            const today = new Date();
            let age = today.getFullYear() - bd.getFullYear();
            const mDiff = today.getMonth() - bd.getMonth();
            if (mDiff < 0 || (mDiff === 0 && today.getDate() < bd.getDate())) {
                age--;
            }
            return age;
        }
        if (resBirth) {
            const today = new Date();
            today.setDate(today.getDate() - 1);
            const y = today.getFullYear();
            const m = String(today.getMonth() + 1).padStart(2, '0');
            const d = String(today.getDate()).padStart(2, '0');
            resBirth.setAttribute('max', `${y}-${m}-${d}`);
            resBirth.addEventListener('change', () => {
                const age = computeAgeFromDateString(resBirth.value);
                resAge.value = (age !== null && !isNaN(age)) ? age : '';
            });
            resBirth.addEventListener('input', () => {
                const age = computeAgeFromDateString(resBirth.value);
                resAge.value = (age !== null && !isNaN(age)) ? age : '';
            });
        }
    </script>
</body>
</html>