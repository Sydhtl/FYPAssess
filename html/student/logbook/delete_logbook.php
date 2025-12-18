<?php
include '../../../php/mysqlConnect.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$logbookId = $_POST['logbook_id'] ?? null;
$studentId = $_SESSION['upmId'];

if (!$logbookId) {
    echo json_encode(['success' => false, 'error' => 'Missing logbook ID']);
    exit();
}

$conn->begin_transaction();

try {
    // Verify that this logbook belongs to the current student
    $verifyStmt = $conn->prepare("SELECT Logbook_ID FROM logbook WHERE Logbook_ID = ? AND Student_ID = ?");
    $verifyStmt->bind_param("is", $logbookId, $studentId);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result();
    
    if ($verifyResult->num_rows === 0) {
        throw new Exception("Logbook not found or unauthorized");
    }
    $verifyStmt->close();
    
    // Delete agendas first (foreign key constraint)
    $deleteAgendasStmt = $conn->prepare("DELETE FROM logbook_agenda WHERE Logbook_ID = ?");
    $deleteAgendasStmt->bind_param("i", $logbookId);
    
    if (!$deleteAgendasStmt->execute()) {
        throw new Exception("Failed to delete agendas: " . $deleteAgendasStmt->error);
    }
    $deleteAgendasStmt->close();
    
    // Delete logbook entry
    $deleteLogbookStmt = $conn->prepare("DELETE FROM logbook WHERE Logbook_ID = ? AND Student_ID = ?");
    $deleteLogbookStmt->bind_param("is", $logbookId, $studentId);
    
    if (!$deleteLogbookStmt->execute()) {
        throw new Exception("Failed to delete logbook: " . $deleteLogbookStmt->error);
    }
    $deleteLogbookStmt->close();
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Logbook deleted successfully']);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
