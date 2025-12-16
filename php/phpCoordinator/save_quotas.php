<?php
include '../mysqlConnect.php';
session_start();

// Check if user is logged in as Coordinator
if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$userId = $_SESSION['upmId'];
$year = $input['year'] ?? '';
$semester = $input['semester'] ?? '';
$quotas = $input['quotas'] ?? []; // Array of {supervisor_id, quota}

if (empty($year) || empty($semester) || empty($quotas)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Get FYP_Session_IDs for the selected year and semester in the coordinator's department
$fypSessionIds = [];
$fypSessionQuery = "SELECT DISTINCT fs.FYP_Session_ID 
                    FROM fyp_session fs
                    INNER JOIN course c ON fs.Course_ID = c.Course_ID
                    INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
                    WHERE l.Lecturer_ID = ? 
                    AND fs.FYP_Session = ? 
                    AND fs.Semester = ?";
if ($stmt = $conn->prepare($fypSessionQuery)) {
    $stmt->bind_param("ssi", $userId, $year, $semester);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $fypSessionIds[] = $row['FYP_Session_ID'];
        }
    }
    $stmt->close();
}

if (empty($fypSessionIds)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No FYP sessions found for selected year and semester']);
    exit();
}

// Find the latest year and semester in the system
$latestYear = '';
$latestSemester = '';
$latestQuery = "SELECT FYP_Session, Semester 
                FROM fyp_session 
                ORDER BY FYP_Session DESC, Semester DESC 
                LIMIT 1";
if ($result = $conn->query($latestQuery)) {
    if ($row = $result->fetch_assoc()) {
        $latestYear = $row['FYP_Session'];
        $latestSemester = (int)$row['Semester'];
    }
    $result->free();
}

// Determine if current year/semester is the latest
// Compare year (string comparison works for "YYYY/YYYY" format) and semester (integer)
$isLatest = false;
if (!empty($latestYear) && !empty($latestSemester)) {
    $isLatest = ($year === $latestYear && (int)$semester === $latestSemester);
}

// Start transaction
$conn->begin_transaction();

try {
    // Process each quota
    foreach ($quotas as $quotaData) {
        $supervisorId = (int)($quotaData['supervisor_id'] ?? 0);
        $quota = (int)($quotaData['quota'] ?? 0);
        
        if ($supervisorId <= 0) {
            continue; // Skip invalid supervisor IDs
        }
        
        // First, delete existing quota records for this supervisor and year/semester
        // This ensures we don't have duplicate records if FYP_Session_IDs change
        $deletePlaceholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
        $deleteQuery = "DELETE FROM supervisor_quota_history 
                       WHERE Supervisor_ID = ? AND FYP_Session_ID IN ($deletePlaceholders)";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteTypes = 'i' . str_repeat('i', count($fypSessionIds));
        $deleteParams = array_merge([$supervisorId], $fypSessionIds);
        $deleteStmt->bind_param($deleteTypes, ...$deleteParams);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Save quota for ALL FYP_Session_IDs (all courses) for the selected year/semester
        // This ensures each course has its own quota record in supervisor_quota_history
        // Since we deleted existing records above, we can simply insert for each FYP_Session_ID
        foreach ($fypSessionIds as $fypSessionId) {
            $insertQuery = "INSERT INTO supervisor_quota_history (FYP_Session_ID, Quota, Supervisor_ID) 
                           VALUES (?, ?, ?)";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("iii", $fypSessionId, $quota, $supervisorId);
            $insertStmt->execute();
            $insertStmt->close();
        }
        
        // If this is the latest year/semester, update supervisor.Supervisor_Quota
        if ($isLatest) {
            $updateSupervisorQuery = "UPDATE supervisor SET Supervisor_Quota = ? WHERE Supervisor_ID = ?";
            $updateSupervisorStmt = $conn->prepare($updateSupervisorQuery);
            $updateSupervisorStmt->bind_param("ii", $quota, $supervisorId);
            $updateSupervisorStmt->execute();
            $updateSupervisorStmt->close();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Quotas saved successfully',
        'is_latest' => $isLatest
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error saving quotas: ' . $e->getMessage()
    ]);
}

$conn->close();
?>

