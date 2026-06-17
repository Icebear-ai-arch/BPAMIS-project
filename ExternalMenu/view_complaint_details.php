<?php
include '../controllers/session_control.php';
include '../server/server.php';

// Accept external_id or user_id for external complainant session
$externalSessionId = null;
if(isset($_SESSION['external_id'])) $externalSessionId = (int)$_SESSION['external_id'];
elseif(isset($_SESSION['user_id'])) $externalSessionId = (int)$_SESSION['user_id'];
if(!$externalSessionId){ header('Location: ../login.php'); exit; }
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id<=0){ header('Location: view_complaints.php'); exit; }

// Fetch complaint securely and ensure ownership for external complainant
// Detect external_complainant_id column or fallback to Resident_ID
$useExternalCol=false;
if($colRes = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'external_complainant_id'")){
    if($colRes->num_rows>0) $useExternalCol=true; $colRes->close();
}
$idField = $useExternalCol? 'external_complainant_id':'Resident_ID';
// Try to also get Attachment_Path and case_type if present
$hasAttachment=false; $hasCaseType=false;
if($c1 = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'Attachment_Path'")){ if($c1->num_rows>0) $hasAttachment=true; $c1->close(); }
if($c2 = $conn->query("SHOW COLUMNS FROM complaint_info LIKE 'case_type'")){ if($c2->num_rows>0) $hasCaseType=true; $c2->close(); }
$selectCols = "Complaint_ID, Complaint_Title, Complaint_Details, Date_Filed, Status, incident_date, incident_time".
    ($hasAttachment? ", Attachment_Path":"").
    ($hasCaseType? ", case_type":"");
$sql = "SELECT $selectCols FROM complaint_info WHERE Complaint_ID = ? AND $idField = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii',$id,$externalSessionId);
$stmt->execute();
$res = bpamis_stmt_get_result($stmt);
if($res->num_rows===0){ $stmt->close(); header('Location: view_complaints.php?error=notfound'); exit; }
$complaint = $res->fetch_assoc();
$stmt->close();

// Helper to safely encode relative paths segment by segment
function encode_path($rel){
    $rel = str_replace('\\','/',$rel);
    $rel = preg_replace('#\.\.+#','',$rel); // strip traversal
    $rel = ltrim($rel,'/');
    $segments = array_filter(explode('/', $rel), fn($s)=>$s!=='');
    return implode('/', array_map('rawurlencode', $segments));
}

function relative_time($date){
    if(!$date) return '';
    $ts = strtotime($date); $diff = time()-$ts; if($diff<60) return 'just now';
    $units=[31536000=>'year',2592000=>'month',604800=>'week',86400=>'day',3600=>'hour',60=>'minute'];
    foreach($units as $secs=>$label){ if($diff>=$secs){ $v=floor($diff/$secs); return $v.' '.$label.($v>1?'s':'').' ago'; } }
    return 'just now';
}

$status = $complaint['Status'];
$caseTypeRaw = $hasCaseType? ($complaint['case_type'] ?? ''):'';
$formattedCaseType = $caseTypeRaw? ucwords(strtolower($caseTypeRaw)) : '';
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

    <?php include '../includes/external_nav.php'; ?>

    <main class="relative z-10 max-w-5xl mx-auto px-4 pt-10 pb-24 animate-fade-in">
        <!-- Back -->
        <div class="mb-6">
            <a href="view_complaints.php" class="inline-flex items-center gap-2 text-sm text-primary-700 font-medium hover:text-primary-900 transition"><span class="w-8 h-8 rounded-lg bg-white/70 backdrop-blur flex items-center justify-center shadow"><i class="fa fa-arrow-left"></i></span><span>Back to Complaints</span></a>
        </div>

        <!-- Hero / Summary -->
        <section class="relative glass rounded-2xl p-8 border border-white/60 shadow-sm overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 bg-gradient-to-br from-primary-100 to-primary-300 rounded-full opacity-40 blur-2xl"></div>
            <header class="relative flex flex-col md:flex-row md:items-start gap-8">
                <div class="flex items-center">
                    <div class="w-20 h-20 rounded-2xl bg-white/60 backdrop-blur flex items-center justify-center ring-4 ring-primary-100 shadow-inner">
                        <i class="fa fa-file-alt text-primary-600 text-3xl"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex flex-wrap items-center gap-3">
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Complaint Details</span>
                        <?php if($formattedCaseType): ?>
                            <?php
                                $ctLower = strtolower($formattedCaseType);
                                $ctClassMap = [
                                    'criminal' => 'bg-red-50 text-red-600 border border-red-200',
                                    'civil' => 'bg-gray-50 text-gray-600 border border-gray-200',
                                ];
                                $ctClasses = $ctClassMap[$ctLower] ?? 'bg-primary-50 text-primary-600 border border-primary-200';
                            ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold <?= $ctClasses ?>">
                                <i class="fa fa-scale-balanced text-[11px]"></i> <?= htmlspecialchars($formattedCaseType) ?>
                            </span>
                        <?php endif; ?>
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
                    <?php if($hasAttachment && !empty($complaint['Attachment_Path'])): ?>
                        <?php
                        $paths = array_filter(array_map('trim', explode(';',$complaint['Attachment_Path'])));
                        $items = [];
                        foreach($paths as $p){
                            if(!$p) continue;
                            $clean = str_replace('\\','/',$p);
                            $clean = preg_replace('#\.\.+#','',$clean);
                            $clean = ltrim($clean,'/');
                            if($clean==='') continue;
                            $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
                            $type = in_array($ext,['png','jpg','jpeg','gif','webp','bmp'])? 'image':($ext==='pdf'?'pdf':'file');
                            $items[] = ['path'=>$clean,'ext'=>$ext,'type'=>$type,'encoded'=>encode_path($clean)];
                        }
                        ?>
                        <div>
                            <h2 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-3">Attachments</h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4" id="attachmentGallery">
                                <?php foreach($items as $it): ?>
                                <div class="group relative border rounded-xl bg-white/70 backdrop-blur p-2 shadow-sm hover:shadow-md transition overflow-hidden">
                                    <?php if($it['type']==='image'): ?>
                                        <img src="../<?= htmlspecialchars($it['encoded']) ?>" alt="Attachment" class="w-full h-28 object-cover rounded-lg border cursor-pointer" onclick="previewImage('../<?= htmlspecialchars($it['encoded']) ?>')" />
                                    <?php elseif($it['type']==='pdf'): ?>
                                        <div class="w-full h-28 flex flex-col items-center justify-center gap-2 rounded-lg border bg-white/80">
                                            <i class="fa fa-file-pdf text-red-500 text-3xl"></i>
                                            <span class="text-[11px] text-gray-600 truncate px-2">PDF File</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-28 flex flex-col items-center justify-center gap-2 rounded-lg border bg-white/80">
                                            <i class="fa fa-file text-gray-400 text-3xl"></i>
                                            <span class="text-[11px] text-gray-600 truncate px-2">File</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2 rounded-lg">
                                        <?php if($it['type']==='image'): ?>
                                            <button type="button" aria-label="Preview image" class="h-9 w-9 flex items-center justify-center rounded-full bg-white/90 text-gray-700 hover:bg-white shadow" onclick="previewImage('../<?= htmlspecialchars($it['encoded']) ?>')"><i class="fa fa-eye"></i></button>
                                        <?php else: ?>
                                            <a aria-label="View file" href="../<?= htmlspecialchars($it['encoded']) ?>" target="_blank" class="h-9 w-9 flex items-center justify-center rounded-full bg-white/90 text-gray-700 hover:bg-white shadow"><i class="fa fa-eye"></i></a>
                                        <?php endif; ?>
                                        <a aria-label="Download attachment" href="../<?= htmlspecialchars($it['encoded']) ?>" download class="h-9 w-9 flex items-center justify-center rounded-full bg-white/90 text-primary-600 hover:bg-white shadow"><i class="fa fa-download"></i></a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <aside class="md:col-span-2 space-y-6">
                    <div class="rounded-2xl border border-primary-100 bg-white/80 p-6 shadow-sm">
                        <h3 class="text-sm font-semibold tracking-wider text-gray-500 uppercase mb-4 flex items-center gap-2"><i class="fa fa-circle-info text-primary-500"></i> Metadata</h3>
                        <ul class="text-sm text-gray-600 space-y-2">
                            <li class="flex items-center gap-2"><i class="fa fa-hashtag text-primary-500"></i> <span class="font-medium">ID:</span> <?= htmlspecialchars($displayId) ?></li>
                            <li class="flex items-center gap-2"><i class="fa fa-calendar text-primary-500"></i> <span class="font-medium">Incident Date:</span> <?=
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
            <div class="mt-10 pt-6 border-t border-dashed border-primary-200 flex items-center justify-between flex-wrap gap-4">
                <div class="text-xs text-gray-500">Generated: <?= date('F d, Y h:i A') ?></div>
                <div class="flex gap-2">
                    <a href="view_complaints.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-primary-700 border border-primary-200 shadow-sm text-sm font-medium transition"><i class="fa fa-arrow-left"></i> Back</a>
                </div>
            </div>
        </section>
    </main>
    <?php include("../chatbot/bpamis_case_assistant.php"); ?>
    <!-- Lightbox Modal -->
    <div id="imgLightbox" class="fixed inset-0 bg-black/70 backdrop-blur-sm hidden items-center justify-center z-50 p-6">
        <div class="relative max-w-5xl w-full">
            <button type="button" onclick="closePreview()" class="absolute -top-10 right-0 text-white bg-white/10 hover:bg-white/20 rounded-full w-10 h-10 flex items-center justify-center" aria-label="Close preview"><i class="fa fa-times text-lg"></i></button>
            <img id="lightboxImg" src="" alt="Preview" class="w-full max-h-[78vh] object-contain rounded-xl shadow-2xl ring-1 ring-white/20" />
        </div>
    </div>
    <script>
        function previewImage(src){
            const lb=document.getElementById('imgLightbox');
            const img=document.getElementById('lightboxImg');
            img.src=src;
            lb.classList.remove('hidden');
            lb.classList.add('flex');
        }
        function closePreview(){
            const lb=document.getElementById('imgLightbox');
            const img=document.getElementById('lightboxImg');
            img.src='';
            lb.classList.add('hidden');
            lb.classList.remove('flex');
        }
        document.addEventListener('keydown',e=>{ if(e.key==='Escape'){ closePreview(); }});
        document.getElementById('imgLightbox').addEventListener('click',e=>{ if(e.target.id==='imgLightbox'){ closePreview(); }});
    </script>
</body>
</html>
