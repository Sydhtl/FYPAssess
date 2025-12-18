<?php
session_start();
include '../db_connect.php';

// Get login ID
$loginID = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : (isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'hazura');

// Fetch signature from database
$stmt = $conn->prepare("SELECT Signature_File FROM signature_lecturer WHERE Lecturer_ID = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Prepare failed';
    exit();
}

$stmt->bind_param('s', $loginID);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo 'Signature not found';
    exit();
}

$stmt->bind_result($blob);
$stmt->fetch();
$stmt->close();

// Try to detect mime type from binary
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $blob ? finfo_buffer($finfo, $blob) : 'application/octet-stream';
finfo_close($finfo);
if (!$mime) {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="signature_' . $loginID . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $blob;
$conn->close();
?>