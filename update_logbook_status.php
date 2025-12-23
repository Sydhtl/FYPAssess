<?php
// update_logbook_status.php
session_start();
header('Content-Type: application/json');
include '../db_connect.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$logbookID = isset($input['logbook_id']) ? intval($input['logbook_id']) : null;
$newStatus = isset($input['status']) ? $input['status'] : null;

// Validate input
if (!$logbookID || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Validate status value
$validStatuses = ['Approved', 'Waiting for Approval', 'Declined'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

// A. Get Login ID 
if (isset($_SESSION['user_id'])) {
    $loginID = $_SESSION['user_id'];
} else {
    $loginID = 'hazura'; // Fallback
}

// Get supervisor ID to verify permission
$supervisorID = null;
$stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
$stmt->bind_param("s", $loginID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $supervisorID = $row['Supervisor_ID'];
}

if (!$supervisorID) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify that this logbook belongs to this supervisor or has NULL supervisor
// Also update the supervisor_id if it's NULL
$verifyStmt = $conn->prepare("SELECT Logbook_ID, Supervisor_ID, Student_ID FROM logbook WHERE Logbook_ID = ?");
$verifyStmt->bind_param("i", $logbookID);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Logbook not found']);
    exit;
}

$logbookRow = $verifyResult->fetch_assoc();
$logbookSupervisorID = $logbookRow['Supervisor_ID'];
$studentID = $logbookRow['Student_ID'];

// Verify the supervisor has access to this student
$accessStmt = $conn->prepare("SELECT Student_ID FROM student_enrollment WHERE Student_ID = ? AND Supervisor_ID = ?");
$accessStmt->bind_param("si", $studentID, $supervisorID);
$accessStmt->execute();
$accessResult = $accessStmt->get_result();

if ($accessResult->num_rows === 0 && $logbookSupervisorID !== null && $logbookSupervisorID != $supervisorID) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - This student is not assigned to you']);
    exit;
}

// Update the logbook status and set Supervisor_ID if it's currently NULL
$updateStmt = $conn->prepare("UPDATE logbook SET Logbook_Status = ?, Supervisor_ID = ? WHERE Logbook_ID = ?");
$updateStmt->bind_param("sii", $newStatus, $supervisorID, $logbookID);

if ($updateStmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'logbook_id' => $logbookID,
        'new_status' => $newStatus
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
