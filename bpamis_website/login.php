
<?php
// Try to detect existing role-specific sessions (created by logindb.php using session_name)
// If the user is already logged in under any role, show a message and redirect to their dashboard.
if (session_status() === PHP_SESSION_NONE) {
    // Direct server-side detection of existing active role sessions (authoritative).
    $possible_names = ['BPAMIS_RESIDENT','BPAMIS_EXTERNAL','BPAMIS_SEC','BPAMIS_OFFICIAL','BPAMIS_LUPONHEAD','BPAMIS_APP','PHPSESSID'];

    foreach ($possible_names as $sname) {
        if (!empty($_COOKIE[$sname])) {
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
            session_name($sname);
            @session_start();

            $isLogged = (!empty($_SESSION['user_id']) || !empty($_SESSION['official_id']) || !empty($_SESSION['user']));
            if ($isLogged) {
                $role = strtolower($_SESSION['role'] ?? '');
                $redirect = '/BPAMIS/bpamis_website/bpamis.php';
                if ($role === 'resident') $redirect = '../ResidentMenu/home-resident.php';
                elseif ($role === 'external') $redirect = '../ExternalMenu/home-external.php';
                elseif ($role === 'official') {
                    $pos = strtolower($_SESSION['official_position'] ?? '');
                    if (strpos($pos, 'barangay secretary') !== false) $redirect = '../SecMenu/home-secretary.php';
                    elseif (strpos($pos, 'lupon-hepe') !== false || strpos($pos, 'lupon head') !== false) $redirect = '../LuponHeadMenu/home-luponhead.php';
                    elseif (strpos($pos, 'lupon tagapamayapa') !== false) $redirect = '../OfficialMenu/home-lupon.php';
                    elseif (strpos($pos, 'barangay captain') !== false) $redirect = '../OfficialMenu/home-captain.php';
                    else $redirect = '../SecMenu/home-secretary.php';
                }
                $displayName = htmlspecialchars($_SESSION['username'] ?? $_SESSION['official_name'] ?? $_SESSION['user'] ?? 'your account');
                echo "<!doctype html><html><head><meta charset=\"utf-8\"></head><body>
                    <script>
                        // Persist and broadcast active login for cross-tab awareness, but do not show alert here
                        // so users who just logged in aren't interrupted by an extra message.
                        try { localStorage.setItem('bpamis_auth', JSON.stringify({logged_in:true, redirect:'{$redirect}', user:'{$displayName}', ts:Date.now()})); } catch(e) {}
                        try { const bc = new BroadcastChannel('bpamis-auth'); bc.postMessage({type:'logged-in', redirect:'{$redirect}', user:'{$displayName}'}); } catch(e) {}
                        window.location.replace('{$redirect}');
                    </script>
                </body></html>";
                if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
                exit;
            }
            if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        }
    }
    // No active role sessions — start a fresh default public session.
    @session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPAMIS Login</title>
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

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            width: 100%;
            max-width: 1200px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            animation: none !important;
            opacity: 1 !important;
            transform: none !important;
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

        /* Fade-in animation for mobile */
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

        .form-container {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            min-height: 600px;
        }

        .login-left {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.25) 100%);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            position: relative;
            z-index: 1;
        }

        .login-left::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.05);
            z-index: -1;
        }

        .login-right {
            padding: 3rem;
            background: white;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        
        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
        }
        
        /* Password toggle button positioning with larger hit area */
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
            /* increase padding and size so it's easy to tap on mobile */
            padding: 0.45rem;
            width: 2.6rem;
            height: 2.6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
        }

        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            background: #F9FAFB;
            transition: all 0.3s ease;
        }
        
        /* Password field should have right padding to accommodate the icon */
        input[type="password"].input-field {
            /* make room for the larger toggle button */
            padding-right: 3.6rem;
        }

        .input-field:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .login-btn {
            background: #2563eb;
            /* BPAMIS blue color */
            color: #ffffff;
            /* White text */
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: #1d4ed8;
            /* Darker blue on hover */
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
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

        .social-btn {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            /* Stack the form into a single column on small screens while preserving desktop layout */
            .form-container {
                grid-template-columns: 1fr;
            }

            .login-left {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
                background: linear-gradient(135deg, rgba(37, 99, 235, 0.15) 0%, rgba(37, 99, 235, 0.25) 100%);
            }

            /* Keep the right-side card hidden on very small screens (mobile) as before */
            .login-right {
                display: none;
            }

            .login-container {
                margin: 1rem;
            }

            /* Reduce the large heading size for mobile but keep visual hierarchy */
            .text-4xl {
                font-size: 1.1rem;
                text-align: center;
            }

            /*
                Make the login input fields match the page's standard text size on mobile.
                This ensures inputs are readable and consistent with other text.
                We avoid changing desktop styles; these rules only apply below 768px.
            */
            #login_user.input-field,
            #login_pass.input-field,
            .mobile-login-form .input-field,
            .input-field {
                font-size: 0.7rem; /* same as body / other paragraph text */
            }

            /* Keep spacing comfortable on mobile */
            .text-sm,
            .text-gray-600,
            .register-link {
                font-size: 0.7rem;
            }

            .login-button {
                font-size: 0.7rem;
                font-weight: 600;
            }

            .mobile-login-form h3 {
                /* Reduced size for mobile header to improve spacing on small screens */
                font-size: 1rem;
                line-height: 1.2;
            }

            .mobile-login-form .input-field {
                /* keep input padding consistent with desktop but allow responsive font */
                padding: 0.75rem 1rem 0.75rem 2.5rem;
            }

            .mobile-login-form .checkbox-group label span {
                font-size: 0.95rem;
            }

            .mobile-login-form .forgot-password {
                font-size: 0.95rem;
            }

            /* Make form and action icons slightly smaller on mobile to save space */
            .input-group i {
                font-size: 0.7rem; /* slightly smaller than desktop */
                left: 0.9rem; /* nudge icon closer to edge for smaller inputs */
            }

            /* Password toggle sizing on mobile - still larger than the icon for a good hitbox */
            .input-group button.password-toggle {
                right: 0.7rem;
                width: 2.2rem;
                height: 2.2rem;
                padding: 0.3rem;
            }
            .input-group button.password-toggle i {
                font-size: 0.95rem;
            }

            /* Social buttons and their icons */
            .social-btn {
                width: 36px;
                height: 36px;
            }
            .social-btn i {
                font-size: 0.95rem;
            }
        }
        
        /* Styling for the login form */
        #loginForm {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
        }

        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .forms-container {
            position: relative;
            width: 200%;
            display: flex;
            transition: transform 0.6s ease-in-out;
        }

        .forms-container.show-register {
            transform: translateX(-50%);
        }

        /* Flip card styles */
        .flip-card-container {
            perspective: 1000px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .flip-card {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            transition: transform 0.6s;
        }

        .flip-card-front,
        .flip-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 24px;
            overflow: hidden;
        }

        .flip-card-front {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
        }

        .flip-card-back {
            background: #f9fafb;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem;
            transform: rotateY(180deg);
        }

        .flip-card-container:hover .flip-card {
            transform: rotateY(180deg);
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">
    <!--  include_once(includes/bpamis_nav.php);-->
    <!-- Add flex container for centering   register-bg flex flex-col min-h-screen -->
    <div class="register-bg flex flex-col min-h-screen flex-1 flex items-center justify-center p-4">

        <div class="login-container premium-glass premium-stats-container">
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
                <div class="login-left premium-left-pattern hide-mobile">
                    <div class="flex justify-center w-full">
                        <div class="relative mb-8">
                            <a href="bpamis.php"><img src="assets/images/logo.png" alt="BPAMIS Logo" class="w-20 h-20 object-contain"></a>
                           
                        </div>
                    </div>
                    <h2 class="text-4xl font-bold text-gray-800 mb-4 leading-tight">
                        Join our digital community — BPAMIS <br>brings adjudication to <br>your fingertips.
                    </h2>
                    <p class="text-gray-600 md:block hidden">Experience a streamlined barangay management system</p>
                    
                    <!-- Mobile Login Form -->
                    <div class="mobile-login-form md:hidden w-full max-w-sm mt-8 bg-white rounded-lg p-4 shadow-lg border border-gray-200 ">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center mobile-fade-in-delay-1">Get Started</h3>
                        <form id="loginFormMobile" method="POST" action = "../controllers/logindb.php" class="space-y-6">
                            <div class="input-group form-field mobile-fade-in-delay-1">
                                <i class="fas fa-envelope"></i>
                                <input type="input" name="login_user" id="login_user_mobile" class="input-field"
                                    placeholder="Enter your email" required>
                            </div>

                            <div class="input-group form-field mobile-fade-in-delay-2">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="login_pass" id="login_pass_mobile" class="input-field"
                                    placeholder="••••••••" required>
                                <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('login_pass_mobile', this)" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye text-gray-400"></i>
                                </button>
                            </div>
                             <!-- Forgot password (mobile) -->
                            <div class="text-right mt-1 mb-2">
                                <a href="forgot_password.php" class="text-xs text-blue-600 hover:underline">Forgot password?</a>
                            </div>

                            <div id="loginErrorMobile" class="hidden mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 shadow-sm flex items-start gap-2">
                                <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
                                <span class="flex-1"></span>
                            </div>

                            

                            <button type="submit"
                                class="w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 transition-all duration-300 login-button mobile-fade-in-delay-4">
                                Log in
                            </button>

                            <p class="text-center text-gray-600 mt-4 register-link mobile-fade-in-delay-5">
                                Don't have an account?
                                <a href="register.php" class="text-blue-600 hover:text-blue-700">Register here</a>
                            </p>
                        </form>

                        <div id="loginMessageMobile" class="hidden mt-4 text-center"></div>
                    </div>
                </div>

                <div class="login-right premium-staggered premium-pattern">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6 ml-2">Get Started</h3>
                    <!-- <p class="mb-6 text-gray-600">Don't have an account? <a href="register.php"
                            class="text-blue-600 font-medium toggle-form">Sign Up.</a></p> -->

                    <form id="loginForm" method="POST" action = "../controllers/logindb.php" class="space-y-6">
                        <div class="input-group form-field">
                            <i class="fas fa-envelope"></i>
                            <input type="text" name="login_user" id="login_user" class="input-field"
                                placeholder="Enter your email" required>
                        </div>

                        <div class="input-group form-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="login_pass" id="login_pass" class="input-field"
                                placeholder="••••••••" required>
                            <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('login_pass', this)" aria-label="Toggle password visibility">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>

                        <!-- Forgot password (desktop) -->
                        <div class="text-right mt-1 mb-2">
                            <a href="forgot_password.php" class="text-sm text-blue-600 hover:underline">Forgot password?</a>
                        </div>

                        <div id="loginError" class="hidden mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 shadow-sm flex items-start gap-2">
                            <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
                            <span class="flex-1"></span>
                        </div>

                        

                        <button type="submit"
                            class="w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 transition-all duration-300 login-button">
                            Log in
                        </button>

                        <p class="text-center text-gray-600 mt-4 register-link">
                            Don't have an account?
                            <a href="register.php" class="text-blue-600 hover:text-blue-700">Register here</a>
                        </p>
                    </form>

                    <br>
                    <div id="loginMessage" class="hidden mt-4 text-center"></div>
                </div>
            </div>
        </div>

    </div>



    <script>
      function showLoginError(isMobile = false, message = 'Invalid username or password.') {
          const desktopErr = document.getElementById('loginError');
          const mobileErr = document.getElementById('loginErrorMobile');
          const target = isMobile ? mobileErr : desktopErr;
          if (!target) return;
          const span = target.querySelector('span');
          if (span) span.textContent = message;
          target.classList.remove('hidden');
        const form = document.getElementById(isMobile ? 'loginFormMobile' : 'loginForm');
        if (form) {
            form.classList.add('animate-shake');
            setTimeout(()=>form.classList.remove('animate-shake'),500);
        }
        const passField = document.getElementById(isMobile ? 'login_pass_mobile' : 'login_pass');
        if (passField) passField.value='';
        // auto-hide after delay
        setTimeout(()=> target.classList.add('hidden'), 6000);
    }

    function clearInlineErrors() {
        const ids = ['loginError','loginErrorMobile'];
        ids.forEach(id=>{ const el = document.getElementById(id); if(el){ const span=el.querySelector('span'); if(span) span.textContent=''; el.classList.add('hidden');}});
    }

    // Password toggle function
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

    window.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        if (params.get('login_error') === 'true') {
            // Assume server flagged invalid credentials; show on both for consistency
            showLoginError(false);
            showLoginError(true);
        }
        // Attach client-side validation to prevent empty submits
        const desktopForm = document.getElementById('loginForm');
        const mobileForm = document.getElementById('loginFormMobile');
        const desktopBtn = desktopForm ? desktopForm.querySelector('button[type="submit"]') : null;
        const mobileBtn = mobileForm ? mobileForm.querySelector('button[type="submit"]') : null;

        function updateLoginButtonState(form, btn){
            if(!form || !btn) return;
            const user = form.querySelector('input[name="login_user"]');
            const pass = form.querySelector('input[name="login_pass"]');
            const disabled = !(user && pass && user.value.trim() && pass.value.trim());
            btn.disabled = disabled;
            btn.classList.toggle('opacity-50', disabled);
            btn.classList.toggle('cursor-not-allowed', disabled);
        }

        function attachInputListeners(form, btn){
            if(!form || !btn) return;
            form.querySelectorAll('input[name="login_user"], input[name="login_pass"]').forEach(inp => {
                inp.addEventListener('input', ()=> updateLoginButtonState(form, btn));
            });
            updateLoginButtonState(form, btn);
        }

        function attachValidation(form, isMobile){
            if(!form) return;
            form.addEventListener('submit', (e)=>{
                clearInlineErrors();
                const user = form.querySelector('input[name="login_user"]');
                const pass = form.querySelector('input[name="login_pass"]');
                if(!user.value.trim() || !pass.value.trim()){
                    e.preventDefault();
                    showLoginError(isMobile, 'Username and password are required.');
                    return;
                }
                // AJAX submit to avoid full page reload
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn ? submitBtn.textContent : '';
                if(submitBtn){ submitBtn.disabled = true; submitBtn.textContent = 'Logging in...'; }
                const fd = new FormData(form);
                fd.append('ajax','1');
                fetch('../controllers/logindb.php', { method:'POST', body: fd })
    .then(r=> r.ok ? r.json() : Promise.reject(new Error('Network error')))
    .then(data => {
        if(data.success){
            // ✅ Normal login success
            try { localStorage.setItem('bpamis_auth', JSON.stringify({logged_in:true, redirect:data.redirect, user:(fd.get('login_user')||'').toString(), ts:Date.now()})); } catch(e) {}
            try { const bc = new BroadcastChannel('bpamis-auth'); bc.postMessage({type:'logged-in', redirect:data.redirect, user:(fd.get('login_user')||'').toString()}); } catch(e) {}
            window.location.href = data.redirect;
        } else {
            // Handle inactive/unverified accounts
            if (data.message && (data.message.includes('inactive') || data.message.includes('not Activated'))) {
                alert(data.message);  
                window.location.href = '../bpamis_website/bpamis.php'; 
            } else {
                //  Wrong credentials → show inline error
                showLoginError(isMobile, data.message || 'Invalid username or password.');
            }
        }
    })
    .catch(err => {
        console.error(err);
        showLoginError(isMobile, 'Login failed. Please try again.');
    })
                    .finally(()=>{ if(submitBtn){ submitBtn.disabled=false; submitBtn.textContent = originalText; }});
            });
        }
        attachValidation(desktopForm,false);
        attachValidation(mobileForm,true);
        attachInputListeners(desktopForm, desktopBtn);
        attachInputListeners(mobileForm, mobileBtn);
    });

       
    </script>
</body>

</html>
<!-- <script>
// Cross-tab / already-open login page detection:
// If another tab logs in and sets localStorage / BroadcastChannel, this tab auto-redirects without manual refresh.
(function(){
    let redirected = false;
    function attemptRedirect(src){
        if (redirected) return;
        try {
            const dataRaw = localStorage.getItem('bpamis_auth');
            if (dataRaw) {
                const data = JSON.parse(dataRaw);
                if (data && data.logged_in && data.redirect) {
                    // Confirm with server to avoid stale localStorage-caused redirects
                    fetch('../controllers/session_check.php', {credentials:'same-origin'})
                        .then(r => r.ok ? r.json() : Promise.resolve({logged_in:false}))
                        .then(s => {
                            if (s && s.logged_in && s.redirect) {
                                redirected = true;
                                // Show alert only when the event originated from another tab (broadcast).
                                if (src === 'broadcast') {
                                    alert('You are logged in as ' + (s.user || data.user || 'your account') + '. You will be redirected to your account.');
                                }
                                // Silent redirect for init/poll to avoid alert in the same tab after fresh login
                                window.location.replace(s.redirect);
                            } else {
                                try { localStorage.removeItem('bpamis_auth'); } catch(e) {}
                            }
                        })
                        .catch(()=>{ /* ignore */ });
                }
            }
        } catch(e) {}
    }
    // Poll as a fallback (in case BroadcastChannel unsupported)
    const pollId = setInterval(()=> attemptRedirect('poll'), 4000);
    // Listen for BroadcastChannel events
    try {
        const bc = new BroadcastChannel('bpamis-auth');
        bc.onmessage = (ev)=> {
            if (ev.data && ev.data.type === 'logged-in') {
                try { localStorage.setItem('bpamis_auth', JSON.stringify({logged_in:true, redirect:ev.data.redirect, user:ev.data.user||'', ts:Date.now()})); } catch(e) {}
                attemptRedirect('broadcast');
            }
            if (ev.data && ev.data.type === 'logged-out') {
                try { localStorage.removeItem('bpamis_auth'); } catch(e) {}
            }
        };
    } catch(e) {}
    // Initial check shortly after load
    setTimeout(()=> attemptRedirect('init'), 800);
})();
</script> -->