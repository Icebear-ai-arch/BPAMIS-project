<?php
// Serve JS with a permissive Service-Worker-Allowed header so scope can be the app root.
// If deployed under /BPAMIS/... => allow scope '/BPAMIS/'. If deployed at domain root => allow scope '/'.
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$pathParts = array_values(array_filter(explode('/', $uriPath)));

$knownTopLevel = [
    'SecMenu','LuponHeadMenu','OfficialMenu','ResidentMenu','ExternalMenu',
    'controllers','includes','bpamis_website','Assets','assets','uploads','uploads_id',
    'vendor','src','server','sql','tools','chatbot','phpmailer','PhpSpreadsheet'
];

$appRoot = '/';
if (!empty($pathParts) && !in_array($pathParts[0], $knownTopLevel, true)) {
    $appRoot = '/' . $pathParts[0];
}

// Set proper headers for service worker
header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: ' . rtrim($appRoot, '/') . '/');
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

// Read and output the actual service worker file
$swPath = __DIR__ . '/../sw.js';
if (file_exists($swPath)) {
    readfile($swPath);
} else {
    // Fallback minimal service worker if file not found
    echo "self.addEventListener('install', e => self.skipWaiting());\n";
    echo "self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));\n";
}
?>
