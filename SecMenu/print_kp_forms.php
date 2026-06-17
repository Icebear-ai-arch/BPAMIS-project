
<?php
// Clean and modernized print page for KP Forms with multi-select printing
include '../controllers/session_control.php';
include '../server/server.php'; // Keep include for consistency; not used directly here

// Mapping from form_id to PDF filename (aligned with view_kp_forms.php)
$formPdfMap = [
    'KP-Form-01' => 'KP Form 1 - Notice to Constitute Lupon.pdf',
    'KP-Form-02' => 'KP Form 2 - Appointment.pdf',
    'KP-Form-03' => 'KP Form 3.pdf',
    'KP-Form-04' => 'KP Form 4 - List of Appointed Lupon Members.pdf',
    'KP-Form-05' => 'KP Form 5 - Oath of Office.pdf',
    'KP-Form-06' => 'KP Form 6.pdf',
    'KP-Form-07' => 'KP Form 7.pdf',
    'KP-Form-08' => 'KP Form 8.pdf',
    'KP-Form-09' => 'KP Form 9.pdf',
    'KP-Form-10' => 'KP Form 10.pdf',
    'KP-Form-11' => 'KP Form 11.pdf',
    'KP-Form-12' => 'KP Form 12.pdf',
    'KP-Form-13' => 'KP Form 13.pdf',
    'KP-Form-14' => 'KP Form 14.pdf',
    'KP-Form-15' => 'KP Form 15.pdf',
    'KP-Form-16' => 'KP Form 16.pdf',
    'KP-Form-17' => 'KP Form 17.pdf',
    'KP-Form-18' => 'KP Form 18.pdf',
    'KP-Form-19' => 'KP Form 19.pdf',
    'KP-Form-20' => 'KP Form 20.pdf',
    'KP-Form-20-A' => 'KP Form 20-A.pdf',
    'KP-Form-20-B' => 'KP Form 20-B.pdf',
    'KP-Form-21' => 'KP Form 21.pdf',
    'KP-Form-22' => 'KP Form 22.pdf',
    'KP-Form-23' => 'KP Form 23.pdf',
    'KP-Form-24' => 'KP Form 24.pdf',
    'KP-Form-25' => 'KP Form 25.pdf',
];

// Define KP Forms metadata for rendering
$kpForms = [
    ['id'=>'KP-Form-01','name'=>'Notice to Constitute the Lupon','description'=>'Official notice for the constitution of the Lupong Tagapamayapa.','icon'=>'fa-users'],
    ['id'=>'KP-Form-02','name'=>'Appointment Letter','description'=>'Letter of appointment for Lupon members.','icon'=>'fa-envelope'],
    ['id'=>'KP-Form-03','name'=>'Notice of Appointment','description'=>'Notice informing of appointment to the Lupon.','icon'=>'fa-bell'],
    ['id'=>'KP-Form-04','name'=>'List of Appointed Lupon Members','description'=>'Official list of appointed Lupon members.','icon'=>'fa-list'],
    ['id'=>'KP-Form-05','name'=>'Lupon Member Oath Statement','description'=>'Oath statement for Lupon members.','icon'=>'fa-hand-holding-heart'],
    ['id'=>'KP-Form-06','name'=>'Withdrawal of Appointment','description'=>'Form for withdrawal of Lupon appointment.','icon'=>'fa-times-circle'],
    ['id'=>'KP-Form-07','name'=>'Complainant\'s Form','description'=>'Official form to be filled out by the complainant to initiate a case.','icon'=>'fa-file-signature'],
    ['id'=>'KP-Form-08','name'=>'Notice of Hearing','description'=>'Notice sent to parties for scheduled hearing.','icon'=>'fa-calendar-check'],
    ['id'=>'KP-Form-09','name'=>'Summon for the Respondent','description'=>'Official summons requiring the respondent to appear before the Lupong Tagapamayapa.','icon'=>'fa-gavel'],
    ['id'=>'KP-Form-10','name'=>'Notice for Constitution of Pangkat','description'=>'Notice for the constitution of the Pangkat ng Tagapagkasundo.','icon'=>'fa-user-friends'],
    ['id'=>'KP-Form-11','name'=>'Notice to Chosen Pangkat Member','description'=>'Notice to inform chosen Pangkat members.','icon'=>'fa-user-check'],
    ['id'=>'KP-Form-12','name'=>'Notice of Hearing (Conciliation Proceedings)','description'=>'Notice for conciliation proceedings before the Pangkat.','icon'=>'fa-handshake'],
    ['id'=>'KP-Form-13','name'=>'Subpoena Letter','description'=>'Subpoena to compel attendance of witnesses.','icon'=>'fa-envelope-open-text'],
    ['id'=>'KP-Form-14','name'=>'Agreement for Arbitration','description'=>'Agreement to submit the dispute to arbitration by the Punong Barangay.','icon'=>'fa-balance-scale'],
    ['id'=>'KP-Form-15','name'=>'Arbitration Award','description'=>'Decision rendered by the Punong Barangay after arbitration.','icon'=>'fa-trophy'],
    ['id'=>'KP-Form-16','name'=>'Amicable Settlement','description'=>'Written agreement between the parties resolving their dispute.','icon'=>'fa-file-contract'],
    ['id'=>'KP-Form-17','name'=>'Repudiation','description'=>'Form for repudiation of amicable settlement.','icon'=>'fa-undo'],
    ['id'=>'KP-Form-18','name'=>'Notice of Hearing for Complainant','description'=>'Notice of hearing specifically for the complainant.','icon'=>'fa-calendar-alt'],
    ['id'=>'KP-Form-19','name'=>'Notice of Hearing for Respondent','description'=>'Notice of hearing specifically for the respondent.','icon'=>'fa-calendar-alt'],
    ['id'=>'KP-Form-20','name'=>'Certification to File Action (from Lupon Secretary)','description'=>'Certification from Lupon Secretary allowing parties to file in court.','icon'=>'fa-certificate'],
    ['id'=>'KP-Form-20','name'=>'Certification to File Action (from Pangkat Secretary)','description'=>'Certification from Pangkat Secretary allowing parties to file in court.','icon'=>'fa-certificate'],
    ['id'=>'KP-Form-22','name'=>'Certification to File Action','description'=>'General certification allowing parties to file a case in court after failed settlement.','icon'=>'fa-certificate'],
    ['id'=>'KP-Form-23','name'=>'Certification to Bar Action','description'=>'Certification barring complainant from filing action in court.','icon'=>'fa-ban'],
    ['id'=>'KP-Form-24','name'=>'Certification to Bar Counterclaim','description'=>'Certification barring respondent from filing counterclaim in court.','icon'=>'fa-ban'],
    ['id'=>'KP-Form-25','name'=>'Motion for Execution','description'=>'Motion requesting execution of amicable settlement or arbitration award.','icon'=>'fa-clipboard-check'],
    // ['id'=>'KP-Form-26','name'=>'Notice of Hearing (Re: Motion for Execution)','description'=>'Notice of hearing for motion for execution.','icon'=>'fa-calendar-check'],
    // ['id'=>'KP-Form-27','name'=>'Notice of Execution','description'=>'Notice informing parties of the execution of amicable settlement or arbitration award.','icon'=>'fa-clipboard-check'],
    // //['id'=>'KP-Form-28','name'=>'Monthly Transmittal of Final Reports','description'=>'Monthly report of final settlements and arbitration awards.','icon'=>'fa-chart-bar'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Print KP Forms - Barangay Panducot</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { primary: { 50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76' } },
                    fontFamily: { poppins: ['Poppins','sans-serif'] },
                    animation: { float: 'float 13s ease-in-out infinite', fadeIn: 'fadeIn .6s ease-out', scaleIn: 'scaleIn .5s ease-out' },
                    keyframes: {
                        float: { '0%,100%': { transform:'translateY(0)' }, '50%': { transform:'translateY(-12px)' } },
                        fadeIn: { '0%': { opacity:0 }, '100%': { opacity:1 } },
                        scaleIn: { '0%': { opacity:0, transform:'scale(.96)' }, '100%': { opacity:1, transform:'scale(1)' } }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family:'Poppins',sans-serif; background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%); }
        .premium-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .premium-card { background: linear-gradient(135deg, rgba(255,255,255,.95) 0%, rgba(255,255,255,.85) 100%); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,.35); box-shadow: 0 12px 36px rgba(0,0,0,.08); transition: all .3s ease; }
        .premium-card:hover { transform: translateY(-4px); box-shadow: 0 20px 50px rgba(0,0,0,.12); }
        .icon-badge { width: 44px; height: 44px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea, #36b3f9); color:#fff; box-shadow: 0 8px 24px rgba(102,126,234,.35); }
        .chip { display:inline-flex; align-items:center; gap:.4rem; padding:.25rem .55rem; border-radius: 999px; font-size:.72rem; font-weight:600; background:#e0effe; color:#026aad; }
        .orb { position:absolute; border-radius:50%; filter:blur(40px); opacity:.55; mix-blend-mode:multiply; pointer-events:none; }
        .orb.one { width: 460px; height: 460px; background: linear-gradient(135deg, #0c9ced, #7cccfd); top:-140px; right:-120px; animation: float 14s ease-in-out infinite; }
        .orb.two { width: 340px; height: 340px; background: linear-gradient(135deg, #bae2fd, #e0effe); bottom:-120px; left:-100px; animation: float 11s ease-in-out reverse infinite; }
        
        /* Mobile Responsive Styles */
        @media (max-width: 640px) {
            /* Container and Body */
            .container { padding-left: 0.5rem; padding-right: 0.5rem; }
            body { font-size: 0.7rem; }

            /* Orb Size Reduction */
            .orb.one { width: 200px; height: 200px; top: -60px; right: -60px; }
            .orb.two { width: 150px; height: 150px; bottom: -40px; left: -50px; }

            /* Hero Section Compression */
            section.container { margin-top: 0.75rem !important; }
            section.container .premium-gradient { 
                padding: 0.75rem !important; 
                border-radius: 1rem !important; 
            }
            
            section.container h1 { 
                font-size: 1.125rem !important; 
                line-height: 1.3; 
            }
            
            section.container p { 
                font-size: 0.7rem !important; 
                margin-top: 0.5rem !important; 
            }

            /* Hero Floating Orbs */
            section.container .absolute.top-10 { width: 1rem !important; height: 1rem !important; }
            section.container .absolute.bottom-10 { width: 0.75rem !important; height: 0.75rem !important; }

            /* Search and Controls Grid */
            section.container .grid { 
                grid-template-columns: 1fr !important; 
                gap: 0.5rem !important; 
                margin-top: 0.75rem !important; 
            }

            /* Search Input */
            section.container input[type="text"] {
                font-size: 0.7rem !important;
                padding: 0.5rem 2rem 0.5rem 0.75rem !important;
                border-radius: 0.5rem !important;
            }

            section.container .fa-search {
                right: 0.625rem !important;
                font-size: 0.7rem !important;
            }

            /* Controls Row */
            section.container .flex.items-center.gap-4 {
                gap: 0.5rem !important;
                flex-wrap: wrap;
            }

            section.container label {
                font-size: 0.7rem !important;
            }

            section.container input[type="checkbox"] {
                width: 0.875rem !important;
                height: 0.875rem !important;
            }

            /* Download Button */
            #download-selected {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
                border-radius: 0.5rem !important;
            }

            /* Main Grid - Single Column */
            .container > .grid {
                grid-template-columns: 1fr !important;
                gap: 0.75rem !important;
                margin-top: 0.75rem !important;
            }

            /* Premium Cards */
            .premium-card {
                padding: 0.75rem !important;
                border-radius: 1rem !important;
            }

            .premium-card:hover {
                transform: none !important;
            }

            /* Card Content */
            .premium-card .flex.items-start {
                gap: 0.5rem !important;
            }

            .premium-card input[type="checkbox"] {
                margin-top: 0.25rem !important;
                width: 0.875rem !important;
                height: 0.875rem !important;
            }

            /* Icon Badge */
            .icon-badge {
                width: 2rem !important;
                height: 2rem !important;
                border-radius: 0.5rem !important;
                font-size: 0.75rem !important;
            }

            /* Card Text */
            .premium-card h3 {
                font-size: 0.875rem !important;
                line-height: 1.3;
            }

            .premium-card p {
                font-size: 0.7rem !important;
                margin-top: 0.25rem !important;
                line-height: 1.4;
            }

            /* Chip/Badge */
            .chip {
                font-size: 9px !important;
                padding: 0.15rem 0.4rem !important;
                gap: 0.25rem !important;
            }

            /* Card Footer Buttons */
            .premium-card .flex.items-center.justify-between {
                margin-top: 0.5rem !important;
                padding-top: 0.5rem !important;
                flex-direction: column !important;
                gap: 0.5rem !important;
            }

            .premium-card .flex.items-center.justify-between a,
            .premium-card .flex.items-center.justify-between span {
                width: 100% !important;
                justify-content: center;
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
                border-radius: 0.5rem !important;
            }

            .premium-card .flex.items-center.justify-between i {
                margin-right: 0.25rem !important;
                font-size: 0.65rem !important;
            }

            /* Tip Card */
            .container.mx-auto.mt-8.mb-10 {
                margin-top: 0.75rem !important;
                margin-bottom: 1rem !important;
            }

            .container.mx-auto.mt-8.mb-10 .premium-card {
                padding: 0.75rem !important;
            }

            .container.mx-auto.mt-8.mb-10 .flex.items-start {
                gap: 0.5rem !important;
            }

            .container.mx-auto.mt-8.mb-10 .w-10 {
                width: 2rem !important;
                height: 2rem !important;
                font-size: 0.75rem !important;
                border-radius: 0.5rem !important;
            }

            .container.mx-auto.mt-8.mb-10 h3 {
                font-size: 0.875rem !important;
            }

            .container.mx-auto.mt-8.mb-10 p {
                font-size: 0.7rem !important;
                margin-top: 0.25rem !important;
                line-height: 1.4;
            }

            /* Spacing Reductions */
            .mt-8 { margin-top: 0.75rem !important; }
            .mt-5 { margin-top: 0.75rem !important; }
            .mt-4 { margin-top: 0.5rem !important; }
            .mt-2 { margin-top: 0.25rem !important; }
            .mt-1 { margin-top: 0.25rem !important; }
            .gap-6 { gap: 0.75rem !important; }
            .gap-4 { gap: 0.5rem !important; }
            .gap-3 { gap: 0.5rem !important; }
            .px-4 { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }
            .py-3 { padding-top: 0.5rem !important; padding-bottom: 0.5rem !important; }
            .p-8 { padding: 0.75rem !important; }
            .p-6 { padding: 0.75rem !important; }

            /* Icon Sizes */
            .fas, .far, .fab { font-size: inherit; }
        }

        @media print { .no-print { display: none !important; } body { font-size: 12pt; background: #fff; } }
    </style>
</head>
<body class="bg-white font-sans relative overflow-x-hidden">
    <div class="orb one"></div>
    <div class="orb two"></div>
    <?php include 'sidebar_.php'; ?>
    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- Hero -->
    <section class="container mx-auto mt-8 px-4 no-print">
        <div class="premium-gradient rounded-2xl p-8 text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute top-10 left-10 w-16 h-16 bg-white rounded-full animate-[float_10s_ease-in-out_infinite]"></div>
                <div class="absolute bottom-10 right-10 w-10 h-10 bg-white rounded-full animate-[float_12s_ease-in-out_infinite]"></div>
            </div>
            <div class="relative z-10">
                <h1 class="text-2xl sm:text-3xl font-bold">Print Katarungang Pambarangay (KP) Forms</h1>
                <p class="mt-2 text-blue-100 max-w-3xl">Select one or multiple forms to print. You can also open the fillable template for each form.</p>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="relative">
                        <input id="kp-search" type="text" placeholder="Search forms by ID or name..." class="w-full px-4 py-3 rounded-xl bg-white/95 text-gray-700 placeholder-gray-400 shadow focus:outline-none focus:ring-4 focus:ring-white/30" />
                        <i class="fas fa-search absolute right-4 top-1/2 -translate-y-1/2 text-primary-600"></i>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-2 text-blue-50"><input id="select-all" type="checkbox" class="h-4 w-4"> <span>Select All</span></label>
                        <button id="download-selected" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm shadow disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-download mr-2"></i>Download Selected
                        </button>
                        
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container mx-auto mt-8 px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($kpForms as $form):
                $id = $form['id'];
                $name = $form['name'];
                $desc = $form['description'];
                $icon = $form['icon'];
                $pdfFile = isset($formPdfMap[$id]) ? $formPdfMap[$id] : '';
                $exists = $pdfFile && file_exists(__DIR__ . '/KP-Forms/' . $pdfFile);
                $pdfUrl = $exists ? ('KP-Forms/' . rawurlencode($pdfFile)) : '';
                $templateLink = '../SecMenu/KP-Forms/fill_kp_forms.php?form=' . rawurlencode($id);
            ?>
                <div class="premium-card rounded-2xl p-6 flex flex-col animate-scaleIn" data-search-text="<?= htmlspecialchars($id . ' ' . $name . ' ' . $desc) ?>">
                    <div class="flex items-start gap-4">
                        <input type="checkbox" class="kp-check mt-2 h-4 w-4" data-pdf-url="<?= htmlspecialchars($pdfUrl) ?>" <?= $exists ? '' : 'disabled' ?> />
                        <div class="icon-badge flex-shrink-0"><i class="fas <?= htmlspecialchars($icon) ?>"></i></div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-3 flex-wrap">
                                <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($name) ?></h3>
                                <span class="chip"><?= htmlspecialchars($id) ?></span>
                            </div>
                            <p class="text-gray-600 mt-1"><?= htmlspecialchars($desc) ?></p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                        <?php if ($exists): ?>
                            <a href="<?= $pdfUrl ?>" target="_blank" class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm shadow"><i class="fas fa-print mr-2"></i>Print</a>
                        <?php else: ?>
                            <span class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-200 text-gray-500 text-sm cursor-not-allowed"><i class="fas fa-print mr-2"></i>Unavailable</span>
                        <?php endif; ?>
                        <!--<a href="<?= $templateLink ?>" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm shadow"><i class="fas fa-file-signature mr-2"></i>Open Template</a>-->
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="container mx-auto mt-8 mb-10 no-print">
            <div class="premium-card rounded-2xl p-6 border border-blue-100">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-700 flex items-center justify-center"><i class="fas fa-circle-info"></i></div>
                    <div>
                        <h3 class="text-lg font-semibold text-blue-900">Tip</h3>
                        <p class="text-blue-700 mt-1">Use the checkbox to select multiple forms and click “Download Selected”. Each form will be downloaded directly.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Client-side search filter
        const searchInput = document.getElementById('kp-search');
        function updateVisible() {
            const q = (searchInput?.value || '').toLowerCase().trim();
            document.querySelectorAll('[data-search-text]').forEach(card => {
                const txt = (card.getAttribute('data-search-text') || '').toLowerCase();
                const visible = txt.includes(q);
                card.style.display = visible ? '' : 'none';
            });
        }
        if (searchInput) searchInput.addEventListener('input', updateVisible);

        // Select all handling
        const selectAll = document.getElementById('select-all');
        const downloadBtn = document.getElementById('download-selected');
        function updateDownloadEnabled() {
            const anyChecked = Array.from(document.querySelectorAll('.kp-check:checked')).length > 0;
            if (downloadBtn) downloadBtn.disabled = !anyChecked;
        }
        if (selectAll) {
            selectAll.addEventListener('change', () => {
                const checks = document.querySelectorAll('.kp-check');
                checks.forEach(chk => {
                    const card = chk.closest('[data-search-text]');
                    const isHidden = card && card.style.display === 'none';
                    if (!isHidden && !chk.disabled) chk.checked = selectAll.checked;
                });
                updateDownloadEnabled();
            });
        }
        document.addEventListener('change', (e) => {
            if (e.target?.classList?.contains('kp-check')) updateDownloadEnabled();
        });

        // Download selected: trigger direct downloads via anchor with download attribute
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                const selected = Array.from(document.querySelectorAll('.kp-check:checked'));
                if (!selected.length) return;
                selected.forEach(chk => {
                    const url = chk.getAttribute('data-pdf-url');
                    if (!url) return;
                    try {
                        const a = document.createElement('a');
                        a.href = url;
                        // Try to derive a filename from the URL
                        const seg = url.split('/').pop() || '';
                        try { a.download = decodeURIComponent(seg); } catch { a.download = seg; }
                        a.style.display = 'none';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                    } catch (_) {
                        // Fallback: open in new tab if download attribute fails
                        window.open(url, '_blank');
                    }
                });
            });
        }
        // Initial
        updateVisible();
        updateDownloadEnabled();
    </script>
    <?php include('../chatbot/bpamis_case_assistant.php'); ?>
</body>
</html>