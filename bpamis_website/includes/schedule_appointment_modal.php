<?php
// Help Center Modal Include File
// This file contains the Help Center modal that can be included in any page
if (!defined(' SCHEDULE_APPOINTMENT_MODAL_INCLUDED')) {
    define('SCHEDULE_APPOINTMENT_MODAL_INCLUDED', true);
}
?>

<style>
    /* Custom styles for the appointment modal with backdrop blur */
    #appointmentModal {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 60; /* Higher z-index to ensure it appears on top of other elements */
    }

    #modalContent {
        transition: all 0.3s ease-out;
        position: relative; /* Ensure proper stacking context */
        z-index: 61; /* Higher than the backdrop */
    }

    /* Focus styles for accessibility */
    .focus-visible-ring:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.5);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        #modalContent {
            max-width: 95vw !important;
            margin: 1rem;
            max-height: 90vh;
            overflow-y: auto;
        }

        #modalContent .p-6 {
            padding: 1rem !important;
        }

        /* Mobile Typography */
        #modalContent h3 {
            font-size: 1.25rem !important;
            line-height: 1.4;
            margin-bottom: 1rem !important;
        }

        #modalContent label {
            font-size: 0.875rem !important;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        #modalContent select,
        #modalContent input,
        #modalContent textarea {
            font-size: 1rem !important;
            padding: 0.75rem !important;
            border-radius: 0.5rem !important;
        }

        #modalContent textarea {
            min-height: 80px;
            resize: vertical;
        }

        /* Mobile Button Styles */
        #modalContent .flex.justify-end {
            flex-direction: row;
            gap: 0.75rem;
        }

        #modalContent .flex.justify-end button {
            flex: 1;
            padding: 0.75rem 1rem !important;
            font-size: 0.875rem !important;
            font-weight: 500;
            border-radius: 0.5rem !important;
        }

        /* Mobile Close Button */
        #modalContent .text-gray-400 {
            font-size: 1.25rem !important;
            padding: 0.5rem;
        }

        /* Mobile Form Spacing */
        #modalContent .space-y-4 > div {
            margin-bottom: 1rem !important;
        }

        #modalContent .space-y-2 {
            margin-bottom: 0.75rem !important;
        }

        /* Mobile Modal Header */
        #modalContent .flex.justify-between {
            margin-bottom: 1.5rem !important;
        }

        /* Mobile Form Container */
        #modalContent form {
            margin-top: 0.5rem;
        }

        /* Mobile Input Focus States */
        #modalContent select:focus,
        #modalContent input:focus,
        #modalContent textarea:focus {
            border-color: #3B82F6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        /* Mobile Touch Targets */
        #modalContent button,
        #modalContent select,
        #modalContent input,
        #modalContent textarea {
            min-height: 44px;
        }

        /* Mobile Scrollbar Styling */
        #modalContent::-webkit-scrollbar {
            width: 4px;
        }

        #modalContent::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        #modalContent::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        #modalContent::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    }

    /* Extra Small Mobile Devices */
    @media (max-width: 480px) {
        #modalContent {
            max-width: 98vw !important;
            margin: 0.5rem;
        }

        #modalContent .p-6 {
            padding: 0.75rem !important;
        }

        #modalContent h3 {
            font-size: 1.125rem !important;
        }

        #modalContent label {
            font-size: 0.8rem !important;
        }

        #modalContent select,
        #modalContent input,
        #modalContent textarea {
            font-size: 0.9rem !important;
            padding: 0.625rem !important;
        }

        #modalContent button {
            font-size: 0.8rem !important;
            padding: 0.625rem 0.875rem !important;
        }
    }

    /* Landscape Mobile Orientation */
    @media (max-width: 768px) and (orientation: landscape) {
        #modalContent {
            max-height: 85vh;
        }

        #modalContent .p-6 {
            padding: 0.75rem !important;
        }

        #modalContent h3 {
            font-size: 1.125rem !important;
            margin-bottom: 0.75rem !important;
        }

        #modalContent .space-y-4 > div {
            margin-bottom: 0.75rem !important;
        }
    }
</style>


<!-- Appointment Modal -->
<div id="appointmentModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-60 flex items-center justify-center backdrop-blur-[4px]">
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

<script>
    // Store focusable elements and last active element for accessibility
    let appointmentFocusableElements = [];
    let appointmentFirstFocusableElement = null;
    let appointmentLastFocusableElement = null;

    function openModal(event) {
        if (event) event.preventDefault();
        
        // Store last active element for restoring focus later
        window.lastAppointmentActiveElement = document.activeElement;
        
        const modal = document.getElementById('appointmentModal');
        const modalContent = document.getElementById('modalContent');
        
        // Check if Help Center modal is open and close it first
        const helpCenterModal = document.getElementById('helpCenterModal');
        if (helpCenterModal && !helpCenterModal.classList.contains('hidden')) {
            // If Help Center modal has its own closing function, call it
            if (typeof closeHelpCenterModal === 'function') {
                closeHelpCenterModal();
            }
        }

        modal.classList.remove('hidden');
        // Trigger reflow
        void modalContent.offsetWidth;
        modalContent.classList.remove('opacity-0', 'scale-95');
        modalContent.classList.add('opacity-100', 'scale-100');
        
        // Ensure the modal is at the top of the stacking context
        modal.style.zIndex = '60';
        
        // Prevent body scrolling when modal is open
        document.body.classList.add('overflow-hidden');

        // Set focus on modal for accessibility
        setTimeout(() => {
            modalContent.focus();

            // Get all focusable elements
            appointmentFocusableElements = modalContent.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (appointmentFocusableElements.length > 0) {
                appointmentFirstFocusableElement = appointmentFocusableElements[0];
                appointmentLastFocusableElement = appointmentFocusableElements[appointmentFocusableElements.length - 1];
                appointmentFirstFocusableElement.focus();
            }
        }, 100);
    }

    function closeModal() {
        const modal = document.getElementById('appointmentModal');
        const modalContent = document.getElementById('modalContent');

        modalContent.classList.remove('opacity-100', 'scale-100');
        modalContent.classList.add('opacity-0', 'scale-95');

        setTimeout(() => {
            modal.classList.add('hidden');
            
            // Re-enable body scrolling
            document.body.classList.remove('overflow-hidden');
            
            // Restore focus to the element that was active before opening the modal
            if (window.lastAppointmentActiveElement) {
                window.lastAppointmentActiveElement.focus();
            }
        }, 300);
    }

    // Document ready event listener
    document.addEventListener('DOMContentLoaded', function () {
        if (document.getElementById('appointmentForm')) {
            document.getElementById('appointmentForm').addEventListener('submit', function (e) {
                e.preventDefault();
                // Add your form submission logic here
                alert('Appointment scheduled successfully!');
                closeModal();
            });
        }

        // Close modal when clicking outside
        const appointmentModal = document.getElementById('appointmentModal');
        if (appointmentModal) {
            appointmentModal.addEventListener('click', function (e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            
            // Handle keyboard navigation for accessibility
            document.addEventListener('keydown', function (event) {
                // Close modal when pressing Escape key
                if (event.key === 'Escape' && !appointmentModal.classList.contains('hidden')) {
                    closeModal();
                    return;
                }

                // Trap focus inside modal when Tab key is pressed
                if (event.key === 'Tab' && !appointmentModal.classList.contains('hidden')) {
                    // If shift key is also pressed and focus is on first element, move to last element
                    if (event.shiftKey && document.activeElement === appointmentFirstFocusableElement) {
                        event.preventDefault();
                        appointmentLastFocusableElement.focus();
                    }
                    // If focus is on last element, move to first element
                    else if (!event.shiftKey && document.activeElement === appointmentLastFocusableElement) {
                        event.preventDefault();
                        appointmentFirstFocusableElement.focus();
                    }
                }
            });
        }
    });
</script>