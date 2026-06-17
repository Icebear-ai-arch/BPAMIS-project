
<?php
include '../controllers/session_control.php';
// Ensure DB connection and session are initialized before any output
require_once(__DIR__ . '/../server/server.php');
// --- CAPTCHA VERIFICATION FUNCTION ---
function verify_captcha($captcha_response)
{
    $secret = "6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe"; // Google's test secret key
    $url = "https://www.google.com/recaptcha/api/siteverify";
    $data = [
        'secret' => $secret,
        'response' => $captcha_response
    ];
    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ]
    ];
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    if ($result === FALSE) {
        return false;
    }
    $resultData = json_decode($result, true);
    return $resultData["success"] ?? false;
}

// --- REGISTER HANDLER (demo, does not save) ---
$errors = [];
$old = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_email'])) {
    $response = ['success' => false, 'message' => ''];

    $fname = trim($_POST['reg_fname']);
    $mname = trim($_POST['reg_mname']);
    $lname = trim($_POST['reg_lname']);
    $address = trim($_POST['reg_address']);
    $email = trim($_POST['reg_email']);
    $type = $_POST['reg_type'];
    $pass = $_POST['reg_pass'];
    $pass2 = $_POST['reg_pass_confirm'];
    $terms = isset($_POST['reg_terms']);
    $privacy = isset($_POST['reg_privacy']);
    $captcha = $_POST['g-recaptcha-response'];

    // Save old values except passwords
    $old = [
        'reg_fname' => $fname,
        'reg_mname' => $mname,
        'reg_lname' => $lname,
        'reg_address' => $address,
        'reg_email' => $email,
        'reg_type' => $type,
        'reg_terms' => $terms,
        'reg_privacy' => $privacy
    ];

    // No validation or qualification checks
    // Remove captcha validation
    // if (empty($captcha) || !verify_captcha($captcha)) {
    //     $errors['captcha'] = "Please complete the captcha correctly.";
    // }

    if (empty($errors)) {
        $response['success'] = true;
        $response['message'] = 'Registration successful!';

        // Send JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        $response['message'] = implode('<br>', $errors);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>
<?php
// Fetch officials for table list
$officials = [];
if (isset($conn)) {
    if ($stmt = $conn->prepare("SELECT Official_ID, official_username, Name, email, Contact_Number, Position, isActive FROM barangay_officials ORDER BY Official_ID DESC")) {
        $stmt->execute();
        $result = bpamis_stmt_get_result($stmt);
        if ($result) {
            $officials = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Official Account • Admin</title>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f7ff',
                            100: '#e0effe',
                            200: '#bae2fd',
                            300: '#7cccfd',
                            400: '#36b3f9',
                            500: '#0c9ced',
                            600: '#0281d4',
                            700: '#026aad',
                            800: '#065a8f',
                            900: '#0a4b76'
                        }
                    },
                    animation: {
                        'float': 'float 3s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' }
                        }
                    }
                }
            }
        }
    </script>

        <style>
            /* Premium shared styles to match Add External Account */
            .premium-card { background: linear-gradient(135deg, rgba(255,255,255,.96) 0%, rgba(255,255,255,.9) 100%); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,.35); box-shadow: 0 12px 36px rgba(0,0,0,.08); }
            .premium-gradient { background: linear-gradient(135deg, #667eea 0%, #36b3f9 100%); }
            .table-head { background: linear-gradient(180deg, #eef5ff, #e8f2ff); }
            .badge { display:inline-flex; align-items:center; padding: .25rem .5rem; border-radius: 999px; font-size:.72rem; font-weight:600; }
            .badge.green { background:#dcfce7; color:#166534; }
            .badge.gray { background:#e5e7eb; color:#374151; }
            .orb { position:absolute; border-radius:50%; filter:blur(40px); opacity:.5; mix-blend-mode:multiply; pointer-events:none; }
            .orb.one { width: 380px; height: 380px; background: linear-gradient(135deg, #0c9ced, #7cccfd); top:-120px; right:-120px; }
            .orb.two { width: 260px; height: 260px; background: linear-gradient(135deg, #bae2fd, #e0effe); bottom:-80px; left:-100px; }

            /* Mobile Responsive Styles */
            @media (max-width: 640px) {
                /* Container and Body */
                .container { padding-left: 0.75rem; padding-right: 0.75rem; }
                body { font-size: 0.7rem; }

                /* Hero Header Compression */
                section.container .premium-gradient { padding: 0.75rem !important; border-radius: 1rem !important; }
                section.container .premium-gradient h1 { font-size: 1.125rem !important; line-height: 1.3; }
                section.container .premium-gradient p { font-size: 0.7rem !important; margin-top: 0.25rem !important; }
                section.container .premium-gradient .w-10 { width: 2rem !important; height: 2rem !important; font-size: 0.875rem; }
                section.container .premium-gradient .gap-3 { gap: 0.5rem !important; }

                /* Orb Size Reduction */
                .orb.one { width: 200px; height: 200px; top: -60px; right: -60px; }
                .orb.two { width: 150px; height: 150px; bottom: -40px; left: -50px; }

                /* Form Card */
                .premium-card { padding: 0.75rem !important; border-radius: 1rem !important; margin-bottom: 0.75rem !important; }

                /* Form Grid - Single Column */
                #registerForm .grid { grid-template-columns: 1fr !important; gap: 0.5rem !important; }
                #registerForm .space-y-5 { gap: 0.5rem !important; }

                /* Form Labels */
                #registerForm label { font-size: 9px !important; margin-bottom: 0.25rem !important; font-weight: 500; }

                /* Form Inputs */
                #registerForm input[type="text"],
                #registerForm input[type="email"],
                #registerForm input[type="password"],
                #registerForm select {
                    font-size: 0.7rem !important;
                    padding: 0.5rem !important;
                    padding-right: 2.5rem !important;
                    border-radius: 0.5rem !important;
                }

                /* Password Toggle Button */
                #registerForm button[type="button"] {
                    right: 0.625rem !important;
                    font-size: 0.7rem !important;
                }

                /* Password Mismatch Message */
                #passwordMismatchMsg { font-size: 9px !important; margin-top: 0.25rem !important; }

                /* Form Buttons - Stack Vertically */
                #registerForm .flex.items-center.justify-end {
                    flex-direction: column !important;
                    gap: 0.5rem !important;
                    padding-top: 0.5rem !important;
                }

                #registerForm button[type="button"],
                #registerForm button[type="submit"] {
                    width: 100% !important;
                    font-size: 0.7rem !important;
                    padding: 0.5rem 0.75rem !important;
                    border-radius: 0.5rem !important;
                }

                /* Table Card Header */
                .premium-card h2 { font-size: 1rem !important; font-weight: 600; }
                .premium-card .flex.items-center.justify-between {
                    flex-direction: column !important;
                    align-items: stretch !important;
                    gap: 0.5rem !important;
                    margin-bottom: 0.75rem !important;
                }

                /* Search Input */
                #official-search {
                    width: 100% !important;
                    max-width: 100% !important;
                    font-size: 0.7rem !important;
                    padding: 0.5rem 2.25rem 0.5rem 0.625rem !important;
                    border-radius: 0.5rem !important;
                }

                #official-search + i {
                    right: 0.625rem !important;
                    font-size: 0.7rem !important;
                }

                /* Table Wrapper - Horizontal Scroll */
                .overflow-x-auto { border-radius: 0.75rem !important; }

                /* Table Compression */
                table { font-size: 0.7rem !important; }
                
                thead th {
                    font-size: 9px !important;
                    padding: 0.5rem 0.625rem !important;
                    white-space: nowrap;
                }

                tbody td {
                    font-size: 0.7rem !important;
                    padding: 0.5rem 0.625rem !important;
                    white-space: nowrap;
                }

                /* Badge Compression */
                .badge {
                    font-size: 9px !important;
                    padding: 0.2rem 0.4rem !important;
                }

                /* Empty State Message */
                tbody td[colspan] {
                    padding: 1rem !important;
                    font-size: 0.7rem !important;
                }

                /* Spacing Reductions */
                .mt-6 { margin-top: 0.75rem !important; }
                .mb-8 { margin-bottom: 0.75rem !important; }
                .py-6 { padding-top: 0.75rem !important; padding-bottom: 0.75rem !important; }
                .mb-4 { margin-bottom: 0.5rem !important; }
                .pt-2 { padding-top: 0.5rem !important; }

                /* Icon Sizes */
                .fas, .far, .fab { font-size: inherit; }
            }

            body {
                background: radial-gradient(circle at 20% 20%, #e0f2ff 0%, #f5f9ff 50%, #ffffff 100%);
                min-height: 100vh;
            }

            .register-container {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 24px;
                box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
                width: 100%;
                max-width: 1200px;
                height: 600px;
                /* Fixed height to match design */
                overflow: hidden;
                border: 1px solid rgba(255, 255, 255, 0.3);
                position: relative;
                animation: none !important;
                opacity: 1 !important;
                transform: none !important;
            }
            
            @media (max-width: 768px) {
                .register-container {
                    height: auto;
                    min-height: 100vh;
                    max-height: none;
                    overflow: visible;
                }
            }
            
            /* Premium container effects */
            .premium-stats-container {
                position: relative;
                border: 1px solid rgba(255, 255, 255, 0.3);
                transform: translateY(0);
                transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
                overflow: hidden;
            }

            .premium-stats-container:hover {
                transform: translateY(-5px) scale(1.03);
                box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            }

            /* Animated gradient background */
            .premium-gradient-bg {
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: linear-gradient(45deg,
                        rgba(59, 130, 246, 0.3),
                        rgba(16, 185, 129, 0.3),
                        rgba(236, 72, 153, 0.3),
                        rgba(139, 92, 246, 0.3)
                    );
                animation: rotate-gradient 8s linear infinite;
                z-index: 0;
            }

            @keyframes rotate-gradient {
                0% {
                    transform: rotate(0deg);
                }
                100% {
                    transform: rotate(360deg);
                }
            }
            
            /* Modal animation styles */
            #legalModalContent {
                transition: opacity 0.3s ease, transform 0.3s ease;
            }
            
            #legalModalContent.scale-95 {
                transform: scale(0.95);
            }
            
            #legalModalContent.scale-100 {
                transform: scale(1);
            }
            
            #legalModalContent.opacity-0 {
                opacity: 0;
            }
            
            #legalModalContent.opacity-100 {
                opacity: 1;
            }

            /* Floating particles effect */
            .premium-particles {
                position: absolute;
                inset: 0;
                z-index: 1;
                overflow: hidden;
            }

            .premium-particles::before,
            .premium-particles::after,
            .premium-particles .particle {
                content: '';
                position: absolute;
                background: white;
                border-radius: 50%;
                opacity: 0.4;
                animation-timing-function: cubic-bezier(0.25, 0.46, 0.45, 0.94);
                animation-iteration-count: infinite;
            }

            .premium-particles::before {
                width: 6px;
                height: 6px;
                top: 20%;
                left: 20%;
                animation: float-particle 4s infinite;
            }

            .premium-particles::after {
                width: 10px;
                height: 10px;
                bottom: 15%;
                right: 30%;
                animation: float-particle 7s infinite 1s;
            }
            
            /* Additional particles */
            .particle-1 {
                width: 5px;
                height: 5px;
                top: 65%;
                left: 75%;
                background-color: rgba(59, 130, 246, 0.5); /* blue */
                animation: float-particle 6s infinite 0.5s;
            }
            
            .particle-2 {
                width: 8px;
                height: 8px;
                top: 30%;
                left: 60%;
                background-color: rgba(16, 185, 129, 0.5); /* green */
                animation: float-particle 8s infinite 2s;
            }
            
            .particle-3 {
                width: 4px;
                height: 4px;
                top: 70%;
                left: 25%;
                background-color: rgba(236, 72, 153, 0.5); /* pink */
                animation: float-particle 5s infinite 1.5s;
            }
            
            .particle-4 {
                width: 7px;
                height: 7px;
                top: 10%;
                left: 85%;
                background-color: rgba(139, 92, 246, 0.5); /* purple */
                animation: float-particle 7s infinite 1s;
            }

            @keyframes float-particle {
                0%, 100% {
                    transform: translateY(0) translateX(0);
                    opacity: 0.2;
                }
                25% {
                    transform: translateY(-15px) translateX(10px);
                    opacity: 0.6;
                }
                50% {
                    transform: translateY(5px) translateX(15px);
                    opacity: 0.4;
                }
                75% {
                    transform: translateY(10px) translateX(-5px);
                    opacity: 0.6;
                }
            }

            .form-container {
                display: grid;
                grid-template-columns: 1.2fr 1fr;
                height: 100%;
                /* Changed from max-height */
            }
            
            @media (max-width: 768px) {
                .form-container {
                    height: auto;
                    min-height: 100vh;
                }
            }

            /* Legal Modal Styles */
            .legal-custom-scrollbar {
                scrollbar-width: thin;
                scrollbar-color: rgba(59, 130, 246, 0.5) rgba(243, 244, 246, 1);
            }

            .legal-custom-scrollbar::-webkit-scrollbar {
                width: 8px;
            }

            .legal-custom-scrollbar::-webkit-scrollbar-track {
                background: rgba(243, 244, 246, 1);
                border-radius: 4px;
            }

            .legal-custom-scrollbar::-webkit-scrollbar-thumb {
                background-color: rgba(59, 130, 246, 0.5);
                border-radius: 4px;
            }

            /* Animation for section transitions */
            .legal-section {
                scroll-margin-top: 140px;
                /* Ensures section headings aren't hidden under the fixed header */
            }

            /* Active tab indicator */
            .legal-tab.active {
                color: #1E40AF;
                border-bottom-color: #1E40AF;
            }

            /* Focus styles for accessibility */
            .focus-visible-ring:focus-visible {
                outline: none;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
            }

            /* Ensure modal has proper height and scroll behavior */
            #legalModalContent {
                display: flex;
                flex-direction: column;
                max-height: 90vh;
            }

            #legalContentScroll {
                -webkit-overflow-scrolling: touch;
            }

            /* Mobile Responsive Styles for Legal Modal */
            @media (max-width: 768px) {
                /* Mobile Modal Container */
                #legalModalContent {
                    width: 95vw !important;
                    max-width: 95vw !important;
                    margin: 1rem;
                    max-height: 90vh;
                }

                /* Mobile Typography - Smaller font sizes */
                #legalModalContent .text-lg {
                    font-size: 0.8rem !important;
                    line-height: 1.4;
                    font-weight: 600;
                }

                #legalModalContent h2 {
                    font-size: 1rem !important;
                    line-height: 1.3;
                }

                #legalModalContent h3 {
                    font-size: 0.9rem !important;
                    line-height: 1.4;
                    margin-bottom: 0.75rem !important;
                }

                #legalModalContent h4 {
                    font-size: 0.85rem !important;
                    line-height: 1.4;
                    font-weight: 500;
                }

                #legalModalContent p {
                    font-size: 0.8rem !important;
                    line-height: 1.6;
                }

                #legalModalContent .text-sm {
                    font-size: 0.75rem !important;
                    line-height: 1.5;
                }

                /* Mobile Header */
                #legalModalContent .bg-gradient-to-r {
                    padding: 1rem !important;
                }

                #legalModalContent .bg-gradient-to-r h2 {
                    font-size: 0.9rem !important;
                }

                #legalModalContent .bg-gradient-to-r .text-xl {
                    font-size: 1rem !important;
                }

                #legalModalContent .bg-gradient-to-r .text-lg {
                    font-size: 0.9rem !important;
                }

                /* Mobile Tab Navigation */
                #legalModalContent .border-b {
                    padding: 0 1rem !important;
                }

                #legalModalContent .legal-tab {
                    padding: 0.75rem 0.5rem !important;
                    font-size: 0.75rem !important;
                    white-space: nowrap;
                }

                #legalModalContent .legal-tab i {
                    font-size: 0.7rem !important;
                    margin-right: 0.25rem !important;
                }

                /* Mobile Content Area */
                #legalModalContent .p-6 {
                    padding: 1rem !important;
                }

                #legalModalContent .legal-section {
                    margin-bottom: 2rem !important;
                }

                #legalModalContent .legal-section h3 {
                    font-size: 0.9rem !important;
                    margin-bottom: 0.75rem !important;
                    padding-bottom: 0.5rem !important;
                }

                #legalModalContent .legal-section h4 {
                    font-size: 0.85rem !important;
                    margin-top: 1rem !important;
                    margin-bottom: 0.5rem !important;
                }

                #legalModalContent .legal-section p {
                    margin-bottom: 0.75rem !important;
                }

                /* Mobile Lists */
                #legalModalContent .list-disc,
                #legalModalContent .list-decimal {
                    padding-left: 1.25rem !important;
                }

                #legalModalContent .list-disc li,
                #legalModalContent .list-decimal li {
                    margin-bottom: 0.5rem !important;
                    font-size: 0.75rem !important;
                    line-height: 1.5;
                }

                /* Mobile Links */
                #legalModalContent a {
                    font-size: 0.75rem !important;
                }

                /* Mobile Footer */
                #legalModalContent .p-4 {
                    padding: 1rem !important;
                }

                #legalModalContent .text-sm {
                    font-size: 0.7rem !important;
                }

                #legalModalContent button {
                    padding: 0.5rem 1rem !important;
                    font-size: 0.8rem !important;
                }

                /* Mobile Scrollbar */
                #legalModalContent::-webkit-scrollbar {
                    width: 6px;
                }

                #legalModalContent::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 3px;
                }

                #legalModalContent::-webkit-scrollbar-thumb {
                    background: #c1c1c1;
                    border-radius: 3px;
                }

                #legalModalContent::-webkit-scrollbar-thumb:hover {
                    background: #a8a8a8;
                }

                /* Mobile Touch Targets */
                #legalModalContent .legal-tab,
                #legalModalContent button {
                    min-height: 44px;
                }
            }

            /* Extra Small Mobile Devices */
            @media (max-width: 480px) {
                #legalModalContent {
                    width: 98vw !important;
                    max-width: 98vw !important;
                    margin: 0.5rem;
                }

                #legalModalContent .text-lg {
                    font-size: 0.75rem !important;
                }

                #legalModalContent h2 {
                    font-size: 0.9rem !important;
                }

                #legalModalContent h3 {
                    font-size: 0.85rem !important;
                }

                #legalModalContent h4 {
                    font-size: 0.8rem !important;
                }

                #legalModalContent p {
                    font-size: 0.75rem !important;
                }

                #legalModalContent .text-sm {
                    font-size: 0.7rem !important;
                }

                #legalModalContent .list-disc li,
                #legalModalContent .list-decimal li {
                    font-size: 0.7rem !important;
                }

                #legalModalContent .bg-gradient-to-r {
                    padding: 0.75rem !important;
                }

                #legalModalContent .p-6 {
                    padding: 0.75rem !important;
                }

                #legalModalContent .legal-tab {
                    padding: 0.625rem 0.375rem !important;
                    font-size: 0.7rem !important;
                }

                #legalModalContent .legal-tab i {
                    font-size: 0.65rem !important;
                }

                #legalModalContent .p-4 {
                    padding: 0.75rem !important;
                }

                #legalModalContent button {
                    font-size: 0.75rem !important;
                    padding: 0.5rem 0.875rem !important;
                }
            }

            /* Landscape Mobile Orientation */
            @media (max-width: 768px) and (orientation: landscape) {
                #legalModalContent {
                    max-height: 85vh;
                }

                #legalModalContent .bg-gradient-to-r {
                    padding: 0.75rem !important;
                }

                #legalModalContent .p-6 {
                    padding: 0.75rem !important;
                }

                #legalModalContent h3 {
                    font-size: 0.85rem !important;
                    margin-bottom: 0.5rem !important;
                }

                #legalModalContent .legal-section {
                    margin-bottom: 1.5rem !important;
                }

                #legalModalContent .legal-section h4 {
                    margin-top: 0.75rem !important;
                }
            }
            
            .register-left {
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.25) 100%);
                padding-top: 0rem;
                display: flex;
                flex-direction: column;
                justify-content: center;
                height: 100%;
                position: center;
                z-index: 1;
            }

            .register-left::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255, 255, 255, 0.05);
                z-index: -1;
            }

            .register-right {
                padding: 2rem;
                background: white;
                overflow-y: auto;
                height: 100%;
                /* Changed from max-height */
                display: flex;
                flex-direction: column;
                position: relative;
                z-index: 1;
            }

            .register-content {
                width: 100%;
                max-width: 500px;
                display: flex;
                flex-direction: column;
                align-items: left;
                justify-content: center;
                text-align: left;
                margin-left:4rem;
            }

            .input-group {
                position: relative;
                margin-bottom: 0.75rem;
                /* Reduced spacing */
            }

            .input-group i {
                position: absolute;
                left: 1rem;
                top: 50%;
                transform: translateY(-50%);
                color: #6B7280;
            }

            .input-field {
                width: 100%;
                padding: 0.75rem 1rem 0.75rem 2.5rem;
                border: 1px solid #E5E7EB;
                border-radius: 12px;
                background: #F9FAFB;
                transition: all 0.3s ease;
                padding-right: 2.5rem;
                /* Make room for the eye icon */
            }

            .input-field:focus {
                border-color: #3B82F6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                outline: none;
            }

            .register-btn {
                background: #2563eb;
                color: #ffffff;
                padding: 0.75rem;
                border-radius: 12px;
                font-weight: 600;
                width: 100%;
                transition: all 0.3s ease;
            }

            .register-btn:hover {
                background: #1d4ed8;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            }

            .input-group button:hover {
                color: #4B5563;
            }

            .input-group button:focus {
                outline: none;
            }

            @media (max-width: 768px) {
                .form-container {
                    grid-template-columns: 1fr;
                }

                .register-left {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: flex-start;
                    padding: 2rem 1rem;
                    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.25) 100%);
                    min-height: 100vh;
                    overflow-y: auto;
                    padding-bottom: 4rem;
                }

                .register-right {
                    display: none;
                }

                .register-container {
                    margin: 1rem;
                }
                
                .text-4xl {
                    font-size: 0.9rem;
                    text-align: center;
                }
                
                .mobile-register-form {
                    width: 100%;
                    max-width: 100%;
                    margin-top: 2rem;
                    padding-bottom: 4rem;
                    height: auto;
                    overflow-y: visible;
                    padding-right: 0.5rem;
                }
                
                .mobile-register-form h3 {
                    font-size: 1rem;
                }
                
                .mobile-register-form .input-field {
                    font-size: 0.7rem;
                    padding: 0.6rem 0.8rem 0.6rem 2.2rem;
                }
                
                .mobile-register-form .text-sm {
                    font-size: 0.7rem;
                }
                
                .mobile-register-form .register-btn {
                    font-size: 0.8rem;
                    font-weight: 600;
                }
                
                /* Mobile scrollbar styling */
                .register-left::-webkit-scrollbar {
                    width: 6px;
                }
                
                .register-left::-webkit-scrollbar-track {
                    background: rgba(235, 245, 255, 0.3);
                    border-radius: 3px;
                }
                
                .register-left::-webkit-scrollbar-thumb {
                    background: rgba(37, 99, 235, 0.4);
                    border-radius: 3px;
                }
                
                .register-left::-webkit-scrollbar-thumb:hover {
                    background: rgba(37, 99, 235, 0.6);
                }
                
                /* Firefox scrollbar */
                .register-left {
                    scrollbar-width: thin;
                    scrollbar-color: rgba(37, 99, 235, 0.4) rgba(235, 245, 255, 0.3);
                }

                .text-gray-600 {
                    font-size: 0.7rem;
                }

                .bg-blue-600 {
                    font-size: 0.7rem;
                }

                .register-content {
                width: 100%;
                max-width: 500px;
                display: flex;
                flex-direction: column;
                align-items: left;
                justify-content: center;
                text-align: left;
                margin-top:1 rem;
                margin-left:1rem;
            }
            }

            /* Add this new style for form layout */
            #registerForm {
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }

            .flex.flex-col.gap-2 {
                gap: 0.5rem;
            }

            /* Update the scrollbar styles */
            .register-right::-webkit-scrollbar {
                width: 8px;
            }

            .register-right::-webkit-scrollbar-track {
                background: rgba(235, 245, 255, 0.5);
                /* Lowered track opacity */
                border-radius: 4px;
            }

            .register-right::-webkit-scrollbar-thumb {
                background: rgba(37, 99, 235, 0.6);
                /* Lowered thumb opacity */
                border-radius: 4px;
                transition: all 0.3s ease;
            }

            .register-right::-webkit-scrollbar-thumb:hover {
                background: rgba(29, 78, 216, 0.8);
                /* Slightly more opaque on hover */
            }

            /* For Firefox */
            .register-right {
                scrollbar-width: thin;
                scrollbar-color: rgba(37, 99, 235, 0.6) rgba(235, 245, 255, 0.5);
            }
            
            /* Password field and toggle styling */
            input[type="password"].input-field {
                padding-right: 3rem;
            }
            
            /* Password toggle button positioning */
            .input-group button.password-toggle {
                position: absolute;
                right: 1.2rem;
                top: 50%;
                transform: translateY(-50%);
                background: transparent;
                border: none;
                color: #9CA3AF;
                cursor: pointer;
                z-index: 2;
                padding: 0;
                width: 1.5rem;
                height: 1.5rem;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            
            /* Password toggle icon styling */
            .password-toggle {
                cursor: pointer;
                transition: color 0.2s ease;
                z-index: 10;
            }
            
            .password-toggle:hover {
                color: #4B5563;
            }
            
            @media (max-width: 768px) {
                .input-group button.password-toggle {
                    right: 1.2rem; /* Slightly closer to the edge for mobile */
                    top: 50%;
                    transform: translateY(-50%);
                    width: 1.3rem;
                    height: 1.3rem;
                }
            }

            /* Small screens: push the password toggle to the utmost right edge of the input */
            @media (max-width: 640px) {
                .input-group button.password-toggle {
                    right: 0.5rem !important;
                }
            }
            
            /* Password toggle icon styling */
            .password-toggle {
                cursor: pointer;
                transition: color 0.2s ease;
                z-index: 10;
            }
            
            .password-toggle:hover {
                color: #4B5563;
            }

            /* Add transition classes */
            .fade-enter {
                opacity: 0;
                transform: translateY(20px);
            }

            .fade-enter-active {
                opacity: 1;
                transform: translateY(0);
                transition: opacity 0.6s ease, transform 0.6s ease;
            }

            /* Smooth transitions for form elements */
            .input-field,
            .register-btn {
                transition: all 0.3s ease;
            }

            /* Button hover effect */
            .register-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
            }

            /* Form appear animation wrapper */
            .form-appear {
                opacity: 0;
                transform: translateY(20px);
                animation: formAppear 0.6s ease forwards 0.3s;
            }

            @keyframes formAppear {
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .form-checkbox {
                appearance: none;
                -webkit-appearance: none;
                border: 1px solid #cbd5e0;
                border-radius: 4px;
                padding: 8px;
                display: inline-block;
                position: relative;
                vertical-align: middle;
                cursor: pointer;
                margin-right: 8px;
            }

            .form-checkbox:checked {
                background-color: #2563eb;
                border-color: #2563eb;
            }

            .form-checkbox:checked:after {
                content: '';
                display: block;
                position: absolute;
                left: 50%;
                top: 50%;
                width: 4px;
                height: 8px;
                border: solid white;
                border-width: 0 2px 2px 0;
                transform: translate(-50%, -60%) rotate(45deg);
            }

            /* Update the form animation wrapper to include checkbox and button animations */
            .flex.flex-col.gap-3.mb-4,
            .w-full.bg-blue-600.text-white {
                opacity: 0;
                transform: translateY(20px);
                animation: formElementAppear 0.6s ease forwards;
            }

            /* Add different delays for checkboxes and button */
            .flex.flex-col.gap-3.mb-4 {
                animation-delay: 0.4s;
            }

            .w-full.bg-blue-600.text-white {
                animation-delay: 0.5s;
            }

            @keyframes formElementAppear {
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Add smooth transitions */
            .form-checkbox,
            button[type="submit"] {
                transition: all 0.3s ease;
            }

            /* Slide-in animation for new elements */
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Apply the slide-in animation to specific elements */
            .animate-slide-in {
                animation: slideIn 0.6s ease-out forwards;
            }

            /* Fade-in animation for mobile register */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .mobile-fade-in {
                animation: fadeInUp 0.8s ease-out forwards;
            }

            .mobile-fade-in-delay-1 {
                animation: fadeInUp 0.8s ease-out 0.1s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-2 {
                animation: fadeInUp 0.8s ease-out 0.2s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-3 {
                animation: fadeInUp 0.8s ease-out 0.3s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-4 {
                animation: fadeInUp 0.8s ease-out 0.4s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-5 {
                animation: fadeInUp 0.8s ease-out 0.5s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-6 {
                animation: fadeInUp 0.8s ease-out 0.6s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-7 {
                animation: fadeInUp 0.8s ease-out 0.7s forwards;
                opacity: 0;
            }

            .mobile-fade-in-delay-8 {
                animation: fadeInUp 0.8s ease-out 0.8s forwards;
                opacity: 0;
            }
    </style>
</head>

<body class="font-sans">
    <?php include_once('../includes/barangay_official_sec_nav.php'); ?>

    <!-- Hero Header -->
    <section class="container mx-auto px-4 mt-6">
        <div class="premium-gradient rounded-2xl p-6 text-white relative overflow-hidden">
            <div class="orb one"></div>
            <div class="orb two"></div>
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center shadow"><i class="fas fa-id-card"></i></div>
                    <h1 class="text-2xl md:text-3xl font-bold">Add Official Account</h1>
                </div>
                <!-- hero header note -->
                <p class="text-blue-100 mt-1">Create an official user account using the premium layout.</p>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-6">
        <!-- Form Card -->
        <div class="premium-card rounded-2xl p-6 mb-8">
            <form id="registerForm" method="POST" action="../controllers/create_officialdb.php" class="space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                        <input type="text" name="reg_fname" id="reg_fname" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Juan" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                        <input type="text" name="reg_mname" id="reg_mname" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Santos" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="reg_lname" id="reg_lname" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Dela Cruz" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-0">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Birthdate</label>
                        <input type="date" name="reg_birthdate" id="reg_birthdate" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-white focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="Birthdate" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Age</label>
                        <input type="text" name="reg_age" id="reg_age" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-100 text-gray-700" placeholder="Age" readonly />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="reg_email" id="reg_email" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="user@example.com" required />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                        <input type="text" name="reg_contact" id="reg_contact" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="09XXXXXXXXX" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type of Official</label>
                        <select name="reg_type" id="reg_type" class="w-full px-3 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" required>
                            <option value="" disabled selected>Type of Official</option>
                            <option value="Lupon Tagapamayapa">Lupon Tagapamayapa</option>
                            <option value="Lupon-Hepe">Lupon-Hepe</option>
                            <option value="Secretary">Secretary</option>
                            <option value="Barangay Captain">Barangay Captain</option>
                        </select>
                    </div>
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="reg_pass" id="reg_pass" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="••••••••" required />
                        <button type="button" class="absolute top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 password-toggle" onclick="togglePassword('reg_pass', this)" aria-label="Show password"><i class="fas fa-eye"></i></button>
                    </div>
                    <div class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="reg_pass_confirm" id="reg_pass_confirm" class="w-full px-3 py-2 pr-10 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" placeholder="••••••••" required />
                        <button type="button" class="absolute top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 password-toggle" onclick="togglePassword('reg_pass_confirm', this)" aria-label="Show password"><i class="fas fa-eye"></i></button>
                        <p id="passwordMismatchMsg" class="text-xs text-red-600 mt-1 hidden">Passwords do not match.</p>
                    </div>
                </div>

               

                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="cancelBtn" class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700"><i class="fas fa-xmark mr-2"></i>Cancel</button>
                    <button id="submitBtn" type="submit" name="Signup" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white font-semibold disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        <i class="fas fa-user-plus mr-2"></i>Create Account
                    </button>
                </div>
            </form>
        </div>

        <!-- Table Card -->
        <div class="premium-card rounded-2xl p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-semibold text-gray-900">Official Accounts</h2>
                <div class="relative">
                    <input id="official-search" type="text" placeholder="Search name, email, contact, or position..." class="w-64 max-w-full pl-3 pr-10 py-2 rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-300" />
                    <i class="fas fa-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" aria-hidden="true"></i>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse overflow-hidden rounded-xl">
                    <thead class="table-head">
                        <tr>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">ID</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Name</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Email</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Contact</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Position</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Status</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider p-3">Actions</th>

                        </tr>
                    </thead>
                    <tbody id="officials-body" class="divide-y divide-gray-100">
                        <?php if (!empty($officials)): ?>
                            <?php foreach ($officials as $o): 
                                $name = trim($o['Name'] ?? '');
                                $search = strtolower(trim(($o['official_username'] ?? '') . ' ' . $name . ' ' . ($o['email'] ?? '') . ' ' . ($o['Contact_Number'] ?? '') . ' ' . ($o['Position'] ?? '')));
                                $active = (int)($o['isActive'] ?? 0) === 1;
                            ?>
                            <tr class="odd:bg-white even:bg-blue-50/30 hover:bg-primary-50 transition-colors cursor-pointer" data-search-text="<?= htmlspecialchars($search) ?>" onclick="location.href='view_account.php?type=official&id=<?= (int)$o['Official_ID'] ?>'">
                                    <td class="p-3 text-gray-800"><?= (int)$o['Official_ID'] ?></td>
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($name) ?></td>
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($o['email'] ?? '') ?></td>
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($o['Contact_Number'] ?? '') ?></td>
                                    <td class="p-3 text-gray-800"><?= htmlspecialchars($o['Position'] ?? '') ?></td>
                                    <td class="p-3">
                                    <?php if ($active): ?>
                                        <span class="badge green">Active</span>
                                    <?php else: ?>
                                        <span class="badge gray">Inactive</span>
                                    <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-gray-800">
                                        <a href="view_account.php?type=official&id=<?= (int)$o['Official_ID'] ?>" title="View account" class="inline-flex items-center justify-center w-8 h-8 rounded-md text-primary-700 hover:bg-primary-50" onclick="event.stopPropagation();">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="p-6 text-center text-gray-500">No official accounts found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include 'sidebar_.php';?>
    <script>
        // Replace the existing handleRegister function
        function handleRegister(event) {
            event.preventDefault();

            const form = document.getElementById('registerForm');
            const messageDiv = document.getElementById('registerMessage');
            const formData = new FormData(form);

            fetch('register.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    messageDiv.classList.remove('hidden');
                    if (data.success) {
                        messageDiv.className = 'text-center p-4 text-green-600 bg-green-50 border border-green-200 rounded-lg';
                        messageDiv.textContent = 'Registration successful! Redirecting to login...';

                        // Redirect to login page after 2 seconds
                        setTimeout(() => {
                            window.location.href = 'login.php?registered=1';
                        }, 2000);
                    } else {
                        messageDiv.className = 'text-center p-4 text-red-600 bg-red-50 border border-red-200 rounded-lg';
                        messageDiv.textContent = data.message || 'Registration failed. Please try again.';
                    }
                })
            // .catch(error => {
            //     console.error('Error:', error);
            //     messageDiv.classList.remove('hidden');
            //     messageDiv.className = 'text-center p-4 text-red-600 bg-red-50 border border-red-200 rounded-lg';
            //     messageDiv.textContent = 'An error occurred. Please try again.';
            // });

            messageDiv.className = 'text-center p-4 text-green-600 bg-green-50 border border-green-200 rounded-lg';
            messageDiv.textContent = 'Registration successful! Redirecting to login...';

            // Redirect to login page after 2 seconds
            setTimeout(() => {
                window.location.href = 'login.php?registered=1';
            }, 2000);

        }

        // Add this function before the closing 
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        //para sa confirm password
         const password = document.getElementById('reg_pass');
         const confirmPassword = document.getElementById('reg_pass_confirm');
         const mismatchMsg = document.getElementById('passwordMismatchMsg');
         const submitBtn = document.getElementById('submitBtn');

            function validatePasswords() {
                const hasBoth = password.value.length > 0 && confirmPassword.value.length > 0;
                const matches = password.value === confirmPassword.value;
                if (hasBoth && !matches) {
                    mismatchMsg.classList.remove('hidden');
                    submitBtn.disabled = true;
                } else {
                    mismatchMsg.classList.add('hidden');
                    submitBtn.disabled = !(hasBoth && matches);
                }
            }

           password.addEventListener('input', validatePasswords);
           confirmPassword.addEventListener('input', validatePasswords);
            validatePasswords();

            // Birthdate / Age handling for official form
            const offBirth = document.getElementById('reg_birthdate');
            const offAge = document.getElementById('reg_age');
            function computeAgeFromDateString(datestr) {
                if (!datestr) return null;
                const parts = datestr.split('-');
                if (parts.length !== 3) return null;
                const y = parseInt(parts[0], 10);
                const m = parseInt(parts[1], 10) - 1;
                const d = parseInt(parts[2], 10);
                const bd = new Date(y, m, d);
                if (isNaN(bd.getTime())) return null;
                const today = new Date();
                let age = today.getFullYear() - bd.getFullYear();
                const mDiff = today.getMonth() - bd.getMonth();
                if (mDiff < 0 || (mDiff === 0 && today.getDate() < bd.getDate())) {
                    age--;
                }
                return age;
            }
            if (offBirth) {
                const today = new Date();
                today.setDate(today.getDate() - 1);
                const y = today.getFullYear();
                const m = String(today.getMonth() + 1).padStart(2, '0');
                const d = String(today.getDate()).padStart(2, '0');
                offBirth.setAttribute('max', `${y}-${m}-${d}`);
                offBirth.addEventListener('change', () => {
                    const age = computeAgeFromDateString(offBirth.value);
                    offAge.value = (age !== null && !isNaN(age)) ? age : '';
                });
                offBirth.addEventListener('input', () => {
                    const age = computeAgeFromDateString(offBirth.value);
                    offAge.value = (age !== null && !isNaN(age)) ? age : '';
                });
            }

            // Cancel: reset form and validation state
            const cancelBtn = document.getElementById('cancelBtn');
            cancelBtn?.addEventListener('click', () => {
                const form = document.getElementById('registerForm');
                form?.reset();
                mismatchMsg?.classList?.add('hidden');
                submitBtn.disabled = true;
            });

            // Client-side table search for officials
            const officialSearch = document.getElementById('official-search');
            function updateOfficialFilter() {
                const q = (officialSearch?.value || '').toLowerCase().trim();
                document.querySelectorAll('#officials-body [data-search-text]')?.forEach(row => {
                    const txt = (row.getAttribute('data-search-text') || '').toLowerCase();
                    row.style.display = txt.includes(q) ? '' : 'none';
                });
            }
            officialSearch?.addEventListener('input', updateOfficialFilter);
            updateOfficialFilter();

    </script>
</body>

</html>