<?php
require_once __DIR__ . '/../controllers/session_control.php';
require_once __DIR__ . '/../server/server.php';
require_once __DIR__ . '/../includes/db_compat.php';

if(!isset($_SESSION['user_id'])){ header('Location: ../login.php'); exit; }
$residentId = (int)$_SESSION['user_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id<=0){ header('Location: view_complaints.php'); exit; }

$T_COMPLAINT_INFO = bpamis_table($conn, 'complaint_info');
$TB_COMPLAINT_INFO = bpamis_quote_table($T_COMPLAINT_INFO);

// Fetch complaint securely and ensure ownership
$stmt = $conn->prepare("SELECT Complaint_ID, Complaint_Title, Complaint_Details, Date_Filed, Status, Attachment_Path,incident_date, incident_time FROM {$TB_COMPLAINT_INFO} WHERE Complaint_ID = ? AND Resident_ID = ? LIMIT 1");
$stmt->bind_param('ii',$id,$residentId);
$stmt->execute();
$rows = bpamis_stmt_fetch_all_assoc($stmt);
if(empty($rows)){ $stmt->close(); header('Location: view_complaints.php?error=notfound'); exit; }
$complaint = $rows[0];
$stmt->close();

function relative_time($date){
    if(!$date) return '';
    $ts = strtotime($date); $diff = time()-$ts; if($diff<60) return 'just now';
    $units=[31536000=>'year',2592000=>'month',604800=>'week',86400=>'day',3600=>'hour',60=>'minute'];
    foreach($units as $secs=>$label){ if($diff>=$secs){ $v=floor($diff/$secs); return $v.' '.$label.($v>1?'s':'').' ago'; } }
    return 'just now';
}

$status = $complaint['Status'];
$statusNorm = strtolower(trim((string)$status));
$resolutionMap = [
    'in case' => 'This complaint is currently processed within the Barangay Justice System as part of an open case.',
    'rejected' => 'This complaint was evaluated and not admitted into the Barangay Justice System.',
    'pending' => 'This complaint is awaiting validation or further action.',
    'resolved' => 'This complaint has been successfully resolved.',
];
$resolutionNote = $resolutionMap[$statusNorm] ?? 'No additional resolution notes available.';

// Status styling
$statusStyles = [
    'resolved' => ['badge'=>'bg-green-50 text-green-700 border-green-200','chip'=>'text-green-600 bg-green-50 border-green-200'],
    'rejected' => ['badge'=>'bg-red-50 text-red-700 border-red-200','chip'=>'text-red-600 bg-red-50 border-red-200'],
    'pending' => ['badge'=>'bg-amber-50 text-amber-700 border-amber-200','chip'=>'text-amber-600 bg-amber-50 border-amber-200'],
    'in case' => ['badge'=>'bg-blue-50 text-blue-700 border-blue-200','chip'=>'text-blue-600 bg-blue-50 border-blue-200'],
];
$style = $statusStyles[$statusNorm] ?? ['badge'=>'bg-gray-50 text-gray-700 border-gray-200','chip'=>'text-gray-600 bg-gray-50 border-gray-200'];

$displayId = 'COMP-'.str_pad($complaint['Complaint_ID'],3,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Complaint Details</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'} }, animation:{'float':'float 10s ease-in-out infinite','fade-in':'fadeIn .4s ease-out'}, keyframes:{ float:{'0%,100%':{transform:'translateY(0)'},'50%':{transform:'translateY(-16px)'}}, fadeIn:{'0%':{opacity:0},'100%':{opacity:1}} } } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />
    <style>
        .glass { background: linear-gradient(135deg, rgba(255,255,255,0.85), rgba(255,255,255,0.65)); backdrop-filter: blur(12px) saturate(140%); -webkit-backdrop-filter: blur(12px) saturate(140%); }
        
        /* Mobile optimizations: compact view for small screens */
        @media (max-width: 768px) {
            /* Reduce background orbs */
            .pointer-events-none .absolute {
                width: 200px !important;
                height: 200px !important;
            }
            
            /* Main container - reduce padding */
            main {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
                padding-top: 1.5rem !important;
                padding-bottom: 3rem !important;
            }
            
            /* Section card - tighter padding */
            section.glass {
                padding: 0.75rem !important;
            }
            
            /* Header area - compact */
            header.relative {
                margin-bottom: 1rem !important;
            }
            
            header h1 {
                font-size: 0.95rem !important;
                line-height: 1.3rem !important;
            }
            
            header .text-sm, header .text-xs {
                font-size: 0.65rem !important;
            }
            
            /* Title text inside h1 */
            header h1 .bg-clip-text {
                font-size: 0.95rem !important;
            }
            
            /* Status badges - smaller */
            header .px-3.py-1 {
                font-size: 10px !important;
                padding: 0.25rem 0.5rem !important;
            }
            
            /* Icon boxes - smaller */
            header .w-20.h-20 {
                width: 3rem !important;
                height: 3rem !important;
            }
            
            header .w-20.h-20 i {
                font-size: 1.5rem !important;
            }
            
            /* Icon container next to Complaint ID */
            header .w-20.h-20.rounded-2xl {
                width: 2.5rem !important;
                height: 2.5rem !important;
                border-radius: 0.75rem !important;
            }
            
            header .w-20.h-20.rounded-2xl i {
                font-size: 1.25rem !important;
            }
            
            /* Keep icon, title, and status in same row on mobile */
            header.relative.flex {
                flex-direction: row !important;
                align-items: flex-start !important;
            }
            
            header .flex.items-center {
                flex-shrink: 0 !important;
            }
            
            header .flex-1.min-w-0 {
                min-width: 0 !important;
            }
            
            /* Global font size for content */
            main p, main span, main label, main div {
                font-size: 0.75rem !important;
            }
            
            /* Headings */
            main h2 { 
                font-size: 0.8rem !important; 
            }
            main h3 { 
                font-size: 0.75rem !important; 
            }
            
            /* Section headers */
            main h2.text-sm.font-semibold.tracking-wider {
                font-size: 0.65rem !important;
            }
            
            /* All section labels and headings */
            main h2 { 
                font-size: 0.75rem !important; 
            }
            main h3 { 
                font-size: 0.7rem !important; 
            }
            
            /* Buttons - smaller */
            main button,
            main a.inline-flex {
                font-size: 0.7rem !important;
                padding: 0.375rem 0.625rem !important;
            }
            
            /* Form inputs and textareas */
            main input, 
            main select, 
            main textarea {
                font-size: 0.75rem !important;
                padding: 0.5rem 0.65rem !important;
            }
            
            /* Content boxes - tighter padding */
            .rounded-xl.border.bg-white\/80 {
                padding: 0.65rem !important;
            }
            
            .rounded-2xl.border {
                padding: 0.75rem !important;
            }
            
            /* Grid gaps */
            .grid.gap-8 {
                gap: 1rem !important;
            }
            
            .grid.gap-4 {
                gap: 0.65rem !important;
            }
            
            .grid.sm\\:grid-cols-3,
            .grid.md\\:grid-cols-4 {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
            
            /* Space-y utilities */
            .space-y-8 > * + * {
                margin-top: 1rem !important;
            }
            
            .space-y-6 > * + * {
                margin-top: 0.75rem !important;
            }
            
            .space-y-4 > * + * {
                margin-top: 0.65rem !important;
            }
            
            /* Icons */
            main i.fa,
            main i.fas,
            main i.far {
                font-size: 0.75rem !important;
            }
            
            /* Attachment gallery items */
            #attachmentGallery .aspect-video i {
                font-size: 1.5rem !important;
            }
            
            /* Back button */
            .mb-6 a {
                font-size: 0.7rem !important;
            }
            
            .mb-6 a .w-8.h-8 {
                width: 1.75rem !important;
                height: 1.75rem !important;
            }
            
            /* Metadata sidebar - smaller text */
            aside .text-sm {
                font-size: 0.7rem !important;
            }
            
            aside ul li {
                font-size: 0.65rem !important;
            }
            
            aside .font-medium {
                font-size: 0.65rem !important;
            }
            
            /* Status card in sidebar */
            aside .text-xs {
                font-size: 0.6rem !important;
            }
            
            aside .text-base {
                font-size: 0.75rem !important;
            }
        }
        
        @media (max-width: 640px) {
            /* Extra compact for very small screens */
            main {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
                padding-top: 1.25rem !important;
            }
            
            section.glass {
                padding: 0.65rem !important;
            }
            
            header h1 {
                font-size: 1rem !important;
            }
            
            main p, main span, main label {
                font-size: 0.7rem !important;
            }
            
            main button,
            main a.inline-flex {
                font-size: 0.65rem !important;
                padding: 0.35rem 0.55rem !important;
            }
            
            .rounded-xl.border.bg-white\/80 {
                padding: 0.5rem !important;
            }
            
            .grid.gap-4 {
                gap: 0.5rem !important;
            }
            
            #attachmentGallery {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
        
        @media (max-width: 480px) {
            /* Ultra compact for smallest screens */
            header h1 {
                font-size: 0.95rem !important;
            }
            
            main p, main span {
                font-size: 0.65rem !important;
            }
            
            #attachmentGallery {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans relative overflow-x-hidden min-h-screen">
    <!-- Orbs -->
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-[480px] h-[480px] rounded-full bg-blue-200/40 blur-3xl animate-float"></div>
        <div class="absolute top-1/3 -right-52 w-[560px] h-[560px] rounded-full bg-cyan-200/40 blur-[160px] animate-[float_18s_ease-in-out_infinite]"></div>
        <div class="absolute -bottom-52 left-1/3 w-[520px] h-[520px] rounded-full bg-indigo-200/30 blur-3xl animate-[float_16s_ease-in-out_infinite]"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[900px] h-[900px] rounded-full bg-gradient-to-br from-blue-50 via-white to-cyan-50 opacity-70 blur-[200px]"></div>
    </div>

    <?php include '../includes/resident_nav.php'; ?>

    <main class="relative z-10 max-w-5xl mx-auto px-4 pt-10 pb-24 animate-fade-in">
        <!-- Back -->
        <div class="mb-6">
            <a href="view_complaints.php" class="inline-flex items-center gap-2 text-sm text-primary-700 font-medium hover:text-primary-900 transition"><span class="w-8 h-8 rounded-lg bg-white/70 backdrop-blur flex items-center justify-center shadow"><i class="fa fa-arrow-left"></i></span><span>Back to Complaints</span></a>
        </div>

        <!-- Hero / Summary -->
        <section class="relative glass rounded-2xl p-8 border border-white/60 shadow-sm overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 bg-gradient-to-br from-primary-100 to-primary-300 rounded-full opacity-40 blur-2xl"></div>
            
            <!-- Mobile Back Button (inside container) -->
            <div class="md:hidden mb-4">
                <a href="view_complaints.php" class="inline-flex items-center gap-2 text-sm text-primary-700 font-medium hover:text-primary-900 transition">
                    <span class="w-7 h-7 rounded-lg bg-white/70 backdrop-blur flex items-center justify-center shadow">
                        <i class="fa fa-arrow-left text-xs"></i>
                    </span>
                    <span class="text-xs">Back to Complaints</span>
                </a>
            </div>
            
            <header class="relative flex flex-col md:flex-row md:items-start gap-8">
                <div class="flex items-center">
                    <div class="w-20 h-20 rounded-2xl bg-white/60 backdrop-blur flex items-center justify-center ring-4 ring-primary-100 shadow-inner">
                        <i class="fa fa-file-alt text-primary-600 text-3xl"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Complaint Details</span>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full border <?= $style['badge'] ?>">
                            <?= htmlspecialchars($status) ?>
                        </span>
                    </h1>
                    <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hashtag text-primary-500"></i> <?= htmlspecialchars($displayId) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-calendar text-primary-500"></i> <?= date('F d, Y', strtotime($complaint['Date_Filed'])) ?></span>
                        <span class="inline-flex items-center gap-1"><i class="fa fa-hourglass-half text-primary-500"></i> <?= relative_time($complaint['Date_Filed']) ?></span>
                    </div>
                </div>
            </header>

            <!-- Details Grid -->
            <div class="mt-10 grid gap-8 md:grid-cols-5">
                <div class="md:col-span-3 space-y-8">
             
                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Description</h2>
                        <div class="relative rounded-xl border border-primary-100/70 bg-white/80 p-5 shadow-sm">
                            <div class="absolute -top-3 left-5 px-2 text-[10px] font-semibold tracking-wide uppercase bg-primary-100 text-primary-700 rounded-full">Details</div>
                            <p class="text-gray-700 leading-relaxed whitespace-pre-line">
                                <?= nl2br(htmlspecialchars($complaint['Complaint_Details'] ?: 'No description provided.')) ?>
                            </p>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Resolution Notes</h2>
                        <div class="rounded-xl border border-primary-100/70 bg-white/80 p-5 shadow-sm leading-relaxed text-gray-700">
                            <?= htmlspecialchars($resolutionNote) ?>
                        </div>
                    </div>
                    <?php
                        // Enhanced attachment gallery (parity with external view) with improved path handling
                        // Previous approach stripped spaces causing broken links; now we preserve spaces and URL-encode when outputting.
                        $attachmentsRaw = $complaint['Attachment_Path'] ?? '';
                        $paths = array_filter(array_map('trim', explode(';', $attachmentsRaw)));
                        $items = [];
                        foreach($paths as $p){
                            if(!$p) continue;
                            $clean = str_replace('\\','/', $p);          // normalize slashes
                            // Remove any parent traversal attempts
                            $clean = preg_replace('#\.{2,}#','', $clean);
                            $clean = ltrim($clean,'/'); // ensure relative
                            if($clean==='') continue;
                            $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
                            $type = in_array($ext,['png','jpg','jpeg','gif','webp','bmp']) ? 'image' : ($ext==='pdf' ? 'pdf' : 'file');
                            $items[] = ['path'=>$clean,'ext'=>$ext,'type'=>$type];
                        }
                        // Helper to URL encode each path segment (preserving folder structure while encoding spaces & special chars)
                        function encode_path($rel){
                            return implode('/', array_map('rawurlencode', explode('/', $rel))); 
                        }
                    ?>
                    <?php if(!empty($items)): ?>
                    <div>
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3 flex items-center gap-2"><i class="fa fa-paperclip text-primary-500"></i> Attachments</h2>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4" id="attachmentGallery">
                            <?php foreach($items as $it): ?>
                                <?php $encodedLink = encode_path($it['path']); ?>
                                <div class="group relative rounded-xl border bg-white/80 border-gray-200 hover:border-primary-300 hover:shadow-glow transition overflow-hidden">
                                    <div class="aspect-video w-full bg-gray-100 flex items-center justify-center overflow-hidden">
                                        <?php if($it['type']==='image'): ?>
                                            <img src="../<?= htmlspecialchars($encodedLink) ?>" alt="Attachment" class="w-full h-full object-cover object-center group-hover:scale-105 transition" onerror="this.onerror=null;this.src='https://via.placeholder.com/300x180?text=Missing';" />
                                        <?php elseif($it['type']==='pdf'): ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-sm font-medium">
                                                <i class="fa fa-file-pdf text-3xl mb-1"></i>
                                                PDF File
                                            </div>
                                        <?php else: ?>
                                            <div class="flex flex-col items-center justify-center text-primary-600 text-xs font-medium p-2 text-center">
                                                <i class="fa fa-paperclip text-2xl mb-1"></i>
                                                <span class="break-all leading-tight">File</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/45 transition flex items-center justify-center opacity-0 group-hover:opacity-100">
                                        <div class="flex gap-2">
                                            <?php if($it['type']==='image'): ?>
                                                <button type="button" onclick="previewImage('../<?= htmlspecialchars($encodedLink) ?>')" aria-label="View image" class="inline-flex items-center justify-center h-9 w-9 rounded-md bg-white/90 hover:bg-white text-primary-700 text-sm font-medium shadow-sm"><i class="fa fa-eye"></i></button>
                                            <?php endif; ?>
                                            <a href="../<?= htmlspecialchars($encodedLink) ?>" download aria-label="Download file" class="inline-flex items-center justify-center h-9 w-9 rounded-md bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium shadow-sm"><i class="fa fa-download"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                    </div>
                    <?php endif; ?>
                    <?php if($status === 'Pending' || $status === 'IN CASE'): ?>
                    <div class="mt-8">
                        <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3 flex items-center gap-2">
                            <i class="fa fa-upload text-primary-500"></i> Add Evidence
                        </h2>
                        <form action="upload_attachment.php" method="POST" enctype="multipart/form-data" id="evidenceForm"
                            class="space-y-4">
                            <input type="hidden" name="complaint_id" value="<?= htmlspecialchars($complaint['Complaint_ID']) ?>">
                            
                            <div class="relative">
                                <label for="evidence-attachment" class="flex flex-col justify-center items-center w-full h-40 bg-gradient-to-br from-gray-50 to-white rounded-xl border border-dashed border-gray-300 cursor-pointer hover:border-primary-300 hover:bg-primary-50/40 transition group">
                                    <div class="flex flex-col justify-center items-center pt-4 pb-5">
                                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl mb-3 group-hover:text-primary-500 transition"></i>
                                        <p class="text-sm text-gray-600 font-medium">Click to upload or drag & drop</p>
                                        <p class="text-xs text-gray-400 mt-1">PNG, JPG or PDF (max. 20MB each)</p>
                                    </div>
                                    <input id="evidence-attachment" type="file" name="attachments[]" class="hidden" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.pdf" required />
                                </label>
                            </div>
                            
                            <div id="evidenceError" class="hidden mt-2 p-3 rounded-md bg-red-50 border border-red-200 text-red-700 text-sm flex items-start gap-2">
                                <i class="fa fa-triangle-exclamation mt-0.5"></i>
                                <span id="evidenceErrorText"></span>
                            </div>
                            
                            <div id="evidencePreview" class="hidden mt-2 grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
                            
                            <button type="submit" id="evidenceSubmitBtn"
                                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium shadow-sm transition">
                                <i class="fa fa-paperclip"></i> Upload Evidence
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <aside class="md:col-span-2 space-y-6">
                    <div class="rounded-2xl border border-primary-100 bg-white/80 p-6 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-4 flex items-center gap-2"><i class="fa fa-circle-info text-primary-500"></i> Metadata</h3>
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li class="flex items-center gap-2"><i class="fa fa-hashtag text-primary-500"></i> <span class="font-medium">ID:</span> <?= htmlspecialchars($displayId) ?></li>
                            <li class="flex items-center gap-2"><i class="fa fa-calendar text-primary-500"></i> <span class="font-medium">Filed:</span> <?=
                                !empty($complaint['incident_date'])
                                    ? (date('F d, Y', strtotime($complaint['incident_date'])) . (!empty($complaint['incident_time']) ? ' at '.date('g:i A', strtotime($complaint['incident_time'])) : ''))
                                    : date('F d, Y', strtotime($complaint['Date_Filed']))
                             ?></li>
                            <li class="flex items-center gap-2"><i class="fa fa-clock-rotate-left text-primary-500"></i> <span class="font-medium">Relative:</span> <?= relative_time($complaint['incident_date']) ?></li>
                            <li class="flex items-center gap-2"><i class="fa fa-tag text-primary-500"></i> <span class="font-medium">Status:</span> <?= htmlspecialchars($status) ?></li>
                        </ul>
                    </div>
                    <div class="rounded-2xl bg-gradient-to-br from-primary-600 to-primary-500 text-white p-6 shadow-sm">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center"><i class="fa fa-file-alt text-white text-xl"></i></div>
                            <div>
                                <p class="text-xs uppercase tracking-wide text-white/70 font-semibold">Current Status</p>
                                <p class="text-base font-medium"><?= htmlspecialchars($status) ?></p>
                            </div>
                        </div>
                        <p class="text-sm text-white/90 leading-relaxed">This page provides a detailed summary of your submitted complaint. Monitor updates through your notifications or the main complaints list.</p>
                        <div class="mt-5 flex flex-col gap-3">
                            <a href="view_complaints.php" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back to List</a>
                            <?php if(strtolower($status)==='in case'): ?>
                                <a href="view_cases.php" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-sm font-medium transition"><i class="fa fa-gavel"></i> View Related Case</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </aside>
            </div>
            
        </section>
    </main>
    <?php include("../chatbot/bpamis_case_assistant.php"); ?>
    </body>
    <script>
    function previewImage(src){
        var modal=document.getElementById('imgPreviewModal');
        var img=document.getElementById('imgPreviewTag');
        if(!modal||!img) return; img.src=src; modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden');
    }
    function closePreview(){
        var modal=document.getElementById('imgPreviewModal'); if(!modal) return; modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden');
    }
    
    // Evidence file upload handler with drag & drop
    document.addEventListener('DOMContentLoaded', function() {
        const inputFile = document.getElementById('evidence-attachment');
        const label = document.querySelector('label[for="evidence-attachment"]');
        const errorBox = document.getElementById('evidenceError');
        const errorText = document.getElementById('evidenceErrorText');
        const previewGrid = document.getElementById('evidencePreview');
        
        if (!inputFile || !label) return;
        
        const MAX_BYTES = 20 * 1024 * 1024; // 20MB
        const originalLabelHTML = label.innerHTML;
        let objectUrls = [];
        
        function resetPreview() {
            objectUrls.forEach(u => URL.revokeObjectURL(u));
            objectUrls = [];
            previewGrid.innerHTML = '';
            previewGrid.classList.add('hidden');
        }
        
        inputFile.addEventListener('change', function() {
            resetPreview();
            errorBox.classList.add('hidden');
            errorText.textContent = '';
            
            if (!inputFile.files.length) {
                label.innerHTML = originalLabelHTML;
                return;
            }
            
            const files = [...inputFile.files];
            const oversized = files.filter(f => f.size > MAX_BYTES);
            
            if (oversized.length) {
                errorText.textContent = 'These files exceed 20MB and were removed: ' + oversized.map(o => o.name).join(', ');
                errorBox.classList.remove('hidden');
                const dt = new DataTransfer();
                files.filter(f => f.size <= MAX_BYTES).forEach(f => dt.items.add(f));
                inputFile.files = dt.files;
                if (!inputFile.files.length) {
                    label.innerHTML = originalLabelHTML;
                    return;
                }
            }
            
            const validFiles = [...inputFile.files];
            
            // Update label summary
            let summaryHTML = '';
            if (validFiles.length === 1) {
                summaryHTML = `<div class="flex flex-col justify-center items-center pt-4 pb-5"><i class="fas fa-file-alt text-primary-500 text-3xl mb-2"></i><p class="text-sm text-gray-700 font-medium truncate max-w-[240px]" title="${validFiles[0].name}">${validFiles[0].name}</p></div>`;
            } else {
                summaryHTML = `<div class="flex flex-col justify-center items-center pt-4 pb-5"><i class="fas fa-file-alt text-primary-500 text-3xl mb-2"></i><p class="text-sm text-gray-700 font-medium">${validFiles.length} files selected</p></div>`;
            }
            label.innerHTML = summaryHTML;
            
            // Build previews
            validFiles.forEach((file, idx) => {
                const url = URL.createObjectURL(file);
                objectUrls.push(url);
                let inner = '';
                
                if (file.type.startsWith('image/')) {
                    inner = `<img src="${url}" alt="${file.name}" class="w-full h-24 object-cover rounded-md border" />`;
                } else if (file.type === 'application/pdf') {
                    inner = `<div class="flex flex-col items-center justify-center gap-2 p-3 rounded-md border bg-white"><i class="fa fa-file-pdf text-red-500 text-2xl"></i><span class="text-[11px] text-center line-clamp-2">${file.name}</span></div>`;
                } else {
                    inner = `<div class="flex flex-col items-center justify-center gap-2 p-3 rounded-md border bg-white"><i class="fa fa-file text-gray-500 text-2xl"></i><span class="text-[11px] text-center line-clamp-2">${file.name}</span></div>`;
                }
                
                const wrap = document.createElement('div');
                wrap.className = 'relative group';
                wrap.innerHTML = inner + `
                    <div class="absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-3 rounded-md">
                        <button type="button" data-action="view" data-index="${idx}" class="p-2 rounded-full bg-white/90 text-gray-700 hover:bg-white shadow" title="View"><i class="fa fa-eye"></i></button>
                        <button type="button" data-action="remove" data-index="${idx}" class="p-2 rounded-full bg-white/90 text-red-600 hover:bg-white shadow" title="Remove"><i class="fa fa-trash"></i></button>
                    </div>`;
                previewGrid.appendChild(wrap);
            });
            
            // Add action handlers
            previewGrid.querySelectorAll('button[data-action]')?.forEach(btn => {
                btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    const action = btn.getAttribute('data-action');
                    const index = parseInt(btn.getAttribute('data-index'));
                    if (isNaN(index)) return;
                    
                    if (action === 'view') {
                        window.open(objectUrls[index], '_blank');
                    } else if (action === 'remove') {
                        const current = [...inputFile.files];
                        const dt = new DataTransfer();
                        current.forEach((f, i) => {
                            if (i !== index) dt.items.add(f);
                        });
                        inputFile.files = dt.files;
                        inputFile.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            });
            
            if (validFiles.length) {
                previewGrid.classList.remove('hidden');
            }
        });
        
        // Drag & drop handlers
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(ev => 
            label.addEventListener(ev, (e) => {
                e.preventDefault();
                e.stopPropagation();
            }, false)
        );
        
        ['dragenter', 'dragover'].forEach(ev => 
            label.addEventListener(ev, () => label.classList.add('border-primary-300', 'bg-primary-50/50'), false)
        );
        
        ['dragleave', 'drop'].forEach(ev => 
            label.addEventListener(ev, () => label.classList.remove('border-primary-300', 'bg-primary-50/50'), false)
        );
        
        label.addEventListener('drop', (e) => {
            inputFile.files = e.dataTransfer.files;
            inputFile.dispatchEvent(new Event('change', {bubbles: true}));
        });
    });
    </script>
    <div id="imgPreviewModal" class="hidden fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex items-center justify-center p-6">
        <div class="relative max-w-4xl w-full">
            <button onclick="closePreview()" class="absolute -top-4 -right-4 w-10 h-10 rounded-full bg-white text-gray-700 flex items-center justify-center shadow-lg hover:bg-primary-600 hover:text-white transition"><i class="fa fa-xmark text-lg"></i></button>
            <div class="bg-white rounded-2xl overflow-hidden shadow-glow ring-1 ring-primary-200/40">
                <img id="imgPreviewTag" src="" alt="Preview" class="w-full max-h-[80vh] object-contain bg-black" />
            </div>
        </div>
    </div>
    </html>
</html>
