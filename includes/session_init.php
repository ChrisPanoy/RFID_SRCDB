<?php
/**
 * Consolidated Session Initializer
 * Handles session start and fixes some common server misconfigurations 
 * regarding session save paths (e.g., missing directories on cPanel).
 */

// Start output buffering to prevent "headers already sent" errors
ob_start();

$session_path = @ini_get('session.save_path');

// If session path is missing or not writable, or if we are on a known problematic server,
// we can try to use a local directory.
if (empty($session_path) || (!is_dir($session_path) && !@mkdir($session_path, 0777, true)) || !is_writable($session_path)) {
    // Try to use a 'sessions' folder in the root directory
    $local_session_path = __DIR__ . '/../sessions';
    if (!is_dir($local_session_path)) {
        @mkdir($local_session_path, 0777, true);
        @file_put_contents($local_session_path . '/.htaccess', "Deny from all");
    }
    
    if (is_dir($local_session_path) && is_writable($local_session_path)) {
        session_save_path($local_session_path);
    }
}

if (session_status() === PHP_SESSION_NONE) {
    // We suppress the warning with @ just in case the server is extremely restrictive,
    // though the above logic should have fixed the path already.
    if (!@session_start()) {
        // Last ditch effort: if session_start still fails due to path issues,
        // and we are outputting warnings, it will break headers.
        // We already have ob_start(), so we are safer.
    }
}
