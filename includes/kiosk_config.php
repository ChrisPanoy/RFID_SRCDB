<?php
// Kiosk/public access token for lab dashboards and scan endpoints.
// IMPORTANT: Change this to a strong, random string (e.g., from a password generator).
// Anyone with this token can read the live feed and submit scans via the kiosk pages.
if (!defined('KIOSK_TOKEN')) {
    define('KIOSK_TOKEN', 'lab-kiosk-public-token-change-me-2025');
}
