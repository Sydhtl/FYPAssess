<?php
header('Content-Type: application/json');
include '../coordinator_bootstrap.php';

// Default response
$response = [
    'success' => false,
    'currentYear' => null,
    'currentSemester' => null,
    'widgets' => [
        'firstCourseStudentCount' => 0,
        'secondCourseStudentCount' => 0,
        'totalLecturers' => 0,
        'firstCourseCode' => '',
        'secondCourseCode' => '',
        'baseCourseCode' => ''
    ],
    'titleChart' => [
        'labels' => ['Approved', 'Waiting for approval', 'Rejected'],
        'data' => [0, 0, 0]
    ],
    'courseCharts' => [],
];

$userId = $_SESSION['upmId'] ?? null;
if (!$userId) {
    echo json_encode($response);
    exit();
}

$departmentId = null;
// Department lookup
if ($stmt = $conn->prepare('SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1')) {
    $stmt->bind_param('s', $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $departmentId = (int)$row['Department_ID'];
        }
    }
    $stmt->close();
}

if ($departmentId === null) {
    echo json_encode($response);
    exit();
}

// Latest session for this department
if ($stmt = $conn->prepare('
    SELECT fs.FYP_Session, fs.Semester
    FROM fyp_session fs
    INNER JOIN course c ON fs.Course_ID = c.Course_ID
    WHERE c.Department_ID = ?
    ORDER BY fs.FYP_Session DESC, fs.Semester DESC
    LIMIT 1
')) {
    $stmt->bind_param('i', $departmentId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $response['currentYear'] = $row['FYP_Session'];
            $response['currentSemester'] = (int)$row['Semester'];
        }
    }
    $stmt->close();
}

$currentYear = $response['currentYear'] ?? null;
$currentSemester = $response['currentSemester'] ?? null;
if ($currentYear === null) {
    $currentYear = '2024/2025';
    $currentSemester = 2;
}

// Title status counts
$titleCounts = [
    'Approved' => 0,
    'Waiting For Approval' => 0,
    'Rejected' => 0
];
if ($stmt = $conn->prepare('
    SELECT fp.Title_Status, COUNT(*) AS cnt
    FROM fyp_project fp
    INNER JOIN student s ON fp.Student_ID = s.Student_ID
    INNER JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
    WHERE s.Department_ID = ?
      AND fs.FYP_Session = ?
      AND fs.Semester = ?
    GROUP BY fp.Title_Status
')) {
    $stmt->bind_param('isi', $departmentId, $currentYear, $currentSemester);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $status = trim($row['Title_Status']);
            $count = (int)$row['cnt'];
            if (strcasecmp($status, 'Approved') === 0) {
                $titleCounts['Approved'] = $count;
            } elseif (strcasecmp($status, 'Rejected') === 0) {
                $titleCounts['Rejected'] = $count;
            } else {
                $titleCounts['Waiting For Approval'] += $count;
            }
        }
    }
    $stmt->close();
}
$response['titleChart']['data'] = [
    $titleCounts['Approved'],
    $titleCounts['Waiting For Approval'],
    $titleCounts['Rejected']
];

// Courses under department
$courses = [];
if ($stmt = $conn->prepare('SELECT Course_ID, Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code')) {
    $stmt->bind_param('i', $departmentId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $courses[] = [
                'id' => (int)$row['Course_ID'],
                'code' => $row['Course_Code']
            ];
        }
    }
    $stmt->close();
}

$baseCourseCode = '';
if (!empty($courses)) {
    $baseCourseCode = preg_replace('/[-_ ]?[A-Za-z]$/', '', $courses[0]['code']);
}

$firstCourseStudentCount = 0;
$secondCourseStudentCount = 0;
$firstCourseCode = $courses[0]['code'] ?? '';
$secondCourseCode = $courses[1]['code'] ?? '';

// Helper to fetch session IDs for a course
$getSessionIds = function(int $courseId) use ($conn, $currentYear, $currentSemester): array {
    $ids = [];
    $stmt = $conn->prepare('SELECT FYP_Session_ID FROM fyp_session WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ?');
    if ($stmt) {
        $stmt->bind_param('isi', $courseId, $currentYear, $currentSemester);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $ids[] = (int)$row['FYP_Session_ID'];
            }
        }
        $stmt->close();
    }
    return $ids;
};

// Student counts for first/second courses
if (!empty($courses)) {
    $sessions = $getSessionIds($courses[0]['id']);
    if (!empty($sessions)) {
        $place = implode(',', array_fill(0, count($sessions), '?'));
        $types = 'i' . str_repeat('i', count($sessions));
        $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM student WHERE Course_ID = ? AND FYP_Session_ID IN ($place)");
        if ($stmt) {
            $params = array_merge([$courses[0]['id']], $sessions);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    $firstCourseStudentCount = (int)$row['cnt'];
                }
            }
            $stmt->close();
        }
    }
    if (count($courses) > 1) {
        $sessions = $getSessionIds($courses[1]['id']);
        if (!empty($sessions)) {
            $place = implode(',', array_fill(0, count($sessions), '?'));
            $types = 'i' . str_repeat('i', count($sessions));
            $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM student WHERE Course_ID = ? AND FYP_Session_ID IN ($place)");
            if ($stmt) {
                $params = array_merge([$courses[1]['id']], $sessions);
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($row = $res->fetch_assoc()) {
                        $secondCourseStudentCount = (int)$row['cnt'];
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Total lecturers
if ($stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM lecturer WHERE Department_ID = ?')) {
    $stmt->bind_param('i', $departmentId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $response['widgets']['totalLecturers'] = (int)$row['cnt'];
        }
    }
    $stmt->close();
}

$response['widgets']['firstCourseStudentCount'] = $firstCourseStudentCount;
$response['widgets']['secondCourseStudentCount'] = $secondCourseStudentCount;
$response['widgets']['firstCourseCode'] = $firstCourseCode;
$response['widgets']['secondCourseCode'] = $secondCourseCode;
$response['widgets']['baseCourseCode'] = $baseCourseCode;

// Build course charts (complete vs incomplete lecturers per assessment)
foreach ($courses as $course) {
    $sessionIds = $getSessionIds($course['id']);
    if (empty($sessionIds)) {
        continue;
    }

    // Students in sessions
    $students = [];
    $place = implode(',', array_fill(0, count($sessionIds), '?'));
    $types = str_repeat('i', count($sessionIds));
    $sql = "SELECT Student_ID FROM student WHERE Course_ID = {$course['id']} AND FYP_Session_ID IN ($place)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param($types, ...$sessionIds);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $students[] = $row['Student_ID'];
            }
        }
        $stmt->close();
    }
    $totalStudents = count($students);
    if ($totalStudents === 0) continue;

    // Assessments
    $assessments = [];
    if ($stmt = $conn->prepare('SELECT a.Assessment_ID, a.Assessment_Name, COALESCE(ac.Role_Name, "Supervisor") AS Role_Name FROM assessment a LEFT JOIN assessment_classification ac ON a.Assessment_ID = ac.Assessment_ID WHERE a.Course_ID = ? ORDER BY a.Assessment_Name')) {
        $stmt->bind_param('i', $course['id']);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $role = (strtolower($row['Role_Name']) === 'assessor') ? 'Assessor' : 'Supervisor';
                $assessments[] = [
                    'id' => (int)$row['Assessment_ID'],
                    'name' => $row['Assessment_Name'],
                    'role' => $role
                ];
            }
        }
        $stmt->close();
    }
    if (empty($assessments)) continue;

    $assessmentData = [];
    foreach ($assessments as $a) {
        $criteriaIds = [];
        if ($stmt = $conn->prepare('SELECT Criteria_ID FROM assessment_criteria WHERE Assessment_ID = ?')) {
            $stmt->bind_param('i', $a['id']);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $criteriaIds[] = (int)$row['Criteria_ID'];
                }
            }
            $stmt->close();
        }

        $lecturerStatus = [];
        if ($a['role'] === 'Supervisor') {
            // Supervisors
            $supStmt = $conn->prepare('SELECT DISTINCT se.Supervisor_ID, s.Lecturer_ID FROM student_enrollment se INNER JOIN supervisor s ON se.Supervisor_ID = s.Supervisor_ID WHERE se.Fyp_Session_ID IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ') AND se.Supervisor_ID IS NOT NULL');
            if ($supStmt) {
                $supStmt->bind_param(str_repeat('i', count($sessionIds)), ...$sessionIds);
                if ($supStmt->execute()) {
                    $supRes = $supStmt->get_result();
                    while ($supRow = $supRes->fetch_assoc()) {
                        $lecturerId = $supRow['Lecturer_ID'];
                        $supervisorId = $supRow['Supervisor_ID'];

                        // Students for this supervisor
                        $lecStuStmt = $conn->prepare('SELECT DISTINCT se.Student_ID FROM student_enrollment se WHERE se.Fyp_Session_ID IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ') AND se.Supervisor_ID = ?');
                        if ($lecStuStmt) {
                            $params = array_merge($sessionIds, [$supervisorId]);
                            $types = str_repeat('i', count($sessionIds)) . 'i';
                            $lecStuStmt->bind_param($types, ...$params);
                            $lecStu = [];
                            if ($lecStuStmt->execute()) {
                                $lecRes = $lecStuStmt->get_result();
                                while ($row = $lecRes->fetch_assoc()) {
                                    $lecStu[] = $row['Student_ID'];
                                }
                            }
                            $lecStuStmt->close();

                            $complete = false;
                            if (!empty($lecStu) && !empty($criteriaIds)) {
                                $stuPlace = implode(',', array_fill(0, count($lecStu), '?'));
                                $critPlace = implode(',', array_fill(0, count($criteriaIds), '?'));
                                $checkSql = "SELECT COUNT(DISTINCT CONCAT(e.Student_ID, '_', e.Criteria_ID)) AS completed_count FROM evaluation e WHERE e.Assessment_ID = ? AND e.Student_ID IN ($stuPlace) AND e.Criteria_ID IN ($critPlace) AND e.Supervisor_ID = ? AND e.Evaluation_Percentage IS NOT NULL";
                                if ($checkStmt = $conn->prepare($checkSql)) {
                                    $expected = count($lecStu) * count($criteriaIds);
                                    $types = 'i' . str_repeat('s', count($lecStu)) . str_repeat('i', count($criteriaIds)) . 'i';
                                    $params = array_merge([$a['id']], $lecStu, $criteriaIds, [$supervisorId]);
                                    if ($checkStmt->bind_param($types, ...$params) && $checkStmt->execute()) {
                                        $resCheck = $checkStmt->get_result();
                                        if ($r = $resCheck->fetch_assoc()) {
                                            $complete = ((int)$r['completed_count'] === $expected);
                                        }
                                    }
                                    $checkStmt->close();
                                }
                            }
                            $lecturerStatus[$lecturerId] = $complete ? 'complete' : 'incomplete';
                        }
                    }
                }
                $supStmt->close();
            }
        } else {
            // Assessors
            $assStmt = $conn->prepare('SELECT DISTINCT se.Assessor_ID_1 as Assessor_ID, a.Lecturer_ID FROM student_enrollment se INNER JOIN assessor a ON se.Assessor_ID_1 = a.Assessor_ID WHERE se.Fyp_Session_ID IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ') AND se.Assessor_ID_1 IS NOT NULL UNION SELECT DISTINCT se.Assessor_ID_2 as Assessor_ID, a.Lecturer_ID FROM student_enrollment se INNER JOIN assessor a ON se.Assessor_ID_2 = a.Assessor_ID WHERE se.Fyp_Session_ID IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ') AND se.Assessor_ID_2 IS NOT NULL');
            if ($assStmt) {
                $assStmt->bind_param(str_repeat('i', count($sessionIds) * 2), ...array_merge($sessionIds, $sessionIds));
                if ($assStmt->execute()) {
                    $assRes = $assStmt->get_result();
                    $lecturerAssessors = [];
                    while ($row = $assRes->fetch_assoc()) {
                        $lecturerId = $row['Lecturer_ID'];
                        $assId = $row['Assessor_ID'];
                        $lecturerAssessors[$lecturerId] = $lecturerAssessors[$lecturerId] ?? [];
                        if (!in_array($assId, $lecturerAssessors[$lecturerId])) {
                            $lecturerAssessors[$lecturerId][] = $assId;
                        }
                    }

                    foreach ($lecturerAssessors as $lecturerId => $assessorIds) {
                        $lecStuStmt = $conn->prepare('SELECT DISTINCT se.Student_ID FROM student_enrollment se WHERE se.Fyp_Session_ID IN (' . implode(',', array_fill(0, count($sessionIds), '?')) . ') AND (se.Assessor_ID_1 IN (' . implode(',', array_fill(0, count($assessorIds), '?')) . ') OR se.Assessor_ID_2 IN (' . implode(',', array_fill(0, count($assessorIds), '?')) . '))');
                        if ($lecStuStmt) {
                            $params = array_merge($sessionIds, $assessorIds, $assessorIds);
                            $types = str_repeat('i', count($sessionIds)) . str_repeat('i', count($assessorIds) * 2);
                            $lecStuStmt->bind_param($types, ...$params);
                            $lecStu = [];
                            if ($lecStuStmt->execute()) {
                                $lecRes = $lecStuStmt->get_result();
                                while ($row = $lecRes->fetch_assoc()) {
                                    $lecStu[] = $row['Student_ID'];
                                }
                            }
                            $lecStuStmt->close();

                            $complete = false;
                            if (!empty($lecStu) && !empty($criteriaIds)) {
                                $stuPlace = implode(',', array_fill(0, count($lecStu), '?'));
                                $critPlace = implode(',', array_fill(0, count($criteriaIds), '?'));
                                $assPlace = implode(',', array_fill(0, count($assessorIds), '?'));
                                $checkSql = "SELECT COUNT(DISTINCT CONCAT(e.Student_ID, '_', e.Criteria_ID)) AS completed_count FROM evaluation e WHERE e.Assessment_ID = ? AND e.Student_ID IN ($stuPlace) AND e.Criteria_ID IN ($critPlace) AND e.Assessor_ID IN ($assPlace) AND e.Evaluation_Percentage IS NOT NULL";
                                if ($checkStmt = $conn->prepare($checkSql)) {
                                    $expected = count($lecStu) * count($criteriaIds);
                                    $types = 'i' . str_repeat('s', count($lecStu)) . str_repeat('i', count($criteriaIds)) . str_repeat('i', count($assessorIds));
                                    $params = array_merge([$a['id']], $lecStu, $criteriaIds, $assessorIds);
                                    if ($checkStmt->bind_param($types, ...$params) && $checkStmt->execute()) {
                                        $resCheck = $checkStmt->get_result();
                                        if ($r = $resCheck->fetch_assoc()) {
                                            $complete = ((int)$r['completed_count'] === $expected);
                                        }
                                    }
                                    $checkStmt->close();
                                }
                            }
                            $lecturerStatus[$lecturerId] = $complete ? 'complete' : 'incomplete';
                        }
                    }
                }
                $assStmt->close();
            }
        }

        $completeCount = 0;
        $incompleteCount = 0;
        foreach ($lecturerStatus as $status) {
            if ($status === 'complete') {
                $completeCount++;
            } else {
                $incompleteCount++;
            }
        }

        $assessmentData[] = [
            'id' => $a['id'],
            'name' => $a['name'],
            'role' => $a['role'],
            'submitted' => $completeCount,
            'notSubmitted' => $incompleteCount,
            'total' => ($completeCount + $incompleteCount),
            'studentCount' => $totalStudents
        ];
    }

    $response['courseCharts'][] = [
        'course_code' => $course['code'],
        'assessments' => $assessmentData
    ];
}

$response['widgets']['baseCourseCode'] = $baseCourseCode;
$response['success'] = true;
$response['currentYear'] = $currentYear;
$response['currentSemester'] = $currentSemester;

// Normalize widget values
$response['widgets']['firstCourseStudentCount'] = $firstCourseStudentCount;
$response['widgets']['secondCourseStudentCount'] = $secondCourseStudentCount;

// Total lecturers already set

echo json_encode($response);
