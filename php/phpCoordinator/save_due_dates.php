<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['allocations']) || !is_array($input['allocations'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid data format']);
    exit();
}

if (!isset($input['fyp_session_id']) || $input['fyp_session_id'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'FYP_Session_ID is required']);
    exit();
}

$fypSessionId = intval($input['fyp_session_id']);

try {
    $conn->begin_transaction();
    
    foreach ($input['allocations'] as $allocation) {
        if (!isset($allocation['assessment_id'])) {
            continue;
        }
        
        $assessmentId = intval($allocation['assessment_id']);
        
        if (isset($allocation['due_dates']) && is_array($allocation['due_dates'])) {
            foreach ($allocation['due_dates'] as $dueDate) {
                if (empty($dueDate['start_date']) || empty($dueDate['end_date']) || 
                    empty($dueDate['start_time']) || empty($dueDate['end_time']) || 
                    empty($dueDate['role'])) {
                    continue;
                }
                
                $startDate = $dueDate['start_date'];
                $endDate = $dueDate['end_date'];
                $startTime = $dueDate['start_time'];
                $endTime = $dueDate['end_time'];
                $role = $dueDate['role'];
                $dueId = isset($dueDate['due_id']) ? intval($dueDate['due_id']) : 0;
                
                // Check if due_date already exists (if due_id is provided)
                if ($dueId > 0) {
                    // Update existing due_date
                    $updateSql = "UPDATE due_date SET Start_Date = ?, End_Date = ?, Start_Time = ?, End_Time = ?, Role = ? WHERE Due_ID = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    if (!$updateStmt) {
                        throw new Exception('Prepare update failed: ' . $conn->error);
                    }
                    $updateStmt->bind_param('sssssi', $startDate, $endDate, $startTime, $endTime, $role, $dueId);
                    if (!$updateStmt->execute()) {
                        $updateStmt->close();
                        throw new Exception('Update failed: ' . $conn->error);
                    }
                    $updateStmt->close();
                } else {
                    // Insert new due_date with Assessment_ID and FYP_Session_ID
                    $insertSql = "INSERT INTO due_date (Assessment_ID, FYP_Session_ID, Start_Date, End_Date, Start_Time, End_Time, Role) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $conn->prepare($insertSql);
                    if (!$insertStmt) {
                        throw new Exception('Prepare insert failed: ' . $conn->error);
                    }
                    $insertStmt->bind_param('iisssss', $assessmentId, $fypSessionId, $startDate, $endDate, $startTime, $endTime, $role);
                    
                    if (!$insertStmt->execute()) {
                        $insertStmt->close();
                        throw new Exception('Insert failed: ' . $conn->error);
                    }
                    $insertStmt->close();
                }
            }
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
$conn->close();
?>
