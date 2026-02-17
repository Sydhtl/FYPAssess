<?php
// update_logbook_status.php
session_start();
include '../db_connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logbookId = $_POST['id'] ?? null;
    $statusInput = $_POST['status'] ?? null;

    if (!$logbookId || !$statusInput) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    // Map HTML values to DB ENUM values
    // DB: enum('Approved','Waiting for Approval','Rejected')
    $dbStatus = '';
    switch ($statusInput) {
        case 'Approved':
            $dbStatus = 'Approved';
            break;
        case 'Rejected':
            $dbStatus = 'Rejected';
            break;
        case 'Waiting for Approval':
        default:
            $dbStatus = 'Waiting for Approval';
            break;
    }

    $stmt = $conn->prepare("UPDATE logbook SET Logbook_Status = ? WHERE Logbook_ID = ?");
    $stmt->bind_param("si", $dbStatus, $logbookId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }

    $stmt->close();
    $conn->close();
}
?>