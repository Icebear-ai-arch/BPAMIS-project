
<?php
include '../controllers/session_control.php';
include_once __DIR__ . '/../server/server.php';

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = $_POST['blotter_description'];
    $reported_by = $_POST['reported_by'];
    $date_reported = $_POST['date_reported'];

    $stmt = $conn->prepare("INSERT INTO BLOTTER_INFO (Blotter_Description, Reported_By, Date_Reported) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $description, $reported_by, $date_reported);

    if ($stmt->execute()) {
        $success = true;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Blotter Report</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,0.10), 0 4px 18px -2px rgba(6,90,143,0.20)'}, keyframes:{fadeIn:{'0%':{opacity:0,transform:'translateY(4px)'},'100%':{opacity:1,transform:'translateY(0)'}},pulseSoft:{'0%,100%':{opacity:1},'50%':{opacity:.55}}}, animation:{'fade-in':'fadeIn .5s ease-out','pulse-soft':'pulseSoft 3s ease-in-out infinite'} } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        .bg-orbs:before, .bg-orbs:after { content:""; position:absolute; border-radius:9999px; filter:blur(70px); opacity:.35; }
        .bg-orbs:before { width:480px; height:480px; background:linear-gradient(135deg,#7cccfd,#0c9ced); top:-160px; left:-140px; }
        .bg-orbs:after { width:420px; height:420px; background:linear-gradient(135deg,#bae2fd,#7cccfd); bottom:-140px; right:-120px; }
        .glass { background:linear-gradient(145deg,rgba(255,255,255,.88),rgba(255,255,255,.65)); backdrop-filter:blur(14px) saturate(140%); -webkit-backdrop-filter:blur(14px) saturate(140%); }
        .input-base { width:100%; border-radius:0.5rem; border:1px solid rgba(209,213,219,.7); background:rgba(255,255,255,.7); padding:.625rem .75rem; font-size:.875rem; transition:.2s; }
        .input-base:not(textarea){ height:44px; line-height:1.2; }
        .input-base:focus { outline:none; background:#fff; border-color:#36b3f9; box-shadow:0 0 0 4px rgba(12,156,237,.25); }
        .field-label { font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; display:flex; gap:6px; align-items:center; color:#4b5563; }
        
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
            
            /* Reduce background orbs */
            .bg-orbs:before {
                width: 280px !important;
                height: 280px !important;
                filter: blur(48px) !important;
                top: -80px !important;
                left: -70px !important;
            }
            
            .bg-orbs:after {
                width: 240px !important;
                height: 240px !important;
                filter: blur(48px) !important;
                bottom: -60px !important;
                right: -60px !important;
            }
            
            /* Reduce body min-height */
            body.min-h-screen {
                min-height: auto !important;
            }
            
            /* Header - compact */
            header {
                padding-top: 1rem !important;
            }
            
            header .glass {
                padding: 0.75rem !important;
            }
            
            header h1 {
                font-size: 1.125rem !important;
            }
            
            header h1 .w-12 {
                width: 2.25rem !important;
                height: 2.25rem !important;
                font-size: 0.875rem !important;
            }
            
            header p {
                font-size: 0.7rem !important;
                margin-top: 0.5rem !important;
            }
            
            header .text-xs {
                font-size: 9px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            header .flex.items-center.gap-3 {
                gap: 0.375rem !important;
            }
            
            /* Main content - compact */
            main {
                margin-top: 1rem !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                padding-bottom: 1rem !important;
            }
            
            main section.glass {
                padding: 0.75rem !important;
            }
            
            /* Section spacing */
            main .mb-8 {
                margin-bottom: 1rem !important;
            }
            
            main h2 {
                font-size: 0.875rem !important;
            }
            
            main h2 i {
                font-size: 0.7rem !important;
            }
            
            main .text-sm {
                font-size: 0.7rem !important;
            }
            
            /* Form spacing */
            #blotterForm {
                gap: 1rem !important;
            }
            
            #blotterForm .space-y-8 > * + * {
                margin-top: 1rem !important;
            }
            
            /* Field labels */
            .field-label {
                font-size: 9px !important;
                margin-bottom: 0.25rem !important;
                gap: 0.25rem !important;
            }
            
            .field-label i {
                font-size: 9px !important;
            }
            
            /* Input fields */
            .input-base {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.625rem !important;
                height: 38px !important;
            }
            
            .input-base:not(textarea) {
                height: 38px !important;
            }
            
            /* Textarea */
            textarea.input-base {
                min-height: 80px !important;
                padding: 0.5rem 0.625rem !important;
            }
            
            /* Grid gaps */
            .grid.gap-6 {
                gap: 1rem !important;
            }
            
            /* Buttons */
            button {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            button i {
                font-size: 0.7rem !important;
            }
            
            /* Success message */
            .mb-6.rounded-lg {
                font-size: 0.7rem !important;
                padding: 0.5rem 0.75rem !important;
            }
            
            /* Border top spacing */
            .pt-4 {
                padding-top: 0.75rem !important;
            }
            
            /* Icon sizes */
            .fa, .fas, .far {
                font-size: 0.7rem !important;
            }
            
            header .fa {
                font-size: 0.65rem !important;
            }
            
            /* Decorative glass elements */
            .glass .absolute {
                display: none !important;
            }
            
            /* Reduce border radius for compact feel */
            .rounded-2xl {
                border-radius: 0.75rem !important;
            }
            
            .rounded-xl {
                border-radius: 0.5rem !important;
            }
            
            .rounded-lg {
                border-radius: 0.375rem !important;
            }
        }
    </style>
</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
    <?php include '../includes/barangay_official_sec_nav.php'; ?>

    <!-- Page Heading (Premium glass hero) -->
    <header class="relative max-w-screen-2xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
        <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-clipboard-list text-lg"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Add Blotter Report</span>
                    </h1>
                    <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">File a new blotter report for barangay records.</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-file-circle-plus text-primary-500"></i> New Entry</div>
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-database text-primary-500"></i> Saved to DB</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Form Card -->
    <main class="relative z-10 max-w-6xl mx-auto px-4 md:px-8 mt-10 pb-2 md:pb-24">
        <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 p-6 md:p-10 animate-fade-in">
            <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
                <h2 class="text-lg md:text-xl font-semibold text-gray-800 flex items-center gap-2"><i class="fa fa-circle-plus text-primary-500"></i> Blotter Details</h2>
                <a href="view_blotter.php" class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-700 font-medium"><i class="fa fa-arrow-left"></i> Back to Blotter List</a>
            </div>

            <?php if ($success): ?>
                <div class="mb-6 rounded-lg border border-green-300 bg-green-50 text-green-700 px-4 py-3 text-sm flex items-start gap-2"><i class="fa fa-check-circle mt-0.5"></i><span>Blotter report has been successfully recorded.</span></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="space-y-8" id="blotterForm">
                <div>
                    <label for="blotter_description" class="field-label"><i class="fa fa-align-left"></i> Blotter Description</label>
                    <textarea name="blotter_description" id="blotter_description" rows="5" required class="input-base resize-y" placeholder="Provide a clear description of the incident..."></textarea>
                </div>
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label for="reported_by" class="field-label"><i class="fa fa-user"></i> Reported By</label>
                        <input type="text" name="reported_by" id="reported_by" required class="input-base" placeholder="Full name" />
                    </div>
                    <div>
                        <label for="date_reported" class="field-label"><i class="fa fa-calendar-day"></i> Date Reported</label>
                        <input type="date" name="date_reported" id="date_reported" required class="input-base" />
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-dashed border-primary-200/60">
                    <button type="button" id="cancelBtn" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
                        <i class="fa fa-paper-plane"></i> Submit Blotter Report
                    </button>
                </div>
            </form>
        </section>
    </main>

    <script>
        // Prevent future dates for Date Reported and support Cancel reset
        (function(){
            const dateInput = document.getElementById('date_reported');
            if (dateInput) {
                const today = new Date();
                const yyyy = today.getFullYear();
                const mm = String(today.getMonth()+1).padStart(2,'0');
                const dd = String(today.getDate()).padStart(2,'0');
                const max = `${yyyy}-${mm}-${dd}`;
                dateInput.setAttribute('max', max);
            }
            const form = document.getElementById('blotterForm');
            const cancelBtn = document.getElementById('cancelBtn');
            if (cancelBtn && form) {
                cancelBtn.addEventListener('click', () => {
                    form.reset();
                });
            }
        })();
    </script>

    <?php include 'sidebar_.php';?>
</body>
</html>