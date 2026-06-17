
<?php
// --- CAPTCHA VERIFICATION FUNCTION ---
function verify_captcha($captcha_response)
{
    $secret = "6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe";
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
    $house_no = trim($_POST['reg_house_no']);
    $purok = trim($_POST['reg_purok']);
    $address = "$house_no, $purok, Barangay Panducot Calumpit Bulacan";
    $email = trim($_POST['reg_email']);
    // $type = $_POST['reg_type'];
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
        // 'reg_type' => $type,
        'reg_terms' => $terms,
        'reg_privacy' => $privacy
    ];


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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPAMIS Register</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles/auth.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="styles/premium-animations.css">
    <link rel="stylesheet" href="styles/premium-patterns.css">
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />

    <style>
        body {
            background: #e8f0fe;
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
                    rgba(139, 92, 246, 0.3));
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
            background-color: rgba(59, 130, 246, 0.5);
            /* blue */
            animation: float-particle 6s infinite 0.5s;
        }

        .particle-2 {
            width: 8px;
            height: 8px;
            top: 30%;
            left: 60%;
            background-color: rgba(16, 185, 129, 0.5);
            /* green */
            animation: float-particle 8s infinite 2s;
        }

        .particle-3 {
            width: 4px;
            height: 4px;
            top: 70%;
            left: 25%;
            background-color: rgba(236, 72, 153, 0.5);
            /* pink */
            animation: float-particle 5s infinite 1.5s;
        }

        .particle-4 {
            width: 7px;
            height: 7px;
            top: 10%;
            left: 85%;
            background-color: rgba(139, 92, 246, 0.5);
            /* purple */
            animation: float-particle 7s infinite 1s;
        }

        @keyframes float-particle {

            0%,
            100% {
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

            /* Make rotating gradient background much larger on mobile
               so rotation is barely noticeable */
            .premium-gradient-bg {
                width: 380%;
                height: 380%;
                top: -140%;
                left: -140%;
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

            /* Force mobile icon sizes across register + modal views */
            .mobile-register-form i,
            .register-left i,
            .register-right i,
            .input-group i {
                font-size: 0.7rem !important;
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

            /* Further increase on extra-small screens for subtle rotation */
            .premium-gradient-bg {
                width: 450%;
                height: 450%;
                top: -175%;
                left: -175%;
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
                /* Keep modal tab icons at 0.7rem on extra-small devices */
                font-size: 0.7rem !important;
            }

            /* Ensure other mobile icons are also 0.7rem on extra-small devices */
            .mobile-register-form i,
            .register-left i,
            .register-right i,
            .input-group i {
                font-size: 0.7rem !important;
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

        /* Valid ID Info Modal - Mobile Typography */
        @media (max-width: 768px) {
            #validIdInfoModal .text-base,
            #validIdInfoModal .text-sm,
            #validIdInfoModal p,
            #validIdInfoModal li,
            #validIdInfoModal a,
            #validIdInfoModal h3,
            #validIdInfoModal h4 {
                font-size: 0.7rem !important;
                line-height: 1.5;
            }
        }

        @media (max-width: 480px) {
            #validIdInfoModal .text-base,
            #validIdInfoModal .text-sm,
            #validIdInfoModal p,
            #validIdInfoModal li,
            #validIdInfoModal a,
            #validIdInfoModal h3,
            #validIdInfoModal h4 {
                font-size: 0.7rem !important;
            }
        }

        .register-left {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.25) 100%);
            padding-top: 3rem;
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
            margin-left: 4rem;
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

            /* Tighter spacing between fields on mobile */
            .mobile-register-form .grid.gap-4 {
                gap: 0.3rem !important;
            }
            .mobile-register-form .input-group {
                margin-bottom: 0.3rem !important;
            }
            /* Override Tailwind space-y-4 vertical spacing inside the form */
            .mobile-register-form form.space-y-4 > :not([hidden]) ~ :not([hidden]) {
                margin-top: 0.3rem !important;
            }
            /* Reduce spacing in checkbox stack */
            .mobile-register-form .flex.flex-col.gap-3 {
                gap: 0.3rem !important;
            }
            .mobile-register-form .mb-4 {
                margin-bottom: 0.3rem !important;
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
                margin-top: 1 rem;
                margin-left: 1rem;
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
            padding-right: 3.6rem;
        }

        /* Password toggle button positioning */
        .input-group button.password-toggle {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: #9CA3AF;
            cursor: pointer;
            z-index: 10;
            padding: 0.45rem;
            width: 2.6rem;
            height: 2.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
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
                right: 0.7rem;
                top: 50%;
                transform: translateY(-50%);
                width: 2.2rem;
                height: 2.2rem;
                padding: 0.3rem;
            }
            .input-group button.password-toggle i {
                font-size: 0.95rem;
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

<body class="bg-gray-50 font-sans">
    <!--  include_once('includes/bpamis_nav.php'); -->
    <div class="register-bg flex flex-col min-h-screen flex-1 flex items-center justify-center py-8 px-4">
        <div class="register-container premium-glass premium-stats-container">
            <!-- Animated gradient background -->
            <div class="premium-gradient-bg"></div>

            <!-- Glass morphism overlay -->
            <div class="absolute inset-0 bg-white/70 backdrop-blur-lg"></div>

            <!-- Floating particles effect -->
            <div class="premium-particles">
                <div class="particle particle-1"></div>
                <div class="particle particle-2"></div>
                <div class="particle particle-3"></div>
                <div class="particle particle-4"></div>
            </div>

            <div class="form-container relative z-10" style="height: 100%">
                <div class="register-left premium-left-pattern">
                    <div class="register-content">
                        <div class="flex justify-center w-full mb-8">
                            <a href="bpamis.php"><img src="assets/images/logo.png" alt="BPAMIS Logo"
                                    class="w-20 h-20 object-contain"></a>
                        </div>
                        <h2 class="text-4xl font-bold text-gray-800 mb-4 leading-tight">
                            Join our digital community — BPAMIS <br>brings adjudication to <br>your fingertips.
                        </h2>
                        <p class="text-gray-600 md:block hidden">Experience a streamlined barangay management system</p>
                    </div>

                    <!-- Mobile Register Form -->
                    <div
                        class="mobile-register-form md:hidden w-full max-w-sm mt-8 bg-white rounded-lg p-4 shadow-lg border border-gray-200 ">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center mobile-fade-in-delay-1">Get Started
                        </h3>
                        <p class="mb-6 text-gray-600 text-center mobile-fade-in-delay-1">Already have an account? <a
                                href="login.php" class="text-blue-600 font-medium">Log In</a></p>
                        <div class="text-center mt-2">
                        </div>

                        <form id="registerForm" method="POST" action="../controllers/registerdb.php" class="space-y-4"
                            enctype="multipart/form-data">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="input-group mobile-fade-in-delay-2">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="reg_fname" id="reg_fname" class="input-field"
                                        placeholder="First Name" required>
                                </div>
                                <div class="input-group mobile-fade-in-delay-3">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="reg_mname" id="reg_mname" class="input-field"
                                        placeholder="Middle Name">
                                </div>
                            </div>

                            <div class="input-group mobile-fade-in-delay-4">
                                <i class="fas fa-user"></i>
                                <input type="text" name="reg_lname" id="reg_lname" class="input-field"
                                    placeholder="Last Name" required>
                            </div>

                                <div class="grid grid-cols-2 gap-4 mobile-fade-in-delay-5">
                                    <div class="input-group">
                                        <i class="fas fa-cake-candles"></i>
                                        <input type="date" name="reg_birthdate" id="reg_birthdate" class="input-field"
                                            placeholder="Birthdate" required>
                                    </div>
                                    <div class="input-group">
                                        <i class="fas fa-child"></i>
                                        <input type="text" name="reg_age" id="reg_age" class="input-field bg-gray-100 cursor-not-allowed" readonly
                                            placeholder="Age" aria-readonly="true">
                                    </div>
                                </div>
                                <div id="birthdateErrorMobile" class="hidden mt-2 text-sm text-red-600"></div>

                            <div class="input-group mobile-fade-in-delay-6">
                                <i class="fas fa-home"></i>
                                <input type="text" name="reg_house_no" id="reg_house_no" class="input-field"
                                    placeholder="House Number" required>
                            </div>

                            <div class="input-group mobile-fade-in-delay-7">
                                <i class="fas fa-road"></i>
                                <select name="reg_purok" id="reg_purok" class="input-field" required>
                                    <option value="" disabled selected hidden>Select Purok</option>
                                    <option value="Purok 1">Purok 1</option>
                                    <option value="Purok 2">Purok 2</option>
                                    <option value="Purok 3">Purok 3</option>
                                    <option value="Purok 4">Purok 4</option>
                                    <option value="Purok 5">Purok 5</option>
                                </select>
                            </div>

                            <div class="input-group mobile-fade-in-delay-8">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" name="reg_address" id="reg_address" class="input-field bg-gray-100 cursor-not-allowed"
                                    value="Barangay Panducot Calumpit Bulacan" readonly>
                            </div>

                            <div class="input-group mobile-fade-in-delay-9">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="reg_email" id="reg_email" class="input-field"
                                    placeholder="Email Address" required>
                            </div>

                            <!-- Valid ID (mobile) -->
                            <div class="mt-2 mobile-fade-in-delay-8">
                                <div class="flex items-center justify-between">
                                    <label class="block text-sm font-medium text-gray-700">Valid ID <span
                                            class="text-xs text-gray-400">(JPG, PNG max 1MB)</span></label>
                                    <button type="button" class="text-blue-600 hover:text-blue-700 focus:outline-none"
                                        title="Valid ID Info" aria-label="Valid ID Info"
                                        onclick="openValidIdInfoModal()">
                                        <i class="fas fa-circle-info text-base"></i>
                                    </button>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <label for="valid_id_mobile"
                                        class="flex items-center gap-3 w-full h-12 bg-white rounded-md border border-dashed border-gray-200 px-4 cursor-pointer hover:border-blue-300 transition">
                                        <i class="fa fa-id-card-clip text-gray-400 text-2xl"></i>
                                        <span id="validIdLabelMobile" class="text-sm text-gray-600">Click to upload a
                                            single ID file</span>
                                    </label>
                                    <input id="valid_id_mobile" type="file" name="valid_id"
                                        class="hidden valid-id-input" accept="image/jpeg,image/png" required
                                        aria-required="true" />
                                </div>
                                <div id="validIdErrorMobile" class="hidden mt-2 text-sm text-red-600"></div>
                                <div id="validIdPreviewMobile" class="hidden mt-2"></div>
                            </div>

                            <div class="input-group relative mobile-fade-in-delay-7">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="reg_pass_mobile" id="reg_pass_mobile" class="input-field"
                                    placeholder="Password" required>
                                <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('reg_pass_mobile', this)"
                                    aria-label="Toggle password visibility">
                                    <i class="fas fa-eye text-gray-400"></i>
                                </button>
                            </div>

                            <div class="input-group relative mobile-fade-in-delay-8">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="reg_pass_confirm_mobile" id="reg_pass_confirm_mobile"
                                    class="input-field" placeholder="Confirm Password" required>
                                <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('reg_pass_confirm_mobile', this)"
                                    aria-label="Toggle password visibility">
                                    <i class="fas fa-eye text-gray-400"></i>
                                </button>
                            </div>
                            <div class="flex flex-col gap-3 mb-4 animate-slide-in">
                                <label class="flex items-start gap-2 cursor-pointer mobile-fade-in-delay-7">
                                    <input type="checkbox" name="reg_terms"
                                        class="mt-1 form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out"
                                        required>
                                    <span class="text-sm text-gray-600">
                                        I agree to the <a href="javascript:void(0)" id="termsLink"
                                            class="text-blue-600 hover:text-blue-700 underline">Terms and Conditions</a>
                                </label>

                                <label class="flex items-start gap-2 cursor-pointer mobile-fade-in-delay-7">
                                    <input type="checkbox" name="reg_privacy"
                                        class="mt-1 form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out"
                                        required>
                                    <span class="text-sm text-gray-600">
                                        I accept the <a href="javascript:void(0)" id="privacyLink"
                                            class="text-blue-600 hover:text-blue-700 underline">Privacy Policy</a>
                                    </span>
                                </label>
                            </div>

                            <button type="submit"
                                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-all duration-300 font-medium animate-slide-in"
                                name="Signup">
                                Create Account
                            </button>
                        </form>

                        <div id="registerMessageMobile" class="hidden mt-4 text-center"></div>
                    </div>
                </div>

                <div class="register-right form-appear premium-staggered premium-pattern">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2 premium-fade-in">Get Started</h3>
                    <p class="mb-6 text-gray-600 premium-fade-in">Already have an account? <a href="login.php"
                            class="text-blue-600 font-medium toggle-form">Log In</a></p>

                    <!-- Desktop Register Form -->
                    <form id="registerForm" method="POST" action="../controllers/registerdb.php" class="space-y-4"
                        enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="reg_fname" id="reg_fname" class="input-field"
                                    placeholder="First Name" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="reg_mname" id="reg_mname" class="input-field"
                                    placeholder="Middle Name">
                            </div>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="reg_lname" id="reg_lname" class="input-field"
                                placeholder="Last Name" required>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-2 gap-4">
                            <div class="input-group">
                                <i class="fas fa-cake-candles"></i>
                                <input type="date" name="reg_birthdate" id="reg_birthdate_desktop" class="input-field"
                                    placeholder="Birthdate" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-child"></i>
                                <input type="text" name="reg_age" id="reg_age_desktop" class="input-field bg-gray-100 cursor-not-allowed"
                                    placeholder="Age" readonly aria-readonly="true">
                            </div>
                        </div>
                        <div id="birthdateError" class="hidden mt-2 text-sm text-red-600"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="input-group">
                                <i class="fas fa-home"></i>
                                <input type="text" name="reg_house_no" id="reg_house_no" class="input-field"
                                    placeholder="House Number" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-road"></i>
                                <select name="reg_purok" id="reg_purok" class="input-field" required>
                                    <option value="" disabled selected hidden>Select Purok</option>
                                    <option value="Purok 1">Purok 1</option>
                                    <option value="Purok 2">Purok 2</option>
                                    <option value="Purok 3">Purok 3</option>
                                    <option value="Purok 4">Purok 4</option>
                                    <option value="Purok 5">Purok 5</option>
                                </select>
                            </div>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" class="input-field bg-gray-100 cursor-not-allowed"
                                value="Barangay Panducot Calumpit Bulacan" readonly disabled>
                        </div>


                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="reg_email" id="reg_email" class="input-field"
                                placeholder="Email Address" required>
                        </div>

                        <div class="mt-2 premium-fade-in">
                            <div class="flex items-center justify-between ">
                                <label class="block text-sm font-medium text-gray-700">Valid ID <span
                                        class="text-xs text-gray-400">(JPG, PNG; max 1MB)</span></label>
                                <button type="button" class="text-blue-600 hover:text-blue-700 focus:outline-none"
                                    title="Valid ID Info" aria-label="Valid ID Info"
                                    onclick="openValidIdInfoModal()">
                                    <i class="fas fa-circle-info"></i>
                                </button>
                            </div>
                            <div class="mt-2 flex items-center gap-3 ">
                                <label for="valid_id"
                                    class="flex items-center gap-3 w-full h-12 bg-white rounded-md border border-dashed border-gray-200 px-4 cursor-pointer hover:border-blue-300 transition">
                                    <i class="fa fa-id-card-clip text-gray-400 text-2xl"></i>
                                    <span id="validIdLabel" class="text-sm text-gray-600">Click to upload a single ID
                                        file</span>
                                </label>
                                <input id="valid_id" type="file" name="valid_id" class="hidden valid-id-input"
                                    accept="image/jpeg,image/png" required aria-required="true" />
                            </div>
                            <div id="validIdError" class="hidden mt-2 text-sm text-red-600"></div>
                            <div id="validIdPreview" class="hidden mt-2"></div>
                        </div>

                        <div class="input-group relative premium-fade-in">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="reg_pass" id="reg_pass" class="input-field premium-input"
                                placeholder="Password" required>
                            <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('reg_pass', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>

                        <div class="input-group relative premium-fade-in">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="reg_pass_confirm" id="reg_pass_confirm"
                                class="input-field premium-input" placeholder="Confirm Password" required>
                            <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('reg_pass_confirm', this)"
                                aria-label="Toggle password visibility">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>

                        <div class="flex flex-col gap-3 mb-4 animate-slide-in">
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" name="reg_terms"
                                    class="mt-1 form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out"
                                    required>
                                <span class="text-sm text-gray-600">
                                    I agree to the
                                    <a href="javascript:void(0)" id="termsLinkDesktop"
                                       class="text-blue-600 hover:text-blue-700 underline"
                                       onclick="openLegalModal(event, 'terms-of-service')">Terms and Conditions</a>
                                </span>
                            </label>

                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" name="reg_privacy"
                                    class="mt-1 form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out"
                                    required>
                                <span class="text-sm text-gray-600">
                                    I accept the
                                    <a href="javascript:void(0)" id="privacyLinkDesktop"
                                       class="text-blue-600 hover:text-blue-700 underline"
                                       onclick="openLegalModal(event, 'privacy-policy')">Privacy Policy</a>
                                </span>
                            </label>
                        </div>

                        <button type="submit"
                            class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-all duration-300 font-medium animate-slide-in"
                            name="Signup">
                            Create Account
                        </button>
                    </form>

                    <br>
                    <div id="registerMessage" class="hidden mt-4 text-center"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal Modal -->
    <div id="legalModal" class="fixed inset-0 hidden z-50 overflow-hidden" aria-labelledby="legal-title" role="dialog"
        aria-modal="true">
        <!-- Background overlay with blur -->
        <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-[4px]" aria-hidden="true"
            onclick="closeLegalModal()"></div>

        <!-- Modal container - centered with max-height -->
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <!-- Modal panel with responsive sizing -->
            <div id="legalModalContent"
                class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl mx-auto flex flex-col transform transition-all ease-out duration-300 scale-95 opacity-0"
                tabindex="-1" style="max-height: 90vh; display: flex; flex-direction: column;">
                <!-- Modal header (fixed) -->
                <div
                    class="bg-gradient-to-r from-blue-800 to-blue-600 p-4 flex justify-between items-center sticky top-0 z-10 rounded-t-lg">
                    <div class="flex items-center">
                        <div class="flex-shrink-0 bg-white p-2 rounded-full">
                            <i class="fas fa-gavel text-blue-600 text-xl"></i>
                        </div>
                        <h2 id="legal-title" class="ml-3 text-lg font-bold text-white">Legal Information</h2>
                    </div>
                    <button
                        class="text-white bg-blue-700 bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 focus:outline-none focus:ring-2 focus:ring-white"
                        onclick="closeLegalModal()" aria-label="Close">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>

                <!-- Tab navigation (sticky) -->
                <div class="border-b border-gray-200 bg-white sticky top-[65px] z-[5] px-4">
                    <nav class="flex flex-wrap space-x-2 md:space-x-8" aria-label="Legal sections">
                        <button onclick="scrollToLegalSection('terms-of-service')"
                            class="legal-tab py-4 px-1 text-sm font-medium border-b-2 border-transparent hover:border-blue-300 hover:text-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md">
                            <i class="fas fa-file-contract mr-1"></i> Terms of Service
                        </button>
                        <button onclick="scrollToLegalSection('privacy-policy')"
                            class="legal-tab py-4 px-1 text-sm font-medium border-b-2 border-transparent hover:border-blue-300 hover:text-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md">
                            <i class="fas fa-user-shield mr-1"></i> Privacy Policy
                        </button>
                        <button onclick="scrollToLegalSection('cookies-policy')"
                            class="legal-tab py-4 px-1 text-sm font-medium border-b-2 border-transparent hover:border-blue-300 hover:text-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md">
                            <i class="fas fa-cookie-bite mr-1"></i> Cookies Policy
                        </button>
                        <button onclick="scrollToLegalSection('accessibility')"
                            class="legal-tab py-4 px-1 text-sm font-medium border-b-2 border-transparent hover:border-blue-300 hover:text-blue-700 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-md">
                            <i class="fas fa-universal-access mr-1"></i> Accessibility
                        </button>
                    </nav>
                </div>

                <!-- Modal body (scrollable) -->
                <div class="overflow-y-auto flex-grow legal-custom-scrollbar p-6" id="legalContentScroll"
                    style="max-height: calc(90vh - 140px); overflow-y: auto;">
                    <!-- Terms of Service Section -->
                    <section id="terms-of-service" class="legal-section mb-10">
                        <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Terms of
                            Service
                        </h3>

                        <div class="space-y-4 text-gray-700">
                            <p>Last Updated: July 1, 2025</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">1. Agreement to Terms</h4>
                            <p>By accessing or using the Barangay Panducot Adjudication Management Information System
                                (BPAMIS), you agree to be bound by these Terms of Service and all applicable laws and
                                regulations. If you do not agree with any of these terms, you are prohibited from using
                                or
                                accessing this system.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">2. Use License</h4>
                            <p>Permission is granted to temporarily access the materials within BPAMIS for personal,
                                non-commercial viewing only. This is the grant of a license, not a transfer of title,
                                and
                                under this license you may not:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Modify or copy the materials</li>
                                <li>Use the materials for any commercial purpose</li>
                                <li>Attempt to decompile or reverse engineer any software contained in BPAMIS</li>
                                <li>Remove any copyright or other proprietary notations from the materials</li>
                                <li>Transfer the materials to another person or "mirror" the materials on any other
                                    server
                                </li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">3. Disclaimer</h4>
                            <p>The materials on BPAMIS are provided on an 'as is' basis. The Barangay Panducot makes no
                                warranties, expressed or implied, and hereby disclaims and negates all other warranties
                                including, without limitation, implied warranties or conditions of merchantability,
                                fitness
                                for a particular purpose, or non-infringement of intellectual property or other
                                violation of
                                rights.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">4. Limitations</h4>
                            <p>In no event shall the Barangay Panducot or its suppliers be liable for any damages
                                (including, without limitation, damages for loss of data or profit, or due to business
                                interruption) arising out of the use or inability to use BPAMIS, even if the Barangay
                                Panducot or a Barangay Panducot authorized representative has been notified orally or in
                                writing of the possibility of such damage.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">5. Accuracy of Materials</h4>
                            <p>The materials appearing on BPAMIS could include technical, typographical, or photographic
                                errors. The Barangay Panducot does not warrant that any of the materials on BPAMIS are
                                accurate, complete, or current. The Barangay Panducot may make changes to the materials
                                contained on BPAMIS at any time without notice.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">6. Links</h4>
                            <p>The Barangay Panducot has not reviewed all of the sites linked to its system and is not
                                responsible for the contents of any such linked site. The inclusion of any link does not
                                imply endorsement by the Barangay Panducot of the site. Use of any such linked website
                                is at
                                the user's own risk.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">7. Modifications</h4>
                            <p>The Barangay Panducot may revise these Terms of Service for BPAMIS at any time without
                                notice. By using this system, you are agreeing to be bound by the then current version
                                of
                                these Terms of Service.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">8. Governing Law</h4>
                            <p>These terms and conditions are governed by and construed in accordance with the laws of
                                the
                                Philippines and you irrevocably submit to the exclusive jurisdiction of the courts in
                                that
                                location.</p>
                        </div>
                    </section>

                    <!-- Privacy Policy Section -->
                    <section id="privacy-policy" class="legal-section mb-10">
                        <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Privacy
                            Policy
                        </h3>

                        <div class="space-y-4 text-gray-700">
                            <p>Last Updated: July 1, 2025</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">1. Information We Collect</h4>
                            <p>BPAMIS collects several types of information from and about users of our system,
                                including:
                            </p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Personal identifiable information such as name, postal address, email address,
                                    telephone
                                    number, and government-issued IDs when you register for an account</li>
                                <li>Information about your internet connection, the equipment you use to access our
                                    system,
                                    and usage details</li>
                                <li>Records and copies of your correspondence if you contact us</li>
                                <li>Details of transactions you carry out through our system and of the fulfillment of
                                    your
                                    requests</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">2. How We Use Your Information</h4>
                            <p>We use information that we collect about you or that you provide to us, including any
                                personal information:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>To present our system and its contents to you</li>
                                <li>To provide you with information, products, or services that you request from us</li>
                                <li>To fulfill any other purpose for which you provide it</li>
                                <li>To carry out our obligations and enforce our rights arising from any contracts
                                    entered
                                    into between you and us</li>
                                <li>To notify you about changes to our system or any products or services we offer or
                                    provide</li>
                                <li>To allow you to participate in interactive features on our system</li>
                                <li>In any other way we may describe when you provide the information</li>
                                <li>For any other purpose with your consent</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">3. Disclosure of Your Information</h4>
                            <p>We may disclose personal information that we collect or you provide as described in this
                                privacy policy:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>To our subsidiaries and affiliates</li>
                                <li>To contractors, service providers, and other third parties we use to support our
                                    operations</li>
                                <li>To fulfill the purpose for which you provide it</li>
                                <li>For any other purpose disclosed by us when you provide the information</li>
                                <li>With your consent</li>
                                <li>To comply with any court order, law, or legal process, including to respond to any
                                    government or regulatory request</li>
                                <li>To enforce or apply our terms of use and other agreements</li>
                                <li>If we believe disclosure is necessary or appropriate to protect the rights,
                                    property, or
                                    safety of the Barangay Panducot, our users, or others</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">4. Data Security</h4>
                            <p>We have implemented measures designed to secure your personal information from accidental
                                loss and from unauthorized access, use, alteration, and disclosure. All information you
                                provide to us is stored on secure servers behind firewalls. Any sensitive information
                                will
                                be encrypted using Secure Socket Layer (SSL) technology.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">5. Data Retention</h4>
                            <p>We will only retain your personal data for as long as necessary to fulfill the purposes
                                we
                                collected it for, including for the purposes of satisfying any legal, accounting, or
                                reporting requirements.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">6. Your Rights</h4>
                            <p>Under the Data Privacy Act of 2012, you have rights concerning your personal data,
                                including:
                            </p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>The right to be informed</li>
                                <li>The right to access</li>
                                <li>The right to object</li>
                                <li>The right to erasure or blocking</li>
                                <li>The right to damages</li>
                                <li>The right to file a complaint</li>
                                <li>The right to rectify</li>
                                <li>The right to data portability</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">7. Changes to Our Privacy Policy</h4>
                            <p>We may update our privacy policy from time to time. If we make material changes to how we
                                treat our users' personal information, we will notify you through a notice on the BPAMIS
                                homepage. The date the privacy policy was last revised is identified at the top of the
                                page.
                            </p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">8. Contact Information</h4>
                            <p>To ask questions or comment about this privacy policy and our privacy practices, contact
                                us
                                at: info@bpamis.gov.ph</p>
                        </div>
                    </section>

                    <!-- Cookies Policy Section -->
                    <section id="cookies-policy" class="legal-section mb-10">
                        <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Cookies
                            Policy
                        </h3>

                        <div class="space-y-4 text-gray-700">
                            <p>Last Updated: July 1, 2025</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">1. What Are Cookies</h4>
                            <p>Cookies are small pieces of text sent by your web browser by a website you visit. A
                                cookie
                                file is stored in your web browser and allows the system or a third-party to recognize
                                you
                                and make your next visit easier and the system more useful to you.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">2. How BPAMIS Uses Cookies</h4>
                            <p>When you use and access BPAMIS, we may place a number of cookie files in your web
                                browser. We
                                use cookies for the following purposes:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>To enable certain functions of the system</li>
                                <li>To provide analytics</li>
                                <li>To store your preferences</li>
                                <li>To enable authentication and security</li>
                            </ul>
                            <p>We use both session and persistent cookies on the system and we use different types of
                                cookies to run the system:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Essential cookies: These are cookies that are required for the operation of BPAMIS.
                                    They
                                    include, for example, cookies that enable you to log into secure areas of our
                                    system.
                                </li>
                                <li>Analytical/performance cookies: They allow us to recognize and count the number of
                                    visitors and to see how visitors move around BPAMIS when they are using it. This
                                    helps
                                    us to improve the way our system works, for example, by ensuring that users are
                                    finding
                                    what they are looking for easily.</li>
                                <li>Functionality cookies: These are used to recognize you when you return to BPAMIS.
                                    This
                                    enables us to personalize our content for you and remember your preferences.</li>
                                <li>Targeting cookies: These cookies record your visit to BPAMIS, the pages you have
                                    visited
                                    and the links you have followed. We will use this information to make our system
                                    more
                                    relevant to your interests.</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">3. Third-Party Cookies</h4>
                            <p>In addition to our own cookies, we may also use various third-party cookies to report
                                usage
                                statistics of the system and so on.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">4. What Are Your Choices Regarding
                                Cookies
                            </h4>
                            <p>If you'd like to delete cookies or instruct your web browser to delete or refuse cookies,
                                please visit the help pages of your web browser.</p>
                            <p>Please note, however, that if you delete cookies or refuse to accept them, you might not
                                be
                                able to use all of the features we offer, you may not be able to store your preferences,
                                and
                                some of our pages might not display properly.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">5. Where Can You Find More Information
                                About
                                Cookies</h4>
                            <p>You can learn more about cookies at the following third-party websites:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>AllAboutCookies: <a href="https://www.allaboutcookies.org/"
                                        class="text-blue-600 hover:underline">https://www.allaboutcookies.org/</a></li>
                                <li>Network Advertising Initiative: <a href="https://www.networkadvertising.org/"
                                        class="text-blue-600 hover:underline">https://www.networkadvertising.org/</a>
                                </li>
                            </ul>
                        </div>
                    </section>

                    <!-- Accessibility Section -->
                    <section id="accessibility" class="legal-section mb-10">
                        <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Accessibility
                            Statement</h3>

                        <div class="space-y-4 text-gray-700">
                            <p>Last Updated: July 1, 2025</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">1. Commitment to Accessibility</h4>
                            <p>BPAMIS is committed to ensuring digital accessibility for people with disabilities. We
                                are
                                continually improving the user experience for everyone, and applying the relevant
                                accessibility standards.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">2. Conformance Status</h4>
                            <p>The Web Content Accessibility Guidelines (WCAG) defines requirements for designers and
                                developers to improve accessibility for people with disabilities. It defines three
                                levels of
                                conformance: Level A, Level AA, and Level AAA. BPAMIS is partially conformant with WCAG
                                2.1
                                level AA. Partially conformant means that some parts of the content do not fully conform
                                to
                                the accessibility standard.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">3. Accessibility Features</h4>
                            <p>BPAMIS includes the following accessibility features:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Keyboard accessibility for all interactive elements</li>
                                <li>Text alternatives for non-text content</li>
                                <li>Clear headings and labels</li>
                                <li>Consistent navigation</li>
                                <li>Color contrast that meets WCAG 2.1 AA standards</li>
                                <li>Resizable text without loss of content or functionality</li>
                                <li>Focus indicators for keyboard navigation</li>
                                <li>ARIA landmarks for screen readers</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">4. Assistive Technology Compatibility
                            </h4>
                            <p>BPAMIS is designed to be compatible with the following assistive technologies:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Screen readers (NVDA, JAWS, VoiceOver)</li>
                                <li>Screen magnifiers</li>
                                <li>Speech recognition software</li>
                                <li>Keyboard-only navigation</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">5. Known Limitations</h4>
                            <p>Despite our efforts to ensure accessibility of BPAMIS, there may be some limitations.
                                Below
                                is a description of known limitations:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Some older PDF documents are not fully accessible to screen reader software</li>
                                <li>Some data visualizations may not include adequate text descriptions</li>
                                <li>Some third-party content may not be fully accessible</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">6. Feedback</h4>
                            <p>We welcome your feedback on the accessibility of BPAMIS. Please let us know if you
                                encounter
                                accessibility barriers:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Email: accessibility@bpamis.gov.ph</li>
                                <li>Phone: +63 (xxx) xxx-xxxx</li>
                            </ul>
                            <p>We try to respond to feedback within 3 business days.</p>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">7. Assessment Approach</h4>
                            <p>Barangay Panducot assessed the accessibility of BPAMIS by the following approaches:</p>
                            <ul class="list-disc pl-5 space-y-1 mt-2">
                                <li>Self-evaluation</li>
                                <li>External evaluation with assistive technology users</li>
                                <li>Automated testing tools</li>
                            </ul>

                            <h4 class="text-lg font-medium text-blue-700 mt-6">8. Formal Approval</h4>
                            <p>This accessibility statement was prepared on July 1, 2025 and was last reviewed on July
                                1,
                                2025.</p>
                        </div>
                    </section>
                </div>

                <!-- Modal footer (fixed) -->
                <div
                    class="p-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center sticky bottom-0 z-10 rounded-b-lg">
                    <p class="text-sm text-gray-600">© <?php echo date('Y'); ?> BPAMIS. All Rights Reserved.</p>
                    <button type="button"
                        class="inline-flex justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        onclick="closeLegalModal()">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Valid ID Info Modal -->
    <div id="validIdInfoModal" class="fixed inset-0 hidden z-50" role="dialog" aria-modal="true" aria-labelledby="validIdInfoTitle">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50" onclick="closeValidIdInfoModal()" aria-hidden="true"></div>
        <!-- Panel -->
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl mx-auto transform transition-all ease-out duration-300 p-0 overflow-hidden flex flex-col h-[90vh] md:max-h-[85vh]">
                <!-- Header -->
                <div class="bg-blue-600 text-white px-4 py-3 flex items-center justify-between flex-shrink-0">
                    <h3 id="validIdInfoTitle" class="text-base font-semibold">Valid ID Information</h3>
                    <button class="p-2 rounded hover:bg-blue-700/30 focus:outline-none focus:ring-2 focus:ring-white" aria-label="Close"
                        onclick="closeValidIdInfoModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <!-- Body -->
                <div class="overflow-y-auto flex-grow legal-custom-scrollbar p-6" id="validIdInfoContentScroll"
                    style="max-height: calc(90vh - 140px); overflow-y: auto;">
                    <div>
                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Accepted IDs</h4>
                        <ul class="list-disc pl-5 text-sm text-gray-700 space-y-1">
                            <li>PhilSys National ID (Philippine Identification System)</li>
                            <li>COMELEC Voter's ID</li>
                        </ul>
                        <p class="text-xs text-gray-500 mt-2">Upload a clear image showing your full name and key details. Max file size: 1MB. Accepted formats: JPG or PNG.</p>
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Data Privacy Notice</h4>
                        <p class="text-sm text-gray-700">Your identification document may contain personal and sensitive personal information. By submitting your ID, you acknowledge and consent to its processing solely for identity verification in connection with BPAMIS services, consistent with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations, particularly:</p>
                        <ul class="list-disc pl-5 text-sm text-gray-700 mt-2 space-y-1">
                            <li>Section 11 – General Data Privacy Principles (transparency, legitimate purpose, and proportionality)</li>
                            <li>Section 12 – Criteria for Lawful Processing of Personal Information</li>
                            <li>Applicable provisions on sensitive personal information and security measures under the IRR</li>
                        </ul>
                        <p class="text-xs text-gray-500 mt-2">Your data is handled with appropriate organizational, physical, and technical safeguards and retained only as long as necessary for the stated purpose and legal obligations.</p>
                    </div>

                    <div class="border-t pt-4">
                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Don't have an ID yet?</h4>
                        <ul class="list-disc pl-5 text-sm text-blue-700 space-y-2">
                            <li>
                                Get your National ID:
                                <a href="https://national-id.gov.ph/" target="_blank" rel="noopener noreferrer" class="underline hover:text-blue-900">https://national-id.gov.ph/</a>
                            </li>
                            <li>
                                COMELEC Voter's ID and Registration Info:
                                <div class="pl-4 space-y-1">
                                    <a href="https://comelec.gov.ph/?r=VoterRegistration/WhatisVoterRegistration/VoterIDIssuance" target="_blank" rel="noopener noreferrer" class="underline hover:text-blue-900">Voter's ID Issuance</a><br>
                                    <a href="https://comelec.gov.ph/?r=VoterRegistration/WhatisVoterRegistration/qnavr" target="_blank" rel="noopener noreferrer" class="underline hover:text-blue-900">Q&A on Voter Registration</a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <!-- Footer -->
                <div class="p-4 bg-gray-50 border-t border-gray-200 flex justify-end flex-shrink-0">
                    <button type="button" class="inline-flex justify-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        onclick="closeValidIdInfoModal()">Close</button>
                </div>
            </div>
        </div>
    </div>


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

        // Birthdate / Age handling
        const birthdateMobile = document.getElementById('reg_birthdate');
        const ageMobile = document.getElementById('reg_age');
        const birthdateDesktop = document.getElementById('reg_birthdate_desktop');
        const ageDesktop = document.getElementById('reg_age_desktop');
        const birthdateError = document.getElementById('birthdateError');
        const birthdateErrorMobile = document.getElementById('birthdateErrorMobile');

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
            // age in years
            let age = today.getFullYear() - bd.getFullYear();
            const mDiff = today.getMonth() - bd.getMonth();
            if (mDiff < 0 || (mDiff === 0 && today.getDate() < bd.getDate())) {
                age--;
            }
            return age;
        }

        function updateAgeInputs(sourceBirthdateEl) {
            const val = sourceBirthdateEl && sourceBirthdateEl.value ? sourceBirthdateEl.value : '';
            const age = computeAgeFromDateString(val);
            if (age !== null && !isNaN(age)) {
                if (ageMobile) ageMobile.value = age;
                if (ageDesktop) ageDesktop.value = age;
            } else {
                if (ageMobile) ageMobile.value = '';
                if (ageDesktop) ageDesktop.value = '';
            }
        }

        function validateBirthdateField(birthEl, errEl) {
            if (!birthEl) return true; // nothing to validate
            const val = birthEl.value;
            // If empty, do not show an error message yet (disable will still prevent submit)
            if (!val) {
                if (errEl) { errEl.textContent = ''; errEl.classList.add('hidden'); }
                return false;
            }
            const age = computeAgeFromDateString(val);
            const today = new Date();
            const bd = new Date(val + 'T00:00:00');
            // disallow today or future
            if (bd >= new Date(today.getFullYear(), today.getMonth(), today.getDate())) {
                if (errEl) { errEl.textContent = 'Invalid birthdate.'; errEl.classList.remove('hidden'); }
                return false;
            }
            if (age === null || age === undefined || isNaN(age)) {
                if (errEl) { errEl.textContent = 'Invalid birthdate.'; errEl.classList.remove('hidden'); }
                return false;
            }
            if (age <= 7) {
                if (errEl) { errEl.textContent = 'Invalid birthdate.'; errEl.classList.remove('hidden'); }
                return false;
            }
            if (errEl) { errEl.textContent = ''; errEl.classList.add('hidden'); }
            return true;
        }

        // Attach listeners
        [birthdateMobile, birthdateDesktop].forEach(el => {
            if (!el) return;
            // Set max to yesterday to help UX
            const today = new Date();
            today.setDate(today.getDate() - 1);
            const y = today.getFullYear();
            const m = String(today.getMonth() + 1).padStart(2, '0');
            const d = String(today.getDate()).padStart(2, '0');
            el.setAttribute('max', `${y}-${m}-${d}`);
            el.addEventListener('change', function () {
                updateAgeInputs(el);
                // validate and re-run overall validation
                validateAll();
            });
            el.addEventListener('input', function () {
                updateAgeInputs(el);
                validateAll();
            });
        });

        //para sa confirm password
        const password = document.getElementById('reg_pass');
        const confirmPassword = document.getElementById('reg_pass_confirm');
        const mismatchMsg = document.getElementById('passwordMismatchMsg');
        // Collect all submit buttons (desktop + mobile) sharing name="Signup"
        const submitButtons = Array.from(document.querySelectorAll('button[name="Signup"]'));
        // Mobile fields (may not exist on desktop view)
        const passwordMobile = document.getElementById('reg_pass_mobile');
        const confirmPasswordMobile = document.getElementById('reg_pass_confirm_mobile');

        // Ensure mismatch message elements exist (desktop)
        let desktopMismatch = mismatchMsg;
        if (!desktopMismatch && confirmPassword) {
            desktopMismatch = document.createElement('div');
            desktopMismatch.id = 'passwordMismatchMsg';
            // Place after input-group to avoid shifting icons
            const container = confirmPassword.parentElement;
            container.parentElement.insertBefore(desktopMismatch, container.nextSibling);
        }
        if (desktopMismatch) {
            desktopMismatch.className = 'mt-2 hidden';
        }

        // Mobile mismatch message element
        let mobileMismatch = document.getElementById('passwordMismatchMsgMobile');
        if (!mobileMismatch && confirmPasswordMobile) {
            mobileMismatch = document.createElement('div');
            mobileMismatch.id = 'passwordMismatchMsgMobile';
            const mContainer = confirmPasswordMobile.parentElement;
            mContainer.parentElement.insertBefore(mobileMismatch, mContainer.nextSibling);
        }
        if (mobileMismatch) {
            mobileMismatch.className = 'mt-2 hidden';
        }

        function renderAlert(msgEl, message, type) {
            if (!msgEl) return;
            const isError = type === 'error';
            const animationClass = isError ? '' : 'animate-slide-in fade-enter-active';
            msgEl.innerHTML = `
                                                            <div class="flex items-start gap-2 px-3 py-2 rounded-lg border shadow-sm text-sm ${animationClass}
                                                                ${isError ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700'}">
                                                                 <span class="mt-0.5 text-base">
                                                                        <i class="fas ${isError ? 'fa-exclamation-circle text-red-500' : 'fa-check-circle text-green-500'}"></i>
                                                                 </span>
                                                                 <span>${message}</span>
                                                            </div>`;
            msgEl.classList.remove('hidden');
            msgEl.setAttribute('role', 'alert');
            msgEl.setAttribute('aria-live', 'polite');
        }

        function setInvalid(el, msgEl, message) {
            if (!el || !msgEl) return;
            el.classList.add('border-red-500', 'bg-red-50');
            el.classList.remove('border-green-500', 'bg-green-50');
            renderAlert(msgEl, message, 'error');
        }

        function clearState(el, msgEl) {
            if (!el || !msgEl) return;
            el.classList.remove('border-red-500', 'bg-red-50', 'border-green-500', 'bg-green-50');
            msgEl.innerHTML = '';
            msgEl.classList.add('hidden');
            msgEl.removeAttribute('role');
            msgEl.removeAttribute('aria-live');
        }

        function setValid(el, msgEl) {
            if (!el || !msgEl) return;
            el.classList.remove('border-red-500', 'bg-red-50');
            el.classList.add('border-green-500', 'bg-green-50');
            renderAlert(msgEl, 'Passwords match', 'success');
        }

        function validatePair(primary, confirm, msgEl) {
            if (!confirm || !msgEl) return false;
            if (!primary.value && !confirm.value) {
                clearState(confirm, msgEl);
                return false;
            }
            if (!confirm.value) {
                clearState(confirm, msgEl);
                return false;
            }
            if (primary.value !== confirm.value) {
                setInvalid(confirm, msgEl, 'Passwords do not match');
                return false;
            } else {
                setValid(confirm, msgEl);
                return true;
            }
        }

        function validateAll() {
            const desktopOk = validatePair(password, confirmPassword, desktopMismatch);
            const mobileOk = validatePair(passwordMobile, confirmPasswordMobile, mobileMismatch);

            // For each submit button, evaluate only the fields within its own (closest) form
            submitButtons.forEach(btn => {
                if (!btn) return;
                const form = btn.closest('form');
                if (!form) return;

                // Gather required inputs inside this form
                const requiredInputs = Array.from(form.querySelectorAll('input[required]'));
                // Separate text/email/password vs checkboxes
                const textInputsEmpty = requiredInputs.filter(i => i.type !== 'checkbox').some(i => !i.value.trim());
                const uncheckedBoxes = requiredInputs.filter(i => i.type === 'checkbox').some(i => !i.checked);

                // Find password pair inside form (either desktop or mobile naming)
                const p = form.querySelector('input[name="reg_pass"], input[name="reg_pass_mobile"]');
                const c = form.querySelector('input[name="reg_pass_confirm"], input[name="reg_pass_confirm_mobile"]');
                let passwordsOk = true;
                if (p && c) {
                    if (!p.value || !c.value || p.value !== c.value) {
                        passwordsOk = false;
                    }
                }

                // Validate birthdate inside this form (if present)
                const birthEl = form.querySelector('input[name="reg_birthdate"]');
                // choose appropriate error element
                const errEl = (birthEl === birthdateMobile) ? birthdateErrorMobile : birthdateError;
                const birthOk = validateBirthdateField(birthEl, errEl);

                // If this is the desktop form, use desktopOk for mismatch styling logic else mobileOk
                // (Already applied above via validatePair)

                const disable = textInputsEmpty || uncheckedBoxes || !passwordsOk || !birthOk;
                btn.disabled = disable;
                btn.classList.toggle('opacity-50', disable);
                btn.classList.toggle('cursor-not-allowed', disable);
            });
        }

        [password, confirmPassword, passwordMobile, confirmPasswordMobile].forEach(el => {
            if (!el) return;
            el.addEventListener('input', validateAll);
        });

        // Also watch required text & checkbox inputs for enabling/disabling logic
        const allRequired = Array.from(document.querySelectorAll('form input[required]'));
        allRequired.forEach(inp => inp.addEventListener('input', validateAll));
        allRequired.forEach(inp => inp.addEventListener('change', validateAll));

        validateAll();



        // Store focusable elements and last active element
        let legalFocusableElements = [];
        let legalFirstFocusableElement = null;
        let legalLastFocusableElement = null;

        // Open modal with specific section
        function openLegalModal(event, section = null) {
            if (event) {
                event.preventDefault();
            }

            const modal = document.getElementById('legalModal');
            const modalContent = document.getElementById('legalModalContent');

            // Store last active element to restore focus later
            window.lastLegalActiveElement = document.activeElement;

            // Display modal
            modal.classList.remove('hidden');

            // Trigger reflow for animation
            void modalContent.offsetWidth;

            // Show with animation
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');

            // Prevent body scrolling
            document.body.classList.add('overflow-hidden');

            // Set focus and establish focus trap
            setTimeout(() => {
                modalContent.focus();

                // Get all focusable elements
                legalFocusableElements = modalContent.querySelectorAll(
                    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                if (legalFocusableElements.length > 0) {
                    legalFirstFocusableElement = legalFocusableElements[0];
                    legalLastFocusableElement = legalFocusableElements[legalFocusableElements.length - 1];
                    legalFirstFocusableElement.focus();
                }

                // If a specific section is requested, scroll to it after a short delay to ensure DOM is ready
                if (section) {
                    setTimeout(() => {
                        scrollToLegalSection(section, true);
                    }, 100);
                }
            }, 100);
        }

        // Close modal
        function closeLegalModal() {
            const modal = document.getElementById('legalModal');
            const modalContent = document.getElementById('legalModalContent');

            // Hide with animation
            modalContent.classList.remove('scale-100', 'opacity-100');
            modalContent.classList.add('scale-95', 'opacity-0');

            setTimeout(() => {
                modal.classList.add('hidden');

                // Re-enable body scrolling
                document.body.classList.remove('overflow-hidden');

                // Restore focus
                if (window.lastLegalActiveElement) {
                    window.lastLegalActiveElement.focus();
                }

                // Reset active tab
                resetActiveTabs();
            }, 300);
        }

        // Scroll to a specific section and highlight its tab
        function scrollToLegalSection(sectionId, updateActiveTab = true) {
            const section = document.getElementById(sectionId);
            const scrollContainer = document.getElementById('legalContentScroll');

            if (section && scrollContainer) {
                // Calculate the scroll position (accounting for the sticky header)
                const headerHeight = 140; // Combined height of the header and tab navigation
                const sectionPosition = section.offsetTop - headerHeight;

                // Scroll to section with smooth behavior
                scrollContainer.scrollTo({
                    top: sectionPosition,
                    behavior: 'smooth'
                });

                // Update active tab
                if (updateActiveTab) {
                    resetActiveTabs();

                    // Find and activate the correct tab
                    const tabs = document.querySelectorAll('.legal-tab');
                    tabs.forEach(tab => {
                        if (tab.textContent.toLowerCase().includes(sectionId.replace(/-/g, ' '))) {
                            tab.classList.add('active');
                            tab.setAttribute('aria-current', 'page');
                        }
                    });
                }
            }
        }

        // Reset all tabs to inactive state
        function resetActiveTabs() {
            const tabs = document.querySelectorAll('.legal-tab');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                tab.setAttribute('aria-current', 'false');
            });
        }

        // Document ready event listener
        document.addEventListener('DOMContentLoaded', function () {
            // Hook Terms & Privacy links (desktop + mobile) to open modal
            const termSelectors = ["a#termsLink", "a[href='#'][class*='Terms']", "a[href='javascript:void(0)']#termsLink"]; // primary explicit selector
            const privacySelectors = ["a#privacyLink", "a[href='#'][class*='Privacy']", "a[href='javascript:void(0)']#privacyLink"]; // primary explicit selector

            function bindModalLink(selectors, sectionId) {
                selectors.forEach(sel => {
                    document.querySelectorAll(sel).forEach(el => {
                        el.addEventListener('click', function (e) {
                            e.preventDefault();
                            openLegalModal(e, sectionId);
                        });
                    });
                });
            }

            bindModalLink(["#termsLink"], 'terms-of-service');
            bindModalLink(["#privacyLink"], 'privacy-policy');

            // If links exist inside the desktop form without IDs (fallback), attempt text-based binding
            document.querySelectorAll('a').forEach(a => {
                const text = (a.textContent || '').toLowerCase();
                if (text.includes('terms and conditions') && !a.id) {
                    a.addEventListener('click', e => { e.preventDefault(); openLegalModal(e, 'terms-of-service'); });
                }
                if (text.includes('privacy policy') && !a.id) {
                    a.addEventListener('click', e => { e.preventDefault(); openLegalModal(e, 'privacy-policy'); });
                }
            });

            if (document.getElementById('legalModal')) {
                // Add click listener on the backdrop
                const backdrop = document.querySelector('#legalModal .fixed.inset-0.bg-black');
                if (backdrop) {
                    backdrop.addEventListener('click', function () {
                        closeLegalModal();
                    });
                }

                // Initialize tabs
                const tabs = document.querySelectorAll('.legal-tab');
                if (tabs.length > 0) {
                    tabs[0].classList.add('active');
                    tabs[0].setAttribute('aria-current', 'page');
                }

                // Handle keyboard navigation
                document.addEventListener('keydown', function (event) {
                    // Only process if modal is open
                    if (document.getElementById('legalModal').classList.contains('hidden')) {
                        return;
                    }

                    // Close modal when pressing Escape key
                    if (event.key === 'Escape') {
                        closeLegalModal();
                        return;
                    }

                    // Trap focus inside modal when Tab key is pressed
                    if (event.key === 'Tab') {
                        // If shift key is also pressed and focus is on first element, move to last element
                        if (event.shiftKey && document.activeElement === legalFirstFocusableElement) {
                            event.preventDefault();
                            legalLastFocusableElement.focus();
                        }
                        // If focus is on last element, move to first element
                        else if (!event.shiftKey && document.activeElement === legalLastFocusableElement) {
                            event.preventDefault();
                            legalFirstFocusableElement.focus();
                        }
                    }
                });

                // Implement scroll spy for tab highlighting
                const legalContentScroll = document.getElementById('legalContentScroll');
                if (legalContentScroll) {
                    legalContentScroll.addEventListener('scroll', function () {
                        const sections = document.querySelectorAll('.legal-section');
                        const scrollPosition = legalContentScroll.scrollTop + 150; // Add offset for header

                        let currentSection = '';

                        // Find the section that is currently most visible in the viewport
                        sections.forEach(section => {
                            const sectionTop = section.offsetTop;
                            const sectionHeight = section.offsetHeight;

                            if (scrollPosition >= sectionTop &&
                                scrollPosition <= (sectionTop + sectionHeight)) {
                                currentSection = section.getAttribute('id');
                            }
                        });

                        if (currentSection) {
                            resetActiveTabs();

                            // Find and activate the correct tab
                            const tabs = document.querySelectorAll('.legal-tab');
                            tabs.forEach(tab => {
                                if (tab.textContent.toLowerCase().includes(currentSection.replace(/-/g, ' '))) {
                                    tab.classList.add('active');
                                    tab.setAttribute('aria-current', 'page');
                                }
                            });
                        }
                    });
                }
            }
        });

    </script>

    <script>
        // Valid ID Info Modal controls
        function openValidIdInfoModal() {
            const m = document.getElementById('validIdInfoModal');
            if (!m) return;
            m.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeValidIdInfoModal() {
            const m = document.getElementById('validIdInfoModal');
            if (!m) return;
            m.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Close on Escape when modal is open
        document.addEventListener('keydown', (e) => {
            const m = document.getElementById('validIdInfoModal');
            if (!m || m.classList.contains('hidden')) return;
            if (e.key === 'Escape') {
                closeValidIdInfoModal();
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            const MAX_ID_BYTES = 1 * 1024 * 1024; // 1MB
            const allowed = ['image/jpeg', 'image/png'];

            function setupValidId(inputId, labelId, errId, previewId) {
                const input = document.getElementById(inputId);
                const label = document.getElementById(labelId);
                const err = document.getElementById(errId);
                const preview = document.getElementById(previewId);
                let objectUrl = null;

                if (!input) return;
                // Only add manual click handler if the label does NOT already target the input via 'for'
                const lbl = document.querySelector('label[for="' + inputId + '"]');
                if (lbl && (!lbl.hasAttribute('for') || lbl.getAttribute('for') !== inputId)) {
                    lbl.addEventListener('click', () => input.click());
                }

                input.addEventListener('change', () => {
                    if (err) err.classList.add('hidden'); if (err) err.textContent = '';
                    if (preview) preview.innerHTML = ''; if (preview) preview.classList.add('hidden');
                    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }

                    if (!input.files || !input.files.length) {
                        if (label) label.textContent = 'Click to upload a single ID file';
                        return;
                    }
                    const file = input.files[0];
                    if (file.size > MAX_ID_BYTES) {
                        if (err) err.textContent = 'Selected file exceeds 1MB.';
                        if (err) err.classList.remove('hidden');
                        input.value = '';
                        if (label) label.textContent = 'Click to upload a single ID file';
                        return;
                    }

                    // Determine MIME-type: prefer file.type, otherwise infer from extension
                    let type = file.type || '';
                    if (!type && file.name) {
                        const ext = file.name.split('.').pop().toLowerCase();
                        if (ext === 'jpg' || ext === 'jpeg') type = 'image/jpeg';
                        else if (ext === 'png') type = 'image/png';
                        else type = '';
                    }

                    if (!allowed.includes(type)) {
                        if (err) err.textContent = 'Invalid file type. Allowed: JPG, JPEG, PNG.';
                        if (err) err.classList.remove('hidden');
                        input.value = '';
                        if (label) label.textContent = 'Click to upload a single ID file';
                        return;
                    }

                    if (label) label.textContent = file.name;
                    if (type.startsWith('image/')) {
                        objectUrl = URL.createObjectURL(file);
                        if (preview) preview.innerHTML = `<img src="${objectUrl}" alt="${file.name}" class="w-24 h-24 object-cover rounded-md border" />`;
                        if (preview) preview.classList.remove('hidden');
                    } else {
                        // Shouldn't occur as we only allow images, but fallback to filename
                        if (preview) preview.innerHTML = `<div class="inline-flex items-center gap-3 p-3 rounded-md border bg-white"><span class="text-sm">${file.name}</span></div>`;
                        if (preview) preview.classList.remove('hidden');
                    }
                });
            }
            // initialize for both mobile and desktop inputs if present
            setupValidId('valid_id', 'validIdLabel', 'validIdError', 'validIdPreview');
            setupValidId('valid_id_mobile', 'validIdLabelMobile', 'validIdErrorMobile', 'validIdPreviewMobile');
        });
    </script>
</body>

</html>