<?php
session_start();

// Store session ID before destroying to ensure it's truly gone
$sessionId = session_id();

// Completely destroy the session
session_unset();
session_destroy();

// Set session cookie to expire immediately
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
header("Set-Cookie: PHPSESSID=deleted; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/; httponly");

// Clear all session-related headers
header("Clear-Site-Data: \"cache\", \"cookies\", \"storage\"");

header("Location: login/Login.php");
exit();
?>