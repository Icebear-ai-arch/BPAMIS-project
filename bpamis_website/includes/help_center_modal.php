<?php
// Help Center Modal Include File
// This file contains the Help Center modal that can be included in any page
if (!defined('HELP_CENTER_MODAL_INCLUDED')) {
    define('HELP_CENTER_MODAL_INCLUDED', true);
}
?>

<style>
/* Custom styles for the Help Center side panel */
.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(59, 130, 246, 0.5) rgba(243, 244, 246, 1);
}

.custom-scrollbar::-webkit-scrollbar {
    width: 8px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(243, 244, 246, 1);
    border-radius: 4px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: rgba(59, 130, 246, 0.5);
    border-radius: 4px;
}

/* Smooth slide in/out transition */
#helpCenterModalContent {
    transition: transform 0.3s ease-out;
}

/* Focus styles for accessibility */
.focus-visible-ring:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

/* Animation for help answers */
.help-answer {
    transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Mobile Modal Container */
    #helpCenterModalContent {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* Mobile Typography - Smaller font sizes */
    #helpCenterModalContent .text-lg {
        font-size: 0.8rem !important;
        line-height: 1.4;
        font-weight: 600;
    }

    #helpCenterModalContent h2 {
        font-size: 1rem !important;
        line-height: 1.3;
    }

    #helpCenterModalContent h3 {
        font-size: 0.9rem !important;
        line-height: 1.4;
        margin-bottom: 0.75rem !important;
    }

    #helpCenterModalContent h4 {
        font-size: 0.85rem !important;
        line-height: 1.4;
        font-weight: 500;
    }

    #helpCenterModalContent p {
        font-size: 0.8rem !important;
        line-height: 1.6;
    }

    #helpCenterModalContent .text-sm {
        font-size: 0.75rem !important;
        line-height: 1.5;
    }

    /* Mobile Header */
    #helpCenterModalContent .bg-gradient-to-r {
        padding: 1rem !important;
    }

    #helpCenterModalContent .bg-gradient-to-r h2 {
        font-size: 0.9rem !important;
    }

    #helpCenterModalContent .bg-gradient-to-r .text-xl {
        font-size: 1rem !important;
    }

    #helpCenterModalContent .bg-gradient-to-r .text-lg {
        font-size: 0.9rem !important;
    }

    /* Mobile Content Area */
    #helpCenterModalContent .p-4 {
        padding: 1rem !important;
    }

    #helpCenterModalContent .space-y-6 > div {
        margin-bottom: 1.5rem !important;
    }

    /* Mobile Help Items */
    #helpCenterModalContent .help-item {
        margin-bottom: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }

    #helpCenterModalContent .help-question {
        padding: 0.75rem 0 !important;
    }

    #helpCenterModalContent .help-answer {
        padding-top: 0.75rem !important;
        padding-left: 0.75rem !important;
    }

    #helpCenterModalContent .help-answer p {
        margin-bottom: 0.75rem !important;
    }

    /* Mobile Lists */
    #helpCenterModalContent .list-disc,
    #helpCenterModalContent .list-decimal {
        padding-left: 1.25rem !important;
    }

    #helpCenterModalContent .list-disc li,
    #helpCenterModalContent .list-decimal li {
        margin-bottom: 0.5rem !important;
        font-size: 0.75rem !important;
        line-height: 1.5;
    }

    /* Mobile Icons */
    #helpCenterModalContent .fa-chevron-down {
        font-size: 0.8rem !important;
    }

    /* Mobile Touch Targets */
    #helpCenterModalContent .help-question,
    #helpCenterModalContent button {
        min-height: 44px;
    }

    /* Mobile Help Section */
    #helpCenterModalContent .mt-6 {
        margin-top: 1.5rem !important;
    }

    #helpCenterModalContent .pt-4 {
        padding-top: 1rem !important;
    }

    #helpCenterModalContent .mb-3 {
        margin-bottom: 0.75rem !important;
    }

    #helpCenterModalContent .my-3 {
        margin: 0.75rem 0 !important;
    }

    #helpCenterModalContent .p-3 {
        padding: 0.75rem !important;
    }

    /* Mobile Contact Information */
    #helpCenterModalContent .mt-4 {
        margin-top: 1rem !important;
    }

    #helpCenterModalContent .mb-6 {
        margin-bottom: 1.5rem !important;
    }

    #helpCenterModalContent .space-y-3 > div {
        margin-bottom: 0.75rem !important;
    }

    #helpCenterModalContent .flex.items-start {
        gap: 0.75rem;
    }

    #helpCenterModalContent .flex.items-start i {
        font-size: 0.875rem !important;
        margin-top: 0.125rem !important;
    }

    #helpCenterModalContent .flex.items-start p {
        font-size: 0.75rem !important;
        line-height: 1.4;
    }

    #helpCenterModalContent .flex.items-start .text-sm {
        font-size: 0.7rem !important;
    }

    /* Mobile Buttons */
    #helpCenterModalContent .flex.flex-col.gap-2 {
        gap: 0.5rem !important;
    }

    #helpCenterModalContent .flex.flex-col.gap-2 a {
        padding: 0.625rem 0.875rem !important;
        font-size: 0.8rem !important;
    }

    /* Mobile Scrollbar */
    #helpCenterModalContent::-webkit-scrollbar {
        width: 6px;
    }

    #helpCenterModalContent::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    #helpCenterModalContent::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    #helpCenterModalContent::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
}

/* Extra Small Mobile Devices */
@media (max-width: 480px) {
    #helpCenterModalContent .text-lg {
        font-size: 0.75rem !important;
    }

    #helpCenterModalContent h2 {
        font-size: 0.9rem !important;
    }

    #helpCenterModalContent h3 {
        font-size: 0.85rem !important;
    }

    #helpCenterModalContent h4 {
        font-size: 0.8rem !important;
    }

    #helpCenterModalContent p {
        font-size: 0.75rem !important;
    }

    #helpCenterModalContent .text-sm {
        font-size: 0.7rem !important;
    }

    #helpCenterModalContent .list-disc li,
    #helpCenterModalContent .list-decimal li {
        font-size: 0.7rem !important;
    }

    #helpCenterModalContent .bg-gradient-to-r {
        padding: 0.75rem !important;
    }

    #helpCenterModalContent .p-4 {
        padding: 0.75rem !important;
    }

    #helpCenterModalContent .help-question {
        padding: 0.625rem 0 !important;
    }

    #helpCenterModalContent .help-answer {
        padding-top: 0.625rem !important;
        padding-left: 0.625rem !important;
    }

    #helpCenterModalContent .flex.items-start p {
        font-size: 0.7rem !important;
    }

    #helpCenterModalContent .flex.items-start .text-sm {
        font-size: 0.65rem !important;
    }

    #helpCenterModalContent .flex.flex-col.gap-2 a {
        font-size: 0.75rem !important;
        padding: 0.5rem 0.75rem !important;
    }
}

/* Landscape Mobile Orientation */
@media (max-width: 768px) and (orientation: landscape) {
    #helpCenterModalContent {
        max-height: 85vh;
    }

    #helpCenterModalContent .bg-gradient-to-r {
        padding: 0.75rem !important;
    }

    #helpCenterModalContent .p-4 {
        padding: 0.75rem !important;
    }

    #helpCenterModalContent h3 {
        font-size: 0.85rem !important;
        margin-bottom: 0.5rem !important;
    }

    #helpCenterModalContent .space-y-6 > div {
        margin-bottom: 1rem !important;
    }

    #helpCenterModalContent .help-item {
        margin-bottom: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
}
</style>

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
                                <a href="../bpamis_website/register.php"
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
                                <a href="../bpamis_website/login.php"
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
                        <a href="#" onclick="closeHelpCenterModal(); setTimeout(() => openModal(event), 10);"
                            class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200 transition-colors">
                            <i class="fas fa-calendar-alt mr-2"></i> Schedule an Appointment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Store focusable elements and last active element
let helpFocusableElements = [];
let helpFirstFocusableElement = null;
let helpLastFocusableElement = null;
let guideCategory = null;

// Help Center Side Panel Functions
function openHelpCenterModal(event) {
    if (event) event.preventDefault();
    const modal = document.getElementById('helpCenterModal');
    const modalContent = document.getElementById('helpCenterModalContent');

    // Store last active element for restoring focus later
    window.lastHelpActiveElement = document.activeElement;

    modal.classList.remove('hidden');
    // Trigger reflow
    void modalContent.offsetWidth;
    modalContent.classList.remove('translate-x-full');
    modalContent.classList.add('translate-x-0');

    // Prevent body scrolling when modal is open
    document.body.classList.add('overflow-hidden');

    // Set focus on modal for accessibility
    setTimeout(() => {
        modalContent.focus();

        // Get all focusable elements
        helpFocusableElements = modalContent.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );

        if (helpFocusableElements.length > 0) {
            helpFirstFocusableElement = helpFocusableElements[0];
            helpLastFocusableElement = helpFocusableElements[helpFocusableElements.length - 1];
            helpFirstFocusableElement.focus();
        }

        // If a category is specified, scroll to it
        if (guideCategory) {
            const categoryElement = document.getElementById(guideCategory);
            if (categoryElement) {
                categoryElement.scrollIntoView({ behavior: 'smooth' });
            }
        }
    }, 100);
}

function closeHelpCenterModal() {
    const modal = document.getElementById('helpCenterModal');
    const modalContent = document.getElementById('helpCenterModalContent');

    modalContent.classList.remove('translate-x-0');
    modalContent.classList.add('translate-x-full');

    setTimeout(() => {
        modal.classList.add('hidden');
        // Re-enable body scrolling
        document.body.classList.remove('overflow-hidden');

        // Restore focus to the element that was active before opening the modal
        if (window.lastHelpActiveElement) {
            window.lastHelpActiveElement.focus();
        }
    }, 300);
}

// Toggle help item answers
function toggleHelpAnswer(id) {
    const answer = document.getElementById(id);
    const icon = document.querySelector(`[data-help="${id}"] i.fa-chevron-down`);

    if (answer.classList.contains('hidden')) {
        // Close other open help items (accordion behavior)
        document.querySelectorAll('.help-answer').forEach(item => {
            if (item.id !== id && !item.classList.contains('hidden')) {
                item.classList.add('hidden');
                const otherIcon = document.querySelector(`[data-help="${item.id}"] i`);
                if (otherIcon) otherIcon.classList.remove('rotate-180');
            }
        });

        answer.classList.remove('hidden');
        icon.classList.add('rotate-180');

        // Scroll the answer into view with some offset
        setTimeout(() => {
            answer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    } else {
        answer.classList.add('hidden');
        icon.classList.remove('rotate-180');
    }
}

// Document ready event listener
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('helpCenterModal')) {
        // Add click listener on the backdrop element directly
        const backdrop = document.querySelector('#helpCenterModal .fixed.inset-0.bg-black');
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                closeHelpCenterModal();
            });
        }

        // Handle keyboard navigation for accessibility
        document.addEventListener('keydown', function (event) {
            // Close modal when pressing Escape key
            if (event.key === 'Escape' && !document.getElementById('helpCenterModal').classList.contains('hidden')) {
                closeHelpCenterModal();
                return;
            }

            // Trap focus inside modal when Tab key is pressed
            if (event.key === 'Tab' && !document.getElementById('helpCenterModal').classList.contains('hidden')) {
                // If shift key is also pressed and focus is on first element, move to last element
                if (event.shiftKey && document.activeElement === helpFirstFocusableElement) {
                    event.preventDefault();
                    helpLastFocusableElement.focus();
                }
                // If focus is on last element, move to first element
                else if (!event.shiftKey && document.activeElement === helpLastFocusableElement) {
                    event.preventDefault();
                    helpFirstFocusableElement.focus();
                }
            }
        });
    }
});
</script>
