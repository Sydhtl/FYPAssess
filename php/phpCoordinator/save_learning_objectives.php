<?php
include '../mysqlConnect.php';
session_start();

// Ensure only Coordinators can save learning objectives
if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['courseId']) || !isset($data['learningObjectives']) || !isset($data['year']) || !isset($data['semester'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$userId = $_SESSION['upmId'];
$courseId = $data['courseId'];
$learningObjectives = $data['learningObjectives'];
$selectedYear = $data['year'];
$selectedSemester = $data['semester'];

try {
    // Verify that the coordinator has permission for this course's department
    $deptCheckQuery = "SELECT c.Department_ID 
                       FROM course c
                       INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
                       WHERE c.Course_ID = ? AND l.Lecturer_ID = ?
                       LIMIT 1";
    
    if ($stmt = $conn->prepare($deptCheckQuery)) {
        $stmt->bind_param("is", $courseId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No permission for this course']);
            exit();
        }
        $stmt->close();
    }
    
    // Resolve FYP_Session_ID for the selected course/year/semester
    $fypSessionId = null;
    $sessionQuery = "SELECT FYP_Session_ID FROM fyp_session WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ? LIMIT 1";
    if ($stmt = $conn->prepare($sessionQuery)) {
        $stmt->bind_param("isi", $courseId, $selectedYear, $selectedSemester);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $fypSessionId = (int)$row['FYP_Session_ID'];
        }
        $stmt->close();
    }

    if (!$fypSessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No matching FYP session found for the selected year and semester.']);
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();

    // Delete existing learning objective allocations for this course & session
    $deleteQuery = "DELETE FROM learning_objective_allocation 
                    WHERE Course_ID = ? AND FYP_Session_ID = ?";

    if ($stmt = $conn->prepare($deleteQuery)) {
        $stmt->bind_param("ii", $courseId, $fypSessionId);
        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();
    }

    // Insert new learning objective allocations scoped to the session
    $insertQuery = "INSERT INTO learning_objective_allocation 
                    (Course_ID, Assessment_ID, Criteria_ID, LearningObjective_Code, Percentage, FYP_Session_ID) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    
    $insertedCount = 0;
    
    if ($stmt = $conn->prepare($insertQuery)) {
        foreach ($learningObjectives as $item) {
            if (!isset($item['assessment_id']) || !isset($item['learningObjectives'])) {
                continue;
            }
            
            $assessmentId = $item['assessment_id'];
            
            // Process each learning objective for this assessment
            foreach ($item['learningObjectives'] as $lo) {
                if (empty($lo['objective'])) {
                    continue;
                }
                // Require criteria
                if (!isset($lo['criteria_id']) || $lo['criteria_id'] === '' || $lo['criteria_id'] === null) {
                    continue;
                }
                
                // Extract LO code from display format (e.g., "CPS 1(C) - Communication" -> "CPS 1(C)")
                $loCode = trim($lo['objective']);
                if (strpos($loCode, ' - ') !== false) {
                    $loCode = trim(substr($loCode, 0, strpos($loCode, ' - ')));
                }
                
                $percentage = isset($lo['marks']) && $lo['marks'] !== '' ? floatval($lo['marks']) : null;
                $criteriaId = intval($lo['criteria_id']);
                
                $stmt->bind_param("iiisdi", $courseId, $assessmentId, $criteriaId, $loCode, $percentage, $fypSessionId);
                if ($stmt->execute()) {
                    $insertedCount++;
                }
            }
        }
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully saved $insertedCount learning objective allocation(s)",
        'deleted' => $deletedCount,
        'inserted' => $insertedCount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error saving learning objectives: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
