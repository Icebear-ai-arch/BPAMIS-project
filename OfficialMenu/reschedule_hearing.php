<?php
include '../controllers/session_control.php';
include '../SecMenu/schedule/db-connect.php';

// Handle AJAX fetch
if (isset($_GET['ajax']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM schedule_list WHERE hearingID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = bpamis_stmt_get_result($stmt);
    echo json_encode($result->fetch_assoc());
    exit;
}

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
    $participants = trim($_POST['participants'] ?? '');
    $remarks = trim($_POST['hearing_remarks'] ?? '') ?: 'N/A';
    $hearingDateTime = $hearingDate . ' ' . $hearingTime . ':00';

    if ($editId > 0) {
        $stmt = $conn->prepare("UPDATE schedule_list SET hearingTitle=?, hearingDateTime=?, place=?, participant=?, remarks=? WHERE hearingID=?");
        $stmt->bind_param("sssssi", $hearingTitle, $hearingDateTime, $venue, $participants, $remarks, $editId);
        $stmt->execute();
        $notice = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">
                     <strong>Success!</strong> Hearing has been updated.
                   </div>';
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
    tailwind.config = { theme:{ extend:{ colors:{ primary:{50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76'}}, boxShadow:{glow:'0 0 0 1px rgba(12,156,237,.10),0 4px 18px -2px rgba(6,90,143,.18)'} } } };
  </script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
  <style>
    .bg-orbs:before,.bg-orbs:after{content:"";position:absolute;border-radius:9999px;filter:blur(70px);opacity:.35}
    .bg-orbs:before{width:480px;height:480px;background:linear-gradient(135deg,#7cccfd,#0c9ced);top:-160px;left:-140px}
    .bg-orbs:after{width:420px;height:420px;background:linear-gradient(135deg,#bae2fd,#7cccfd);bottom:-140px;right:-120px}
    .glass{background:linear-gradient(145deg,rgba(255,255,255,.9),rgba(255,255,255,.7));backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%)}
    .input-base{width:100%;border-radius:.65rem;border:1px solid rgba(209,213,219,.7);background:rgba(255,255,255,.65);padding:.65rem .75rem;font-size:.85rem;transition:.2s}
    .input-base:not(textarea){height:44px;line-height:1.2}
    .input-base:focus{outline:none;background:#fff;border-color:#36b3f9;box-shadow:0 0 0 4px rgba(12,156,237,.25)}
    .field-label{font-size:11px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;margin-bottom:4px;display:flex;gap:6px;align-items:center;color:#4b5563}
  </style>
</head>
<body class="min-h-screen font-sans bg-gradient-to-br from-primary-50 via-white to-primary-100 text-gray-800 relative overflow-x-hidden bg-orbs">

<!-- Top Navigation -->
<?php include '../includes/barangay_official_cap_nav.php'; ?>

  <!-- Page Header (premium) -->
  <header class="relative max-w-6xl mx-auto px-4 md:px-8 pt-8">
    <div class="relative glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 px-6 py-8 md:px-10 md:py-12 overflow-hidden">
      <div class="absolute -top-10 -right-10 w-48 h-48 rounded-full bg-primary-200/60 blur-2xl"></div>
      <div class="absolute -bottom-12 -left-12 w-64 h-64 rounded-full bg-primary-300/40 blur-3xl"></div>
      <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
        <div>
          <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-gray-800 flex items-center gap-3">
            <span class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-primary-100 text-primary-600 shadow-inner ring-1 ring-white/60"><i class="fa fa-calendar-pen text-lg"></i></span>
            <span class="bg-clip-text text-transparent bg-gradient-to-r from-primary-700 to-primary-500">Reschedule Hearing</span>
          </h1>
          <p class="mt-3 text-sm md:text-base text-gray-600 max-w-prose">Update the hearing schedule for a case and optionally notify involved parties.</p>
        </div>
        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
          <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-bell text-primary-500"></i> Notifications</div>
          <div class="px-3 py-1 rounded-full bg-white/70 border border-primary-100 flex items-center gap-2"><i class="fa fa-shield-halved text-primary-500"></i> Secure Form</div>
        </div>
      </div>
    </div>
  </header>

  <main class="relative z-10 max-w-5xl mx-auto px-4 md:px-8 mt-10 pb-24">
    <section class="glass rounded-2xl shadow-glow border border-white/60 ring-1 ring-primary-100/60 p-6 md:p-10 space-y-8">
    
      <?php
        include '../server/server.php';

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $hearing_id = $_POST['hearing_id'];
            $new_date = $_POST['hearing_date'];
            $new_time = $_POST['hearing_time'];
            $remark = $_POST['hearing_remarks'];
            $notify = isset($_POST['notify_parties']) ? 1 : 0;

          if ($hearing_id && $new_date && $new_time) {
            $new_datetime = date("Y-m-d H:i:s", strtotime("$new_date $new_time"));

            $stmt = $conn->prepare("UPDATE schedule_list SET hearingDateTime = ?, remarks = ? WHERE hearingID = ?");
            $stmt->bind_param("ssi", $new_datetime, $remark, $hearing_id);

            if ($stmt->execute()) {
                echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" role="alert">';
                echo '<strong>Success!</strong> Hearing has been rescheduled.';
                echo '</div>';
              }

                if ($notify) {
                    // Fetch complaint_id from case_info
                    $get_case = $conn->prepare("
                        SELECT ci.complaint_id 
                        FROM schedule_list sl
                        JOIN case_info ci ON sl.case_id = ci.case_id
                        WHERE sl.hearingID = ?
                    ");
                    $get_case->bind_param("i", $hearing_id);
                    $get_case->execute();
                    $case_result = bpamis_stmt_get_result($get_case);

                    if ($case_result->num_rows > 0) {
                        $row = $case_result->fetch_assoc();
                        $complaint_id = $row['complaint_id'];

                        $msg = "Your hearing (ID: $hearing_id) has been rescheduled to $new_date at $new_time.";

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
                }
            } else {
                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" role="alert">';
                echo '<strong>Error!</strong> ' . $conn->error;
                echo '</div>';
            }
        }
        ?>

    <?= $notice ?? '' ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <!-- First Column -->
        <div class="space-y-6">
                    <div>
            <label for="schedule-dropdown" class="field-label"><i class="fa fa-calendar"></i> Select Hearing</label>
      <select id="schedule-dropdown" name="case_id" class="input-base" required>
                            <option value="">-- Select a Case --</option>
            <?php
            $sql = "SELECT hearingID, hearingTitle FROM schedule_list";
            $result = $conn->query($sql);
            while ($row = $result->fetch_assoc()) {
                echo "<option value='{$row['hearingID']}'>{$row['hearingID']} - " . htmlspecialchars($row['hearingTitle']) . "</option>";
            }
            ?>
            </select>
        </div>

      <input type="hidden" id="hearing_id" name="hearing_id" value="<?= $editData['hearingID'] ?? '' ?>">
      
        <div class="mb-4">
          <label for="hearing-title" class="field-label"><i class="fa fa-heading"></i> Title</label>
          <input readonly type="text" id="hearing-title" name="hearing_title" value="<?= $editData['hearingTitle'] ?? '' ?>" class="input-base bg-white/80" required>
        </div>

        <div class="mb-4">
          <label for="hearing-date" class="field-label"><i class="fa fa-calendar-day"></i> Date</label>
          <input type="date" id="hearing-date" name="hearing_date" value="<?= isset($editData['hearingDateTime']) ? date('Y-m-d', strtotime($editData['hearingDateTime'])) : '' ?>" class="input-base" required>
        </div>
        </div>

        <div class="space-y-6">
          <div class="mb-4">
          <label for="hearing-time" class="field-label"><i class="fa fa-clock"></i> Time</label>
          <input type="time" id="hearing-time" name="hearing_time" value="<?= isset($editData['hearingDateTime']) ? date('H:i', strtotime($editData['hearingDateTime'])) : '' ?>" class="input-base" required>
          </div>

          <div class="mb-4">
          <label for="venue" class="field-label"><i class="fa fa-location-dot"></i> Venue</label>
          <input readonly type="text" id="venue" name="venue" value="<?= $editData['place'] ?? '' ?>" class="input-base bg-white/80" required>
          </div>

          <div class="mb-4">
          <label for="participants" class="field-label"><i class="fa fa-users"></i> Participants</label>
          <textarea readonly id="participants" name="participants" class="input-base bg-white/80 resize-y" rows="3"><?= $editData['participant'] ?? '' ?></textarea>
        </div>


          

          <div class="mb-4">
          <label for="hearing-remarks" class="field-label"><i class="fa fa-align-left"></i> Remarks</label>
          <textarea id="hearing-remarks" name="hearing_remarks" class="input-base resize-y" rows="4" placeholder="Add remarks (optional)"><?= $editData['remarks'] ?? '' ?></textarea>
          </div>
          </div>
          </div>
          <div class="flex flex-col sm:flex-row justify-between items-center gap-3 mt-6 pt-4 border-t border-dashed border-primary-200/60">
            <a href="view_hearing_calendar_captain.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/80 hover:bg-white text-gray-700 border border-gray-300 text-sm font-medium shadow-sm transition"><i class="fa fa-arrow-left"></i> Back to Calendar</a>
            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="notify_parties" class="h-4 w-4 text-blue-600 border-gray-300 rounded"> Notify parties</label>
              <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white shadow text-sm font-medium transition">
                <i class="fas fa-calendar-check"></i> Update Hearing
              </button>
            </div>
          </div>
      </form>
    </section>
  </main>


<script>
  document.getElementById('hearing-date').min = new Date().toISOString().split('T')[0];

 document.getElementById('schedule-dropdown').addEventListener('change', function () {
  const id = this.value;
  if (!id) return;

  fetch(`<?= $_SERVER['PHP_SELF'] ?>?ajax=1&id=${id}`)
    .then(res => res.json())
    .then(data => {
      document.getElementById('hearing_id').value = data.hearingID || '';
      document.getElementById('hearing-title').value = data.hearingTitle || '';
      
      if (data.hearingDateTime) {
        const [date, time] = data.hearingDateTime.split(' ');
        document.getElementById('hearing-date').value = date;
        document.getElementById('hearing-time').value = time.slice(0, 5);
      }

      document.getElementById('venue').value = data.place || '';
      document.getElementById('participants').value = data.participant || '';
      document.getElementById('hearing-remarks').value = data.remarks || '';
      const cName = document.getElementById('complainant_name');
      if (cName) cName.value = data.complainant_name || '';
      const rName = document.getElementById('Respondent_Name');
      if (rName) rName.value = data.respondent_name || '';
    });
});

</script>
<?php include '../chatbot/bpamis_case_assistant.php'?>
</body>
</html>