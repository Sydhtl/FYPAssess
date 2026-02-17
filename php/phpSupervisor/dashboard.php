<?php
// Start Session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../db_connect.php';

// 1. CAPTURE ROLE & USER ID
// Check if loginID is in session, otherwise default to 'GUEST'
$loginID = isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'USER';
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'supervisor';

// 2. PREPARE MODULE TITLE
$moduleTitle = ucfirst($activeRole) . " Module";

// 3. FETCH COURSE INFO AND SESSION ID
// HARDCODED: Using FYP_Session_ID 1 and 2 for current academic sessions
$courseCode = "SWE4949";
$courseSession = "2024/2025 - 1";
$latestSessionID = 1; // Hardcoded to session 1 for now

error_log("DEBUG [Dashboard]: Using hardcoded Session ID: " . $latestSessionID);

// A. Get Login ID and Role
if (isset($_SESSION['upmId'])) {
    $loginID = $_SESSION['upmId'];
    error_log("DEBUG [Dashboard]: Using upmId from session: " . $loginID);
} else {
    $loginID = 'hazura'; // Fallback
    error_log("WARNING [Dashboard]: Session upmId not set, using fallback: " . $loginID);
}

// Check if user has Coordinator role
$userRole = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$isCoordinator = ($userRole === 'Coordinator');

// Get lecturer full name
$lecturerName = $loginID; // Default fallback
$stmtName = $conn->prepare("SELECT Lecturer_Name FROM lecturer WHERE Lecturer_ID = ?");
$stmtName->bind_param("s", $loginID);
$stmtName->execute();
if ($rowName = $stmtName->get_result()->fetch_assoc()) {
    $lecturerName = $rowName['Lecturer_Name'];
}
$stmtName->close();

// B. Lookup Numeric ID
$currentUserID = null;
if ($activeRole === 'supervisor') {
    $stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currentUserID = $row['Supervisor_ID'];
        error_log("DEBUG [Dashboard]: Found Supervisor_ID: " . $currentUserID . " for Lecturer_ID: " . $loginID);
    } else {
        error_log("ERROR [Dashboard]: No Supervisor_ID found for Lecturer_ID: " . $loginID);
    }
    $stmt->close();
} elseif ($activeRole === 'assessor') {
    $stmt = $conn->prepare("SELECT Assessor_ID FROM assessor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $currentUserID = $row['Assessor_ID'];
        error_log("DEBUG [Dashboard]: Found Assessor_ID: " . $currentUserID . " for Lecturer_ID: " . $loginID);
    } else {
        error_log("ERROR [Dashboard]: No Assessor_ID found for Lecturer_ID: " . $loginID);
    }
    $stmt->close();
}

// ========== DEBUG: LOG CRITICAL VALUES ==========
error_log("DEBUG [Dashboard]: Active Role: " . $activeRole);
error_log("DEBUG [Dashboard]: Login ID: " . $loginID);
error_log("DEBUG [Dashboard]: Current User ID: " . ($currentUserID ?? 'NULL'));
error_log("DEBUG [Dashboard]: Latest Session ID: " . ($latestSessionID ?? 'NULL'));

if (!$currentUserID) {
    error_log("CRITICAL [Dashboard]: currentUserID is NULL - No data will be fetched!");
}
if (!$latestSessionID) {
    error_log("CRITICAL [Dashboard]: latestSessionID is NULL - No data will be fetched!");
}

// ========== FETCH DASHBOARD DATA ==========

// Initialize variables
$superviseeCount = 0;
$inProgressCount = 0;
$approvalRequestCount = 0;
$nearestDueDays = 0;

// 1. COUNT SUPERVISEES (from student_enrollment table - sessions 1 and 2)
if ($currentUserID) {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT Student_ID) as count FROM student_enrollment WHERE Supervisor_ID = ? AND FYP_Session_ID IN (1, 2)");
    $stmt->bind_param("i", $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $superviseeCount = $row['count'];
        error_log("DEBUG [Dashboard]: Supervisee count: " . $superviseeCount);
    }
    $stmt->close();
} else {
    error_log("WARNING [Dashboard]: Skipping supervisee count - currentUserID is NULL");
}

// 2. COUNT IN-PROGRESS TASKS (evaluations not submitted by supervisor - sessions 1 and 2)
// Assessment IDs: 1 for Course 1, and 4,5 for Course 2
$stmt = $conn->prepare("
        SELECT COUNT(DISTINCT se.Student_ID, a.Assessment_ID) as count 
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID
        JOIN course c ON s.Course_ID = c.Course_ID
        JOIN assessment a ON c.Course_ID = a.Course_ID
        LEFT JOIN evaluation e ON se.Student_ID = e.Student_ID 
            AND e.Supervisor_ID = ? 
            AND e.Assessment_ID = a.Assessment_ID
        WHERE se.Supervisor_ID = ?
        AND se.FYP_Session_ID IN (1, 2)
        AND (
            (c.Course_ID = 1 AND a.Assessment_ID = 1) OR
            (c.Course_ID = 2 AND a.Assessment_ID IN (4, 5))
        )
        AND e.Evaluation_ID IS NULL
    ");
$stmt->bind_param("ii", $currentUserID, $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $inProgressCount = $row['count'];
}
$stmt->close();

// 3. COUNT APPROVAL REQUESTS (logbook + project title not approved - sessions 1 and 2)
// Logbook with status 'Waiting for Approval'
$stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM logbook l
        JOIN student_enrollment se ON l.Student_ID = se.Student_ID
        WHERE se.Supervisor_ID = ? 
        AND se.FYP_Session_ID IN (1, 2)
        AND l.Logbook_Status = 'Waiting for Approval'
    ");
$stmt->bind_param("i", $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $approvalRequestCount += $row['count'];
}
$stmt->close();

// Project title not approved
$stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM fyp_project pt
        JOIN student_enrollment se ON pt.Student_ID = se.Student_ID
        WHERE se.Supervisor_ID = ? 
        AND se.FYP_Session_ID IN (1, 2)
        AND (pt.Title_Status = 'Waiting For Approval')
    ");
$stmt->bind_param("i", $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $approvalRequestCount += $row['count'];
}
$stmt->close();

// 4. GET NEAREST EVALUATION DUE DATE
$stmt = $conn->prepare("
        SELECT DATEDIFF(dd.End_Date, CURDATE()) as days_left
        FROM due_date dd
        WHERE dd.Role = 'Supervisor' 
        AND dd.End_Date >= CURDATE()
        AND dd.FYP_Session_ID IN (1, 2)
        ORDER BY dd.End_Date ASC
        LIMIT 1
    ");
$stmt->execute();
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $nearestDueDays = $row['days_left'] . ' days';
}
$stmt->close();


// 5. FETCH REMINDER DATA - Evaluation deadlines from due_date, custom messages from reminder table
$reminderItems = [];
// Fetch due dates for supervisor assessments grouped by date (like coordinator)
$stmt = $conn->prepare("
    SELECT 
        dd.Start_Date,
        dd.End_Date,
        dd.Start_Time,
        dd.End_Time,
        dd.Role,
        a.Assessment_Name,
        c.Course_Code
    FROM due_date dd
    JOIN assessment a ON dd.Assessment_ID = a.Assessment_ID
    JOIN course c ON a.Course_ID = c.Course_ID
    WHERE dd.Role = 'Supervisor'
    AND dd.End_Date >= CURDATE()
    AND dd.FYP_Session_ID IN (1, 2)
    ORDER BY dd.Start_Date ASC, dd.Start_Time ASC
");
$stmt->execute();
$stmt->execute();
$result = $stmt->get_result();

// Group reminders by date
$groupedByDate = [];
while ($row = $result->fetch_assoc()) {
    $dateKey = $row['End_Date'];
    if (!isset($groupedByDate[$dateKey])) {
        $groupedByDate[$dateKey] = [];
    }
    $groupedByDate[$dateKey][] = [
        'assessment_name' => $row['Assessment_Name'],
        'course_code' => $row['Course_Code'],
        'start_time' => $row['Start_Time'],
        'end_time' => $row['End_Time'],
        'end_date' => $row['End_Date'],
        'role' => $row['Role']
    ];
}
$stmt->close();

// Convert grouped data to reminder items format
foreach ($groupedByDate as $date => $assessments) {
    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    $formattedDate = $dateObj ? $dateObj->format('d F Y') : $date;

    $reminderItems[] = [
        'type' => 'due_date',
        'date' => $formattedDate,
        'assessments' => $assessments
    ];
}

// Fetch custom reminder messages from reminder table (filtered by role)
$stmt = $conn->prepare("SELECT Message, Date FROM reminder WHERE Role = 'Supervisor' OR Role IS NULL ORDER BY Date DESC LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    if (!empty($row['Message'])) {
        $reminderItems[] = [
            'type' => 'custom',
            'message' => $row['Message'],
            'date' => $row['Date'] ? date('d F Y', strtotime($row['Date'])) : null
        ];
    }
}
$stmt->close();



// 6. FETCH CHART DATA - Supervisee's Marks (sessions 1 and 2)
$marksData = [];
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT 
            se.Student_ID,
            (SELECT Student_Name FROM student WHERE Student_ID = se.Student_ID LIMIT 1) as Student_Name,
            c.Course_Code,
            COALESCE(SUM(CASE WHEN a.Course_ID = c.Course_ID THEN e.Evaluation_Percentage ELSE 0 END), 0) as total_marks
        FROM student_enrollment se
        JOIN course c ON c.Course_ID = (CASE 
            WHEN se.FYP_Session_ID = 1 THEN 1 
            WHEN se.FYP_Session_ID = 2 THEN 2 
        END)
        LEFT JOIN evaluation e ON se.Student_ID = e.Student_ID
        LEFT JOIN assessment a ON e.Assessment_ID = a.Assessment_ID
        WHERE se.Supervisor_ID = ?
        AND se.FYP_Session_ID IN (1, 2)
        GROUP BY se.Student_ID, se.FYP_Session_ID, c.Course_Code
        ORDER BY Student_Name
    ");
    $stmt->bind_param("i", $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $marksData[] = $row;
    }
    $stmt->close();
}

// 7. FETCH CHART DATA - Logbook Submissions (sessions 1 and 2)
$logbookData = [];
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT 
            s.Student_ID,
            s.Student_Name,
            COUNT(CASE WHEN l.Course_ID = 1 AND l.FYP_Session_ID = 2 AND l.Logbook_Status = 'Approved' THEN 1 END) as submitted_A,
            COUNT(CASE WHEN l.Course_ID = 2 AND l.FYP_Session_ID = 1 AND l.Logbook_Status = 'Approved' THEN 1 END) as submitted_B,
            6 as required
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID
        LEFT JOIN logbook l ON s.Student_ID = l.Student_ID
        WHERE se.Supervisor_ID = ?
        AND se.FYP_Session_ID IN (1, 2)
        GROUP BY s.Student_ID, s.Student_Name
        ORDER BY s.Student_Name
    ");
    $stmt->bind_param("i", $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logbookData[] = $row;
    }
    $stmt->close();
}

// 8. FETCH IN-PROGRESS EVALUATION TASKS (sessions 1 and 2)
// Assessments that supervisor needs to evaluate (Assessment 1 for Course 1, Assessment 4,5 for Course 2)
$inProgressTasks = [];
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT 
            s.Student_ID,
            s.Student_Name,
            pt.Project_Title,
            a.Assessment_Name,
            a.Assessment_ID,
            c.Course_ID,
            dd.End_Date
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID
        JOIN course c ON s.Course_ID = c.Course_ID
        JOIN assessment a ON c.Course_ID = a.Course_ID
        LEFT JOIN fyp_project pt ON s.Student_ID = pt.Student_ID
        LEFT JOIN due_date dd ON a.Assessment_ID = dd.Assessment_ID AND dd.FYP_Session_ID = se.FYP_Session_ID AND dd.Role = 'Supervisor'
        LEFT JOIN evaluation e ON s.Student_ID = e.Student_ID 
            AND a.Assessment_ID = e.Assessment_ID 
            AND e.Supervisor_ID = ?
        WHERE se.Supervisor_ID = ?
        AND se.FYP_Session_ID IN (1, 2)
        AND (
            (c.Course_ID = 1 AND a.Assessment_ID = 1) OR
            (c.Course_ID = 2 AND a.Assessment_ID IN (4, 5))
        )
        AND e.Evaluation_ID IS NULL
        ORDER BY s.Student_Name, a.Assessment_ID
    ");
    $stmt->bind_param("ii", $currentUserID, $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $inProgressTasks[] = $row;
    }
    $stmt->close();
}

// 9. FETCH UPCOMING TASKS (Assessor evaluations - Assessment 2 for Course 1, Assessment 3 for Course 2 - sessions 1 and 2)
$upcomingTasks = [];
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT DISTINCT
            s.Student_ID,
            s.Student_Name,
            pt.Project_Title,
            a.Assessment_Name,
            a.Assessment_ID,
            asess.Date,
            asess.Time,
            asess.Venue,
            asess.Session_ID,
            GROUP_CONCAT(DISTINCT CONCAT(l.Lecturer_Name, '|', IFNULL(l.Lecturer_PhoneNo, 'N/A')) ORDER BY l.Lecturer_Name SEPARATOR '||') as Assessor_Info
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID AND se.FYP_Session_ID = s.FYP_Session_ID
        JOIN course c ON s.Course_ID = c.Course_ID
        JOIN assessment a ON c.Course_ID = a.Course_ID
        LEFT JOIN fyp_project pt ON s.Student_ID = pt.Student_ID
        LEFT JOIN student_session ss ON s.Student_ID = ss.Student_ID AND se.FYP_Session_ID = ss.FYP_Session_ID
        LEFT JOIN assessment_session asess ON ss.Session_ID = asess.Session_ID AND asess.Assessment_ID = a.Assessment_ID
        LEFT JOIN assessor_session ases ON asess.Session_ID = ases.Session_ID
        LEFT JOIN assessor ase ON ases.Assessor_ID = ase.Assessor_ID
        LEFT JOIN lecturer l ON ase.Lecturer_ID = l.Lecturer_ID
        WHERE se.Supervisor_ID = ?
        AND se.FYP_Session_ID IN (1, 2)
        AND (
            (c.Course_ID = 1 AND a.Assessment_ID = 2) OR
            (c.Course_ID = 2 AND a.Assessment_ID = 3)
        )
        AND asess.Date IS NOT NULL
        AND asess.Date >= CURDATE()
        GROUP BY s.Student_ID, s.Student_Name, pt.Project_Title, a.Assessment_Name, a.Assessment_ID, asess.Date, asess.Time, asess.Venue, asess.Session_ID
        ORDER BY asess.Date, asess.Time
    ");
    $stmt->bind_param("i", $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    error_log("DEBUG [Seminar Schedule]: Query returned " . $result->num_rows . " rows for Supervisor ID: " . $currentUserID);
    while ($row = $result->fetch_assoc()) {
        error_log("DEBUG [Seminar Schedule]: Found student " . $row['Student_ID'] . " with session " . $row['Session_ID'] . ", Date: " . $row['Date']);

        // Parse assessor info to separate names and phones
        $assessorNames = [];
        $assessorTooltips = [];
        if (!empty($row['Assessor_Info'])) {
            $assessors = explode('||', $row['Assessor_Info']);
            foreach ($assessors as $assessor) {
                $parts = explode('|', $assessor);
                if (count($parts) == 2) {
                    $assessorNames[] = $parts[0];
                    $assessorTooltips[] = $parts[1];
                }
            }
        }
        $row['Assessor_Names'] = implode(', ', $assessorNames);
        $row['Assessor_Phones'] = $assessorTooltips;

        $upcomingTasks[] = $row;
    }
    $stmt->close();
}
error_log("DEBUG [Seminar Schedule]: Total upcoming tasks found: " . count($upcomingTasks));

// 10. FETCH COMPLETED TASKS (Supervisor evaluations already submitted - sessions 1 and 2)
$completedTasks = [];
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT 
            se.Student_ID,
            (SELECT Student_Name FROM student WHERE Student_ID = se.Student_ID LIMIT 1) as Student_Name,
            pt.Project_Title,
            a.Assessment_Name,
            SUM(e.Evaluation_Percentage) as Total_Marks,
            MAX(e.Evaluation_ID) as latest_eval
        FROM student_enrollment se
        JOIN evaluation e ON se.Student_ID = e.Student_ID
        JOIN assessment a ON e.Assessment_ID = a.Assessment_ID
        LEFT JOIN fyp_project pt ON se.Student_ID = pt.Student_ID
        WHERE se.Supervisor_ID = ?
        AND se.FYP_Session_ID IN (1, 2)
        AND (
            (se.FYP_Session_ID = 1 AND a.Assessment_ID = 1) OR
            (se.FYP_Session_ID = 2 AND a.Assessment_ID IN (4, 5))
        )
        GROUP BY se.Student_ID, se.FYP_Session_ID, pt.Project_Title, a.Assessment_Name
        ORDER BY latest_eval DESC
    ");
    $stmt->bind_param("i", $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $completedTasks[] = $row;
    }
    $stmt->close();
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Dashboard_Supervisor</title>
    <link rel="stylesheet" href="../../css/supervisor/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css">
    <!-- <link rel="stylesheet" href="../../css/dashboard.css"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()"><i class="bi bi-list"></i></button>

        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>

            <span id="nameSide">Hi, <?php echo ucwords(strtolower($lecturerName)); ?></span>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'supervisor') ? 'menu-expanded' : ''; ?>"
                data-target="supervisorMenu" onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items <?php echo ($activeRole == 'supervisor') ? 'expanded' : ''; ?>">
                <a href="dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>


                <a href="notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>


                <a href="industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Industry Collaboration
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=supervisor" id="evaluationForm"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
                <a href="report.php?role=supervisor" id="superviseesReport"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>
                <a href="logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>
                <a href="signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="project_title.php?role=supervisor" id="projectTitle"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Project Title
                </a>
            </div>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'assessor') ? 'menu-expanded' : ''; ?>"
                data-target="assessorMenu" onclick="toggleMenu('assessorMenu', this)">
                <span class="role-text">Assessor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="assessorMenu" class="menu-items <?php echo ($activeRole == 'assessor') ? 'expanded' : ''; ?>">
                <a href="../phpAssessor/dashboard.php?role=assessor" id="Dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="../phpAssessor/notification.php?role=assessor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
            </div>

            <?php if ($isCoordinator): ?>
                <a href="javascript:void(0)"
                    class="role-header <?php echo ($activeRole == 'coordinator') ? 'menu-expanded' : ''; ?>"
                    data-target="coordinatorMenu" onclick="toggleMenu('coordinatorMenu', this)">
                    <span class="role-text">Coordinator</span>
                    <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
                </a>

                <div id="coordinatorMenu"
                    class="menu-items <?php echo ($activeRole == 'coordinator') ? 'expanded' : ''; ?>">
                    <a href="../../html/coordinator/dashboard/dashboardCoordinator.php" id="CoordinatorDashboard">
                        <i class="bi bi-house-fill icon-padding"></i> Dashboard
                    </a>
                    <a href="../../html/coordinator/notification/notification.php" id="CoordinatorNotification">
                        <i class="bi bi-bell-fill icon-padding"></i> Notification
                    </a>
                    <a href="../../html/coordinator/studentAssignation/studentAssignation.php" id="StudentAssignation">
                        <i class="bi bi-people-fill icon-padding"></i> Student Assignation
                    </a>
                    <a href="../../html/coordinator/dateTimeAllocation/dateTimeAllocation.php" id="DateTimeAllocation">
                        <i class="bi bi-calendar-check-fill icon-padding"></i> Date & Time Allocation
                    </a>
                    <a href="../../html/coordinator/learningObjective/learningObjective.php" id="LearningObjective">
                        <i class="bi bi-book-fill icon-padding"></i> Learning Objective
                    </a>
                    <a href="../../html/coordinator/markSubmission/markSubmission.php" id="MarkSubmission">
                        <i class="bi bi-file-earmark-check-fill icon-padding"></i> Mark Submission
                    </a>
                    <a href="../../html/coordinator/signatureSubmission/signatureSubmission.php"
                        id="CoordinatorSignatureSubmission">
                        <i class="bi bi-pen-fill icon-padding"></i> Signature Submission
                    </a>
                </div>
            <?php endif; ?>

            <a href="../login.php" id="logout"><i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i>
                Logout</a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">
        <a href="../dashboard/dashboard.html">
            <img src="../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>
        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule"><?php echo $moduleTitle; ?></div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode"><?php echo $courseCode; ?></div>
                <div id="courseSession"><?php echo $courseSession; ?></div>
            </div>
        </div>
    </div>

    <div id="main" class="main-grid">

        <div class="metrics-grid">
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-user-group"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Supervisee</span>
                    <span id="supervisee-count" class="widget-value"><?php echo $superviseeCount; ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-bars-progress"></i></span>
                <div class="widget-content">
                    <span class="widget-title">In-progress task</span>
                    <span id="in-progress-count" class="widget-value"><?php echo $inProgressCount; ?></span>
                </div>
            </div>
            <div class="widget due-widget">
                <span class="widget-icon"><i class="fa-solid fa-bell"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Approval request</span>
                    <span id="approval-request-count" class="widget-value"><?php echo $approvalRequestCount; ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-clock"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Evaluation due</span>
                    <span id="evaluation-due" class="widget-value"><?php echo $nearestDueDays; ?></span>
                </div>
            </div>
        </div>

        <div class="overview-reminder-grid">

            <div class="overview-section">

                <div class="overview-charts-grid">
                    <div class="chart-container">
                        <h3>Supervisee's Marks</h3>
                        <div class="custom-legend">
                            <span><span class="legend-dot" style="background-color: #F8C9D4;"></span> SWE4949A</span>
                            <span><span class="legend-dot" style="background-color: #2E86C1;"></span> SWE4949B</span>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="marksChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-container">
                        <h3>Approved Logbook </h3>
                        <div class="custom-legend">
                            <span><span class="legend-dot" style="background-color: #F8C9D4;"></span> SWE4949A</span>
                            <span><span class="legend-dot" style="background-color: #2E86C1;"></span> SWE4949B</span>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="logbookChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reminder-area">
                <h1 class="card-title">Reminder</h1>
                <div class="reminder-card">
                    <div class="reminder-card-content">
                        <?php if (!empty($reminderItems)): ?>
                            <?php foreach ($reminderItems as $index => $reminder): ?>
                                <?php if ($index > 0): ?>
                                    <hr class="reminder-separator">
                                <?php endif; ?>
                                <div class="reminder-item">
                                    <?php if ($reminder['type'] === 'custom'): ?>
                                        <?php if (!empty($reminder['date'])): ?>
                                            <p class="reminder-date"><?php echo htmlspecialchars($reminder['date']); ?></p>
                                        <?php endif; ?>
                                        <ul>
                                            <li><?php echo htmlspecialchars($reminder['message']); ?></li>
                                        </ul>
                                    <?php elseif ($reminder['type'] === 'due_date'): ?>
                                        <p class="reminder-date"><?php echo htmlspecialchars($reminder['date']); ?></p>
                                        <ul>
                                            <?php foreach ($reminder['assessments'] as $assessment): ?>
                                                <?php
                                                // Format time
                                                $startTime = $assessment['start_time'] ? date('H:i', strtotime($assessment['start_time'])) : '';
                                                $endTime = $assessment['end_time'] ? date('H:i', strtotime($assessment['end_time'])) : '';
                                                $timeStr = $startTime && $endTime ? "$startTime - $endTime" : ($startTime ? $startTime : '');
                                                ?>
                                                <li style="list-style-type: disc; margin-bottom: 8px;">
                                                    <strong>Assessment:</strong>
                                                    <?php echo htmlspecialchars($assessment['assessment_name']); ?><br>
                                                    <?php if ($assessment['course_code']): ?>
                                                        <strong>Course Code:</strong>
                                                        <?php echo htmlspecialchars($assessment['course_code']); ?><br>
                                                    <?php endif; ?>
                                                    <?php if ($assessment['role']): ?>
                                                        <strong>Role:</strong> <?php echo htmlspecialchars($assessment['role']); ?><br>
                                                    <?php endif; ?>
                                                    <?php if ($timeStr): ?>
                                                        <strong>Time:</strong> <?php echo htmlspecialchars($timeStr); ?>
                                                    <?php endif; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="reminder-item">
                                <p class="reminder-date" style="text-align: center; color: #999; font-style: italic;">No
                                    reminder for now</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <div class="evaluation-area">

            <h1 class="card-title">Evaluation task</h1>
            <div class="evaluation-task-card">
                <div class="tab-buttons">
                    <button class="task-tab active-tab" data-tab="inprogress">In-progress</button>
                    <button class="task-tab" data-tab="completed">Completed</button>
                    <button class="task-tab" data-tab="upcoming">Seminar Schedule</button>
                </div>
                <div class="task-list-area">

                    <!-- IN-PROGRESS TAB -->
                    <div class="task-group active" data-group="inprogress">
                        <div class="task-row header-row four-col-grid">
                            <span class="col-supervisee">Supervisee</span>
                            <span class="col-project-title">Project title</span>
                            <span class="col-assessment-type">Assessment type</span>
                            <span class="col-due-date">Due date</span>
                        </div>
                        <?php
                        // Group tasks by student
                        $groupedInProgress = [];
                        foreach ($inProgressTasks as $task) {
                            $studentId = $task['Student_ID'];
                            if (!isset($groupedInProgress[$studentId])) {
                                $groupedInProgress[$studentId] = [
                                    'student_id' => $task['Student_ID'],
                                    'name' => $task['Student_Name'],
                                    'title' => $task['Project_Title'] ?: 'No title submitted',
                                    'assessments' => []
                                ];
                            }
                            // Use Assessment_ID as key to prevent duplicates
                            $assessmentKey = $task['Assessment_ID'];
                            if (!isset($groupedInProgress[$studentId]['assessments'][$assessmentKey])) {
                                $groupedInProgress[$studentId]['assessments'][$assessmentKey] = [
                                    'name' => $task['Assessment_Name'],
                                    'due' => $task['End_Date'] ? date('d M Y', strtotime($task['End_Date'])) : 'TBA'
                                ];
                            } else {
                                // If assessment already exists but current row has a valid date and stored one is TBA, update it
                                if ($task['End_Date'] && $groupedInProgress[$studentId]['assessments'][$assessmentKey]['due'] === 'TBA') {
                                    $groupedInProgress[$studentId]['assessments'][$assessmentKey]['due'] = date('d M Y', strtotime($task['End_Date']));
                                }
                            }
                        }

                        if (empty($groupedInProgress)): ?>
                            <div class="task-row data-row four-col-grid">
                                <span class="col-supervisee"
                                    style="grid-column: 1 / -1; text-align: center; color: #999;">No in-progress
                                    tasks</span>
                            </div>
                        <?php else:
                            foreach ($groupedInProgress as $studentData):
                                $assessmentNames = array_column($studentData['assessments'], 'name');
                                $dueDates = array_column($studentData['assessments'], 'due');
                                ?>
                                <div class="task-row data-row four-col-grid">
                                    <span
                                        class="col-supervisee"><?php echo htmlspecialchars($studentData['name']); ?><br><small><?php echo htmlspecialchars($studentData['student_id']); ?></small></span>
                                    <span
                                        class="col-project-title"><?php echo htmlspecialchars($studentData['title']); ?></span>
                                    <span
                                        class="col-assessment-type"><?php echo implode('<br>', array_map('htmlspecialchars', $assessmentNames)); ?></span>
                                    <span
                                        class="col-due-date"><?php echo implode('<br>', array_map('htmlspecialchars', $dueDates)); ?></span>
                                </div>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </div>

                    <!-- UPCOMING TAB -->
                    <div class="task-group" data-group="upcoming">
                        <?php
                        // Group upcoming tasks by date and venue
                        $groupedUpcoming = [];
                        foreach ($upcomingTasks as $task) {
                            if ($task['Date']) {
                                $dateKey = $task['Date'] . '_' . $task['Venue'];
                                if (!isset($groupedUpcoming[$dateKey])) {
                                    $groupedUpcoming[$dateKey] = [
                                        'date' => $task['Date'],
                                        'venue' => $task['Venue'] ?: 'TBA',
                                        'tasks' => []
                                    ];
                                }
                                $groupedUpcoming[$dateKey]['tasks'][] = $task;
                            }
                        }

                        if (empty($groupedUpcoming)): ?>
                            <div style="padding: 20px; text-align: center; color: #999;">No upcoming assessment sessions
                            </div>
                        <?php else:
                            $sessionIndex = 0;
                            foreach ($groupedUpcoming as $dateGroup):
                                $sessionIndex++;
                                $sessionId = 'list-' . $sessionIndex;
                                $dateObj = new DateTime($dateGroup['date']);
                                $formattedDate = $dateObj->format('d M Y, D');
                                ?>
                                <div class="task-group-header" data-target="#<?php echo $sessionId; ?>">
                                    <i class="fas fa-chevron-down toggle-icon"></i>
                                    <?php echo $formattedDate . ' - ' . htmlspecialchars($dateGroup['venue']); ?>
                                </div>
                                <div id="<?php echo $sessionId; ?>" class="task-list-details">
                                    <div class="task-row header-row five-col-grid">
                                        <span class="col-student">Student</span>
                                        <span class="col-time">Time</span>
                                        <span class="col-project-title">Project title</span>
                                        <span class="col-assessment-type">Assessment type</span>
                                        <span class="col-assessor">Assessor</span>
                                    </div>
                                    <?php foreach ($dateGroup['tasks'] as $task):
                                        $timeFormatted = $task['Time'] ? date('g.i a', strtotime($task['Time'])) : 'TBA';

                                        // Build assessor display with tooltips
                                        $assessorDisplay = '';
                                        if (!empty($task['Assessor_Names'])) {
                                            $names = explode(', ', $task['Assessor_Names']);
                                            $phones = $task['Assessor_Phones'] ?? [];
                                            $assessorParts = [];
                                            foreach ($names as $index => $name) {
                                                $phone = isset($phones[$index]) ? $phones[$index] : 'N/A';
                                                $assessorParts[] = '<span class="assessor-name" data-phone="' . htmlspecialchars($phone) . '">' . htmlspecialchars($name) . '</span>';
                                            }
                                            $assessorDisplay = implode(', ', $assessorParts);
                                        } else {
                                            $assessorDisplay = 'TBA';
                                        }
                                        ?>
                                        <div class="task-row data-row five-col-grid">
                                            <span
                                                class="col-student"><?php echo htmlspecialchars($task['Student_Name']); ?><br><small><?php echo htmlspecialchars($task['Student_ID']); ?></small></span>
                                            <span class="col-time"><?php echo $timeFormatted; ?></span>
                                            <span
                                                class="col-project-title"><?php echo htmlspecialchars($task['Project_Title'] ?: 'No title submitted'); ?></span>
                                            <span
                                                class="col-assessment-type"><?php echo htmlspecialchars($task['Assessment_Name']); ?></span>
                                            <span class="col-assessor"><?php echo $assessorDisplay; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </div>

                    <!-- COMPLETED TAB -->
                    <div class="task-group" data-group="completed">
                        <div class="task-row header-row four-col-grid">
                            <span class="col-student">Student</span>
                            <span class="col-project-title">Project title</span>
                            <span class="col-assessment-type">Assessment type</span>
                            <span class="col-marks">Total Marks (%)</span>
                        </div>
                        <?php if (empty($completedTasks)): ?>
                            <div class="task-row data-row four-col-grid">
                                <span class="col-student" style="grid-column: 1 / -1; text-align: center; color: #999;">No
                                    completed evaluations</span>
                            </div>
                        <?php else:
                            foreach ($completedTasks as $task):
                                ?>
                                <div class="task-row data-row four-col-grid">
                                    <span
                                        class="col-student"><?php echo htmlspecialchars($task['Student_Name']); ?><br><small><?php echo htmlspecialchars($task['Student_ID']); ?></small></span>
                                    <span
                                        class="col-project-title"><?php echo htmlspecialchars($task['Project_Title'] ?: 'No title submitted'); ?></span>
                                    <span
                                        class="col-assessment-type"><?php echo htmlspecialchars($task['Assessment_Name']); ?></span>
                                    <span
                                        class="col-marks"><?php echo htmlspecialchars(number_format($task['Total_Marks'], 2)); ?></span>
                                </div>
                                <?php
                            endforeach;
                        endif;
                        ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- JAVASCRIPT LOGIC ---
        function openNav() {
            var fullWidth = "220px";
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            // CRITICAL FIX: Targets the main content area (now with id="main")
            var mainContent = document.getElementById("main");
            var menuIcon = document.querySelector(".menu-icon");

            // 1. Expand the Sidebar
            document.getElementById("mySidebar").style.width = fullWidth;

            // 2. Push the main content AND the header container to the right
            if (mainContent) mainContent.style.marginLeft = fullWidth;
            if (header) header.style.marginLeft = fullWidth;

            // 3. Show the links
            document.getElementById("nameSide").style.display = "block";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                // Show role headers and other links
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
            // CRITICAL FIX: Targets the main content area (now with id="main")
            var mainContent = document.getElementById("main");
            var menuIcon = document.querySelector(".menu-icon");

            // 1. Collapse the Sidebar
            sidebar.style.width = collapsedWidth;

            // 2. Move the main content AND the header container back
            if (mainContent) mainContent.style.marginLeft = collapsedWidth;
            if (header) header.style.marginLeft = collapsedWidth;

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
            const roleHeaders = document.querySelectorAll('.role-header');

            // Function to check if a role has an active menu item
            const hasActiveMenuItem = (menu) => {
                return menu.querySelector('.active-menu-item') !== null;
            };

            // Function to update role header highlighting based on active menu items and expansion state
            const updateRoleHeaderHighlighting = () => {
                document.querySelectorAll('.role-header').forEach(header => {
                    const menuId = header.getAttribute('data-target');
                    if (!menuId) return;
                    const targetMenu = document.getElementById(menuId);

                    if (!targetMenu) return;

                    // Check if this specific menu contains the currently active page/link
                    const hasActiveLink = targetMenu.querySelector('.active-menu-item') !== null;

                    // Check if this menu is currently expanded (open)
                    const isExpanded = targetMenu.classList.contains('expanded');

                    // LOGIC: 
                    // Only highlight the Role Header if it holds the active page 
                    // BUT the menu is currently collapsed (hidden).
                    if (hasActiveLink && !isExpanded) {
                        header.classList.add('active-role');
                    } else {
                        header.classList.remove('active-role');
                    }
                });
            };

            // Function to handle the role menu toggle
            const handleRoleToggle = (header) => {
                const menuId = header.getAttribute('data-target');
                if (!menuId) return;
                const targetMenu = document.getElementById(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');

                if (!targetMenu) return;

                const isExpanded = targetMenu.classList.contains('expanded');

                // Collapse all other menus
                document.querySelectorAll('.menu-items').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.remove('expanded');
                        menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                    }
                });

                // Toggle current menu
                targetMenu.classList.toggle('expanded', !isExpanded);

                if (targetMenu.classList.contains('expanded')) {
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                    // Only show child links if sidebar is expanded
                    if (document.getElementById("mySidebar").style.width === "220px") {
                        targetMenu.querySelectorAll('a').forEach(a => a.style.display = 'block');
                    }
                } else {
                    arrowIcon.classList.remove('bi-chevron-down');
                    arrowIcon.classList.add('bi-chevron-right');
                    targetMenu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                }

                // Update all role header highlighting based on current state
                updateRoleHeaderHighlighting();
            };

            roleHeaders.forEach(header => {
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleRoleToggle(header);
                });

                // Initial arrow state based on 'expanded' class in HTML
                const menuId = header.getAttribute('data-target');
                if (!menuId) return;
                const targetMenu = document.getElementById(menuId);
                if (targetMenu && targetMenu.classList.contains('expanded')) {
                    const arrowIcon = header.querySelector('.arrow-icon');
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                }
            });


            // --- Task List Accordion Logic ---
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');
            const groupHeaders = document.querySelectorAll('.task-group-header');

            // --- Tab Switching Logic (Client-side simulation) ---
            tabs.forEach(tab => {
                tab.addEventListener('click', function (e) {
                    e.preventDefault();
                    const tabName = this.getAttribute('data-tab');

                    console.log('Tab clicked:', tabName); // Debug log

                    // 1. Update active tab style
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    this.classList.add('active-tab');

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

            // --- Task Group Expand/Collapse Logic (Accordion) ---
            groupHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const targetId = header.getAttribute('data-target');
                    const targetList = document.querySelector(targetId);
                    const toggleIcon = header.querySelector('.toggle-icon');

                    if (targetList) {
                        const isExpanded = targetList.classList.contains('expanded');

                        // Toggle CSS classes
                        targetList.classList.toggle('expanded', !isExpanded);
                        toggleIcon.classList.toggle('fa-chevron-right', !targetList.classList.contains('expanded'));
                        toggleIcon.classList.toggle('fa-chevron-down', targetList.classList.contains('expanded'));
                    }
                });
            });

            // Default to 'inprogress' tab view on load
            document.querySelector('.task-group[data-group="inprogress"]').classList.add('active');

            console.log('DOM loaded, initializing charts...');

            // Prepare PHP data for charts
            <?php
            // Prepare marks data for JavaScript
            $marksLabels = [];
            $marksCourseA = [];
            $marksCourseB = [];

            foreach ($marksData as $mark) {
                $firstName = explode(' ', $mark['Student_Name'])[0];
                $labelKey = $firstName;
                $displayLabel = [$firstName, '(' . $mark['Student_ID'] . ')'];

                if (!isset($marksLabels[$labelKey])) {
                    $marksLabels[$labelKey] = $displayLabel;
                }

                if (strpos($mark['Course_Code'], 'A') !== false) {
                    $marksCourseA[$labelKey] = round($mark['total_marks'], 2);
                } else if (strpos($mark['Course_Code'], 'B') !== false) {
                    $marksCourseB[$labelKey] = round($mark['total_marks'], 2);
                }
            }

            // Ensure all students have entries for both courses
            $marksDataA = [];
            $marksDataB = [];
            $marksLabelsDisplay = [];
            foreach ($marksLabels as $key => $label) {
                $marksLabelsDisplay[] = $label;
                $marksDataA[] = isset($marksCourseA[$key]) ? $marksCourseA[$key] : 0;
                $marksDataB[] = isset($marksCourseB[$key]) ? $marksCourseB[$key] : 0;
            }

            // Prepare logbook data for JavaScript
            $logbookLabels = [];
            $logbookSubmittedA = [];
            $logbookSubmittedB = [];

            foreach ($logbookData as $log) {
                $firstName = explode(' ', $log['Student_Name'])[0];
                $displayLabel = [$firstName, '(' . $log['Student_ID'] . ')'];

                if (!in_array($displayLabel, $logbookLabels)) {
                    $logbookLabels[] = $displayLabel;
                    $logbookSubmittedA[] = (int) $log['submitted_A'];
                    $logbookSubmittedB[] = (int) $log['submitted_B'];
                }
            }
            ?>

            const marksLabels = <?php echo json_encode($marksLabelsDisplay); ?>;
            const marksDataA = <?php echo json_encode($marksDataA); ?>;
            const marksDataB = <?php echo json_encode($marksDataB); ?>;

            const logbookLabels = <?php echo json_encode($logbookLabels); ?>;
            const logbookSubmittedA = <?php echo json_encode($logbookSubmittedA); ?>;
            const logbookSubmittedB = <?php echo json_encode($logbookSubmittedB); ?>;

            // Chart 1: Supervisee's Marks (Stacked Bar)
            const ctxMarks = document.getElementById('marksChart');
            console.log('Canvas element marksChart:', ctxMarks);

            if (ctxMarks) {
                try {
                    const marksChart = new Chart(ctxMarks, {
                        type: 'bar',
                        data: {
                            labels: marksLabels,
                            datasets: [{
                                label: 'SWE4949A',
                                data: marksDataA,
                                backgroundColor: '#F8C9D4',
                                borderRadius: 4
                            },
                            {
                                label: 'SWE4949B',
                                data: marksDataB,
                                backgroundColor: '#2E86C1',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            let label = context.dataset.label || '';
                                            if (label) {
                                                label += ': ';
                                            }
                                            label += context.parsed.y + '%';
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: { display: false }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Total (%)'
                                    },
                                    max: 100
                                }
                            }
                        }
                    });
                    console.log('Marks chart created successfully:', marksChart);
                } catch (error) {
                    console.error('Error creating marks chart:', error);
                }
            } else {
                console.error('Canvas element marksChart not found!');
            }

            // Chart 2: Logbook Submission (Grouped Bar)
            const ctxLogbook = document.getElementById('logbookChart');
            console.log('Canvas element logbookChart:', ctxLogbook);

            if (ctxLogbook) {
                try {
                    const logbookChart = new Chart(ctxLogbook, {
                        type: 'bar',
                        data: {
                            labels: logbookLabels,
                            datasets: [{
                                label: 'SWE4949A',
                                data: logbookSubmittedA,
                                backgroundColor: '#F8C9D4',
                                borderRadius: 4
                            },
                            {
                                label: 'SWE4949B',
                                data: logbookSubmittedB,
                                backgroundColor: '#2E86C1',
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    stacked: false,
                                    grid: { display: false }
                                },
                                y: {
                                    stacked: false,
                                    beginAtZero: true,
                                    title: {
                                        display: true,
                                        text: 'Total'
                                    },
                                    ticks: {
                                        stepSize: 1
                                    },
                                    max: 7
                                }
                            }
                        }
                    });
                    console.log('Logbook chart created successfully:', logbookChart);
                } catch (error) {
                    console.error('Error creating logbook chart:', error);
                }
            } else {
                console.error('Canvas element logbookChart not found!');
            }

        });

    </script>
</body>

</html>