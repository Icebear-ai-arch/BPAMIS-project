
<?php
require_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/../../server/server.php';

use PhpOffice\PhpWord\TemplateProcessor;

// Path to the template
define('TEMPLATE_PATH', __DIR__ . '/KP Form 1 - Notice to Constitute Lupon.docx');
define('OUTPUT_DIR', __DIR__);

// Fetch cases for dropdown
function getCasesForDropdown($conn) {
    $cases = [];
    $sql = "SELECT c.Case_ID, ci.Complaint_Title, ci.Date_Filed, c.Case_Status, 
        CASE WHEN ci.Resident_ID IS NOT NULL THEN ri.First_Name WHEN ci.External_Complainant_ID IS NOT NULL THEN eci.first_name ELSE 'Unknown' END AS Complainant_First,
        CASE WHEN ci.Resident_ID IS NOT NULL THEN ri.Last_Name WHEN ci.External_Complainant_ID IS NOT NULL THEN eci.last_name ELSE 'Unknown' END AS Complainant_Last,
        resp.First_Name AS Respondent_First, resp.Last_Name AS Respondent_Last
        FROM case_info c
        JOIN complaint_info ci ON c.Complaint_ID = ci.Complaint_ID
        LEFT JOIN resident_info ri ON ci.Resident_ID = ri.Resident_ID
        LEFT JOIN external_complainant eci ON ci.External_Complainant_ID = eci.external_complaint_id
        LEFT JOIN resident_info resp ON ci.Respondent_ID = resp.Resident_ID
        ORDER BY c.Date_Opened DESC";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $cases[] = $row;
        }
    }
    return $cases;
}

// Helper: Render the multi-step wizard form
function renderWizard($step = 1, $data = [], $error = '') {
    global $conn;
    $fields = [
        'case_id' => 'Select Case',
        'province' => 'Province',
        'municipal' => 'Municipality/City',
        'barangay' => 'Barangay',
        'day' => 'Day',
        'year' => 'Year',
        'punong_barangay' => 'Punong Barangay',
    ];
    $steps = [
        1 => ['case_id', 'province', 'municipal', 'barangay', 'day', 'year'],
        2 => ['lupon_members'],
        3 => ['punong_barangay'],
        4 => [], // Review step
    ];
    $totalSteps = count($steps);
    $progress = ($step / $totalSteps) * 100;
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Fill KP Form 1 - Notice to Constitute Lupon</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>';
    echo '</head><body class="bg-blue-50 min-h-screen">';
    echo '<div class="flex justify-center items-center min-h-screen">';
    echo '<div class="w-full max-w-lg bg-white rounded-xl shadow-lg p-8 my-10">';
    echo '<div class="mb-6">';
    echo '<div class="flex items-center justify-between mb-2">';
    echo '<h2 class="text-2xl font-bold text-blue-800">Fill KP Form 1</h2>';
    echo '<a href="../view_kp_forms.php" class="text-sm text-gray-500 hover:text-blue-700"><i class="fas fa-arrow-left mr-1"></i>Back to Forms</a>';
    echo '</div>';
    echo '<div class="w-full bg-gray-200 rounded-full h-2.5 mb-4"><div class="bg-blue-600 h-2.5 rounded-full" style="width: '.round($progress).'%"></div></div>';
    echo '<div class="flex justify-between text-xs text-gray-600 mb-2">';
    echo '<span class="'.($step==1?'font-bold text-blue-700':'').'">Step 1</span>';
    echo '<span class="'.($step==2?'font-bold text-blue-700':'').'">Step 2</span>';
    echo '<span class="'.($step==3?'font-bold text-blue-700':'').'">Step 3</span>';
    echo '<span class="'.($step==4?'font-bold text-blue-700':'').'">Review</span>';
    echo '</div>';
    echo '</div>';
    if ($error) {
        echo '<div class="bg-red-100 text-red-700 border border-red-300 rounded px-4 py-2 mb-4">'.$error.'</div>';
    }
    echo '<form method="post" autocomplete="off">';
    echo '<input type="hidden" name="step" value="'.$step.'">';
    foreach ($data as $k => $v) {
        if (!in_array($k, $steps[$step])) {
            if (is_array($v)) {
                foreach ($v as $vv) {
                    echo '<input type="hidden" name="'.htmlspecialchars($k).'[]" value="'.htmlspecialchars($vv).'">';
                }
            } else {
                echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
            }
        }
    }
    if ($step <= 3) {
        // Set default values for location fields if not set
        if ($step == 1) {
            if (empty($data['province'])) $data['province'] = 'Bulacan';
            if (empty($data['municipal'])) $data['municipal'] = 'Calumpit';
            if (empty($data['barangay'])) $data['barangay'] = 'Panducot';
        }
        foreach ($steps[$step] as $name) {
            $value = isset($data[$name]) ? $data[$name] : '';
            $required = ($name !== 'punong_barangay') ? 'required' : '';
            if ($name === 'case_id') {
                $cases = getCasesForDropdown($conn);
                echo '<label for="case_id" class="block mb-2 font-medium text-gray-700">Select Case</label>';
                echo '<select id="case_id" name="case_id" class="mb-4 w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-400" required>';
                echo '<option value="">-- Select a Case --</option>';
                foreach ($cases as $case) {
                    $selected = ($value == $case['Case_ID']) ? 'selected' : '';
                    $label = $case['Case_ID'] . ': ' . htmlspecialchars($case['Complaint_Title']) . ' (' . htmlspecialchars($case['Complainant_First'] . ' ' . $case['Complainant_Last']) . ' vs ' . htmlspecialchars($case['Respondent_First'] . ' ' . $case['Respondent_Last']) . ')';
                    echo '<option value="'.htmlspecialchars($case['Case_ID']).'" '.$selected.'>'.$label.'</option>';
                }
                echo '</select>';
                // Add JS for autofill
                echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    var caseSelect = document.getElementById("case_id");
                    caseSelect.addEventListener("change", function() {
                        var province = document.getElementById("province");
                        var municipal = document.getElementById("municipal");
                        var barangay = document.getElementById("barangay");
                        if (province && !province.value) province.value = "Bulacan";
                        if (municipal && !municipal.value) municipal.value = "Calumpit";
                        if (barangay && !barangay.value) barangay.value = "Panducot";
                    });
                });
                </script>';
            } elseif ($name === 'lupon_members') {
                // Dynamic Lupon members fields
                $members = isset($data['lupon_members']) && is_array($data['lupon_members']) ? $data['lupon_members'] : [];
                $max = 25;
                $min = 5;
                // Remove trailing empty fields except one
                while (count($members) > 1 && end($members) === '') array_pop($members);
                // Always show at least $min fields
                $count = max($min, count($members));
                if ($count < $max && (empty($members) || end($members) !== '')) $count++;
                echo '<label class="block mb-2 font-medium text-gray-700">Constitute of Lupon Tagapamayapa (max 25)</label>';
                echo '<div id="lupon-members-list">';
                for ($i = 0; $i < $count && $i < $max; $i++) {
                    $val = isset($members[$i]) ? htmlspecialchars($members[$i]) : '';
                    echo '<input type="text" name="lupon_members[]" class="mb-2 w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-400" placeholder="Member #'.($i+1).'" value="'.$val.'">';
                }
                echo '</div>';
                echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    var list = document.getElementById("lupon-members-list");
                    function updateFields() {
                        var fields = list.querySelectorAll("input[name="lupon_member[]"]");
                        var filled = 0;
                        for (var i=0; i<fields.length; i++) if (fields[i].value.trim() !== "") filled++;
                        // Remove extra trailing blanks
                        for (var i=fields.length-1; i>0; i--) {
                            if (fields[i].value.trim() === "" && fields[i-1].value.trim() === "") fields[i].remove();
                            else break;
                        }
                        // Always show a blank below last filled, up to 25
                        if (fields.length < 25 && (fields[fields.length-1].value.trim() !== "")) {
                            var input = document.createElement("input");
                            input.type = "text";
                            input.name = "lupon_members[]";
                            input.className = "mb-2 w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-400";
                            input.placeholder = "Member #" + (fields.length+1);
                            list.appendChild(input);
                        }
                    }
                    list.addEventListener("input", updateFields);
                    updateFields();
                });
                </script>';
            } else {
                echo '<label for="'.$name.'" class="block mb-2 font-medium text-gray-700">'.$fields[$name].'</label>';
                if (is_array($value)) $value = '';
                echo '<input type="text" id="'.$name.'" name="'.$name.'" value="'.htmlspecialchars($value).'" '.$required.' class="mb-4 w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-400">';
            }
        }
    } elseif ($step == 4) {
        // Review step
        $cases = getCasesForDropdown($conn);
        $selectedCase = null;
        foreach ($cases as $case) {
            if ($case['Case_ID'] == $data['case_id']) {
                $selectedCase = $case;
                break;
            }
        }
        if ($selectedCase) {
            echo '<div class="mb-4"><span class="font-semibold text-gray-700">Case:</span> '.htmlspecialchars($selectedCase['Case_ID']) . ': ' . htmlspecialchars($selectedCase['Complaint_Title']) . ' (' . htmlspecialchars($selectedCase['Complainant_First'] . ' ' . $selectedCase['Complainant_Last']) . ' vs ' . htmlspecialchars($selectedCase['Respondent_First'] . ' ' . $selectedCase['Respondent_Last']) . ')</div>';
        }
        echo '<div class="mb-4"><span class="font-semibold text-gray-700">Province:</span> '.htmlspecialchars($data['province']).'</div>';
        echo '<div class="mb-4"><span class="font-semibold text-gray-700">Municipality:</span> '.htmlspecialchars($data['municipal']).'</div>';
        echo '<div class="mb-4"><span class="font-semibold text-gray-700">Barangay:</span> '.htmlspecialchars($data['barangay']).'</div>';
        echo '<div class="mb-4"><span class="font-semibold text-gray-700">Day:</span> '.htmlspecialchars($data['day']).'</div>';
        echo '<div class="mb-4"><span class="font-semibold text-gray-700">Year:</span> '.htmlspecialchars($data['year']).'</div>';
        if (!empty($data['lupon_members']) && is_array($data['lupon_members'])) {
            echo '<div class="mb-4"><span class="font-semibold text-gray-700">Lupon Tagapamayapa Members:</span><ul class="list-disc ml-6">';
            foreach ($data['lupon_members'] as $member) {
                if (trim($member) !== '') echo '<li>'.htmlspecialchars($member).'</li>';
            }
            echo '</ul></div>';
        }
        echo '<div class="mb-4"><span class="font-semibold text-gray-700">Punong Barangay:</span> '.htmlspecialchars($data['punong_barangay']).'</div>';
    }
    echo '<div class="flex justify-between mt-8">';
    if ($step > 1) {
        echo '<button type="submit" name="back" value="1" class="bg-gray-400 hover:bg-gray-500 text-white font-semibold py-2 px-6 rounded"><i class="fas fa-arrow-left mr-1"></i>Back</button>';
    } else {
        echo '<span></span>';
    }
    if ($step < 4) {
        echo '<button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded">Next <i class="fas fa-arrow-right ml-1"></i></button>';
    } else {
        echo '<button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded"><i class="fas fa-file-word mr-1"></i>Generate</button>';
    }
    echo '</div>';
    echo '</form>';
    echo '</div></div></body></html>';
}

// Handle wizard logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = isset($_POST['step']) ? intval($_POST['step']) : 1;
    $data = $_POST;
    unset($data['step'], $data['back']);
    $error = '';
    if (isset($_POST['back'])) {
        $step = max(1, $step - 1);
        renderWizard($step, $data);
        exit;
    }
    // Validation per step
    if ($step == 1) {
        foreach (['case_id','province','municipal','barangay','day','year'] as $f) {
            if (empty($_POST[$f])) $error = 'Please fill all required fields.';
        }
    } elseif ($step == 2) {
        // At least one lupon member required
        $hasMember = false;
        if (isset($_POST['lupon_members']) && is_array($_POST['lupon_members'])) {
            foreach ($_POST['lupon_members'] as $member) {
                if (trim($member) !== '') {
                    $hasMember = true;
                    break;
                }
            }
        }
        if (!$hasMember) $error = 'Please enter at least one appointee.';
    } elseif ($step == 3) {
        if (empty($_POST['punong_barangay'])) $error = 'Please enter the Punong Barangay.';
    }
    if ($error) {
        renderWizard($step, $data, $error);
        exit;
    }
    if ($step < 4) {
        renderWizard($step+1, $data);
        exit;
    }
    // Step 4: Generate document
    $province = $data['province'] ?? '';
    $municipal = $data['municipal'] ?? '';
    $barangay = $data['barangay'] ?? '';
    $day = $data['day'] ?? '';
    $year = $data['year'] ?? '';
    $punong_barangay = $data['punong_barangay'] ?? '';
    $lupon_members = isset($data['lupon_members']) && is_array($data['lupon_members']) ? $data['lupon_members'] : [];
    $templateProcessor = new TemplateProcessor(TEMPLATE_PATH);
    $templateProcessor->setValue('province', $province);
    $templateProcessor->setValue('municipal', $municipal);
    $templateProcessor->setValue('barangay', $barangay);
    $templateProcessor->setValue('day', $day);
    $templateProcessor->setValue('year', $year);
    $templateProcessor->setValue('punong_barangay', $punong_barangay);
    for ($i = 1; $i <= 25; $i++) {
        $templateProcessor->setValue('appointee'.$i, isset($lupon_members[$i-1]) ? $lupon_members[$i-1] : '');
    }
    $tmpFile = tempnam(sys_get_temp_dir(), 'kpform1_') . '.docx';
    $templateProcessor->saveAs($tmpFile);
    // Show success and download link
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>KP Form 1 Generated</title>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>';
    echo '</head><body class="bg-blue-50 min-h-screen">';
    echo '<div class="flex justify-center items-center min-h-screen">';
    echo '<div class="w-full max-w-lg bg-white rounded-xl shadow-lg p-8 my-10 text-center">';
    echo '<div class="text-green-700 bg-green-100 border border-green-300 rounded px-4 py-4 mb-6"><i class="fas fa-check-circle fa-2x mb-2"></i><br>KP Form 1 has been generated successfully!</div>';
    $downloadUrl = basename($tmpFile);
    // Move file to output dir for download
    $finalPath = OUTPUT_DIR . '/' . $downloadUrl;
    copy($tmpFile, $finalPath);
    unlink($tmpFile);
    echo '<a href="'.htmlspecialchars($downloadUrl).'" download class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded mb-4"><i class="fas fa-download mr-1"></i>Download KP Form 1</a><br>';
    echo '<a href="fill_kp_forms.php" class="inline-block mt-2 text-gray-500 hover:text-blue-700"><i class="fas fa-arrow-left mr-1"></i>Fill Another Form</a>';
    echo '<a href="../view_kp_forms.php" class="ml-2 py-2 px-4 mt-2 text-gray-500 hover:text-blue-700"><i class="fas fa-arrow-left mr-1"></i>Back to KP Form</a>';
    echo '</div></div></body></html>';
    exit;
} else {
    renderWizard(1, []);
} 