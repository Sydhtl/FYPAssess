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
            'students' => []
        ]);
        $conn->close();
        exit();
    }
    
    $fypSessionId = $sessionRow['FYP_Session_ID'];
    $sessionStmt->close();

    // Fetch students with their project titles
    // Filter by: Course_ID, FYP_Session, Semester, and Department_ID
    $studentsQuery = "SELECT 
                        s.Student_ID,
                        s.Student_Name,
                        COALESCE(fp.Project_Title, fp.Proposed_Title, 'N/A') AS Project_Title
                      FROM student s
                      INNER JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
                      LEFT JOIN fyp_project fp ON s.Student_ID = fp.Student_ID
                      WHERE fs.Course_ID = ?
                        AND fs.FYP_Session = ?
                        AND fs.Semester = ?
                        AND s.Department_ID = ?
                      ORDER BY s.Student_Name";

    $studentsStmt = $conn->prepare($studentsQuery);
    if (!$studentsStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $studentsStmt->bind_param('isii', $courseId, $fypSession, $semester, $departmentId);
    $studentsStmt->execute();
    $studentsResult = $studentsStmt->get_result();
    
    $students = [];
    $studentIds = [];
    while ($row = $studentsResult->fetch_assoc()) {
        $studentIds[] = $row['Student_ID'];
        $students[] = [
            'student_id' => $row['Student_ID'],
            'name' => $row['Student_Name'],
            'project_title' => $row['Project_Title'] ?? 'N/A'
        ];
    }
    $studentsStmt->close();

    // Fetch evaluations for these students and match with learning objective allocations
    if (!empty($studentIds)) {
        // Get all learning objective allocations for this course and session
        $loAllocationsQuery = "SELECT 
                                  loa.LO_Allocation_ID,
                                  loa.Assessment_ID,
                                  loa.Criteria_ID,
                                  loa.LearningObjective_Code
                               FROM learning_objective_allocation loa
                               WHERE loa.Course_ID = ?
                                 AND loa.FYP_Session_ID = ?";
        $loStmt = $conn->prepare($loAllocationsQuery);
        if ($loStmt) {
            $loStmt->bind_param('ii', $courseId, $fypSessionId);
            $loStmt->execute();
            $loResult = $loStmt->get_result();
            
            // Store LO allocations in an array for direct matching
            $loAllocations = [];
            while ($loRow = $loResult->fetch_assoc()) {
                $loAllocations[] = [
                    'assessment_id' => $loRow['Assessment_ID'],
                    'criteria_id' => $loRow['Criteria_ID'],
                    'learning_objective_code' => $loRow['LearningObjective_Code'],
                    'lo_allocation_id' => $loRow['LO_Allocation_ID']
                ];
            }
            $loStmt->close();

            // Get assessment classifications to identify assessor tasks
            $assessmentClassifications = [];
            $classQuery = "SELECT Assessment_ID, Role_Name 
                          FROM assessment_classification 
                          WHERE Assessment_ID IN (
                              SELECT DISTINCT Assessment_ID 
                              FROM learning_objective_allocation 
                              WHERE Course_ID = ? AND FYP_Session_ID = ?
                          )";
            $classStmt = $conn->prepare($classQuery);
            if ($classStmt) {
                $classStmt->bind_param('ii', $courseId, $fypSessionId);
                $classStmt->execute();
                $classResult = $classStmt->get_result();
                while ($classRow = $classResult->fetch_assoc()) {
                    $assessmentClassifications[$classRow['Assessment_ID']] = $classRow['Role_Name'];
                }
                $classStmt->close();
            }

            // Fetch evaluations for all students, including Assessor_ID to filter assessor evaluations
            $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
            $evaluationsQuery = "SELECT 
                                    e.Student_ID,
                                    e.Assessment_ID,
                                    e.Criteria_ID,
                                    e.Evaluation_Percentage,
                                    e.Assessor_ID,
                                    e.Supervisor_ID
                                 FROM evaluation e
                                 WHERE e.Student_ID IN ($placeholders)";
            
            $evalStmt = $conn->prepare($evaluationsQuery);
            if ($evalStmt) {
                $types = str_repeat('s', count($studentIds));
                $evalStmt->bind_param($types, ...$studentIds);
                $evalStmt->execute();
                $evalResult = $evalStmt->get_result();
                
                // Build evaluation map: (Student_ID, Assessment_ID, Criteria_ID) -> Evaluation_Percentage
                // For assessor tasks: collect all percentages and average them
                // For supervisor tasks: take the first evaluation found (current behavior)
                $evalMap = [];
                $assessorEvalMap = []; // Temporary storage for assessor evaluations to average
                
                while ($evalRow = $evalResult->fetch_assoc()) {
                    $studentId = $evalRow['Student_ID'];
                    $assessmentId = $evalRow['Assessment_ID'];
                    $criteriaId = $evalRow['Criteria_ID'] ?? null;
                    $evalPercentage = $evalRow['Evaluation_Percentage'];
                    $assessorId = $evalRow['Assessor_ID'];
                    $supervisorId = $evalRow['Supervisor_ID'];
                    
                    // Handle NULL Criteria_ID
                    $criteriaKey = $criteriaId ?? 'NULL';
                    $evalKey = $studentId . '_' . $assessmentId . '_' . $criteriaKey;
                    
                    // Check if this assessment is an assessor task
                    // If assessment is not in classification table, default to supervisor task
                    $isAssessorTask = isset($assessmentClassifications[$assessmentId]) && 
                                     strtolower($assessmentClassifications[$assessmentId]) === 'assessor';
                    
                    if ($isAssessorTask && $assessorId) {
                        // For assessor tasks: collect all assessor evaluations to average later
                        // Multiple assessors can evaluate the same student for the same criteria
                        if (!isset($assessorEvalMap[$evalKey])) {
                            $assessorEvalMap[$evalKey] = [];
                        }
                        $assessorEvalMap[$evalKey][] = floatval($evalPercentage);
                    } else if (!$isAssessorTask && $supervisorId) {
                        // For supervisor tasks: take the first evaluation found (current behavior)
                        // Only one supervisor evaluates each student per criteria
                        if (!isset($evalMap[$evalKey])) {
                            $evalMap[$evalKey] = floatval($evalPercentage);
                        }
                    }
                    // Note: Evaluations that don't match the assessment classification are skipped
                    // (e.g., assessor task with Supervisor_ID only, or supervisor task with Assessor_ID only)
                }
                
                // Calculate averages for assessor tasks
                foreach ($assessorEvalMap as $evalKey => $percentages) {
                    if (!empty($percentages)) {
                        $average = array_sum($percentages) / count($percentages);
                        $evalMap[$evalKey] = $average;
                    }
                }
                
                $evalStmt->close();

                // Attach evaluations to students by matching with LO allocations
                foreach ($students as &$student) {
                    $student['evaluations'] = [];
                    
                    // For each LO allocation, find matching evaluation
                    foreach ($loAllocations as $loAlloc) {
                        $assessmentId = $loAlloc['assessment_id'];
                        $criteriaId = $loAlloc['criteria_id'];
                        $loCode = $loAlloc['learning_objective_code'];
                        
                        // Match evaluation by Student_ID, Assessment_ID, and Criteria_ID
                        $criteriaKey = $criteriaId ?? 'NULL';
                        $evalKey = $student['student_id'] . '_' . $assessmentId . '_' . $criteriaKey;
                        
                        if (isset($evalMap[$evalKey])) {
                            // Use the evaluation_percentage directly (no averaging)
                            $evalPercentage = $evalMap[$evalKey];
                            
                            // Store in format: assessment_id_criteria_id_learning_objective_code
                            // This ensures columns with same LO code but different criteria get different marks
                            $criteriaIdStr = $criteriaId ?? 'NULL';
                            $studentKey = $assessmentId . '_' . $criteriaIdStr . '_' . $loCode;
                            $student['evaluations'][$studentKey] = round($evalPercentage, 2);
                        }
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>
