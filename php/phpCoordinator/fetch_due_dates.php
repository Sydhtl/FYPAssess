<?php
include __DIR__ . '/../mysqlConnect.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$year = isset($_GET['year']) ? $_GET['year'] : '';
$semester = isset($_GET['semester']) ? intval($_GET['semester']) : 0;
$userId = $_SESSION['upmId'];

try {
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

    // Get FYP_Session_IDs for the selected year and semester
    $fypSessionIds = [];
    if (!empty($year) && !empty($semester)) {
        $fypSessionQuery = "SELECT DISTINCT fs.FYP_Session_ID 
                            FROM fyp_session fs
                            INNER JOIN course c ON fs.Course_ID = c.Course_ID
                            INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
                            WHERE l.Lecturer_ID = ? 
                            AND fs.FYP_Session = ? 
                            AND fs.Semester = ?";
        $fypStmt = $conn->prepare($fypSessionQuery);
        if ($fypStmt) {
            $fypStmt->bind_param("ssi", $userId, $year, $semester);
            $fypStmt->execute();
            $fypResult = $fypStmt->get_result();
            while ($row = $fypResult->fetch_assoc()) {
                $fypSessionIds[] = $row['FYP_Session_ID'];
            }
            $fypStmt->close();
        }
    }

    // Get courses for this department
    $coursesQuery = "SELECT Course_ID, Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code";
    $coursesStmt = $conn->prepare($coursesQuery);
    if (!$coursesStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $coursesStmt->bind_param('i', $departmentId);
    $coursesStmt->execute();
    $coursesResult = $coursesStmt->get_result();
    
    $allocations = [];
    
    while ($courseRow = $coursesResult->fetch_assoc()) {
        $courseId = $courseRow['Course_ID'];
        $courseCode = $courseRow['Course_Code'];
        
        // Get assessments for this course
        $assessmentsQuery = "SELECT a.Assessment_ID, a.Assessment_Name 
                            FROM assessment a 
                            WHERE a.Course_ID = ? 
                            ORDER BY a.Assessment_Name";
        $assessmentsStmt = $conn->prepare($assessmentsQuery);
        if ($assessmentsStmt) {
            $assessmentsStmt->bind_param('i', $courseId);
            $assessmentsStmt->execute();
            $assessmentsResult = $assessmentsStmt->get_result();
            
            while ($assessmentRow = $assessmentsResult->fetch_assoc()) {
                $assessmentId = $assessmentRow['Assessment_ID'];
                $assessmentName = $assessmentRow['Assessment_Name'];
                
                $dueDates = [];
                
                // Query due_date by Assessment_ID and FYP_Session_ID
                if (!empty($fypSessionIds)) {
                    $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
                    $dueDateQuery = "SELECT Due_ID, Start_Date, End_Date, Start_Time, End_Time, Role 
                                     FROM due_date 
                                     WHERE Assessment_ID = ? 
                                     AND FYP_Session_ID IN ($placeholders)";
                    $dueDateStmt = $conn->prepare($dueDateQuery);
                    if ($dueDateStmt) {
                        $types = 'i' . str_repeat('i', count($fypSessionIds));
                        $params = array_merge([$assessmentId], $fypSessionIds);
                        $dueDateStmt->bind_param($types, ...$params);
                        $dueDateStmt->execute();
                        $dueDateResult = $dueDateStmt->get_result();
                        
                        while ($dueDateRow = $dueDateResult->fetch_assoc()) {
                            $dueDates[] = [
                                'due_id' => $dueDateRow['Due_ID'],
                                'start_date' => $dueDateRow['Start_Date'],
                                'end_date' => $dueDateRow['End_Date'],
                                'start_time' => $dueDateRow['Start_Time'],
                                'end_time' => $dueDateRow['End_Time'],
                                'role' => $dueDateRow['Role']
                            ];
                        }
                        $dueDateStmt->close();
                    }
                }
                
                // Only include assessments that have due dates, or include all if no filters
                // But we'll include all assessments and let the frontend handle empty state
                $allocations[] = [
                    'course_id' => $courseId,
                    'course_code' => $courseCode,
                    'assessment_id' => $assessmentId,
                    'assessment_name' => $assessmentName,
                    'due_dates' => $dueDates
                ];
            }
            $assessmentsStmt->close();
        }
    }
    $coursesStmt->close();

    echo json_encode([
        'success' => true,
        'allocations' => $allocations,
        'fyp_session_ids' => $fypSessionIds
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>
