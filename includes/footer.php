
<style>
    /* Custom styles for the Legal Modal */
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

    /* Mobile responsive styles for footer section */
    @media (max-width: 768px) {
      .text-sm {
        font-size: 0.7rem;
      }
    }

    @media (max-width: 640px) {
      .text-sm {
        font-size: 0.7rem;
      }
    }

    @media (max-width: 480px) {
      .text-sm {
        font-size: 0.7rem;
      }
    }

        /* Ensure footer layout isn't affected by page-wide mobile overrides */
        @media (max-width: 640px) {
                #siteFooter .container {
                        /* Restore horizontal gutters even if a global .container rule zeroes padding on mobile */
                        padding-left: 1rem !important; /* 16px */
                        padding-right: 1rem !important; /* 16px */
                        margin-left: auto !important;
                        margin-right: auto !important;
                }
        /* Force brand title to 1rem on mobile */
        #siteFooter .footer-brand {
            font-size: 1rem !important;
            line-height: 1.25rem; /* keep readable line-height */
        }
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

</style>

<footer id="siteFooter" class="relative bg-gradient-to-b from-white via-blue-50 to-blue-100 overflow-hidden">
    <!-- Premium Background Elements -->
    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 via-blue-600 to-blue-400"></div>
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-200 opacity-20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-blue-300 opacity-20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 right-0 w-64 h-64 bg-blue-100 opacity-30 rounded-full blur-2xl"></div>
        <div class="absolute bottom-1/4 left-0 w-72 h-72 bg-blue-100 opacity-30 rounded-full blur-2xl"></div>
    </div>

    <div class="container mx-auto px-4 py-6 md:py-10 relative z-10">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 md:gap-8 lg:gap-12">

            <!-- Column 1: BPAMIS Brand - Enhanced with better visuals -->
            <div class="col-span-1 md:col-span-4 lg:col-span-4">
                <div class="flex items-start space-x-3 md:space-x-4 mb-4 md:mb-6">
                    <div
                        class="flex items-center justify-center w-12 h-12 md:w-16 md:h-16 rounded-xl border-4 border-white bg-gradient-to-br from-blue-100 via-white to-blue-50 shadow-xl">
                        <a href="#"><img src="../assets/img/logo.png" alt="BPAMIS Logo" class="w-8 h-8 md:w-10 md:h-10 object-contain"></a>
                    </div>
                    <div>
                        <h3
                            class="text-lg md:text-xl font-bold bg-gradient-to-r from-blue-800 to-blue-600 bg-clip-text text-transparent mb-1 footer-brand">
                            BPAMIS</h3>
                        <p class="text-sm md:text-sm text-gray-600 leading-tight">Barangay Panducot Adjudication Management Information System
                        </p>
                    </div>
                </div>
                
            </div>

            

            <!-- Legal column now spans wider after removing Newsletter -->
            <div class="col-span-1 md:col-span-2 lg:col-span-0">
                <!-- This div is empty to maintain the grid layout after removing the newsletter section -->
            </div>
        </div>

        <!-- Bottom Bar - Enhanced with better visuals -->
        <div class="mt-12 md:mt-16 pt-6 md:pt-8 border-t border-blue-200">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="flex items-center mb-3 md:mb-0">
                    <p class="text-xs md:text-sm text-gray-600">&copy; <?php echo date('Y'); ?> BPAMIS. All Rights Reserved.</p>
                </div>
                <div class="flex flex-wrap justify-center gap-4 md:gap-6">
                    <a href="#" onclick="openLegalModal(event, 'privacy-policy')"
                        class="text-xs md:text-sm text-gray-600 hover:text-blue-700 transition-colors hover:underline">Privacy</a>
                    <a href="#" onclick="openLegalModal(event, 'terms-of-service')"
                        class="text-xs md:text-sm text-gray-600 hover:text-blue-700 transition-colors hover:underline">Terms</a>
                    <a href="#" onclick="openHelpCenterModal(event)"
                        class="text-xs md:text-sm text-gray-600 hover:text-blue-700 transition-colors hover:underline">Support</a>
                    <a href="#" onclick="openLegalModal(event, 'accessibility')"
                        class="text-xs md:text-sm text-gray-600 hover:text-blue-700 transition-colors hover:underline">Accessibility</a>
                     <a href="#" onclick="openUserGuideModal(event, 'user-guides')"
                        class="text-xs md:text-sm text-gray-600 hover:text-blue-700 transition-colors hover:underline">User Guides</a>
                
                
                    </div>
            </div>
        </div>
    </div>
</footer>

<!-- Help Center Side Panel -->
<div id="helpCenterModal" class="fixed inset-0 hidden z-50" aria-labelledby="help-center-title" role="dialog"
    aria-modal="true">
    <!-- Background overlay with blur -->
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-[4px]" aria-hidden="true"
        onclick="closeHelpCenterModal()"></div>

    <!-- Side panel container -->
    <div class="flex justify-end h-full">
        <!-- Side panel with responsive sizing -->
        <div id="helpCenterModalContent"
            class="relative bg-white shadow-xl w-full sm:max-w-md md:max-w-lg h-full flex flex-col transform transition-all ease-out duration-300 translate-x-full"
            tabindex="-1">
            <!-- Side panel header (fixed) -->
            <div
                class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 flex justify-between items-center sticky top-0 z-10">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-white p-2 rounded-full">
                        <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                    </div>
                    <h2 id="help-center-title" class="ml-3 text-lg font-bold text-white">Help Center</h2>
                </div>
                <button
                    class="text-white bg-blue-600 bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 focus:outline-none focus:ring-2 focus:ring-white"
                    onclick="closeHelpCenterModal()" aria-label="Close">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow custom-scrollbar bg-white">
                <div class="space-y-6 bg-white">
                    <!-- Help Category: Getting Started -->
                    <div id="getting-started" class="bg-white">
                        <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">Getting
                            Started</h3>

                        <!-- Help Item 1 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help1')" data-help="help1">
                                <h4 class="font-medium text-gray-800">Creating an Account</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help1" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>If you're a resident of Barangay Panducot, you can register for an account to
                                    access various services:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Click on the "Register" button on the homepage</li>
                                    <li>Fill in your personal information</li>
                                    <li>Create a secure password</li>
                                    <li>Submit your registration</li>
                                </ol>
                                <p class="mt-2">Your account will need to be verified before you can use all
                                    features.</p>
                                <a href="../register.php"
                                    class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Register
                                    Now <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        </div>

                        <!-- Help Item 2 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help2')" data-help="help2">
                                <h4 class="font-medium text-gray-800">Logging In</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help2" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>To log in to your BPAMIS account:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Click on the "Login" button on the homepage</li>
                                    <li>Enter your registered email/username</li>
                                    <li>Enter your password</li>
                                    <li>Click "Sign In"</li>
                                </ol>
                                <p class="mt-2">If you forgot your password, use the "Forgot Password" link on the
                                    login page.</p>
                                <a href="../login.php"
                                    class="mt-2 inline-block text-sm text-blue-600 hover:text-blue-800 font-medium">Log
                                    In <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        </div>

                        <!-- Help Item 3 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help3')" data-help="help3">
                                <h4 class="font-medium text-gray-800">System Navigation</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help3" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>Once logged in, you can access various features depending on your role:</p>
                                <ul class="list-disc pl-5 mt-2 space-y-1">
                                    <li>File complaints</li>
                                    <li>Track case status</li>
                                    <li>View hearing schedules</li>
                                    <li>Access relevant forms and documents</li>
                                </ul>
                                <p class="mt-2">The main navigation menu is on the left side of your dashboard and
                                    contains all accessible features for your user role.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Help Category: User Guides -->
                    <div id="user-guides">
                        <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">User Guides
                        </h3>

                        <!-- Help Item 4 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help4')" data-help="help4">
                                <h4 class="font-medium text-gray-800">Filing a Complaint</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help4" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Log in to your resident account</li>
                                    <li>Navigate to "Submit Complaints" in the menu</li>
                                    <li>Fill out the complaint form with all required details</li>
                                    <li>Upload any supporting documents if necessary</li>
                                    <li>Review your complaint information</li>
                                    <li>Submit your complaint</li>
                                </ol>
                                <p class="mt-3 text-sm text-gray-600">Note: Your complaint will be reviewed by the
                                    Barangay Secretary before proceeding to the next steps.</p>
                            </div>
                        </div>

                        <!-- Help Item 5 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help5')" data-help="help5">
                                <h4 class="font-medium text-gray-800">Tracking Your Case</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help5" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Log in to your account</li>
                                    <li>Go to "View Cases" in your dashboard</li>
                                    <li>Find your case in the list and click on it</li>
                                    <li>View the detailed status, updates, and next steps</li>
                                </ol>
                                <p class="mt-3 text-sm text-gray-600">You'll receive notifications when there are
                                    updates to your case.</p>
                            </div>
                        </div>

                        <!-- Help Item 6 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help6')" data-help="help6">
                                <h4 class="font-medium text-gray-800">Viewing Hearing Schedules</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help6" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Log in to your account</li>
                                    <li>Navigate to "View Cases" and select your case</li>
                                    <li>Look for the "Hearing Schedule" section</li>
                                    <li>You can also receive SMS/email notifications for upcoming hearings</li>
                                </ol>
                                <p class="mt-3 text-sm text-gray-600">If you need to reschedule, contact the
                                    Barangay Secretary as soon as possible.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Help Category: Troubleshooting -->
                    <div id="troubleshooting">
                        <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">
                            Troubleshooting</h3>

                        <!-- Help Item 7 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help7')" data-help="help7">
                                <h4 class="font-medium text-gray-800">Login Problems</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help7" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <p class="mb-2"><strong>Issue:</strong> Unable to log in to your account</p>
                                <p class="mb-2"><strong>Solutions:</strong></p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                    <li>Make sure you're using the correct email/username and password</li>
                                    <li>Check if Caps Lock is turned on</li>
                                    <li>Clear your browser cache and cookies</li>
                                    <li>Use the "Forgot Password" link to reset your password</li>
                                    <li>If problems persist, contact the support team</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Help Item 8 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help8')" data-help="help8">
                                <h4 class="font-medium text-gray-800">File Upload Issues</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help8" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <p class="mb-2"><strong>Issue:</strong> Unable to upload documents</p>
                                <p class="mb-2"><strong>Solutions:</strong></p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                    <li>Ensure the file size is under 5MB</li>
                                    <li>Use only supported file types (PDF, JPG, PNG)</li>
                                    <li>Check your internet connection</li>
                                    <li>Try using a different browser</li>
                                </ul>
                            </div>
                        </div>

                        <!-- Help Item 9 -->
                        <div class="help-item border-b border-gray-100 pb-4">
                            <div class="help-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleHelpAnswer('help9')" data-help="help9">
                                <h4 class="font-medium text-gray-800">Page Not Loading</h4>
                                <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                            </div>
                            <div id="help9" class="help-answer pt-2 pl-4 text-gray-600 hidden">
                                <p class="mb-2"><strong>Issue:</strong> Pages are not loading or loading
                                    incompletely</p>
                                <p class="mb-2"><strong>Solutions:</strong></p>
                                <ul class="list-disc pl-5 text-gray-700 space-y-1">
                                    <li>Refresh the page</li>
                                    <li>Clear your browser cache</li>
                                    <li>Check your internet connection</li>
                                    <li>Try using a different browser</li>
                                    <li>If using a mobile device, try on a desktop computer</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Help Category: Contact Support -->
                    <div id="contact-support">
                        <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">Contact
                            Support</h3>

                        <!-- Contact Information -->
                        <div class="mt-4 mb-6">
                            <h4 class="font-medium text-gray-800 mb-3">Support Contact Information</h4>
                            <div class="space-y-3">
                                <div class="flex items-start">
                                    <i class="fas fa-phone-alt text-blue-600 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-gray-700 font-medium">Phone Support</p>
                                        <p class="text-gray-600">+63 (xxx) xxx-xxxx</p>
                                        <p class="text-sm text-gray-500">Monday to Friday, 8:00 AM - 5:00 PM</p>
                                    </div>
                                </div>

                                <div class="flex items-start">
                                    <i class="fas fa-envelope text-blue-600 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-gray-700 font-medium">Email Support</p>
                                        <p class="text-gray-600">support@bpamis.gov.ph</p>
                                        <p class="text-sm text-gray-500">We aim to respond within 24 hours</p>
                                    </div>
                                </div>

                                <div class="flex items-start">
                                    <i class="fas fa-map-marker-alt text-blue-600 mt-1 mr-3"></i>
                                    <div>
                                        <p class="text-gray-700 font-medium">Visit Us</p>
                                        <p class="text-gray-600">Barangay Hall, Barangay Panducot</p>
                                        <p class="text-gray-600">Calumpit, Bulacan</p>
                                        <p class="text-sm text-gray-500">Monday to Friday, 8:00 AM - 5:00 PM</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Help Section -->
                <div class="mt-6 pt-4 border-t border-gray-200">
                    <h3 class="text-lg font-semibold text-blue-700 mb-3">Need More Help?</h3>
                    <p class="text-gray-600 text-sm">If you couldn't find your concerns and inquiries, please
                        contact us:</p>
                    <div class="flex flex-col mt-3 gap-2">
                        <a href="#" onclick="openContactModal(event);"
                            class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                            <i class="fas fa-envelope mr-2"></i> Contact Us Directly
                        </a>
                        <a href="#" onclick="openModal(event);"
                            class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-calendar-alt mr-2"></i> Schedule an Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- User Guide Side Panel -->
<div id="guideModal" class="fixed inset-0 hidden z-50" aria-labelledby="guide-title" role="dialog" aria-modal="true">
    <!-- Background overlay with blur -->
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-[4px]" aria-hidden="true"
        onclick="closeGuideModal()"></div>

    <!-- Side panel container -->
    <div class="flex justify-end h-full">
        <!-- Side panel with responsive sizing -->
        <div id="guideModalContent"
            class="relative bg-white shadow-xl w-full sm:max-w-md md:max-w-lg h-full flex flex-col transform transition-all ease-out duration-300 translate-x-full"
            tabindex="-1">
            <!-- Side panel header (fixed) -->
            <div
                class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 flex justify-between items-center sticky top-0 z-10">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-white p-2 rounded-full">
                        <i class="fas fa-book text-blue-600 text-xl"></i>
                    </div>
                    <h2 id="faqs-title" class="ml-3 text-lg font-bold text-white">User Guide Manual</h2>
                </div>
                <button
                    class="text-white bg-blue-600 bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 focus:outline-none focus:ring-2 focus:ring-white"
                    onclick="closeGuideModal()" aria-label="Close">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-grow guide-custom-scrollbar bg-white">
                <div class="space-y-6 bg-white">
                    <!-- Guide Category: Getting Started -->
                    <div id="getting-started" class="bg-white">
                        <h3 class="text-lg font-semibold text-emerald-700 border-b border-emerald-100 pb-2">Getting
                            Started</h3>

                        <!-- Guide Item 1 -->
                        <div class="guide-item border-b border-gray-100 pb-4">
                            <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleGuideAnswer('guide1')" data-guide="guide1">
                                <h4 class="font-medium text-gray-800">How to Access BPAMIS</h4>
                                <i class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                            </div>
                            <div id="guide1" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>To access the BPAMIS system:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Open your web browser and navigate to the BPAMIS website</li>
                                    <li>Click on the "Login" button in the top right corner</li>
                                    <li>Enter your username and password</li>
                                    <li>Click "Sign In" to access your dashboard</li>
                                </ol>
                                <p class="mt-2">If you don't have an account yet, you'll need to register first.
                                    Click on the "Register" button and follow the instructions.</p>
                            </div>
                        </div>

                        <!-- Guide Item 2 -->
                        <div class="guide-item border-b border-gray-100 pb-4">
                            <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleGuideAnswer('guide2')" data-guide="guide2">
                                <h4 class="font-medium text-gray-800">Understanding Your Dashboard</h4>
                                <i class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                            </div>
                            <div id="guide2" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>Your dashboard is customized based on your user role. Here are the key
                                    components:</p>
                                <ul class="list-disc pl-5 mt-2 space-y-1">
                                    <li><strong>Navigation Menu:</strong> Located on the left side, provides access
                                        to all system features</li>
                                    <li><strong>Quick Stats:</strong> Shows important numbers and metrics relevant
                                        to your role</li>
                                    <li><strong>Recent Activity:</strong> Displays your recent actions and
                                        notifications</li>
                                    <li><strong>Calendar:</strong> Shows upcoming hearings and important dates</li>
                                    <li><strong>Quick Actions:</strong> Buttons for common tasks like filing a new
                                        complaint</li>
                                </ul>
                                <p class="mt-2">Hover over any element on the dashboard for more information about
                                    its function.</p>
                            </div>
                        </div>

                        <!-- Guide Item 3 -->
                        <div class="guide-item border-b border-gray-100 pb-4">
                            <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleGuideAnswer('guide3')" data-guide="guide3">
                                <h4 class="font-medium text-gray-800">Updating Your Profile</h4>
                                <i class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                            </div>
                            <div id="guide3" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>To update your profile information:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Click on your profile icon in the top right corner</li>
                                    <li>Select "Profile Settings" from the dropdown menu</li>
                                    <li>Update your personal information, contact details, or password</li>
                                    <li>Click "Save Changes" to apply your updates</li>
                                </ol>
                                <p class="mt-2">It's important to keep your contact information up-to-date to
                                    receive notifications about case updates and hearing schedules.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Guide Category: Filing Complaints -->
                    <div id="filing-complaints" class="bg-white mt-8">
                        <h3 class="text-lg font-semibold text-emerald-700 border-b border-emerald-100 pb-2">Filing
                            Complaints</h3>

                        <!-- Guide Item 4 -->
                        <div class="guide-item border-b border-gray-100 pb-4">
                            <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleGuideAnswer('guide4')" data-guide="guide4">
                                <h4 class="font-medium text-gray-800">How to File a New Complaint</h4>
                                <i class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                            </div>
                            <div id="guide4" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>To file a new complaint in BPAMIS:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-2">
                                    <li>From your dashboard, click on "Submit Complaint" or navigate to the
                                        Complaints section in the menu</li>
                                    <li>Fill out the complaint form with all required information:
                                        <ul class="list-disc pl-5 mt-1">
                                            <li>Type of complaint</li>
                                            <li>Date and time of incident</li>
                                            <li>Respondent information (the person you're filing against)</li>
                                            <li>Detailed description of the incident</li>
                                        </ul>
                                    </li>
                                    <li>Upload any supporting documents or evidence (optional)</li>
                                    <li>Review your complaint details</li>
                                    <li>Click "Submit" to file your complaint</li>
                                </ol>
                                <p class="mt-2">After submission, you'll receive a confirmation with a complaint
                                    reference number. You can use this number to track the status of your complaint.
                                </p>
                            </div>
                        </div>

                        <!-- Guide Item 5 -->
                        <div class="guide-item border-b border-gray-100 pb-4">
                            <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleGuideAnswer('guide5')" data-guide="guide5">
                                <h4 class="font-medium text-gray-800">Tracking Your Complaint Status</h4>
                                <i class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                            </div>
                            <div id="guide5" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>To check the status of your submitted complaint:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-1">
                                    <li>Log in to your BPAMIS account</li>
                                    <li>Navigate to "My Complaints" or "View Complaints" in the menu</li>
                                    <li>Find your complaint in the list or search using your reference number</li>
                                    <li>Click on the complaint to view detailed status information</li>
                                </ol>
                                <p class="mt-2">Complaint statuses include:</p>
                                <ul class="list-disc pl-5 mt-2 space-y-1">
                                    <li><strong>Pending:</strong> Complaint has been received but not yet processed
                                    </li>
                                    <li><strong>Under Review:</strong> Barangay officials are reviewing the
                                        complaint</li>
                                    <li><strong>Scheduled:</strong> A hearing date has been set</li>
                                    <li><strong>Mediation:</strong> The case is in the mediation process</li>
                                    <li><strong>Resolved:</strong> The case has been settled</li>
                                    <li><strong>Referred:</strong> The case has been referred to another body</li>
                                </ul>
                                <p class="mt-2">You will receive notifications when your complaint status changes or
                                    when action is required from you.</p>
                            </div>
                        </div>

                        <!-- Guide Item 6 -->
                        <div class="guide-item border-b border-gray-100 pb-4">
                            <div class="guide-question flex justify-between items-center cursor-pointer py-2"
                                onclick="toggleGuideAnswer('guide6')" data-guide="guide6">
                                <h4 class="font-medium text-gray-800">Preparing for a Hearing</h4>
                                <i class="fas fa-chevron-down text-emerald-500 transition-transform duration-300"></i>
                            </div>
                            <div id="guide6" class="guide-answer pt-2 pl-4 text-gray-600 hidden">
                                <p>When your case is scheduled for a hearing, follow these steps to prepare:</p>
                                <ol class="list-decimal pl-5 mt-2 space-y-2">
                                    <li>Confirm your attendance through the system when you receive a hearing
                                        notification</li>
                                    <li>Prepare all relevant documents and evidence to support your case</li>
                                    <li>Review the hearing details in your notification:
                                        <ul class="list-disc pl-5 mt-1">
                                            <li>Date and time of the hearing</li>
                                            <li>Location (physical or virtual)</li>
                                            <li>Names of officials who will be present</li>
                                        </ul>
                                    </li>
                                    <li>If you need to reschedule, request a new date at least 3 days before the
                                        hearing</li>
                                    <li>Arrive at least 15 minutes early on the day of the hearing</li>
                                </ol>
                                <p class="mt-2">Remember to maintain respectful conduct during the hearing and
                                    follow the instructions of the Barangay officials.</p>
                            </div>
                        </div>
                    </div>
                </div>
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
                    <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Terms of Service
                    </h3>

                    <div class="space-y-4 text-gray-700">
                        <p>Last Updated: July 1, 2025</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">1. Agreement to Terms</h4>
                        <p>By accessing or using the Barangay Panducot Adjudication Management Information System
                            (BPAMIS), you agree to be bound by these Terms of Service and all applicable laws and
                            regulations. If you do not agree with any of these terms, you are prohibited from using or
                            accessing this system.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">2. Use License</h4>
                        <p>Permission is granted to temporarily access the materials within BPAMIS for personal,
                            non-commercial viewing only. This is the grant of a license, not a transfer of title, and
                            under this license you may not:</p>
                        <ul class="list-disc pl-5 space-y-1 mt-2">
                            <li>Modify or copy the materials</li>
                            <li>Use the materials for any commercial purpose</li>
                            <li>Attempt to decompile or reverse engineer any software contained in BPAMIS</li>
                            <li>Remove any copyright or other proprietary notations from the materials</li>
                            <li>Transfer the materials to another person or "mirror" the materials on any other server
                            </li>
                        </ul>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">3. Disclaimer</h4>
                        <p>The materials on BPAMIS are provided on an 'as is' basis. The Barangay Panducot makes no
                            warranties, expressed or implied, and hereby disclaims and negates all other warranties
                            including, without limitation, implied warranties or conditions of merchantability, fitness
                            for a particular purpose, or non-infringement of intellectual property or other violation of
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
                            imply endorsement by the Barangay Panducot of the site. Use of any such linked website is at
                            the user's own risk.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">7. Modifications</h4>
                        <p>The Barangay Panducot may revise these Terms of Service for BPAMIS at any time without
                            notice. By using this system, you are agreeing to be bound by the then current version of
                            these Terms of Service.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">8. Governing Law</h4>
                        <p>These terms and conditions are governed by and construed in accordance with the laws of the
                            Philippines and you irrevocably submit to the exclusive jurisdiction of the courts in that
                            location.</p>
                    </div>
                </section>

                <!-- Privacy Policy Section -->
                <section id="privacy-policy" class="legal-section mb-10">
                    <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Privacy Policy
                    </h3>

                    <div class="space-y-4 text-gray-700">
                        <p>Last Updated: July 1, 2025</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">1. Information We Collect</h4>
                        <p>BPAMIS collects several types of information from and about users of our system, including:
                        </p>
                        <ul class="list-disc pl-5 space-y-1 mt-2">
                            <li>Personal identifiable information such as name, postal address, email address, telephone
                                number, and government-issued IDs when you register for an account</li>
                            <li>Information about your internet connection, the equipment you use to access our system,
                                and usage details</li>
                            <li>Records and copies of your correspondence if you contact us</li>
                            <li>Details of transactions you carry out through our system and of the fulfillment of your
                                requests</li>
                        </ul>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">2. How We Use Your Information</h4>
                        <p>We use information that we collect about you or that you provide to us, including any
                            personal information:</p>
                        <ul class="list-disc pl-5 space-y-1 mt-2">
                            <li>To present our system and its contents to you</li>
                            <li>To provide you with information, products, or services that you request from us</li>
                            <li>To fulfill any other purpose for which you provide it</li>
                            <li>To carry out our obligations and enforce our rights arising from any contracts entered
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
                            <li>If we believe disclosure is necessary or appropriate to protect the rights, property, or
                                safety of the Barangay Panducot, our users, or others</li>
                        </ul>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">4. Data Security</h4>
                        <p>We have implemented measures designed to secure your personal information from accidental
                            loss and from unauthorized access, use, alteration, and disclosure. All information you
                            provide to us is stored on secure servers behind firewalls. Any sensitive information will
                            be encrypted using Secure Socket Layer (SSL) technology.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">5. Data Retention</h4>
                        <p>We will only retain your personal data for as long as necessary to fulfill the purposes we
                            collected it for, including for the purposes of satisfying any legal, accounting, or
                            reporting requirements.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">6. Your Rights</h4>
                        <p>Under the Data Privacy Act of 2012, you have rights concerning your personal data, including:
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
                            homepage. The date the privacy policy was last revised is identified at the top of the page.
                        </p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">8. Contact Information</h4>
                        <p>To ask questions or comment about this privacy policy and our privacy practices, contact us
                            at: info@bpamis.gov.ph</p>
                    </div>
                </section>

                <!-- Cookies Policy Section -->
                <section id="cookies-policy" class="legal-section mb-10">
                    <h3 class="text-xl font-semibold text-blue-800 mb-4 pb-2 border-b border-blue-100">Cookies Policy
                    </h3>

                    <div class="space-y-4 text-gray-700">
                        <p>Last Updated: July 1, 2025</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">1. What Are Cookies</h4>
                        <p>Cookies are small pieces of text sent by your web browser by a website you visit. A cookie
                            file is stored in your web browser and allows the system or a third-party to recognize you
                            and make your next visit easier and the system more useful to you.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">2. How BPAMIS Uses Cookies</h4>
                        <p>When you use and access BPAMIS, we may place a number of cookie files in your web browser. We
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
                            <li>Essential cookies: These are cookies that are required for the operation of BPAMIS. They
                                include, for example, cookies that enable you to log into secure areas of our system.
                            </li>
                            <li>Analytical/performance cookies: They allow us to recognize and count the number of
                                visitors and to see how visitors move around BPAMIS when they are using it. This helps
                                us to improve the way our system works, for example, by ensuring that users are finding
                                what they are looking for easily.</li>
                            <li>Functionality cookies: These are used to recognize you when you return to BPAMIS. This
                                enables us to personalize our content for you and remember your preferences.</li>
                            <li>Targeting cookies: These cookies record your visit to BPAMIS, the pages you have visited
                                and the links you have followed. We will use this information to make our system more
                                relevant to your interests.</li>
                        </ul>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">3. Third-Party Cookies</h4>
                        <p>In addition to our own cookies, we may also use various third-party cookies to report usage
                            statistics of the system and so on.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">4. What Are Your Choices Regarding Cookies
                        </h4>
                        <p>If you'd like to delete cookies or instruct your web browser to delete or refuse cookies,
                            please visit the help pages of your web browser.</p>
                        <p>Please note, however, that if you delete cookies or refuse to accept them, you might not be
                            able to use all of the features we offer, you may not be able to store your preferences, and
                            some of our pages might not display properly.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">5. Where Can You Find More Information About
                            Cookies</h4>
                        <p>You can learn more about cookies at the following third-party websites:</p>
                        <ul class="list-disc pl-5 space-y-1 mt-2">
                            <li>AllAboutCookies: <a href="https://www.allaboutcookies.org/"
                                    class="text-blue-600 hover:underline">https://www.allaboutcookies.org/</a></li>
                            <li>Network Advertising Initiative: <a href="https://www.networkadvertising.org/"
                                    class="text-blue-600 hover:underline">https://www.networkadvertising.org/</a></li>
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
                        <p>BPAMIS is committed to ensuring digital accessibility for people with disabilities. We are
                            continually improving the user experience for everyone, and applying the relevant
                            accessibility standards.</p>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">2. Conformance Status</h4>
                        <p>The Web Content Accessibility Guidelines (WCAG) defines requirements for designers and
                            developers to improve accessibility for people with disabilities. It defines three levels of
                            conformance: Level A, Level AA, and Level AAA. BPAMIS is partially conformant with WCAG 2.1
                            level AA. Partially conformant means that some parts of the content do not fully conform to
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

                        <h4 class="text-lg font-medium text-blue-700 mt-6">4. Assistive Technology Compatibility</h4>
                        <p>BPAMIS is designed to be compatible with the following assistive technologies:</p>
                        <ul class="list-disc pl-5 space-y-1 mt-2">
                            <li>Screen readers (NVDA, JAWS, VoiceOver)</li>
                            <li>Screen magnifiers</li>
                            <li>Speech recognition software</li>
                            <li>Keyboard-only navigation</li>
                        </ul>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">5. Known Limitations</h4>
                        <p>Despite our efforts to ensure accessibility of BPAMIS, there may be some limitations. Below
                            is a description of known limitations:</p>
                        <ul class="list-disc pl-5 space-y-1 mt-2">
                            <li>Some older PDF documents are not fully accessible to screen reader software</li>
                            <li>Some data visualizations may not include adequate text descriptions</li>
                            <li>Some third-party content may not be fully accessible</li>
                        </ul>

                        <h4 class="text-lg font-medium text-blue-700 mt-6">6. Feedback</h4>
                        <p>We welcome your feedback on the accessibility of BPAMIS. Please let us know if you encounter
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
                        <p>This accessibility statement was prepared on July 1, 2025 and was last reviewed on July 1,
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

<!-- Contact Modal -->
<div id="contactModal"
    class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center backdrop-blur-[4px]">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 transform transition-all opacity-0 scale-95"
        id="contactModalContent">
        <div class="grid md:grid-cols-2">
            <!-- Left side - Contact Information -->
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-8 text-white rounded-l-lg">
                <h3 class="text-2xl font-bold mb-6">Get in Touch</h3>
                <p class="mb-8">We'd love to hear from you. Send us a message and we'll respond as soon as possible.
                </p>

                <div class="space-y-6">
                    <div class="flex items-center space-x-4">
                        <div class="bg-white/10 p-3 rounded-lg">
                            <i class="fas fa-map-marker-alt text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold">Address</h4>
                            <p class="text-blue-100">Panducot, Calumpit, Bulacan</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="bg-white/10 p-3 rounded-lg">
                            <i class="fas fa-phone text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold">Phone</h4>
                            <p class="text-blue-100">+63 912 345 6789</p>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div class="bg-white/10 p-3 rounded-lg">
                            <i class="fas fa-envelope text-2xl"></i>
                        </div>
                        <div>
                            <h4 class="font-semibold">Email</h4>
                            <p class="text-blue-100">info@bpamis.gov.ph</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right side - Contact Form -->
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-bold text-gray-800">Send Message</h3>
                    <button class="text-gray-400 hover:text-gray-600" onclick="closeContactModal()">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>

                <form id="contactForm" class="space-y-4">
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Full Name</label>
                        <div class="relative">
                            <i class="fas fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required placeholder="Juan Dela Cruz">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="email"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required placeholder="juan@example.com">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Subject</label>
                        <div class="relative">
                            <i class="fas fa-tag absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <select
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required>
                                <option value="">Select a subject</option>
                                <option value="general">General Inquiry</option>
                                <option value="technical">Technical Support</option>
                                <option value="documents">Document Request</option>
                                <option value="complaint">File a Complaint</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700">Message</label>
                        <div class="relative">
                            <i class="fas fa-comment absolute left-3 top-3 text-gray-400"></i>
                            <textarea rows="4"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required placeholder="How can we help you?"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                            onclick="closeContactModal()">Cancel</button>
                        <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Send
                            Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Appointment Modal -->
<div id="appointmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 transform transition-all opacity-0 scale-95"
        id="modalContent">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-2xl font-bold text-gray-800">Schedule Appointment</h3>
                <button class="text-gray-400 hover:text-gray-600" onclick="closeModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <form id="appointmentForm" class="space-y-4">
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Purpose of Visit</label>
                    <select
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required>
                        <option value="">Select purpose</option>
                        <option value="document">Document Request</option>
                        <option value="complaint">File a Complaint</option>
                        <option value="mediation">Mediation Session</option>
                        <option value="clearance">Barangay Clearance</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Preferred Date</label>
                    <input type="date"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Preferred Time</label>
                    <select
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        required>
                        <option value="">Select time</option>
                        <option value="09:00">9:00 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="13:00">1:00 PM</option>
                        <option value="14:00">2:00 PM</option>
                        <option value="15:00">3:00 PM</option>
                        <option value="16:00">4:00 PM</option>
                    </select>
                </div>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Additional Notes</label>
                    <textarea
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        rows="3" placeholder="Any additional information..."></textarea>
                </div>

                <div class="flex justify-end space-x-3 mt-6">
                    <button type="button" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium"
                        onclick="closeModal()">Cancel</button>
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Include FAQs Modal -->

<!-- Footer -->
<?php
include '../bpamis_website/includes/faqs_modal.php';
?>

<!-- Include Help Center Modal -->
<?php include('../bpamis_website/includes/help_center_modal.php'); ?>

<!-- Include User Guide Modal -->
<?php include('../bpamis_website/includes/user_guide_modal.php'); ?>

<!-- Include Contact Support Modal -->
<?php include('../bpamis_website/includes/contact_support_modal.php'); ?>

<!-- Include Schedule Appointment Modal -->
<?php include('../bpamis_website/includes/schedule_appointment_modal.php'); ?>


<!-- Legal Modal JavaScript -->
<script>
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