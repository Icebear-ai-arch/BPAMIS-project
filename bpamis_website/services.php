<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPAMIS - Barangay Panducot Adjudication Management Information System</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="styles/auth.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/png" href="/BPAMIS/SecMenu/logo.png" />

    <style>
        body {}

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


        .news-card {
            transition: transform 0.3s ease;
        }

        .news-card:hover {
            transform: translateY(-5px);
        }

        .service-card {
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .gradient-bg {
            background: linear-gradient(to right, #f0f7ff, #e0effe);
        }

        .card-hover {
            transition: all 0.3s ease;
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .loader-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }

        .loader {
            position: relative;
            width: 120px;
            height: 120px;
        }

        .loader-circle {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 4px solid transparent;
            border-top-color: #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loader-circle:nth-child(2) {
            border-top-color: transparent;
            border-right-color: #2563eb;
            animation-duration: 0.8s;
        }

        .loader-circle:nth-child(3) {
            border-top-color: transparent;
            border-right-color: transparent;
            border-bottom-color: #2563eb;
            animation-duration: 0.6s;
        }

        .fade-in-element {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
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

        .loader-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards 0.5s;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
            }
        }

        .fade-out {
            opacity: 0;
            pointer-events: none;
        }

        /* Map container styles */
        .map-container {
            position: relative;
            transition: all 0.3s ease;
        }

        .map-container:hover {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px);
        }

        .service-block {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .service-block.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .service-block:hover .relative>div:first-child {
            transform: rotate(0);
        }

        @media (max-width: 768px) {
            .service-block {
                padding: 2rem 1rem;
            }
        }

        .process-steps {
            padding: 2rem;
            position: relative;
        }

        .bg-white-blue-white {
            background: linear-gradient(180deg, #fff 0%, #2563eb 40%, #fff 100%);
        }

        .step-container {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
        }

        .step-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .step-content {
            margin-left: 1rem;
            flex-grow: 1;
        }

        .step-connector {
            position: absolute;
            left: 1.75rem;
            top: 3.5rem;
            width: 2px;
            height: 2rem;
            background-color: #e5e7eb;
            z-index: 1;
        }

        .step-container:last-child .step-connector {
            display: none;
        }

        .step-container:hover .step-icon {
            transform: scale(1.1);
            box-shadow: 0 0 0 8px rgba(59, 130, 246, 0.1);
        }

        @media (max-width: 768px) {

             /* Improved mobile spacing */
            .container {
                padding-left: 1rem;
                padding-right: 1rem;
            }

            .process-steps {
                padding: 1rem;
            }

            .step-icon {
                width: 3rem;
                height: 3rem;
            }

            .step-content h4 {
                font-size: 0.9rem;
            }

            .step-content p {
                font-size: 0.8rem;
            }

            .step-connector {
                height: 1.5rem;
            }

            .hero-btn {
                padding: 0.6rem 1rem;
                display: block;
                width: 70%;
                margin: 0 auto 0.8rem auto;
                font-size: 0.8rem;
            }
        }

        .service-item {
            transition: all 0.3s ease;
            padding: 1.5rem;
            border-radius: 0.75rem;
        }

        .service-item:hover {
            background-color: #F3E8FF;
            transform: translateX(10px);
        }

        .service-item:hover .w-12 {
            background-color: #9333EA;
        }

        .service-item:hover .w-12 i {
            color: white;
        }

        @media (max-width: 768px) {
            .service-item {
                padding: 1rem;
            }

            .service-item h4 {
                font-size: 1rem;
            }

            .service-item p {
                font-size: 0.875rem;
            }
        }

        .hero-btn {
            position: relative;
            overflow: hidden;
            transform: translateY(0);
            transition: all 0.3s ease;
            padding: 1rem 2.5rem;
            font-size: 1rem;
            border-radius: 9999px;
            font-weight: 600;
            display: inline-block;
            border: 2px solid transparent;
            text-decoration: none;
            margin: 0 42px 12.8px 42px;
        }

        .hero-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%) rotate(45deg);
            transition: transform 0.6s ease;
        }

        .hero-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .hero-btn:hover::before {
            transform: translateX(100%) rotate(45deg);
        }

        .hero-btn-primary {
            background: white;
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

        /* Mobile responsiveness for hero section */
        @media (max-width: 768px) {
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

        /* Extra small devices */
        @media (max-width: 640px) {
            /* Container width and padding control for small screens */
            .container {
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }

            .hero-section h1.text-7xl {
                font-size: 2rem;
            }

            .hero-section p.text-xl {
                font-size: 20px;
            }
            
            /* BPAMIS Core Services section responsiveness */
            /* Services Section Title */
            .text-6xl.lg\:text-5xl {
                font-size: 2.25rem;
                line-height: 1.2;
                margin-bottom: 1rem;
            }

            .text-4xl{
                font-size: 1.5rem;
            }
            
            .py-20 {
                padding-top: 3rem;
                padding-bottom: 3rem;
            }
            
            .mb-20 {
                margin-bottom: 2rem;
            }
            
            .service-block {
                margin-bottom: 2.5rem;
            }
            
            /* Service Cards */
            .service-block .p-8 {
                padding: 1rem;
            }
            
            .service-block h3.text-2xl {
                font-size: 1.5rem;
            }
            
            .service-block .space-y-4 {
                margin-top: 0.75rem;
            }
            
            .service-block .gap-3 {
                font-size: 0.85rem;
            }
            
            /* Process Steps */
            .step-icon {
                width: 2.5rem;
                height: 2.5rem;
            }
            
            .step-icon i {
                font-size: 1.25rem;
            }
            
            .step-content h4 {
                font-size: 0.85rem;
            }
            
            .step-content p {
                font-size: 0.75rem;
            }
            
            .service-item h4 {
                font-size: 1rem;
            }
            
            .service-item p {
                font-size: 0.85rem;
            }
            .hero-btn {
                font-size: 0.8rem;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 480px) {

            .container {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            .hero-section h1.text-7xl {
                font-size: 2rem;
            }

            .hero-section p.text-xl {
                font-size: 20px;
            }

            .hero-btn {
                font-size: 0.8rem;
            }
            
            /* BPAMIS Core Services extreme mobile adjustments */
            .text-6xl.lg\:text-5xl {
                font-size: 1.75rem;
            }

            .text-4xl{
                font-size: 1.5rem;
            }
            
            .text-center.max-w-4xl p.text-xl {
                font-size: 0.9rem;
                padding: 0 0.5rem;
            }
            
            .inline-flex.items-center.px-6.py-3 {
                padding: 0.4rem 0.75rem;
                font-size: 0.7rem;
            }
            
            .service-block .mb-6 {
                margin-bottom: 0.75rem;
            }
            
            .service-block .mb-8 {
                margin-bottom: 0.75rem;
            }
            
            .service-block .w-12.h-12 {
                width: 2.5rem;
                height: 2.5rem;
            }
            
            .service-block .w-12.h-12 i {
                font-size: 1.1rem;
            }
            
            .space-y-24 > div {
                margin-bottom: 3rem;
            }
            
            /* Smaller process steps on small screens */
            .step-container {
                margin-bottom: 1.25rem;
            }
            
            .step-connector {
                height: 1.25rem;
                left: 1.25rem;
            }
            
            /* Service items adjustments */
            .service-item {
                padding: 0.75rem;
            }
            
            .service-item .gap-4 {
                gap: 0.75rem;
            }
        }

        /* Specific layout adjustments for service blocks on mobile */
        @media (max-width: 768px) {
            
        }

        @media (max-width: 768px) {
            .hero-section .flex.justify-center.gap-4 {
                flex-direction: column;
                width: 100%;
                padding: 0 1rem;
                gap: 0.5rem;
                align-items: center;
            }
            .hero-btn {
                display: block;
                width: 100%;
                margin: 0 0 0.5rem 0;
            }
            .hero-btn:last-child {
                margin-bottom: 0;
            }
        }
    </style>
</head>

<body>
    <!-- Add this at the very top of the body -->
    <div class="loader-wrapper">
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
                Community-Centered Services</h1>
            <p class="text-sm md:text-xl mb-6 md:mb-8 fade-in-element stagger-fade-delay-2 hero-element">Support &
                Modernizing Barangay Adjudication Through Integrated Digital Solutions</p>
            <div class="flex justify-center gap-4 fade-in-element stagger-fade-delay-3 hero-element">
                <a href="register.php" class="hero-btn hero-btn-primary">Get Started</a>
                <a href="#" onclick="openUserGuideModal(event)" class="hero-btn hero-btn-secondary">View User Guide</a>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-12 md:py-20 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center max-w-4xl mx-auto mb-12 md:mb-20 fade-in-element"
                style="margin-top: 10px;">
                <div
                    class="inline-flex items-center px-3 md:px-6 py-2 md:py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-xs md:text-sm font-semibold mb-4 md:mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Support for Local Justice
                </div>
                <h2
                    class="text-3xl md:text-6xl lg:text-5xl font-bold mb-4 md:mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    BPAMIS Core Services
                </h2>
                <p class="text-sm md:text-xl text-gray-600 leading-relaxed max-w-1xl mx-auto stagger-fade-delay-3 px-2">
                    Streamlining justice and document services for the Barangay needs.
                </p>
                <div class="w-16 md:w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-4 md:mt-8 stagger-fade-delay-4"></div>
            </div>
            <!-- Services Grid -->
            <div class="space-y-16 md:space-y-24">
                <!-- Barangay Cases -->
                <div class="service-block" data-aos="fade-up">
                    <div class="flex flex-col lg:flex-row items-center gap-8 md:gap-12">
                        <div class="lg:w-1/2">
                            <div class="relative">
                                <div
                                    class="absolute -inset-4 bg-blue-100 rounded-2xl transform -rotate-3 transition-transform group-hover:rotate-0">
                                </div>
                                <div class="relative bg-white p-4 md:p-8 rounded-xl shadow-lg">
                                    <div class="flex items-center gap-3 md:gap-4 mb-4 md:mb-6">
                                        <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-gavel text-lg md:text-2xl text-white"></i>
                                        </div>
                                        <h3 class="text-lg md:text-2xl font-bold text-gray-800">Barangay Cases</h3>
                                    </div>
                                    <p class="text-sm md:text-base text-gray-600 mb-6 md:mb-8">Efficient management and resolution of barangay
                                        disputes and cases</p>
                                    <div class="space-y-3 md:space-y-4">
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-blue-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Online filing of complaints and disputes</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-blue-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Real-time case tracking and updates</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-blue-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Automated scheduling of hearings</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-blue-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Digital documentation and record-keeping</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Process Steps for Barangay Cases -->
                        <div class="lg:w-1/2">
                            <div class="process-steps">
                                <div class="step-container">
                                    <div class="step-icon bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white">
                                        <i class="fas fa-file-signature text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">File Complaint</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Submit your case details online</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div class="step-icon bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white">
                                        <i class="fas fa-clipboard-check text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Case Review</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Initial assessment by officials and validations.</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div class="step-icon bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white">
                                        <i class="fas fa-calendar-alt text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Schedule Hearing</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Set mediation date and time</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div class="step-icon bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white">
                                        <i class="fas fa-handshake text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Mediation</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Resolve disputes through dialogue</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div class="step-icon bg-blue-100 text-blue-600 hover:bg-blue-600 hover:text-white">
                                        <i class="fas fa-check-circle text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Resolution</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Case closed with documentation</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Document Processing -->
                <div class="service-block" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex flex-col lg:flex-row-reverse items-center gap-8 md:gap-12">
                        <div class="lg:w-1/2">
                            <div class="relative">
                                <div
                                    class="absolute -inset-4 bg-green-100 rounded-2xl transform rotate-3 transition-transform group-hover:rotate-0">
                                </div>
                                <div class="relative bg-white p-4 md:p-8 rounded-xl shadow-lg">
                                    <div class="flex items-center gap-3 md:gap-4 mb-4 md:mb-6">
                                        <div class="w-10 h-10 md:w-12 md:h-12 bg-green-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-file-alt text-lg md:text-2xl text-white"></i>
                                        </div>
                                        <h3 class="text-lg md:text-2xl font-bold text-gray-800">Document Processing</h3>
                                    </div>
                                    <p class="text-sm md:text-base text-gray-600 mb-6 md:mb-8">Easy access to barangay certificates and other
                                        official documents</p>
                                    <div class="space-y-3 md:space-y-4">
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-green-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Online document request submission</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-green-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Digital certificate verification</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-green-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Secure document storage</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-green-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Real-time processing status updates</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Process Steps for Document Processing -->
                        <div class="lg:w-1/2">
                            <div class="process-steps">
                                <div class="step-container">
                                    <div class="step-icon bg-green-100 text-green-600 hover:bg-green-600 hover:text-white">
                                        <i class="fas fa-file-alt text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Request Document</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Submit a request for barangay documents.</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div
                                        class="step-icon bg-green-100 text-green-600 hover:bg-green-600 hover:text-white">
                                        <i class="fas fa-clipboard-list text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Verify Requirements</h4>
                                        <p class="text-xs md:text-sm text-gray-600">System checks and validates uploaded documents.</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div
                                        class="step-icon bg-green-100 text-green-600 hover:bg-green-600 hover:text-white">
                                        <i class="fas fa-cogs text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Process Request</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Barangay staff reviews and processing the request.</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div
                                        class="step-icon bg-green-100 text-green-600 hover:bg-green-600 hover:text-white">
                                        <i class="fas fa-certificate text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Digital Certificate Issuance</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Secured issuance of document digital copies.</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div
                                        class="step-icon bg-green-100 text-green-600 hover:bg-green-600 hover:text-white">
                                        <i class="fas fa-chart-line text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Track Status</h4>
                                        <p class="text-xs md:text-sm text-gray-600">Users receive real-time updates on request
                                            status.</p>
                                    </div>
                                    <div class="step-connector"></div>
                                </div>

                                <div class="step-container">
                                    <div
                                        class="step-icon bg-green-100 text-green-600 hover:bg-green-600 hover:text-white">
                                        <i class="fas fa-shield-alt text-lg md:text-2xl"></i>
                                    </div>
                                    <div class="step-content">
                                        <h4 class="text-sm md:text-base font-semibold text-gray-800">Secure Access & Archiving</h4>
                                        <p class="text-xs md:text-sm text-gray-600">All documents are stored in a secure database.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resident Services -->
                <div class="service-block" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex flex-col lg:flex-row items-center gap-8 md:gap-12">
                        <div class="lg:w-1/2">
                            <div class="relative">
                                <div
                                    class="absolute -inset-4 bg-purple-100 rounded-2xl transform -rotate-3 transition-transform group-hover:rotate-0">
                                </div>
                                <div class="relative bg-white p-4 md:p-8 rounded-xl shadow-lg">
                                    <div class="flex items-center gap-3 md:gap-4 mb-4 md:mb-6">
                                        <div
                                            class="w-10 h-10 md:w-12 md:h-12 bg-purple-600 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-users text-lg md:text-2xl text-white"></i>
                                        </div>
                                        <h3 class="text-lg md:text-2xl font-bold text-gray-800">Resident Services</h3>
                                    </div>
                                    <p class="text-sm md:text-base text-gray-600 mb-6 md:mb-8">Comprehensive resident information and assistance
                                        management</p>
                                    <div class="space-y-3 md:space-y-4">
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-purple-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Online resident profile management</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-purple-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Community announcements and updates</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-purple-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Digital assistance requests</span>
                                        </div>
                                        <div class="flex items-center gap-2 md:gap-3 text-gray-700">
                                            <i class="fas fa-check-circle text-purple-600 text-sm md:text-base"></i>
                                            <span class="text-xs md:text-base">Online appointment scheduling</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Process Steps for Resident Services -->
                        <div class="lg:w-1/2">
                            <div class="space-y-4 md:space-y-6 p-4 md:p-6 bg-white rounded-xl shadow-lg">
                                <!-- Online Resident Profile Management -->
                                <div class="service-item">
                                    <div class="flex items-start gap-3 md:gap-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-user-circle text-lg md:text-2xl text-purple-600"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow">
                                            <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1 md:mb-2">Online Resident Profile
                                                Management</h4>
                                            <p class="text-xs md:text-base text-gray-600">Maintain accurate resident information including
                                                household details, personal data, and residency status in a centralized,
                                                secure system.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Community Announcements -->
                                <div class="service-item">
                                    <div class="flex items-start gap-3 md:gap-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-bullhorn text-lg md:text-2xl text-purple-600"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow">
                                            <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1 md:mb-2">Community Announcements
                                                and Updates</h4>
                                            <p class="text-xs md:text-base text-gray-600">Stay informed about barangay news, events, public
                                                advisories, and emergency alerts through a centralized announcements
                                                platform.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Digital Assistance -->
                                <div class="service-item">
                                    <div class="flex items-start gap-3 md:gap-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-hands-helping text-lg md:text-2xl text-purple-600"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow">
                                            <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1 md:mb-2">Digital Assistance
                                                Requests</h4>
                                            <p class="text-xs md:text-base text-gray-600">Submit requests for services such as barangay
                                                clearances, certifications, and other support directly from your device
                                                without needing to visit in person.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Online Appointment -->
                                <div class="service-item">
                                    <div class="flex items-start gap-3 md:gap-4">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                                <i class="fas fa-calendar-check text-lg md:text-2xl text-purple-600"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow">
                                            <h4 class="text-sm md:text-lg font-semibold text-gray-800 mb-1 md:mb-2">Online Appointment
                                                Scheduling</h4>
                                            <p class="text-xs md:text-base text-gray-600">Book appointments with barangay officials or staff
                                                for services like consultations, document processing, or dispute
                                                hearings with real-time availability tracking.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-12 md:py-20 bg-white-blue-white">
        <div class="container mx-auto px-4">

            <div class="text-center max-w-4xl mx-auto mb-12 md:mb-20 fade-in-element mt-8 md:mt-16">

                <div
                    class="inline-flex items-center px-3 md:px-6 py-2 md:py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-xs md:text-sm font-semibold mb-4 md:mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Barangay Officials
                </div>
                <h2
                    class="text-3xl md:text-6xl lg:text-5xl font-bold mb-4 md:mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    Barangay Officials and Their Roles
                </h2>
                <p class="text-sm md:text-xl text-gray-600 leading-relaxed max-w-1xl mx-auto stagger-fade-delay-3 px-2">
                    This section outlines the different barangay officials roles within BPAMIS and their corresponding
                    responsibilities and access levels.
                </p>
                <div class="w-16 md:w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-4 md:mt-8 stagger-fade-delay-4"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12">
                <!-- Barangay Secretary Card -->
                <div class="user-role-card fade-in-element stagger-fade-delay-1">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-4 md:p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl user-role-inner ripple-effect">
                        <div class="role-gradient-bg"></div>
                        <div class="role-particles">
                            <div class="particle" style="left: 20%; animation-delay: 0s;"></div>
                            <div class="particle" style="left: 40%; animation-delay: 1s;"></div>
                            <div class="particle" style="left: 60%; animation-delay: 2s;"></div>
                            <div class="particle" style="left: 80%; animation-delay: 3s;"></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="w-16 h-16 md:w-20 md:h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6 role-icon-container">
                                <i class="fas fa-user-tie text-2xl md:text-3xl text-blue-600 role-icon"></i>
                            </div>
                            <h4 class="text-lg md:text-xl font-bold text-center text-gray-800 mb-3 md:mb-4 role-title">Barangay Secretary
                            </h4>
                            <div class="space-y-2 md:space-y-3 text-gray-600">
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Manages case documents and resident records</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Schedules and coordinates hearings</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Primary administrator of BPAMIS platform</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barangay Captain Card -->
                <div class="user-role-card fade-in-element stagger-fade-delay-2">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-4 md:p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl user-role-inner ripple-effect">
                        <div class="role-gradient-bg"></div>
                        <div class="role-particles">
                            <div class="particle" style="left: 25%; animation-delay: 0.5s;"></div>
                            <div class="particle" style="left: 45%; animation-delay: 1.5s;"></div>
                            <div class="particle" style="left: 65%; animation-delay: 2.5s;"></div>
                            <div class="particle" style="left: 85%; animation-delay: 3.5s;"></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="w-16 h-16 md:w-20 md:h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6 role-icon-container">
                                <i class="fas fa-user-shield text-2xl md:text-3xl text-green-600 role-icon"></i>
                            </div>
                            <h4 class="text-lg md:text-xl font-bold text-center text-gray-800 mb-3 md:mb-4 role-title">Barangay Captain
                            </h4>
                            <div class="space-y-2 md:space-y-3 text-gray-600">
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-green-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Oversees case resolutions and approvals</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-green-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Approves official documents</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-green-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Monitors barangay activities through BPAMIS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lupon Tagapamayapa Card -->
                <div class="user-role-card fade-in-element stagger-fade-delay-3">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-4 md:p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl user-role-inner ripple-effect">
                        <div class="role-gradient-bg"></div>
                        <div class="role-particles">
                            <div class="particle" style="left: 30%; animation-delay: 1s;"></div>
                            <div class="particle" style="left: 50%; animation-delay: 2s;"></div>
                            <div class="particle" style="left: 70%; animation-delay: 3s;"></div>
                            <div class="particle" style="left: 90%; animation-delay: 4s;"></div>
                        </div>
                        <div class="relative z-10">
                            <div
                                class="w-16 h-16 md:w-20 md:h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6 role-icon-container">
                                <i class="fas fa-balance-scale text-2xl md:text-3xl text-purple-600 role-icon"></i>
                            </div>
                            <h4 class="text-lg md:text-xl font-bold text-center text-gray-800 mb-3 md:mb-4 role-title">Lupon Tagapamayapa
                            </h4>
                            <div class="space-y-2 md:space-y-3 text-gray-600">
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Handles dispute mediation processes</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Accesses and manages case files</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3 role-feature">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base">Updates resolution status through BPAMIS</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Who Can Access Our Services Section -->

        <div class="container mx-auto px-4 mt-8 md:mt-16">
            <div class="text-center max-w-4xl mx-auto mb-12 md:mb-20 fade-in-element mt-8 md:mt-16">

                <div
                    class="inline-flex items-center px-3 md:px-6 py-2 md:py-3 bg-white/80 backdrop-blur-sm text-blue-700 rounded-full text-xs md:text-sm font-semibold mb-4 md:mb-6 border border-blue-200/50 shadow-sm stagger-fade-delay-1">
                    <i class="fas fa-star mr-2 text-blue-500"></i>
                    Beneficiaries
                </div>
                <h2
                    class="text-2xl md:text-3xl lg:text-5xl font-bold mb-4 md:mb-8 bg-gradient-to-r from-gray-900 via-blue-800 to-gray-900 bg-clip-text text-transparent leading-tight stagger-fade-delay-2">
                    Who Can Access Our Services
                </h2>
                <p class="text-sm md:text-xl text-gray-600 leading-relaxed max-w-1xl mx-auto stagger-fade-delay-3 px-2">
                    This section outlines the different user roles within BPAMIS and their corresponding
                    accessibility to the services provided.
                </p>
                <div class="w-16 md:w-24 h-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full mx-auto mt-4 md:mt-8 stagger-fade-delay-4"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
                <!-- Residents Card -->
                <div class="user-card group slide-in-left stagger-fade-delay-2">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-4 md:p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl">
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>
                        <div class="relative">
                            <div class="flex items-center gap-4 md:gap-6 mb-6 md:mb-8">
                                <div class="w-12 h-12 md:w-16 md:h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-home text-2xl md:text-3xl text-blue-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg md:text-2xl font-bold text-gray-800">Residents</h3>
                                    <p class="text-blue-600 text-sm md:text-base">Full Service Access</p>
                                </div>
                            </div>
                            <div class="space-y-3 md:space-y-4">
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">Create and manage personal BPAMIS accounts</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">File complaints and track case progress</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">Request official documents online</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-blue-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">Access community announcements and updates</span>
                                </div>
                                <a href="register.php"
                                    class="mt-4 md:mt-6 inline-flex items-center text-blue-600 hover:text-blue-700 text-sm md:text-base">
                                    Register as Resident
                                    <i
                                        class="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- External Complainant Card -->
                <div class="user-card group slide-in-right stagger-fade-delay-1">
                    <div
                        class="relative bg-white rounded-xl shadow-lg p-4 md:p-8 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl">
                        <div
                            class="absolute -inset-1 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity">
                        </div>
                        <div class="relative">
                            <div class="flex items-center gap-4 md:gap-6 mb-6 md:mb-8">
                                <div class="w-12 h-12 md:w-16 md:h-16 bg-purple-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user-friends text-2xl md:text-3xl text-purple-600"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg md:text-2xl font-bold text-gray-800">External Complainant</h3>
                                    <p class="text-purple-600 text-sm md:text-base">Limited Service Access</p>
                                </div>
                            </div>
                            <div class="space-y-3 md:space-y-4">
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">File complaints against Panducot residents</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">Track case status and updates remotely</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">Receive hearing notifications</span>
                                </div>
                                <div class="flex items-start gap-2 md:gap-3">
                                    <i class="fas fa-check-circle text-purple-600 mt-1 text-sm md:text-base"></i>
                                    <span class="text-xs md:text-base text-gray-600">Access case-related documents</span>
                                </div>
                                <a href="register.php?type=external"
                                    class="mt-4 md:mt-6 inline-flex items-center text-purple-600 hover:text-purple-700 text-sm md:text-base">
                                    Register as External Complainant
                                    <i
                                        class="fas fa-arrow-right ml-2 transition-transform group-hover:translate-x-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <?php
    include 'includes/footer.php';
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Increased loading time to 3.5 seconds total
            setTimeout(() => {
                const loader = document.querySelector('.loader-wrapper');
                loader.classList.add('fade-out');

                // Increased fade-out transition to 1 second
                setTimeout(() => {
                    loader.remove();
                }, 1000); // Increased from 500ms to 1000ms
            }, 2500); // Increased from 2000ms to 2500ms
        });

        // Optional: Hide loader when all content is fully loaded
        window.addEventListener('load', function () {
            const loader = document.querySelector('.loader-wrapper');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.remove();
                }, 1000); // Increased fade-out time
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1
            });

            document.querySelectorAll('.service-block, .fade-in-element').forEach(block => {
                observer.observe(block);
            });
            
            // Initialize scroll observer for other animation elements
            const scrollObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1
            });

            // Observe all scroll-animated elements
            document.querySelectorAll('.scroll-animate, .slide-in-left, .slide-in-right, .scale-in, .flip-in, .text-reveal').forEach(el => {
                scrollObserver.observe(el);
            });
        }); 
    </script>

    <!-- Include FAQs Modal -->
    <?php include('includes/faqs_modal.php'); ?>

    <!-- Include User Guide Modal -->
    <?php include('includes/user_guide_modal.php'); ?>

    <!-- Include Schedule Appointment Modal -->
    <?php include('includes/schedule_appointment_modal.php'); ?>

</body>

</html>