<?php
// Allows test harness to inject request body into chatbot.php without changing production fetch flow.
if (isset($GLOBALS['CHATBOT_TEST_PAYLOAD'])) {
    // Create a temp stream and override php://input via stream wrapper context is non-trivial.
    // Instead, define a function chatbot_get_input() that chatbot.php will use.
    if (!function_exists('chatbot_get_input')) {
        function chatbot_get_input(): string {
            return (string)$GLOBALS['CHATBOT_TEST_PAYLOAD'];
        }
    }
}
?>
