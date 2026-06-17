<?php
// FAQs Modal Include File
// This file contains the FAQs modal that can be included in any page
if (!defined('FAQS_MODAL_INCLUDED')) {
    define('FAQS_MODAL_INCLUDED', true);
}
?>

<style>
/* Custom styles for the FAQ side panel */
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
#faqsModalContent {
    transition: transform 0.3s ease-out;
}

/* Focus styles for accessibility */
.focus-visible-ring:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
}

/* Animation for FAQ answers */
.faq-answer {
    transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
}

/* Remove background image from About BPAMIS section */
#about-bpamis {
    background-image: none !important;
    background-color: #ffffff !important;
}

/* Ensure all FAQ items have white background */
.faq-item {
    background-color: #ffffff;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Mobile Modal Container */
    #faqsModalContent {
        width: 100% !important;
        max-width: 100% !important;
    }

    /* Mobile Typography - Smaller font sizes */
    #faqsModalContent .text-lg {
        font-size: 0.8rem !important;
        line-height: 1.4;
        font-weight: 600;
    }

    #faqsModalContent h2 {
        font-size: 1rem !important;
        line-height: 1.3;
    }

    #faqsModalContent h3 {
        font-size: 0.9rem !important;
        line-height: 1.4;
        margin-bottom: 0.75rem !important;
    }

    #faqsModalContent h4 {
        font-size: 0.85rem !important;
        line-height: 1.4;
        font-weight: 500;
    }

    #faqsModalContent p {
        font-size: 0.8rem !important;
        line-height: 1.6;
    }

    #faqsModalContent .text-sm {
        font-size: 0.75rem !important;
        line-height: 1.5;
    }

    /* Mobile Header */
    #faqsModalContent .bg-gradient-to-r {
        padding: 1rem !important;
    }

    #faqsModalContent .bg-gradient-to-r h2 {
        font-size: 0.9rem !important;
    }

    #faqsModalContent .bg-gradient-to-r .text-xl {
        font-size: 1rem !important;
    }

    #faqsModalContent .bg-gradient-to-r .text-lg {
        font-size: 0.9rem !important;
    }

    /* Mobile Content Area */
    #faqsModalContent .p-4 {
        padding: 1rem !important;
    }

    #faqsModalContent .space-y-6 > div {
        margin-bottom: 1.5rem !important;
    }

    /* Mobile FAQ Items */
    #faqsModalContent .faq-item {
        margin-bottom: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }

    #faqsModalContent .faq-question {
        padding: 0.75rem 0 !important;
    }

    #faqsModalContent .faq-answer {
        padding-top: 0.75rem !important;
        padding-left: 0.75rem !important;
    }

    #faqsModalContent .faq-answer p {
        margin-bottom: 0.75rem !important;
    }

    /* Mobile Lists */
    #faqsModalContent .list-disc,
    #faqsModalContent .list-decimal {
        padding-left: 1.25rem !important;
    }

    #faqsModalContent .list-disc li,
    #faqsModalContent .list-decimal li {
        margin-bottom: 0.5rem !important;
        font-size: 0.75rem !important;
        line-height: 1.5;
    }

    /* Mobile Icons */
    #faqsModalContent .fa-chevron-down {
        font-size: 0.8rem !important;
    }

    /* Mobile Touch Targets */
    #faqsModalContent .faq-question,
    #faqsModalContent button {
        min-height: 44px;
    }

    /* Mobile Help Section */
    #faqsModalContent .mt-6 {
        margin-top: 1.5rem !important;
    }

    #faqsModalContent .pt-4 {
        padding-top: 1rem !important;
    }

    #faqsModalContent .mb-3 {
        margin-bottom: 0.75rem !important;
    }

    #faqsModalContent .my-3 {
        margin: 0.75rem 0 !important;
    }

    #faqsModalContent .p-3 {
        padding: 0.75rem !important;
    }

    /* Mobile Scrollbar */
    #faqsModalContent::-webkit-scrollbar {
        width: 6px;
    }

    #faqsModalContent::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    #faqsModalContent::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    #faqsModalContent::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }
}

/* Extra Small Mobile Devices */
@media (max-width: 480px) {
    #faqsModalContent .text-lg {
        font-size: 0.75rem !important;
    }

    #faqsModalContent h2 {
        font-size: 0.9rem !important;
    }

    #faqsModalContent h3 {
        font-size: 0.85rem !important;
    }

    #faqsModalContent h4 {
        font-size: 0.8rem !important;
    }

    #faqsModalContent p {
        font-size: 0.75rem !important;
    }

    #faqsModalContent .text-sm {
        font-size: 0.7rem !important;
    }

    #faqsModalContent .list-disc li,
    #faqsModalContent .list-decimal li {
        font-size: 0.7rem !important;
    }

    #faqsModalContent .bg-gradient-to-r {
        padding: 0.75rem !important;
    }

    #faqsModalContent .p-4 {
        padding: 0.75rem !important;
    }

    #faqsModalContent .faq-question {
        padding: 0.625rem 0 !important;
    }

    #faqsModalContent .faq-answer {
        padding-top: 0.625rem !important;
        padding-left: 0.625rem !important;
    }
}

/* Landscape Mobile Orientation */
@media (max-width: 768px) and (orientation: landscape) {
    #faqsModalContent {
        max-height: 85vh;
    }

    #faqsModalContent .bg-gradient-to-r {
        padding: 0.75rem !important;
    }

    #faqsModalContent .p-4 {
        padding: 0.75rem !important;
    }

    #faqsModalContent h3 {
        font-size: 0.85rem !important;
        margin-bottom: 0.5rem !important;
    }

    #faqsModalContent .space-y-6 > div {
        margin-bottom: 1rem !important;
    }

    #faqsModalContent .faq-item {
        margin-bottom: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }
}
</style>

<!-- FAQs Side Panel -->
<div id="faqsModal" class="fixed inset-0 hidden z-50" aria-labelledby="faqs-title" role="dialog" aria-modal="true">
    <!-- Background overlay with blur -->
    <div class="fixed inset-0 bg-black bg-opacity-50 backdrop-blur-[4px]" aria-hidden="true" onclick="closeFaqsModal()"></div>
    
    <!-- Side panel container -->
    <div class="flex justify-end h-full">
        <!-- Side panel with responsive sizing -->
        <div id="faqsModalContent" class="relative bg-white shadow-xl w-full sm:max-w-md md:max-w-lg h-full flex flex-col transform transition-all ease-out duration-300 translate-x-full" tabindex="-1">
        <!-- Side panel header (fixed) -->
        <div class="bg-gradient-to-r from-blue-700 to-blue-500 p-4 flex justify-between items-center sticky top-0 z-10">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-white p-2 rounded-full">
                    <i class="fas fa-circle-question text-blue-600 text-xl"></i>
                </div>
                <h2 id="faqs-title" class="ml-3 text-lg font-bold text-white">Frequently Asked Questions</h2>
            </div>
            <button class="text-white bg-blue-600 bg-opacity-20 hover:bg-opacity-30 rounded-full p-2 focus:outline-none focus:ring-2 focus:ring-white" onclick="closeFaqsModal()" aria-label="Close">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <div class="p-4 overflow-y-auto flex-grow custom-scrollbar bg-white">
            <div class="space-y-6 bg-white">
                <!-- FAQ Category: About BPAMIS -->
                <div id="about-bpamis" class="bg-white">
                    <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">About BPAMIS</h3>
                    
                    <!-- FAQ Item 1 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq1')" data-faq="faq1">
                            <h4 class="font-medium text-gray-800">What is BPAMIS?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq1" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>BPAMIS (Barangay Panducot Adjudication Management Information System) is a digital platform designed to streamline barangay justice administration. It helps manage case records, schedule hearings, and monitor the progress of disputes brought before the barangay justice system.</p>
                        </div>
                    </div>
                    
                    <!-- FAQ Item 2 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq2')" data-faq="faq2">
                            <h4 class="font-medium text-gray-800">Who can use BPAMIS?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq2" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>BPAMIS is designed for multiple user types including:</p>
                            <ul class="list-disc pl-5 mt-2 space-y-1">
                                <li>Barangay Secretary - For case management and record keeping</li>
                                <li>Barangay Captain - For case oversight and decision making</li>
                                <li>Lupon Tagapamayapa - For case mediation and resolution</li>
                                <li>Residents - For filing complaints and checking case status</li>
                                <li>External Complainants - For non-residents to file complaints</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- FAQ Item 3 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq3')" data-faq="faq3">
                            <h4 class="font-medium text-gray-800">Is my data secure in BPAMIS?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq3" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>Yes, BPAMIS employs modern security measures including:</p>
                            <ul class="list-disc pl-5 mt-2 space-y-1">
                                <li>Encrypted data storage and transmission</li>
                                <li>Role-based access controls</li>
                                <li>Regular security audits</li>
                                <li>Compliance with Philippine Data Privacy Act</li>
                            </ul>
                            <p class="mt-2">We take the confidentiality of case information very seriously and implement best practices to ensure data protection.</p>
                        </div>
                    </div>
                    
                    <!-- FAQ Item 4 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq4')" data-faq="faq4">
                            <h4 class="font-medium text-gray-800">How do I create an account?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq4" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>To create an account:</p>
                            <ol class="list-decimal pl-5 mt-2 space-y-1">
                                <li>Click on the "Register" button on the homepage</li>
                                <li>Select your user type (Resident or External Complainant)</li>
                                <li>Fill in the required information</li>
                                <li>Verify your email address</li>
                                <li>Wait for account approval (for certain user types)</li>
                            </ol>
                            <p class="mt-2">For Barangay officials, accounts are created by the system administrator.</p>
                        </div>
                    </div>
                </div>
                
                <!-- FAQ Category: Using BPAMIS -->
                <div id="using-bpamis">
                    <h3 class="text-lg font-semibold text-blue-700 border-b border-blue-100 pb-2">Using BPAMIS</h3>
                    
                    <!-- FAQ Item 5 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq5')" data-faq="faq5">
                            <h4 class="font-medium text-gray-800">How do I file a complaint?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq5" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>To file a complaint:</p>
                            <ol class="list-decimal pl-5 mt-2 space-y-1">
                                <li>Log in to your BPAMIS account</li>
                                <li>Navigate to "Submit Complaint" in the dashboard</li>
                                <li>Fill out the complaint form with all required details</li>
                                <li>Upload any supporting documents</li>
                                <li>Submit the form</li>
                            </ol>
                            <p class="mt-2">You'll receive a confirmation and tracking number for your complaint.</p>
                        </div>
                    </div>
                    
                    <!-- FAQ Item 6 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq6')" data-faq="faq6">
                            <h4 class="font-medium text-gray-800">How can I check my case status?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq6" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>To check your case status:</p>
                            <ol class="list-decimal pl-5 mt-2 space-y-1">
                                <li>Log in to your BPAMIS account</li>
                                <li>Go to "View Cases" in the dashboard</li>
                                <li>Select the case you want to check</li>
                            </ol>
                            <p class="mt-2">You'll see the current stage of your case, scheduled hearings, and any updates from the barangay officials.</p>
                        </div>
                    </div>
                    
                    <!-- FAQ Item 7 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq7')" data-faq="faq7">
                            <h4 class="font-medium text-gray-800">What types of disputes can be handled?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq7" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>BPAMIS can handle various types of disputes that fall under the jurisdiction of the Katarungang Pambarangay, including:</p>
                            <ul class="list-disc pl-5 mt-2 space-y-1">
                                <li>Neighborly disputes</li>
                                <li>Minor property conflicts</li>
                                <li>Small monetary claims</li>
                                <li>Interpersonal conflicts</li>
                                <li>Other civil disputes with penalties not exceeding one year imprisonment</li>
                            </ul>
                            <p class="mt-2">Criminal cases with heavier penalties are typically referred to the proper courts.</p>
                        </div>
                    </div>
                    
                    <!-- FAQ Item 8 -->
                    <div class="faq-item border-b border-gray-100 pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq8')" data-faq="faq8">
                            <h4 class="font-medium text-gray-800">What if I forget my password?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq8" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>If you forget your password:</p>
                            <ol class="list-decimal pl-5 mt-2 space-y-1">
                                <li>Click on "Forgot Password" on the login page</li>
                                <li>Enter your registered email address</li>
                                <li>Check your email for a password reset link</li>
                                <li>Follow the instructions to create a new password</li>
                            </ol>
                            <p class="mt-2">If you don't receive the email, check your spam folder or contact the Barangay office for assistance.</p>
                        </div>
                    </div>

                    <!-- FAQ Item 9 -->
                    <div class="faq-item pb-4">
                        <div class="faq-question flex justify-between items-center cursor-pointer py-2" onclick="toggleFaqAnswer('faq9')" data-faq="faq9">
                            <h4 class="font-medium text-gray-800">How do I schedule a hearing?</h4>
                            <i class="fas fa-chevron-down text-blue-500 transition-transform duration-300"></i>
                        </div>
                        <div id="faq9" class="faq-answer pt-2 pl-4 text-gray-600 hidden">
                            <p>For Barangay Officials:</p>
                            <ol class="list-decimal pl-5 mt-2 space-y-1">
                                <li>Log in to your BPAMIS account</li>
                                <li>Navigate to "Appoint Hearing" in the dashboard</li>
                                <li>Select the case from the list</li>
                                <li>Choose available date and time</li>
                                <li>Send notifications to all parties involved</li>
                            </ol>
                            <p class="mt-2">For residents, you'll receive notifications about scheduled hearings through email and the system dashboard.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Help Section -->
            <div class="mt-6 pt-4 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-blue-700 mb-3">Need More Help?</h3>
                <p class="text-gray-600 text-sm">If you couldn't find the answer to your question, please contact us:</p>
                
                <div class="bg-blue-50 p-3 rounded-lg border border-blue-100 my-3">
                    <p class="text-gray-700 flex items-start">
                        <i class="fas fa-external-link-alt text-blue-600 mt-1 mr-2"></i>
                        <span>Need help with National Barangay Policies or DILG Guidelines? Visit 
                            <a href="https://www.dilg.gov.ph/faqs/" target="_blank" class="text-blue-600 font-medium hover:text-blue-800 hover:underline inline-flex items-center">
                                DILG FAQs <i class="fas fa-arrow-right text-xs ml-1"></i>
                            </a>
                        </span>
                    </p>
                </div>
                
                <!-- <div class="flex flex-col mt-3 gap-2">
                    <a href="#" onclick="openContactModal(event);" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-envelope mr-2"></i> Send a Message
                    </a>
                    <a href="#" onclick="openModal(event);" class="inline-flex items-center justify-center px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-calendar-alt mr-2"></i> Schedule an Appointment
                    </a>
                </div> -->
            </div>
        </div>
        
    </div>
</div>

<!-- FAQs Side Panel JavaScript -->
<script>
    // Store focusable elements and last active element
    let focusableElements = [];
    let firstFocusableElement = null;
    let lastFocusableElement = null;

    // FAQs Side Panel Functions
    function openFaqsModal(event, faqCategory = null) {
        if (event) {
            event.preventDefault();
        }
        const modal = document.getElementById('faqsModal');
        const modalContent = document.getElementById('faqsModalContent');
        
        // Store last active element to restore focus later
        window.lastFaqActiveElement = document.activeElement;
        
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
            focusableElements = modalContent.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            
            if (focusableElements.length > 0) {
                firstFocusableElement = focusableElements[0];
                lastFocusableElement = focusableElements[focusableElements.length - 1];
                firstFocusableElement.focus();
            }
            
            // If a category is specified, scroll to it
            if (faqCategory) {
                const categoryElement = document.getElementById(faqCategory);
                if (categoryElement) {
                    categoryElement.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }, 100);
    }

    function closeFaqsModal() {
        const modal = document.getElementById('faqsModal');
        const modalContent = document.getElementById('faqsModalContent');
        
        modalContent.classList.remove('translate-x-0');
        modalContent.classList.add('translate-x-full');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');
            
            // Restore focus to the element that was active before opening the modal
            if (window.lastFaqActiveElement) {
                window.lastFaqActiveElement.focus();
            }
        }, 300);
    }

    // Toggle FAQ answers
    function toggleFaqAnswer(id) {
        const answer = document.getElementById(id);
        const icon = document.querySelector(`[data-faq="${id}"] i.fa-chevron-down`);
        
        if (answer.classList.contains('hidden')) {
            // Close other open FAQs (accordion behavior)
            document.querySelectorAll('.faq-answer').forEach(item => {
                if (item.id !== id && !item.classList.contains('hidden')) {
                    item.classList.add('hidden');
                    const otherIcon = document.querySelector(`[data-faq="${item.id}"] i`);
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
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('faqsModal')) {
            // Add click listener on the backdrop element directly
            const backdrop = document.querySelector('#faqsModal .fixed.inset-0.bg-black');
            if (backdrop) {
                backdrop.addEventListener('click', function() {
                    closeFaqsModal();
                });
            }
            
            // Handle keyboard navigation for accessibility
            document.addEventListener('keydown', function(event) {
                // Close modal when pressing Escape key
                if (event.key === 'Escape' && !document.getElementById('faqsModal').classList.contains('hidden')) {
                    closeFaqsModal();
                    return;
                }
                
                // Trap focus inside modal when Tab key is pressed
                if (event.key === 'Tab' && !document.getElementById('faqsModal').classList.contains('hidden')) {
                    // If shift key is also pressed and focus is on first element, move to last element
                    if (event.shiftKey && document.activeElement === firstFocusableElement) {
                        event.preventDefault();
                        lastFocusableElement.focus();
                    }
                    // If focus is on last element, move to first element
                    else if (!event.shiftKey && document.activeElement === lastFocusableElement) {
                        event.preventDefault();
                        firstFocusableElement.focus();
                    }
                }
            });
            
            // Make FAQ links open the specific FAQ category
            document.querySelectorAll('[data-faq-category]').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const category = this.getAttribute('data-faq-category');
                    openFaqsModal(e, category);
                });
            });
        }
    });

    // Helper function to create FAQs links
    function createFaqLink(text, category = null, classes = '') {
        return `<a href="#" data-faq-category="${category}" class="faq-link ${classes}">${text}</a>`;
    }
</script>