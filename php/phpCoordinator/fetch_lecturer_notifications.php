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
    // Get coordinator's department ID
    $deptStmt = $conn->prepare("SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1");
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

    // Get all FYP sessions for this department to get all assessments
    $sessionsQuery = "SELECT DISTINCT fs.FYP_Session_ID 
                     FROM fyp_session fs
                     INNER JOIN student s ON fs.FYP_Session_ID = s.FYP_Session_ID
                     WHERE s.Department_ID = ?";
    $sessionsStmt = $conn->prepare($sessionsQuery);
    if (!$sessionsStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $sessionsStmt->bind_param('i', $departmentId);
    $sessionsStmt->execute();
    $sessionsResult = $sessionsStmt->get_result();
    $fypSessionIds = [];
    while ($row = $sessionsResult->fetch_assoc()) {
        $fypSessionIds[] = $row['FYP_Session_ID'];
    }
    $sessionsStmt->close();

    if (empty($fypSessionIds)) {
        echo json_encode(['success' => true, 'notifications' => []]);
        exit();
    }

    // Get all courses in the department
    $coursesQuery = "SELECT Course_ID FROM course WHERE Department_ID = ?";
    $coursesStmt = $conn->prepare($coursesQuery);
    if (!$coursesStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $coursesStmt->bind_param('i', $departmentId);
    $coursesStmt->execute();
    $coursesResult = $coursesStmt->get_result();
    $courseIds = [];
    while ($row = $coursesResult->fetch_assoc()) {
        $courseIds[] = $row['Course_ID'];
    }
    $coursesStmt->close();

    if (empty($courseIds)) {
        echo json_encode(['success' => true, 'notifications' => []]);
        exit();
    }

    // Get all lecturers in the department with their Supervisor_ID and Assessor_ID mappings
    // Use subqueries to avoid GROUP_CONCAT issues with NULL
    $lecturersQuery = "SELECT DISTINCT l.Lecturer_ID, l.Lecturer_Name
                       FROM lecturer l
                       WHERE l.Department_ID = ?";
    $lecturersStmt = $conn->prepare($lecturersQuery);
    if (!$lecturersStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $lecturersStmt->bind_param('i', $departmentId);
    $lecturersStmt->execute();
    $lecturersResult = $lecturersStmt->get_result();
    $allLecturers = [];
    while ($row = $lecturersResult->fetch_assoc()) {
        $allLecturers[$row['Lecturer_ID']] = $row['Lecturer_Name'];
    }
    $lecturersStmt->close();
    
    // Create mappings from Supervisor_ID and Assessor_ID to Lecturer_ID
    $lecturerToSupervisorMap = [];
    $lecturerToAssessorMap = [];
    
    // Get Supervisor_ID to Lecturer_ID mapping
    if (!empty($allLecturers)) {
        $lecturerIds = array_keys($allLecturers);
        $placeholders = implode(',', array_fill(0, count($lecturerIds), '?'));
        $supervisorMapQuery = "SELECT Supervisor_ID, Lecturer_ID FROM supervisor WHERE Lecturer_ID IN ($placeholders)";
        $supervisorMapStmt = $conn->prepare($supervisorMapQuery);
        if ($supervisorMapStmt) {
            $types = str_repeat('s', count($lecturerIds));
            $supervisorMapStmt->bind_param($types, ...$lecturerIds);
            $supervisorMapStmt->execute();
            $supervisorMapResult = $supervisorMapStmt->get_result();
            while ($row = $supervisorMapResult->fetch_assoc()) {
                $lecturerToSupervisorMap[$row['Supervisor_ID']] = $row['Lecturer_ID'];
            }
            $supervisorMapStmt->close();
        }
        
        // Get Assessor_ID to Lecturer_ID mapping
        $assessorMapQuery = "SELECT Assessor_ID, Lecturer_ID FROM assessor WHERE Lecturer_ID IN ($placeholders)";
        $assessorMapStmt = $conn->prepare($assessorMapQuery);
        if ($assessorMapStmt) {
            $assessorMapStmt->bind_param($types, ...$lecturerIds);
            $assessorMapStmt->execute();
            $assessorMapResult = $assessorMapStmt->get_result();
            while ($row = $assessorMapResult->fetch_assoc()) {
                $lecturerToAssessorMap[$row['Assessor_ID']] = $row['Lecturer_ID'];
            }
            $assessorMapStmt->close();
        }
    }

    // Get assessments with their classification (Supervisor/Assessor) and criteria
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    $assessmentsQuery = "SELECT a.Assessment_ID, a.Assessment_Name, a.Course_ID,
                                COALESCE(ac_class.Role_Name, 'Supervisor') as Role_Name,
                                ac.Criteria_ID, ac.Criteria_Name
                         FROM assessment a
                         LEFT JOIN assessment_classification ac_class ON a.Assessment_ID = ac_class.Assessment_ID
                         LEFT JOIN assessment_criteria ac ON a.Assessment_ID = ac.Assessment_ID
                         WHERE a.Course_ID IN ($placeholders)
                         ORDER BY a.Assessment_ID, ac.Criteria_ID";
    
    $assessmentsStmt = $conn->prepare($assessmentsQuery);
    if (!$assessmentsStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $types = str_repeat('i', count($courseIds));
    $assessmentsStmt->bind_param($types, ...$courseIds);
    $assessmentsStmt->execute();
    $assessmentsResult = $assessmentsStmt->get_result();
    
    // Organize assessments by role
    $assessmentsByRole = [];
    while ($row = $assessmentsResult->fetch_assoc()) {
        $assessmentId = $row['Assessment_ID'];
        $role = strtolower($row['Role_Name']) === 'assessor' ? 'Assessor' : 'Supervisor';
        
        if (!isset($assessmentsByRole[$role][$assessmentId])) {
            $assessmentsByRole[$role][$assessmentId] = [
                'assessment_id' => $assessmentId,
                'assessment_name' => $row['Assessment_Name'],
                'criteria' => []
            ];
        }
        
        if ($row['Criteria_ID']) {
            $assessmentsByRole[$role][$assessmentId]['criteria'][$row['Criteria_ID']] = [
                'criteria_id' => $row['Criteria_ID'],
                'criteria_name' => $row['Criteria_Name']
            ];
        }
    }
    $assessmentsStmt->close();
    
    // Get due dates for assessments by role and session
    $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
    $dueDatesQuery = "SELECT dd.Assessment_ID, dd.Role, dd.FYP_Session_ID, dd.End_Date, dd.End_Time
                      FROM due_date dd
                      WHERE dd.FYP_Session_ID IN ($placeholders)";
    $dueDatesStmt = $conn->prepare($dueDatesQuery);
    if (!$dueDatesStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $types = str_repeat('i', count($fypSessionIds));
    $dueDatesStmt->bind_param($types, ...$fypSessionIds);
    $dueDatesStmt->execute();
    $dueDatesResult = $dueDatesStmt->get_result();
    
    $dueDatesByRole = [];
    while ($row = $dueDatesResult->fetch_assoc()) {
        $assessmentId = $row['Assessment_ID'];
        $role = $row['Role'];
        $fypSessionId = $row['FYP_Session_ID'];
        
        if (!isset($dueDatesByRole[$role][$assessmentId][$fypSessionId])) {
            $dueDatesByRole[$role][$assessmentId][$fypSessionId] = [
                'end_date' => $row['End_Date'],
                'end_time' => $row['End_Time']
            ];
        }
    }
    $dueDatesStmt->close();

    // Get student enrollments to map lecturers to students
    $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
    $enrollmentQuery = "SELECT se.Student_ID, se.Supervisor_ID, se.Assessor_ID_1, se.Assessor_ID_2, se.Fyp_Session_ID
                        FROM student_enrollment se
                        WHERE se.Fyp_Session_ID IN ($placeholders)";
    $enrollmentStmt = $conn->prepare($enrollmentQuery);
    if (!$enrollmentStmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    $types = str_repeat('i', count($fypSessionIds));
    $enrollmentStmt->bind_param($types, ...$fypSessionIds);
    $enrollmentStmt->execute();
    $enrollmentResult = $enrollmentStmt->get_result();
    
    // Map lecturers to their students by role and session
    $lecturerStudents = [];
    while ($row = $enrollmentResult->fetch_assoc()) {
        $fypSessionId = $row['Fyp_Session_ID'];
        $studentId = $row['Student_ID'];
        
        // Supervisor - map Supervisor_ID to Lecturer_ID
        if ($row['Supervisor_ID'] && isset($lecturerToSupervisorMap[$row['Supervisor_ID']])) {
            $lecturerId = $lecturerToSupervisorMap[$row['Supervisor_ID']];
            if (!isset($lecturerStudents[$lecturerId]['Supervisor'][$fypSessionId])) {
                $lecturerStudents[$lecturerId]['Supervisor'][$fypSessionId] = [];
            }
            $lecturerStudents[$lecturerId]['Supervisor'][$fypSessionId][] = $studentId;
        }
        
        // Assessor 1 - map Assessor_ID to Lecturer_ID
        if ($row['Assessor_ID_1'] && isset($lecturerToAssessorMap[$row['Assessor_ID_1']])) {
            $lecturerId = $lecturerToAssessorMap[$row['Assessor_ID_1']];
            if (!isset($lecturerStudents[$lecturerId]['Assessor'][$fypSessionId])) {
                $lecturerStudents[$lecturerId]['Assessor'][$fypSessionId] = [];
            }
            $lecturerStudents[$lecturerId]['Assessor'][$fypSessionId][] = $studentId;
        }
        
        // Assessor 2 - map Assessor_ID to Lecturer_ID
        if ($row['Assessor_ID_2'] && isset($lecturerToAssessorMap[$row['Assessor_ID_2']])) {
            $lecturerId = $lecturerToAssessorMap[$row['Assessor_ID_2']];
            if (!isset($lecturerStudents[$lecturerId]['Assessor'][$fypSessionId])) {
                $lecturerStudents[$lecturerId]['Assessor'][$fypSessionId] = [];
            }
            $lecturerStudents[$lecturerId]['Assessor'][$fypSessionId][] = $studentId;
        }
    }
    $enrollmentStmt->close();

    // Get evaluation status for all students, mapped to Lecturer_ID
    $allStudentIds = [];
    foreach ($lecturerStudents as $studentsByRole) {
        foreach ($studentsByRole as $roleStudents) {
            foreach ($roleStudents as $sessionStudents) {
                $allStudentIds = array_merge($allStudentIds, $sessionStudents);
            }
        }
    }
    $allStudentIds = array_unique($allStudentIds);
    
    $evaluationStatus = [];
    if (!empty($allStudentIds)) {
        $placeholders = implode(',', array_fill(0, count($allStudentIds), '?'));
        $evalQuery = "SELECT Student_ID, Assessment_ID, Criteria_ID, Supervisor_ID, Assessor_ID
                      FROM evaluation
                      WHERE Student_ID IN ($placeholders)";
        $evalStmt = $conn->prepare($evalQuery);
        if ($evalStmt) {
            $types = str_repeat('s', count($allStudentIds));
            $evalStmt->bind_param($types, ...$allStudentIds);
            $evalStmt->execute();
            $evalResult = $evalStmt->get_result();
            while ($row = $evalResult->fetch_assoc()) {
                $studentId = $row['Student_ID'];
                $assessmentId = $row['Assessment_ID'];
                $criteriaId = $row['Criteria_ID'] ?? 'NULL';
                
                // Map Supervisor_ID to Lecturer_ID
                if ($row['Supervisor_ID'] && isset($lecturerToSupervisorMap[$row['Supervisor_ID']])) {
                    $lecturerId = $lecturerToSupervisorMap[$row['Supervisor_ID']];
                    if (!isset($evaluationStatus['Supervisor'][$lecturerId][$studentId])) {
                        $evaluationStatus['Supervisor'][$lecturerId][$studentId] = [];
                    }
                    if (!isset($evaluationStatus['Supervisor'][$lecturerId][$studentId][$assessmentId])) {
                        $evaluationStatus['Supervisor'][$lecturerId][$studentId][$assessmentId] = [];
                    }
                    $evaluationStatus['Supervisor'][$lecturerId][$studentId][$assessmentId][$criteriaId] = true;
                }
                
                // Map Assessor_ID to Lecturer_ID
                if ($row['Assessor_ID'] && isset($lecturerToAssessorMap[$row['Assessor_ID']])) {
                    $lecturerId = $lecturerToAssessorMap[$row['Assessor_ID']];
                    if (!isset($evaluationStatus['Assessor'][$lecturerId][$studentId])) {
                        $evaluationStatus['Assessor'][$lecturerId][$studentId] = [];
                    }
                    if (!isset($evaluationStatus['Assessor'][$lecturerId][$studentId][$assessmentId])) {
                        $evaluationStatus['Assessor'][$lecturerId][$studentId][$assessmentId] = [];
                    }
                    $evaluationStatus['Assessor'][$lecturerId][$studentId][$assessmentId][$criteriaId] = true;
                }
            }
            $evalStmt->close();
        }
    }

    // Build notifications for lecturers with incomplete tasks
    $notifications = [];
    $currentTime = time();
    
    foreach ($allLecturers as $lecturerId => $lecturerName) {
        if (!isset($lecturerStudents[$lecturerId])) {
            continue; // Lecturer has no assigned students
        }
        
        $incompleteTasks = [];
        $seenTasks = []; // Track unique tasks by role_assessmentId to avoid duplicates
        
        // Check Supervisor role - iterate through assessments first to check all students across all sessions
        if (isset($lecturerStudents[$lecturerId]['Supervisor']) && isset($assessmentsByRole['Supervisor'])) {
            // Get all students for this lecturer across all sessions
            $allSupervisorStudents = [];
            foreach ($lecturerStudents[$lecturerId]['Supervisor'] as $fypSessionId => $studentIds) {
                $allSupervisorStudents = array_merge($allSupervisorStudents, $studentIds);
            }
            $allSupervisorStudents = array_unique($allSupervisorStudents);
            
            // Check each assessment once
            foreach ($assessmentsByRole['Supervisor'] as $assessmentId => $assessmentInfo) {
                $taskKey = 'Supervisor_' . $assessmentId;
                
                // Skip if already added
                if (isset($seenTasks[$taskKey])) {
                    continue;
                }
                
                // Check if assessment is incomplete - need all criteria evaluated for all students
                $isIncomplete = false;
                $criteria = $assessmentInfo['criteria'];
                
                if (empty($criteria)) continue; // Skip if no criteria
                
                // Check all students across all sessions
                foreach ($allSupervisorStudents as $studentId) {
                    foreach ($criteria as $criteriaId => $criteriaInfo) {
                        if (!isset($evaluationStatus['Supervisor'][$lecturerId][$studentId][$assessmentId][$criteriaId])) {
                            $isIncomplete = true;
                            break 2;
                        }
                    }
                }
                
                if ($isIncomplete) {
                    $seenTasks[$taskKey] = true;
                    // Get due date (use first available session's due date)
                    $endDate = null;
                    $endTime = null;
                    foreach ($lecturerStudents[$lecturerId]['Supervisor'] as $fypSessionId => $studentIds) {
                        if (isset($dueDatesByRole['Supervisor'][$assessmentId][$fypSessionId])) {
                            $endDate = $dueDatesByRole['Supervisor'][$assessmentId][$fypSessionId]['end_date'];
                            $endTime = $dueDatesByRole['Supervisor'][$assessmentId][$fypSessionId]['end_time'];
                            break;
                        }
                    }
                    
                    $incompleteTasks[] = [
                        'assessment_id' => $assessmentId,
                        'assessment_name' => $assessmentInfo['assessment_name'],
                        'role' => 'Supervisor',
                        'status' => 'Incomplete',
                        'end_date' => $endDate,
                        'end_time' => $endTime,
                        'remaining_days' => null,
                        'fyp_session_id' => null
                    ];
                }
            }
        }
        
        // Check Assessor role - iterate through assessments first to check all students across all sessions
        if (isset($lecturerStudents[$lecturerId]['Assessor']) && isset($assessmentsByRole['Assessor'])) {
            // Get all students for this lecturer across all sessions
            $allAssessorStudents = [];
            foreach ($lecturerStudents[$lecturerId]['Assessor'] as $fypSessionId => $studentIds) {
                $allAssessorStudents = array_merge($allAssessorStudents, $studentIds);
            }
            $allAssessorStudents = array_unique($allAssessorStudents);
            
            // Check each assessment once
            foreach ($assessmentsByRole['Assessor'] as $assessmentId => $assessmentInfo) {
                $taskKey = 'Assessor_' . $assessmentId;
                
                // Skip if already added
                if (isset($seenTasks[$taskKey])) {
                    continue;
                }
                
                // Check if assessment is incomplete
                $isIncomplete = false;
                $criteria = $assessmentInfo['criteria'];
                
                if (empty($criteria)) continue; // Skip if no criteria
                
                // Check all students across all sessions
                foreach ($allAssessorStudents as $studentId) {
                    foreach ($criteria as $criteriaId => $criteriaInfo) {
                        if (!isset($evaluationStatus['Assessor'][$lecturerId][$studentId][$assessmentId][$criteriaId])) {
                            $isIncomplete = true;
                            break 2;
                        }
                    }
                }
                
                if ($isIncomplete) {
                    $seenTasks[$taskKey] = true;
                    // Get due date (use first available session's due date)
                    $endDate = null;
                    $endTime = null;
                    foreach ($lecturerStudents[$lecturerId]['Assessor'] as $fypSessionId => $studentIds) {
                        if (isset($dueDatesByRole['Assessor'][$assessmentId][$fypSessionId])) {
                            $endDate = $dueDatesByRole['Assessor'][$assessmentId][$fypSessionId]['end_date'];
                            $endTime = $dueDatesByRole['Assessor'][$assessmentId][$fypSessionId]['end_time'];
                            break;
                        }
                    }
                    
                    $incompleteTasks[] = [
                        'assessment_id' => $assessmentId,
                        'assessment_name' => $assessmentInfo['assessment_name'],
                        'role' => 'Assessor',
                        'status' => 'Incomplete',
                        'end_date' => $endDate,
                        'end_time' => $endTime,
                        'remaining_days' => null,
                        'fyp_session_id' => null
                    ];
                }
            }
        }
        
        // Only add notification if lecturer has incomplete tasks
        if (!empty($incompleteTasks)) {
            // Calculate sort_date - use current time so new changes appear at top
            $sortDate = date('Y-m-d H:i:s', $currentTime);
            
            $notifications[] = [
                'lecturer_id' => $lecturerId,
                'lecturer_name' => $lecturerName,
                'tasks' => $incompleteTasks,
                'sort_date' => $sortDate
            ];
        }
    }
    
    // Sort notifications by sort_date (most recent first)
    usort($notifications, function($a, $b) {
        return strtotime($b['sort_date']) - strtotime($a['sort_date']);
    });

    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->error ?? $e->getMessage()
    ]);
}

$conn->close();
?>
