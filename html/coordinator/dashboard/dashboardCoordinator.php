<?php
// Prevent caching to stop back button access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

include '../../../php/coordinator_bootstrap.php';
?>
<script>
// Prevent back button after logout
window.history.pushState(null, "", window.location.href);
window.onpopstate = function() {
    window.history.pushState(null, "", window.location.href);
};
</script>
<?php

// -------------------------
// Coordinator context & session
// -------------------------
$userId = $_SESSION['upmId'] ?? null;
$departmentId = null;
$currentYear = null;
$currentSemester = null;

if ($userId) {
    $deptStmt = $conn->prepare("SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1");
    if ($deptStmt) {
        $deptStmt->bind_param('s', $userId);
        if ($deptStmt->execute()) {
            $deptRes = $deptStmt->get_result();
            if ($deptRow = $deptRes->fetch_assoc()) {
                $departmentId = (int)$deptRow['Department_ID'];
            }
        }
        $deptStmt->close();
    }
}

// Get the latest session from database for this department
if ($departmentId !== null) {
    $latestSessionStmt = $conn->prepare("
        SELECT fs.FYP_Session, fs.Semester
        FROM fyp_session fs
        INNER JOIN course c ON fs.Course_ID = c.Course_ID
        WHERE c.Department_ID = ?
        ORDER BY fs.FYP_Session DESC, fs.Semester DESC
        LIMIT 1
    ");
    if ($latestSessionStmt) {
        $latestSessionStmt->bind_param('i', $departmentId);
        if ($latestSessionStmt->execute()) {
            $latestRes = $latestSessionStmt->get_result();
            if ($latestRow = $latestRes->fetch_assoc()) {
                $currentYear = $latestRow['FYP_Session'];
                $currentSemester = (int)$latestRow['Semester'];
            }
        }
        $latestSessionStmt->close();
    }
}

// Fallback to default if no session found
if ($currentYear === null) {
    $currentYear = '2024/2025';
    $currentSemester = 2;
}

// -------------------------
// FYP Title status counts (current session only)
// -------------------------
$titleStatusCounts = [
    'Approved' => 0,
    'Waiting For Approval' => 0,
    'Rejected' => 0
];

if ($userId && $departmentId !== null) {
    $statusQuery = "
        SELECT fp.Title_Status, COUNT(*) AS cnt
        FROM fyp_project fp
        INNER JOIN student s ON fp.Student_ID = s.Student_ID
        INNER JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
        WHERE s.Department_ID = ?
          AND fs.FYP_Session = ?
          AND fs.Semester = ?
        GROUP BY fp.Title_Status
    ";

    if ($stmt = $conn->prepare($statusQuery)) {
        $stmt->bind_param('isi', $departmentId, $currentYear, $currentSemester);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $status = trim($row['Title_Status']);
                $count = (int)$row['cnt'];
                if (strcasecmp($status, 'Approved') === 0) {
                    $titleStatusCounts['Approved'] = $count;
                } elseif (strcasecmp($status, 'Rejected') === 0) {
                    $titleStatusCounts['Rejected'] = $count;
                } else {
                    // Treat any other status as Waiting For Approval
                    $titleStatusCounts['Waiting For Approval'] += $count;
                }
            }
        }
        $stmt->close();
    }
}

$titleChartData = [
    'labels' => ['Approved', 'Waiting for approval', 'Rejected'],
    'data'   => [
        $titleStatusCounts['Approved'],
        $titleStatusCounts['Waiting For Approval'],
        $titleStatusCounts['Rejected']
    ]
];

// -------------------------
// Widget data: Student counts per course and total lecturers
// -------------------------
$firstCourseStudentCount = 0;
$secondCourseStudentCount = 0;
$firstCourseCode = '';
$secondCourseCode = '';
$baseCourseCode = '';
$totalLecturers = 0;

// -------------------------
// Course & assessment completion data for current session
// -------------------------
$courseCharts = [];

if ($departmentId !== null) {
    // 1) Get courses under this department
    $courses = [];
    $courseStmt = $conn->prepare("SELECT Course_ID, Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code");
    if ($courseStmt) {
        $courseStmt->bind_param('i', $departmentId);
        if ($courseStmt->execute()) {
            $courseRes = $courseStmt->get_result();
            while ($cRow = $courseRes->fetch_assoc()) {
                $courses[] = [
                    'id' => (int)$cRow['Course_ID'],
                    'code' => $cRow['Course_Code']
                ];
            }
        }
        $courseStmt->close();
    }
    if (!empty($courses)) {
        $baseCourseCode = preg_replace('/[-_ ]?[A-Za-z]$/', '', $courses[0]['code']);
    }
    
    // Get student counts for first and second courses
    if (!empty($courses)) {
        // First course
        $firstCourse = $courses[0];
        $firstCourseCode = $firstCourse['code'];
        $firstSessionIds = [];
        $firstFsStmt = $conn->prepare("
            SELECT FYP_Session_ID 
            FROM fyp_session 
            WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ?
        ");
        if ($firstFsStmt) {
            $firstFsStmt->bind_param('isi', $firstCourse['id'], $currentYear, $currentSemester);
            if ($firstFsStmt->execute()) {
                $firstFsRes = $firstFsStmt->get_result();
                while ($fsRow = $firstFsRes->fetch_assoc()) {
                    $firstSessionIds[] = (int)$fsRow['FYP_Session_ID'];
                }
            }
            $firstFsStmt->close();
        }
        if (!empty($firstSessionIds)) {
            $placeholders = implode(',', array_fill(0, count($firstSessionIds), '?'));
            $firstStudentStmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM student 
                WHERE Course_ID = ? AND FYP_Session_ID IN ($placeholders)
            ");
            if ($firstStudentStmt) {
                $params = array_merge([$firstCourse['id']], $firstSessionIds);
                $types = 'i' . str_repeat('i', count($firstSessionIds));
                $firstStudentStmt->bind_param($types, ...$params);
                if ($firstStudentStmt->execute()) {
                    $firstStudentRes = $firstStudentStmt->get_result();
                    if ($row = $firstStudentRes->fetch_assoc()) {
                        $firstCourseStudentCount = (int)$row['cnt'];
                    }
                }
                $firstStudentStmt->close();
            }
        }
        
        // Second course (if exists)
        if (count($courses) > 1) {
            $secondCourse = $courses[1];
            $secondCourseCode = $secondCourse['code'];
            $secondSessionIds = [];
            $secondFsStmt = $conn->prepare("
                SELECT FYP_Session_ID 
                FROM fyp_session 
                WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ?
            ");
            if ($secondFsStmt) {
                $secondFsStmt->bind_param('isi', $secondCourse['id'], $currentYear, $currentSemester);
                if ($secondFsStmt->execute()) {
                    $secondFsRes = $secondFsStmt->get_result();
                    while ($fsRow = $secondFsRes->fetch_assoc()) {
                        $secondSessionIds[] = (int)$fsRow['FYP_Session_ID'];
                    }
                }
                $secondFsStmt->close();
            }
            if (!empty($secondSessionIds)) {
                $placeholders = implode(',', array_fill(0, count($secondSessionIds), '?'));
                $secondStudentStmt = $conn->prepare("
                    SELECT COUNT(*) AS cnt
                    FROM student 
                    WHERE Course_ID = ? AND FYP_Session_ID IN ($placeholders)
                ");
                if ($secondStudentStmt) {
                    $params = array_merge([$secondCourse['id']], $secondSessionIds);
                    $types = 'i' . str_repeat('i', count($secondSessionIds));
                    $secondStudentStmt->bind_param($types, ...$params);
                    if ($secondStudentStmt->execute()) {
                        $secondStudentRes = $secondStudentStmt->get_result();
                        if ($row = $secondStudentRes->fetch_assoc()) {
                            $secondCourseStudentCount = (int)$row['cnt'];
                        }
                    }
                    $secondStudentStmt->close();
                }
            }
        }
    }
    
    // Get total lecturers for the department
    $lecturerCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM lecturer WHERE Department_ID = ?");
    if ($lecturerCountStmt) {
        $lecturerCountStmt->bind_param('i', $departmentId);
        if ($lecturerCountStmt->execute()) {
            $lecturerCountRes = $lecturerCountStmt->get_result();
            if ($row = $lecturerCountRes->fetch_assoc()) {
                $totalLecturers = (int)$row['cnt'];
            }
        }
        $lecturerCountStmt->close();
    }

    // 2) For each course, find FYP session IDs for the current year/semester
    foreach ($courses as $course) {
        $courseId = $course['id'];
        $sessionIds = [];
        $fsStmt = $conn->prepare("
            SELECT FYP_Session_ID 
            FROM fyp_session 
            WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ?
        ");
        if ($fsStmt) {
            $fsStmt->bind_param('isi', $courseId, $currentYear, $currentSemester);
            if ($fsStmt->execute()) {
                $fsRes = $fsStmt->get_result();
                while ($fsRow = $fsRes->fetch_assoc()) {
                    $sessionIds[] = (int)$fsRow['FYP_Session_ID'];
                }
            }
            $fsStmt->close();
        }
        if (empty($sessionIds)) continue;

        // 3) Get students in these sessions for this course
        $students = [];
        $placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
        $types = str_repeat('i', count($sessionIds));
        $studentSql = "
            SELECT Student_ID 
            FROM student 
            WHERE Course_ID = $courseId
              AND FYP_Session_ID IN ($placeholders)
        ";
        if ($stmtStu = $conn->prepare($studentSql)) {
            $stmtStu->bind_param($types, ...$sessionIds);
            if ($stmtStu->execute()) {
                $stuRes = $stmtStu->get_result();
                while ($sRow = $stuRes->fetch_assoc()) {
                    $students[] = $sRow['Student_ID'];
                }
            }
            $stmtStu->close();
        }
        $totalStudents = count($students);
        if ($totalStudents === 0) continue;

        // 4) Get assessments for this course with their role classification
        $assessments = [];
        $asmtStmt = $conn->prepare("
            SELECT a.Assessment_ID, a.Assessment_Name, COALESCE(ac.Role_Name, 'Supervisor') AS Role_Name
            FROM assessment a
            LEFT JOIN assessment_classification ac ON a.Assessment_ID = ac.Assessment_ID
            WHERE a.Course_ID = ?
            ORDER BY a.Assessment_Name
        ");
        if ($asmtStmt) {
            $asmtStmt->bind_param('i', $courseId);
            if ($asmtStmt->execute()) {
                $asmtRes = $asmtStmt->get_result();
                while ($aRow = $asmtRes->fetch_assoc()) {
                    $role = strtolower($aRow['Role_Name']) === 'assessor' ? 'Assessor' : 'Supervisor';
                    $assessments[] = [
                        'id' => (int)$aRow['Assessment_ID'],
                        'name' => $aRow['Assessment_Name'],
                        'role' => $role
                    ];
                }
            }
            $asmtStmt->close();
        }
        if (empty($assessments)) continue;

        // 5) For each assessment, count lecturers who completed vs incomplete
        $assessmentData = [];
        foreach ($assessments as $a) {
            $assessmentRole = $a['role']; // 'Supervisor' or 'Assessor'
            $assessmentId = $a['id'];
            
            // Get all criteria for this assessment to check completion
            $criteriaIds = [];
            $criteriaStmt = $conn->prepare("
                SELECT Criteria_ID
                FROM assessment_criteria
                WHERE Assessment_ID = ?
            ");
            if ($criteriaStmt) {
                $criteriaStmt->bind_param('i', $assessmentId);
                if ($criteriaStmt->execute()) {
                    $criteriaRes = $criteriaStmt->get_result();
                    while ($critRow = $criteriaRes->fetch_assoc()) {
                        $criteriaIds[] = (int)$critRow['Criteria_ID'];
                    }
                }
                $criteriaStmt->close();
            }
            
            $lectureCompletionMap = []; // Maps lecturer_id to completion status for this assessment
            
            if ($assessmentRole === 'Supervisor') {
                // For Supervisor role assessments: only get supervisors from student_enrollment
                $supStmt = $conn->prepare("
                    SELECT DISTINCT se.Supervisor_ID, s.Lecturer_ID
                    FROM student_enrollment se
                    INNER JOIN supervisor s ON se.Supervisor_ID = s.Supervisor_ID
                    WHERE se.Fyp_Session_ID IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")
                      AND se.Supervisor_ID IS NOT NULL
                ");
                if ($supStmt) {
                    $supStmt->bind_param(str_repeat('i', count($sessionIds)), ...$sessionIds);
                    if ($supStmt->execute()) {
                        $supRes = $supStmt->get_result();
                        while ($supRow = $supRes->fetch_assoc()) {
                            $lecturerId = $supRow['Lecturer_ID'];
                            $supervisorId = $supRow['Supervisor_ID'];
                            
                            // Get students supervised by this lecturer for this session
                            $lecturerStudents = [];
                            $lecStuStmt = $conn->prepare("
                                SELECT DISTINCT se.Student_ID
                                FROM student_enrollment se
                                WHERE se.Fyp_Session_ID IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")
                                  AND se.Supervisor_ID = ?
                            ");
                            if ($lecStuStmt) {
                                $sessionIdsWithSup = array_merge($sessionIds, [$supervisorId]);
                                $typesStu = str_repeat('i', count($sessionIds)) . 'i';
                                $lecStuStmt->bind_param($typesStu, ...$sessionIdsWithSup);
                                if ($lecStuStmt->execute()) {
                                    $lecStuRes = $lecStuStmt->get_result();
                                    while ($stuRow = $lecStuRes->fetch_assoc()) {
                                        $lecturerStudents[] = $stuRow['Student_ID'];
                                    }
                                }
                                $lecStuStmt->close();
                            }
                            
                            // Check if all students have complete evaluations for all criteria
                            $isComplete = false;
                            if (!empty($lecturerStudents) && !empty($criteriaIds)) {
                                $stuPlace = implode(',', array_fill(0, count($lecturerStudents), '?'));
                                $critPlace = implode(',', array_fill(0, count($criteriaIds), '?'));
                                $checkSql = "
                                    SELECT COUNT(DISTINCT CONCAT(e.Student_ID, '_', e.Criteria_ID)) AS completed_count
                                    FROM evaluation e
                                    WHERE e.Assessment_ID = ?
                                      AND e.Student_ID IN ($stuPlace)
                                      AND e.Criteria_ID IN ($critPlace)
                                      AND e.Supervisor_ID = ?
                                      AND e.Evaluation_Percentage IS NOT NULL
                                ";
                                if ($checkStmt = $conn->prepare($checkSql)) {
                                    $expectedCount = count($lecturerStudents) * count($criteriaIds);
                                    $bindTypes = 'i' . str_repeat('s', count($lecturerStudents)) . str_repeat('i', count($criteriaIds)) . 'i';
                                    $params = array_merge([$assessmentId], $lecturerStudents, $criteriaIds, [$supervisorId]);
                                    if (!$checkStmt->bind_param($bindTypes, ...$params)) {
                                        error_log("bind_param failed: " . $checkStmt->error);
                                    }
                                    if ($checkStmt->execute()) {
                                        $checkRes = $checkStmt->get_result();
                                        if ($checkRow = $checkRes->fetch_assoc()) {
                                            $completedCount = (int)$checkRow['completed_count'];
                                            $isComplete = ($completedCount === $expectedCount);
                                        }
                                    }
                                    $checkStmt->close();
                                }
                            }
                            
                            $lectureCompletionMap[$lecturerId] = $isComplete ? 'complete' : 'incomplete';
                        }
                    }
                    $supStmt->close();
                }
            } else {
                // For Assessor role assessments: only get assessors from student_enrollment
                $assStmt = $conn->prepare("
                    SELECT DISTINCT se.Assessor_ID_1 as Assessor_ID, a.Lecturer_ID
                    FROM student_enrollment se
                    INNER JOIN assessor a ON se.Assessor_ID_1 = a.Assessor_ID
                    WHERE se.Fyp_Session_ID IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")
                      AND se.Assessor_ID_1 IS NOT NULL
                    UNION
                    SELECT DISTINCT se.Assessor_ID_2 as Assessor_ID, a.Lecturer_ID
                    FROM student_enrollment se
                    INNER JOIN assessor a ON se.Assessor_ID_2 = a.Assessor_ID
                    WHERE se.Fyp_Session_ID IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")
                      AND se.Assessor_ID_2 IS NOT NULL
                ");
                if ($assStmt) {
                    $sessionIdsForAss = array_merge($sessionIds, $sessionIds);
                    $assStmt->bind_param(str_repeat('i', count($sessionIdsForAss)), ...$sessionIdsForAss);
                    if ($assStmt->execute()) {
                        $assRes = $assStmt->get_result();
                        // Group by Lecturer_ID to handle lecturers who are assessors for multiple students
                        $lecturerToAssessors = []; // Maps lecturer_id to array of assessor_ids
                        while ($assRow = $assRes->fetch_assoc()) {
                            $lecturerId = $assRow['Lecturer_ID'];
                            $assessorId = $assRow['Assessor_ID'];
                            if (!isset($lecturerToAssessors[$lecturerId])) {
                                $lecturerToAssessors[$lecturerId] = [];
                            }
                            if (!in_array($assessorId, $lecturerToAssessors[$lecturerId])) {
                                $lecturerToAssessors[$lecturerId][] = $assessorId;
                            }
                        }
                        
                        // For each lecturer, check completion
                        foreach ($lecturerToAssessors as $lecturerId => $assessorIds) {
                            // Get students assessed by this lecturer (via any of their assessor IDs)
                            $lecturerStudents = [];
                            $assIdsPlace = implode(',', array_fill(0, count($assessorIds), '?'));
                            $lecStuStmt = $conn->prepare("
                                SELECT DISTINCT se.Student_ID
                                FROM student_enrollment se
                                WHERE se.Fyp_Session_ID IN (" . implode(',', array_fill(0, count($sessionIds), '?')) . ")
                                  AND (se.Assessor_ID_1 IN ($assIdsPlace) OR se.Assessor_ID_2 IN ($assIdsPlace))
                            ");
                            if ($lecStuStmt) {
                                $sessionIdsWithAss = array_merge($sessionIds, $assessorIds, $assessorIds);
                                $typesAssStu = str_repeat('i', count($sessionIds)) . str_repeat('i', count($assessorIds) * 2);
                                $lecStuStmt->bind_param($typesAssStu, ...$sessionIdsWithAss);
                                if ($lecStuStmt->execute()) {
                                    $lecStuRes = $lecStuStmt->get_result();
                                    while ($stuRow = $lecStuRes->fetch_assoc()) {
                                        $lecturerStudents[] = $stuRow['Student_ID'];
                                    }
                                }
                                $lecStuStmt->close();
                            }
                            
                            // Check if all students have complete evaluations for all criteria
                            // For assessor role, check Assessor_ID in evaluation table
                            $isComplete = false;
                            if (!empty($lecturerStudents) && !empty($criteriaIds)) {
                                $stuPlace = implode(',', array_fill(0, count($lecturerStudents), '?'));
                                $critPlace = implode(',', array_fill(0, count($criteriaIds), '?'));
                                $assIdsPlaceForEval = implode(',', array_fill(0, count($assessorIds), '?'));
                                $checkSql = "
                                    SELECT COUNT(DISTINCT CONCAT(e.Student_ID, '_', e.Criteria_ID)) AS completed_count
                                    FROM evaluation e
                                    WHERE e.Assessment_ID = ?
                                      AND e.Student_ID IN ($stuPlace)
                                      AND e.Criteria_ID IN ($critPlace)
                                      AND e.Assessor_ID IN ($assIdsPlaceForEval)
                                      AND e.Evaluation_Percentage IS NOT NULL
                                ";
                                if ($checkStmt = $conn->prepare($checkSql)) {
                                    $expectedCount = count($lecturerStudents) * count($criteriaIds);
                                    $bindTypes = 'i' . str_repeat('s', count($lecturerStudents)) . str_repeat('i', count($criteriaIds)) . str_repeat('i', count($assessorIds));
                                    $params = array_merge([$assessmentId], $lecturerStudents, $criteriaIds, $assessorIds);
                                    if (!$checkStmt->bind_param($bindTypes, ...$params)) {
                                        error_log("bind_param failed: " . $checkStmt->error);
                                    }
                                    if ($checkStmt->execute()) {
                                        $checkRes = $checkStmt->get_result();
                                        if ($checkRow = $checkRes->fetch_assoc()) {
                                            $completedCount = (int)$checkRow['completed_count'];
                                            $isComplete = ($completedCount === $expectedCount);
                                        }
                                    }
                                    $checkStmt->close();
                                }
                            }
                            
                            $lectureCompletionMap[$lecturerId] = $isComplete ? 'complete' : 'incomplete';
                        }
                    }
                    $assStmt->close();
                }
            }
            
            // Count complete and incomplete lecturers
            $completeLecturers = 0;
            $incompleteLecturers = 0;
            foreach ($lectureCompletionMap as $status) {
                if ($status === 'complete') {
                    $completeLecturers++;
                } else {
                    $incompleteLecturers++;
                }
            }
            
            $assessmentData[] = [
                'id' => $a['id'],
                'name' => $a['name'],
                'role' => $a['role'],
                'submitted' => $completeLecturers,
                'notSubmitted' => $incompleteLecturers,
                'total' => count($lectureCompletionMap),
                'studentCount' => $totalStudents
            ];
        }

        $courseCharts[] = [
            'course_code' => $course['code'],
            'assessments' => $assessmentData
        ];
    }
    
    // -------------------------
    // Fetch due dates for reminders
    // -------------------------
    $reminders = [];
    if (!empty($courses) && $currentYear !== null && $currentSemester !== null) {
        // Get all FYP session IDs for current year and semester for all courses in department
        $allSessionIds = [];
        foreach ($courses as $course) {
            $sessionStmt = $conn->prepare("
                SELECT FYP_Session_ID 
                FROM fyp_session 
                WHERE Course_ID = ? AND FYP_Session = ? AND Semester = ?
            ");
            if ($sessionStmt) {
                $sessionStmt->bind_param('isi', $course['id'], $currentYear, $currentSemester);
                if ($sessionStmt->execute()) {
                    $sessionRes = $sessionStmt->get_result();
                    while ($sRow = $sessionRes->fetch_assoc()) {
                        $allSessionIds[] = (int)$sRow['FYP_Session_ID'];
                    }
                }
                $sessionStmt->close();
            }
        }
        
        // Fetch due dates for all assessments in these sessions
        if (!empty($allSessionIds)) {
            $placeholders = implode(',', array_fill(0, count($allSessionIds), '?'));
            $dueDateQuery = "
                SELECT dd.Due_ID, dd.Start_Date, dd.End_Date, dd.Start_Time, dd.End_Time, dd.Role,
                       a.Assessment_Name, c.Course_Code
                FROM due_date dd
                INNER JOIN assessment a ON dd.Assessment_ID = a.Assessment_ID
                INNER JOIN course c ON a.Course_ID = c.Course_ID
                WHERE dd.FYP_Session_ID IN ($placeholders)
                ORDER BY dd.Start_Date, dd.Start_Time, c.Course_Code, a.Assessment_Name
            ";
            $dueDateStmt = $conn->prepare($dueDateQuery);
            if ($dueDateStmt) {
                $types = str_repeat('i', count($allSessionIds));
                $dueDateStmt->bind_param($types, ...$allSessionIds);
                if ($dueDateStmt->execute()) {
                    $dueDateRes = $dueDateStmt->get_result();
                    while ($ddRow = $dueDateRes->fetch_assoc()) {
                        $reminders[] = [
                            'course_code' => $ddRow['Course_Code'],
                            'assessment_name' => $ddRow['Assessment_Name'],
                            'start_date' => $ddRow['Start_Date'],
                            'end_date' => $ddRow['End_Date'],
                            'start_time' => $ddRow['Start_Time'],
                            'end_time' => $ddRow['End_Time'],
                            'role' => $ddRow['Role']
                        ];
                    }
                }
                $dueDateStmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Coordinator</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
    // Prevent back button after logout
    window.history.pushState(null, "", window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, "", window.location.href);
    };

    // Check session validity on page load and periodically
    function validateSession() {
        fetch('../../../php/check_session_alive.php')
            .then(function(resp){ return resp.json(); })
            .then(function(data){
                if (!data.valid) {
                    // Session is invalid, redirect to login
                    window.location.href = '../../login/Login.php';
                }
            })
            .catch(function(err){
                // If we can't reach the server, assume session is invalid
                console.warn('Session validation failed:', err);
                window.location.href = '../../login/Login.php';
            });
    }

    // Validate session on page load
    window.addEventListener('load', validateSession);

    // Also check every 10 seconds
    setInterval(validateSession, 10000);
    </script>
</head>
<body>

    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()">☰</button>

        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>

            <span id="nameSide">HI, <?php echo htmlspecialchars($coordinatorName); ?></span>

            <a href="#supervisorMenu" class="role-header" data-role="supervisor">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-right arrow-icon"></i>
                </span>
            </a>

            <div id="supervisorMenu" class="menu-items">
                <a href="../../../php/phpSupervisor/dashboard.php" id="dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../../../php/phpSupervisor/notification.php" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../../../php/phpSupervisor/industrey%20collaboration.php" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i>
                    Industry Collaboration</a>
                <a href="../../../php/phpAssessor_Supervisor/evaluation_form.php" id="evaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
                <a href="../../../php/phpSupervisor/report.php" id="superviseesReport"><i class="bi bi-bar-chart-fill icon-padding"></i> Supervisees' Report</a>
                <a href="../../../php/phpSupervisor/logbook_submission.php" id="logbookSubmission"><i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission</a>
            </div>

            <a href="#assessorMenu" class="role-header" data-role="assessor">
                <span class="role-text">Assessor</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-right arrow-icon"></i>
                </span>
            </a>

            <div id="assessorMenu" class="menu-items">
                <a href="../../../php/phpAssessor/dashboard.php" id="Dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../../../php/phpAssessor/notification.php" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../../../php/phpAssessor_Supervisor/evaluation_form.php" id="EvaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
            </div>

            <a href="#coordinatorMenu" class="role-header active-role menu-expanded" data-role="coordinator">
                <span class="role-text">Coordinator</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-down arrow-icon"></i>
                </span>
            </a>

            <div id="coordinatorMenu" class="menu-items expanded">
                <a href="dashboardCoordinator.php" id="coordinatorDashboard" class="active-menu-item"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../studentAssignation/studentAssignation.php" id="studentAssignation"><i class="bi bi-people-fill icon-padding"></i> Student Assignment</a>
                <a href="../learningObjective/learningObjective.php" id="learningObjective"><i class="bi bi-book-fill icon-padding"></i> Learning Objective</a>
                <a href="../markSubmission/markSubmission.php" id="markSubmission"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Signature Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Date and Time Allocation</a>
            </div>

            <a href="../../logout.php" id="logout">
                <i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout
            </a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">
        <a href="../dashboard/dashboardCoordinator.php">
            <img src="../../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>

        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule">Coordinator Module</div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode"><?php echo htmlspecialchars($baseCourseCode ?: $firstCourseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars($currentYear . ' - ' . $currentSemester); ?></div>
            </div>
        </div>
    </div>

    <div id="main" class="main-grid">
        <!-- Top widgets -->
        <div class="metrics-grid">
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-users"></i></span>
                <div class="widget-content">
                    <span class="widget-title"><?php echo htmlspecialchars($firstCourseCode ? 'Total Students for ' . $firstCourseCode : 'Title Submissions'); ?></span>
                    <span id="titleSubmissions" class="widget-value"><?php echo $firstCourseStudentCount; ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-users"></i></span>
                <div class="widget-content">
                    <span class="widget-title"><?php echo htmlspecialchars($secondCourseCode ? 'Total Students for ' . $secondCourseCode : 'Overall Progress'); ?></span>
                    <span id="overallProgress" class="widget-value"><?php echo $secondCourseStudentCount; ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-users"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Total Lecturers</span>
                    <span id="totalStudents" class="widget-value"><?php echo $totalLecturers; ?></span>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="task-reminder-section">
            <div class="evaluation-area">
                <h1 class="card-title">Submission Progress</h1>

                <div class="evaluation-task-card">
                    <div class="tab-buttons">
                        <button class="task-tab active-tab" data-tab="title">FYP Title Submission</button>
                        <?php foreach ($courseCharts as $idx => $course): ?>
                            <button class="task-tab" data-tab="course-<?php echo $idx; ?>"><?php echo htmlspecialchars($course['course_code']); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="graph-hover-hint">Hover over the chart to see overall status.
                        <br><br>Click on the chart to see detailed assessment status.
                    </div>
                    <div class="task-list-area">

                        <!-- FYP Title Submission Tab -->
                        <div class="task-group active" data-group="title">
                            <div class="graph-container-wrapper">
                                <div id="titleChartContainer" class="chart-container">
                                    <canvas id="titleChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Course Assessment Tabs -->
                        <?php foreach ($courseCharts as $idx => $course): ?>
                        <div class="task-group" data-group="course-<?php echo $idx; ?>">
                            <div class="graph-container-wrapper">
                                <div id="courseChartContainer-<?php echo $idx; ?>" class="chart-container">
                                    <canvas id="courseChart-<?php echo $idx; ?>"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Reminder Section -->
            <div class="reminder-area">
                <h1 class="card-title">Reminder</h1>
                <div class="reminder-card">
                    <div class="reminder-card-content">
                        <?php if (empty($reminders)): ?>
                            <div class="reminder-item">
                                <p class="reminder-date" style="text-align: center; color: #999; font-style: italic;">No reminder for now</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            // Group reminders by date
                            $groupedReminders = [];
                            foreach ($reminders as $reminder) {
                                $dateKey = $reminder['start_date'];
                                if (!isset($groupedReminders[$dateKey])) {
                                    $groupedReminders[$dateKey] = [];
                                }
                                $groupedReminders[$dateKey][] = $reminder;
                            }
                            // Sort by date
                            ksort($groupedReminders);
                            $firstItem = true;
                            foreach ($groupedReminders as $date => $dateReminders): 
                            ?>
                                <?php if (!$firstItem): ?>
                                    <hr class="reminder-separator">
                                <?php endif; ?>
                                <div class="reminder-item">
                                    <?php 
                                    // Format date
                                    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
                                    $formattedDate = $dateObj ? $dateObj->format('d F Y') : $date;
                                    ?>
                                    <p class="reminder-date"><?php echo htmlspecialchars($formattedDate); ?></p>
                                    <ul>
                                        <?php foreach ($dateReminders as $reminder): ?>
                                            <?php 
                                            // Format time
                                            $startTime = $reminder['start_time'] ? date('H:i', strtotime($reminder['start_time'])) : '';
                                            $endTime = $reminder['end_time'] ? date('H:i', strtotime($reminder['end_time'])) : '';
                                            $timeStr = $startTime && $endTime ? "$startTime - $endTime" : ($startTime ? $startTime : '');
                                            ?>
                                            <li style="list-style-type: disc; margin-bottom: 8px;">
                                                <strong>Assessment:</strong> <?php echo htmlspecialchars($reminder['assessment_name']); ?><br>
                                                <?php if ($reminder['course_code']): ?>
                                                    <strong>Course Code:</strong> <?php echo htmlspecialchars($reminder['course_code']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($reminder['role']): ?>
                                                    <strong>Role:</strong> <?php echo htmlspecialchars($reminder['role']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($timeStr): ?>
                                                    <strong>Time:</strong> <?php echo htmlspecialchars($timeStr); ?>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php $firstItem = false; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        .custom-tooltip {
            opacity: 0;
            position: absolute;
            background: transparent;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 1000;
            font-family: 'Montserrat', sans-serif;
            min-width: 180px;
        }
        .tooltip-header {
            background-color: #363636;
            color: #ffffff;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px 4px 0 0;
        }
        .tooltip-body {
            background-color: #1a2a1a;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 0 0 4px 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tooltip-indicator {
            width: 12px;
            height: 12px;
            background-color: #4CAF50;
            border: 1px solid #e0e0e0;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .tooltip-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 13px;
            color: #e0e0e0;
        }
        .tooltip-content div {
            color: #e0e0e0;
        }
    </style>
    <script>
        // Dynamic data from backend
        const titleStatusData = <?php echo json_encode($titleChartData); ?>;
        const courseChartsData = <?php echo json_encode($courseCharts); ?>;
        let titleChartInstance = null;
        let courseChartInstances = [];

        // Function to create bar chart where each title has its own bars with gaps
        function createGroupedBarChart(canvasId, chartData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            // Extract labels and data
            const labels = chartData.titles || chartData.data.map(item => item.name);
            const data = chartData.data || [chartData];
            const courseCode = chartData.course_code || '';

            // Check if this is SWE4949-A or B chart to use Complete/Incomplete labels
            const isSWE4949 = canvasId.includes('swe4949a') || canvasId.includes('swe4949b');
            const positiveLabel = isSWE4949 ? 'Complete' : 'Complete';
            const negativeLabel = isSWE4949 ? 'Incomplete' : 'Incomplete';

            // Create datasets for submitted and not submitted (now representing lecturers)
            const submittedData = data.map(item => item.submitted);
            const notSubmittedData = data.map(item => item.notSubmitted);
            const totals = data.map(item => item.total);

            const datasets = [
                {
                    label: positiveLabel,
                    data: submittedData,
                    backgroundColor: '#4CAF50',
                    borderColor: '#4CAF50',
                    borderWidth: 1,
                    barThickness: 'flex',
                    maxBarThickness: 50,
                    categoryPercentage: 0.5,
                    barPercentage: 0.7
                },
                {
                    label: negativeLabel,
                    data: notSubmittedData,
                    backgroundColor: '#F44336',
                    borderColor: '#F44336',
                    borderWidth: 1,
                    barThickness: 'flex',
                    maxBarThickness: 50,
                    categoryPercentage: 0.5,
                    barPercentage: 0.7
                }
            ];

            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    layout: {
                        padding: {
                            left: 10,
                            right: 10
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    onHover: (event, activeElements) => {
                        event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                    },
                    onClick: (event, activeElements) => {
                        if (activeElements.length > 0 && courseCode) {
                            const dataIndex = activeElements[0].index;
                            const datasetIndex = activeElements[0].datasetIndex;
                            const assessmentData = data[dataIndex];
                            const isComplete = datasetIndex === 0; // First dataset is "Complete"
                            
                            // Get assessment ID and role from the data
                            const assessmentId = assessmentData.id;
                            const role = assessmentData.role ? assessmentData.role.toLowerCase() : 'supervisor';
                            const status = isComplete ? 'completed' : 'incomplete';
                            
                            // Normalize course code for tab (lowercase, remove special chars)
                            const normalizedCourseCode = courseCode.toLowerCase().replace(/[^a-zA-Z0-9]/g, '');
                            
                            // Create filter value in the format: supervisor_${assessment_id}_${status} or assessor_${assessment_id}_${status}
                            const filterValue = `${role}_${assessmentId}_${status}`;
                            
                            // Navigate to markSubmission.php with course tab, view, and assessment filter
                            const params = new URLSearchParams({
                                tab: normalizedCourseCode,
                                view: 'lecturer-progress',
                                assessmentFilter: filterValue
                            });
                            window.location.href = '../markSubmission/markSubmission.php?' + params.toString();
                        }
                    },
                    interaction: {
                        mode: 'point',
                        intersect: true
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { display: false },
                            ticks: { 
                                font: { size: 11 },
                                maxRotation: 45,
                                minRotation: 45
                            },
                            border: { display: true, color: '#000', width: 1 }
                        },
                        y: {
                            beginAtZero: true,
                            max: Math.max(...totals) + 5,
                            ticks: { 
                                stepSize: 5,
                                font: { size: 12 },
                                precision: 0
                            },
                            border: { display: true, color: '#000', width: 1 },
                            grid: { display: false },
                            title: {
                                display: true,
                                text: 'Number of Lecturers',
                                font: { size: 12, weight: 'bold' }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: { size: 12 },
                                padding: 12,
                                boxWidth: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            enabled: false, // Disable default tooltip
                            external: function(context) {
                                // Custom tooltip implementation
                                let tooltipEl = document.getElementById('chartjs-tooltip');
                                
                                // Create tooltip if it doesn't exist
                                if (!tooltipEl) {
                                    tooltipEl = document.createElement('div');
                                    tooltipEl.id = 'chartjs-tooltip';
                                    tooltipEl.className = 'custom-tooltip';
                                    document.body.appendChild(tooltipEl);
                                }
                                
                                // Hide tooltip if no data
                                if (!context.tooltip || context.tooltip.opacity === 0 || !context.tooltip.dataPoints || context.tooltip.dataPoints.length === 0) {
                                    tooltipEl.style.opacity = '0';
                                    tooltipEl.style.pointerEvents = 'none';
                                    return;
                                }

                                const chart = context.chart;
                                const dataPoint = context.tooltip.dataPoints[0];
                                const dataIndex = dataPoint.dataIndex;
                                const datasetIndex = dataPoint.datasetIndex;
                                const dataset = chart.data.datasets[datasetIndex];
                                const value = dataPoint.parsed.y;
                                const total = totals[dataIndex];
                                const studentCount = data[dataIndex].studentCount || 0;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                                const count = value.toFixed(0);
                                const statusLabel = dataset.label; // "Complete" or "Incomplete"
                                const assessmentName = chart.data.labels[dataIndex]; // Assessment name

                                // Position tooltip
                                const position = chart.canvas.getBoundingClientRect();
                                const left = position.left + context.tooltip.caretX + window.scrollX;
                                const top = position.top + context.tooltip.caretY + window.scrollY;
                                
                                // Set tooltip position
                                tooltipEl.style.opacity = '1';
                                tooltipEl.style.left = left + 'px';
                                tooltipEl.style.top = top + 'px';
                                tooltipEl.style.transform = 'translate(-50%, -100%)';
                                tooltipEl.style.marginTop = '-10px';
                                tooltipEl.style.pointerEvents = 'none';

                                // Determine indicator color based on status
                                const indicatorColor = (statusLabel === 'Complete') ? '#4CAF50' : '#F44336';

                                // Set tooltip content with dark theme - assessment name and status in header, details in body
                                tooltipEl.innerHTML = `
                                    <div class="tooltip-header">${assessmentName}</div>
                                    <div class="tooltip-body">
                                        <div class="tooltip-indicator" style="background-color: ${indicatorColor};"></div>
                                        <div class="tooltip-content">
                                            <div>${statusLabel}: ${count} Lecturers (${percentage}%)</div>
                                            <div>Total Students: ${studentCount}</div>
                                        </div>
                                    </div>
                                `;
                            }
                        },
                        datalabels: {
                            display: false
                        }
                    }
                }
            });
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // FYP Title Submission Chart (Pie)
            const titleCtx = document.getElementById('titleChart');
            if (titleCtx && titleStatusData && Array.isArray(titleStatusData.data)) {
                const totalCount = titleStatusData.data.reduce((sum, val) => sum + (parseInt(val) || 0), 0);
                titleChartInstance = new Chart(titleCtx, {
                    type: 'pie',
                    data: {
                        labels: titleStatusData.labels,
                        datasets: [{
                            data: titleStatusData.data,
                            backgroundColor: [
                                '#4CAF50',  // Approved
                                '#FF9800',  // Waiting for approval
                                '#F44336'   // Rejected
                            ],
                            borderColor: '#ffffff',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: (event, activeElements) => {
                            event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                        },
                        onClick: (event, activeElements) => {
                            if (activeElements.length > 0) {
                                const dataIndex = activeElements[0].index;
                                const labels = titleStatusData.labels;
                                const statusMap = {
                                    'Approved': 'approved',
                                    'Waiting for approval': 'waiting',
                                    'Rejected': 'rejected'
                                };
                                const selectedLabel = labels[dataIndex];
                                const statusFilter = statusMap[selectedLabel] || 'all';
                                
                                // Navigate to markSubmission.php with status filter and FYP title submission tab
                                window.location.href = '../markSubmission/markSubmission.php?tab=fyp-title-submission&status=' + encodeURIComponent(statusFilter);
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: { size: 12 },
                                    padding: 12,
                                    usePointStyle: true
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const pct = totalCount > 0 ? ((value / totalCount) * 100).toFixed(1) : 0;
                                        return `${label}: ${value} (${pct}%)`;
                                    }
                                }
                            },
                            datalabels: {
                                color: '#fff',
                                font: { weight: 'bold' },
                                formatter: function(value) {
                                    if (totalCount === 0) return '';
                                    const pct = ((value / totalCount) * 100).toFixed(0);
                                    return value > 0 ? `${pct}%` : '';
                                }
                            }
                        }
                    }
                });
            }

            // Course assessment charts (Complete vs Incomplete) per course code
            if (Array.isArray(courseChartsData)) {
                courseChartsData.forEach((course, idx) => {
                    const chartId = 'courseChart-' + idx;
                    const chartData = {
                        titles: course.assessments.map(a => a.name),
                        data: course.assessments.map(a => ({
                            id: a.id,
                            name: a.name,
                            role: a.role,
                            submitted: a.submitted,
                            notSubmitted: a.notSubmitted,
                            total: a.total,
                            studentCount: a.studentCount
                        })),
                        course_code: course.course_code
                    };
                    courseChartInstances[idx] = createGroupedBarChart(chartId, chartData);
                });
            }
        });
    </script>

    <script>
        // --- REALTIME DASHBOARD POLLING ---
        let coordinatorPollInterval = null;
        let coordinatorDataHash = '';

        function hashCoordinator(data) {
            try {
                return JSON.stringify({
                    widgets: data.widgets,
                    title: data.titleChart,
                    courses: (data.courseCharts || []).map(c => ({ code: c.course_code, data: c.assessments }))
                });
            } catch (e) { return ''; }
        }

        function updateWidgets(widgets) {
            if (!widgets) return;
            const w1 = document.getElementById('titleSubmissions');
            const w2 = document.getElementById('overallProgress');
            const w3 = document.getElementById('totalStudents');
            if (w1) w1.textContent = widgets.firstCourseStudentCount ?? 0;
            if (w2) w2.textContent = widgets.secondCourseStudentCount ?? 0;
            if (w3) w3.textContent = widgets.totalLecturers ?? 0;
            const courseCodeEl = document.getElementById('courseCode');
            const courseSessionEl = document.getElementById('courseSession');
            if (courseCodeEl && widgets.baseCourseCode) {
                courseCodeEl.textContent = widgets.baseCourseCode || widgets.firstCourseCode || '';
            }
            if (courseSessionEl && window.currentYear && window.currentSemester) {
                courseSessionEl.textContent = `${window.currentYear} - ${window.currentSemester}`;
            }
        }

        function updateTitleChart(titleData) {
            if (!titleChartInstance || !titleData) return;
            const ds = titleChartInstance.data.datasets[0];
            titleChartInstance.data.labels = titleData.labels || ['Approved', 'Waiting for approval', 'Rejected'];
            ds.data = titleData.data || [0,0,0];
            titleChartInstance.update();
        }

        function updateCourseCharts(courses) {
            if (!Array.isArray(courses)) return;
            if (courses.length !== courseChartInstances.length) {
                // Structure changed; safest is to refresh page
                window.location.reload();
                return;
            }
            courses.forEach((course, idx) => {
                const chart = courseChartInstances[idx];
                if (!chart || !course.assessments) return;
                chart.data.labels = course.assessments.map(a => a.name);
                const dsComplete = chart.data.datasets[0];
                const dsIncomplete = chart.data.datasets[1];
                dsComplete.data = course.assessments.map(a => a.submitted);
                dsIncomplete.data = course.assessments.map(a => a.notSubmitted);
                chart.options.scales.y.max = Math.max(...course.assessments.map(a => a.total || 0)) + 5;
                chart.update();
            });
        }

        function applyCoordinatorData(data) {
            if (!data || !data.success) return;
            window.currentYear = data.currentYear || window.currentYear;
            window.currentSemester = data.currentSemester || window.currentSemester;
            updateWidgets(data.widgets);
            updateTitleChart(data.titleChart);
            updateCourseCharts(data.courseCharts);
        }

        function fetchCoordinatorMetrics() {
            fetch('../../../php/phpCoordinator/fetch_dashboard_metrics.php')
                .then(resp => resp.json())
                .then(data => {
                    const newHash = hashCoordinator(data);
                    if (newHash !== coordinatorDataHash) {
                        coordinatorDataHash = newHash;
                        applyCoordinatorData(data);
                    }
                })
                .catch(err => console.error('Coordinator dashboard poll failed:', err));
        }

        function startCoordinatorPolling() {
            fetchCoordinatorMetrics();
            coordinatorPollInterval = setInterval(fetchCoordinatorMetrics, 1000);
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (coordinatorPollInterval) { clearInterval(coordinatorPollInterval); coordinatorPollInterval = null; }
            } else {
                if (!coordinatorPollInterval) { startCoordinatorPolling(); }
            }
        });

        (function initCoordinatorRealtime(){
            const initial = {
                success: true,
                widgets: {
                    firstCourseStudentCount: <?php echo json_encode($firstCourseStudentCount); ?>,
                    secondCourseStudentCount: <?php echo json_encode($secondCourseStudentCount); ?>,
                    totalLecturers: <?php echo json_encode($totalLecturers); ?>,
                    firstCourseCode: <?php echo json_encode($firstCourseCode); ?>,
                    secondCourseCode: <?php echo json_encode($secondCourseCode); ?>,
                    baseCourseCode: <?php echo json_encode($baseCourseCode ?: $firstCourseCode); ?>
                },
                titleChart: <?php echo json_encode($titleChartData); ?>,
                courseCharts: <?php echo json_encode($courseCharts); ?>
            };
            coordinatorDataHash = hashCoordinator(initial);
            startCoordinatorPolling();
        })();
    </script>

    <script>
        // --- JAVASCRIPT LOGIC ---
        function openNav() {
            var fullWidth = "220px";
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main"); 
            var menuIcon = document.querySelector(".menu-icon");

            // 1. Expand the Sidebar
            document.getElementById("mySidebar").style.width = fullWidth;

            // 2. Push the main content AND the header container to the right
            if (mainContent) {
                mainContent.style.marginLeft = fullWidth;
            }
            if (header) {
                header.style.marginLeft = fullWidth;
            }

            // 3. Show the links
            document.getElementById("nameSide").style.display = "block";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                if (links[i].classList.contains('role-header') || links[i].id === 'logout') {
                    links[i].style.display = 'flex';
                } else if (links[i].id === 'close') {
                    links[i].style.display = 'flex';
                }
            }
            
            // Show currently expanded menu items
            document.querySelectorAll('.menu-items.expanded a').forEach(a => a.style.display = 'block');

            // 4. Hide the open icon
            if (menuIcon) menuIcon.style.display = "none";
        }

        function closeNav() {
            var collapsedWidth = "60px";
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main"); 
            var menuIcon = document.querySelector(".menu-icon");

            // 1. Collapse the Sidebar
            sidebar.style.width = collapsedWidth;

            // 2. Move the main content AND the header container back
            if (mainContent) {
                mainContent.style.marginLeft = collapsedWidth;
            }
            if (header) {
                header.style.marginLeft = collapsedWidth;
            }

            // 3. Hide the name and the links (except for the open menu icon)
            document.getElementById("nameSide").style.display = "none";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                links[i].style.display = "none";
            }

            // 4. Show the open icon
            if (menuIcon) menuIcon.style.display = "block";
        }

        // Ensure the collapsed state is set immediately on page load
        window.onload = function () {
            closeNav();
        };

        // --- Role Toggle Logic ---
        document.addEventListener('DOMContentLoaded', () => {
            // Store the active menu item ID to restore when menu expands again
            let activeMenuItemId = null;
            
            // Function to set active menu item based on current page
            function setActiveMenuItemBasedOnPage() {
                const currentPage = window.location.pathname;
                const fileName = currentPage.split('/').pop();
                
                // Map page files to menu item IDs
                const pageToMenuItemMap = {
                    'dashboardCoordinator.php': 'coordinatorDashboard',
                    'studentAssignation.php': 'studentAssignation',
                    'learningObjective/learningObjective.php': 'learningObjective',
                    'notification/notification.php': 'coordinatorNotification',
                    'dateTimeAllocation/dateTimeAllocation.php': 'dateTimeAllocation',
                    'markSubmission/markSubmission.php': 'markSubmission',
                    'signatureSubmission.php': 'signatureSubmission',
                    'dateTimeAllocation.php': 'dateTimeAllocation'
                };
                
                // Check if we're in a subdirectory
                if (currentPage.includes('dashboard/')) {
                    return 'coordinatorDashboard';
                } else if (currentPage.includes('studentAssignation/')) {
                    return 'studentAssignation';
                } else if (currentPage.includes('learningObjective/')) {
                    return 'learningObjective';
                } else if (currentPage.includes('notification/')) {
                    return 'coordinatorNotification';
                } else if (currentPage.includes('dateTimeAllocation/')) {
                    return 'dateTimeAllocation';
                } else if (currentPage.includes('markSubmission/')) {
                    return 'markSubmission';
                }
                
                // Get the menu item ID for current page, default to dashboard
                return pageToMenuItemMap[fileName] || 'coordinatorDashboard';
            }
            
            // Initialize active menu item based on current page
            activeMenuItemId = setActiveMenuItemBasedOnPage();
            
            // Function to set active menu item
            function setActiveMenuItem(menuItemId) {
                const coordinatorMenuItems = document.querySelectorAll('#coordinatorMenu a');
                coordinatorMenuItems.forEach(item => {
                    item.classList.remove('active-menu-item');
                });
                
                const activeItem = document.querySelector(`#${menuItemId}`);
                if (activeItem) {
                    activeItem.classList.add('active-menu-item');
                    activeMenuItemId = menuItemId;
                }
            }
            
            // Ensure coordinator header always has active-role class on coordinator pages
            // And ensure other roles do NOT have active-role on coordinator pages
            const allRoleHeaders = document.querySelectorAll('.role-header');
            const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
            const coordinatorMenu = document.querySelector('#coordinatorMenu');
            
            // Remove active-role from all non-coordinator roles (since we're on coordinator pages)
            allRoleHeaders.forEach(header => {
                const roleType = header.getAttribute('data-role');
                if (roleType !== 'coordinator') {
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                }
            });
            
            if (coordinatorHeader && coordinatorMenu) {
                coordinatorHeader.classList.add('active-role');
                
                // If coordinator menu is expanded, add menu-expanded class to header and set active item
                if (coordinatorMenu.classList.contains('expanded')) {
                    coordinatorHeader.classList.add('menu-expanded');
                    // Set active menu item based on current page
                    setActiveMenuItem(activeMenuItemId);
                } else {
                    coordinatorHeader.classList.remove('menu-expanded');
                }
            }
            
            const arrowContainers = document.querySelectorAll('.arrow-container');
            
            // Function to handle the role menu toggle
            const handleRoleToggle = (header) => {
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');
                const isCoordinator = header.getAttribute('data-role') === 'coordinator';
                
                if (!targetMenu) return;

                const isExpanded = targetMenu.classList.contains('expanded');

                // Collapse all other menus and reset their arrows
                document.querySelectorAll('.menu-items').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.remove('expanded');
                        menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                    }
                });
                
                // CRITICAL: Always ensure coordinator header state is correct
                const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
                const coordinatorMenu = document.querySelector('#coordinatorMenu');
                
                if (coordinatorHeader && coordinatorMenu) {
                    // Coordinator header ALWAYS has active-role on coordinator pages
                    coordinatorHeader.classList.add('active-role');
                    
                    // If coordinator menu is collapsed, ensure it shows white (remove menu-expanded)
                    if (!coordinatorMenu.classList.contains('expanded')) {
                        coordinatorHeader.classList.remove('menu-expanded');
                    } else {
                        // If coordinator menu is expanded, ensure it shows normal (add menu-expanded)
                        coordinatorHeader.classList.add('menu-expanded');
                        // Restore active menu item if menu is expanded
                        if (activeMenuItemId) {
                            setTimeout(() => {
                                setActiveMenuItem(activeMenuItemId);
                            }, 10);
                        }
                    }
                }
                
                // Remove active-role from all non-coordinator roles (they shouldn't be highlighted on coordinator pages)
                document.querySelectorAll('.role-header').forEach(h => {
                    const roleType = h.getAttribute('data-role');
                    // Only keep active-role for coordinator on coordinator pages
                    if (roleType !== 'coordinator') {
                        h.classList.remove('active-role');
                        // Also remove menu-expanded class from other roles
                        h.classList.remove('menu-expanded');
                    }
                });
                document.querySelectorAll('.arrow-icon').forEach(icon => {
                    if (icon !== arrowIcon) {
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-right');
                    }
                });

                // Toggle current menu
                targetMenu.classList.toggle('expanded', !isExpanded);
                
                // Handle coordinator header styling based on menu state
                if (isCoordinator) {
                    // Coordinator header always has active-role on coordinator pages
                    header.classList.add('active-role');
                    
                    // Check menu state AFTER toggle
                    const isNowExpanded = targetMenu.classList.contains('expanded');
                    
                    // Add/remove menu-expanded class based on menu state AFTER toggle
                    if (isNowExpanded) {
                        // Menu is now expanded - remove white background from header
                        header.classList.add('menu-expanded');
                        // Restore active menu item when menu expands (ensure it's visible and styled)
                        if (activeMenuItemId) {
                            // Small delay to ensure menu is fully expanded before setting active item
                            setTimeout(() => {
                                setActiveMenuItem(activeMenuItemId);
                            }, 10);
                        }
                    } else {
                        // Menu is now collapsed - add white background to header
                        header.classList.remove('menu-expanded');
                        // Keep active menu item class for when menu expands again
                        // The activeMenuItemId variable already stores the current active item
                    }
                } else {
                    // For other roles, DO NOT add active-role class
                    // Other roles should only be highlighted when actually on their pages
                    // Just toggle the menu, but don't highlight the header
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                    
                    // IMPORTANT: After toggling other roles, ensure coordinator header state is maintained
                    // This ensures coordinator stays white when its menu is collapsed, even when other roles are clicked
                    if (coordinatorHeader && coordinatorMenu) {
                        coordinatorHeader.classList.add('active-role');
                        if (!coordinatorMenu.classList.contains('expanded')) {
                            coordinatorHeader.classList.remove('menu-expanded');
                        } else {
                            coordinatorHeader.classList.add('menu-expanded');
                            if (activeMenuItemId) {
                                setTimeout(() => {
                                    setActiveMenuItem(activeMenuItemId);
                                }, 10);
                            }
                        }
                    }
                }
                
                // Show/hide child links for the current menu (only when sidebar is expanded)
                const sidebar = document.getElementById("mySidebar");
                const isSidebarExpanded = sidebar.style.width === "220px";

                targetMenu.querySelectorAll('a').forEach(a => {
                    if(isSidebarExpanded) {
                         a.style.display = targetMenu.classList.contains('expanded') ? 'block' : 'none';
                    } else {
                        a.style.display = 'none';
                    }
                });

                // Toggle arrow direction
                if (isExpanded) {
                    arrowIcon.classList.remove('bi-chevron-down');
                    arrowIcon.classList.add('bi-chevron-right');
                } else {
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                }
            };

            arrowContainers.forEach(container => {
                // Attach event listener to the role header itself
                const header = container.closest('.role-header');
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleRoleToggle(header);
                });
            });

            // --- Menu Item Click Handlers ---
            // Handle clicks on coordinator menu items
            const coordinatorMenuItems = document.querySelectorAll('#coordinatorMenu a');
            coordinatorMenuItems.forEach(menuItem => {
                menuItem.addEventListener('click', (event) => {
                    const coordinatorMenu = document.querySelector('#coordinatorMenu');
                    const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
                    
                    // Only set active menu item if coordinator menu is expanded
                    if (coordinatorMenu && coordinatorMenu.classList.contains('expanded')) {
                        // Store the clicked menu item ID
                        const menuItemId = menuItem.getAttribute('id');
                        if (menuItemId) {
                            setActiveMenuItem(menuItemId);
                        }
                    }
                });
            });

            // --- Tab Switching Logic ---
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.getAttribute('data-tab');

                    // 1. Update active tab style
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');

                    // 2. Switch active task group
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            group.classList.add('active');
                        } else {
                            group.classList.remove('active');
                        }
                    });
                });
            });

            // Default to 'title' tab view on load
            document.querySelector('.task-group[data-group="title"]').classList.add('active');
        });
    </script>
</body>
</html>

