<?php
// Minimal smoke test for chatbot OpenRouter integration
header('Content-Type: text/plain');

// Simulate POST body
$_POST = [];
$payload = json_encode([ 'message' => 'What is Katarungang Pambarangay?' ]);

// Use output buffering to capture chatbot.php output
ob_start();
// Provide php://input via a temp stream workaround: include uses php://input directly, so we set it via stream wrapper
// On PHP built-in server or Apache, php://input is read-only; instead, directly call the same logic by including the file after
// creating a temp file and pointing to it via wrapper. Simpler: set a global var to signal test mode.
$GLOBALS['CHATBOT_TEST_PAYLOAD'] = $payload;
include __DIR__ . '/test_shim.php';
include __DIR__ . '/chatbot.php';
$out = ob_get_clean();
echo $out;
?>
