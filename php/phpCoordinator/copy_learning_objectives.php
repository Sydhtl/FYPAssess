<?php
include '../mysqlConnect.php';
session_start();

// Ensure only Coordinators can copy learning objectives
if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Coordinator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get JSON data from request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['courseId']) || !isset($data['year']) || !isset($data['semester'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

$userId = $_SESSION['upmId'];
$courseId = $data['courseId'];
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
    
    // Resolve FYP_Session_ID for the selected course/year/semester (target session)
    $targetFypSessionId = null;
    $sessionQuery = "SELECT FYP_Session_ID FROM fyp_session WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ? LIMIT 1";
    if ($stmt = $conn->prepare($sessionQuery)) {
        $stmt->bind_param("isi", $courseId, $selectedYear, $selectedSemester);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $targetFypSessionId = (int)$row['FYP_Session_ID'];
        }
        $stmt->close();
    }

    if (!$targetFypSessionId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No matching FYP session found for the selected year and semester.']);
        exit();
    }

    // Check if target session already has learning objectives
    $checkQuery = "SELECT COUNT(*) as count FROM learning_objective_allocation 
                   WHERE Course_ID = ? AND FYP_Session_ID = ?";
    $hasExistingData = false;
    if ($stmt = $conn->prepare($checkQuery)) {
        $stmt->bind_param("ii", $courseId, $targetFypSessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $hasExistingData = (int)$row['count'] > 0;
        }
        $stmt->close();
    }

    if ($hasExistingData) {
        echo json_encode([
            'success' => true,
            'message' => 'Learning objectives already exist for this session.',
            'copied' => 0
        ]);
        exit();
    }

    // Determine previous session (previous semester of same year, or semester 2 of previous year)
    $previousYear = $selectedYear;
    $previousSemester = (int)$selectedSemester - 1;
    
    if ($previousSemester < 1) {
        // Need to go to previous year, semester 2
        // Parse year string like "2024/2025" to get previous year
        if (preg_match('/(\d{4})\/(\d{4})/', $selectedYear, $matches)) {
            $startYear = (int)$matches[1];
            $endYear = (int)$matches[2];
            $previousYear = ($startYear - 1) . '/' . ($endYear - 1);
            $previousSemester = 2;
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid year format.']);
            exit();
        }
    }

    // Resolve FYP_Session_ID for the previous session (source session)
    $sourceFypSessionId = null;
    $sourceSessionQuery = "SELECT FYP_Session_ID FROM fyp_session WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ? LIMIT 1";
    if ($stmt = $conn->prepare($sourceSessionQuery)) {
        $stmt->bind_param("isi", $courseId, $previousYear, $previousSemester);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $sourceFypSessionId = (int)$row['FYP_Session_ID'];
        }
        $stmt->close();
    }

    if (!$sourceFypSessionId) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "No previous session found to copy from (looking for $previousYear Semester $previousSemester)."
        ]);
        exit();
    }

    // Fetch learning objectives from source session
    $sourceQuery = "SELECT Assessment_ID, Criteria_ID, LearningObjective_Code, Percentage
                    FROM learning_objective_allocation
                    WHERE Course_ID = ? AND FYP_Session_ID = ?";
    
    $sourceData = [];
    if ($stmt = $conn->prepare($sourceQuery)) {
        $stmt->bind_param("ii", $courseId, $sourceFypSessionId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $sourceData[] = [
                'assessment_id' => $row['Assessment_ID'],
                'criteria_id' => $row['Criteria_ID'],
                'learning_objective_code' => $row['LearningObjective_Code'],
                'percentage' => $row['Percentage']
            ];
        }
        $stmt->close();
    }

    if (empty($sourceData)) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "No learning objectives found in previous session ($previousYear Semester $previousSemester) to copy."
        ]);
        exit();
    }

    // Begin transaction
    $conn->begin_transaction();

    // Insert learning objectives into target session
    $insertQuery = "INSERT INTO learning_objective_allocation 
                    (Course_ID, Assessment_ID, Criteria_ID, LearningObjective_Code, Percentage, FYP_Session_ID) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    
    $insertedCount = 0;
    
    if ($stmt = $conn->prepare($insertQuery)) {
        foreach ($sourceData as $item) {
            $stmt->bind_param("iiisdi", 
                $courseId, 
                $item['assessment_id'], 
                $item['criteria_id'], 
                $item['learning_objective_code'], 
                $item['percentage'], 
                $targetFypSessionId
            );
            if ($stmt->execute()) {
                $insertedCount++;
            }
        }
        $stmt->close();
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Successfully copied $insertedCount learning objective allocation(s) from $previousYear Semester $previousSemester.",
        'copied' => $insertedCount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error copying learning objectives: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
