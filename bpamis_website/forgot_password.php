<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BPAMIS</title>
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
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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

        .particle-1 {
            width: 5px;
            height: 5px;
            top: 65%;
            left: 75%;
            background-color: rgba(59, 130, 246, 0.5);
            animation: float-particle 6s infinite 0.5s;
        }

        .particle-2 {
            width: 8px;
            height: 8px;
            top: 30%;
            left: 60%;
            background-color: rgba(16, 185, 129, 0.5);
            animation: float-particle 8s infinite 2s;
        }

        .particle-3 {
            width: 4px;
            height: 4px;
            top: 70%;
            left: 25%;
            background-color: rgba(236, 72, 153, 0.5);
            animation: float-particle 5s infinite 1.5s;
        }

        .particle-4 {
            width: 7px;
            height: 7px;
            top: 10%;
            left: 85%;
            background-color: rgba(139, 92, 246, 0.5);
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
        }

        .input-group {
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

        .input-field {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            background: #F9FAFB;
            transition: all 0.3s ease;
        }
        
        input[type="password"].input-field {
            padding-right: 3rem;
        }

        .input-field:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        .login-btn, .fp-btn {
            background: #2563eb;
            color: #ffffff;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .login-btn:hover, .fp-btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
        }

        .fp-btn:disabled {
            background: #93c5fd;
            cursor: not-allowed;
            transform: none;
        }
        
        .password-toggle {
            cursor: pointer;
            transition: color 0.2s ease;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #4B5563;
        }

        @media (max-width: 768px) {
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

            .login-right {
                display: none;
            }

            .login-container {
                margin: 1rem;
            }

            .text-4xl {
                font-size: 1.1rem;
                text-align: center;
            }

            .input-field {
                font-size: 0.7rem;
            }

            .text-sm, .text-gray-600, .register-link {
                font-size: 0.7rem;
            }

            .login-button, .fp-btn {
                font-size: 0.7rem;
                font-weight: 600;
            }

            .mobile-login-form h3 {
                font-size: 1rem;
                line-height: 1.2;
            }

            .mobile-login-form .input-field {
                padding: 0.75rem 1rem 0.75rem 2.5rem;
            }

            .input-group i {
                font-size: 0.7rem;
                left: 0.9rem;
            }

            .input-group button.password-toggle {
                right: 0.9rem;
                width: 1.25rem;
                height: 1.25rem;
            }

            .input-group button.password-toggle i {
                font-size: 0.7rem;
            }
        }

        .animate-shake {
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
</head>

<body class="bg-gray-50 font-sans">
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
                        Reset your password<br>and regain access<br>to BPAMIS
                    </h2>
                    <p class="text-gray-600 md:block hidden">Secure password recovery for your account</p>
                    
                    <!-- Mobile Forgot Password Form -->
                    <div class="mobile-login-form md:hidden w-full max-w-sm mt-8 bg-white rounded-lg p-4 shadow-lg border border-gray-200">
                        <h3 class="text-2xl font-bold text-gray-800 mb-6 text-center mobile-fade-in-delay-1">Reset Password</h3>
                        
                        <!-- Step 1: Email Input (Mobile) -->
                        <div id="step1-mobile">
                            <p class="text-sm text-gray-600 mb-4">Enter your account email. We'll send a 6-digit code to reset your password.</p>
                            <div class="input-group form-field mobile-fade-in-delay-1">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="fp-email-mobile" class="input-field" placeholder="you@example.com" required>
                            </div>
                            <div id="fp-msg-mobile" class="hidden mt-2 mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 shadow-sm flex items-start gap-2">
                                <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
                                <span class="flex-1"></span>
                            </div>
                            <button id="fp-send-mobile" class="w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 transition-all duration-300 fp-btn mobile-fade-in-delay-2">
                                Send OTP
                            </button>
                            <p class="text-xs text-gray-500 mt-3 text-center">Code expires in 15 minutes. Max 5 requests/hour.</p>
                            <p class="text-center text-gray-600 mt-4 register-link mobile-fade-in-delay-3">
                                Remember your password?
                                <a href="login.php" class="text-blue-600 hover:text-blue-700">Back to Login</a>
                            </p>
                        </div>

                        <!-- Step 2: OTP & New Password (Mobile) -->
                        <div id="step2-mobile" class="hidden">
                            <p class="text-sm text-gray-600 mb-4">Enter the code you received and choose a new password.</p>
                            <div class="input-group form-field">
                                <i class="fas fa-key"></i>
                                <input type="text" id="fp-otp-mobile" maxlength="6" class="input-field" placeholder="6-digit code" required>
                            </div>
                            <div class="input-group form-field">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="fp-new-mobile" class="input-field" placeholder="New password (min 6 chars)" required>
                                <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('fp-new-mobile', this)">
                                    <i class="fas fa-eye text-gray-400"></i>
                                </button>
                            </div>
                            <div class="input-group form-field">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="fp-new2-mobile" class="input-field" placeholder="Confirm new password" required>
                                <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                    onclick="togglePassword('fp-new2-mobile', this)">
                                    <i class="fas fa-eye text-gray-400"></i>
                                </button>
                            </div>
                            <div id="fp-msg2-mobile" class="hidden mt-2 mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 shadow-sm flex items-start gap-2">
                                <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
                                <span class="flex-1"></span>
                            </div>
                            <button id="fp-reset-mobile" class="w-full bg-green-600 text-white py-2 px-4 rounded-full hover:bg-green-700 transition-all duration-300 fp-btn mb-2">
                                Reset Password
                            </button>
                            <button id="fp-back-mobile" class="w-full text-sm text-gray-600 hover:text-gray-800 py-2">
                                ← Back to email
                            </button>
                        </div>

                        <!-- Step 3: Success (Mobile) -->
                        <div id="fp-done-mobile" class="hidden text-center">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-green-500 text-5xl"></i>
                            </div>
                            <p class="text-green-600 font-medium mb-2">Password Updated!</p>
                            <p class="text-sm text-gray-600 mb-4">Check your email for confirmation.</p>
                            <a href="login.php" class="inline-block w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 transition-all duration-300">
                                Return to Login
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Desktop Form -->
                <div class="login-right premium-staggered premium-pattern">
                    <h3 class="text-2xl font-bold text-gray-800 mb-6 ml-2">Reset Password</h3>

                    <!-- Step 1: Email Input (Desktop) -->
                    <div id="step1">
                        <p class="text-sm text-gray-600 mb-4">Enter your account email. We'll send a 6-digit code to reset your password.</p>
                        <div class="input-group form-field">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="fp-email" class="input-field" placeholder="you@example.com" required>
                        </div>
                        <div id="fp-msg" class="hidden mt-2 mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 shadow-sm flex items-start gap-2">
                            <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
                            <span class="flex-1"></span>
                        </div>
                        <button id="fp-send" class="w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 transition-all duration-300 fp-btn">
                            Send OTP
                        </button>
                        <p class="text-xs text-gray-500 mt-3 text-center">Code expires in 15 minutes. Max 5 requests/hour.</p>
                        <p class="text-center text-gray-600 mt-4 register-link">
                            Remember your password?
                            <a href="login.php" class="text-blue-600 hover:text-blue-700">Back to Login</a>
                        </p>
                    </div>

                    <!-- Step 2: OTP & New Password (Desktop) -->
                    <div id="step2" class="hidden">
                        <p class="text-sm text-gray-600 mb-4">Enter the code you received and choose a new password.</p>
                        <div class="input-group form-field">
                            <i class="fas fa-key"></i>
                            <input type="text" id="fp-otp" maxlength="6" class="input-field" placeholder="6-digit code" required>
                        </div>
                        <div class="input-group form-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="fp-new" class="input-field" placeholder="New password (min 6 chars)" required>
                            <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('fp-new', this)">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                        <div class="input-group form-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="fp-new2" class="input-field" placeholder="Confirm new password" required>
                            <button type="button" class="password-toggle hover:text-gray-600 focus:outline-none"
                                onclick="togglePassword('fp-new2', this)">
                                <i class="fas fa-eye text-gray-400"></i>
                            </button>
                        </div>
                        <div id="fp-msg2" class="hidden mt-2 mb-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-600 shadow-sm flex items-start gap-2">
                            <i class="fas fa-exclamation-circle mt-0.5 text-red-500"></i>
                            <span class="flex-1"></span>
                        </div>
                        <button id="fp-reset" class="w-full bg-green-600 text-white py-2 px-4 rounded-full hover:bg-green-700 transition-all duration-300 fp-btn mb-2">
                            Reset Password
                        </button>
                        <button id="fp-back" class="w-full text-sm text-gray-600 hover:text-gray-800 py-2">
                            ← Back to email
                        </button>
                    </div>

                    <!-- Step 3: Success (Desktop) -->
                    <div id="fp-done" class="hidden text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-green-500 text-5xl"></i>
                        </div>
                        <p class="text-green-600 font-medium mb-2">Password Updated!</p>
                        <p class="text-sm text-gray-600 mb-4">Check your email for confirmation.</p>
                        <a href="login.php" class="inline-block w-full bg-blue-600 text-white py-2 px-4 rounded-full hover:bg-blue-700 transition-all duration-300">
                            Return to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// Password toggle function
function togglePassword(fieldId, button) {
    const field = document.getElementById(fieldId);
    const icon = button.querySelector('i');
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Helper function for POST requests
async function postJson(url, data) {
    const fd = new FormData();
    for (const k in data) fd.append(k, data[k]);
    const r = await fetch(url, { method: 'POST', body: fd });
    const ct = r.headers.get('content-type') || '';
    if (ct.includes('application/json')) return r.json();
    return { success: false, message: 'Unexpected response', raw: await r.text() };
}

// Show error message
function showError(isMobile, msgId, message) {
    const msg = document.getElementById(msgId);
    if (!msg) return;
    const span = msg.querySelector('span');
    if (span) span.textContent = message;
    msg.classList.remove('hidden');
    setTimeout(() => msg.classList.add('hidden'), 6000);
}

// Hide error message
function hideError(msgId) {
    const msg = document.getElementById(msgId);
    if (msg) {
        msg.classList.add('hidden');
        const span = msg.querySelector('span');
        if (span) span.textContent = '';
    }
}

// Desktop: Send OTP
document.getElementById('fp-send').addEventListener('click', async () => {
    const email = document.getElementById('fp-email').value.trim();
    hideError('fp-msg');
    if (!email) {
        showError(false, 'fp-msg', 'Enter your email');
        return;
    }
    
    const btn = document.getElementById('fp-send');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    const res = await postJson('../controllers/forgot_password_email.php', { email });
    btn.disabled = false;
    btn.textContent = 'Send OTP';
    
    console.log('forgot_password_email response:', res);
    if (res.mail_error) console.error('PHPMailer error (forgot_password_email):', res.mail_error);
    
    if (res.success) {
        document.getElementById('step1').classList.add('hidden');
        document.getElementById('step2').classList.remove('hidden');
    } else {
        showError(false, 'fp-msg', res.message || 'Failed to send OTP');
        console.error('Forgot OTP error', res);
    }
});

// Desktop: Back button
document.getElementById('fp-back').addEventListener('click', () => {
    document.getElementById('step2').classList.add('hidden');
    document.getElementById('step1').classList.remove('hidden');
    hideError('fp-msg2');
});

// Desktop: Reset password
document.getElementById('fp-reset').addEventListener('click', async () => {
    const email = document.getElementById('fp-email').value.trim();
    const otp = document.getElementById('fp-otp').value.trim();
    const newp = document.getElementById('fp-new').value;
    const newp2 = document.getElementById('fp-new2').value;
    hideError('fp-msg2');
    
    if (!otp || otp.length !== 6) {
        showError(false, 'fp-msg2', 'Enter the 6-digit code');
        return;
    }
    if (newp.length < 6) {
        showError(false, 'fp-msg2', 'Password must be at least 6 characters');
        return;
    }
    if (newp !== newp2) {
        showError(false, 'fp-msg2', 'Passwords do not match');
        return;
    }
    
    const btn = document.getElementById('fp-reset');
    btn.disabled = true;
    btn.textContent = 'Resetting...';
    
    const res = await postJson('../controllers/verify_forgot_otp.php', { email, otp, new_password: newp });
    btn.disabled = false;
    btn.textContent = 'Reset Password';
    
    console.log('verify_forgot_otp response:', res);
    if (res.mail_error) console.error('PHPMailer error (verify_forgot_otp):', res.mail_error);
    
    if (res.success) {
        document.getElementById('step2').classList.add('hidden');
        document.getElementById('fp-done').classList.remove('hidden');
    } else {
        showError(false, 'fp-msg2', res.message || 'Unable to reset password');
        console.error('Reset error', res);
    }
});

// Mobile: Send OTP
document.getElementById('fp-send-mobile').addEventListener('click', async () => {
    const email = document.getElementById('fp-email-mobile').value.trim();
    hideError('fp-msg-mobile');
    if (!email) {
        showError(true, 'fp-msg-mobile', 'Enter your email');
        return;
    }
    
    const btn = document.getElementById('fp-send-mobile');
    btn.disabled = true;
    btn.textContent = 'Sending...';
    
    const res = await postJson('../controllers/forgot_password_email.php', { email });
    btn.disabled = false;
    btn.textContent = 'Send OTP';
    
    console.log('forgot_password_email response:', res);
    if (res.mail_error) console.error('PHPMailer error (forgot_password_email):', res.mail_error);
    
    if (res.success) {
        document.getElementById('step1-mobile').classList.add('hidden');
        document.getElementById('step2-mobile').classList.remove('hidden');
    } else {
        showError(true, 'fp-msg-mobile', res.message || 'Failed to send OTP');
        console.error('Forgot OTP error', res);
    }
});

// Mobile: Back button
document.getElementById('fp-back-mobile').addEventListener('click', () => {
    document.getElementById('step2-mobile').classList.add('hidden');
    document.getElementById('step1-mobile').classList.remove('hidden');
    hideError('fp-msg2-mobile');
});

// Mobile: Reset password
document.getElementById('fp-reset-mobile').addEventListener('click', async () => {
    const email = document.getElementById('fp-email-mobile').value.trim();
    const otp = document.getElementById('fp-otp-mobile').value.trim();
    const newp = document.getElementById('fp-new-mobile').value;
    const newp2 = document.getElementById('fp-new2-mobile').value;
    hideError('fp-msg2-mobile');
    
    if (!otp || otp.length !== 6) {
        showError(true, 'fp-msg2-mobile', 'Enter the 6-digit code');
        return;
    }
    if (newp.length < 6) {
        showError(true, 'fp-msg2-mobile', 'Password must be at least 6 characters');
        return;
    }
    if (newp !== newp2) {
        showError(true, 'fp-msg2-mobile', 'Passwords do not match');
        return;
    }
    
    const btn = document.getElementById('fp-reset-mobile');
    btn.disabled = true;
    btn.textContent = 'Resetting...';
    
    const res = await postJson('../controllers/verify_forgot_otp.php', { email, otp, new_password: newp });
    btn.disabled = false;
    btn.textContent = 'Reset Password';
    
    console.log('verify_forgot_otp response:', res);
    if (res.mail_error) console.error('PHPMailer error (verify_forgot_otp):', res.mail_error);
    
    if (res.success) {
        document.getElementById('step2-mobile').classList.add('hidden');
        document.getElementById('fp-done-mobile').classList.remove('hidden');
    } else {
        showError(true, 'fp-msg2-mobile', res.message || 'Unable to reset password');
        console.error('Reset error', res);
    }
});
</script>
</body>
</html>