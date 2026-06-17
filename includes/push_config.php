<?php
// filepath: c:\xampp\htdocs\BPAMIS_01\includes\push_config.php
// Push Notification Configuration for Cross-Device Deployment

/**
 * Determines the base URL for the application dynamically
 * Works in development (localhost), staging, and production environments
 */
function getPushBaseUrl() {
    // Check if HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    
    $protocol = $isHttps ? 'https' : 'http';
    
    // Get host (handles proxy/load balancer scenarios)
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Determine app root from REQUEST_URI
    $pathParts = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'] ?? '')));
    $appRoot = isset($pathParts[0]) ? '/' . $pathParts[0] : '/';
    
    return [
        'protocol' => $protocol,
        'host' => $host,
        'appRoot' => $appRoot,
        'baseUrl' => $protocol . '://' . $host . $appRoot,
        'fullUrl' => $protocol . '://' . $host
    ];
}

/**
 * Gets the notification icon URL (absolute)
 */
function getPushIconUrl() {
    $config = getPushBaseUrl();
    return $config['baseUrl'] . '/SecMenu/logo.png';
}

/**
 * Validates if a request is from an allowed origin (for CORS)
 */
function isAllowedOrigin($origin) {
    if (empty($origin)) return false;
    
    $config = getPushBaseUrl();
    $host = $config['host'];
    
    // Allow same host, localhost, and 127.0.0.1
    $allowed = [
        $host,
        'localhost',
        '127.0.0.1',
        'localhost:' . ($_SERVER['SERVER_PORT'] ?? '80'),
    ];
    
    foreach ($allowed as $allowedHost) {
        if (strpos($origin, $allowedHost) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Gets allowed CORS origin header value
 */
function getAllowedCorsOrigin() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (isAllowedOrigin($origin)) {
        return $origin;
    }
    
    // Fallback to current host
    $config = getPushBaseUrl();
    return $config['fullUrl'];
}
