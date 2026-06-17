<?php
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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add External User</title>
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

         
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #e8f0fe;
            min-height: 100vh;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            width: 100%;
            max-width: 1200px;
            height: 600px;
            /* Fixed height to match design */
            overflow: hidden;
        }

        .form-container {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            height: 100%;
            /* Changed from max-height */
        }

        .register-left {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.2) 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .register-right {
            padding: 2rem;
            background: white;
            overflow-y: auto;
            height: 100%;
            /* Changed from max-height */
            display: flex;
            flex-direction: column;
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
                display: none;
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
    </style>
</head>

<body class="bg-gray-50 font-sans">
    <?php include_once('../includes/barangay_official_cap_nav.php'); ?>
    <div class="register-bg flex flex-col min-h-screen flex-1 flex items-center justify-center py-8 px-4">
        <div class="register-container">
            <div class="form-container">
                <div class="register-left">
                    <img src="logo.png" alt="BPAMIS Logo" class="w-20 h-20 mb-8">
                    <h2 class="text-4xl font-bold text-gray-800 mb-4 leading-tight">
                        Join our digital community — BPAMIS <br>brings adjudication to <br>your fingertips.
                    </h2>
                    <p class="text-gray-600">Experience a streamlined barangay management system</p>
                </div>

                <div class="register-right form-appear">
                    <h3 class="text-2xl font-bold text-gray-800 mb-2">Get Started</h3>
                    <p class="mb-6 text-gray-600">Already have an account? <a href="login.php"
                            class="text-blue-600 font-medium toggle-form">Log In</a></p>

                    <form id="registerForm" method="POST" action="../controllers/create_externaldb.php" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="reg_fname" id="reg_fname" class="input-field"
                                    placeholder="First Name" required>
                            </div>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="reg_lname" id="reg_lname" class="input-field"
                                    placeholder="Last Name" required>
                            </div>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-user"></i>
                            <input type="text" name="reg_mname" id="reg_mname" class="input-field"
                                placeholder="Middle Name">
                        </div>

                        <div class="input-group">
                            <i class="fas fa-home"></i>
                            <input type="text" name="reg_address" id="reg_address" class="input-field"
                                placeholder="Address" required>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-envelope"></i>
                            <input type="email" name="reg_email" id="reg_email" class="input-field"
                                placeholder="Email Address" required>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-phone"></i>
                            <input type="text" name="reg_contact" id="reg_email" class="input-field"
                                placeholder="Contact Number" required>
                        </div>

                        <div class="input-group">
                            <i class="fas fa-users"></i>
                            <select name="reg_type" id="reg_type" class="input-field" required>
                                <option value="" disabled selected>Type of User</option>
                                <option value="resident">Resident</option>
                                <!--<option value="barangay_official">Barangay Official</option>
                                <option value="external_complainant">External Complainant</option>-->
                            </select>
                        </div>

                        <div class="input-group relative">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="reg_pass" id="reg_pass" class="input-field pr-10"
                                placeholder="Password" required>
                            <button type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('reg_pass', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <div class="input-group relative" style="animation-delay: 0.9s;">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="reg_pass_confirm" id="reg_pass_confirm"
                                class="input-field pr-10" placeholder="Confirm Password" required>
                            <button type="button"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('reg_pass_confirm', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <p id="passwordMismatchMsg" class="text-sm text-red-600 hidden mt-1">Passwords do not match.</p>

                        <div class="flex flex-col gap-3 mb-4 animate-slide-in">
                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" name="reg_terms" class="mt-1 form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out" required>
                                <span class="text-sm text-gray-600">
                                    I agree to the <a href="#" class="text-blue-600 hover:text-blue-700 underline">Terms and Conditions</a>
                                </span>
                            </label>

                            <label class="flex items-start gap-2 cursor-pointer">
                                <input type="checkbox" name="reg_privacy" class="mt-1 form-checkbox h-4 w-4 text-blue-600 transition duration-150 ease-in-out" required>
                                <span class="text-sm text-gray-600">
                                    I accept the <a href="#" class="text-blue-600 hover:text-blue-700 underline">Privacy Policy</a>
                                </span>
                            </label>
                        </div>

                        <button id="submitBtn" type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-all duration-300 font-medium animate-slide-in" name="Signup" disabled>
                            Create Account
                        </button>
                    </form>

                    <br>
                    <div id="registerMessage" class="hidden mt-4 text-center"></div>
                </div>
            </div>
        </div>
    </div>

    </div>
    <?php include 'sidebar_.php';?>
    <script>
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
                if (password.value && confirmPassword.value && password.value !== confirmPassword.value) {
                    mismatchMsg.classList.remove('hidden');
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else {
                    mismatchMsg.classList.add('hidden');
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }

           password.addEventListener('input', validatePasswords);
           confirmPassword.addEventListener('input', validatePasswords);

    </script>
</body>

</html>