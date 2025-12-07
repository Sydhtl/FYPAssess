<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(401);
    echo 'Unauthorized';
    exit();
}

$lecturerId = $_SESSION['upmId'];

$stmt = $conn->prepare("SELECT Stamp_File FROM stamp WHERE Lecturer_ID = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo 'Prepare failed';
    exit();
}
$stmt->bind_param('s', $lecturerId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo 'Stamp not found';
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
header('Content-Disposition: inline; filename="stamp_' . $lecturerId . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo $blob;
$conn->close();
?>
