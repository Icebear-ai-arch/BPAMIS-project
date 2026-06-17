<?php
// Help Center Modal Include File
// This file contains the Help Center modal that can be included in any page
if (!defined('CONTACT_SUPPORT_MODAL_INCLUDED')) {
    define('CONTACT_SUPPORT_MODAL_INCLUDED', true);
}
?>

<style>
    #contactModal {
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    #contactModalContent {
        transition: all 0.3s ease-out;
    }

    .contact-card {
        transition: transform 0.25s cubic-bezier(.4, 2, .6, 1), box-shadow 0.25s;
        will-change: transform, box-shadow;
    }

    .contact-card:hover {
        transform: scale(1.01);
        box-shadow: 0 12px 32px 0 rgba(37, 99, 235, 0.18);
        z-index: 2;
    }

    .contact-card .contact-icon {
        transition: transform 0.25s cubic-bezier(.4, 2, .6, 1);
    }

    .contact-card:hover .contact-icon {
        transform: scale(1.18) rotate(-12deg);
    }

    /* Mobile Responsive Styles */
    @media (max-width: 768px) {
        #contactModalContent {
            max-width: 95vw !important;
            margin: 1rem;
            max-height: 90vh;
            overflow-y: auto;
        }

        #contactModalContent .grid {
            grid-template-columns: 1fr !important;
        }

        /* Mobile Typography */
        #contactModalContent h3 {
            font-size: 1.25rem !important;
            line-height: 1.4;
            margin-bottom: 0.75rem !important;
        }

        #contactModalContent p {
            font-size: 0.875rem !important;
            line-height: 1.5;
        }

        #contactModalContent label {
            font-size: 0.875rem !important;
            font-weight: 500;
            margin-bottom: 0.375rem;
        }

        #contactModalContent input,
        #contactModalContent select,
        #contactModalContent textarea {
            font-size: 1rem !important;
            padding: 0.75rem 0.75rem 0.75rem 2.5rem !important;
            border-radius: 0.5rem !important;
        }

        #contactModalContent textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Mobile Left Side (Contact Info) */
        #contactModalContent .bg-gradient-to-br {
            padding: 1.5rem !important;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }

        #contactModalContent .bg-gradient-to-br h3 {
            font-size: 1.125rem !important;
            margin-bottom: 0.5rem !important;
        }

        #contactModalContent .bg-gradient-to-br p {
            font-size: 0.8rem !important;
            margin-bottom: 1rem !important;
        }

        #contactModalContent .space-y-6 > div {
            margin-bottom: 0.75rem !important;
        }

        #contactModalContent .flex.items-center.space-x-4 {
            gap: 0.5rem;
        }

        #contactModalContent .bg-white\/10 {
            padding: 0.5rem !important;
        }

        #contactModalContent .bg-white\/10 i {
            font-size: 1.25rem !important;
        }

        #contactModalContent .bg-white\/10 + div h4 {
            font-size: 0.875rem !important;
            font-weight: 600;
        }

        #contactModalContent .bg-white\/10 + div p {
            font-size: 0.75rem !important;
        }

        /* Mobile Right Side (Form) */
        #contactModalContent .p-8 {
            padding: 1.5rem !important;
        }

        #contactModalContent .flex.justify-between {
            margin-bottom: 1rem !important;
        }

        #contactModalContent .space-y-4 > div {
            margin-bottom: 0.75rem !important;
        }

        #contactModalContent .space-y-2 {
            margin-bottom: 0.5rem !important;
        }

        /* Mobile Button Styles */
        #contactModalContent .flex.justify-end {
            flex-direction: row;
            gap: 0.5rem;
        }

        #contactModalContent .flex.justify-end button {
            flex: 1;
            padding: 0.625rem 0.875rem !important;
            font-size: 0.875rem !important;
            font-weight: 500;
            border-radius: 0.5rem !important;
        }

        /* Mobile Close Button */
        #contactModalContent .text-gray-400 {
            font-size: 1.25rem !important;
            padding: 0.5rem;
        }

        /* Mobile Input Icons */
        #contactModalContent .relative i {
            font-size: 0.875rem !important;
        }

        /* Center icons in input fields for mobile */
        #contactModalContent .relative i.fas.fa-user,
        #contactModalContent .relative i.fas.fa-envelope,
        #contactModalContent .relative i.fas.fa-tag,
        #contactModalContent .relative i.fas.fa-comment {
            top: 50% !important;
            transform: translateY(-50%) !important;
            left: 0.75rem !important;
        }

        /* Special positioning for textarea icon */
        #contactModalContent .relative i.fas.fa-comment {
            top: 1rem !important;
            transform: none !important;
        }

        /* Mobile Touch Targets */
        #contactModalContent button,
        #contactModalContent select,
        #contactModalContent input,
        #contactModalContent textarea {
            min-height: 44px;
        }

        /* Mobile Scrollbar Styling */
        #contactModalContent::-webkit-scrollbar {
            width: 4px;
        }

        #contactModalContent::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 2px;
        }

        #contactModalContent::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 2px;
        }

        #contactModalContent::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    }

    /* Extra Small Mobile Devices */
    @media (max-width: 480px) {
        #contactModalContent {
            max-width: 98vw !important;
            margin: 0.5rem;
        }

        #contactModalContent .bg-gradient-to-br {
            padding: 1rem !important;
        }

        #contactModalContent .p-8 {
            padding: 1rem !important;
        }

        #contactModalContent h3 {
            font-size: 1.125rem !important;
        }

        #contactModalContent .bg-gradient-to-br h3 {
            font-size: 1rem !important;
        }

        #contactModalContent label {
            font-size: 0.8rem !important;
        }

        #contactModalContent input,
        #contactModalContent select,
        #contactModalContent textarea {
            font-size: 0.9rem !important;
            padding: 0.625rem 0.625rem 0.625rem 2.25rem !important;
        }

        #contactModalContent button {
            font-size: 0.8rem !important;
            padding: 0.625rem 0.875rem !important;
        }

        #contactModalContent .bg-white\/10 + div h4 {
            font-size: 0.8rem !important;
        }

        #contactModalContent .bg-white\/10 + div p {
            font-size: 0.7rem !important;
        }
    }

    /* Landscape Mobile Orientation */
    @media (max-width: 768px) and (orientation: landscape) {
        #contactModalContent {
            max-height: 85vh;
        }

        #contactModalContent .bg-gradient-to-br {
            padding: 1rem !important;
        }

        #contactModalContent .p-8 {
            padding: 1rem !important;
        }

        #contactModalContent h3 {
            font-size: 1.125rem !important;
            margin-bottom: 0.55rem !important;
        }

        #contactModalContent .space-y-4 > div {
            margin-bottom: 0.55rem !important;
        }

        #contactModalContent .space-y-6 > div {
            margin-bottom: 0.55rem !important;
        }
    }
</style>

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
    
<script>
    function openContactModal(event) {
        event.preventDefault();
        const modal = document.getElementById('contactModal');
        const modalContent = document.getElementById('contactModalContent');

        modal.classList.remove('hidden');
        // Trigger reflow
        void modalContent.offsetWidth;
        modalContent.classList.remove('opacity-0', 'scale-95');
        modalContent.classList.add('opacity-100', 'scale-100');
    }

    function closeContactModal() {
        const modal = document.getElementById('contactModal');
        const modalContent = document.getElementById('contactModalContent');

        modalContent.classList.remove('opacity-100', 'scale-100');
        modalContent.classList.add('opacity-0', 'scale-95');

        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    document.getElementById('contactForm').addEventListener('submit', function (e) {
        e.preventDefault();
        // Add your form submission logic here
        alert('Message sent successfully!');
        closeContactModal();
    });

    document.getElementById('contactModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeContactModal();
        }
    });

</script>