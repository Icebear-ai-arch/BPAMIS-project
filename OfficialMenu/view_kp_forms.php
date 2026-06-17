<?php
/**
 * View KP Forms Page
 * Barangay Panducot Adjudication Management Information System
 */
include '../controllers/session_control.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KP Forms - Barangay Panducot</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    
    <?php include 'sidebar_.php';?>
</head>
<body class="bg-blue-50">
    <?php include '../includes/barangay_official_cap_nav.php'; ?>
    <div class="container mx-auto mt-10 p-5 bg-white shadow-md rounded-lg">
        <h2 class="text-2xl font-semibold text-blue-800 mb-6">Katarungang Pambarangay Forms</h2>
        
        <div class="mb-6">
            <p class="text-gray-700">
                The Katarungang Pambarangay (KP) forms are official documents used in the barangay justice system. 
                These forms help in documenting various stages of the dispute resolution process as mandated by 
                the Katarungang Pambarangay Law (P.D. 1508 and R.A. 7160).
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php
            // In a real application, you would fetch this from a database
            // For now, we'll use a hardcoded array of forms
            $kpForms = [
                [
                    'id' => 'KP-Form-01',
                    'name' => 'Complaint Form',
                    'description' => 'Form to be filled out by the complainant to start the process.',
                    'icon' => 'fa-file-signature'
                ],
                [
                    'id' => 'KP-Form-02',
                    'name' => 'Summons',
                    'description' => 'Official notice requiring the respondent to appear before the Lupong Tagapamayapa.',
                    'icon' => 'fa-gavel'
                ],
                [
                    'id' => 'KP-Form-03',
                    'name' => 'Notice of Hearing (Mediation)',
                    'description' => 'Notice sent to parties for the scheduled mediation proceedings.',
                    'icon' => 'fa-calendar-check'
                ],
                [
                    'id' => 'KP-Form-04',
                    'name' => 'Notice of Hearing (Conciliation)',
                    'description' => 'Notice sent to parties for the scheduled conciliation proceedings.',
                    'icon' => 'fa-handshake'
                ],
                [
                    'id' => 'KP-Form-05',
                    'name' => 'Agreement for Arbitration',
                    'description' => 'Agreement to submit the dispute to arbitration by the Punong Barangay.',
                    'icon' => 'fa-balance-scale'
                ],
                [
                    'id' => 'KP-Form-06',
                    'name' => 'Arbitration Award',
                    'description' => 'Decision rendered by the Punong Barangay after arbitration.',
                    'icon' => 'fa-trophy'
                ],
                [
                    'id' => 'KP-Form-07',
                    'name' => 'Amicable Settlement',
                    'description' => 'Written agreement between the parties resolving their dispute.',
                    'icon' => 'fa-file-contract'
                ],
                [
                    'id' => 'KP-Form-08',
                    'name' => 'Certification to File Action',
                    'description' => 'Certificate allowing parties to file a case in court after failed settlement.',
                    'icon' => 'fa-certificate'
                ],
                [
                    'id' => 'KP-Form-09',
                    'name' => 'Notice of Execution',
                    'description' => 'Notice informing parties of the execution of amicable settlement or arbitration award.',
                    'icon' => 'fa-clipboard-check'
                ]
            ];
            
            foreach ($kpForms as $form) {
                echo '<div class="bg-gray-50 rounded-lg shadow-sm p-6 flex flex-col hover:shadow-md transition-shadow">';
                echo '<div class="flex items-center mb-4">';
                echo '<span class="text-blue-600 bg-blue-100 p-3 rounded-full mr-4"><i class="fas ' . $form['icon'] . ' text-xl"></i></span>';
                echo '<h3 class="text-lg font-semibold">' . $form['id'] . ': ' . $form['name'] . '</h3>';
                echo '</div>';
                echo '<p class="text-gray-600 flex-grow">' . $form['description'] . '</p>';
                echo '<div class="flex justify-between mt-4 pt-4 border-t border-gray-200">';
                echo '<a href="view_form_template.php?id=' . $form['id'] . '" class="text-blue-600 hover:underline flex items-center">';
                echo '<i class="fas fa-eye mr-1"></i> View Template';
                echo '</a>';
                echo '<a href="print_kp_forms.php?form=' . $form['id'] . '" class="text-green-600 hover:underline flex items-center">';
                echo '<i class="fas fa-print mr-1"></i> Generate Form';
                echo '</a>';
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
        
        <div class="mt-8 p-4 bg-blue-100 rounded-lg">
            <h3 class="text-xl font-semibold text-blue-800">Need Help?</h3>
            <p class="mt-2 text-blue-700">
                For guidance on how to properly fill out these forms or for other legal inquiries, 
                please consult with the Barangay Secretary or Punong Barangay during office hours.            </p>
        </div>
    </div>
    
    <!-- Case Assistant Chatbot -->
    <?php include '../chatbot/bpamis_case_assistant.php'?>
</body>
</html>
