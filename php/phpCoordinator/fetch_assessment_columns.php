<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['upmId'];

try {
    // Get parameters
    $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    $fypSession = isset($_GET['year']) ? $_GET['year'] : null;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : null;

    if (!$courseId || !$fypSession || !$semester) {
        throw new Exception('Missing required parameters: course_id, year, and semester are required');
    }

    // Get coordinator's department
    $deptQuery = "SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1";
    $deptStmt = $conn->prepare($deptQuery);
    if (!$deptStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $deptStmt->bind_param('s', $userId);
    $deptStmt->execute();
    $deptResult = $deptStmt->get_result();
    if (!$deptRow = $deptResult->fetch_assoc()) {
        throw new Exception('Coordinator not found');
    }
    $departmentId = $deptRow['Department_ID'];
    $deptStmt->close();

    // Get FYP_Session_ID for the given course, year, and semester
    $sessionQuery = "SELECT FYP_Session_ID FROM fyp_session 
                     WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ? 
                     LIMIT 1";
    $sessionStmt = $conn->prepare($sessionQuery);
    if (!$sessionStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $sessionStmt->bind_param('isi', $courseId, $fypSession, $semester);
    $sessionStmt->execute();
    $sessionResult = $sessionStmt->get_result();
    
    if (!$sessionRow = $sessionResult->fetch_assoc()) {
        $sessionStmt->close();
        echo json_encode([
            'success' => true,
            'columns' => []
        ]);
        $conn->close();
        exit();
    }
    
    $fypSessionId = $sessionRow['FYP_Session_ID'];
    $sessionStmt->close();

    // Fetch assessments with their learning objective allocations and criteria
    // Format: Assessment_Name + Criteria_Name + LearningObjective_Code + (Percentage)
    $columnsQuery = "SELECT 
                        a.Assessment_ID,
                        a.Assessment_Name,
                        loa.Criteria_ID,
                        ac.Criteria_Name,
                        loa.LearningObjective_Code,
                        loa.Percentage,
                        loa.LO_Allocation_ID
                     FROM assessment a
                     INNER JOIN learning_objective_allocation loa ON a.Assessment_ID = loa.Assessment_ID
                     LEFT JOIN assessment_criteria ac ON loa.Criteria_ID = ac.Criteria_ID
                     WHERE a.Course_ID = ?
                       AND loa.Course_ID = ?
                       AND loa.FYP_Session_ID = ?
                     ORDER BY a.Assessment_ID, loa.Criteria_ID, loa.LearningObjective_Code";

    $columnsStmt = $conn->prepare($columnsQuery);
    if (!$columnsStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $columnsStmt->bind_param('iii', $courseId, $courseId, $fypSessionId);
    $columnsStmt->execute();
    $columnsResult = $columnsStmt->get_result();
    
    $columns = [];
    while ($row = $columnsResult->fetch_assoc()) {
        // Format: Assessment_Name + Criteria_Name (if exists) + LearningObjective_Code + (Percentage%)
        $criteriaName = $row['Criteria_Name'] ? ' - ' . $row['Criteria_Name'] : '';
        $columnTitle = $row['Assessment_Name'] . $criteriaName . ' ' . $row['LearningObjective_Code'] . ' (' . number_format($row['Percentage'], 2) . '%)';
        
        $columns[] = [
            'assessment_id' => $row['Assessment_ID'],
            'assessment_name' => $row['Assessment_Name'],
            'criteria_id' => $row['Criteria_ID'],
            'criteria_name' => $row['Criteria_Name'] ?? null,
            'learning_objective_code' => $row['LearningObjective_Code'],
            'percentage' => $row['Percentage'],
            'column_title' => $columnTitle,
            'lo_allocation_id' => $row['LO_Allocation_ID']
        ];
    }
    $columnsStmt->close();

    echo json_encode([
        'success' => true,
        'columns' => $columns
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>
