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
    $courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
    $fypSession = isset($_GET['year']) ? $_GET['year'] : null;
    $semester = isset($_GET['semester']) ? (int)$_GET['semester'] : null;

    if (!$courseId || !$fypSession || !$semester) {
        throw new Exception('Missing required parameters: course_id, year, and semester are required');
    }

    // Resolve coordinator department
    $deptStmt = $conn->prepare("SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1");
    if (!$deptStmt) throw new Exception('Prepare failed: ' . $conn->error);
    $deptStmt->bind_param('s', $userId);
    $deptStmt->execute();
    $deptRes = $deptStmt->get_result();
    $deptRow = $deptRes->fetch_assoc();
    $deptStmt->close();
    if (!$deptRow) throw new Exception('Coordinator not found');
    $departmentId = $deptRow['Department_ID'];

    // Get FYP_Session_ID
    $sessionStmt = $conn->prepare("SELECT FYP_Session_ID FROM fyp_session WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ? LIMIT 1");
    if (!$sessionStmt) throw new Exception('Prepare failed: ' . $conn->error);
    $sessionStmt->bind_param('isi', $courseId, $fypSession, $semester);
    $sessionStmt->execute();
    $sessionRes = $sessionStmt->get_result();
    $sessionRow = $sessionRes->fetch_assoc();
    $sessionStmt->close();
    if (!$sessionRow) {
        echo json_encode(['success' => true, 'assessments' => [], 'lecturers' => []]);
        $conn->close();
        exit();
    }
    $fypSessionId = $sessionRow['FYP_Session_ID'];

    // Criteria per assessment (with names) - must be fetched first
    $criteriaByAssessment = [];
    $critStmt = $conn->prepare("SELECT ac.Criteria_ID, ac.Assessment_ID, ac.Criteria_Name 
                                FROM assessment_criteria ac 
                                WHERE ac.Assessment_ID IN (SELECT Assessment_ID FROM assessment WHERE Course_ID = ?)");
    if ($critStmt) {
        $critStmt->bind_param('i', $courseId);
        $critStmt->execute();
        $critRes = $critStmt->get_result();
        while ($row = $critRes->fetch_assoc()) {
            if (!isset($criteriaByAssessment[$row['Assessment_ID']])) {
                $criteriaByAssessment[$row['Assessment_ID']] = [];
            }
            $criteriaByAssessment[$row['Assessment_ID']][] = [
                'criteria_id' => $row['Criteria_ID'],
                'criteria_name' => $row['Criteria_Name']
            ];
        }
        $critStmt->close();
    }

    // Assessments with role classification (default Supervisor) and criteria
    $assessments = ['Supervisor' => [], 'Assessor' => []];
    $assStmt = $conn->prepare("SELECT a.Assessment_ID, a.Assessment_Name, COALESCE(ac.Role_Name,'Supervisor') AS Role_Name
                               FROM assessment a
                               LEFT JOIN assessment_classification ac ON a.Assessment_ID = ac.Assessment_ID
                               WHERE a.Course_ID = ?
                               ORDER BY a.Assessment_ID");
    if ($assStmt) {
        $assStmt->bind_param('i', $courseId);
        $assStmt->execute();
        $assRes = $assStmt->get_result();
        while ($row = $assRes->fetch_assoc()) {
            $role = strtolower($row['Role_Name']) === 'assessor' ? 'Assessor' : 'Supervisor';
            $assessmentId = $row['Assessment_ID'];
            // Get criteria for this assessment
            $criteriaList = $criteriaByAssessment[$assessmentId] ?? [];
            $assessments[$role][] = [
                'assessment_id' => $assessmentId,
                'assessment_name' => $row['Assessment_Name'],
                'criteria' => $criteriaList
            ];
        }
        $assStmt->close();
    }

    // Supervisors with students in session
    $supervisors = [];
    $supStmt = $conn->prepare("SELECT DISTINCT s.Supervisor_ID, l.Lecturer_Name
                               FROM student_enrollment se
                               INNER JOIN supervisor s ON se.Supervisor_ID = s.Supervisor_ID
                               INNER JOIN lecturer l ON s.Lecturer_ID = l.Lecturer_ID
                               INNER JOIN student st ON se.Student_ID = st.Student_ID
                               WHERE se.Fyp_Session_ID = ? AND st.Course_ID = ?");
    if ($supStmt) {
        $supStmt->bind_param('ii', $fypSessionId, $courseId);
        $supStmt->execute();
        $supRes = $supStmt->get_result();
        while ($row = $supRes->fetch_assoc()) {
            $supervisors[$row['Supervisor_ID']] = $row['Lecturer_Name'];
        }
        $supStmt->close();
    }

    // Assessors with students in session (both slots)
    $assessors = [];
    $assrStmt = $conn->prepare("SELECT DISTINCT a.Assessor_ID, l.Lecturer_Name
                                FROM student_enrollment se
                                INNER JOIN assessor a ON (se.Assessor_ID_1 = a.Assessor_ID OR se.Assessor_ID_2 = a.Assessor_ID)
                                INNER JOIN lecturer l ON a.Lecturer_ID = l.Lecturer_ID
                                INNER JOIN student st ON se.Student_ID = st.Student_ID
                                WHERE se.Fyp_Session_ID = ? AND st.Course_ID = ?");
    if ($assrStmt) {
        $assrStmt->bind_param('ii', $fypSessionId, $courseId);
        $assrStmt->execute();
        $assrRes = $assrStmt->get_result();
        while ($row = $assrRes->fetch_assoc()) {
            $assessors[$row['Assessor_ID']] = $row['Lecturer_Name'];
        }
        $assrStmt->close();
    }

    // Map students per supervisor/assessor
    $studentsBySupervisor = [];
    $studentsByAssessor = [];
    $enrollStmt = $conn->prepare("SELECT se.Student_ID, se.Supervisor_ID, se.Assessor_ID_1, se.Assessor_ID_2
                                  FROM student_enrollment se
                                  INNER JOIN student st ON se.Student_ID = st.Student_ID
                                  WHERE se.Fyp_Session_ID = ? AND st.Course_ID = ?");
    if ($enrollStmt) {
        $enrollStmt->bind_param('ii', $fypSessionId, $courseId);
        $enrollStmt->execute();
        $enRes = $enrollStmt->get_result();
        while ($row = $enRes->fetch_assoc()) {
            if ($row['Supervisor_ID']) {
                $studentsBySupervisor[$row['Supervisor_ID']][] = $row['Student_ID'];
            }
            if ($row['Assessor_ID_1']) {
                $studentsByAssessor[$row['Assessor_ID_1']][] = $row['Student_ID'];
            }
            if ($row['Assessor_ID_2']) {
                $studentsByAssessor[$row['Assessor_ID_2']][] = $row['Student_ID'];
            }
        }
        $enrollStmt->close();
    }

    // Gather evaluations for all students in session/course
    $allStudentIds = array_unique(array_merge(
        array_merge(...array_values($studentsBySupervisor ?: [[]])),
        array_merge(...array_values($studentsByAssessor ?: [[]]))
    ));
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
            $evalRes = $evalStmt->get_result();
            while ($row = $evalRes->fetch_assoc()) {
                $studentId = $row['Student_ID'];
                $assessmentId = $row['Assessment_ID'];
                $criteriaId = $row['Criteria_ID'] ?? null;
                $supId = $row['Supervisor_ID'] ?? null;
                $assId = $row['Assessor_ID'] ?? null;
                if ($supId) {
                    $evaluationStatus['Supervisor'][$supId][$studentId][$assessmentId][$criteriaId ?? 'NULL'] = true;
                }
                if ($assId) {
                    $evaluationStatus['Assessor'][$assId][$studentId][$assessmentId][$criteriaId ?? 'NULL'] = true;
                }
            }
            $evalStmt->close();
        }
    }

    // Build lecturer rows combining supervisor/assessor roles
    $lecturers = [];
    $lecturerIndex = [];
    foreach ($supervisors as $supId => $name) {
        $lecturerIndex[$name] = count($lecturers);
        $lecturers[] = [
            'name' => $name,
            'supervisor_id' => $supId,
            'assessor_ids' => [],
            'status' => ['Supervisor' => [], 'Assessor' => []]
        ];
    }
    foreach ($assessors as $assId => $name) {
        if (isset($lecturerIndex[$name])) {
            $lecturers[$lecturerIndex[$name]]['assessor_ids'][] = $assId;
        } else {
            $lecturerIndex[$name] = count($lecturers);
            $lecturers[] = [
                'name' => $name,
                'supervisor_id' => null,
                'assessor_ids' => [$assId],
                'status' => ['Supervisor' => [], 'Assessor' => []]
            ];
        }
    }

    // Helper to check completion
    $isComplete = function($role, $roleId, $assessmentId) use ($evaluationStatus, $criteriaByAssessment, $studentsBySupervisor, $studentsByAssessor) {
        $criteriaList = $criteriaByAssessment[$assessmentId] ?? [];
        if (empty($criteriaList)) return false;
        $students = $role === 'Supervisor' ? ($studentsBySupervisor[$roleId] ?? []) : ($studentsByAssessor[$roleId] ?? []);
        if (empty($students)) return false;
        foreach ($students as $sid) {
            foreach ($criteriaList as $crit) {
                $cid = is_array($crit) ? $crit['criteria_id'] : $crit;
                $cidKey = $cid ?? 'NULL';
                if (empty($evaluationStatus[$role][$roleId][$sid][$assessmentId][$cidKey])) {
                    return false;
                }
            }
        }
        return true;
    };

    // Fill status matrix
    foreach ($lecturers as &$lec) {
        // Supervisor role
        foreach ($assessments['Supervisor'] as $ass) {
            if ($lec['supervisor_id']) {
                $lec['status']['Supervisor'][$ass['assessment_id']] = $isComplete('Supervisor', $lec['supervisor_id'], $ass['assessment_id']) ? 'Completed' : 'Incomplete';
            } else {
                $lec['status']['Supervisor'][$ass['assessment_id']] = 'N/A';
            }
        }
        // Assessor role
        foreach ($assessments['Assessor'] as $ass) {
            if (!empty($lec['assessor_ids'])) {
                // If multiple assessor ids, mark complete only if all assessor ids complete
                $allComplete = true;
                foreach ($lec['assessor_ids'] as $aid) {
                    if (!$isComplete('Assessor', $aid, $ass['assessment_id'])) {
                        $allComplete = false;
                        break;
                    }
                }
                $lec['status']['Assessor'][$ass['assessment_id']] = $allComplete ? 'Completed' : 'Incomplete';
            } else {
                $lec['status']['Assessor'][$ass['assessment_id']] = 'N/A';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'assessments' => $assessments,
        'lecturers' => $lecturers
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
$conn->close();
?>
