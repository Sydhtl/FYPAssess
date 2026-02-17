<?php
// Start Session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include '../db_connect.php';

// 1. CAPTURE ROLE & USER ID
// Check if loginID is in session, otherwise default to 'GUEST'
$loginID = isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'USER';
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'assessor';

// 2. PREPARE MODULE TITLE
$moduleTitle = ucfirst($activeRole) . " Module";

// 3. FETCH COURSE INFO
// HARDCODED: Using FYP_Session_ID 1 and 2 for 2024/2025 sessions
$courseCode = "SWE4949";
$courseSession = "2024/2025 - 1";
$latestSessionID = 1; // Hardcoded to session 1

// A. Get Login ID and Role
if (isset($_SESSION['upmId'])) {
    $loginID = $_SESSION['upmId'];
} else {
    $loginID = 'hazura'; // Fallback
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

// B. Lookup Numeric Assessor ID
$currentUserID = null;
if ($activeRole === 'assessor') {
    $stmt = $conn->prepare("SELECT Assessor_ID FROM assessor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc())
        $currentUserID = $row['Assessor_ID'];
}

// ========== FETCH DASHBOARD DATA ==========

// Initialize variables
$inProgressCount = 0;
$upcomingCount = 0;
$nearestDueDays = 0;

// 1. COUNT IN-PROGRESS TASKS (evaluations not submitted by assessor)
// Assessor Assessment IDs: 2 (Proposal Seminar - Course 1), 3 (Seminar Demonstration - Course 2)
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CONCAT(se.Student_ID, '-', a.Assessment_ID)) as count 
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID
        JOIN course c ON s.Course_ID = c.Course_ID
        JOIN assessment a ON c.Course_ID = a.Course_ID
        LEFT JOIN evaluation e ON se.Student_ID = e.Student_ID 
            AND e.Assessor_ID = ? 
            AND e.Assessment_ID = a.Assessment_ID
        WHERE (se.Assessor_ID_1 = ? OR se.Assessor_ID_2 = ?)
        AND se.FYP_Session_ID IN (1, 2)
        AND a.Assessment_ID IN (2, 3)
        AND e.Evaluation_ID IS NULL
    ");
    $stmt->bind_param("iii", $currentUserID, $currentUserID, $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $inProgressCount = $row['count'];
    }
    $stmt->close();

    // 2. COUNT UPCOMING TASKS (assessment sessions scheduled for future)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT ss.Session_ID) as count
        FROM student_enrollment se
        JOIN student_session ss ON se.Student_ID = ss.Student_ID
        JOIN assessment_session asess ON ss.Session_ID = asess.Session_ID
        WHERE (se.Assessor_ID_1 = ? OR se.Assessor_ID_2 = ?)
        AND se.FYP_Session_ID IN (1, 2)
        AND asess.Date >= CURDATE()
    ");
    $stmt->bind_param("ii", $currentUserID, $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $upcomingCount = $row['count'];
    }
    $stmt->close();

    // 3. GET NEAREST EVALUATION DUE DATE
    $stmt = $conn->prepare("
        SELECT DATEDIFF(End_Date, CURDATE()) as days_left
        FROM due_date
        WHERE Role = 'Assessor' 
        AND End_Date >= CURDATE()
        ORDER BY End_Date ASC
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $nearestDueDays = $row['days_left'] . ' days';
    }
    $stmt->close();
}

// FETCH REMINDER DATA - Evaluation deadlines from due_date, custom messages from reminder table
$reminderItems = [];
// Fetch due dates for assessor assessments grouped by date
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
    WHERE dd.Role = 'Assessor'
    AND dd.End_Date >= CURDATE()
    AND dd.FYP_Session_ID IN (1, 2)
    ORDER BY dd.Start_Date ASC, dd.Start_Time ASC
");
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
$stmt = $conn->prepare("SELECT Message, Date FROM reminder WHERE Role = 'Assessor' OR Role IS NULL ORDER BY Date DESC LIMIT 10");
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

// 4. FETCH IN-PROGRESS EVALUATION TASKS
// Assessments that assessor needs to evaluate (Assessment 2 for Course 1, Assessment 3 for Course 2)
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
        LEFT JOIN due_date dd ON a.Assessment_ID = dd.Assessment_ID AND dd.FYP_Session_ID = se.FYP_Session_ID AND dd.Role = 'Assessor'
        LEFT JOIN evaluation e ON s.Student_ID = e.Student_ID 
            AND a.Assessment_ID = e.Assessment_ID 
            AND e.Assessor_ID = ?
        WHERE (se.Assessor_ID_1 = ? OR se.Assessor_ID_2 = ?)
        AND se.FYP_Session_ID IN (1, 2)
        AND a.Assessment_ID IN (2, 3)
        AND e.Evaluation_ID IS NULL
        ORDER BY s.Student_Name, a.Assessment_ID
    ");
    $stmt->bind_param("iii", $currentUserID, $currentUserID, $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $inProgressTasks[] = $row;
    }
    $stmt->close();
}

// 5. FETCH UPCOMING TASKS (Assessment sessions scheduled)
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
        JOIN student_session ss ON s.Student_ID = ss.Student_ID AND se.FYP_Session_ID = ss.FYP_Session_ID
        JOIN assessment_session asess ON ss.Session_ID = asess.Session_ID AND asess.Assessment_ID IN (2, 3)
        JOIN assessment a ON asess.Assessment_ID = a.Assessment_ID
        LEFT JOIN fyp_project pt ON s.Student_ID = pt.Student_ID
        LEFT JOIN assessor_session ases ON asess.Session_ID = ases.Session_ID
        LEFT JOIN assessor ase ON ases.Assessor_ID = ase.Assessor_ID
        LEFT JOIN lecturer l ON ase.Lecturer_ID = l.Lecturer_ID
        WHERE (se.Assessor_ID_1 = ? OR se.Assessor_ID_2 = ?)
        AND se.FYP_Session_ID IN (1, 2)
        AND a.Assessment_ID IN (2, 3)
        AND asess.Date >= CURDATE()
        GROUP BY s.Student_ID, s.Student_Name, pt.Project_Title, a.Assessment_Name, a.Assessment_ID, asess.Date, asess.Time, asess.Venue, asess.Session_ID
        ORDER BY asess.Date, asess.Time
    ");
    $stmt->bind_param("ii", $currentUserID, $currentUserID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
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

// 6. FETCH COMPLETED TASKS (Assessor evaluations already submitted)
$completedTasks = [];
if ($currentUserID) {
    $stmt = $conn->prepare("
        SELECT 
            s.Student_ID,
            s.Student_Name,
            pt.Project_Title,
            a.Assessment_Name,
            SUM(e.Evaluation_Percentage) as Total_Marks,
            MAX(e.Evaluation_ID) as latest_eval
        FROM evaluation e
        JOIN student s ON e.Student_ID = s.Student_ID
        JOIN assessment a ON e.Assessment_ID = a.Assessment_ID
        JOIN course c ON a.Course_ID = c.Course_ID
        LEFT JOIN fyp_project pt ON s.Student_ID = pt.Student_ID
        WHERE e.Assessor_ID = ?
        AND a.Assessment_ID IN (2, 3)
        GROUP BY s.Student_ID, s.Student_Name, pt.Project_Title, a.Assessment_Name
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
    <title>Dashboard</title>
    <link rel="stylesheet" href="../../css/assessor/dashboard.css">
    <link rel="stylesheet" href="../../css/background.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
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
                data-target="#supervisorMenu" onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items">
                <a href="../phpSupervisor/dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="../phpSupervisor/industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Industry Collaboration
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=supervisor" id="evaluationForm"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
                <a href="../phpSupervisor/report.php?role=supervisor" id="superviseesReport"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>
                <a href="../phpSupervisor/logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>
                <a href="../phpSupervisor/signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="../phpSupervisor/project_title.php?role=supervisor" id="projectTitle"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Project Title
                </a>
            </div>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'assessor') ? 'menu-expanded' : ''; ?>"
                data-target="#assessorMenu" onclick="toggleMenu('assessorMenu', this)">
                <span class="role-text">Assessor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>
            <div id="assessorMenu" class="menu-items expanded">
                <a href="../phpAssessor/dashboard.php?role=assessor" id="Dashboard"
                    class="<?php echo ($activeRole == 'assessor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="../phpAssessor/notification.php?role=assessor" id="Notification"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-bell-fill icon-padding"></i> Notification
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
            </div>

            <?php if ($isCoordinator): ?>
                <a href="javascript:void(0)"
                    class="role-header <?php echo ($activeRole == 'coordinator') ? 'menu-expanded' : ''; ?>"
                    data-target="#coordinatorMenu" onclick="toggleMenu('coordinatorMenu', this)">
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

            <a href="#" id="logout">
                <i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout
            </a>
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

    <!-- CRITICAL FIX: Added id="main" here to match the JavaScript code -->
    <div id="main" class="main-grid">
        <!-- Top widgets would go here, maintaining vertical alignment with the section below -->
        <div class="metrics-grid">
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-bars-progress"></i></span>
                <div class="widget-content">
                    <span class="widget-title">In-progress task</span>
                    <span id="in-progress-count" class="widget-value"><?php echo $inProgressCount; ?></span>
                </div>
            </div>
            <div class="widget due-widget">
                <span class="widget-icon"><i class="fa-solid fa-arrow-right"></i></span>
                <div class="widget-content due-content">
                    <span class="widget-title">Upcoming task</span>
                    <span id="due-soon-count" class="widget-value"><?php echo $upcomingCount; ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-clock"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Evaluation due</span>
                    <span id="completed-count" class="widget-value"><?php echo $nearestDueDays; ?></span>
                </div>
            </div>
        </div>
        <!-- Two-Column Layout Section -->
        <div class="task-reminder-section">
            <!-- LEFT COLUMN: Evaluation Task List -->
            <div class="evaluation-area">
                <h1 class="card-title">Evaluation task</h1>
                <div class="evaluation-task-card">
                    <div class="tab-buttons">
                        <button class="task-tab active-tab" data-tab="inprogress">In-progress</button>
                        <button class="task-tab" data-tab="upcoming">Upcoming task</button>
                        <button class="task-tab" data-tab="completed">Completed</button>
                    </div>
                    <div class="task-list-area">

                        <div class="task-group active" data-group="inprogress">
                            <div class="task-row header-row four-col-grid">
                                <span class="col-supervisee">Student</span>
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
                                        <i class="fas fa-chevron-right toggle-icon"></i>
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
                                            // Display assessor names with phone tooltips
                                            $assessorDisplay = 'TBA';
                                            if (!empty($task['Assessor_Names'])) {
                                                $names = explode(', ', $task['Assessor_Names']);
                                                $phones = $task['Assessor_Phones'];
                                                $assessorParts = [];
                                                foreach ($names as $index => $name) {
                                                    $phone = isset($phones[$index]) ? $phones[$index] : 'N/A';
                                                    $assessorParts[] = '<span class="assessor-name" data-phone="' . htmlspecialchars($phone) . '">' . htmlspecialchars($name) . '</span>';
                                                }
                                                $assessorDisplay = implode(', ', $assessorParts);
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

                        <div class="task-group" data-group="completed">
                            <div class="task-row header-row four-col-grid">
                                <span class="col-student">Student</span>
                                <span class="col-project-title">Project title</span>
                                <span class="col-assessment-type">Assessment type</span>
                                <span class="col-marks">Marks</span>
                            </div>
                            <?php if (empty($completedTasks)): ?>
                                <div class="task-row data-row four-col-grid">
                                    <span class="col-student"
                                        style="grid-column: 1 / -1; text-align: center; color: #999;">No
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

            <!-- RIGHT COLUMN: Reminder -->
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
    </div>

    <script>
        // --- State Variables ---
        let selectedStudentId = null;
        let selectedAssessmentKey = null;
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
                    const targetMenu = document.querySelector(menuId);

                    if (!targetMenu) return;
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
                const targetMenu = document.querySelector(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');

                if (!targetMenu) return;
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
                const targetMenu = document.querySelector(menuId);
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
                tab.addEventListener('click', (e) => {
                    console.log('Tab clicked:', e.target);
                    const tabName = e.target.getAttribute('data-tab');
                    console.log('Tab name:', tabName);

                    // 1. Update active tab style
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');

                    // 2. Switch active task group
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            console.log('Activating group:', tabName);
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
                        // 1. Check what the new state will be
                        const isExpanding = !targetList.classList.contains('expanded');

                        // 2. Toggle the content
                        targetList.classList.toggle('expanded', isExpanding);

                        // 3. Toggle the icon classes based on the new state
                        toggleIcon.classList.toggle('fa-chevron-right', !isExpanding); // Add 'right' if it is NOT expanding (i.e., collapsing)
                        toggleIcon.classList.toggle('fa-chevron-down', isExpanding);  // Add 'down' if it IS expanding
                    }
                });
            });

            // Default to 'inprogress' tab view on load
            document.querySelector('.task-group[data-group="inprogress"]').classList.add('active');
        });

    </script>
</body>

</html>