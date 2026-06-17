<?php
// Help Center Modal Include File
// This file contains the Help Center modal that can be included in any page
if (!defined('USER_GUIDE_MODAL_INCLUDED')) {
    define('USER_GUIDE_MODAL_INCLUDED', true);
}
?>

<style>
    /* User Guide Modal Styles */
    #userGuideModal .transform {
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    #userGuideModal {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    /* Custom styles for the User Guide side panel */
    .guide-custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(59, 130, 246, 0.5) rgba(243, 244, 246, 1);
    }

    .guide-custom-scrollbar::-webkit-scrollbar {
        width: 8px;
    }

    .guide-custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(243, 244, 246, 1);
        border-radius: 4px;
    }

    .guide-custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: rgba(59, 130, 246, 0.5);
        border-radius: 4px;
    }

    /* Smooth slide in/out transition */
    #guideModalContent {
        transition: transform 0.3s ease-out;
    }

    /* Focus styles for accessibility */
    .guide-focus-visible-ring:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
    }

    /* Animation for guide answers */
    .guide-answer {
        transition: max-height 0.3s ease-out, opacity 0.3s ease-out;
    }

    /* Remove background image from categories */
    #getting-started,
    #filing-complaints {
        background-image: none !important;
        background-color: #ffffff !important;
    }

    /* Ensure all guide items have white background */
    .guide-item {
        background-color: #ffffff;
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        /* Mobile Modal Container */
        #guideModalContent {
            width: 100% !important;
            max-width: 100% !important;
        }

        /* Mobile Typography - Smaller font sizes */
        #guideModalContent .text-lg {
            font-size: 0.8rem !important;
            line-height: 1.4;
            font-weight: 600;
        }

        #guideModalContent h2 {
            font-size: 1rem !important;
            line-height: 1.3;
        }

        #guideModalContent h3 {
            font-size: 0.9rem !important;
            line-height: 1.4;
            margin-bottom: 0.75rem !important;
        }

        #guideModalContent h4 {
            font-size: 0.85rem !important;
            line-height: 1.4;
            font-weight: 500;
        }

        #guideModalContent p {
            font-size: 0.8rem !important;
            line-height: 1.6;
        }

        #guideModalContent .text-sm {
            font-size: 0.75rem !important;
            line-height: 1.5;
        }

        /* Mobile Header */
        #guideModalContent .bg-gradient-to-r {
            padding: 1rem !important;
        }

        #guideModalContent .bg-gradient-to-r h2 {
            font-size: 0.9rem !important;
        }

        #guideModalContent .bg-gradient-to-r .text-xl {
            font-size: 1rem !important;
        }

        #guideModalContent .bg-gradient-to-r .text-lg {
            font-size: 0.9rem !important;
        }

        /* Mobile Content Area */
        #guideModalContent .p-4 {
            padding: 1rem !important;
        }

        #guideModalContent .space-y-6 > div {
            margin-bottom: 1.5rem !important;
        }

        /* Mobile Guide Items */
        #guideModalContent .guide-item {
            margin-bottom: 0.75rem !important;
            padding-bottom: 0.75rem !important;
        }

        #guideModalContent .guide-question {
            padding: 0.75rem 0 !important;
        }

        #guideModalContent .guide-answer {
            padding-top: 0.75rem !important;
            padding-left: 0.75rem !important;
        }

        #guideModalContent .guide-answer p {
            margin-bottom: 0.75rem !important;
        }

        /* Mobile Lists */
        #guideModalContent .list-disc,
        #guideModalContent .list-decimal {
            padding-left: 1.25rem !important;
        }

        #guideModalContent .list-disc li,
        #guideModalContent .list-decimal li {
            margin-bottom: 0.5rem !important;
            font-size: 0.75rem !important;
            line-height: 1.5;
        }

        /* Mobile Icons */
        #guideModalContent .fa-chevron-down {
            font-size: 0.8rem !important;
        }

        /* Mobile Touch Targets */
        #guideModalContent .guide-question,
        #guideModalContent button {
            min-height: 44px;
        }

        /* Mobile Help Section */
        #guideModalContent .mt-6 {
            margin-top: 1.5rem !important;
        }

        #guideModalContent .pt-4 {
            padding-top: 1rem !important;
        }

        #guideModalContent .mb-3 {
            margin-bottom: 0.75rem !important;
        }

        #guideModalContent .my-3 {
            margin: 0.75rem 0 !important;
        }

        #guideModalContent .p-3 {
            padding: 0.75rem !important;
        }

        /* Mobile Scrollbar */
        #guideModalContent::-webkit-scrollbar {
            width: 6px;
        }

        #guideModalContent::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        #guideModalContent::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        #guideModalContent::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    }

    /* Extra Small Mobile Devices */
    @media (max-width: 480px) {
        #guideModalContent .text-lg {
            font-size: 0.75rem !important;
        }

        #guideModalContent h2 {
            font-size: 0.9rem !important;
        }

        #guideModalContent h3 {
            font-size: 0.85rem !important;
        }

        #guideModalContent h4 {
            font-size: 0.8rem !important;
        }

        #guideModalContent p {
            font-size: 0.75rem !important;
        }

        #guideModalContent .text-sm {
            font-size: 0.7rem !important;
        }

        #guideModalContent .list-disc li,
        #guideModalContent .list-decimal li {
            font-size: 0.7rem !important;
        }

        #guideModalContent .bg-gradient-to-r {
            padding: 0.75rem !important;
        }

        #guideModalContent .p-4 {
            padding: 0.75rem !important;
        }

        #guideModalContent .guide-question {
            padding: 0.625rem 0 !important;
        }

        #guideModalContent .guide-answer {
            padding-top: 0.625rem !important;
            padding-left: 0.625rem !important;
        }
    }

    /* Landscape Mobile Orientation */
    @media (max-width: 768px) and (orientation: landscape) {
        #guideModalContent {
            max-height: 85vh;
        }

        #guideModalContent .bg-gradient-to-r {
            padding: 0.75rem !important;
        }

        #guideModalContent .p-4 {
            padding: 0.75rem !important;
        }

        #guideModalContent h3 {
            font-size: 0.85rem !important;
            margin-bottom: 0.5rem !important;
        }

        #guideModalContent .space-y-6 > div {
            margin-bottom: 1rem !important;
        }

        #guideModalContent .guide-item {
            margin-bottom: 0.5rem !important;
            padding-bottom: 0.5rem !important;
        }
    }
</style>


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

<script>
    function openUserGuideModal(event) {
        event.preventDefault();
        const modal = document.getElementById('guideModal');
        const modalContent = document.getElementById('guideModalContent');

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
            if (guideCategory) {
                const categoryElement = document.getElementById(guideCategory);
                if (categoryElement) {
                    categoryElement.scrollIntoView({ behavior: 'smooth' });
                }
            }
        }, 100);
    }

    function closeGuideModal() {
        const modal = document.getElementById('guideModal');
        const modalContent = document.getElementById('guideModalContent');

        modalContent.classList.remove('translate-x-0');
        modalContent.classList.add('translate-x-full');

        setTimeout(() => {
            modal.classList.add('hidden');
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');

            // Restore focus to the element that was active before opening the modal
            if (window.lastGuideActiveElement) {
                window.lastGuideActiveElement.focus();
            }
        }, 300);
    }

    // Close modal when clicking outside
    document.getElementById('guideModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeUserGuideModal();
        }
    });

    // Toggle Guide answers
    function toggleGuideAnswer(id) {
        const answer = document.getElementById(id);
        const icon = document.querySelector(`[data-guide="${id}"] i.fa-chevron-down`);

        if (answer.classList.contains('hidden')) {
            // Close other open guide items (accordion behavior)
            document.querySelectorAll('.guide-answer').forEach(item => {
                if (item.id !== id && !item.classList.contains('hidden')) {
                    item.classList.add('hidden');
                    const otherIcon = document.querySelector(`[data-guide="${item.id}"] i`);
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
</script>