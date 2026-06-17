<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>BPAMIS - Contact Us</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body {
            background: #ffffff;
        }

        .hero-section {
            /* background: linear-gradient(180deg, rgba(62, 131, 249, 0.68) 0%, rgba(96, 165, 250, 0.64) 60%, rgb(255, 255, 255)), url('assets/images/brgyhall.png');
             */
            background-size: cover;
            background-position: center;
            height: 600px;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .hero-section::before {
            /* display: none; */
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(62, 131, 249, 0.83) 0%, rgba(96, 165, 250, 0.64) 60%, rgb(255, 255, 255)), url('assets/images/brgyhall.png');
            filter: blur(8px);
            z-index: 0;
            opacity: 1;
            background-size: cover;
            Background-position: center;

        }

        .hero-section .container {
            position: relative;
            z-index: 1;
        }

        .glass-card {
            background: #ffffff;
            border: 1px solid rgba(229, 231, 235, 0.5);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        input,
        textarea,
        select {
            transition: all 0.2s ease;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            background: #ffffff;
        }

        button {
            transition: all 0.2s ease;
        }

        .fade-up {
            opacity: 0;
            animation: fadeUp 0.5s ease-out forwards;
        }

        .fade-in-element {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }

        .fade-in-element.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Stagger delay classes for animations */
        .stagger-fade-delay-1 {
            transition-delay: 0.2s;
        }

        .stagger-fade-delay-2 {
            transition-delay: 0.4s;
        }

        .stagger-fade-delay-3 {
            transition-delay: 0.6s;
        }

        .stagger-fade-delay-4 {
            transition-delay: 0.8s;
        }

        /* Update loader styles */
        .loader-wrapper {
            position: fixed;
            inset: 0;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 1;
            visibility: visible;
            transition: opacity 1s ease-out, visibility 1s ease-out;
        }

        .loader-wrapper.fade-out {
            opacity: 0;
            visibility: hidden;
        }

        .loader {
            position: relative;
            width: 100px;
            height: 100px;
        }

        .loader-circle {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 4px solid transparent;
            border-radius: 50%;
        }

        .loader-circle:nth-child(1) {
            border-top-color: #2563eb;
            animation: spin 1s linear infinite;
        }

        .loader-circle:nth-child(2) {
            border-right-color: #2563eb;
            animation: spin 0.8s linear infinite reverse;
        }

        .loader-circle:nth-child(3) {
            border-bottom-color: #2563eb;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        /* Loader Logo Animation */
        .loader-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            opacity: 0;
            animation: logoFade 0.5s ease-out 0.5s forwards;
        }

        @keyframes logoFade {
            to {
                opacity: 1;
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-btn {
            padding: 1rem 2.5rem;
            font-size: 1rem;
            border-radius: 9999px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            border: 2px solid transparent;
            text-decoration: none;
        }

        .hero-btn-primary {
            background-color: white;
            color: #2563eb;
            border-color: white;
        }

        .hero-btn-primary:hover {
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-3px);
        }

        .hero-btn-secondary {
            background-color: transparent;
            color: white;
            border-color: white;
        }

        .hero-btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-3px);
        }

        /* Enhanced form animations and styles */
        .group.is-focused label {
            color: #2563eb !important;
        }

        .group:hover .fas {
            transform: translateY(-2px);
        }

        .group .fas {
            transition: all 0.3s ease;
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateY(0);
            }

            to {
                opacity: 0;
                transform: translateY(10px);
            }
        }

        .animate-bounce {
            animation: bounce 0.5s;
        }

        .animate-fadeOut {
            animation: fadeOut 0.5s forwards;
        }

        /* Form step indicator */
        .step-indicator {
            position: relative;
        }

        .step-indicator::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #E5E7EB;
            z-index: 0;
        }

        .step {
            position: relative;
            z-index: 1;
            background-color: white;
        }

        /* Input focus styles */
        input:focus,
        select:focus,
        textarea:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            border-color: #3B82F6;
        }

        /* Add responsive design for mobile devices */
        @media (max-width: 768px) {

            /* Improved mobile spacing */
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            /* Adjust hero section for mobile */
            .hero-section {
                height: auto;
                padding-top: 8rem;
                padding-bottom: 6rem;
            }

            /* Disable fade animations on mobile for hero section */
            .hero-section .fade-in-element {
                opacity: 1 !important;
                transform: none !important;
                transition: none !important;
                animation: none !important;
            }

            .hero-section .stagger-fade-delay-1,
            .hero-section .stagger-fade-delay-2,
            .hero-section .stagger-fade-delay-3,
            .hero-section .stagger-fade-delay-4 {
                transition-delay: 0s !important;
            }

            /* Reduce font sizes on mobile */
            h1.text-7xl {
                font-size: 2.5rem;
                line-height: 1.2;
            }

            /* Make buttons more touch-friendly */
            .hero-btn {
                padding: 0.6rem 1rem;
                display: block;
                width: 70%;
                margin: 0 auto 0.8rem auto;
                font-size: 0.8rem;
            }

            .hero-btn:last-child {
                margin-bottom: 0;
            }

            /* Fix the flex layout on mobile */
            .flex.justify-center.gap-4 {
                flex-direction: column;
                width: 100%;
            }

            /* Add more space between sections on mobile */
            section {
                padding-top: 3rem;
                padding-bottom: 3rem;
            }

            /* Responsive form adjustments */
            .glass-card {
                padding: 1.5rem !important;
            }

            .grid.grid-cols-1 {
                gap: 1rem !important;
            }

            .text-sm {
                font-size: 0.7rem !important;
            }

            .text-2xl,
            .text-3xl {
                font-size: 1rem;
            }

            .text-4xl {
                font-size: 1.5rem;
            }

            /* Make buttons smaller for mobile hero section */
            
            .hero-btn:last-child {
                margin-bottom: 0;
            }
           

             /* Adjust hero section for mobile to match contact.php */
             .hero-section {
                height: auto;
                padding-top: 6rem;
                padding-bottom: 2rem;
            }
            .hero-section h1.text-4xl,
            .hero-section h1.text-7xl {
                margin-bottom: 0.2rem !important;
            }
            .hero-section p.text-sm,
            .hero-section p.text-xl {
                margin-bottom: 0.5rem !important;
            }
            .hero-btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.75rem;
                width: 60%;
                min-width: 120px;
                max-width: 180px;
                margin: 0 auto 0.5rem auto;
            }
            .hero-btn:last-child {
                margin-bottom: 0;
            }
            
        }

        @media (max-width: 640px) {

            /* Container width and padding control for small screens */
            .container {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            /* Adjust button container specifically for hero buttons */
            .flex.justify-center.gap-4 {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0 !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 280px !important;
            }

            /* Reduce all paddings */
            .p-8 {
                padding: 1.25rem !important;
            }

            .px-6,
            .px-8 {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            /* Further reduce grid gap spacing */
            .gap-8,
            .gap-12,
            .gap-16 {
                gap: 0.75rem !important;
            }

            /* Reduce text sizes further for smallest screens */
            h1.text-7xl {
                font-size: 2rem;
            }

            p.text-xl {
                font-size: 1rem;
            }

            .text-sm {
                font-size: 0.7rem !important;
            }

            .text-2xl,
            .text-3xl {
                font-size: 1rem;
            }

            .text-4xl {
                font-size: 1.3rem;
            }

            .text-xl {
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {

            /* Extra small screen optimizations */
            .container {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            /* Reduce all paddings */
            .p-8,
            .p-6 {
                padding: 1rem !important;
            }

            /* Minimize gap spacing */
            .gap-8,
            .gap-12,
            .gap-16,
            .gap-6,
            .gap-4 {
                gap: 0.5rem !important;
            }

            /* Ensure no overflow in mobile */
            .overflow-x-auto {
                max-width: 100vw;
            }

            /* Make text smaller for very small screens */
            p {
                font-size: 0.9rem !important;
            }

            /* Further adjustments for very small screens */
            .p-6 {
                padding: 1rem;
            }

            /* Ensure horizontal scrolling doesn't happen */
            body {
                overflow-x: hidden;
                width: 100%;
            }

            /* Fix spacing on mobile screens */
            .px-8 {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .py-16 {
                padding-top: 2rem;
                padding-bottom: 2rem;
            }

            /* Make form elements more mobile-friendly */
            input,
            select,
            textarea {
                font-size: 16px !important;
                /* Prevents zoom on iOS */
            }

            /* Hero section adjustments for smallest screens */
            h1.text-7xl {
                font-size: 1.8rem;
                margin-bottom: 0.5rem;
            }

            p.text-xl {
                font-size: 0.9rem !important;
                margin-bottom: 1.5rem;
            }

            .text-sm {
                font-size: 0.7rem !important;
            }

            .text-2xl,
            .text-3xl {
                font-size: 1rem;
            }

            .text-4xl {
                font-size: 1.3rem;
            }

            .text-xl {
                font-size: 20px;
            }

            .text-sm{
                font-size: 10.4px !important;
            }
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Immediate mobile animation disabling script (placed early in the head) -->
    <script>
        (function () {
            // Check if it's a mobile device
            if (window.innerWidth < 768) {
                // Create and inject immediate CSS rule to disable animations in hero section
                const style = document.createElement('style');
                style.textContent = `
                    .hero-section .fade-in-element {
                        opacity: 1 !important;
                        transform: none !important;
                        transition: none !important;
                        animation: none !important;
                    }
                    .hero-section .stagger-fade-delay-1,
                    .hero-section .stagger-fade-delay-2,
                    .hero-section .stagger-fade-delay-3,
                    .hero-section .stagger-fade-delay-4 {
                        transition-delay: 0s !important;
                    }
                `;
                document.head.appendChild(style);
            }
        })();
    </script>
</head>

<body>


    <!-- Add this at the very top of the body -->
    <div class="loader-wrapper" id="loader">
        <div class="loader">
            <div class="loader-circle"></div>
            <div class="loader-circle"></div>
            <div class="loader-circle"></div>
            <img src="assets/images/logo.png" alt="BPAMIS Logo" class="loader-logo">
        </div>
    </div>


    <?php include_once('includes/bpamis_nav.php'); ?>

    <!-- Hero Section -->
    <section class="hero-section flex items-center justify-center text-white text-center">
        <div class="container mx-auto px-4">
            <h1 class="text-4xl md:text-7xl font-bold mb-4 md:mb-6 fade-in-element stagger-fade-delay-1 hero-element">
                Reach Out for Support</h1>
            <p class="text-sm md:text-xl mb-6 md:mb-8 fade-in-element stagger-fade-delay-2 hero-element">Support &
                Inquiries — professional and service-focused</p>
            <div class="flex justify-center gap-4 fade-in-element stagger-fade-delay-3 hero-element">
                <a href="register.php" class="hero-btn hero-btn-primary">Get Started</a>
                <a href="#" onclick="openUserGuideModal(event)" class="hero-btn hero-btn-secondary">View User Guide</a>
            </div>
        </div>
    </section>

    <!-- Schedule Appointment Section -->
    <section class="py-16 md:py-20"
        style="background: linear-gradient(180deg, #fff 10%,rgba(37, 100, 235, 0.65) 50%, #fff 100%);">
        <div class="container mx-auto px-4">
            <!-- Grid layout for side-by-side content -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-12 max-w-7xl mx-auto items-center">
                <!-- Left side: Header & Title -->
                <div class="fade-in-element text-left lg:pr-6" style="margin-top: 10px;">
                    <div
                        class="inline-flex items-center px-3 sm:px-4 md:px-6 py-1.5 sm:py-2 md:py-3 bg-white/90 backdrop-blur-sm text-blue-600 rounded-full text-sm sm:text-sm font-semibold mb-3 sm:mb-4 md:mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                        <i class="fas fa-calendar-check mr-1 sm:mr-2 text-blue-500 text-xs"></i>
                        Appointment Booking
                    </div>
                    <h2
                        class="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 md:mb-6 bg-gradient-to-r from-blue-700 via-blue-600 to-blue-800 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                        Schedule an Appointment
                    </h2>
                    <p
                        class="text-sm sm:text-base md:text-lg text-gray-600 leading-relaxed max-w-xl stagger-fade-delay-3">
                        Fill out the form to schedule your appointment with us for document processing, consultations,
                        or other services
                    </p>
                    <div
                        class="w-16 sm:w-20 md:w-24 h-0.5 sm:h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mt-4 sm:mt-6 md:mt-8 mb-3 sm:mb-4 md:mb-6 stagger-fade-delay-4">
                    </div>

                    <div class="mt-4 sm:mt-6 md:mt-8 space-y-2 sm:space-y-3 md:space-y-4 stagger-fade-delay-4">
                        <div class="flex items-start gap-2 sm:gap-3">
                            <div class="bg-blue-100 p-1 sm:p-1.5 md:p-2 rounded-full text-blue-600 mt-1">
                                <i class="fas fa-check text-xs sm:text-sm"></i>
                            </div>
                            <p class="text-sm sm:text-sm md:text-base text-gray-700">Simple and fast booking process</p>
                        </div>
                        <div class="flex items-start gap-2 sm:gap-3">
                            <div class="bg-blue-100 p-1 sm:p-1.5 md:p-2 rounded-full text-blue-600 mt-1">
                                <i class="fas fa-check text-xs sm:text-sm"></i>
                            </div>
                            <p class="text-sm sm:text-sm md:text-base text-gray-700">Professional and courteous service
                            </p>
                        </div>
                        <div class="flex items-start gap-2 sm:gap-3">
                            <div class="bg-blue-100 p-1 sm:p-1.5 md:p-2 rounded-full text-blue-600 mt-1">
                                <i class="fas fa-check text-xs sm:text-sm"></i>
                            </div>
                            <p class="text-sm sm:text-sm md:text-base text-gray-700">Quick confirmation and reminders
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Right side: Appointment Form -->
                <div
                    class="glass-card rounded-xl sm:rounded-2xl p-4 sm:p-6 md:p-8 lg:p-10 mb-6 sm:mb-8 md:mb-12 shadow-xl border border-blue-100 transform transition-all hover:shadow-2xl hover:scale-[1.01] fade-in-element stagger-fade-delay-2">
                    <form id="appointmentForm" class="space-y-4 sm:space-y-6 md:space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 md:gap-6 lg:gap-8">
                            <div class="group">
                                <label
                                    class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Full
                                    Name</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i
                                            class="fas fa-user text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                    </div>
                                    <input type="text" id="fullName" name="fullName"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 text-sm"
                                        required placeholder="    Full Name">
                                </div>
                            </div>
                            <div class="group">
                                <label
                                    class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Email
                                    Address</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i
                                            class="fas fa-envelope text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                    </div>
                                    <input type="email" id="email" name="email"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 text-sm"
                                        required placeholder="    Email Address">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 md:gap-6 lg:gap-8">
                            <div class="group">
                                <label
                                    class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Phone
                                    Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i
                                            class="fas fa-phone text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                    </div>
                                    <input type="tel" id="phone" name="phone"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 text-sm"
                                        required placeholder="    Phone Number">
                                </div>
                            </div>
                            <div class="group">
                                <label
                                    class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Purpose
                                    of Appointment</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">

                                    </div>
                                    <select id="purpose" name="purpose"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 appearance-none text-sm"
                                        required>
                                        <option value="">Select Purpose</option>
                                        <option value="document">Document Request</option>
                                        <option value="complaint">File a Complaint</option>
                                        <option value="mediation">Mediation Session</option>
                                        <option value="inquiry">General Inquiry</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 md:gap-6 lg:gap-8">
                            <div class="group">
                                <label
                                    class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Preferred
                                    Date</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">

                                    </div>
                                    <input type="date" id="preferredDate" name="preferredDate"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 text-sm"
                                        required placeholder="    Select Date">
                                </div>
                            </div>
                            <div class="group">
                                <label
                                    class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Preferred
                                    Time</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">

                                    </div>
                                    <select id="preferredTime" name="preferredTime"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 appearance-none text-sm"
                                        required>
                                        <option value="">Select Time</option>
                                        <option value="morning">Morning (9:00 AM - 11:30 AM)</option>
                                        <option value="afternoon">Afternoon (1:00 PM - 4:30 PM)</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <i class="fas fa-chevron-down text-gray-400 text-xs"></i>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="group">
                            <label
                                class="block text-xs sm:text-sm font-medium text-gray-700 mb-1 sm:mb-2 group-hover:text-blue-600 transition-colors">Additional
                                Notes</label>
                            <div class="relative">
                                <div
                                    class="absolute top-2.5 sm:top-3 md:top-4 left-3 flex items-start pointer-events-none">
                                    <i
                                        class="fas fa-sticky-note text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                </div>
                                <textarea id="notes" name="notes" rows="3"
                                    class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white bg-opacity-80 hover:bg-opacity-100 text-sm"
                                    placeholder="    Additional Notes"></textarea>
                            </div>
                        </div>

                        <div class="pt-2 sm:pt-3 md:pt-4 border-t border-gray-100">
                            <button type="submit"
                                class="w-full md:w-auto px-4 sm:px-6 md:px-10 py-2.5 sm:py-3 md:py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-lg hover:shadow-xl transform hover:-translate-y-1 flex items-center justify-center text-sm sm:text-base">
                                <span>Submit Appointment Request</span>
                                <i class="fas fa-arrow-right ml-1 sm:ml-2 text-xs sm:text-sm"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <div id="successMessage"
            class="hidden fixed bottom-4 sm:bottom-8 right-4 sm:right-8 text-gray-800 px-4 sm:px-8 py-4 sm:py-6 rounded-lg sm:rounded-xl shadow-xl sm:shadow-2xl border-l-4 border-green-500 transform transition-all duration-500 z-50 max-w-xs sm:max-w-md bg-white">
            <div class="flex items-start">
                <div class="mr-3 sm:mr-4 bg-green-100 rounded-full p-1.5 sm:p-2">
                    <i class="fas fa-check-circle text-xl sm:text-2xl text-green-500"></i>
                </div>
                <div>
                    <h3 class="font-bold text-base sm:text-lg mb-1">Appointment Scheduled!</h3>
                    <p class="text-gray-600 text-sm sm:text-base">Your appointment request has been submitted
                        successfully. We'll contact you
                        shortly to confirm your appointment.</p>
                </div>
                <button id="closeSuccess" class="ml-2 sm:ml-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="w-full bg-gray-200 h-1 mt-3 sm:mt-4 rounded-full overflow-hidden">
                <div id="progressBar" class="bg-green-500 h-1 rounded-full" style="width: 0%"></div>
            </div>
        </div>

        <!-- Let's Talk Section -->

        <div class="container mx-auto px-4 mt-12 sm:mt-20">
            <div class="max-w-3xl mx-auto">
                <div class="text-center max-w-4xl mx-auto mb-6 sm:mb-8 md:mb-12 fade-in-element">
                    <div
                        class="inline-flex items-center px-3 sm:px-4 md:px-6 py-1.5 sm:py-2 md:py-3 bg-white/90 backdrop-blur-sm text-blue-600 rounded-full text-xs sm:text-sm font-semibold mb-3 sm:mb-4 md:mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                        <i class="fas fa-comment-dots mr-1 sm:mr-2 text-blue-500 text-xs"></i>
                        Contact Us
                    </div>
                    <h2
                        class="text-xl sm:text-2xl md:text-3xl lg:text-4xl font-bold mb-2 sm:mb-3 md:mb-4 bg-gradient-to-r from-blue-700 via-blue-600 to-blue-800 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                        Send Us a Message
                    </h2>
                    <p class="text-sm sm:text-base md:text-lg text-gray-600 max-w-2xl mx-auto stagger-fade-delay-3">
                        You can use the form below to send us your concerns, inquiries, or
                        clarifications. We'll respond as soon as possible to assist you.
                    </p>
                    <div
                        class="w-16 sm:w-20 h-0.5 sm:h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-3 sm:mt-4 md:mt-6 stagger-fade-delay-4">
                    </div>
                </div>

                <div class="space-y-4 sm:space-y-6 md:space-y-8">
                    <form
                        class="space-y-3 sm:space-y-4 md:space-y-6 glass-card rounded-xl sm:rounded-2xl p-4 sm:p-6 md:p-8 shadow-lg border border-blue-50 transition-all hover:shadow-xl fade-in-element stagger-fade-delay-2">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 sm:gap-4 md:gap-6">
                            <div class="group">
                                <div class="relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3 sm:pl-4 flex items-center pointer-events-none">
                                        <i
                                            class="fas fa-user text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                    </div>
                                    <input type="text" placeholder="    Name"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white text-sm"
                                        required>
                                </div>
                            </div>
                            <div class="group">
                                <div class="relative">
                                    <div
                                        class="absolute inset-y-0 left-0 pl-3 sm:pl-4 flex items-center pointer-events-none">
                                        <i
                                            class="fas fa-envelope text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                    </div>
                                    <input type="email" placeholder="    Email Address"
                                        class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white text-sm"
                                        required>
                                </div>
                            </div>
                        </div>
                        <div class="group">
                            <div class="relative">
                                <div
                                    class="absolute top-2.5 sm:top-3 md:top-4 left-3 sm:left-4 flex items-start pointer-events-none">
                                    <i
                                        class="fas fa-comment text-gray-400 group-hover:text-blue-500 text-xs sm:text-sm"></i>
                                </div>
                                <textarea rows="4" placeholder="    Message"
                                    class="w-full p-2.5 sm:p-3 md:p-4 pl-8 sm:pl-10 rounded-lg border-2 border-gray-200 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white text-sm"
                                    required></textarea>
                            </div>
                        </div>
                        <div>
                            <button type="submit"
                                class="w-full md:w-auto px-4 sm:px-6 md:px-8 py-2.5 sm:py-3 md:py-4 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-lg hover:from-blue-700 hover:to-blue-800 transition-all shadow-md hover:shadow-lg transform hover:-translate-y-1 flex items-center justify-center text-sm sm:text-base">
                                <span>Send Message</span>
                                <i class="fas fa-paper-plane ml-1 sm:ml-2 text-xs sm:text-sm"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php
    include 'includes/footer.php';
    ?>

    <script>
        // Loading animation
        window.addEventListener('load', function () {
            const loader = document.getElementById('loader');
            setTimeout(() => {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 1000);
            }, 1500);
        });

        // Handle appointment form submission with enhanced UI feedback
        document.addEventListener('DOMContentLoaded', function () {
            const appointmentForm = document.getElementById('appointmentForm');
            const successMessage = document.getElementById('successMessage');
            const progressBar = document.getElementById('progressBar');
            const closeSuccessBtn = document.getElementById('closeSuccess');

            if (appointmentForm) {
                appointmentForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    // Show loading state on button
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Processing...';
                    submitBtn.disabled = true;

                    // Simulate form processing (would be an AJAX call in production)
                    setTimeout(() => {
                        // Reset button
                        submitBtn.innerHTML = originalBtnText;
                        submitBtn.disabled = false;

                        // Show success message with animation
                        successMessage.classList.remove('hidden');
                        successMessage.classList.add('animate-bounce');

                        // Animate progress bar
                        let width = 0;
                        const progressInterval = setInterval(() => {
                            if (width >= 100) {
                                clearInterval(progressInterval);
                                setTimeout(() => {
                                    successMessage.classList.add('hidden');
                                }, 500);
                            } else {
                                width += 1;
                                progressBar.style.width = width + '%';
                            }
                        }, 50);

                        // Reset form
                        appointmentForm.reset();

                        // Auto-hide success message after 5 seconds
                        setTimeout(() => {
                            successMessage.classList.remove('animate-bounce');
                            successMessage.classList.add('animate-fadeOut');
                            setTimeout(() => {
                                successMessage.classList.add('hidden');
                                successMessage.classList.remove('animate-fadeOut');
                            }, 500);
                        }, 5000);
                    }, 1500);
                });
            }

            // Close success message button
            if (closeSuccessBtn) {
                closeSuccessBtn.addEventListener('click', function () {
                    successMessage.classList.add('animate-fadeOut');
                    setTimeout(() => {
                        successMessage.classList.add('hidden');
                        successMessage.classList.remove('animate-fadeOut');
                    }, 500);
                });
            }

            // Set minimum date for the date picker to today
            const dateInput = document.getElementById('preferredDate');
            if (dateInput) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.min = today;

                // Set a default date (today + 3 days)
                const defaultDate = new Date();
                defaultDate.setDate(defaultDate.getDate() + 3);
                dateInput.value = defaultDate.toISOString().split('T')[0];
            }

            // Add form field animations
            const formGroups = document.querySelectorAll('.group');
            formGroups.forEach(group => {
                const input = group.querySelector('input, select, textarea');
                if (input) {
                    input.addEventListener('focus', () => {
                        group.classList.add('is-focused');
                    });

                    input.addEventListener('blur', () => {
                        group.classList.remove('is-focused');
                    });
                }
            });
        });
    </script>


    <script>
        // Add intersection observer for fade-in elements
        document.addEventListener('DOMContentLoaded', function () {
            // Check if it's a mobile device
            const isMobile = window.innerWidth < 768;

            // If it's a mobile device, immediately show hero elements without animation
            if (isMobile) {
                document.querySelectorAll('.hero-element').forEach(el => {
                    el.classList.add('visible');
                    el.style.opacity = '1';
                    el.style.transform = 'none';
                });
            }

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    // Skip hero elements on mobile
                    if (isMobile && entry.target.classList.contains('hero-element')) {
                        return;
                    }

                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');

                        // If there are children with stagger classes, make them visible too
                        const staggeredElements = entry.target.querySelectorAll('[class*="stagger-fade-delay"]');
                        staggeredElements.forEach(el => {
                            el.classList.add('visible');
                        });
                    }
                });
            }, {
                threshold: 0.1,
                rootMargin: '0px 0px -20px 0px' // Reduced from -50px to -20px for mobile
            });

            // Observe all fade-in elements
            document.querySelectorAll('.fade-in-element').forEach(element => {
                observer.observe(element);
            });

            // Add touch device detection for better mobile experience
            const isTouchDevice = 'ontouchstart' in window || navigator.msMaxTouchPoints;

            if (isTouchDevice) {
                document.body.classList.add('touch-device');

                // Make hover styles work better on touch devices
                document.querySelectorAll('.hero-btn, .group, .glass-card').forEach(el => {
                    el.addEventListener('touchstart', function () {
                        this.classList.add('touch-active');
                    }, { passive: true });

                    el.addEventListener('touchend', function () {
                        setTimeout(() => {
                            this.classList.remove('touch-active');
                        }, 300);
                    }, { passive: true });
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            // Increased loading time to 3.5 seconds total
            setTimeout(() => {
                const loader = document.querySelector('.loader-wrapper');
                if (loader) {
                    loader.classList.add('fade-out');

                    // Increased fade-out transition to 1 second
                    setTimeout(() => {
                        loader.style.display = 'none';
                    }, 1000); // Increased from 500ms to 1000ms
                }
            }, 2500); // Increased from 2000ms to 2500ms
        });

        // Optional: Hide loader when all content is fully loaded
        window.addEventListener('load', function () {
            const loader = document.querySelector('.loader-wrapper');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 1000); // Increased fade-out time
            }
        });

        // Make sure viewport adjusts for mobile devices
        function adjustViewport() {
            const viewportMeta = document.querySelector('meta[name="viewport"]');
            if (window.innerWidth < 768) {
                viewportMeta.content = 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no';
            }
        }

        window.addEventListener('resize', adjustViewport);
        document.addEventListener('DOMContentLoaded', adjustViewport);
    </script>

    <!-- Include FAQs Modal -->
    <?php include('includes/faqs_modal.php'); ?>

    <!-- Include User Guide Modal -->
    <?php include('includes/user_guide_modal.php'); ?>
</body>

</html>