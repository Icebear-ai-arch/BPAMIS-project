<?php
/**
 * Print KP Forms Page
 * Barangay Panducot Adjudication Management Information System
 */
include '../controllers/session_control.php';

// Get the requested form
$form_id = isset($_GET['form']) ? $_GET['form'] : '';
$case_id = isset($_GET['case']) ? $_GET['case'] : '';

// In a real app, you would fetch form details and case details from the database
// For now, we'll use hardcoded data
$formTitle = '';
$formDescription = '';

switch ($form_id) {
    case 'KP-Form-01':
        $formTitle = 'Complaint Form';
        $formDescription = 'Official form to be filled out by the complainant to initiate a case.';
        break;
    case 'KP-Form-02':
        $formTitle = 'Summons';
        $formDescription = 'Official notice requiring the respondent to appear before the Lupong Tagapamayapa.';
        break;
    case 'KP-Form-07':
        $formTitle = 'Amicable Settlement';
        $formDescription = 'Written agreement between the parties resolving their dispute.';
        break;
    default:
        $formTitle = 'KP Form';
        $formDescription = 'Katarungang Pambarangay Form';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $formTitle ?> - Barangay Panducot</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <?php include '../includes/case_assistant_styles.php'; ?>
    <?php include 'sidebar_.php';?>
    <style>
        @media print {
            .no-print {
                display: none;
            }
            body {
                font-size: 12pt;
            }
            .print-container {
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }
            .form-field {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body class="bg-blue-50">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>
    <div class="container mx-auto mt-10 no-print">
        <div class="bg-white p-5 shadow-md rounded-lg">
            <h2 class="text-2xl font-semibold text-blue-800 mb-6"><?= $formTitle ?></h2>
            
            <p class="mb-6 text-gray-700"><?= $formDescription ?></p>
            
            <?php if (!$case_id): ?>
            <!-- Form Selection and Case Selection -->
            <div class="mb-6">
                <form method="get" class="space-y-4">
                    <div>
                        <label for="form" class="block text-sm font-medium text-gray-700">Select Form</label>
                        <select id="form" name="form" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                            <option value="KP-Form-01" <?= ($form_id == 'KP-Form-01') ? 'selected' : '' ?>>KP-Form-01: Complaint Form</option>
                            <option value="KP-Form-02" <?= ($form_id == 'KP-Form-02') ? 'selected' : '' ?>>KP-Form-02: Summons</option>
                            <option value="KP-Form-03" <?= ($form_id == 'KP-Form-03') ? 'selected' : '' ?>>KP-Form-03: Notice of Hearing (Mediation)</option>
                            <option value="KP-Form-04" <?= ($form_id == 'KP-Form-04') ? 'selected' : '' ?>>KP-Form-04: Notice of Hearing (Conciliation)</option>
                            <option value="KP-Form-05" <?= ($form_id == 'KP-Form-05') ? 'selected' : '' ?>>KP-Form-05: Agreement for Arbitration</option>
                            <option value="KP-Form-06" <?= ($form_id == 'KP-Form-06') ? 'selected' : '' ?>>KP-Form-06: Arbitration Award</option>
                            <option value="KP-Form-07" <?= ($form_id == 'KP-Form-07') ? 'selected' : '' ?>>KP-Form-07: Amicable Settlement</option>
                            <option value="KP-Form-08" <?= ($form_id == 'KP-Form-08') ? 'selected' : '' ?>>KP-Form-08: Certification to File Action</option>
                            <option value="KP-Form-09" <?= ($form_id == 'KP-Form-09') ? 'selected' : '' ?>>KP-Form-09: Notice of Execution</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="case" class="block text-sm font-medium text-gray-700">Select Case</label>
                        <select id="case" name="case" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm">
                            <option value="">-- Select a Case --</option>
                            <option value="KP-2025-001">KP-2025-001: Property Boundary Dispute</option>
                            <option value="KP-2025-002">KP-2025-002: Unpaid Debt</option>
                            <option value="KP-2025-003">KP-2025-003: Noise Complaint</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-file-alt mr-1"></i> Generate Form
                        </button>
                        <a href="view_kp_forms.php" class="ml-2 bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                            Back to Forms
                        </a>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <!-- Form Preview -->
            <div class="mb-6 flex justify-between">
                <button onclick="window.print()" class="bg-green-600 text-white py-2 px-4 rounded-lg hover:bg-green-700">
                    <i class="fas fa-print mr-1"></i> Print Form
                </button>
                <a href="print_kp_forms.php" class="bg-gray-500 text-white py-2 px-4 rounded-lg hover:bg-gray-600">
                    <i class="fas fa-arrow-left mr-1"></i> Back
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($case_id): ?>
    <!-- Printable Form -->
    <div class="print-container max-w-4xl mx-auto mt-8 mb-8 bg-white p-8 shadow-lg border">
        <div class="print-header">
            <div class="text-center mb-8">
                <h3 class="text-xl font-bold">REPUBLIC OF THE PHILIPPINES</h3>
                <h4 class="text-lg">PROVINCE OF PAMPANGA</h4>
                <h4 class="text-lg">MUNICIPALITY OF SAN SIMON</h4>
                <h4 class="text-lg font-bold">BARANGAY PANDUCOT</h4>
                <h5 class="text-lg mt-6 font-bold">OFFICE OF THE LUPONG TAGAPAMAYAPA</h5>
            </div>
            
            <div class="text-center my-8">
                <h2 class="text-2xl font-bold border-b-2 border-t-2 border-black py-2"><?= $formTitle ?></h2>
                <p class="text-sm mt-2"><?= $form_id ?></p>
            </div>
        </div>
        
        <?php if ($form_id == 'KP-Form-01'): // Complaint Form ?>
            <div class="form-field">
                <p>Case No: <u>&nbsp; <?= $case_id ?> &nbsp;</u></p>
                <p>For: <u>&nbsp; Property Boundary Dispute &nbsp;</u></p>
            </div>
            
            <div class="form-field">
                <p><b>Complainant/s:</b> <u>&nbsp; Maria Garcia &nbsp;</u></p>
                <p><b>Respondent/s:</b> <u>&nbsp; Roberto Reyes &nbsp;</u></p>
            </div>
            
            <div class="form-field mt-8">
                <h3 class="font-bold text-center mb-4">COMPLAINT</h3>
                <p>I/WE hereby complain against the above named respondent/s for violating my/our rights and interests in the following manner:</p>
                <p class="my-4 border-b border-black">&nbsp; The respondent has constructed a fence that encroaches approximately 2 meters into my property. &nbsp;</p>
                <p class="my-4 border-b border-black">&nbsp; Despite several verbal requests to adjust the fence to the correct boundary line, the respondent has refused to take action. &nbsp;</p>
                <p class="my-4 border-b border-black">&nbsp; This encroachment restricts access to a portion of my property and has damaged my garden. &nbsp;</p>
            </div>
            
            <div class="form-field mt-8">
                <p>THEREFORE, I/WE pray that the respondent/s be summoned, and that this complaint be heard and resolved in accordance with law.</p>
                
                <div class="mt-12 flex justify-between">
                    <div class="text-center w-1/2">
                        <p class="border-t border-black pt-2">Complainant's Signature</p>
                    </div>
                    <div class="text-center w-1/2">
                        <p><?= date('F d, Y') ?></p>
                        <p class="border-t border-black pt-2">Date</p>
                    </div>
                </div>
            </div>
            
            <div class="form-field mt-12">
                <h3 class="font-bold mb-4">ACTION TAKEN:</h3>
                <p>[ ] Complaint settled at mediation stage</p>
                <p>[ ] Complaint referred to conciliation</p>
                <p>[ ] No conciliation/settlement reached</p>
                <p>[ ] Certificate to File Action issued</p>
                
                <div class="mt-12 text-center">
                    <p class="border-t border-black pt-2 w-64 mx-auto">Punong Barangay/Lupon Chairman</p>
                </div>
            </div>
        
        <?php elseif ($form_id == 'KP-Form-07'): // Amicable Settlement ?>
            <div class="form-field">
                <p>Case No: <u>&nbsp; <?= $case_id ?> &nbsp;</u></p>
                <p>For: <u>&nbsp; Property Boundary Dispute &nbsp;</u></p>
            </div>
            
            <div class="form-field">
                <p><b>Complainant/s:</b> <u>&nbsp; Maria Garcia &nbsp;</u></p>
                <p><b>Respondent/s:</b> <u>&nbsp; Roberto Reyes &nbsp;</u></p>
            </div>
            
            <div class="form-field mt-8">
                <h3 class="font-bold text-center mb-4">AMICABLE SETTLEMENT</h3>
                <p>We, the undersigned complainant/s and respondent/s in the above-captioned case, do hereby agree to settle our dispute as follows:</p>
                <p class="my-4 border-b border-black">&nbsp; The respondent agrees to relocate the fence to the correct property boundary within 30 days from this date. &nbsp;</p>
                <p class="my-4 border-b border-black">&nbsp; The respondent will repair any damage caused to the complainant's garden. &nbsp;</p>
                <p class="my-4 border-b border-black">&nbsp; Both parties agree to have the boundary surveyed by a licensed surveyor, with costs shared equally. &nbsp;</p>
                <p class="my-4 border-b border-black">&nbsp; Both parties agree to respect the property boundary as established by the survey. &nbsp;</p>
            </div>
            
            <div class="form-field mt-8">
                <p>We hereby bind ourselves to comply with the above terms and conditions.</p>
                
                <div class="mt-12 flex justify-between">
                    <div class="text-center w-1/2">
                        <p>Maria Garcia</p>
                        <p class="border-t border-black pt-2">Complainant</p>
                    </div>
                    <div class="text-center w-1/2">
                        <p>Roberto Reyes</p>
                        <p class="border-t border-black pt-2">Respondent</p>
                    </div>
                </div>
                
                <div class="mt-12 text-center">
                    <p>ATTESTED:</p>
                    <p class="mt-8">Juan Dela Paz</p>
                    <p class="border-t border-black pt-2 w-64 mx-auto">Punong Barangay/Lupon Chairman</p>
                </div>
            </div>
        
        <?php else: ?>
            <div class="form-field">
                <p class="text-center">Preview for <?= $form_id ?> form is not available in this demo.</p>
                <p class="text-center">In a complete implementation, this would display the appropriate form based on the form type selected.</p>
            </div>
        <?php endif; ?>
    </div>    <?php endif; ?>
    
    <?php include '../includes/case_assistant.php'; ?>
</body>
</html>
