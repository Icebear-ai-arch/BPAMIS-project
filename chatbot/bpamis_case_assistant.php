
<style>
/* Chatbot Button Styles */
.chatbot-button {
    /* Sticky: fixed to the viewport but offset from the corner so it's not flush */
    position: fixed;
    bottom: 2.5rem; /* desktop offset */
    right: 2.5rem;  /* desktop offset */
    margin: 0;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0281d4, #0c9ced);
    box-shadow: 0 4px 15px rgba(2, 129, 212, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2147483647 !important; /* force on top of everything */
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    cursor: pointer;
    border: none;
    outline: none;
    pointer-events: auto;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
    transform: translateZ(0);
}

.chatbot-button:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 6px 20px rgba(2, 129, 212, 0.35);
}

.chatbot-button i {
    font-size: 24px;
    color: white;
    transition: transform 0.3s ease;
}

.chatbot-button:hover i {
    transform: rotate(10deg);
}

.pulse {
    position: absolute;
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background-color: rgba(2, 129, 212, 0.7);
    opacity: 0;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(0.95);
        opacity: 0.7;
    }
    70% {
        transform: scale(1.1);
        opacity: 0;
    }
    100% {
        transform: scale(0.95);
        opacity: 0;
    }
}

.chatbot-container {
    /* Fixed to viewport and positioned above the button; constrained width to avoid overflow */
    position: fixed;
    bottom: calc(2.5rem + 72px); /* sit above the button (desktop offset + container gap) */
    right: 2.5rem;
    left: auto;
    width: min(350px, calc(100% - 32px));
    max-width: 350px;
    max-height: 80vh;
    border-radius: 16px;
    background: white;
    box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
    z-index: 2147483646 !important; /* just under the button, forced on top */
    overflow: hidden;
    opacity: 0;
    transform: translateY(20px) scale(0.95);
    pointer-events: none;
    transition: opacity 0.25s ease, transform 0.25s ease;
    /* Ensure chatbot never interferes with navbar or other fixed elements */
    isolation: isolate;
}

.chatbot-container.active {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: all;
}

.chatbot-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #0281d4, #0c9aedb9);
    color: white;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.chatbot-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
    font-size: 1rem;
}

.chatbot-close {
    background: transparent;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.chatbot-close:hover {
    transform: rotate(90deg);
}

/* Chat Options Menu Styles */
#chatOptionsButton {
    transition: all 0.2s ease;
}

#chatOptionsButton:hover {
    background-color: rgba(255, 255, 255, 0.2);
}

#chatOptionsMenu {
    transform-origin: top left;
    transition: opacity 0.18s cubic-bezier(.2,.8,.2,1), transform 0.18s cubic-bezier(.2,.8,.2,1);
    box-shadow: 0 8px 30px rgba(6, 24, 63, 0.16);
    border-radius: 10px;
    overflow: hidden;
    background: rgba(255,255,255,0.98);
    border: 1px solid rgba(12,156,237,0.08);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    min-width: 9rem;
    padding: 6px 6px;
    z-index: 2147483647;
    box-sizing: border-box;
}
    #chatOptionsMenu a {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 8px 10px;
        color: #0f1724;
        font-size: 0.7rem;
        text-decoration: none;
        border-radius: 8px;
        transition: background 0.16s ease, color 0.16s ease, transform 0.12s ease;
        white-space: nowrap;
    }

    #chatOptionsMenu a:focus,
    #chatOptionsMenu a:hover {
        background: linear-gradient(90deg, rgba(12,156,237,0.06), rgba(12,156,237,0.03));
        color: #053763;
        transform: translateX(2px);
        outline: none;
    }

    #chatOptionsMenu a i {
        color: #0c9ced;
        min-width: 18px;
        text-align: center;
        font-size: 0.7rem;
    }

    #chatOptionsMenu a.delete i,
    #chatOptionsMenu a.delete {
        color: #ef4444;
    }

    /* small divider between menu items (visual grouping) */
    #chatOptionsMenu .menu-divider {
        height: 1px;
        background: linear-gradient(90deg, rgba(12,156,237,0.06), rgba(12,156,237,0.02));
        margin: 6px 4px;
        border-radius: 1px;
    }

    /* Mobile adjustments */
    @media (max-width: 640px) {
        #chatOptionsMenu {
            right: 0 !important;
            left: auto !important;
            min-width: 10rem;
            padding: 4px 4px;
        }

        #chatOptionsMenu a {
            font-size: 0.9rem;
            padding: 8px 10px;
        }
    }

.chatbot-body {
    height: 340px;
    overflow-y: auto;
    padding: 20px;
}

.chatbot-footer {
    padding: 12px 15px;
    border-top: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
}

.chatbot-input {
    flex: 1;
    border: 1px solid #e5e7eb;
    border-radius: 20px;
    padding: 10px 15px;
    font-size: 14px;
    outline: none;
    transition: border-color 0.2s ease;
}

.chatbot-input:focus {
    border-color: #0c9ced;
    box-shadow: 0 0 0 2px rgba(12, 156, 237, 0.1);
}

.send-button {
    background: #0c9ced;
    color: white;
    border: none;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    margin-left: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s ease;
}

.send-button:hover {
    background: #0281d4;
}

.chat-message {
    margin-bottom: 15px;
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
    max-width: 80%;
    padding: 12px 16px;
    border-radius: 18px;
    font-size: 14px;
    line-height: 1.4;
    position: relative;
}

.user-message .message-content {
    background-color: #0c9ced;
    color: white;
    border-bottom-right-radius: 4px;
    margin-right: 10px;
}

.bot-message .message-content {
    background-color: #f0f7ff;
    color: #333;
    border-bottom-left-radius: 4px;
    margin-left: 10px;
}

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

.bot-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #e0effe;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bot-avatar i {
    color: #0281d4;
    font-size: 16px;
}

.message-time {
    font-size: 10px;
    color: #888;
    margin-top: 4px;
    text-align: right;
}

/* Chat suggestion prompts */
.chat-suggestions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
    margin-top: 15px;
    width: 100%;
}

.chat-suggestion {
    background-color: #e0effe;
    color: #0281d4;
    font-size: 12px;
    padding: 8px 12px;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    outline: none;
    line-height: 1.25;
    text-align: left;
    white-space: normal; /* allow wrapping */
    word-break: break-word;
    overflow-wrap: anywhere;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    display: block;
}

.chat-suggestion:hover {
    background-color: #bae2fd;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Extra styling for very long suggestions occupying full row */
.chat-suggestion.long {
    grid-column: 1 / -1; /* occupy full row */
    font-weight: 500;
    background: linear-gradient(90deg,#e0effe,#d3ecff);
    font-size: 13px;
    padding: 10px 14px;
    line-height: 1.35;
    position: relative;
}

/* Legal reference & source citation styles (parity with standalone assistant) */
.legal-reference {
    margin-top: 12px;
    font-size: 12px;
    font-weight: 500;
    color: #0c63b3;
    background: #e6f3ff;
    padding: 6px 10px;
    border-left: 3px solid #0c9ced;
    border-radius: 6px;
}

/* Inline law reference token */
.law-ref { background:#d9eefc; color:#065a8f; padding:2px 6px; border-radius:4px; font-weight:600; font-size:11px; display:inline-block; margin:2px 2px 2px 0; }

.source-citation {
    margin-top: 8px;
    font-size: 11px;
    color: #555;
    background: #f5f9fc;
    padding: 6px 10px;
    border-radius: 6px;
    line-height: 1.4;
}

.source-citation .source-link {
    color: #0c9ced;
    text-decoration: underline;
    margin-left: 4px;
}

.update-timestamp {
    font-size: 10px;
    color: #888;
    margin-top: 4px;
}

/* Mobile responsiveness for chatbot */
@media (max-width: 640px) {
    .chatbot-button {
        width: 50px;
        height: 50px;
        bottom: 1.5rem; /* mobile offset */
        right: 1.5rem;  /* mobile offset */
        z-index: 9999;
    }
    
    .chatbot-button i {
        font-size: 20px;
    }
    
    .chatbot-container {
        /* Mobile: take most of the viewport width but keep safe margins */
        width: calc(100% - 32px);
        max-width: calc(100% - 32px);
        right: 1.5rem;
        left: auto;
        bottom: calc(1.5rem + 56px);
        z-index: 2147483646 !important; /* just under the button, forced on top */
        border-radius: 1rem;
    }

    /* Header */
    .chatbot-header {
        padding: 0.65rem 0.75rem;
        border-radius: 1rem 1rem 0 0;
    }

    .chatbot-header h3 {
        font-size: 0.85rem;
        gap: 0.4rem;
    }

    .chatbot-header h3 i {
        font-size: 0.75rem;
    }

    #chatOptionsButton {
        width: 1.75rem;
        height: 1.75rem;
    }

    #chatOptionsButton i {
        font-size: 0.7rem;
    }

    #chatOptionsMenu {
        width: 8.5rem;
    }

    #chatOptionsMenu a {
        font-size: 0.7rem;
        padding: 0.45rem 0.75rem;
    }

    #chatOptionsMenu a i {
        font-size: 0.65rem;
        width: 0.9rem;
        margin-right: 0.4rem;
    }

    .chatbot-close {
        font-size: 1rem;
    }

    /* Body */
    .chatbot-body {
        height: 320px;
        padding: 0.75rem;
    }

    /* Messages */
    .chat-message {
        margin-bottom: 0.65rem;
    }

    .bot-avatar {
        width: 1.75rem;
        height: 1.75rem;
    }

    .bot-avatar i {
        font-size: 0.75rem;
    }

    .message-content {
        max-width: 82%;
        padding: 0.65rem 0.75rem;
        border-radius: 0.85rem;
        font-size: 0.7rem;
        line-height: 1.4;
    }

    .user-message .message-content {
        border-bottom-right-radius: 0.3rem;
        margin-right: 0.5rem;
    }

    .bot-message .message-content {
        border-bottom-left-radius: 0.3rem;
        margin-left: 0.5rem;
    }

    .message-time {
        font-size: 8px;
        margin-top: 0.3rem;
    }

    /* Typing Animation */
    .typing-animation {
        padding: 0.35rem 0.6rem;
        column-gap: 0.3rem;
    }

    .typing-dot {
        width: 0.35rem;
        height: 0.35rem;
    }

    /* Suggestions */
    .chat-suggestions {
        gap: 0.35rem;
        margin-top: 0.65rem;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    }

    .chat-suggestion {
        font-size: 9px;
        padding: 0.4rem 0.6rem;
        border-radius: 0.75rem;
        line-height: 1.2;
    }

    .chat-suggestion.long {
        font-size: 9px;
        padding: 0.45rem 0.65rem;
    }

    /* Legal References */
    .legal-reference {
        margin-top: 0.6rem;
        font-size: 0.7rem;
        padding: 0.45rem 0.65rem;
        border-radius: 0.35rem;
        border-left-width: 2px;
    }

    .law-ref {
        font-size: 9px;
        padding: 0.15rem 0.35rem;
        border-radius: 0.25rem;
        margin: 0.1rem 0.1rem 0.1rem 0;
    }

    .source-citation {
        margin-top: 0.5rem;
        font-size: 9px;
        padding: 0.4rem 0.6rem;
        border-radius: 0.35rem;
    }

    .source-citation .source-link {
        font-size: 9px;
    }

    .update-timestamp {
        font-size: 8px;
        margin-top: 0.2rem;
    }

    /* Footer */
    .chatbot-footer {
        padding: 0.6rem 0.65rem;
    }

    .chatbot-input {
        padding: 0.55rem 0.75rem;
        font-size: 0.7rem;
        border-radius: 1rem;
    }

    .send-button {
        width: 2rem;
        height: 2rem;
        margin-left: 0.5rem;
    }

    .send-button i {
        font-size: 0.7rem;
    }
}

@media (max-width: 380px) {
    .chatbot-container {
        width: calc(100% - 24px);
        right: 12px;
        left: 12px;
    }

    .chatbot-header {
        padding: 0.55rem 0.65rem;
    }

    .chatbot-header h3 {
        font-size: 0.8rem;
    }

    .chatbot-body {
        height: 300px;
        padding: 0.65rem;
    }

    .message-content {
        font-size: 0.65rem;
        padding: 0.55rem 0.65rem;
    }

    .chat-suggestion {
        font-size: 8px;
        padding: 0.35rem 0.5rem;
    }

    .chatbot-input {
        font-size: 0.65rem;
        padding: 0.5rem 0.65rem;
    }

    .send-button {
        width: 1.85rem;
        height: 1.85rem;
    }

    .send-button i {
        font-size: 0.65rem;
    }

    
}
</style>

<!-- Chatbot Button and Container -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
<button class="chatbot-button" id="chatbotButton" aria-label="Open case assistant chatbot">
    <i class="fas fa-robot"></i>
</button>

<div class="chatbot-container" id="chatbotContainer">
    <div class="chatbot-header">
        <div class="flex items-center">
            <h3><i class="fas fa-robot"></i> Case Assistant</h3>
            <div class="relative ml-2">
                <button id="chatOptionsButton" class="text-white hover:bg-blue-600 rounded-full w-8 h-8 flex items-center justify-center">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <div id="chatOptionsMenu" class="absolute left-0 mt-2 w-40 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                    <a href="#" id="openNewTab" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-external-link-alt mr-2"></i>Open in New Tab
                    </a>
                    <a href="#" id="newChat" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-plus mr-2"></i>New Chat
                    </a>
                    <a href="#" id="deleteChat" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-trash mr-2"></i>Delete Chat
                    </a>
                </div>
            </div>
        </div>
        <button class="chatbot-close" id="chatbotClose" aria-label="Close chatbot">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="chatbot-body" id="chatbotBody">
        <div class="chat-message bot-message">
            <div class="bot-avatar">
                <i class="fas fa-robot"></i>
            </div>
            <div class="message-content">
                Hi there! I'm your Case Assistant. Ask me anything related to barangay laws, blotter cases, or hearings.
                
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
    <div class="chatbot-footer">
        <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Type your question here..." aria-label="Type your message">
        <button class="send-button" id="sendButton" aria-label="Send message">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const chatbotButton = document.getElementById('chatbotButton');
    const chatbotContainer = document.getElementById('chatbotContainer');
    const chatbotClose = document.getElementById('chatbotClose');
    const chatbotInput = document.getElementById('chatbotInput');
    const sendButton = document.getElementById('sendButton');
    const chatbotBody = document.getElementById('chatbotBody');
    
    // Ensure chatbot is closed on page load/reload
    if (chatbotContainer) {
        chatbotContainer.classList.remove('active');
    }
    
    // Chat options menu elements
    const chatOptionsButton = document.getElementById('chatOptionsButton');
    const chatOptionsMenu = document.getElementById('chatOptionsMenu');
    const openNewTabOption = document.getElementById('openNewTab');
    const newChatOption = document.getElementById('newChat');
    const deleteChatOption = document.getElementById('deleteChat');

    // Source / reference mapping (mirrors standalone assistant)
    function getSourceInfo(query) {
        const qLower = query.toLowerCase();
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
            'what is katarungang pambarangay?': {
                mainSource: 'DILG FAQ',
                url: 'https://www.dilg.gov.ph/faqs/',
                lastVerified: 'September 4, 2025',
                legalRef: 'Local Government Code of 1991, Chapter 7, Sections 399-422'
            },
            'how to file a complaint?': {
                mainSource: 'LGUSS FAQs',
                url: 'https://bims.dilg.gov.ph/',
                lastVerified: 'September 4, 2025',
                legalRef: 'Local Government Code of 1991, Section 410'
            },
            'what cases can be resolved at barangay level?': {
                mainSource: 'DILG Region 3 FAQ',
                url: 'https://region3.dilg.gov.ph/index.php/about/faqs',
                lastVerified: 'September 4, 2025',
                legalRef: 'Local Government Code of 1991, Sections 408-409'
            },
            'who can attend barangay hearings?': {
                mainSource: 'DILG FAQ',
                url: 'https://www.dilg.gov.ph/faqs/',
                lastVerified: 'September 4, 2025',
                legalRef: 'Local Government Code of 1991, Section 404, 415'
            },
            'how to prepare for mediation?': {
                mainSource: 'DILG FAQ and Region 3 Guidelines',
                url: 'https://www.dilg.gov.ph/faqs/',
                lastVerified: 'September 4, 2025',
                legalRef: 'Local Government Code of 1991, Sections 412-413'
            }
        };
        // Exact match first
        if (sources[qLower]) return sources[qLower];
        // Fuzzy contains
        for (const key in sources) {
            if (qLower.includes(key)) return sources[key];
        }
        return null;
    }

    // Dynamic suggestion generator (mirrors standalone logic)
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
        const defaultSuggestions = [
            'What is Katarungang Pambarangay?',
            'How to file a complaint?',
            'What cases can be resolved at barangay level?',
            'Who can attend barangay hearings?',
            'How to prepare for mediation?'
        ];
        let suggestionsToUse = defaultSuggestions;
        for (let key in suggestionSets) {
            if (query.includes(key)) { suggestionsToUse = suggestionSets[key]; break; }
        }
        return suggestionsToUse.map(s => `<button class="chat-suggestion" data-query="${s}">${s}</button>`).join('');
    }

    // Track whether the last sent message was from a suggestion button
    let lastWasSuggestion = false;

    // Initialize suggestion buttons
    function initSuggestionButtons() {
        const suggestionButtons = document.querySelectorAll('.chat-suggestion');
        suggestionButtons.forEach(button => {
            const text = button.textContent.trim();
            if (text.length > 38) {
                button.classList.add('long');
                button.setAttribute('title', text); // tooltip for clarity
            }
            button.addEventListener('click', function() {
                const query = this.getAttribute('data-query');
                chatbotInput.value = query;
                // mark this send as coming from a suggestion so backend won't show out-of-scope
                lastWasSuggestion = true;
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
    openNewTabOption.addEventListener('click', () => {
        window.open('../chatbot/standalone_assistant.php', '_blank');
        chatOptionsMenu.classList.add('hidden');
    });

    newChatOption.addEventListener('click', () => {
        // Clear all chat messages
        chatbotBody.innerHTML = '';
        // Add back the welcome message with suggestions
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        // Add a notification about starting a new chat
        chatbotBody.innerHTML = `
            <div class="chat-message bot-message">
                <div class="bot-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">
                    New chat started! I'm your Case Assistant. Ask me anything related to barangay laws, blotter cases, or hearings.
                    
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

    deleteChatOption.addEventListener('click', () => {
        // Clear all chat messages
        chatbotBody.innerHTML = '';
        // Add back the welcome message with suggestions
        const timestamp = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        chatbotBody.innerHTML = `
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

    chatbotButton.addEventListener('click', () => {
        chatbotContainer.classList.toggle('active');
        chatbotInput.focus();
    });

    chatbotClose.addEventListener('click', () => {
        chatbotContainer.classList.remove('active');
    });

    function sendMessage() {
        const message = chatbotInput.value.trim();
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

    chatbotBody.innerHTML += userMessageHTML;
    chatbotInput.value = '';
    // Do not force scroll to bottom; keep current position until bot reply arrives
        
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
        chatbotBody.innerHTML += typingHTML;
        // Optional: ensure typing indicator visible without jumping to very bottom
        const typingEl = document.getElementById(typingAnimationId);
        if (typingEl) {
            typingEl.scrollIntoView({behavior:'smooth', block:'nearest'});
        }

        // include `suggested` flag when the message came from a suggestion button
        const wasSuggestion = !!lastWasSuggestion;
        // reset the flag immediately so subsequent manual messages behave normally
        lastWasSuggestion = false;

        fetch('../chatbot/chatbot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ message, suggested: wasSuggestion })
        })
        .then(response => response.json())
        .then(data => {
            // Remove typing animation
            const typingElement = document.getElementById(typingAnimationId);
            if (typingElement) {
                typingElement.remove();
            }
            
            // Standardize legal citations inside bot response
            function standardizeLegalCitations(html){
                if(!html) return html;
                html = html.replace(/Local Government Code of 1991/gi,'RA 7160');
                html = html.replace(/\bSections?\s+([0-9]{2,3}(?:\s*-\s*[0-9]{2,3})?)/gi,'Sec. $1');
                html = html.replace(/\bSection\s+([0-9]{2,3}[a-z0-9()\-–]*)/gi,'Sec. $1');
                html = html.replace(/\bRA\s*7160\b/g,'<span class="law-ref">RA 7160</span>');
                html = html.replace(/Sec\.\s+[0-9]{2,3}[a-z0-9()\-–]*/gi, m => '<span class="law-ref">'+m+'</span>');
                return html;
            }

            // Convert Markdown-style bold (**text**) to HTML <strong>text</strong>
            function applyMarkdownBold(html){
                if(!html) return html;
                return html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            }

            function formatLegalReference(raw){
                if(!raw) return '';
                let ref = raw.replace(/Local Government Code of 1991/gi,'RA 7160').replace(/\bSection\b/gi,'Sec.');
                ref = ref.replace(/Sec\.\s+[0-9]{2,3}[a-z0-9()\-–]*/gi, m => '<span class="law-ref">'+m+'</span>');
                if(!/RA\s*7160/i.test(ref)) ref = '<span class="law-ref">RA 7160</span> '+ref;
                return ref;
            }

            let botResponse = standardizeLegalCitations(data.reply);
            botResponse = applyMarkdownBold(botResponse);
            const sourceInfo = getSourceInfo(message);
            const formattedLegalRef = sourceInfo ? formatLegalReference(sourceInfo.legalRef) : '';
            const botMessageHTML = `
                <div class="chat-message bot-message">
                    <div class="bot-avatar"><i class="fas fa-robot"></i></div>
                    <div class="message-content">
                        ${botResponse}
                        ${sourceInfo ? `
                            <div class="legal-reference"><strong>Legal Basis:</strong> ${formattedLegalRef}</div>
                            <div class="source-citation">Source: ${sourceInfo.mainSource}
                                <a href="${sourceInfo.url}" target="_blank" class="source-link">View Source</a>
                                <div class="update-timestamp">Last verified: ${sourceInfo.lastVerified}</div>
                            </div>
                        ` : ''}
                        <div class="chat-suggestions">${getSuggestions(message)}</div>
                        <div class="message-time">${timestamp}</div>
                    </div>
                </div>
            `;
            chatbotBody.innerHTML += botMessageHTML;
            // Scroll so that the top of the new bot message is visible (not anchored to very bottom)
            const lastMsg = chatbotBody.lastElementChild;
            if (lastMsg) {
                lastMsg.scrollIntoView({behavior:'smooth', block:'start'});
            }
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
            chatbotBody.innerHTML += `
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
            const lastMsg = chatbotBody.lastElementChild;
            if (lastMsg) {
                lastMsg.scrollIntoView({behavior:'smooth', block:'start'});
            }
            // Re-initialize suggestion buttons for the error message
            initSuggestionButtons();
        });
    }

    sendButton.addEventListener('click', sendMessage);
    chatbotInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
});

</script>