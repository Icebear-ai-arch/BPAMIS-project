<?php
/**
 * Appoint Hearing Page
 * Barangay Panducot Adjudication Management Information System
 */
include '../controllers/session_control.php';
include '../SecMenu/schedule/db-connect.php';

// Handle success message from session
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selectedCase = (int) ($_POST['case_id'] ?? 0);
    $hearingTitle = $_POST['hearing_title'] ?? '';
    $hearingDate = $_POST['hearing_date'] ?? '';
    $hearingTime = $_POST['hearing_time'] ?? '';
    $venue = $_POST['venue'] ?? '';
    $participants = $_POST['participants'];
    $remarks = trim($_POST['hearing_remarks'] ?? '');

    if ($remarks === '') {
        $remarks = 'N/A';
    }

    $hearingDateTime = $hearingDate . ' ' . $hearingTime . ':00';

    $stmt = $conn->prepare("INSERT INTO schedule_list (Case_ID, hearingTitle, hearingDateTime, place, participant, remarks) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $selectedCase, $hearingTitle, $hearingDateTime, $venue, $participants, $remarks);

    if ($stmt->execute()) {
        // Get complainant & respondent IDs
        $residentQuery = "
            SELECT 
                co.Resident_ID, 
                cr.respondent_id AS Respondent_ID
            FROM case_info ci
            JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID
            JOIN complaint_respondents cr ON ci.Complaint_ID = cr.Complaint_ID
            WHERE ci.Case_ID = $selectedCase
            LIMIT 1
        ";
        $residentResult = $conn->query($residentQuery);

        if ($residentResult && $residentResult->num_rows > 0) {
            $resData = $residentResult->fetch_assoc();
            $resident_id = $resData['Resident_ID'];
            $respondent_id = $resData['Respondent_ID'];

            $notif_title = "Hearing Scheduled";
            $notif_message = "Your case (ID: $selectedCase) has been scheduled for a hearing on $hearingDate at $hearingTime.";
            $created_at = date('Y-m-d H:i:s');
            $type = 'Hearing';

            if (!empty($resident_id)) {
                $conn->query("
                    INSERT INTO notifications (resident_id, title, message, is_read, created_at, type)
                    VALUES ($resident_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }

            if (!empty($respondent_id)) {
                $conn->query("
                    INSERT INTO notifications (resident_id, title, message, is_read, created_at, type)
                    VALUES ($respondent_id, '$notif_title', '$notif_message', 0, '$created_at', '$type')
                ");
            }
        }

        // Store success message in session and redirect
        $_SESSION['success_message'] = "Hearing has been scheduled.";
        header("Location: appoint_hearing.php");
        exit();
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Appoint Hearing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script>
        tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.10),0 4px 18px -2px rgba(6,90,143,.18)'} } } };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .bg-orbs:before,.bg-orbs:after{content:"";position:absolute;border-radius:9999px;filter:blur(70px);opacity:.35}
        .bg-orbs:before{width:480px;height:480px;background:linear-gradient(135deg,#7cccfd,#0c9ced);top:-160px;left:-140px}
        .bg-orbs:after{width:420px;height:420px;background:linear-gradient(135deg,#bae2fd,#7cccfd);bottom:-140px;right:-120px}
        .glass{background:linear-gradient(145deg,rgba(255,255,255,.9),rgba(255,255,255,.7));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%)}
        .input-base{width:100%;border-radius:.65rem;border:1px solid rgba(209,213,219,.7);background:rgba(255,255,255,.65);padding:.65rem .75rem;font-size:.85rem;transition:.2s}
        .input-base:not(textarea){height:44px;line-height:1.2}
        .input-base:focus{outline:none;background:#fff;border-color:#36b3f9;box-shadow:0 0 0 4px rgba(12,156,237,.25)}
        .field-label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;display:flex;gap:6px;align-items:center;color:#4b5563}
        .select-readonly{background:#e0effe!important;color:#0c9ced!important;border-color:#0281d4!important;cursor:not-allowed!important;pointer-events:none;opacity:1!important}

        /* Mobile compact adjustments: reduce paddings, font sizes and spacing without changing appearance */
        @media (max-width: 640px) {
            /* Reduce the large backdrop/padding on cards */
            .glass { padding: .75rem !important; border-radius: .75rem !important; }

            /* Header/title sizing */
            header .text-2xl { font-size: 1.125rem !important; }
            header .text-3xl { font-size: 1.25rem !important; }

            /* Labels and inputs */
            .field-label { font-size: 10px !important; gap:4px !important; }
            .input-base { font-size: 0.8rem !important; padding: .45rem .5rem !important; border-radius: .5rem !important; }
            .input-base:not(textarea) { height:38px !important; }
            textarea.input-base { padding: .5rem !important; }

            /* Tighter vertical spacing inside form blocks */
            .space-y-6 > div, .space-y-10 > div { margin-bottom: .5rem !important; }
            .space-y-6, .space-y-10 { gap: .5rem !important; }

            /* Reduce grid gaps on mobile */
            .grid { gap: .5rem !important; }

            /* Buttons: smaller padding and font */
            .inline-flex.px-6 { padding-left: .6rem !important; padding-right: .6rem !important; }
            .inline-flex.px-5 { padding-left: .5rem !important; padding-right: .5rem !important; }
            .inline-flex.items-center { font-size: .85rem !important; }

            /* Slightly smaller orb blurs so they don't dominate */
            .bg-orbs:before{width:360px;height:360px;top:-120px;left:-100px}
            .bg-orbs:after{width:320px;height:320px;bottom:-100px;right:-80px}
        }
    </style>
</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>

    <header class="relative max-w-6xl mx-auto px-4 md:px-8 pt-8">
        <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
            <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
            <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                <div>
                    <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
                        <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-gavel text-lg"></i></span>
                        <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Appoint Hearing</span>
                    </h1>
                    <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Schedule a hearing for a case and notify involved parties automatically.</p>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
                    <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-bell text-primary-500"></i> Auto Notifications</div>
                </div>
            </div>
        </div>
    </header>

    <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 mt-10 pb-24">
        <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 p-6 md:p-10 space-y-10">
            <?php if (!empty($success_message)): ?>
                <div class="mb-4 rounded-lg border border-green-300 bg-green-50 text-green-700 px-4 py-3 text-sm flex items-start gap-2">
                    <i class="fa fa-check-circle mt-0.5"></i>
                    <span><?php echo htmlspecialchars($success_message); ?></span>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-10">
                <input type="hidden" name="participants" id="participantsInput">
                <div class="grid md:grid-cols-2 gap-10">
                    <div class="space-y-6">
                        <div>
                            <label for="case-id" class="field-label"><i class="fa fa-briefcase"></i> Case</label>
                            <select id="case-id" name="case_id" class="input-base" required>
                                <option value="">-- Select a Case --</option>
                                <?php
                                $sql = "SELECT ci.Case_ID, co.Complaint_Title
                                        FROM case_info ci
                                        JOIN complaint_info co ON ci.Complaint_ID = co.Complaint_ID";
                                $result = $conn->query($sql);
                                if ($result && $result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo '<option value="' . $row['Case_ID'] . '">' . $row['Case_ID'] . ' - ' . htmlspecialchars($row['Complaint_Title']) . '</option>';
                                    }
                                } else {
                                    echo '<option disabled>No cases found</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div>
                            <label for="complainant_name" class="field-label"><i class="fa fa-user"></i> Complainant</label>
                            <input type="text" id="complainant_name" name="complainant_name" class="input-base bg-white/80" readonly />
                        </div>
                        <div>
                            <label for="Respondent_Name" class="field-label"><i class="fa fa-users"></i> Respondent(s)</label>
                            <input type="text" id="Respondent_Name" name="Respondent_Name" class="input-base bg-white/80" readonly />
                        </div>
                        <div>
                            <label for="hearing-title" class="field-label"><i class="fa fa-heading"></i> Title</label>
                            <input type="text" id="hearing-title" name="hearing_title" class="input-base" placeholder="Pre-hearing Conference" required />
                        </div>
                        <div>
                            <label for="hearing-date" class="field-label"><i class="fa fa-calendar-day"></i> Date</label>
                            <input type="date" id="hearing-date" name="hearing_date" class="input-base" required />
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label for="hearing-time" class="field-label"><i class="fa fa-clock"></i> Time</label>
                            <input type="time" id="hearing-time" name="hearing_time" class="input-base" required />
                        </div>
                        <div>
                            <label for="venue" class="field-label"><i class="fa fa-location-dot"></i> Venue</label>
                            <input type="text" id="venue" name="venue" class="input-base" placeholder="Barangay Hall Session Room" required />
                        </div>
                        <div>
                            <label for="hearing-remarks" class="field-label"><i class="fa fa-align-left"></i> Remarks</label>
                            <textarea id="hearing-remarks" name="hearing_remarks" rows="6" class="input-base resize-y" placeholder="Optional notes or instructions..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-dashed border-primary-200/60">
                    <a href="#" onclick="goBack(event)" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-lg bg-white/70 hover:bg-white text-gray-600 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-xmark"></i> Cancel</a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow focus:outline-none focus:ring-4 focus:ring-primary-300/50 transition">
                        <i class="fa fa-calendar-plus"></i> Schedule Hearing
                    </button>
                </div>
            </form>
        </section>
    </main>

    <?php include 'sidebar_.php';?>
</body>
<script>
    function goBack(event) {
        event.preventDefault();
        if (document.referrer && document.referrer !== window.location.href) {
            window.history.back();
        } else {
            window.location.href = 'view_hearing_calendar_captain.php';
        }
    }
    $('#case-id').on('change', function () {
        const caseId = $(this).val();
        if (caseId) {
            $.ajax({
                url: '../SecMenu/schedule/get_case_participants.php',
                type: 'GET',
                data: { Case_ID: caseId }, 
                success: function (response) {
                    try {
                        const data = JSON.parse(response);

                        if (data.complainant) {
                            $('#complainant_name').val(data.complainant);
                        } else {
                            $('#complainant_name').val('Not found');
                        }

                        if (data.respondents && data.respondents.length > 0) {
                            $('#Respondent_Name').val(data.respondents.join(', '));
                        } else {
                            $('#Respondent_Name').val('Not found');
                        }

                        const participantString = `Complainant: ${data.complainant ?? 'N/A'}, Respondent(s): ${data.respondents?.join(', ') || 'N/A'}`;
                        $('#participantsInput').val(participantString);

                    } catch (err) {
                        console.error('Invalid JSON:', err);
                        $('#complainant_name').val('');
                        $('#Respondent_Name').val('');
                    }
                },
                error: function () {
                    $('#complainant_name').val('');
                    $('#Respondent_Name').val('');
                    console.error('AJAX failed');
                }
            });
        } else {
            $('#complainant_name').val('');
            $('#Respondent_Name').val('');
        }
    });

    // Ensure no past dates
    document.getElementById('hearing-date').min = new Date().toISOString().split('T')[0];
</script>
</html>
