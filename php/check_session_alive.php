<?php
// This endpoint checks if the user's session is still alive
// Returns JSON: {valid: true/false}
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['upmId'])) {
    echo json_encode(['valid' => false]);
} else {
    echo json_encode(['valid' => true]);
}
exit();
?>
