<?php
include '../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

// Ensure only supervisors can update
if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Supervisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$studentId = isset($input['student_id']) ? trim($input['student_id']) : '';
$action    = isset($input['action']) ? trim($input['action']) : '';

if ($studentId === '' || ($action !== 'approve' && $action !== 'reject')) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    if ($action === 'approve') {
        // Move Proposed_Title -> Project_Title, clear Proposed_Title, set status Approved
        $stmt = $conn->prepare("UPDATE fyp_project SET Project_Title = Proposed_Title, Proposed_Title = NULL, Title_Status = 'Approved' WHERE Student_ID = ? AND Proposed_Title IS NOT NULL AND Proposed_Title != ''");
        if (!$stmt) { throw new Exception('Prepare failed: ' . $conn->error); }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            echo json_encode(['success' => false, 'message' => 'No proposed title to approve']);
            exit();
        }
    } else {
        // Reject: keep Proposed_Title, set status Rejected
        $stmt = $conn->prepare("UPDATE fyp_project SET Title_Status = 'Rejected' WHERE Student_ID = ? AND Proposed_Title IS NOT NULL AND Proposed_Title != ''");
        if (!$stmt) { throw new Exception('Prepare failed: ' . $conn->error); }
        $stmt->bind_param('s', $studentId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected <= 0) {
            echo json_encode(['success' => false, 'message' => 'No proposed title to reject']);
            exit();
        }
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('update_title_status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

$conn->close();
