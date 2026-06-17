
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BPAMIS Case Assistant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // (Optional) Tailwind custom palette (kept minimal and valid now)
        tailwind.config = {
            theme: { extend: { colors: { primary: { 50:'#f0f7ff',100:'#e0effe',200:'#bae2fd',300:'#7cccfd',400:'#36b3f9',500:'#0c9ced',600:'#0281d4',700:'#026aad',800:'#065a8f',900:'#0a4b76' } } } }
        }
    </script>
    <style>
        /* Premium background */
        body.premium-bg { 
            background: radial-gradient(circle at 25% 20%, #f0f7ff 0%, #eef4f9 35%, #dfe7ef 70%); 
            min-height:100vh; 
        }
        /* Prevent duplicate vertical scrollbars: keep scrolling inside the chat panel only */
        html, body { height: 100%; margin: 0; overflow: hidden; }
        .gradient-bg { background: linear-gradient(to right, #f0f7ff, #e0effe); }
        
        /* Chat container styles for standalone page */
        /* Make the standalone chat container feel like a full-page modal
           but keep a consistent outer gap (margin) on all sides. The gap
           is responsive and reduces on small screens. */
        :root {
            --standalone-gap: 1.25rem; /* ~20px outer gap on desktop */
            --standalone-gap-mobile: 0.5rem; /* smaller gap on mobile */
            --standalone-radius: 18px;
        }

        .standalone-chat-container {
            position: fixed;
            inset: var(--standalone-gap); /* creates top/right/bottom/left margin */
            width: auto;
            max-width: calc(100% - (var(--standalone-gap) * 2));
            height: calc(100vh - (var(--standalone-gap) * 2));
            margin: 0;
            border-radius: var(--standalone-radius);
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(14px) saturate(1.2);
            -webkit-backdrop-filter: blur(14px) saturate(1.2);
            box-shadow: 0 10px 35px -5px rgba(15,75,120,.12), 0 2px 6px rgba(0,0,0,.04);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            z-index: 9999;
            -webkit-font-smoothing:antialiased;
        }
        .standalone-chat-container:before { /* subtle border glow */
            content:""; position:absolute; inset:0; padding:1px; border-radius:inherit; pointer-events:none;
            background:linear-gradient(135deg,#0c9ced,#7cccfd,#065a8f);
            -webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite:xor; mask-composite:exclude;
        }
        
        .chat-header {
            padding: 18px 24px;
            background: linear-gradient(120deg,#026aad,#0c9ced 45%,#7cccfd);
            color: #fff;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 26px 26px 0 0;
            position: relative;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.15);
        }
        
        .chat-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 1.25rem;
        }
        
        .chat-body {
            flex: 1;
            overflow-y: auto;
            padding: 26px 28px 24px;
            background: radial-gradient(circle at 85% 15%, rgba(12,156,237,0.10), transparent 60%), radial-gradient(circle at 15% 85%, rgba(6,90,143,0.10), transparent 65%);
        }
        /* custom scrollbar */
        .chat-body::-webkit-scrollbar { width: 10px; }
        .chat-body::-webkit-scrollbar-track { background: transparent; }
        .chat-body::-webkit-scrollbar-thumb { background: linear-gradient(#c5d7e6,#9cc9e4); border-radius: 6px; border:2px solid rgba(255,255,255,0.6); }
        .chat-body::-webkit-scrollbar-thumb:hover { background: linear-gradient(#b2cedf,#7ab8dd); }
        
        .chat-footer {
            padding: 18px 24px;
            border-top: 1px solid rgba(12,156,237,0.15);
            display: flex;
            align-items: center;
            background: linear-gradient(to right,rgba(255,255,255,0.9),rgba(240,249,255,0.85));
            border-radius: 0 0 26px 26px;
        }
        
        .chat-input {
            flex: 1;
            border: 1px solid #d3dfeb;
            background: rgba(255,255,255,0.75);
            border-radius: 24px;
            padding: 14px 22px 13px;
            font-size: 14px;
            outline: none;
            transition: all 0.25s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04), inset 0 0 0 1px rgba(255,255,255,0.6);
        }
        
        .chat-input:focus {
            border-color: #0c9ced;
            box-shadow: 0 0 0 2px rgba(12,156,237,0.25), 0 4px 10px -2px rgba(12,156,237,0.25);
            background: rgba(255,255,255,0.95);
        }
        
        .chat-send-button {
            background: linear-gradient(135deg,#0281d4,#0c9ced 55%,#36b3f9);
            color: #fff;
            border: none;
            width: 48px;
            height: 48px;
            border-radius: 18px;
            margin-left: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 4px 14px -2px rgba(12,156,237,0.45), 0 2px 4px rgba(0,0,0,0.06);
            position: relative;
        }
        .chat-send-button:before {content:"";position:absolute;inset:0;border-radius:inherit;background:linear-gradient(135deg,rgba(255,255,255,0.6),rgba(255,255,255,0));opacity:.3;}
        
    .chat-send-button:hover { transform: translateY(-2px) scale(1.03); box-shadow: 0 6px 18px -2px rgba(12,156,237,0.55); }
    .chat-send-button:active { transform: translateY(0) scale(.98); box-shadow: 0 3px 10px -2px rgba(12,156,237,0.4); }
        
        .chat-message {
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
        }
        
        .user-message {
            justify-content: flex-end;
        }
        
        .bot-message {
            justify-content: flex-start;
        }
        
        .message-content {
            max-width: 62%;
            padding: 14px 18px 16px;
            border-radius: 20px;
            font-size: 14px;
            line-height: 1.55;
            position: relative;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05), 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .user-message .message-content {
            background: linear-gradient(135deg,#0c9ced,#0281d4 60%,#065a8f);
            color: #fff;
            border-bottom-right-radius: 6px;
        }
        
        .bot-message .message-content {
            background: linear-gradient(135deg,#ffffff,#f0f7ff 60%);
            color: #2b3642;
            border-bottom-left-radius: 6px;
            border:1px solid rgba(12,156,237,0.08);
        }
        .bot-message .message-content a { color:#0c79c4; font-weight:500; }
        .bot-message .message-content a:hover { text-decoration:underline; }
        
        /* Typing animation */
        .typing-animation {
            display: flex;
            align-items: center;
            column-gap: 6px;
            padding: 6px 12px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background-color: #0281d4;
            border-radius: 50%;
            opacity: 0.6;
        }

        .typing-dot:nth-child(1) {
            animation: typing 1.2s infinite ease-in-out;
        }

        .typing-dot:nth-child(2) {
            animation: typing 1.2s infinite ease-in-out 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation: typing 1.2s infinite ease-in-out 0.4s;
        }

        @keyframes typing {
            0%, 100% {
                transform: translateY(0);
                opacity: 0.6;
            }
            50% {
                transform: translateY(-5px);
                opacity: 1;
            }
        }
        
        /* Chat suggestion prompts */
        .chat-suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .chat-suggestion {
            background: linear-gradient(135deg,#e0effe,#f0f7ff 70%);
            color: #026aad;
            font-size: 11px;
            padding: 8px 14px;
            border-radius: 22px;
            cursor: pointer;
            transition: all 0.25s ease;
            border: 1px solid rgba(2,129,212,0.15);
            outline: none;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05), inset 0 0 0 1px rgba(255,255,255,0.6);
            position: relative;
        }
        .chat-suggestion:before {content:""; position:absolute; inset:0; border-radius:inherit; background:linear-gradient(135deg,rgba(255,255,255,0.7),rgba(255,255,255,0)); opacity:.5; pointer-events:none;}

    .chat-suggestion:hover { background: linear-gradient(135deg,#cfe7fa,#e3f3ff); transform: translateY(-3px); box-shadow:0 6px 14px -4px rgba(12,156,237,0.35); }
    .chat-suggestion:active { transform: translateY(0); }
        
        /* Chat Options Menu Styles */
        #chatOptionsButton {
            transition: all 0.2s ease;
        }

        #chatOptionsButton:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }

        #chatOptionsMenu {
            transform-origin: top left;
            transition: opacity 0.2s, transform 0.2s;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-radius: 8px;
            overflow: hidden;
            position: absolute;
            background: white;
            z-index: 50;
        }

        #chatOptionsMenu a {
            transition: background-color 0.2s;
            font-size: 13px;
            display: block;
            padding: 8px 16px;
            color: #4b5563;
        }

        #chatOptionsMenu a:hover {
            background-color: #f0f7ff;
        }

        #chatOptionsMenu a i {
            color: #0c9ced;
            width: 16px;
            text-align: center;
            margin-right: 8px;
        }

        #chatOptionsMenu a:last-child i {
            color: #ef4444;
        }
        
        .bot-avatar {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            background: linear-gradient(135deg,#e0effe,#ffffff);
            border:1px solid rgba(2,129,212,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            box-shadow: 0 3px 8px -2px rgba(12,156,237,0.35), 0 2px 4px rgba(0,0,0,0.04);
        }
        
    .bot-avatar i { color: #0281d4; font-size: 18px; }
        
    .message-time { font-size: 10px; color: rgba(255,255,255,0.75); margin-top: 6px; text-align: right; letter-spacing:.5px; }
        
    .bot-message .message-time { color: #6b7a86; }

        /* Citation and source styles */
    .source-citation { margin-top: 12px; padding-top: 8px; border-top: 1px dashed #c7d6e2; font-size: 12px; color:#5a6a76; }

        .source-link {
            display: inline-block;
            margin-top: 4px;
            color: #0281d4;
            text-decoration: underline;
        }

    .legal-reference { background:#f6fbff; padding:8px 12px; border-left:4px solid #0c9ced; margin-top:10px; font-size:12.5px; border-radius:4px; }

    /* Standardized inline law reference highlight */
    .law-ref { background: #e6f3ff; color:#065a8f; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11.5px; display:inline-block; margin:2px 2px 2px 0; }
    .legal-reference b { font-weight:600; }

        .update-timestamp {
            font-size: 11px;
            color: #888;
            margin-top: 4px;
            font-style: italic;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .standalone-chat-container {
                position: fixed;
                /* Respect the device safe-area inset at the bottom (navigation bars).
                   Add an extra offset to keep the chat input above phone nav bars. */
                inset: var(--standalone-gap-mobile) var(--standalone-gap-mobile) calc(var(--standalone-gap-mobile) + env(safe-area-inset-bottom, 16px) + 48px) var(--standalone-gap-mobile) !important;
                height: calc(100vh - (var(--standalone-gap-mobile) * 2) - env(safe-area-inset-bottom, 16px) - 48px) !important;
                border-radius: 12px !important;
            }

            .chat-header, .chat-footer {
                border-radius: 12px !important;
            }
        }

        /* Mobile Optimization - Compressed and Compact */
        @media (max-width: 640px) {
            body.premium-bg {
                padding: 0;
                margin: 0;
            }

            .container {
                padding: 0.5rem !important;
                max-width: 100% !important;
            }

            .container.mx-auto.px-4.py-12 {
                padding: 0.5rem !important;
            }

            .standalone-chat-container {
                position: fixed;
                /* Add extra bottom inset for mobile nav bars and gesture areas.
                   Extra 56px helps keep the input visible on devices with persistent nav UI. */
                inset: var(--standalone-gap-mobile) var(--standalone-gap-mobile) calc(var(--standalone-gap-mobile) + env(safe-area-inset-bottom, 18px) + 56px) var(--standalone-gap-mobile) !important;
                height: calc(100vh - (var(--standalone-gap-mobile) * 2) - env(safe-area-inset-bottom, 18px) - 56px) !important;
                border-radius: 12px !important;
                max-width: 100%;
                margin: 0;
            }

            /* Header */
            .chat-header {
                padding: 0.65rem 0.75rem !important;
                border-radius: 1rem 1rem 0 0 !important;
            }

            .chat-header h3 {
                font-size: 0.95rem !important;
                gap: 0.4rem !important;
            }

            .chat-header h3 i {
                font-size: 0.85rem !important;
            }

            .chat-header .text-sm {
                font-size: 9px !important;
            }

            #chatOptionsButton {
                width: 1.75rem !important;
                height: 1.75rem !important;
            }

            #chatOptionsButton i {
                font-size: 0.7rem !important;
            }

            #chatOptionsMenu {
                width: 8.5rem !important;
            }

            #chatOptionsMenu a {
                font-size: 0.7rem !important;
                padding: 0.45rem 0.75rem !important;
            }

            #chatOptionsMenu a i {
                font-size: 0.65rem !important;
                width: 0.9rem !important;
                margin-right: 0.4rem !important;
            }

            /* Chat Body */
            .chat-body {
                /* Extra bottom padding ensures last messages are not hidden behind nav bars */
                padding: calc(0.75rem + 56px + env(safe-area-inset-bottom, 18px)) !important;
            }

            .chat-body::-webkit-scrollbar {
                width: 4px !important;
            }

            /* Messages */
            .chat-message {
                margin-bottom: 0.65rem !important;
            }

            .bot-avatar {
                width: 1.75rem !important;
                height: 1.75rem !important;
                border-radius: 0.65rem !important;
                margin-right: 0.5rem !important;
            }

            .bot-avatar i {
                font-size: 0.85rem !important;
            }

            .message-content {
                max-width: 75% !important;
                padding: 0.65rem 0.75rem !important;
                border-radius: 0.85rem !important;
                font-size: 0.7rem !important;
                line-height: 1.4 !important;
            }

            .user-message .message-content {
                border-bottom-right-radius: 0.3rem !important;
            }

            .bot-message .message-content {
                border-bottom-left-radius: 0.3rem !important;
            }

            .message-content br {
                line-height: 0.8 !important;
            }

            .message-content a {
                font-size: 0.7rem !important;
            }

            .message-time {
                font-size: 8px !important;
                margin-top: 0.3rem !important;
            }

            /* Typing Animation */
            .typing-animation {
                padding: 0.35rem 0.6rem !important;
                column-gap: 0.3rem !important;
            }

            .typing-dot {
                width: 0.35rem !important;
                height: 0.35rem !important;
            }

            /* Suggestions */
            .chat-suggestions {
                gap: 0.35rem !important;
                margin-top: 0.65rem !important;
            }

            .chat-suggestion {
                font-size: 9px !important;
                padding: 0.4rem 0.65rem !important;
                border-radius: 0.85rem !important;
            }

            /* Legal References */
            .legal-reference {
                padding: 0.45rem 0.65rem !important;
                margin-top: 0.5rem !important;
                font-size: 0.7rem !important;
                border-radius: 0.35rem !important;
                border-left-width: 2px !important;
            }

            .legal-reference b {
                font-size: 9px !important;
            }

            .law-ref {
                padding: 0.15rem 0.35rem !important;
                border-radius: 0.25rem !important;
                font-size: 9px !important;
                margin: 0.1rem 0.1rem 0.1rem 0 !important;
            }

            .source-citation {
                margin-top: 0.6rem !important;
                padding-top: 0.45rem !important;
                font-size: 9px !important;
            }

            .source-link {
                margin-top: 0.2rem !important;
                font-size: 9px !important;
            }

            .update-timestamp {
                font-size: 8px !important;
                margin-top: 0.2rem !important;
            }

            /* Footer */
            .chat-footer {
                /* Ensure footer has extra bottom padding so the input is tappable above nav bars */
                padding: calc(0.65rem) 0.75rem calc(0.65rem + env(safe-area-inset-bottom, 18px) + 20px) !important;
                border-radius: 0 0 1rem 1rem !important;
            }

            .chat-input {
                padding: 0.6rem 0.85rem !important;
                font-size: 0.7rem !important;
                border-radius: 1.2rem !important;
            }

            .chat-send-button {
                width: 2.25rem !important;
                height: 2.25rem !important;
                border-radius: 0.85rem !important;
                margin-left: 0.5rem !important;
            }

            .chat-send-button i {
                font-size: 0.75rem !important;
            }
        }

        @media (max-width: 380px) {
            .container {
                padding: 0.4rem !important;
            }

            .chat-header {
                padding: 0.5rem 0.6rem !important;
            }

            .chat-header h3 {
                font-size: 0.85rem !important;
            }

            .chat-body {
                padding: 0.6rem !important;
            }

            .message-content {
                font-size: 0.65rem !important;
                padding: 0.55rem 0.65rem !important;
            }

            .chat-suggestion {
                font-size: 8px !important;
                padding: 0.35rem 0.55rem !important;
            }

            .chat-input {
                font-size: 0.65rem !important;
                padding: 0.5rem 0.75rem !important;
            }

            .chat-send-button {
                width: 2rem !important;
                height: 2rem !important;
            }

            .chat-send-button i {
                font-size: 0.7rem !important;
            }
        }
    </style>
</head>
<body class="premium-bg font-sans antialiased">
    
    <!-- Chat Container -->
    <div class="container mx-auto px-4 py-12 md:py-16">
        <div class="standalone-chat-container">
            <div class="chat-header">
                <div class="flex items-center">
                    <h3><i class="fas fa-robot"></i> Case Assistant</h3>
                    <div class="relative ml-2">
                        <button id="chatOptionsButton" class="text-white hover:bg-blue-600 rounded-full w-8 h-8 flex items-center justify-center">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div id="chatOptionsMenu" class="hidden left-0 mt-2 w-40 bg-white rounded-md shadow-lg py-1">
                            <a href="#" id="newChat" class="hover:bg-gray-100">
                                <i class="fas fa-plus mr-2"></i>New Chat
                            </a>
                            <a href="#" id="deleteChat" class="hover:bg-gray-100">
                                <i class="fas fa-trash mr-2"></i>Delete Chat
                            </a>
                        </div>
                    </div>
                </div>
                <div class="text-sm text-white opacity-75">Barangay Case Management System</div>
            </div>
            
            <div class="chat-body" id="chatBody">
                <div class="chat-message bot-message">
                    <div class="bot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        Hi there! I'm your Case Assistant. I can help you with questions about barangay laws, KP (Katarungang Pambarangay) forms, CFA (Certificate to File Action), and other matters based on the Local Government Code of 1991.
                        <br><br>
                        For more detailed information, you can refer to these official resources:
                        <br>
                        <a href="https://dilg.gov.ph/PDF_File/reports_resources/dilg-reports-resources-2016120_fce005a61a.pdf" target="_blank" class="text-blue-600 hover:underline">DILG Resource Guide</a> and 
                        
                        <a href="https://www.dilg.gov.ph/faqs/" target="_blank" class="text-blue-600 hover:underline">DILG FAQs</a>
                        
                        <div class="chat-suggestions">
                            <button class="chat-suggestion" data-query="What is Katarungang Pambarangay?">What is Katarungang Pambarangay?</button>
                            <button class="chat-suggestion" data-query="How to file a complaint?">How to file a complaint?</button>
                            <button class="chat-suggestion" data-query="What cases can be resolved at barangay level?">Cases at barangay level</button>
                            <button class="chat-suggestion" data-query="Who can attend barangay hearings?">Who can attend hearings?</button>
                            <button class="chat-suggestion" data-query="How to prepare for mediation?">Prepare for mediation</button>
                        </div>
                        <div class="message-time">Just now</div>
                    </div>
                </div>
            </div>
            
            <div class="chat-footer">
                <input type="text" class="chat-input" id="chatInput" placeholder="Type your question here..." aria-label="Type your message">
                <button class="chat-send-button" id="sendButton" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const chatInput = document.getElementById('chatInput');
        const sendButton = document.getElementById('sendButton');
        const chatBody = document.getElementById('chatBody');

        // Function to get appropriate suggestions based on the current query
        // Function to get source information for queries
        function getSourceInfo(query) {
            const sources = {
                'how many lupon members': {
                    mainSource: 'DILG FAQ - Revised KP Law',
                    url: 'https://www.dilg.gov.ph/faqs/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Section 399(a)'
                },
                'filing fee': {
                    mainSource: 'DILG FAQ',
                    url: 'https://www.dilg.gov.ph/faqs/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Section 418'
                },
                'where can i file': {
                    mainSource: 'BPAMIS Guidelines',
                    url: 'https://www.dilg.gov.ph/faqs/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Section 410'
                },
                'What is Katarungang Pambarangay?': {
                    mainSource: 'DILG FAQ',
                    url: 'https://www.dilg.gov.ph/faqs/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Chapter 7, Sections 399-422'
                },
                'How to file a complaint?': {
                    mainSource: 'LGUSS FAQs',
                    url: 'https://bims.dilg.gov.ph/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Section 410'
                },
                'What cases can be resolved at barangay level?': {
                    mainSource: 'DILG Region 3 FAQ',
                    url: 'https://region3.dilg.gov.ph/index.php/about/faqs',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Sections 408-409'
                },
                'Who can attend barangay hearings?': {
                    mainSource: 'DILG FAQ',
                    url: 'https://www.dilg.gov.ph/faqs/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Section 404, 415'
                },
                'How to prepare for mediation?': {
                    mainSource: 'DILG FAQ and Region 3 Guidelines',
                    url: 'https://www.dilg.gov.ph/faqs/',
                    lastVerified: 'September 4, 2025',
                    legalRef: 'Local Government Code of 1991, Sections 412-413'
                }
            };
            return sources[query] || null;
        }

        function getSuggestions(query) {
            const suggestionSets = {
                'What is Katarungang Pambarangay?': [
                    'What is Local Government Code of 1991',
                    'What is lupon tagapamayapa?',
                    'Who is the chairman of the lupon?',
                    'How many lupon members are there?',
                    'How much is the filing fee?'
                ],
                'How to file a complaint?': [
                    'Where can I file a barangay complaint?',
                    'What documents do I need to prepare?',
                    'Can I file a complaint on behalf of someone else?',
                    'Is there a filing deadline?',
                    'What happens after I file a complaint?'
                ],
                'What cases can be resolved at barangay level?': [
                    'What cases are not allowed in the barangay?',
                    'Are criminal cases handled at the barangay?',
                    'Can disputes over land ownership be resolved here?',
                    'Can cases between residents of different barangays be handled?',
                    'What is the role of conciliation in these cases?'
                ],
                'Who can attend barangay hearings?': [
                    'Can a lawyer attend barangay hearings?',
                    'Are witnesses allowed to attend?',
                    'Can a representative appear in place of a complainant?',
                    'Are hearings open to the public?',
                    'What happens if one party fails to attend?'
                ],
                'How to prepare for mediation?': [
                    'What documents should I bring?',
                    'What are my rights during mediation?',
                    'Can I bring a representative or support person?',
                    'What should I expect during the mediation session?',
                    'What happens if mediation fails?'
                ]
            };

            // Default suggestions for general queries
            const defaultSuggestions = [
                'What is Katarungang Pambarangay?',
                'How to file a complaint?',
                'What cases can be resolved at barangay level?',
                'Who can attend barangay hearings?',
                'How to prepare for mediation?'
            ];

            let suggestionsToUse = defaultSuggestions;
            
            // Check if the query matches any of our main categories
            for (let key in suggestionSets) {
                if (query.includes(key)) {
                    suggestionsToUse = suggestionSets[key];
                    break;
                }
            }

            // Generate HTML for suggestions
            return suggestionsToUse.map(suggestion => 
                `<button class="chat-suggestion" data-query="${suggestion}">${suggestion}</button>`
            ).join('');
        }
        
        // Chat options menu elements
        const chatOptionsButton = document.getElementById('chatOptionsButton');
        const chatOptionsMenu = document.getElementById('chatOptionsMenu');
        const newChatOption = document.getElementById('newChat');
        const deleteChatOption = document.getElementById('deleteChat');

        // Initialize suggestion buttons
        function initSuggestionButtons() {
            const suggestionButtons = document.querySelectorAll('.chat-suggestion');
            suggestionButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const query = this.getAttribute('data-query');
                    chatInput.value = query;
                    sendMessage();
                });
            });
        }
        
        // Initialize suggestion buttons
        initSuggestionButtons();

        // Toggle chat options menu
        chatOptionsButton.addEventListener('click', (e) => {
            e.stopPropagation();
            chatOptionsMenu.classList.toggle('hidden');
        });

        // Close menu when clicking outside
        document.addEventListener('click', () => {
            if (!chatOptionsMenu.classList.contains('hidden')) {
                chatOptionsMenu.classList.add('hidden');
            }
        });

        // Prevent menu from closing when clicking on menu items
        chatOptionsMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Handle menu options
        newChatOption.addEventListener('click', () => {
            // Clear all chat messages
            chatBody.innerHTML = '';
            // Add back the welcome message with suggestions
            const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            // Add a notification about starting a new chat
            chatBody.innerHTML = `
                <div class="chat-message bot-message">
                    <div class="bot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        New chat started! I'm your Case Assistant. I can help you with questions about barangay laws, KP (Katarungang Pambarangay) forms, CFA (Certificate to File Action), and other matters based on the Local Government Code of 1991.
                        <br><br>
                        For more detailed information, you can refer to these official resources:
                        <br>
                        <a href="https://dilg.gov.ph/PDF_File/reports_resources/dilg-reports-resources-2016120_fce005a61a.pdf" target="_blank" class="text-blue-600 hover:underline">DILG Resource Guide</a>
                        <br>
                        <a href="https://www.dilg.gov.ph/faqs/" target="_blank" class="text-blue-600 hover:underline">DILG FAQs</a>
                        
                        <div class="chat-suggestions">
                            <button class="chat-suggestion" data-query="What is Local Government Code of 1991">What is Local Government Code of 1991</button>
                            <button class="chat-suggestion" data-query="What is lupon tagapamayapa?">What is lupon tagapamayapa?</button>
                            <button class="chat-suggestion" data-query="Who is the chairman of the lupon?">Who is the chairman of the lupon?</button>
                            <button class="chat-suggestion" data-query="How many lupon members are there?">How many lupon members are there?</button>
                            <button class="chat-suggestion" data-query="How much is the filing fee?">How much is the filing fee?</button>
                        </div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            chatOptionsMenu.classList.add('hidden');
            // Re-initialize suggestion buttons
            initSuggestionButtons();
        });

        deleteChatOption.addEventListener('click', () => {
            // Clear all chat messages
            chatBody.innerHTML = '';
            // Add back the welcome message with suggestions
            const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            chatBody.innerHTML = `
                <div class="chat-message bot-message">
                    <div class="bot-avatar">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="message-content">
                        Chat history has been deleted. I'm your Case Assistant. Ask me anything related to barangay laws, blotter cases, or hearings.
                        
                        <div class="chat-suggestions">
                            <button class="chat-suggestion" data-query="What is Katarungang Pambarangay?">What is Katarungang Pambarangay?</button>
                            <button class="chat-suggestion" data-query="How to file a complaint?">How to file a complaint?</button>
                            <button class="chat-suggestion" data-query="What cases can be resolved at barangay level?">Cases at barangay level</button>
                            <button class="chat-suggestion" data-query="Who can attend barangay hearings?">Who can attend hearings?</button>
                            <button class="chat-suggestion" data-query="How to prepare for mediation?">Prepare for mediation</button>
                        </div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            chatOptionsMenu.classList.add('hidden');
            // Re-initialize suggestion buttons
            initSuggestionButtons();
        });
        
        function sendMessage() {
            const message = chatInput.value.trim();
            if (!message) return;
            
            const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            const userMessageHTML = `
                <div class="chat-message user-message">
                    <div class="message-content">
                        ${message}
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            
            chatBody.innerHTML += userMessageHTML;
            chatInput.value = '';
            chatBody.scrollTop = chatBody.scrollHeight;
            
            // Add typing animation
            const typingAnimationId = 'typing-animation-' + Date.now();
            const typingHTML = `
                <div class="chat-message bot-message" id="${typingAnimationId}">
                    <div class="bot-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content typing-animation">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>
            `;
            chatBody.innerHTML += typingHTML;
            chatBody.scrollTop = chatBody.scrollHeight;
            
            fetch('../chatbot/chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ message })
            })
            .then(response => response.json())
            .then(data => {
                // Remove typing animation
                const typingElement = document.getElementById(typingAnimationId);
                if (typingElement) {
                    typingElement.remove();
                }
                
                // Raw bot response
                let botResponse = data.reply;

                // Convert Markdown-style bold (**text**) to HTML <strong>text</strong>
                function applyMarkdownBold(html) {
                    if (!html) return html;
                    return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                }

                // Helper to standardize textual legal citations inside free-form model output
                function standardizeLegalCitations(html) {
                    if (!html) return html;
                    // Normalize common long form to RA 7160
                    html = html.replace(/Local Government Code of 1991/gi, 'RA 7160');
                    // Convert 'Sections' phrasing before individual wrapping
                    html = html.replace(/\bSections?\s+([0-9]{2,3}(?:\s*-\s*[0-9]{2,3})?)/gi, 'Sec. $1');
                    // Standardize 'Section' to 'Sec.'
                    html = html.replace(/\bSection\s+([0-9]{2,3}[a-z0-9()\-–]*)/gi, 'Sec. $1');
                    // Wrap RA 7160
                    html = html.replace(/\bRA\s*7160\b/g, '<span class="law-ref">RA 7160</span>');
                    // Wrap Sec. occurrences (avoid double wrapping by temporary marker)
                    html = html.replace(/Sec\.\s+([0-9]{2,3}[a-z0-9()\-–]*)/gi, function(m, g1){
                        return '<span class="law-ref">Sec. '+ g1 +'</span>';
                    });
                    return html;
                }

                function formatLegalReference(raw) {
                    if (!raw) return '';
                    let ref = raw.trim();
                    ref = ref.replace(/Local Government Code of 1991/gi,'RA 7160');
                    ref = ref.replace(/\bSection\b/gi,'Sec.');
                    // Ensure each Sec. token wrapped
                    ref = ref.replace(/Sec\.\s+[0-9]{2,3}[a-z0-9()\-–]*/gi, function(m){return '<span class="law-ref">'+m+'</span>';});
                    if (!/RA\s*7160/i.test(ref)) {
                        ref = '<span class="law-ref">RA 7160</span> ' + ref;
                    }
                    return ref;
                }

                // First apply bold conversion, then legal citation standardization
                botResponse = applyMarkdownBold(botResponse);
                botResponse = standardizeLegalCitations(botResponse);
                const sourceInfo = getSourceInfo(message);
                const formattedLegalRef = sourceInfo ? formatLegalReference(sourceInfo.legalRef) : '';
                const botMessageHTML = `
                    <div class="chat-message bot-message">
                        <div class="bot-avatar"><i class="fas fa-robot"></i></div>
                        <div class="message-content">
                            ${botResponse}
                            
                            ${sourceInfo ? `
                                <div class=\"legal-reference\"><b>Legal Basis:</b> ${formattedLegalRef}</div>
                                <div class=\"source-citation\">
                                    Source: ${sourceInfo.mainSource}
                                    <a href=\"${sourceInfo.url}\" target=\"_blank\" class=\"source-link\">View Source</a>
                                    <div class=\"update-timestamp\">Last verified: ${sourceInfo.lastVerified}</div>
                                </div>
                            ` : ''}
                            <div class="chat-suggestions">
                                ${getSuggestions(message)}
                            </div>
                            <div class="message-time">${timestamp}</div>
                        </div>
                    </div>
                `;
                chatBody.innerHTML += botMessageHTML;
                chatBody.scrollTop = chatBody.scrollHeight;
                // Re-initialize suggestion buttons for the newly added message
                initSuggestionButtons();
            })
            .catch(error => {
                // Remove typing animation
                const typingElement = document.getElementById(typingAnimationId);
                if (typingElement) {
                    typingElement.remove();
                }
                
                console.error('Error:', error);
                chatBody.innerHTML += `
                    <div class="chat-message bot-message">
                        <div class="bot-avatar"><i class="fas fa-robot"></i></div>
                        <div class="message-content">
                            Sorry, an error occurred. Please try again later.
                           
                            <div class="chat-suggestions">
                                <button class="chat-suggestion" data-query="What is Katarungang Pambarangay?">What is Katarungang Pambarangay?</button>
                                <button class="chat-suggestion" data-query="How to file a complaint?">How to file a complaint?</button>
                                <button class="chat-suggestion" data-query="What cases can be resolved at barangay level?">Cases at barangay level</button>
                                <button class="chat-suggestion" data-query="Who can attend barangay hearings?">Who can attend hearings?</button>
                                <button class="chat-suggestion" data-query="How to prepare for mediation?">Prepare for mediation</button>
                            </div>
                             <div class="message-time">${timestamp}</div>
                        </div>
                    </div>
                `;
                // Re-initialize suggestion buttons for the error message
                initSuggestionButtons();
            });
        }
        
        sendButton.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Set focus on input when page loads
        chatInput.focus();
    });
    </script>
</body>
</html>