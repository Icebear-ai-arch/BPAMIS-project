
<?php
// Admin-style Add External User page: premium form + table of existing accounts
require_once(__DIR__ . '/../server/server.php');
include '../controllers/session_control.php';
// Fetch external users
$externals = [];
if (isset($conn)) {
    if ($stmt = $conn->prepare("SELECT external_complaint_id, external_username, first_name, middle_name, last_name, email, contact_number, address, isActive FROM external_complainant ORDER BY external_complaint_id DESC")) {
        $stmt->execute();
        $result = bpamis_stmt_get_result($stmt);
        if ($result) {
            $externals = $result->fetch_all(MYSQLI_ASSOC);
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
    <title>Add External User • Admin</title>
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
        .badge.green { background:#dcfce7; color:#166534; }
        .badge.gray { background:#e5e7eb; color:#374151; }
        .orb { position:absolute; border-radius:50%; filter:blur(40px); opacity:.5; mix-blend-mode:multiply; pointer-events:none; }
        .orb.one { width: 380px; height: 380px; background: linear-gradient(135deg, #0c9ced, #7cccfd); top:-120px; right:-120px; }
        .orb.two { width: 260px; height: 260px; background: linear-gradient(135deg, #bae2fd, #e0effe); bottom:-80px; left:-100px; }
        
        /* Mobile optimizations: compact and compressed layout */
        @media (max-width: 640px) {
            /* Prevent horizontal scroll */
            html, body {
                overflow-x: hidden !important;
                max-width: 100vw !important;
            }
            
            body {
                position: relative !important;
            }
            
            /* Preserve sidebar font sizes */
            #sidebar, #sidebar *, 
            #sidebar p, #sidebar span, #sidebar label, #sidebar div,
            #sidebar button, #sidebar a, #sidebar h1, #sidebar h2, #sidebar h3, #sidebar h4,
            #sidebar input, #sidebar select, #sidebar textarea,
            #sidebar i.fas, #sidebar i.far, #sidebar i.fa {
                font-size: inherit !important;
            }
            
            /* Container padding */
            .container {
                padding: 0.75rem !important;
            }
            
            /* Hero header - compact */
            .premium-gradient {
                padding: 0.75rem !important;
                border-radius: 1rem !important;
            }
            
            .premium-gradient h1 {
                font-size: 1.125rem !important;
            }
            
            .premium-gradient p {
                font-size: 0.7rem !important;
            }
            
            .premium-gradient .w-10 {
                width: 2rem !important;
                height: 2rem !important;
            }
            
            /* Reduce orbs on mobile */
            .orb.one {
                width: 200px !important;
                height: 200px !important;
            }
            
            .orb.two {
                width: 150px !important;
                height: 150px !important;
            }
            
            /* Premium cards - compact */
            .premium-card {
                padding: 0.75rem !important;
                border-radius: 1rem !important;
                margin-bottom: 1rem !important;
            }
            
            /* Form elements */
            form label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
            }
            
            form input,
            form select,
            form textarea {
                font-size: 0.7rem !important;
                padding: 0.5rem !important;
            }
            
            /* Grid layout - stack on mobile */
            .grid.grid-cols-1.md\\:grid-cols-2,
            .grid.grid-cols-1.md\\:grid-cols-3 {
                grid-template-columns: 1fr !important;
                gap: 0.5rem !important;
            }
            
            /* Buttons - compact */
            button, a.inline-flex {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            button i, a.inline-flex i {
                font-size: 0.7rem !important;
                margin-right: 0.25rem !important;
            }
            
            /* Table section header */
            .premium-card h2 {
                font-size: 0.875rem !important;
            }
            
            /* Search input */
            .premium-card input[type="text"] {
                width: 100% !important;
                font-size: 0.7rem !important;
                padding: 0.5rem 2rem 0.5rem 0.5rem !important;
            }
            
            .premium-card .fa-search {
                font-size: 0.7rem !important;
            }
            
            /* Table - compact with horizontal scroll */
            .overflow-x-auto {
                margin: 0 -0.75rem !important;
                padding: 0 0.75rem !important;
            }
            
            table {
                font-size: 0.7rem !important;
            }
            
            table th,
            table td {
                padding: 0.5rem 0.625rem !important;
                white-space: nowrap !important;
            }
            
            table th {
                font-size: 9px !important;
                font-weight: 600 !important;
            }
            
            /* Badges - smaller */
            .badge {
                font-size: 9px !important;
                padding: 0.2rem 0.4rem !important;
            }
            
            /* Form spacing */
            form .space-y-5 > * + * {
                margin-top: 0.75rem !important;
            }
            
            /* Form buttons container */
            form .flex.items-center.justify-end {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }
            
            form .flex.items-center.justify-end button {
                width: 100% !important;
                justify-content: center !important;
            }
            
            /* Password mismatch message */
            #passwordMismatchMsg {
                font-size: 9px !important;
            }
            
            /* Spacing adjustments */
            .mt-6, .mt-8 {
                margin-top: 1rem !important;
            }
            
            .mb-4, .mb-6, .mb-8 {
                margin-bottom: 0.75rem !important;
            }
            
            .py-6 {
                padding-top: 1rem !important;
                padding-bottom: 1rem !important;
            }
            
            .gap-3 {
                gap: 0.5rem !important;
            }
            
            .gap-4 {
                gap: 0.5rem !important;
            }
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
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shadow"><i class="fas fa-user-plus"></i></div>
                    <h1 class="text-2xl md:text-3xl font-bold">Add External Account</h1>
                </div>
                <p class="text-blue-100 mt-1">Create an external user and review existing accounts below.</p>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-6">

        <!-- Form Card -->
        <div class="premium-card rounded-2xl p-6 mb-8">
            <form id="registerForm" method="POST" action="../controllers/create_externaldb.php" class="space-y-5">
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

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="reg_address" id="reg_address" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Street, Barangay, City" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="reg_email" id="reg_email" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="user@example.com" required />
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
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="text" name="reg_contact" id="reg_contact" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="09XXXXXXXXX" required />
                    </div>
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="reg_pass" id="reg_pass" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="••••••••" required />
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-2/2 text-gray-400 hover:text-gray-600" onclick="togglePassword('reg_pass', this)" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="reg_pass_confirm" id="reg_pass_confirm" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="••••••••" required />
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-2/2 text-gray-400 hover:text-gray-600" onclick="togglePassword('reg_pass_confirm', this)" aria-label="Show password"><i class="fas fa-eye"></i></button>
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

        <!-- Table Card -->
        <div class="premium-card rounded-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-900">External Accounts</h2>
                <div class="relative">
                    <input id="user-search" type="text" placeholder="Search name, email, or contact..." class="w-64 max-w-full pl-3 pr-10 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" aria-hidden="true"></i>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse overflow-hidden rounded-xl">
                    <thead class="table-head">
                        <tr>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">ID</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Name</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Email</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Contact</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Address</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Status</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ext-users-body" class="divide-y divide-gray-100">
                        <?php if (!empty($externals)): ?>
                            <?php foreach ($externals as $u): 
                                $name = trim(($u['first_name'] ?? '') . ' ' . ($u['middle_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                $search = strtolower(trim($u['external_username'] . ' ' . $name . ' ' . ($u['email'] ?? '') . ' ' . ($u['contact_number'] ?? '') . ' ' . ($u['address'] ?? '')));
                                $active = (int)($u['isActive'] ?? 0) === 1;
                            ?>
                            <?php $extViewUrlBase = 'view_account.php?type=external&id='; ?>
                            <tr class="odd:bg-white even:bg-blue-50/30 hover:bg-primary-50 transition-colors cursor-pointer" data-search-text="<?= htmlspecialchars($search) ?>" onclick="window.location.href='<?= $extViewUrlBase . (int)$u['external_complaint_id'] ?>'">
                                <td class="p-3 text-gray-800"><?= (int)$u['external_complaint_id'] ?></td>
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($name) ?></td>
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($u['contact_number'] ?? '') ?></td>
                                <td class="p-3 text-gray-800"><?= htmlspecialchars($u['address'] ?? '') ?></td>
                                <td class="p-3">
                                    <?php if ($active): ?>
                                        <span class="badge green">Active</span>
                                    <?php else: ?>
                                        <span class="badge gray">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-right">
                                    <a href="<?= 'view_account.php?type=external&id=' . (int)$u['external_complaint_id'] ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-md bg-white/80 hover:bg-primary-50 text-primary-700 shadow-sm" aria-label="View external account <?= htmlspecialchars($name) ?>">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-6 text-center text-gray-500">No external accounts found.</td>
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
            const form = document.getElementById('registerForm');
            form?.reset();
            mismatchMsg?.classList?.add('hidden');
            submitBtn.disabled = true;
        });

        // Client-side table search
        const searchInput = document.getElementById('user-search');
        function updateFilter() {
            const q = (searchInput?.value || '').toLowerCase().trim();
            document.querySelectorAll('#ext-users-body [data-search-text]')?.forEach(row => {
                const txt = (row.getAttribute('data-search-text') || '').toLowerCase();
                row.style.display = txt.includes(q) ? '' : 'none';
            });
        }
        searchInput?.addEventListener('input', updateFilter);
        updateFilter();

        // Birthdate / Age handling for external user form
        const extBirth = document.getElementById('reg_birthdate');
        const extAge = document.getElementById('reg_age');
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
        if (extBirth) {
            const today = new Date();
            today.setDate(today.getDate() - 1);
            const y = today.getFullYear();
            const m = String(today.getMonth() + 1).padStart(2, '0');
            const d = String(today.getDate()).padStart(2, '0');
            extBirth.setAttribute('max', `${y}-${m}-${d}`);
            extBirth.addEventListener('change', () => {
                const age = computeAgeFromDateString(extBirth.value);
                extAge.value = (age !== null && !isNaN(age)) ? age : '';
            });
            extBirth.addEventListener('input', () => {
                const age = computeAgeFromDateString(extBirth.value);
                extAge.value = (age !== null && !isNaN(age)) ? age : '';
            });
        }
    </script>
</body>
</html>