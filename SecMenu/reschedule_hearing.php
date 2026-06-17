
<?php
// Serve pure JSON for AJAX requests before any HTML or session output
if (isset($_GET['ajax']) && isset($_GET['id'])) {
  require_once './schedule/db-connect.php';
  header('Content-Type: application/json; charset=utf-8');
  $id = intval($_GET['id']);
  // Use actual column names present in the table: 'participant' (singular) and 'Case_ID'.
  // Alias them to the keys the client expects ('participants' and 'case_id').
  $stmt = $conn->prepare("SELECT hearingID, hearingTitle, hearingDateTime, place, participant AS participants, remarks, Case_ID AS case_id FROM schedule_list WHERE hearingID = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $result = bpamis_stmt_get_result($stmt);
  $data = $result->fetch_assoc();
  // Normalize keys so client-side JS always finds lowercase 'case_id' and 'participants'
  if ($data) {
    $data['case_id'] = $data['case_id'] ?? ($data['Case_ID'] ?? null);
    $data['participants'] = $data['participants'] ?? ($data['participant'] ?? null);
  }
  echo json_encode($data);
  exit;
}

include '../controllers/session_control.php';
include './schedule/db-connect.php';

// Fetch selected schedule if editing directly via URL (?id=...)
$editData = null;
if (isset($_GET['id'])) {
    $hearing_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM schedule_list WHERE hearingID = ?");
    $stmt->bind_param("i", $hearing_id);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    if ($result && $result->num_rows > 0) {
        $editData = $result->fetch_assoc();
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $editId = isset($_POST['hearing_id']) ? intval($_POST['hearing_id']) : 0;
    $hearingTitle = $_POST['hearing_title'] ?? '';
    $hearingDate = $_POST['hearing_date'] ?? '';
    $hearingTime = $_POST['hearing_time'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $remarks = trim($_POST['hearing_remarks'] ?? '') ?: 'N/A';
    $hearingDateTime = $hearingDate . ' ' . $hearingTime . ':00';

    if ($editId > 0) {
        $stmt = $conn->prepare("UPDATE schedule_list SET hearingTitle=?, hearingDateTime=?, place=?, remarks=? WHERE hearingID=?");
        $stmt->bind_param("ssssi", $hearingTitle, $hearingDateTime, $venue, $remarks, $editId);
        $stmt->execute();
        $notice = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                     <strong>Success!</strong> Hearing has been rescheduled.
                   </div>';
        
        // Fetch case_id and complaint_id for notifications
        $get_case = $conn->prepare("
            SELECT sl.case_id, ci.complaint_id 
            FROM schedule_list sl
            JOIN case_info ci ON sl.case_id = ci.case_id
            WHERE sl.hearingID = ?
        ");
        $get_case->bind_param("i", $editId);
        $get_case->execute();
        $case_result = bpamis_stmt_get_result($get_case);

        if ($case_result->num_rows > 0) {
            $row = $case_result->fetch_assoc();
            $complaint_id = $row['complaint_id'];

            $msg = "Your hearing (ID: $editId) has been rescheduled to $hearingDate at $hearingTime.";

            // Notify resident
            $get_resident = $conn->prepare("SELECT resident_id FROM complaint_info WHERE complaint_id = ?");
            $get_resident->bind_param("i", $complaint_id);
            $get_resident->execute();
            $res_result = bpamis_stmt_get_result($get_resident);

            if ($res_result->num_rows > 0) {
                $res = $res_result->fetch_assoc();
                $resident_id = $res['resident_id'];

                $stmt_notif = $conn->prepare("
                    INSERT INTO notifications (resident_id, type, title, message, created_at, is_read)
                    VALUES (?, 'Hearing', 'Hearing Rescheduled', ?, NOW(), 0)
                ");
                $stmt_notif->bind_param("is", $resident_id, $msg);
                $stmt_notif->execute();
            }

            // Notify external complainant
            $get_external = $conn->prepare("SELECT external_complainant_id FROM complaint_info WHERE complaint_id = ?");
            $get_external->bind_param("i", $complaint_id);
            $get_external->execute();
            $ext_result = bpamis_stmt_get_result($get_external);

            if ($ext_result->num_rows > 0) {
                $ext = $ext_result->fetch_assoc();
                $external_id = $ext['external_complainant_id'];

                $stmt_ext_notif = $conn->prepare("
                    INSERT INTO notifications (external_complaint_id, type, title, message, created_at, is_read)
                    VALUES (?, 'Hearing', 'Hearing Rescheduled', ?, NOW(), 0)
                ");
                $stmt_ext_notif->bind_param("is", $external_id, $msg);
                $stmt_ext_notif->execute();
            }
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reschedule Hearing</title>
  <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,0.10), 0 4px 18px -2px rgba(6,90,143,0.20)'}, keyframes:{fadeIn:{'0%':{opacity:0,transform:'translateY(4px)'},'100%':{opacity:1,transform:'translateY(0)'}},pulseSoft:{'0%,100%':{opacity:1},'50%':{opacity:.55}}}, animation:{'fade-in':'fadeIn .5s ease-out','pulse-soft':'pulseSoft 3s ease-in-out infinite'} } } };
  </script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    /* Premium UI borrowed from Add Complaint */
    .bg-orbs:before, .bg-orbs:after { content:""; position:absolute; border-radius:9999px; filter:blur(70px); opacity:.35; }
    .bg-orbs:before { width:480px; height:480px; background:linear-gradient(135deg,#7cccfd,#0c9ced); top:-160px; left:-140px; }
    .bg-orbs:after { width:420px; height:420px; background:linear-gradient(135deg,#bae2fd,#7cccfd); bottom:-140px; right:-120px; }
    .glass { background:linear-gradient(145deg,rgba(255,255,255,.88),rgba(255,255,255,.65)); backdrop-filter:blur(14px) saturate(140%); -webkit-backdrop-filter:blur(14px) saturate(140%); }
    .input-base { width:100%; border-radius:0.5rem; border:1px solid rgba(209,213,219,.7); background:rgba(255,255,255,.7); padding:.625rem .75rem; font-size:.875rem; transition:.2s; }
    .input-base:not(textarea){ height:44px; line-height:1.2; }
    .input-base:focus { outline:none; background:#fff; border-color:#36b3f9; box-shadow:0 0 0 4px rgba(12,156,237,.25); }
    .field-label { font-size:11px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; margin-bottom:4px; display:flex; gap:4px; align-items:center; color:#4b5563; }
    /* Readonly-like select visual for locked dropdown */
    select.readonly-style { background-color:#e0effe !important; color:#0c9ced !important; cursor:not-allowed; border-color:#0281d4 !important; box-shadow:none !important; opacity:1 !important; }
    /* Readonly styling for inputs */
    .select-readonly { background-color:#e0effe !important; color:#0c9ced !important; cursor:not-allowed !important; border-color:#0281d4 !important; }
    
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
      
      /* Main container - compact */
      .container.mx-auto {
        margin-top: 1rem !important;
        margin-bottom: 1rem !important;
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
      }
      
      section.max-w-6xl {
        margin-top: 1rem !important;
      }
      
      section .glass {
        padding: 0.75rem !important;
      }
      
      /* Form spacing */
      form.space-y-4 > * + * {
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
      
      textarea.resize-y {
        resize: vertical !important;
      }
      
      /* Remark suggestions */
      #remark-suggestions {
        gap: 0.375rem !important;
      }
      
      #remark-suggestions button {
        font-size: 9px !important;
        padding: 0.25rem 0.5rem !important;
      }
      
      .text-\[11px\] {
        font-size: 8px !important;
      }
      
      .mt-2 {
        margin-top: 0.5rem !important;
      }
      
      /* Grid gaps */
      .grid.gap-6 {
        gap: 1rem !important;
      }
      
      /* Space between form sections */
      .space-y-4 {
        gap: 1rem !important;
      }
      
      .space-y-4 > * + * {
        margin-top: 1rem !important;
      }
      
      .mb-4 {
        margin-bottom: 1rem !important;
      }
      
      /* Buttons */
      button, a.inline-flex {
        font-size: 0.7rem !important;
        padding: 0.5rem 0.75rem !important;
      }
      
      button i, a.inline-flex i {
        font-size: 0.7rem !important;
      }
      
      /* Alert messages */
      .bg-green-100, .bg-red-100 {
        font-size: 0.7rem !important;
        padding: 0.5rem 0.75rem !important;
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
      
      /* Hide desktop sidebar container on mobile, sidebar toggle will handle it */
      body > .flex > .w-64 {
        position: fixed !important;
        z-index: 9999 !important;
      }
      
      /* Don't affect navbar/header flex layouts */
      nav.flex,
      header .flex {
        display: flex !important;
      }
      
      /* Main content flex to block */
      body > .flex:not(nav):not(header *) {
        display: block !important;
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
      
      /* Bottom margin for buttons */
      .mt-6 {
        margin-top: 1rem !important;
      }
    }
  </style>

</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">

<!-- Top Navigation -->
<?php include '../includes/barangay_official_sec_nav.php'; ?>

  <!-- Page Header -->
  <header class="relative max-w-screen-2xl mx-auto px-4 md:px-8 pt-8 animate-fade-in">
    <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
      <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
      <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
          <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-calendar-pen text-lg"></i></span>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Reschedule Hearing</span>
          </h1>
          <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Update the date, time, and remarks of an existing hearing schedule.</p>
        </div>
      </div>
    </div>
  </header>

<div class="flex">
  <!-- Sidebar -->
  <div class="">
    <?php include 'sidebar_.php'; ?>
  </div>

  <!-- Main Content -->
  <div class="container mx-auto mt-8 mb-4 px-4">
    <section class="relative z-10 max-w-6xl mx-auto w-full mt-6">
      <div class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/50 p-6 md:p-8">
    
      <?php
        include '../server/server.php';
        ?>

      <?= $notice ?? '' ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Column -->
                <div class="space-y-4">
                    <div>
            <label for="schedule-dropdown" class="field-label"><i class="fa fa-briefcase"></i> Select Hearing to Reschedule</label>
            <select id="schedule-dropdown" name="case_id" class="input-base" required>
                            <option value="">-- Select a Hearing --</option>
            <?php
            $sql = "SELECT hearingID, hearingTitle FROM schedule_list ORDER BY hearingDateTime DESC";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['hearingID']}'>{$row['hearingID']} - " . htmlspecialchars($row['hearingTitle']) . "</option>";
            }
            ?>
            </select>
        </div>

      <input type="hidden" id="hearing_id" name="hearing_id" value="<?= $editData['hearingID'] ?? '' ?>">
      
        <div>
          <label class="field-label"><i class="fa fa-flag"></i> Status / Phase</label>
          <input type="text" id="case-phase" class="input-base select-readonly" readonly />
        </div>

        <div>
          <label for="complainant_name" class="field-label"><i class="fa fa-user"></i> Complainant</label>
          <input type="text" id="complainant_name" name="complainant_name" class="input-base bg-white/80" readonly />
        </div>

        <div>
          <label for="respondent_name" class="field-label"><i class="fa fa-users"></i> Respondent(s)</label>
          <input type="text" id="respondent_name" name="respondent_name" class="input-base bg-white/80" readonly />
        </div>
        
        <div>
          <label for="hearing-title" class="field-label"><i class="fa fa-heading"></i> Title</label>
          <input type="text" id="hearing-title" name="hearing_title" value="<?= $editData['hearingTitle'] ?? '' ?>" class="input-base select-readonly" readonly required>
        </div>
        </div>

        <div class="space-y-4">
          <div>
          <label for="hearing-date" class="field-label"><i class="fa fa-calendar-day"></i> Date <span class="text-red-600">*</span></label>
          <input type="date" id="hearing-date" name="hearing_date" value="<?= isset($editData['hearingDateTime']) ? date('Y-m-d', strtotime($editData['hearingDateTime'])) : '' ?>" class="input-base" required>
          </div>

          <div>
          <label for="hearing-time" class="field-label"><i class="fa fa-clock"></i> Time <span class="text-red-600">*</span></label>
          <input type="time" id="hearing-time" name="hearing_time" value="<?= isset($editData['hearingDateTime']) ? date('H:i', strtotime($editData['hearingDateTime'])) : '' ?>" class="input-base" required>
          </div>

          <div>
          <label for="venue" class="field-label"><i class="fa fa-location-dot"></i> Venue <span class="text-red-600">*</span></label>
          <input type="text" id="venue" name="venue" value="<?= $editData['place'] ?? '' ?>" class="input-base" required>
          </div>

          <div>
          <label for="hearing-remarks" class="field-label"><i class="fa fa-comment-dots"></i> Remarks</label>
          <textarea id="hearing-remarks" name="hearing_remarks" class="input-base resize-y" rows="4"><?= $editData['remarks'] ?? '' ?></textarea>
          <div class="mt-2">
            <div class="field-label"><i class="fa fa-lightbulb"></i> Suggested remarks</div>
            <div id="remark-suggestions" class="flex flex-wrap gap-2"></div>
            <div class="text-[11px] text-gray-500 mt-1">Click a suggestion to fill in the remarks. Text includes complainant, all respondents, and attendance requirement.</div>
          </div>
          </div>
          </div>
          </div>
          <div class="flex flex-col sm:flex-row justify-between items-center gap-3 mt-6">
            <a href="home-secretary.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
              <i class="fa fa-calendar-check"></i> Update Hearing
            </button>
          </div>
      </form>
      </div>
    </section>
  </div>


<script>
 document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const hearingIdFromUrl = urlParams.get('id');

    const dropdown = document.getElementById('schedule-dropdown');

    if (hearingIdFromUrl && dropdown) {
        // Check if option exists in dropdown
        const optionExists = Array.from(dropdown.options).some(opt => opt.value === hearingIdFromUrl);
        if (optionExists) {
      dropdown.value = hearingIdFromUrl;

      // Trigger change first so the handler runs even if we later disable the control
      if (window.jQuery) {
        $('#schedule-dropdown').trigger('change');
      } else {
        dropdown.dispatchEvent(new Event('change'));
      }

      // Then visually lock the control to readonly
      dropdown.classList.add('readonly-style');
      dropdown.setAttribute('disabled', 'disabled');
        } else {
            console.warn(`Hearing ID ${hearingIdFromUrl} not found in dropdown options.`);
        }
    }
});

// When user selects a hearing from dropdown, fetch its details
$('#schedule-dropdown').on('change', function() {
    const hearingId = $(this).val();
    if (!hearingId) {
        // Clear all fields
        $('#hearing_id').val('');
        $('#hearing-title').val('');
        $('#hearing-date').val('');
        $('#hearing-time').val('');
        $('#venue').val('');
        $('#hearing-remarks').val('');
        $('#case-phase').val('');
        $('#complainant_name').val('');
        $('#respondent_name').val('');
        $('#remark-suggestions').empty();
        return;
    }

    // Fetch hearing details via AJAX
    fetch(`<?= $_SERVER['PHP_SELF'] ?>?ajax=1&id=${hearingId}`)
        .then(res => res.json())
        .then(data => {
            console.log('Hearing Data:', data); // Debug log
            
            // Populate form fields with hearing data
            $('#hearing_id').val(data.hearingID || '');
            
            // Populate scheduled date and time from database
            if (data.hearingDateTime) {
                const [date, time] = data.hearingDateTime.split(' ');
                $('#hearing-date').val(date || '');
                $('#hearing-time').val(time ? time.slice(0, 5) : '');
            } else {
                $('#hearing-date').val('');
                $('#hearing-time').val('');
            }

            // Populate venue from database
            $('#venue').val(data.place || '');
            
            // Populate existing remarks from database
            $('#hearing-remarks').val(data.remarks || '');

            // Now fetch case details for this hearing
            const caseId = data.case_id;
            if (caseId) {
                $.ajax({
                    url: 'schedule/get_case_participants.php',
                    type: 'GET',
                    data: { Case_ID: caseId },
                    success: function(response) {
                        try {
                            const caseData = JSON.parse(response);
                            console.log('Case Data:', caseData); // Debug log

                            // Populate complainant
                            const complainant = caseData.complainant || 'Not found';
                            $('#complainant_name').val(complainant);

                            // Populate respondents - display all names
                            let respondentsDisplay = 'Not found';
                            let respondentsAll = [];
                            if (caseData.respondents && caseData.respondents.length > 0) {
                                respondentsAll = caseData.respondents;
                                respondentsDisplay = caseData.respondents.join(', ');
                            }
                            $('#respondent_name').val(respondentsDisplay);

                            // Show case phase/status
                            const phase = (caseData.phase || caseData.case_status || '').toString();
                            $('#case-phase').val(phase);

                            // Build title: "CaseStatus: Complainant Vs. Respondent(s)"
                            // If multiple respondents, use first respondent + "et. al"
                            let titleRespondent = '';
                            if (caseData.respondents && caseData.respondents.length > 0) {
                                if (caseData.respondents.length > 1) {
                                    titleRespondent = caseData.respondents[0] + ' et. al';
                                } else {
                                    titleRespondent = caseData.respondents[0];
                                }
                            } else {
                                titleRespondent = 'Respondent';
                            }

                            let autoTitle = '';
                            if (phase && complainant && titleRespondent) {
                                autoTitle = `${phase}: ${complainant} Vs. ${titleRespondent}`;
                            } else if (complainant && titleRespondent) {
                                autoTitle = `${complainant} Vs. ${titleRespondent}`;
                            }
                            $('#hearing-title').val(autoTitle);

                            // Build suggested remarks (using current date/time/venue from form)
                            buildRemarkSuggestions({
                                phase: phase,
                                complainant: complainant,
                                respondentsAll: respondentsAll
                            });

                        } catch (err) {
                            console.error('Invalid JSON from get_case_participants:', err);
                            $('#complainant_name').val('');
                            $('#respondent_name').val('');
                            $('#case-phase').val('');
                            $('#hearing-title').val('');
                            $('#remark-suggestions').empty();
                        }
                    },
                    error: function() {
                        $('#complainant_name').val('');
                        $('#respondent_name').val('');
                        $('#case-phase').val('');
                        $('#hearing-title').val('');
                        $('#remark-suggestions').empty();
                        console.error('AJAX failed to fetch case participants');
                    }
                });
            } else {
                console.warn('No case_id found for this hearing');
            }
        })
        .catch(err => {
            console.error('Error fetching hearing details:', err);
        });
});

// Utilities to build and render suggested remarks
function buildRemarkSuggestions(ctx) {
    const container = document.getElementById('remark-suggestions');
    if (!container) return;
    container.innerHTML = '';

    const phase = (ctx.phase || '').toString();
    const comp = (ctx.complainant || '').toString();
    const resps = Array.isArray(ctx.respondentsAll) ? ctx.respondentsAll.filter(Boolean) : [];
    const allRespondentsList = resps.length ? resps.join(', ') : 'Respondent(s)';

    const suggestions = [];

    // Base suggestion tailored to phase
    if (phase) {
        suggestions.push(`This rescheduled hearing is in the ${phase} phase for the case of ${comp} versus ${allRespondentsList}. All parties are required to attend.`);
    }
    // Attendance reminder including schedule placeholders (will be replaced on click)
    suggestions.push(`You are hereby required to attend the rescheduled hearing for the case of ${comp} versus ${allRespondentsList}. Please be punctual and bring necessary documents.`);
    // Neutral scheduling note
    suggestions.push(`This notice serves to inform ${allRespondentsList} that the hearing has been rescheduled for the case filed by ${comp}. Presence is mandatory unless otherwise excused.`);
    // Rescheduling specific
    suggestions.push(`Please take note that the hearing for ${comp} versus ${allRespondentsList} has been rescheduled. Your attendance is required on the new date and time.`);

    // Render as clickable chips
    suggestions.forEach(text => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'px-3 py-1.5 rounded-md border border-primary-200 bg-white/80 hover:bg-white text-[12px] text-gray-700 shadow-sm transition';
        btn.textContent = text.length > 140 ? text.slice(0, 137) + '…' : text;
        btn.title = text;
        btn.addEventListener('click', () => {
            const date = (document.getElementById('hearing-date')?.value || '').toString();
            const time = (document.getElementById('hearing-time')?.value || '').toString();
            const venue = (document.getElementById('venue')?.value || '').toString();

            const whenPart = (date && time) ? ` on ${date} at ${time}` : (date ? ` on ${date}` : '');
            const venuePart = venue ? ` at ${venue}` : '';

            const full = `${text}${whenPart}${venuePart}`.trim();
            const area = document.getElementById('hearing-remarks');
            if (area) area.value = full;
        });
        container.appendChild(btn);
    });
}

</script>
<?php include '../chatbot/bpamis_case_assistant.php'?>
</body>
</html>