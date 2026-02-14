<?php
include '../../../php/mysqlConnect.php';
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    header("Location: ../../login/Login.php");
    exit();
}
?>
<script>
window.history.pushState(null, "", window.location.href);
window.onpopstate = function() {
    window.history.pushState(null, "", window.location.href);
};
function validateSession() {
    fetch('../../../php/check_session_alive.php')
        .then(function(resp){ return resp.json(); })
        .then(function(data){
            if (!data.valid) {
                window.location.href = '../../login/Login.php';
            }
        })
        .catch(function(err){
            console.warn('Session validation failed:', err);
            window.location.href = '../../login/Login.php';
        });
}
window.addEventListener('load', validateSession);
setInterval(validateSession, 10000);
</script>
<?php
$userId = $_SESSION['upmId'];
$coordinatorName = 'Coordinator';
if ($stmt = $conn->prepare("SELECT Lecturer_Name FROM lecturer WHERE Lecturer_ID = ? LIMIT 1")) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $coordinatorName = $row['Lecturer_Name'] ?: $coordinatorName;
        }
    }
    $stmt->close();
}
$departmentId = null;
if ($stmt = $conn->prepare("SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1")) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $departmentId = $row['Department_ID'];
        }
    }
    $stmt->close();
}
$baseCourseCode = '';
if ($departmentId) {
    if ($stmt = $conn->prepare("SELECT Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code LIMIT 1")) {
        $stmt->bind_param("i", $departmentId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $firstCourseCode = $row['Course_Code'];
                $baseCourseCode = preg_replace('/[-_ ]?[A-Za-z]$/', '', $firstCourseCode);
            }
        }
        $stmt->close();
    }
}
$selectedYear = isset($_GET['year']) ? $_GET['year'] : '';
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : '';
$yearOptions = [];
$yearQuery = "SELECT DISTINCT FYP_Session FROM fyp_session ORDER BY FYP_Session DESC";
if ($yearResult = $conn->query($yearQuery)) {
    while ($row = $yearResult->fetch_assoc()) {
        $yearOptions[] = $row['FYP_Session'];
    }
    $yearResult->free();
}
if (empty($selectedYear) && !empty($yearOptions)) {
    $selectedYear = $yearOptions[0];
}
$semesterOptions = [];
$semesterQuery = "SELECT DISTINCT Semester FROM fyp_session ORDER BY Semester";
if ($semesterResult = $conn->query($semesterQuery)) {
    while ($row = $semesterResult->fetch_assoc()) {
        $semesterOptions[] = $row['Semester'];
    }
    $semesterResult->free();
}
if (empty($selectedSemester) && !empty($semesterOptions)) {
    $selectedSemester = $semesterOptions[0];
}
$fypSessionIds = [];
$fypSessionQuery = "SELECT DISTINCT fs.FYP_Session_ID 
                    FROM fyp_session fs
                    INNER JOIN course c ON fs.Course_ID = c.Course_ID
                    INNER JOIN lecturer l ON c.Department_ID = l.Department_ID
                    WHERE l.Lecturer_ID = ? 
                    AND fs.FYP_Session = ? 
                    AND fs.Semester = ?";
if ($stmt = $conn->prepare($fypSessionQuery)) {
    $stmt->bind_param("ssi", $userId, $selectedYear, $selectedSemester);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $fypSessionIds[] = $row['FYP_Session_ID'];
        }
    }
    $stmt->close();
}
$displayFypSessionId = !empty($fypSessionIds) ? $fypSessionIds[0] : null;
$totalStudents = 0;
if ($displayFypSessionId) {
    $studentCountQuery = "SELECT COUNT(*) as total FROM student WHERE FYP_Session_ID = ?";
    if ($stmt = $conn->prepare($studentCountQuery)) {
        $stmt->bind_param("i", $displayFypSessionId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $totalStudents = (int)$row['total'];
            }
        }
        $stmt->close();
    }
}
$lecturerData = [];
if ($displayFypSessionId) {
    $lecturerQuery = "SELECT 
                        l.Lecturer_ID,
                        l.Lecturer_Name,
                        s.Supervisor_ID,
                        COALESCE(sqh.Quota, 0) as Quota
                      FROM lecturer l
                      INNER JOIN supervisor s ON l.Lecturer_ID = s.Lecturer_ID
                      LEFT JOIN supervisor_quota_history sqh ON s.Supervisor_ID = sqh.Supervisor_ID 
                          AND sqh.FYP_Session_ID = ?
                      WHERE l.Department_ID = (
                          SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ?
                      )
                      ORDER BY l.Lecturer_Name";
    if ($stmt = $conn->prepare($lecturerQuery)) {
        $stmt->bind_param("is", $displayFypSessionId, $userId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $lecturerData[] = [
                    'id' => $row['Supervisor_ID'],
                    'lecturer_id' => $row['Lecturer_ID'],
                    'name' => $row['Lecturer_Name'],
                    'quota' => (int)$row['Quota'],
                    'remaining_quota' => (int)$row['Quota'] 
                ];
            }
        }
        $stmt->close();
    }
}
$assessorData = [];
$assessorQuery = "SELECT a.Assessor_ID, l.Lecturer_ID, l.Lecturer_Name
                  FROM assessor a
                  INNER JOIN lecturer l ON a.Lecturer_ID = l.Lecturer_ID
                  WHERE l.Department_ID = (
                      SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ?
                  )
                  ORDER BY l.Lecturer_Name";
if ($stmt = $conn->prepare($assessorQuery)) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $assessorData[] = [
                'assessor_id' => $row['Assessor_ID'],
                'lecturer_id' => $row['Lecturer_ID'],
                'name' => $row['Lecturer_Name']
            ];
        }
    }
    $stmt->close();
}
$lecturerDataJson = json_encode($lecturerData);
$assessorDataJson = json_encode($assessorData);
$studentsData = [];
$courseFilterOptions = [];
if (!empty($fypSessionIds)) {
    $placeholders = implode(',', array_fill(0, count($fypSessionIds), '?'));
    $studentsQuery = "SELECT 
                          s.Student_ID,
                          s.Student_Name,
                          s.Course_ID,
                          c.Course_Code,
                          s.FYP_Session_ID,
                          lsup.Lecturer_Name AS Supervisor_Name,
                          la1.Lecturer_Name AS Assessor1_Name,
                          la2.Lecturer_Name AS Assessor2_Name
                      FROM student s
                      LEFT JOIN student_enrollment se 
                          ON se.Student_ID = s.Student_ID 
                          AND se.Fyp_Session_ID = s.FYP_Session_ID
                      LEFT JOIN supervisor sup 
                          ON se.Supervisor_ID = sup.Supervisor_ID
                      LEFT JOIN lecturer lsup 
                          ON sup.Lecturer_ID = lsup.Lecturer_ID
                      LEFT JOIN assessor a1 
                          ON se.Assessor_ID_1 = a1.Assessor_ID
                      LEFT JOIN lecturer la1 
                          ON a1.Lecturer_ID = la1.Lecturer_ID
                      LEFT JOIN assessor a2 
                          ON se.Assessor_ID_2 = a2.Assessor_ID
                      LEFT JOIN lecturer la2 
                          ON a2.Lecturer_ID = la2.Lecturer_ID
                      INNER JOIN course c
                          ON s.Course_ID = c.Course_ID
                      WHERE s.FYP_Session_ID IN ($placeholders)
                      AND s.Department_ID = (
                          SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ?
                      )
                      ORDER BY s.Student_Name";
    if ($stmt = $conn->prepare($studentsQuery)) {
        $types = str_repeat('i', count($fypSessionIds)) . 's';
        $params = array_merge($fypSessionIds, [$userId]);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $seenCourses = [];
            while ($row = $result->fetch_assoc()) {
                $courseKey = $row['Course_ID'] . '|' . $row['Course_Code'];
                if (!isset($seenCourses[$courseKey])) {
                    $courseFilterOptions[] = [
                        'Course_ID' => $row['Course_ID'],
                        'Course_Code' => $row['Course_Code']
                    ];
                    $seenCourses[$courseKey] = true;
                }
                $studentsData[] = [
                    'id' => $row['Student_ID'],
                    'name' => $row['Student_Name'],
                    'supervisor' => $row['Supervisor_Name'] ?? null,
                    'assessor1' => $row['Assessor1_Name'] ?? null,
                    'assessor2' => $row['Assessor2_Name'] ?? null,
                    'course_id' => $row['Course_ID'],
                    'course_code' => $row['Course_Code'],
                    'fyp_session_id' => $row['FYP_Session_ID'],
                    'selected' => false
                ];
            }
        }
        $stmt->close();
    }
}
$assessmentData = [];
$assessmentQuery = "SELECT Course_ID, Assessment_Name FROM assessment ORDER BY Course_ID, Assessment_Name";
if ($result = $conn->query($assessmentQuery)) {
    while ($row = $result->fetch_assoc()) {
        $courseId = $row['Course_ID'];
        if (!isset($assessmentData[$courseId])) {
            $assessmentData[$courseId] = [];
        }
        $assessmentData[$courseId][] = $row['Assessment_Name'];
    }
    $result->free();
}
$studentsDataJson = json_encode($studentsData);
$courseFilterOptionsJson = json_encode($courseFilterOptions);
$assessmentDataJson = json_encode($assessmentData);
$assessorDataJson = json_encode($assessorData);
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Student Assignment</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/studentAssignation.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script>
    window.history.pushState(null, "", window.location.href);
    window.onpopstate = function() {
        window.history.pushState(null, "", window.location.href);
    };
    </script>
</head>
<body class="student-assignation-page">
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
                <a href="../../../php/phpSupervisor/industry_collaboration.php" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i>
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
                <a href="../dashboard/dashboardCoordinator.php" id="coordinatorDashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="studentAssignation.php" id="studentAssignation" class="active-menu-item"><i class="bi bi-people-fill icon-padding"></i> Student Assignment</a>
                <a href="../learningObjective/learningObjective.php" id="learningObjective"><i class="bi bi-book-fill icon-padding"></i> Learning Objective</a>
                <a href="../markSubmission/markSubmission.php" id="markSubmission"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Stamp Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Deadline Allocation</a>
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
                <div id="courseCode"><?php echo htmlspecialchars($baseCourseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars(($selectedYear ?? '') . ' - ' . ($selectedSemester ?? '')); ?></div>
            </div>
        </div>
    </div>
    <div id="main" class="main-grid student-assignation-main">
        <h1 class="page-title">Student Assignment Page</h1>
        <!-- Filters -->
        <div class="filters-section">
            <div class="filter-group">
                <label for="yearFilter">Year</label>
                <select id="yearFilter" class="filter-select" onchange="reloadPageWithFilters()">
                    <?php foreach ($yearOptions as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>" 
                                <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="semesterFilter">Semester</label>
                <select id="semesterFilter" class="filter-select" onchange="reloadPageWithFilters()">
                    <?php foreach ($semesterOptions as $semester): ?>
                        <option value="<?php echo htmlspecialchars($semester); ?>" 
                                <?php echo ($semester == $selectedSemester) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($semester); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <!-- Summary Containers -->
        <div class="summary-container">
            <div class="summary-box widget">
                <span class="widget-icon"><i class="fa-solid fa-user-tie"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Total Lecturer</span>
                    <span class="widget-value" id="totalLecturer"><?php echo count($lecturerData); ?></span>
                </div>
            </div>
            <div class="summary-box widget">
                <span class="widget-icon"><i class="fa-solid fa-users"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Total Students</span>
                    <span class="widget-value" id="totalStudents"><?php echo $totalStudents; ?></span>
                </div>
            </div>
        </div>
        <!-- Tabs -->
        <div class="evaluation-task-card">
            <div class="tab-buttons">
                <button class="task-tab active-tab" data-tab="quota">Lecturer Quota Assignation</button>
                <button class="task-tab" data-tab="distribution">Student Distribution</button>
                <button class="task-tab" data-tab="assessment">Assessment Session</button>
            </div>
            <div class="task-list-area">
                <!-- Lecturer Quota Assignation Tab -->
                <div class="task-group active" data-group="quota">
                    <div class="quota-table-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Left Actions: Search -->
                            <div class="left-actions">
                                <div class="search-section">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="lecturerSearch" placeholder="Search lecturer name..." />
                                </div>
                            </div>
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-clear-all" onclick="clearAllQuotas()">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Clear All</span>
                                </button>
                                <button class="btn-assign" onclick="assignRemainingQuota()">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Assign Remaining Automatically</span>
                                </button>
                                <div class="button-group">
                                    <button class="btn btn-outline-dark follow-quota-btn" onclick="followPastQuota()" style="background-color: white; color: black; border-color: black;" onmouseover="this.style.backgroundColor='white'; this.style.color='black';" onmouseout="this.style.backgroundColor='white'; this.style.color='black';">Follow Past Quota</button>
                                    <div class="download-dropdown">
                                        <button class="btn-download" onclick="toggleDownloadDropdown()">
                                            <i class="bi bi-download"></i>
                                            <span>Download as...</span>
                                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                                        </button>
                                        <div class="download-dropdown-menu" id="downloadDropdown">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF(); closeDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel(); closeDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="student-click-hint">
                       The coordinator may manually set the quota for selected lecturers before clicking “Assign Remaining Automatically” to distribute the remaining quotas.
                       <br><br>
                       The coordinator may choose “Assign Remaining Automatically” to automatically allocate all lecturer quotas.
                    </div>
                        </div>
                        <!-- Scrollable Table Container -->
                        <div class="table-scroll-container">
                            <table class="quota-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>Quota</th>
                                        <th>Remaining Quota</th>
                                    </tr>
                                </thead>
                                <tbody id="lecturerTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="remaining-student">
                                Remaining Student: <span id="remainingStudent">129</span>
                            </div>
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetQuotas()">Cancel</button>
                                <button class="btn btn-success" onclick="saveQuotas()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Student Distribution Tab -->
                <div class="task-group" data-group="distribution">
                    <div class="quota-table-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Left Actions: Search + Course Filter -->
                            <div class="left-actions">
                                <div class="search-section">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="studentSearch" placeholder="Search student name..." />
                                </div>
                                <div class="course-filter-section">
                                    <label for="courseFilter" class="filter-label">Course</label>
                                    <div class="download-dropdown course-filter-dropdown">
                                        <button class="btn-download" type="button" onclick="toggleCourseFilterDropdown()">
                                            <span id="courseFilterLabel">Both</span>
                                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                                        </button>
                                        <div class="download-dropdown-menu" id="courseFilterMenu">
                                            <a href="javascript:void(0)" class="download-option" data-course-id="both">
                                                <span>Both</span>
                                            </a>
                                            <?php foreach ($courseFilterOptions as $courseOpt): ?>
                                                <a href="javascript:void(0)" class="download-option" data-course-id="<?php echo htmlspecialchars($courseOpt['Course_ID']); ?>">
                                                    <span><?php echo htmlspecialchars($courseOpt['Course_Code']); ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-clear-all" onclick="clearAllAssignments()">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Clear All</span>
                                </button>
                                <div class="assign-dropdown">
                                    <button class="btn-assign" onclick="toggleAssignDropdown()">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span>Assign Remaining Automatically</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="assign-dropdown-menu" id="assignDropdown">
                                        <a href="javascript:void(0)" onclick="assignAutomatically('supervisor'); closeAssignDropdown();" class="assign-option">
                                            <i class="bi bi-person-check"></i>
                                            <span>Assign Supervisor Only</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="assignAutomatically('assessor'); closeAssignDropdown();" class="assign-option">
                                            <i class="bi bi-people"></i>
                                            <span>Assign Assessor Only</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="assignAutomatically('both'); closeAssignDropdown();" class="assign-option">
                                            <i class="bi bi-arrow-repeat"></i>
                                            <span>Assign Both</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleDistributionDownloadDropdown()">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="distributionDownloadDropdown">
                                        <a href="javascript:void(0)" onclick="downloadDistributionAsPDF(); closeDistributionDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadDistributionAsExcel(); closeDistributionDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Scrollable Table Container -->
                        <div class="table-scroll-container">
                            <table class="quota-table" id="studentDistributionTable">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>Supervisor</th>
                                        <th>Assessor 1</th>
                                        <th>Assessor 2</th>
                                    </tr>
                                </thead>
                                <tbody id="studentTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="remaining-student">
                                Total Students: <span id="totalStudentCount"><?php echo $totalStudents; ?></span>
                            </div>
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetAssignments()">Cancel</button>
                                <button class="btn btn-success" onclick="saveAssignments()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Assessment Session Tab -->
                <div class="task-group" data-group="assessment">
                    <div class="quota-table-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Left Actions: Search + Course Filter -->
                            <div class="left-actions">
                                <div class="search-section">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="assessmentStudentSearch" placeholder="Search student name..." />
                                </div>
                                <div class="course-filter-section">
                                    <label for="assessmentCourseFilter" class="filter-label">Course</label>
                                    <div class="download-dropdown course-filter-dropdown">
                                        <button class="btn-download" type="button" onclick="toggleAssessmentCourseFilterDropdown()">
                                            <span id="assessmentCourseFilterLabel">Both</span>
                                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                                        </button>
                                        <div class="download-dropdown-menu" id="assessmentCourseFilterMenu">
                                            <a href="javascript:void(0)" class="download-option" data-course-id="both">
                                                <span>Both</span>
                                            </a>
                                            <?php foreach ($courseFilterOptions as $courseOpt): ?>
                                                <a href="javascript:void(0)" class="download-option" data-course-id="<?php echo htmlspecialchars($courseOpt['Course_ID']); ?>">
                                                    <span><?php echo htmlspecialchars($courseOpt['Course_Code']); ?></span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="sort-section">
                                    <label for="assessmentSortBy" class="filter-label">Sort By</label>
                                    <div class="download-dropdown course-filter-dropdown">
                                        <button class="btn-download" type="button" onclick="toggleAssessmentDateFilterDropdown()">
                                            <span id="assessmentDateFilterLabel">All Dates</span>
                                            <i class="bi bi-chevron-down dropdown-arrow"></i>
                                        </button>
                                        <div class="download-dropdown-menu" id="assessmentDateFilterMenu">
                                            <a href="javascript:void(0)" class="download-option" data-date-value="">
                                                <span>All Dates</span>
                                            </a>
                                            <!-- Date options will be populated dynamically -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-clear-all" onclick="clearAllAssessmentSessions()">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Clear All</span>
                                </button>
                                <button class="btn-assign" onclick="openAssignAssessmentModal()">
                                    <i class="bi bi-arrow-repeat"></i>
                                    <span>Assign Remaining Automatically</span>
                                </button>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleAssessmentDownloadDropdown()">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="assessmentDownloadDropdown">
                                        <a href="javascript:void(0)" onclick="downloadAssessmentAsPDF(); closeAssessmentDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAssessmentAsExcel(); closeAssessmentDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Scrollable Table Container -->
                        <div class="table-scroll-container">
                            <table class="quota-table" id="assessmentSessionTable">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Student Name</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Venue</th>
                                    </tr>
                                </thead>
                                <tbody id="assessmentTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="remaining-student">
                                Total Students: <span id="assessmentStudentCount"><?php echo $totalStudents; ?></span>
                            </div>
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetAssessmentSessions()">Cancel</button>
                                <button class="btn btn-success" onclick="saveAssessmentSessions()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Loading Modal -->
    <div id="loadingModal" class="custom-modal" style="display: none;">
        <div class="modal-dialog">
            <div class="modal-content-custom">
                <div class="modal-icon" style="color: #007bff;"><i class="bi bi-hourglass-split" style="animation: spin 1s linear infinite; font-size: 48px;"></i></div>
                <div class="modal-title-custom">Processing...</div>
                <div class="modal-message" id="loadingModalMessage">Saving data and sending emails. Please wait.</div>
            </div>
        </div>
    </div>
    <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        #loadingModal .modal-icon i {
            font-size: 48px;
        }
    </style>
    <script>
        function reloadPageWithFilters() {
            const yearFilter = document.getElementById('yearFilter').value;
            const semesterFilter = document.getElementById('semesterFilter').value;
            const params = new URLSearchParams();
            if (yearFilter) params.append('year', yearFilter);
            if (semesterFilter) params.append('semester', semesterFilter);
            window.location.href = 'studentAssignation.php?' + params.toString();
        }
        var collapsedWidth = "60px";
        function openNav() {
            var fullWidth = "220px";
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main"); 
            var menuIcon = document.querySelector(".menu-icon");
            document.getElementById("mySidebar").style.width = fullWidth;
            if (mainContent) mainContent.style.marginLeft = fullWidth;
            if (header) header.style.marginLeft = fullWidth;
            document.getElementById("nameSide").style.display = "block";
            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                if (links[i].classList.contains('role-header') || links[i].id === 'logout') {
                    links[i].style.display = 'flex';
                } else if (links[i].id === 'close') {
                    links[i].style.display = 'flex';
                }
            }
            document.querySelectorAll('.menu-items.expanded a').forEach(a => a.style.display = 'block');
            if (menuIcon) menuIcon.style.display = "none";
        }
        function closeNav() {
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main"); 
            var menuIcon = document.querySelector(".menu-icon");
            sidebar.style.width = collapsedWidth;
            if (mainContent) mainContent.style.marginLeft = collapsedWidth;
            if (header) header.style.marginLeft = collapsedWidth;
            document.getElementById("nameSide").style.display = "none";
            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                links[i].style.display = "none";
            }
            if (menuIcon) menuIcon.style.display = "block";
        }
        const lecturers = <?php echo $lecturerDataJson; ?>;
        let filteredLecturers = [...lecturers];
        const students = <?php echo $studentsDataJson; ?>;
        const courseFilterOptions = <?php echo $courseFilterOptionsJson ?? '[]'; ?>;
        const assessorData = <?php echo $assessorDataJson ?? '[]'; ?>;
        const selectedYear = <?php echo json_encode($selectedYear); ?>;
        const selectedSemester = <?php echo json_encode($selectedSemester); ?>;
        let filteredStudents = [...students];
        let currentCourseFilter = 'both';
        let totalStudents = students.length;

        function followPastQuota() {
            try {
                const year = document.getElementById('yearFilter')?.value || selectedYear;
                const semester = document.getElementById('semesterFilter')?.value || selectedSemester;
                const payload = {
                    year: year,
                    semester: semester,
                    lecturer_ids: (lecturers || []).map(l => l.id)
                };
                const remainingStudentEl = document.getElementById('remainingStudent');
                const oldText = remainingStudentEl ? remainingStudentEl.textContent : '';
                if (remainingStudentEl) {
                    remainingStudentEl.textContent = 'Loading...';
                    remainingStudentEl.style.opacity = '0.6';
                }
                fetch('../../../php/phpCoordinator/fetch_past_quotas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.success) {
                        console.error('Failed to fetch past quotas:', data && data.message);
                        if (remainingStudentEl) {
                            remainingStudentEl.textContent = oldText;
                            remainingStudentEl.style.opacity = '1';
                        }
                        return;
                    }
                    const map = new Map();
                    (data.quotas || []).forEach(q => map.set(String(q.supervisor_id), Number(q.quota)));
                    (lecturers || []).forEach(l => {
                        const q = map.get(String(l.id));
                        if (typeof q === 'number' && !Number.isNaN(q)) {
                            l.quota = q;
                        }
                    });
                    updateAllRemainingQuotas();
                    renderLecturerTable();
                    updateRemainingStudent();
                    if (remainingStudentEl) {
                        remainingStudentEl.style.opacity = '1';
                        remainingStudentEl.style.transition = 'opacity 0.3s ease-in-out';
                    }
                    console.log('Past quotas applied successfully. Remaining students: ' + (remainingStudentEl ? remainingStudentEl.textContent : 'N/A'));
                })
                .catch(err => {
                    console.error('Error fetching past quotas:', err);
                    if (remainingStudentEl) {
                        remainingStudentEl.textContent = oldText;
                        remainingStudentEl.style.opacity = '1';
                    }
                })
                .finally(() => {
                    if (remainingStudentEl) {
                        remainingStudentEl.style.opacity = '1';
                    }
                });
            } catch (e) {
                console.error('followPastQuota() error:', e);
            }
        }
        let openDropdown = null; 
        const assessmentData = <?php echo $assessmentDataJson; ?>;
        const baseVenueOptions = [
            'KP1 Lab',
            'iSpace,Block C',
            'Seminar Room A',
            'Putra Future Classroom',
        ];
        function getAllVenueOptions() {
            const customVenues = JSON.parse(localStorage.getItem('customVenues') || '[]');
            return [...baseVenueOptions, ...customVenues];
        }
        function addCustomVenue(venueName) {
            if (!venueName || venueName.trim() === '') return;
            const customVenues = JSON.parse(localStorage.getItem('customVenues') || '[]');
            if (!customVenues.includes(venueName.trim())) {
                customVenues.push(venueName.trim());
                localStorage.setItem('customVenues', JSON.stringify(customVenues));
            }
        }
        let assessmentSessionData = students.map(student => ({
            id: student.id,
            name: student.name,
            course_id: student.course_id,
            course_code: student.course_code,
            fyp_session_id: student.fyp_session_id,
            supervisor: student.supervisor,
            assessor1: student.assessor1,
            assessor2: student.assessor2,
            date: '',
            time: '',
            venue: '',
            assessment_name: assessmentData[student.course_id] ? assessmentData[student.course_id][0] : 'Assessment'
        }));
        function loadAssessmentSessionsFromDatabase() {
            fetch(`../../../php/phpCoordinator/fetch_assessment_sessions.php?year=${encodeURIComponent(selectedYear)}&semester=${encodeURIComponent(selectedSemester)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.sessions) {
                        data.sessions.forEach(session => {
                            const assessmentStudent = assessmentSessionData.find(s => String(s.id) === String(session.student_id));
                            if (assessmentStudent) {
                                assessmentStudent.date = session.date || '';
                                assessmentStudent.time = session.time || '';
                                assessmentStudent.venue = session.venue || '';
                            }
                        });
                        applyAssessmentFilters();
                        populateDateSortDropdown();
                        renderAssessmentTable();
                    }
                })
                .catch(error => {
                    console.error('Error loading assessment sessions:', error);
                });
        }
        let filteredAssessmentStudents = [...assessmentSessionData];
        let currentAssessmentCourseFilter = 'both'; 
        function showLoadingModal(message) {
            const modal = document.getElementById('loadingModal');
            const messageElement = document.getElementById('loadingModalMessage');
            if (modal) {
                if (message && messageElement) {
                    messageElement.textContent = message;
                }
                modal.style.display = 'flex';
            }
        }
        function hideLoadingModal() {
            const modal = document.getElementById('loadingModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            renderLecturerTable();
            updateRemainingStudent();
            initializeTabs();
            initializeSearch();
            initializeRoleToggle();
            initializeStudentDistribution();
            initializeAssessmentSession();
            loadAssessmentSessionsFromDatabase();
        });
        function renderLecturerTable() {
            const tbody = document.getElementById('lecturerTableBody');
            tbody.innerHTML = '';
            filteredLecturers.forEach((lecturer, index) => {
                const row = document.createElement('tr');
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remaining_quota = lecturer.quota - assignedCount;
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${lecturer.name}</td>
                    <td>
                        <input type="number" 
                               class="quota-input" 
                               value="${lecturer.quota}" 
                               min="0"
                               data-lecturer-id="${lecturer.id}"
                               onchange="updateQuota(${lecturer.id}, this.value)"
                               oninput="updateRemainingStudent()" />
                    </td>
                    <td class="remaining-quota" id="remaining-${lecturer.id}">${lecturer.remaining_quota}</td>
                `;
                tbody.appendChild(row);
            });
        }
        function updateQuota(lecturerId, newQuota) {
            const lecturer = lecturers.find(l => l.id === lecturerId);
            if (lecturer) {
                const quotaValue = parseInt(newQuota) || 0;
                lecturer.quota = quotaValue;
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remaining_quota = quotaValue - assignedCount;
                updateRemainingQuota(lecturerId);
                updateRemainingStudent();
                if (document.querySelector('.task-group[data-group="distribution"].active')) {
                    renderStudentTable();
                }
            }
        }
        function updateRemainingQuota(lecturerId) {
            const lecturer = lecturers.find(l => l.id === lecturerId);
            if (lecturer) {
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remaining_quota = lecturer.quota - assignedCount;
                const element = document.getElementById(`remaining-${lecturerId}`);
                if (element) {
                    element.textContent = lecturer.remaining_quota;
                }
            }
        }
        function updateAllRemainingQuotas() {
            lecturers.forEach(lecturer => {
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remaining_quota = lecturer.quota - assignedCount;
                const element = document.getElementById(`remaining-${lecturer.id}`);
                if (element) {
                    element.textContent = lecturer.remaining_quota;
                }
            });
        }
        function updateRemainingStudent() {
            const totalQuota = lecturers.reduce((sum, lecturer) => sum + (parseInt(lecturer.quota) || 0), 0);
            const remaining = Math.max(0, totalStudents - totalQuota);
            document.getElementById('remainingStudent').textContent = remaining;
        }

        var currentModal = null;
        function openModal(modal) {
            if (modal) {
                modal.style.display = 'block';
                currentModal = modal;
            }
        }
        function closeModal(modal) {
            if (modal) {
                modal.style.display = 'none';
                currentModal = null;
            }
        }
        var assignModal = document.createElement('div');
        assignModal.className = 'custom-modal';
        assignModal.id = 'assignQuotaModal';
        document.body.appendChild(assignModal);
        function openAssignModal() {
            openModal(assignModal);
        }
        function closeAssignModal() {
            closeModal(assignModal);
        }
        var clearAllModal = document.createElement('div');
        clearAllModal.className = 'custom-modal';
        clearAllModal.id = 'clearAllModal';
        document.body.appendChild(clearAllModal);
        function openClearAllModal() {
            openModal(clearAllModal);
        }
        function closeClearAllModal() {
            closeModal(clearAllModal);
        }
        var downloadModal = document.createElement('div');
        downloadModal.className = 'custom-modal';
        downloadModal.id = 'downloadModal';
        document.body.appendChild(downloadModal);
        function openDownloadModal() {
            openModal(downloadModal);
        }
        function closeDownloadModal() {
            closeModal(downloadModal);
        }
        var saveModal = document.createElement('div');
        saveModal.className = 'custom-modal';
        saveModal.id = 'saveModal';
        document.body.appendChild(saveModal);
        function openSaveModal() {
            openModal(saveModal);
        }
        function closeSaveModal() {
            closeModal(saveModal);
        }
        var resetModal = document.createElement('div');
        resetModal.className = 'custom-modal';
        resetModal.id = 'resetModal';
        document.body.appendChild(resetModal);
        function openResetModal() {
            openModal(resetModal);
        }
        function closeResetModal() {
            closeModal(resetModal);
        }

        //start untuk assign quota automatically
        function assignRemainingQuota() {
            const remaining = parseInt(document.getElementById('remainingStudent').textContent);
            const lecturersWithoutQuota = lecturers.filter(lecturer => lecturer.quota === 0);
            if (remaining <= 0) {
                assignModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">No Remaining Students</div>
                            <div class="modal-message">There are no remaining students to assign. Please adjust quotas or total students first.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okNoRemaining" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                assignModal.querySelector('#okNoRemaining').onclick = closeAssignModal;
                openAssignModal();
                return;
            }
            if (lecturersWithoutQuota.length === 0) {
                assignModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">All Lecturers Have Quotas</div>
                            <div class="modal-message">All lecturers already have quotas assigned. Please increase total students or reduce some quotas to assign remaining students.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okAllAssigned" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                assignModal.querySelector('#okAllAssigned').onclick = closeAssignModal;
                openAssignModal();
                return;
            }
            const message = `Assign ${remaining} remaining students to ${lecturersWithoutQuota.length} lecturers without quota?`;
            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModal">&times;</span>
                        <div class="modal-title-custom">Assign Remaining Quota</div>
                        <div class="modal-message">${message}</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelAssign" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmAssign" class="btn btn-success" type="button">Assign</button>
                        </div>
                    </div>
                </div>`;
            assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
            assignModal.querySelector('#cancelAssign').onclick = closeAssignModal;
            assignModal.querySelector('#confirmAssign').onclick = function() {
                performAssignQuota();
            };
            openAssignModal();
        }
        function performAssignQuota() {
            const remaining = parseInt(document.getElementById('remainingStudent').textContent);
            const lecturersWithoutQuota = lecturers.filter(lecturer => lecturer.quota === 0);
            const quotaPerLecturer = Math.floor(remaining / lecturersWithoutQuota.length);
            const remainder = remaining % lecturersWithoutQuota.length;
            let distributed = 0;
            lecturersWithoutQuota.forEach((lecturer, index) => {
                const lecturerIndex = lecturers.findIndex(l => l.id === lecturer.id);
                if (lecturerIndex !== -1) {
                    const quotaToAssign = quotaPerLecturer + (index < remainder ? 1 : 0);
                    lecturers[lecturerIndex].quota = quotaToAssign;
                    lecturers[lecturerIndex].remaining_quota = quotaToAssign;
                    distributed += quotaToAssign;
                    const input = document.querySelector(`input[data-lecturer-id="${lecturer.id}"]`);
                    if (input) {
                        input.value = quotaToAssign;
                    }
                    updateRemainingQuota(lecturer.id);
                }
            });
            updateRemainingStudent();
            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModalSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quota Assigned Successfully</div>
                        <div class="modal-message">Remaining ${distributed} students have been assigned to ${lecturersWithoutQuota.length} lecturers without quota.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okAssigned" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            assignModal.querySelector('#closeAssignModalSuccess').onclick = closeAssignModal;
            assignModal.querySelector('#okAssigned').onclick = closeAssignModal;
        }
//end untuk assign quota automatically

//ni untuk clearkan all quota dekat lecturer

        function clearAllQuotas() {
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllModal">&times;</span>
                        <div class="modal-title-custom">Clear All Quotas</div>
                        <div class="modal-message">Are you sure you want to clear all quotas? All quota assignments will be removed.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelClearAll" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmClearAll" class="btn btn-success" type="button">Clear All</button>
                        </div>
                    </div>
                </div>`;
            clearAllModal.querySelector('#closeClearAllModal').onclick = closeClearAllModal;
            clearAllModal.querySelector('#cancelClearAll').onclick = closeClearAllModal;
            clearAllModal.querySelector('#confirmClearAll').onclick = function() {
                performClearAll();
            };
            openClearAllModal();
        }
        function performClearAll() {
            lecturers.forEach(lecturer => {
                lecturer.quota = 0;
                lecturer.remaining_quota = 0;
                const input = document.querySelector(`input[data-lecturer-id="${lecturer.id}"]`);
                if (input) {
                    input.value = 0;
                }
                updateRemainingQuota(lecturer.id);
            });
            updateRemainingStudent();
            if (document.querySelector('.task-group[data-group="distribution"].active')) {
                renderStudentTable();
            }
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quotas Cleared</div>
                        <div class="modal-message">All quotas have been cleared successfully.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okCleared" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            clearAllModal.querySelector('#closeClearAllSuccess').onclick = closeClearAllModal;
            clearAllModal.querySelector('#okCleared').onclick = closeClearAllModal;
        }

      //habis clear quota 
      
      //ni kalau dah set, tapi tanak save, so tekan cancel.
        function resetQuotas() {
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeResetModal">&times;</span>
                        <div class="modal-title-custom">Reset Quotas</div>
                        <div class="modal-message">Are you sure you want to cancel all changes? This will reset quotas to their previous values.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelReset" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmReset" class="btn btn-success" type="button">Reset</button>
                        </div>
                    </div>
                </div>`;
            resetModal.querySelector('#closeResetModal').onclick = closeResetModal;
            resetModal.querySelector('#cancelReset').onclick = closeResetModal;
            resetModal.querySelector('#confirmReset').onclick = function() {
                location.reload();
            };
            openResetModal();
        }
        //sampai sini je



        //ni download as pdf and excel
        function downloadAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                doc.setFont('helvetica');
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0); 
                doc.text('Lecturer Quota Assignation Report', 14, 20);
                const courseLabel = getCurrentCourseLabel();
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0); 
                doc.text(`Year: ${selectedYear}    Semester: ${selectedSemester}`, 14, 30);
                doc.text(`Course: ${courseLabel}`, 14, 36);
                doc.text(`Total Lecturer: ${lecturers.length}`, 14, 42);
                doc.text(`Total Students (all courses): ${totalStudents}`, 14, 48);
                doc.text(`Remaining Students: ${document.getElementById('remainingStudent').textContent}`, 14, 54);
                const tableData = lecturers.map((lecturer, index) => [
                    index + 1,
                    lecturer.name,
                    String(lecturer.quota != null ? lecturer.quota : 0),
                    String(lecturer.remaining_quota != null ? lecturer.remaining_quota : 0)
                ]);
                doc.autoTable({
                    startY: 60,
                    head: [['No.', 'Name', 'Quota', 'Remaining Quota']],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [120, 0, 0], 
                        textColor: [255, 255, 255], 
                        fontStyle: 'bold',
                        fontSize: 11
                    },
                    bodyStyles: {
                        fontSize: 10
                    },
                    alternateRowStyles: {
                        fillColor: [253, 240, 213] 
                    },
                    styles: {
                        cellPadding: 5,
                        fontSize: 10
                    },
                    columnStyles: {
                        0: { cellWidth: 20, halign: 'center' },
                        1: { cellWidth: 'auto', halign: 'left' },
                        2: { cellWidth: 30, halign: 'center' },
                        3: { cellWidth: 50, halign: 'center' }
                    }
                });
                const finalY = doc.lastAutoTable.finalY;
                doc.setFontSize(9);
                doc.setTextColor(128, 128, 128); 
                const now = new Date();
                const dateTime = now.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                doc.text(`Generated on: ${dateTime}`, 14, finalY + 15);
                doc.save('lecturer-quota-assignation.pdf');
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="modal-title-custom">Download Successful</div>
                            <div class="modal-message">PDF file downloaded successfully!</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            } catch (error) {
                console.error('Error generating PDF:', error);
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">Download Failed</div>
                            <div class="modal-message">An error occurred while generating the PDF. Please try again.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            }
        }
        function downloadAsExcel() {
            const tableData = lecturers.map((lecturer, index) => ({
                no: index + 1,
                name: lecturer.name,
                quota: lecturer.quota,
                remaining: lecturer.remainingQuota
            }));
            const courseLabel = getCurrentCourseLabel();
            let csvContent = '';
            csvContent += `Year,${selectedYear}\n`;
            csvContent += `Semester,${selectedSemester}\n`;
            csvContent += `Course,${courseLabel}\n`;
            csvContent += `Total Lecturer,${lecturers.length}\n`;
            csvContent += `Total Students (all courses),${totalStudents}\n`;
            csvContent += `Remaining Students,${document.getElementById('remainingStudent').textContent}\n\n`;
            csvContent += 'No.,Name,Quota,Remaining Quota\n';
            tableData.forEach(row => {
                csvContent += `${row.no},"${row.name}",${row.quota},${row.remaining}\n`;
            });
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'lecturer-quota-assignation.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeDownloadModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Download Successful</div>
                        <div class="modal-message">Excel file (CSV) downloaded successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okDownload" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
            downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
            openDownloadModal();
        }

        //ni bila tekan save . so nanti dia save la quota tu
        function saveQuotas() {
            const year = document.getElementById('yearFilter').value;
            const semester = document.getElementById('semesterFilter').value;
            const quotaData = lecturers.map(lecturer => ({
                supervisor_id: lecturer.id,
                quota: lecturer.quota
            }));
            const requestData = {
                year: year,
                semester: semester,
                quotas: quotaData
            };
            fetch('../../../php/phpCoordinator/save_quotas.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAllRemainingQuotas();
                    if (document.querySelector('.task-group[data-group="distribution"].active')) {
                        renderStudentTable();
                    }
                    renderLecturerTable();
                    showSaveSuccess(data.is_latest);
                } else {
                    showSaveError(data.message || 'Failed to save quotas');
                }
            })
            .catch(error => {
                console.error('Error saving quotas:', error);
                showSaveError('An error occurred while saving quotas. Please try again.');
            });
        }
        function showSaveSuccess(isLatest) {
            let message = 'Quotas saved successfully!';
            if (isLatest) {
                message += ' The supervisor table has been updated with the latest quotas.';
            } else {
                message += ' Quotas have been saved to history.';
            }
            saveModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeSaveModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quotas Saved</div>
                        <div class="modal-message">${message}</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okSave" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            saveModal.querySelector('#closeSaveModal').onclick = closeSaveModal;
            saveModal.querySelector('#okSave').onclick = closeSaveModal;
            openSaveModal();
        }
        function showSaveError(errorMessage) {
            saveModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeSaveModal">&times;</span>
                         <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quotas Saved</div>
                        <div class="modal-message">${errorMessage}</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okSave" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            saveModal.querySelector('#closeSaveModal').onclick = closeSaveModal;
            saveModal.querySelector('#okSave').onclick = closeSaveModal;
            openSaveModal();
        }

        //sini dah habis save quota


        function initializeTabs() {
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.getAttribute('data-tab');
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            group.classList.add('active');
                            if (tabName === 'assessment') {
                                syncAssessmentSessionData();
                                loadAssessmentSessionsFromDatabase();
                            }
                        } else {
                            group.classList.remove('active');
                        }
                    });
                    closeAllDropdowns();
                });
            });
        }
        function closeAllDropdowns() {
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.btn-download.active').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.assign-dropdown-menu.show').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.assign-dropdown .btn-assign.active').forEach(btn => btn.classList.remove('active'));
            closeAssessmentDateFilterDropdown();
            if (openDropdown) {
                const openDropdownElement = document.getElementById(openDropdown);
                if (openDropdownElement) {
                    openDropdownElement.classList.remove('show');
                    openDropdown = null;
                }
            }
        }
        function initializeSearch() {
            const searchInput = document.getElementById('lecturerSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    if (searchTerm === '') {
                        filteredLecturers = [...lecturers];
                    } else {
                        filteredLecturers = lecturers.filter(lecturer => 
                            lecturer.name.toLowerCase().includes(searchTerm)
                        );
                    }
                    renderLecturerTable();
                });
            }
        }

        //ni untuk awal awal tekan student distributioin
        function initializeStudentDistribution() {
            updateAllRemainingQuotas();
            currentCourseFilter = 'both';
            applyStudentFilters();
            initializeStudentSearch();
            updateTotalStudentCount();
            attachDropdownEventListeners();
            const courseFilterMenu = document.getElementById('courseFilterMenu');
            const courseFilterLabel = document.getElementById('courseFilterLabel');
            if (courseFilterMenu && courseFilterLabel) {
                courseFilterMenu.addEventListener('click', function(e) {
                    const option = e.target.closest('.download-option');
                    if (!option) return;
                    const courseId = option.getAttribute('data-course-id') || 'both';
                    const labelText = (option.querySelector('span')?.textContent || 'Both').trim();
                    currentCourseFilter = courseId;
                    courseFilterLabel.textContent = labelText;
                    applyStudentFilters();
                    closeCourseFilterDropdown();
                });
            }
        }
        function getCurrentCourseLabel() {
            if (!courseFilterOptions || !Array.isArray(courseFilterOptions)) {
                return 'Both';
            }
            if (currentCourseFilter === 'both') {
                if (courseFilterOptions.length === 0) return 'Both';
                const codes = courseFilterOptions.map(c => c.Course_Code).filter(Boolean);
                if (codes.length > 1) {
                    return 'Both (' + codes.join(', ') + ')';
                }
                return codes[0] || 'Both';
            }
            const match = courseFilterOptions.find(c => String(c.Course_ID) === String(currentCourseFilter));
            if (match) {
                return match.Course_Code || ('Course ' + currentCourseFilter);
            }
            return 'Course ' + currentCourseFilter;
        }


        function applyStudentFilters() {
            const courseIdFilter = currentCourseFilter;
            const searchInput = document.getElementById('studentSearch');
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            filteredStudents = students.filter(student => {
                if (courseIdFilter !== 'both') {
                    if (String(student.course_id) !== String(courseIdFilter)) {
                        return false;
                    }
                }
                if (searchTerm) {
                    return (student.name || '').toLowerCase().includes(searchTerm);
                }
                return true;
            });
            renderStudentTable();
        }


        function toggleCourseFilterDropdown() {
            const dropdown = document.getElementById('courseFilterMenu');
            const button = document.querySelector('.course-filter-dropdown .btn-download');
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.btn-download.active').forEach(btn => {
                if (btn !== button) {
                    btn.classList.remove('active');
                }
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeCourseFilterDropdown() {
            const dropdown = document.getElementById('courseFilterMenu');
            const button = document.querySelector('.course-filter-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        function renderStudentTable() {
            const tbody = document.getElementById('studentTableBody');
            if (!tbody) {
                console.error('Student table body not found');
                return;
            }
            tbody.innerHTML = '';
            if (!filteredStudents || filteredStudents.length === 0) {
                console.warn('No students to render');
                return;
            }
            filteredStudents.forEach((student, index) => {
                const row = document.createElement('tr');
                const studentId = student.id;
                const supervisorName = student.supervisor || 'Select Supervisor';
                const assessor1Name = student.assessor1 || 'Select Assessor 1';
                const assessor2Name = student.assessor2 || 'Select Assessor 2';
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${student.name || 'Unknown'}</td>
                    <td>
                        <div class="custom-dropdown">
                            <button class="dropdown-btn" type="button" onclick="toggleLecturerDropdown('${studentId}', 'supervisor')">
                                <span class="dropdown-text">${supervisorName}</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-supervisor-${studentId}">
                                <div class="dropdown-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search lecturer..." oninput="filterDropdownLecturers('${studentId}', 'supervisor', this.value)" />
                                </div>
                                <div class="dropdown-options" id="options-supervisor-${studentId}">
                                    ${generateLecturerOptions(studentId, 'supervisor', null)}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="custom-dropdown">
                            <button class="dropdown-btn" type="button" onclick="toggleLecturerDropdown('${studentId}', 'assessor1')">
                                <span class="dropdown-text">${assessor1Name}</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-assessor1-${studentId}">
                                <div class="dropdown-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search lecturer..." oninput="filterDropdownLecturers('${studentId}', 'assessor1', this.value)" />
                                </div>
                                <div class="dropdown-options" id="options-assessor1-${studentId}">
                                    ${generateLecturerOptions(studentId, 'assessor1', student.supervisor)}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="custom-dropdown">
                            <button class="dropdown-btn" type="button" onclick="toggleLecturerDropdown('${studentId}', 'assessor2')">
                                <span class="dropdown-text">${assessor2Name}</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-assessor2-${studentId}">
                                <div class="dropdown-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search lecturer..." oninput="filterDropdownLecturers('${studentId}', 'assessor2', this.value)" />
                                </div>
                                <div class="dropdown-options" id="options-assessor2-${studentId}">
                                    ${generateLecturerOptions(studentId, 'assessor2', student.supervisor)}
                                </div>
                            </div>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
            attachDropdownEventListeners();
        }
        function attachDropdownEventListeners() {
            const tbody = document.getElementById('studentTableBody');
            if (tbody) {
                tbody.removeEventListener('click', handleDropdownOptionClick);
                tbody.addEventListener('click', handleDropdownOptionClick);
            }
        }
        function handleDropdownOptionClick(e) {
            const option = e.target.closest('.dropdown-option');
            if (option && !option.classList.contains('disabled')) {
                const studentId = option.getAttribute('data-student-id');
                const role = option.getAttribute('data-role');
                const lecturerName = option.getAttribute('data-lecturer-name');
                if (studentId && role && lecturerName) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectLecturer(studentId, role, lecturerName);
                }
            }
        }

        // to show only thjose with remaining quota
        function generateLecturerOptions(studentId, role, excludeSupervisor) {
            const student = students.find(s => s.id === studentId);
            if (!student) {
                console.error('Student not found:', studentId);
                return '<div class="dropdown-option disabled">Student not found</div>';
            }
            if (!lecturers || lecturers.length === 0) {
                console.error('No lecturers available');
                return '<div class="dropdown-option disabled">No lecturers available</div>';
            }
            let options = '';
            let optionCount = 0;
            lecturers.forEach(lecturer => {
                if (!lecturer || !lecturer.name) {
                    return; 
                }
                if (role === 'supervisor') {
                    const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                    const currentRemainingQuota = Math.max(0, lecturer.quota - assignedCount);
                    if (lecturer.quota <= 0 && student.supervisor !== lecturer.name) {
                        return; 
                    }
                    if (currentRemainingQuota <= 0 && student.supervisor !== lecturer.name) {
                        return; 
                    }
                }
                if (excludeSupervisor && lecturer.name === excludeSupervisor) {
                    return;
                }
                if (role === 'assessor1' && student.assessor2 === lecturer.name) {
                    return;
                }
                if (role === 'assessor2' && student.assessor1 === lecturer.name) {
                    return;
                }
                if (role === 'supervisor' && (student.assessor1 === lecturer.name || student.assessor2 === lecturer.name)) {
                    return;
                }
                const isSelected = 
                    (role === 'supervisor' && student.supervisor === lecturer.name) ||
                    (role === 'assessor1' && student.assessor1 === lecturer.name) ||
                    (role === 'assessor2' && student.assessor2 === lecturer.name);
                const escapedName = lecturer.name.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                options += `
                    <div class="dropdown-option ${isSelected ? 'selected' : ''}" 
                         data-student-id="${studentId}"
                         data-role="${role}"
                         data-lecturer-name="${lecturer.name.replace(/"/g, '&quot;')}"
                         style="cursor: pointer;">
                        ${lecturer.name}
                    </div>
                `;
                optionCount++;
            });
            if (options === '' || optionCount === 0) {
                options = '<div class="dropdown-option disabled">No options available</div>';
            }
            return options;
        }



        //ni nak pastikan dreopdown tak rosak
        function toggleLecturerDropdown(studentId, role) {
            const studentIdStr = String(studentId);
            const dropdownId = `dropdown-${role}-${studentIdStr}`;
            const dropdown = document.getElementById(dropdownId);
            if (!dropdown) {
                console.error('Dropdown not found:', dropdownId);
                return;
            }
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });
            const isOpening = !dropdown.classList.contains('show');
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                openDropdown = dropdownId;
                const customDropdown = dropdown.closest('.custom-dropdown');
                const button = customDropdown ? customDropdown.querySelector('.dropdown-btn') : null;
                if (button) {
                    const rect = button.getBoundingClientRect();
                    const tableContainer = button.closest('.table-scroll-container');
                    const containerRect = tableContainer ? tableContainer.getBoundingClientRect() : null;
                    dropdown.style.position = 'fixed';
                    const maxDropdownHeight = 300; 
                    const spaceBelow = window.innerHeight - rect.bottom;
                    const spaceAbove = rect.top;
                    const gap = 4; 
                    const minRequiredSpace = maxDropdownHeight + gap + 10; 
                    let positionAbove = false;
                    if (spaceBelow < minRequiredSpace && spaceAbove >= minRequiredSpace) {
                        positionAbove = true;
                        dropdown.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
                        dropdown.style.top = 'auto';
                    } else {
                        dropdown.style.top = (rect.bottom + gap) + 'px';
                        dropdown.style.bottom = 'auto';
                    }
                    const minDropdownWidth = window.innerWidth <= 576 ? 150 : 200;
                    const maxDropdownWidth = 400;
                    const padding = 10; 
                    let leftPosition = rect.left;
                    let dropdownWidth = Math.max(rect.width, minDropdownWidth);
                    if (containerRect) {
                        const containerLeft = containerRect.left + padding;
                        const containerRight = containerRect.right - padding;
                        const maxWidthInContainer = containerRight - containerLeft;
                        dropdownWidth = Math.min(dropdownWidth, maxWidthInContainer, maxDropdownWidth);
                    } else {
                        dropdownWidth = Math.min(dropdownWidth, maxDropdownWidth);
                    }
                    dropdownWidth = Math.min(dropdownWidth, window.innerWidth - (padding * 2));
                    const rightEdge = leftPosition + dropdownWidth;
                    const screenRight = containerRect ? containerRect.right - padding : window.innerWidth - padding;
                    const screenLeft = containerRect ? containerRect.left + padding : padding;
                    if (rightEdge > screenRight) {
                        leftPosition = screenRight - dropdownWidth;
                    }
                    if (leftPosition < screenLeft) {
                        leftPosition = screenLeft;
                        if (leftPosition + dropdownWidth > screenRight) {
                            dropdownWidth = screenRight - leftPosition;
                        }
                    }
                    dropdown.style.left = leftPosition + 'px';
                    dropdown.style.width = dropdownWidth + 'px';
                    dropdown.style.minWidth = dropdownWidth + 'px';
                    dropdown.style.maxWidth = dropdownWidth + 'px';
                }
                //sam,pai sini dahh

                updateAllRemainingQuotas();
                const student = students.find(s => String(s.id) === studentIdStr || s.id === studentId);
                const excludeSupervisor = role === 'supervisor' ? null : (student ? student.supervisor : null);
                const optionsContainer = document.getElementById(`options-${role}-${studentIdStr}`);
                if (optionsContainer) {
                    const newOptions = generateLecturerOptions(studentId, role, excludeSupervisor);
                    optionsContainer.innerHTML = newOptions;
                } else {
                    console.error('Options container not found:', `options-${role}-${studentIdStr}`);
                }
                const searchInput = dropdown.querySelector('.dropdown-search input');
                if (searchInput) {
                    searchInput.value = '';
                    filterDropdownLecturers(studentId, role, '');
                }
            } else {
                openDropdown = null;
                dropdown.style.position = '';
                dropdown.style.top = '';
                dropdown.style.left = '';
                dropdown.style.width = '';
            }
        }
        function filterDropdownLecturers(studentId, role, searchTerm) {
            const optionsContainer = document.getElementById(`options-${role}-${studentId}`);
            if (!optionsContainer) return;
            const searchLower = searchTerm.toLowerCase();
            const options = optionsContainer.querySelectorAll('.dropdown-option');
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(searchLower)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        //ni untuk assign lecturer dekat student sendiri
        function selectLecturer(studentId, role, lecturerName) {
            const studentIdStr = String(studentId);
            const student = students.find(s => String(s.id) === studentIdStr || s.id === studentId);
            if (!student) {
                console.error('Student not found:', studentId);
                return;
            }
            if (!lecturerName) {
                console.error('Lecturer name is empty');
                return;
            }
            const previousSupervisor = student.supervisor;
            if (role === 'supervisor') {
                student.supervisor = lecturerName;
                if (student.assessor1 === lecturerName) {
                    student.assessor1 = null;
                }
                if (student.assessor2 === lecturerName) {
                    student.assessor2 = null;
                }
            } else if (role === 'assessor1') {
                if (student.supervisor === lecturerName) {
                    return;
                }
                if (student.assessor2 === lecturerName) {
                    return;
                }
                student.assessor1 = lecturerName;
            } else if (role === 'assessor2') {
                if (student.supervisor === lecturerName) {
                    return;
                }
                if (student.assessor1 === lecturerName) {
                    return;
                }
                student.assessor2 = lecturerName;
            }
            updateAllRemainingQuotas();
            const dropdownId = `dropdown-${role}-${studentId}`;
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.remove('show');
                openDropdown = null;
            }
            renderStudentTable();
        }
        function clearAllAssignments() {
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllModal">&times;</span>
                        <div class="modal-title-custom">Clear All Assignments</div>
                        <div class="modal-message">Are you sure you want to clear all supervisor and assessor assignments? All assignments will be removed.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelClearAll" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmClearAll" class="btn btn-success" type="button">Clear All</button>
                        </div>
                    </div>
                </div>`;
            clearAllModal.querySelector('#closeClearAllModal').onclick = closeClearAllModal;
            clearAllModal.querySelector('#cancelClearAll').onclick = closeClearAllModal;
            clearAllModal.querySelector('#confirmClearAll').onclick = function() {
                performClearAllAssignments();
            };
            openClearAllModal();
        }
        function performClearAllAssignments() {
            students.forEach(student => {
                student.supervisor = null;
                student.assessor1 = null;
                student.assessor2 = null;
            });
            console.log('All assignments cleared. Call Save to update database.');
            updateAllRemainingQuotas();
            renderStudentTable();
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assignments Cleared</div>
                        <div class="modal-message">All supervisor and assessor assignments have been cleared successfully.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okCleared" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            clearAllModal.querySelector('#closeClearAllSuccess').onclick = closeClearAllModal;
            clearAllModal.querySelector('#okCleared').onclick = closeClearAllModal;
        }

        //ni assign auto untuk student yang tak ada supervisor or assessor
        function assignAutomatically(type) {
            let message = '';
            if (type === 'supervisor') {
                message = 'This will automatically assign remaining supervisors to students. Continue?';
            } else if (type === 'assessor') {
                message = 'This will automatically assign remaining assessors to students. Continue?';
            } else {
                message = 'This will automatically assign remaining supervisors and assessors.  Continue?';
            }
            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModal">&times;</span>
                        <div class="modal-title-custom">Assign Remaining Automatically</div>
                        <div class="modal-message">${message}</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelAssign" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmAssign" class="btn btn-success" type="button">Assign</button>
                        </div>
                    </div>
                </div>`;
            assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
            assignModal.querySelector('#cancelAssign').onclick = closeAssignModal;
            assignModal.querySelector('#confirmAssign').onclick = function() {
                performAutoAssign(type);
            };
            openAssignModal();
        }
        function performAutoAssign(type) {
            const lecturersWithQuota = lecturers.filter(l => l.quota > 0);
            const allLecturers = lecturers.map(l => l.name);
            if (lecturersWithQuota.length === 0 && (type === 'supervisor' || type === 'both')) {
                assignModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">No Lecturers with Quota</div>
                            <div class="modal-message">No lecturers have assigned quotas. Please assign quotas first in the "Lecturer Quota Assignation" tab.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okAssign" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                assignModal.querySelector('#okAssign').onclick = closeAssignModal;
                return;
            }

            //sini start round robin 
            if (type === 'supervisor' || type === 'both') {
                updateAllRemainingQuotas();
                let supervisorsWithRemaining = lecturersWithQuota
                    .map(l => ({
                        ref: l,
                        remaining: l.remaining_quota || 0
                    }))
                    .filter(x => x.remaining > 0);
                if (supervisorsWithRemaining.length === 0) {
                    assignModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content-custom">
                                <span class="close-btn" id="closeAssignModal">&times;</span>
                                <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                                <div class="modal-title-custom">No Remaining Supervisor Quota</div>
                                <div class="modal-message">All lecturers' supervisor quotas have been fully used. Please increase quotas in the Lecturer Quota Assignation tab.</div>
                                <div style="display:flex; justify-content:center;">
                                    <button id="okAssign" class="btn btn-success" type="button">OK</button>
                                </div>
                            </div>
                        </div>`;
                    assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                    assignModal.querySelector('#okAssign').onclick = closeAssignModal;
                    return;
                }
                const studentsNeedingSupervisor = students.filter(s => !s.supervisor);
                let supIndex = 0;
                studentsNeedingSupervisor.forEach(student => {
                    let attempts = 0;
                    let assigned = false;
                    while (attempts < supervisorsWithRemaining.length) {
                        const idx = supIndex % supervisorsWithRemaining.length;
                        const supEntry = supervisorsWithRemaining[idx];
                        if (supEntry.remaining > 0) {
                            student.supervisor = supEntry.ref.name;
                            supEntry.remaining -= 1;
                            assigned = true;
                            supIndex++;
                            break;
                        } else {
                            supIndex++;
                        }
                        attempts++;
                    }
                    if (!assigned) {
                        return;
                    }
                });
                updateAllRemainingQuotas();
            }

            //group based assignments for assessors
            if (type === 'assessor' || type === 'both') {
                let lecturerGroups = [];
                let studentGroups = [[], [], []]; 
                if (lecturersWithQuota.length >= 3) {
                    const groupSize = Math.floor(lecturersWithQuota.length / 3);
                    for (let i = 0; i < 3; i++) {
                        const start = i * groupSize;
                        const end = i === 2 ? lecturersWithQuota.length : (i + 1) * groupSize;
                        lecturerGroups.push(lecturersWithQuota.slice(start, end).map(l => l.name));
                    }
                } else {
                    for (let i = 0; i < lecturersWithQuota.length; i++) {
                        lecturerGroups.push([lecturersWithQuota[i].name]);
                    }
                    while (lecturerGroups.length < 3) {
                        lecturerGroups.push([]);
                    }
                }
                students.forEach((student, index) => {
                    if (student.supervisor) {
                        let supervisorGroupIndex = -1;
                        for (let i = 0; i < lecturerGroups.length; i++) {
                            if (lecturerGroups[i].includes(student.supervisor)) {
                                supervisorGroupIndex = i;
                                break;
                            }
                        }
                        const groupIndex = supervisorGroupIndex >= 0 ? supervisorGroupIndex : (index % 3);
                        studentGroups[groupIndex].push(student);
                    } else {
                        studentGroups[index % 3].push(student);
                    }
                });
                studentGroups.forEach((group, groupIndex) => {
                    const otherGroupIndices = [0, 1, 2].filter(idx => idx !== groupIndex);
                    const assessorGroup1 = lecturerGroups[otherGroupIndices[0]] || [];
                    const assessorGroup2 = lecturerGroups[otherGroupIndices[1]] || [];
                    group.forEach((student, studentIndexInGroup) => {
                        if (assessorGroup1.length > 0 && assessorGroup2.length > 0) {
                            const assessor1Index = studentIndexInGroup % assessorGroup1.length;
                            const assessor2Index = studentIndexInGroup % assessorGroup2.length;
                            student.assessor1 = assessorGroup1[assessor1Index];
                            student.assessor2 = assessorGroup2[assessor2Index];
                        } else if (assessorGroup1.length > 0) {
                            const assessor1Index = studentIndexInGroup % assessorGroup1.length;
                            student.assessor1 = assessorGroup1[assessor1Index];
                            let availableForAssessor2 = allLecturers.filter(l => 
                                l !== student.supervisor && l !== student.assessor1
                            );
                            if (availableForAssessor2.length > 0) {
                                student.assessor2 = availableForAssessor2[studentIndexInGroup % availableForAssessor2.length];
                            } else {
                                student.assessor2 = null;
                            }
                        } else if (assessorGroup2.length > 0) {
                            const assessor2Index = studentIndexInGroup % assessorGroup2.length;
                            student.assessor2 = assessorGroup2[assessor2Index];
                            let availableForAssessor1 = allLecturers.filter(l => 
                                l !== student.supervisor && l !== student.assessor2
                            );
                            if (availableForAssessor1.length > 0) {
                                student.assessor1 = availableForAssessor1[studentIndexInGroup % availableForAssessor1.length];
                            } else {
                                student.assessor1 = null;
                            }
                        } else {
                            let availableLecturers = allLecturers.filter(l => l !== student.supervisor);
                            if (availableLecturers.length === 0) {
                                student.assessor1 = null;
                                student.assessor2 = null;
                            } else if (availableLecturers.length === 1) {
                                student.assessor1 = availableLecturers[0];
                                student.assessor2 = null;
                            } else {
                                const assessor1Index = studentIndexInGroup % availableLecturers.length;
                                let assessor2Index = (studentIndexInGroup + 1) % availableLecturers.length;
                                if (assessor2Index === assessor1Index) {
                                    assessor2Index = (studentIndexInGroup + 2) % availableLecturers.length;
                                }
                                student.assessor1 = availableLecturers[assessor1Index];
                                if (assessor2Index !== assessor1Index) {
                                    student.assessor2 = availableLecturers[assessor2Index];
                                } else {
                                    const otherLecturers = availableLecturers.filter((_, idx) => idx !== assessor1Index);
                                    student.assessor2 = otherLecturers.length > 0 ? otherLecturers[0] : null;
                                }
                            }
                        }
                    });
                });
            }

            //sampai sini dah habis group based assignment
            updateAllRemainingQuotas();
            renderStudentTable();
            let successMessage = '';
            if (type === 'supervisor') {
                successMessage = 'Remaining supervisors have been automatically assigned to students. ';
            } else if (type === 'assessor') {
                successMessage = 'Remaining assessors have been automatically assigned. ';
            } else {
                successMessage = 'Remaining supervisors and assessors have been automatically assigned. ';
            }
            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModalSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assignment Completed</div>
                        <div class="modal-message">${successMessage}</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okAssigned" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            assignModal.querySelector('#closeAssignModalSuccess').onclick = closeAssignModal;
            assignModal.querySelector('#okAssigned').onclick = closeAssignModal;
        }
        function initializeStudentSearch() {
            const searchInput = document.getElementById('studentSearch');
            if (!searchInput) return;
            searchInput.addEventListener('input', function() {
                applyStudentFilters();
            });
        }
        function updateTotalStudentCount() {
            const countElement = document.getElementById('totalStudentCount');
            if (countElement) {
                countElement.textContent = filteredStudents.length;
            }
            const totalTop = document.getElementById('totalStudents');
            if (totalTop) {
                totalTop.textContent = totalStudents;
            }
        }
        function resetAssignments() {
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeResetModal">&times;</span>
                        <div class="modal-title-custom">Reset Assignments</div>
                        <div class="modal-message">Are you sure you want to cancel all changes? This will reset all assignments to their previous values.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelReset" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmReset" class="btn btn-success" type="button">Reset</button>
                        </div>
                    </div>
                </div>`;
            resetModal.querySelector('#closeResetModal').onclick = closeResetModal;
            resetModal.querySelector('#cancelReset').onclick = closeResetModal;
            resetModal.querySelector('#confirmReset').onclick = function() {
                location.reload();
            };
            openResetModal();
        }
        function getSupervisorIdByName(lecturerName) {
            if (!lecturerName || !lecturers || lecturers.length === 0) {
                console.warn('getSupervisorIdByName: Missing lecturer name or lecturers array');
                return null;
            }
            const match = lecturers.find(l => l && l.name === lecturerName);
            if (!match) {
                console.warn(`getSupervisorIdByName: No match found for "${lecturerName}". Available lecturers: ${lecturers.map(l => l.name).join(', ')}`);
                return null;
            }
            console.log(`getSupervisorIdByName: Found "${lecturerName}" -> Supervisor_ID: ${match.id}`);
            return match ? match.id : null;
        }
        function getAssessorIdByName(lecturerName) {
            if (!lecturerName || !assessorData || assessorData.length === 0) return null;
            const match = assessorData.find(a => a && a.name === lecturerName);
            return match ? match.assessor_id : null;
        }


        function saveAssignments() {
            const assignmentData = students.map(student => {
                const supervisorId = student.supervisor ? getSupervisorIdByName(student.supervisor) : null;
                const assessor1Id = student.assessor1 ? getAssessorIdByName(student.assessor1) : null;
                const assessor2Id = student.assessor2 ? getAssessorIdByName(student.assessor2) : null;
                console.log(`Student: ${student.id}, Supervisor: ${student.supervisor || 'NULL'}, Supervisor_ID: ${supervisorId || 'NULL'}`);
                console.log(`  Assessor1: ${student.assessor1 || 'NULL'}, Assessor1_ID: ${assessor1Id || 'NULL'}`);
                console.log(`  Assessor2: ${student.assessor2 || 'NULL'}, Assessor2_ID: ${assessor2Id || 'NULL'}`);
                return {
                    student_id: student.id,
                    fyp_session_id: student.fyp_session_id,
                    supervisor: student.supervisor || null,
                    supervisor_id: supervisorId,
                    assessor1: student.assessor1 || null,
                    assessor1_id: assessor1Id,
                    assessor2: student.assessor2 || null,
                    assessor2_id: assessor2Id
                };
            });
            showLoadingModal('Saving assignments and sending emails. Please wait.');
            console.log('Assignment payload:', JSON.stringify(assignmentData, null, 2));
            fetch('../../../php/phpCoordinator/save_assignments.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ assignments: assignmentData })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateAllRemainingQuotas();
                    renderStudentTable();
                    saveModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content-custom">
                                <span class="close-btn" id="closeSaveModal">&times;</span>
                                <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                                <div class="modal-title-custom">Assignments Saved</div>
                                <div class="modal-message">${data.message || 'Student assignments saved successfully!'}</div>
                                <div style="display:flex; justify-content:center;">
                                    <button id="okSave" class="btn btn-success" type="button">OK</button>
                                </div>
                            </div>
                        </div>`;
                    saveModal.querySelector('#closeSaveModal').onclick = closeSaveModal;
                    saveModal.querySelector('#okSave').onclick = closeSaveModal;
                    openSaveModal();
                } else {
                    showSaveError(data.message || 'Assignments Saved');
                }
            })
            .catch(error => {
                console.error('Error saving assignments:', error);
                showSaveError('An error occurred while saving assignments. Please try again.');
            })
            .finally(() => {
                hideLoadingModal();
            });
        }
        function downloadDistributionAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                doc.setFont('helvetica');
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0);
                doc.text('Student Distribution Report (Grouped by Supervisor)', 14, 20);
                const courseLabel = getCurrentCourseLabel();
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Year: ${selectedYear}    Semester: ${selectedSemester}`, 14, 32);
                doc.text(`Course: ${courseLabel}`, 14, 38);
                doc.text(`Total Students (current view): ${filteredStudents.length}`, 14, 44);
                const studentsBySupervisor = {};
                filteredStudents.forEach(student => {
                    const supervisorName = student.supervisor || 'Unassigned';
                    if (!studentsBySupervisor[supervisorName]) {
                        studentsBySupervisor[supervisorName] = [];
                    }
                    studentsBySupervisor[supervisorName].push(student);
                });
                const supervisorNames = Object.keys(studentsBySupervisor).sort();
                let currentY = 52;
                let studentCounter = 1;
                supervisorNames.forEach((supervisorName, supervisorIndex) => {
                    const students = studentsBySupervisor[supervisorName];
                    if (currentY > 240) {
                        doc.addPage();
                        currentY = 20;
                    }
                    doc.setFontSize(12);
                    doc.setTextColor(120, 0, 0);
                    doc.setFont('helvetica', 'bold');
                    doc.text(`Supervisor: ${supervisorName}`, 14, currentY);
                    doc.setFont('helvetica', 'normal');
                    currentY += 2;
                    const tableData = students.map(student => [
                        studentCounter++,
                        student.name,
                        student.assessor1 || '-',
                        student.assessor2 || '-'
                    ]);
                    doc.autoTable({
                        startY: currentY,
                        head: [['No.', 'Student Name', 'Assessor 1', 'Assessor 2']],
                        body: tableData,
                        theme: 'striped',
                        headStyles: {
                            fillColor: [120, 0, 0],
                            textColor: [255, 255, 255],
                            fontStyle: 'bold',
                            fontSize: 10
                        },
                        bodyStyles: {
                            fontSize: 9
                        },
                        alternateRowStyles: {
                            fillColor: [253, 240, 213]
                        },
                        styles: {
                            cellPadding: 3,
                            fontSize: 9
                        },
                        columnStyles: {
                            0: { cellWidth: 15, halign: 'center' },
                            1: { cellWidth: 'auto', halign: 'left' },
                            2: { cellWidth: 50, halign: 'left' },
                            3: { cellWidth: 50, halign: 'left' }
                        },
                        margin: { left: 14, right: 14 }
                    });
                    currentY = doc.lastAutoTable.finalY + 10;
                });
                const finalY = doc.lastAutoTable.finalY;
                doc.setFontSize(9);
                doc.setTextColor(128, 128, 128);
                const now = new Date();
                const dateTime = now.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                if (finalY > 270) {
                    doc.addPage();
                    doc.text(`Generated on: ${dateTime}`, 14, 20);
                } else {
                    doc.text(`Generated on: ${dateTime}`, 14, finalY + 10);
                }
                doc.save('student-distribution-by-supervisor.pdf');
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="modal-title-custom">Download Successful</div>
                            <div class="modal-message">PDF file downloaded successfully!</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            } catch (error) {
                console.error('Error generating PDF:', error);
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">Download Failed</div>
                            <div class="modal-message">An error occurred while generating the PDF. Please try again.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            }
        }
        function downloadDistributionAsExcel() {
            const courseLabel = getCurrentCourseLabel();
            let csvContent = '';
            csvContent += `Year,${selectedYear}\n`;
            csvContent += `Semester,${selectedSemester}\n`;
            csvContent += `Course,${courseLabel}\n`;
            csvContent += `Total Students (current view),${filteredStudents.length}\n\n`;
            const studentsBySupervisor = {};
            filteredStudents.forEach(student => {
                const supervisorName = student.supervisor || 'Unassigned';
                if (!studentsBySupervisor[supervisorName]) {
                    studentsBySupervisor[supervisorName] = [];
                }
                studentsBySupervisor[supervisorName].push(student);
            });
            const supervisorNames = Object.keys(studentsBySupervisor).sort();
            let studentCounter = 1;
            supervisorNames.forEach(supervisorName => {
                const students = studentsBySupervisor[supervisorName];
                csvContent += `\nSupervisor: ${supervisorName}\n`;
                csvContent += 'No.,Student Name,Assessor 1,Assessor 2\n';
                students.forEach(student => {
                    const a1 = student.assessor1 || '-';
                    const a2 = student.assessor2 || '-';
                    csvContent += `${studentCounter++},"${student.name || ''}","${a1}","${a2}"\n`;
                });
            });
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'student-distribution-by-supervisor.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeDownloadModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Download Successful</div>
                        <div class="modal-message">Excel file (CSV) downloaded successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okDownload" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
            downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
            openDownloadModal();
        }

        //ni first bukak assessment session tab
        function initializeAssessmentSession() {
            currentAssessmentCourseFilter = 'both';
            applyAssessmentFilters();
            initializeAssessmentSearch();
            updateAssessmentStudentCount();
            const assessmentCourseFilterMenu = document.getElementById('assessmentCourseFilterMenu');
            const assessmentCourseFilterLabel = document.getElementById('assessmentCourseFilterLabel');
            if (assessmentCourseFilterMenu && assessmentCourseFilterLabel) {
                assessmentCourseFilterMenu.addEventListener('click', function(e) {
                    const option = e.target.closest('.download-option');
                    if (!option) return;
                    const courseId = option.getAttribute('data-course-id') || 'both';
                    const labelText = (option.querySelector('span')?.textContent || 'Both').trim();
                    currentAssessmentCourseFilter = courseId;
                    assessmentCourseFilterLabel.textContent = labelText;
                    applyAssessmentFilters();
                    closeAssessmentCourseFilterDropdown();
                });
            }
            populateDateSortDropdown();
            currentAssessmentDateFilter = ''; // Default to "All Dates"
            document.getElementById('assessmentDateFilterLabel').textContent = 'All Dates';
        }
        
        let currentAssessmentDateFilter = ''; // '' for "All Dates" or specific date string
        function applyAssessmentFilters() {
            const courseIdFilter = currentAssessmentCourseFilter;
            const searchInput = document.getElementById('assessmentStudentSearch');
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const dateFilter = currentAssessmentDateFilter;
            filteredAssessmentStudents = assessmentSessionData.filter(student => {
                if (courseIdFilter !== 'both') {
                    if (String(student.course_id) !== String(courseIdFilter)) {
                        return false;
                    }
                }
                if (searchTerm) {
                    if (!(student.name || '').toLowerCase().includes(searchTerm)) {
                        return false;
                    }
                }
                if (dateFilter) {
                    if (student.date !== dateFilter) {
                        return false;
                    }
                }
                return true;
            });
            populateDateSortDropdown();
            renderAssessmentTable();
            updateAssessmentStudentCount();
            sortAssessmentTable();
        }
        function initializeAssessmentSearch() {
            const searchInput = document.getElementById('assessmentStudentSearch');
            if (!searchInput) return;
            searchInput.addEventListener('input', function() {
                applyAssessmentFilters();
            });
        }
        function renderAssessmentTable() {
            const tbody = document.getElementById('assessmentTableBody');
            if (!tbody) {
                console.error('Assessment table body not found');
                return;
            }
            tbody.innerHTML = '';
            if (!filteredAssessmentStudents || filteredAssessmentStudents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No students available</td></tr>';
                return;
            }
            const venueOptions = getAllVenueOptions();
            filteredAssessmentStudents.forEach((student, index) => {
                const row = document.createElement('tr');
                const studentId = student.id;
                const currentVenue = student.venue || '';
                const isCustomVenue = currentVenue && !venueOptions.includes(currentVenue);
                if (isCustomVenue) {
                    venueOptions.push(currentVenue);
                }
                let venueOptionsHTML = '<option value="">Select Venue</option>';
                venueOptions.forEach(venue => {
                    const selected = student.venue === venue ? 'selected' : '';
                    venueOptionsHTML += `<option value="${venue}" ${selected}>${venue}</option>`;
                });
                venueOptionsHTML += '<option value="__CUSTOM__">+ Add Custom Venue</option>';
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${student.name || 'Unknown'}</td>
                    <td>
                        <input type="date" 
                               class="form-control assessment-date-input" 
                               value="${student.date || ''}"
                               data-student-id="${studentId}"
                               onchange="updateAssessmentDate('${studentId}', this.value)" />
                    </td>
                    <td>
                        <input type="time" 
                               class="form-control assessment-time-input" 
                               value="${student.time || ''}"
                               data-student-id="${studentId}"
                               onchange="updateAssessmentTime('${studentId}', this.value)" />
                    </td>
                    <td>
                        <div style="position: relative;">
                            <select class="form-control assessment-venue-select" 
                                    id="venue-select-${studentId}"
                                    data-student-id="${studentId}"
                                    onchange="handleVenueSelect('${studentId}', this.value)">
                                ${venueOptionsHTML}
                            </select>
                            <input type="text" 
                                   class="form-control assessment-venue-custom" 
                                   id="venue-custom-${studentId}"
                                   data-student-id="${studentId}"
                                   placeholder="Enter custom venue..."
                                   style="display: none; margin-top: 5px;"
                                   onblur="handleCustomVenueInput('${studentId}')"
                                   onkeypress="if(event.key === 'Enter') handleCustomVenueInput('${studentId}')" />
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        function updateAssessmentDate(studentId, date) {
            const student = assessmentSessionData.find(s => String(s.id) === String(studentId));
            if (student) {
                student.date = date;
                populateDateSortDropdown();
            }
        }
        function updateAssessmentTime(studentId, time) {
            const student = assessmentSessionData.find(s => String(s.id) === String(studentId));
            if (student) {
                student.time = time;
            }
        }
        function handleVenueSelect(studentId, value) {
            if (value === '__CUSTOM__') {
                const customInput = document.getElementById(`venue-custom-${studentId}`);
                const select = document.getElementById(`venue-select-${studentId}`);
                if (customInput && select) {
                    customInput.style.display = 'block';
                    customInput.focus();
                    select.value = ''; 
                }
            } else {
                const customInput = document.getElementById(`venue-custom-${studentId}`);
                if (customInput) {
                    customInput.style.display = 'none';
                    customInput.value = '';
                }
                updateAssessmentVenue(studentId, value);
            }
        }
        function handleCustomVenueInput(studentId) {
            const customInput = document.getElementById(`venue-custom-${studentId}`);
            const select = document.getElementById(`venue-select-${studentId}`);
            if (!customInput || !select) return;
            const customVenue = customInput.value.trim();
            if (customVenue) {
                addCustomVenue(customVenue);
                updateAssessmentVenue(studentId, customVenue);
                const venueOptions = getAllVenueOptions();
                let optionsHTML = '<option value="">Select Venue</option>';
                venueOptions.forEach(venue => {
                    const selected = customVenue === venue ? 'selected' : '';
                    optionsHTML += `<option value="${venue}" ${selected}>${venue}</option>`;
                });
                optionsHTML += '<option value="__CUSTOM__">+ Add Custom Venue</option>';
                select.innerHTML = optionsHTML;
                select.value = customVenue;
                customInput.style.display = 'none';
                customInput.value = '';
                renderAssessmentTable();
            } else {
                customInput.style.display = 'none';
                select.value = '';
            }
        }
        function updateAssessmentVenue(studentId, venue) {
            const student = assessmentSessionData.find(s => String(s.id) === String(studentId));
            if (student) {
                student.venue = venue;
            }
        }
        function updateAssessmentStudentCount() {
            const countElement = document.getElementById('assessmentStudentCount');
            if (countElement) {
                countElement.textContent = filteredAssessmentStudents.length;
            }
        }
        function toggleAssessmentCourseFilterDropdown() {
            const dropdown = document.getElementById('assessmentCourseFilterMenu');
            const button = document.querySelector('.course-filter-dropdown .btn-download');
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.btn-download.active').forEach(btn => {
                if (btn !== button) {
                    btn.classList.remove('active');
                }
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeAssessmentCourseFilterDropdown() {
            const dropdown = document.getElementById('assessmentCourseFilterMenu');
            const button = document.querySelector('.course-filter-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        function populateDateSortDropdown() {
            const dateFilterMenu = document.getElementById('assessmentDateFilterMenu');
            if (!dateFilterMenu) return;
            const uniqueDates = [...new Set(assessmentSessionData
                .map(s => s.date)
                .filter(d => d))].sort();
            dateFilterMenu.innerHTML = '<a href="javascript:void(0)" class="download-option" data-date-value=""><span>All Dates</span></a>';
            uniqueDates.forEach(date => {
                const option = document.createElement('a');
                option.href = 'javascript:void(0)';
                option.className = 'download-option';
                option.setAttribute('data-date-value', date);
                const dateObj = new Date(date);
                const formattedDate = dateObj.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                const span = document.createElement('span');
                span.textContent = formattedDate;
                option.appendChild(span);
                dateFilterMenu.appendChild(option);
            });
            dateFilterMenu.querySelectorAll('.download-option').forEach(option => {
                option.addEventListener('click', function(e) {
                    const dateValue = this.getAttribute('data-date-value') || '';
                    const labelText = this.querySelector('span')?.textContent || 'All Dates';
                    currentAssessmentDateFilter = dateValue;
                    document.getElementById('assessmentDateFilterLabel').textContent = labelText;
                    applyAssessmentFilters();
                    closeAssessmentDateFilterDropdown();
                });
            });
        }
        function toggleAssessmentDateFilterDropdown() {
            const dropdown = document.getElementById('assessmentDateFilterMenu');
            const button = document.querySelector('.sort-section .course-filter-dropdown .btn-download');
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.btn-download.active').forEach(btn => {
                if (btn !== button) {
                    btn.classList.remove('active');
                }
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeAssessmentDateFilterDropdown() {
            const dropdown = document.getElementById('assessmentDateFilterMenu');
            const button = document.querySelector('.sort-section .course-filter-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        let assessmentSortOrder = 'asc'; 
        function sortAssessmentTable() {
            filteredAssessmentStudents.sort((a, b) => {
                let comparison = 0;
                if (a.date && b.date) {
                    comparison = a.date.localeCompare(b.date);
                } else if (a.date && !b.date) {
                    comparison = -1;
                } else if (!a.date && b.date) {
                    comparison = 1;
                } else {
                    comparison = 0;
                }
                if (comparison === 0 && a.time && b.time) {
                    comparison = a.time.localeCompare(b.time);
                } else if (comparison === 0 && a.time && !b.time) {
                    comparison = -1;
                } else if (comparison === 0 && !a.time && b.time) {
                    comparison = 1;
                }
                if (comparison === 0) {
                    comparison = (a.name || '').localeCompare(b.name || '');
                }
                return assessmentSortOrder === 'asc' ? comparison : -comparison;
            });
            renderAssessmentTable();
        }
        var assignAssessmentModal = document.createElement('div');
        assignAssessmentModal.className = 'custom-modal';
        assignAssessmentModal.id = 'assignAssessmentModal';
        document.body.appendChild(assignAssessmentModal);
        function openAssignAssessmentModal() {
            const studentsWithoutData = assessmentSessionData.filter(s => !s.date || !s.time || !s.venue);
            const count = studentsWithoutData.length;
            if (count === 0) {
                assignAssessmentModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignAssessmentModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">No Students to Assign</div>
                            <div class="modal-message">All students already have assessment session data assigned (date, time, and venue).</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okNoStudents" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignAssessmentModal.querySelector('#closeAssignAssessmentModal').onclick = closeAssignAssessmentModal;
                assignAssessmentModal.querySelector('#okNoStudents').onclick = closeAssignAssessmentModal;
                openModal(assignAssessmentModal);
                return;
            }
            const allVenueOptions = getAllVenueOptions();
            let venueCheckboxesHTML = `
                <div style="margin-bottom: 10px;">
                    <label style="display: flex; align-items: center; cursor: pointer; padding: 5px 0;">
                        <input type="checkbox" id="selectAllVenues" checked style="margin-right: 8px; cursor: pointer;" />
                        <strong>Select All</strong>
                    </label>
                </div>
            `;
            allVenueOptions.forEach((venue, index) => {
                venueCheckboxesHTML += `
                    <div style="margin-bottom: 8px;">
                        <label style="display: flex; align-items: center; cursor: pointer; padding: 5px 0;">
                            <input type="checkbox" class="venue-checkbox" value="${venue}" checked style="margin-right: 8px; cursor: pointer;" />
                            <span>${venue}</span>
                        </label>
                    </div>
                `;
            });
            assignAssessmentModal.innerHTML = `
                <div class="modal-dialog" style="max-width: 550px;">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignAssessmentModal">&times;</span>
                        <div class="modal-title-custom">Assign Assessment Sessions Automatically</div>
                        <div class="modal-message" style="margin-bottom: 20px;">
                            This will automatically assign assessment session data (date, time, and venue) to ${count} student(s) who don't have complete information.
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="startDate" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Start Date:</label>
                            <input type="date" id="startDate" class="form-control" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;" required />
                        </div>
                        <div style="margin-bottom: 15px;">
                            <label for="endDate" style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">End Date:</label>
                            <input type="date" id="endDate" class="form-control" style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;" required />
                        </div>
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Select Venues:</label>
                            <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 8px; padding: 10px; background: #f9f9f9;">
                                ${venueCheckboxesHTML}
                            </div>
                        </div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelAssignAssessment" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmAssignAssessment" class="btn btn-success" type="button">Assign</button>
                        </div>
                    </div>
                </div>`;
            const selectAllCheckbox = assignAssessmentModal.querySelector('#selectAllVenues');
            const venueCheckboxes = assignAssessmentModal.querySelectorAll('.venue-checkbox');
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    venueCheckboxes.forEach(cb => {
                        cb.checked = selectAllCheckbox.checked;
                    });
                });
            }
            venueCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(venueCheckboxes).every(c => c.checked);
                    selectAllCheckbox.checked = allChecked;
                });
            });
            assignAssessmentModal.querySelector('#closeAssignAssessmentModal').onclick = closeAssignAssessmentModal;
            assignAssessmentModal.querySelector('#cancelAssignAssessment').onclick = closeAssignAssessmentModal;
            assignAssessmentModal.querySelector('#confirmAssignAssessment').onclick = function() {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                if (!startDate || !endDate) {
                    alert('Please select both start date and end date.');
                    return;
                }
                if (new Date(startDate) > new Date(endDate)) {
                    alert('Start date must be before or equal to end date.');
                    return;
                }
                const selectedVenues = Array.from(venueCheckboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
                if (selectedVenues.length === 0) {
                    alert('Please select at least one venue.');
                    return;
                }
                performAssignAssessmentSessions(startDate, endDate, selectedVenues);
            };
            openModal(assignAssessmentModal);
        }
        function closeAssignAssessmentModal() {
            closeModal(assignAssessmentModal);
        }
        function syncAssessmentSessionData() {
            assessmentSessionData.forEach(assessmentStudent => {
                const currentStudent = students.find(s => String(s.id) === String(assessmentStudent.id));
                if (currentStudent) {
                    assessmentStudent.supervisor = currentStudent.supervisor;
                    assessmentStudent.assessor1 = currentStudent.assessor1;
                    assessmentStudent.assessor2 = currentStudent.assessor2;
                }
            });
        }

//assign automatically assessment session to students

        function performAssignAssessmentSessions(startDate, endDate, selectedVenues = null) {

            syncAssessmentSessionData();
            const studentsNeedingAssignment = assessmentSessionData.filter(s => !s.date || !s.time || !s.venue);
            if (studentsNeedingAssignment.length === 0) {
                closeAssignAssessmentModal();
                return;
            }
            const supervisorNames = [...new Set(studentsNeedingAssignment
                .map(s => s.supervisor)
                .filter(s => s))];
            if (supervisorNames.length === 0) {
                alert('No supervisors found for students needing assignment. Please assign supervisors first.');
                closeAssignAssessmentModal();
                return;
            }
            let lecturerGroups = [];
            if (supervisorNames.length >= 3) {
                const groupSize = Math.floor(supervisorNames.length / 3);
                for (let i = 0; i < 3; i++) {
                    const start = i * groupSize;
                    const end = i === 2 ? supervisorNames.length : (i + 1) * groupSize;
                    lecturerGroups.push(supervisorNames.slice(start, end));
                }
            } else {
                for (let i = 0; i < supervisorNames.length; i++) {
                    lecturerGroups.push([supervisorNames[i]]);
                }
                while (lecturerGroups.length < 3) {
                    lecturerGroups.push([]);
                }
            }
            const studentGroupsByDistribution = [[], [], []]; 
            studentsNeedingAssignment.forEach(student => {
                if (student.supervisor) {
                    let supervisorGroupIndex = -1;
                    for (let i = 0; i < lecturerGroups.length; i++) {
                        if (lecturerGroups[i].includes(student.supervisor)) {
                            supervisorGroupIndex = i;
                            break;
                        }
                    }
                    const groupIndex = supervisorGroupIndex >= 0 ? supervisorGroupIndex : 0;
                    studentGroupsByDistribution[groupIndex].push(student);
                } else {
                    studentGroupsByDistribution[0].push(student);
                }
            });
            const assessmentGroups = [];
            studentGroupsByDistribution.forEach((distributionGroup, distGroupIndex) => {
                if (distributionGroup.length === 0) return;
                const otherGroupIndices = [0, 1, 2].filter(idx => idx !== distGroupIndex);
                const assessorGroup1 = lecturerGroups[otherGroupIndices[0]] || [];
                const assessorGroup2 = lecturerGroups[otherGroupIndices[1]] || [];
                const studentsBySupervisor = {};
                distributionGroup.forEach(student => {
                    const supervisorName = student.supervisor || 'No Supervisor';
                    if (!studentsBySupervisor[supervisorName]) {
                        studentsBySupervisor[supervisorName] = [];
                    }
                    studentsBySupervisor[supervisorName].push(student);
                });
                const allStudentsInGroup = distributionGroup;
                if (allStudentsInGroup.length > 0) {
                    assessmentGroups.push({
                        distributionGroupIndex: distGroupIndex,
                        supervisors: lecturerGroups[distGroupIndex] || [],
                        students: allStudentsInGroup,
                        studentsBySupervisor: studentsBySupervisor,
                        assessorGroup1: assessorGroup1,
                        assessorGroup2: assessorGroup2
                    });
                }
            });
            const venueOptions = selectedVenues && selectedVenues.length > 0 
                ? selectedVenues 
                : getAllVenueOptions();


            function getTimeSlotsForDate(dateStr) {
                const date = new Date(dateStr);
                const dayOfWeek = date.getDay(); 
                const isFriday = dayOfWeek === 5;
                const morningSlots = [
                    '09:00', '09:20', '09:40', '10:00', '10:20', '10:40',
                    '11:00', '11:20', '11:40', '12:00'
                ];
                const afternoonSlots = [
                    '14:00', '14:20', '14:40', '15:00', '15:20', '15:40',
                    '16:00', '16:20', '16:40', '17:00'
                ];
                if (isFriday) {
                    return morningSlots;
                } else {
                    return [...morningSlots, ...afternoonSlots];
                }
            }
            const allTimeSlots = [
                '09:00', '09:20', '09:40', '10:00', '10:20', '10:40',
                '11:00', '11:20', '11:40', '12:00',
                '14:00', '14:20', '14:40', '15:00', '15:20', '15:40',
                '16:00', '16:20', '16:40', '17:00'
            ];
            const start = new Date(startDate);
            const end = new Date(endDate);
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;
            const assessorSchedule = {};
            const venueSchedule = {};
            assessmentSessionData.forEach(student => {
                if (student.date && student.time) {
                    const dateTimeKey = `${student.date}_${student.time}`;
                    if (student.assessor1) {
                        if (!assessorSchedule[student.assessor1]) {
                            assessorSchedule[student.assessor1] = {};
                        }
                        assessorSchedule[student.assessor1][dateTimeKey] = student.time; 
                    }
                    if (student.assessor2) {
                        if (!assessorSchedule[student.assessor2]) {
                            assessorSchedule[student.assessor2] = {};
                        }
                        assessorSchedule[student.assessor2][dateTimeKey] = student.time; 
                    }
                    if (student.venue) {
                        if (!venueSchedule[student.venue]) {
                            venueSchedule[student.venue] = {};
                        }
                        venueSchedule[student.venue][dateTimeKey] = student.time; 
                    }
                }
            });
            function timeToMinutes(timeStr) {
                const [hours, minutes] = timeStr.split(':').map(Number);
                return hours * 60 + minutes;
            }
            function timesOverlap(time1, time2) {
                const minutes1 = timeToMinutes(time1);
                const minutes2 = timeToMinutes(time2);
                const presentationDuration = 20; 
                return (minutes1 >= minutes2 && minutes1 < minutes2 + presentationDuration) ||
                       (minutes2 >= minutes1 && minutes2 < minutes1 + presentationDuration);
            }

            //chck kat sini untuk any assessor 1 punya time.Ada conflict x
            function hasConflict(date, time, assessor1, assessor2, venue) {
                if (assessor1 && assessorSchedule[assessor1]) {
                    for (const existingTimeKey in assessorSchedule[assessor1]) {
                        const [existingDate, existingTime] = existingTimeKey.split('_');
                        if (existingDate === date && timesOverlap(time, existingTime)) {
                            return true;
                        }
                    }
                }

                //check assessor 2 punya time pulak
                if (assessor2 && assessorSchedule[assessor2]) {
                    for (const existingTimeKey in assessorSchedule[assessor2]) {
                        const [existingDate, existingTime] = existingTimeKey.split('_');
                        if (existingDate === date && timesOverlap(time, existingTime)) {
                            return true;
                        }
                    }
                }

                //check tempat full dok
                if (venue && venueSchedule[venue]) {
                    for (const existingTimeKey in venueSchedule[venue]) {
                        const [existingDate, existingTime] = existingTimeKey.split('_');
                        if (existingDate === date && timesOverlap(time, existingTime)) {
                            return true;
                        }
                    }
                }
                return false;
            }

            //tak bz, then reserve
            function reserveSlot(date, time, assessor1, assessor2, venue) {
                const dateTimeKey = `${date}_${time}`;
                if (assessor1) {
                    if (!assessorSchedule[assessor1]) {
                        assessorSchedule[assessor1] = {};
                    }
                    assessorSchedule[assessor1][dateTimeKey] = time; 
                }
                if (assessor2) {
                    if (!assessorSchedule[assessor2]) {
                        assessorSchedule[assessor2] = {};
                    }
                    assessorSchedule[assessor2][dateTimeKey] = time; 
                }
                if (venue) {
                    if (!venueSchedule[venue]) {
                        venueSchedule[venue] = {};
                    }
                    venueSchedule[venue][dateTimeKey] = time; 
                }
            }


            const totalStudentsNeedingAssignment = assessmentGroups.reduce((sum, group) => sum + group.students.length, 0);
            const studentsPerDay = Math.ceil(totalStudentsNeedingAssignment / daysDiff);
            const studentsPerDayCount = new Array(daysDiff).fill(0);
            const datesWithStudents = new Set();
            const maxStudentsPerDay = 10;
            const allStudentsToAssign = [];
            assessmentGroups.forEach(group => {
                group.students.forEach(student => {
                    allStudentsToAssign.push(student);
                });
            });
            allStudentsToAssign.sort((a, b) => {
                const aKey = `${a.assessor1 || ''}|${a.assessor2 || ''}`;
                const bKey = `${b.assessor1 || ''}|${b.assessor2 || ''}`;
                if (aKey !== bKey) return aKey.localeCompare(bKey);
                return (a.name || '').localeCompare(b.name || '');
            });
            let studentIndexGlobal = 0;
            let cycleCount = 0;
            const maxCycles = 10; 
            while (studentIndexGlobal < allStudentsToAssign.length && cycleCount < maxCycles) {
                for (let dayOffsetInCycle = 0; dayOffsetInCycle < daysDiff && studentIndexGlobal < allStudentsToAssign.length; dayOffsetInCycle++) {
                    const currentDate = new Date(start);
                    currentDate.setDate(start.getDate() + dayOffsetInCycle);
                    const targetDate = currentDate.toISOString().split('T')[0];
                    datesWithStudents.add(targetDate);
                    const timeSlotsForDate = getTimeSlotsForDate(targetDate);
                    const studentsOnThisDay = Math.min(maxStudentsPerDay, allStudentsToAssign.length - studentIndexGlobal);
                    const dayStudents = allStudentsToAssign.slice(studentIndexGlobal, studentIndexGlobal + studentsOnThisDay);
                    const venueUsageToday = {};
                    venueOptions.forEach(venue => {
                        const existing = venueSchedule[venue] || {};
                        const countForDate = Object.keys(existing).filter(key => key.startsWith(`${targetDate}_`)).length;
                        venueUsageToday[venue] = countForDate;
                    });


                    const getVenuesByLoad = () => [...venueOptions].sort((a, b) => {
                        const diff = (venueUsageToday[a] || 0) - (venueUsageToday[b] || 0);
                        return diff !== 0 ? diff : a.localeCompare(b);
                    });
                    const roomsNeeded = Math.max(1, Math.min(venueOptions.length, Math.ceil(dayStudents.length / timeSlotsForDate.length)));
                    const activeVenues = getVenuesByLoad().slice(0, roomsNeeded);
                    const studentsByAssessorPair = {};
                    dayStudents.forEach(student => {
                        const key = `${student.assessor1 || ''}|${student.assessor2 || ''}`;
                        if (!studentsByAssessorPair[key]) studentsByAssessorPair[key] = [];
                        studentsByAssessorPair[key].push(student);
                    });
                    const assessorPairKeys = Object.keys(studentsByAssessorPair).sort();
                    const nextSlotIndexByVenue = {};
                    activeVenues.forEach(v => { nextSlotIndexByVenue[v] = 0; });
                    let assignedCountThisDay = 0;
                    assessorPairKeys.forEach(pairKey => {
                        const groupStudents = studentsByAssessorPair[pairKey];
                        const venuesByLoad = [...activeVenues].sort((a, b) => {
                            const diff = (venueUsageToday[a] || 0) - (venueUsageToday[b] || 0);
                            return diff !== 0 ? diff : a.localeCompare(b);
                        });
                        const targetVenue = venuesByLoad[0] || activeVenues[0];
                        groupStudents.forEach(student => {
                            const assessor1 = student.assessor1;
                            const assessor2 = student.assessor2;
                            let assigned = false;
                            for (let t = nextSlotIndexByVenue[targetVenue]; t < timeSlotsForDate.length && !assigned; t++) {
                                const time = timeSlotsForDate[t];
                                if (!hasConflict(targetDate, time, assessor1, assessor2, targetVenue)) {
                                    student.date = targetDate;
                                    student.time = time;
                                    student.venue = targetVenue;
                                    reserveSlot(targetDate, time, assessor1, assessor2, targetVenue);
                                    venueUsageToday[targetVenue] = (venueUsageToday[targetVenue] || 0) + 1;
                                    nextSlotIndexByVenue[targetVenue] = t + 1;
                                    assigned = true;
                                    break;
                                }
                            }
                            if (!assigned) {
                                for (const venue of activeVenues) {
                                    for (let t = nextSlotIndexByVenue[venue] || 0; t < timeSlotsForDate.length && !assigned; t++) {
                                        const time = timeSlotsForDate[t];
                                        if (!hasConflict(targetDate, time, assessor1, assessor2, venue)) {
                                            student.date = targetDate;
                                            student.time = time;
                                            student.venue = venue;
                                            reserveSlot(targetDate, time, assessor1, assessor2, venue);
                                            venueUsageToday[venue] = (venueUsageToday[venue] || 0) + 1;
                                            nextSlotIndexByVenue[venue] = t + 1;
                                            assigned = true;
                                            break;
                                        }
                                    }
                                    if (assigned) break;
                                }
                            }
                            if (!assigned) {
                                const venue = targetVenue || activeVenues[0] || venueOptions[0];
                                const time = timeSlotsForDate[nextSlotIndexByVenue[venue]] || timeSlotsForDate[0] || '09:00';
                                student.date = targetDate;
                                student.time = time;
                                student.venue = venue;
                                reserveSlot(targetDate, time, assessor1, assessor2, venue);
                                venueUsageToday[venue] = (venueUsageToday[venue] || 0) + 1;
                                nextSlotIndexByVenue[venue] = (nextSlotIndexByVenue[venue] || 0) + 1;
                            }
                            assignedCountThisDay++;
                        });
                    });
                    studentIndexGlobal += studentsOnThisDay;
                    if (!studentsPerDayCount[dayOffsetInCycle]) {
                        studentsPerDayCount[dayOffsetInCycle] = 0;
                    }
                    studentsPerDayCount[dayOffsetInCycle] += assignedCountThisDay;
                }
                cycleCount++;
            }
            if (studentIndexGlobal < allStudentsToAssign.length) {
                const remaining = allStudentsToAssign.slice(studentIndexGlobal);
                remaining.forEach(student => {
                    let assigned = false;
                    for (let d = 0; d < daysDiff && !assigned; d++) {
                        const currentDate = new Date(start);
                        currentDate.setDate(start.getDate() + d);
                        const dateStr = currentDate.toISOString().split('T')[0];
                        const timeSlotsForDate = getTimeSlotsForDate(dateStr);
                        for (let v = 0; v < venueOptions.length && !assigned; v++) {
                            const venue = venueOptions[v];
                            for (let t = 0; t < timeSlotsForDate.length && !assigned; t++) {
                                const time = timeSlotsForDate[t];
                                const assessor1 = student.assessor1;
                                const assessor2 = student.assessor2;
                                if (!hasConflict(dateStr, time, assessor1, assessor2, venue)) {
                                    student.date = dateStr;
                                    student.time = time;
                                    student.venue = venue;
                                    reserveSlot(dateStr, time, assessor1, assessor2, venue);
                                    assigned = true;
                                }
                            }
                        }
                    }
                    if (!assigned) {
                        const dateStr = start.toISOString().split('T')[0];
                        const timeSlotsForDate = getTimeSlotsForDate(dateStr);
                        const time = timeSlotsForDate[0] || '09:00';
                        const venue = venueOptions[0];
                        student.date = dateStr;
                        student.time = time;
                        student.venue = venue;
                        reserveSlot(dateStr, time, student.assessor1, student.assessor2, venue);
                    }
                });
            }
            populateDateSortDropdown();
            renderAssessmentTable();
            closeAssignAssessmentModal();
            assignAssessmentModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignAssessmentSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assessment Sessions Assigned</div>
                        <div class="modal-message">Assessment session data has been automatically assigned to ${studentsNeedingAssignment.length} student(s) using 3-group logic. Conflicts have been avoided where possible.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okAssigned" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            assignAssessmentModal.querySelector('#closeAssignAssessmentSuccess').onclick = closeAssignAssessmentModal;
            assignAssessmentModal.querySelector('#okAssigned').onclick = closeAssignAssessmentModal;
        }
        function clearAllAssessmentSessions() {
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllModal">&times;</span>
                        <div class="modal-title-custom">Clear All Assessment Sessions</div>
                        <div class="modal-message">Are you sure you want to clear all assessment session data (date, time, venue)? All data will be removed.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelClearAll" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmClearAll" class="btn btn-success" type="button">Clear All</button>
                        </div>
                    </div>
                </div>`;
            clearAllModal.querySelector('#closeClearAllModal').onclick = closeClearAllModal;
            clearAllModal.querySelector('#cancelClearAll').onclick = closeClearAllModal;
            clearAllModal.querySelector('#confirmClearAll').onclick = function() {
                performClearAllAssessmentSessions();
            };
            openClearAllModal();
        }
        function performClearAllAssessmentSessions() {
            assessmentSessionData.forEach(student => {
                student.date = '';
                student.time = '';
                student.venue = '';
            });
            populateDateSortDropdown();
            renderAssessmentTable();
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assessment Sessions Cleared</div>
                        <div class="modal-message">All assessment session data has been cleared successfully.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okCleared" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            clearAllModal.querySelector('#closeClearAllSuccess').onclick = closeClearAllModal;
            clearAllModal.querySelector('#okCleared').onclick = closeClearAllModal;
        }
        function resetAssessmentSessions() {
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeResetModal">&times;</span>
                        <div class="modal-title-custom">Reset Assessment Sessions</div>
                        <div class="modal-message">Are you sure you want to cancel all changes? This will reset all assessment session data to their previous values.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelReset" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmReset" class="btn btn-success" type="button">Reset</button>
                        </div>
                    </div>
                </div>`;
            resetModal.querySelector('#closeResetModal').onclick = closeResetModal;
            resetModal.querySelector('#cancelReset').onclick = closeResetModal;
            resetModal.querySelector('#confirmReset').onclick = function() {
                location.reload();
            };
            openResetModal();
        }
        function saveAssessmentSessions() {
            const sessionData = assessmentSessionData.map(student => ({
                student_id: student.id,
                student_name: student.name,
                date: student.date || null,
                time: student.time || null,
                venue: student.venue || null,
                course_id: student.course_id,
                course_code: student.course_code,
                fyp_session_id: student.fyp_session_id
            }));
            showLoadingModal('Saving assessment sessions and sending emails. Please wait.');
            fetch('../../../php/phpCoordinator/save_assessment_sessions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ 
                    sessions: sessionData,
                    year: selectedYear,
                    semester: selectedSemester
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadAssessmentSessionsFromDatabase();
                    saveModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content-custom">
                                <span class="close-btn" id="closeSaveModal">&times;</span>
                                <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                                <div class="modal-title-custom">Assessment Sessions Saved</div>
                                <div class="modal-message">${data.message || 'Assessment session data saved successfully!'}</div>
                                <div style="display:flex; justify-content:center;">
                                    <button id="okSave" class="btn btn-success" type="button">OK</button>
                                </div>
                            </div>
                        </div>`;
                    saveModal.querySelector('#closeSaveModal').onclick = closeSaveModal;
                    saveModal.querySelector('#okSave').onclick = closeSaveModal;
                    openSaveModal();
                } else {
                    showSaveError(data.message || 'Assessment session data saved successfully!');
                }
            })
            .catch(error => {
                console.error('Error saving assessment sessions:', error);
                showSaveError('An error occurred while saving assessment sessions. Please try again.');
            })
            .finally(() => {
                hideLoadingModal();
            });
        }
        function downloadAssessmentAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF('p', 'mm', 'a4');
                doc.setFont('helvetica');
                const pageWidth = doc.internal.pageSize.getWidth();
                const pageHeight = doc.internal.pageSize.getHeight();
                const margin = 14;
                const usableWidth = pageWidth - (margin * 2);
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0);
                doc.setFont('helvetica', 'bold');
                doc.text('Assessment Session Report', margin, 20);
                const courseLabel = getAssessmentCourseLabel();
                doc.setFont('helvetica', 'normal');
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Year: ${selectedYear}`, margin, 32);
                doc.text(`Semester: ${selectedSemester}`, margin, 38);
                doc.text(`Course: ${courseLabel}`, margin, 44);
                const sessions = [...filteredAssessmentStudents];
                const byDate = {};
                sessions.forEach(s => {
                    const dateKey = s.date && s.date.trim() !== '' ? s.date : 'Unscheduled';
                    if (!byDate[dateKey]) byDate[dateKey] = [];
                    byDate[dateKey].push(s);
                });
                const dates = Object.keys(byDate).sort((a, b) => {
                    if (a === 'Unscheduled') return 1;
                    if (b === 'Unscheduled') return -1;
                    return a.localeCompare(b);
                });
                let currentY = 54;
                dates.forEach((dateKey, dateIndex) => {
                    if (currentY > pageHeight - 40) {
                        doc.addPage();
                        currentY = 20;
                    }
                    doc.setFillColor(240, 240, 240);
                    doc.rect(margin, currentY - 6, usableWidth, 10, 'F');
                    doc.setFontSize(14);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(0, 0, 0);
                    let displayDate = dateKey;
                    if (dateKey !== 'Unscheduled') {
                        try {
                            const d = new Date(dateKey);
                            displayDate = d.toLocaleDateString('en-US', { 
                                weekday: 'long', 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        } catch (e) {
                            displayDate = dateKey;
                        }
                    }
                    doc.text(`Date: ${displayDate}`, margin + 2, currentY);
                    currentY += 10;
                    const byRoom = {};
                    byDate[dateKey].forEach(s => {
                        const roomKey = s.venue && s.venue.trim() !== '' ? s.venue : 'Not Assigned';
                        if (!byRoom[roomKey]) byRoom[roomKey] = [];
                        byRoom[roomKey].push(s);
                    });
                    const rooms = Object.keys(byRoom).sort((a, b) => a.localeCompare(b));
                    rooms.forEach((roomKey, roomIndex) => {
                        if (currentY > pageHeight - 50) {
                            doc.addPage();
                            currentY = 20;
                        }
                        currentY += 6;
                        doc.setFontSize(12);
                        doc.setFont('helvetica', 'bold');
                        doc.setTextColor(80, 80, 80);
                        doc.text(`   Room: ${roomKey}`, margin, currentY);
                        currentY += 2;
                        const roomStudents = byRoom[roomKey].slice().sort((a, b) => {
                            const ta = (a.time || '').toString();
                            const tb = (b.time || '').toString();
                            if (ta === '' && tb !== '') return 1;
                            if (tb === '' && ta !== '') return -1;
                            const timeCompare = ta.localeCompare(tb);
                            if (timeCompare !== 0) return timeCompare;
                            const ga = `${a.assessor1 || ''}|${a.assessor2 || ''}`;
                            const gb = `${b.assessor1 || ''}|${b.assessor2 || ''}`;
                            if (ga !== gb) return ga.localeCompare(gb);
                            return (a.name || '').localeCompare(b.name || '');
                        });
                        const tableData = roomStudents.map((s, idx) => [
                            idx + 1,
                            s.name || '-',
                            s.supervisor || '-',
                            s.time || '-',
                            s.assessor1 || '-',
                            s.assessor2 || '-'
                        ]);
                        doc.autoTable({
                            startY: currentY + 2,
                            head: [['No.', 'Student Name', 'Supervisor', 'Time', 'Assessor 1', 'Assessor 2']],
                            body: tableData,
                            theme: 'striped',
                            margin: { left: margin, right: margin },
                            headStyles: {
                                fillColor: [120, 0, 0],
                                textColor: [255, 255, 255],
                                fontStyle: 'bold',
                                fontSize: 10,
                                halign: 'left',
                                cellPadding: { top: 3, right: 4, bottom: 3, left: 4 }
                            },
                            bodyStyles: {
                                fontSize: 9,
                                cellPadding: { top: 3, right: 4, bottom: 3, left: 4 },
                                halign: 'left'
                            },
                            alternateRowStyles: {
                                fillColor: [253, 240, 213]
                            },
                            columnStyles: {
                                0: { cellWidth: 12, halign: 'center' },
                                1: { cellWidth: 'auto', halign: 'left' },
                                2: { cellWidth: 'auto', halign: 'left' },
                                3: { cellWidth: 20, halign: 'center' },
                                4: { cellWidth: 'auto', halign: 'left' },
                                5: { cellWidth: 'auto', halign: 'left' }
                            },
                            styles: {
                                overflow: 'linebreak',
                                cellWidth: 'wrap',
                                minCellHeight: 8,
                                lineColor: [200, 200, 200],
                                lineWidth: 0.1
                            },
                            didDrawPage: function(data) {
                                doc.setFontSize(8);
                                doc.setFont('helvetica', 'normal');
                                doc.setTextColor(128, 128, 128);
                                doc.text(
                                    `Page ${doc.internal.getNumberOfPages()}`, 
                                    pageWidth / 2, 
                                    pageHeight - 10, 
                                    { align: 'center' }
                                );
                            }
                        });
                        currentY = doc.lastAutoTable.finalY + 6;
                    });
                    currentY += 4;
                });
                const finalY = doc.lastAutoTable ? doc.lastAutoTable.finalY : currentY + 10;
                if (finalY > pageHeight - 25) {
                    doc.addPage();
                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(128, 128, 128);
                    const now = new Date();
                    const dateTime = now.toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    doc.text(`Generated on: ${dateTime}`, margin, 20);
                } else {
                    doc.setFontSize(9);
                    doc.setFont('helvetica', 'italic');
                    doc.setTextColor(128, 128, 128);
                    const now = new Date();
                    const dateTime = now.toLocaleString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    doc.text(`Generated on: ${dateTime}`, margin, finalY + 12);
                }
                doc.save('assessment-session-report.pdf');
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="modal-title-custom">Download Successful</div>
                            <div class="modal-message">PDF file downloaded successfully!</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            } catch (error) {
                console.error('Error generating PDF:', error);
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">Download Failed</div>
                            <div class="modal-message">An error occurred while generating the PDF. Please try again.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            }
        }
        function downloadAssessmentAsExcel() {
            const courseLabel = getAssessmentCourseLabel();
            let csvContent = '';
            csvContent += `Year,${selectedYear}\n`;
            csvContent += `Semester,${selectedSemester}\n`;
            csvContent += `Course,${courseLabel}\n`;
            csvContent += `Total Students (current view),${filteredAssessmentStudents.length}\n\n`;
            csvContent += 'No.,Student Name,Date,Time,Venue\n';
            filteredAssessmentStudents.forEach((student, index) => {
                csvContent += `${index + 1},"${student.name || ''}","${student.date || '-'}","${student.time || '-'}","${student.venue || '-'}"\n`;
            });
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'assessment-session.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeDownloadModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Download Successful</div>
                        <div class="modal-message">Excel file (CSV) downloaded successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okDownload" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;
            downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
            downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
            openDownloadModal();
        }
        function getAssessmentCourseLabel() {
            if (!courseFilterOptions || !Array.isArray(courseFilterOptions)) {
                return 'Both';
            }
            if (currentAssessmentCourseFilter === 'both') {
                if (courseFilterOptions.length === 0) return 'Both';
                const codes = courseFilterOptions.map(c => c.Course_Code).filter(Boolean);
                if (codes.length > 1) {
                    return 'Both (' + codes.join(', ') + ')';
                }
                return codes[0] || 'Both';
            }
            const match = courseFilterOptions.find(c => String(c.Course_ID) === String(currentAssessmentCourseFilter));
            if (match) {
                return match.Course_Code || ('Course ' + currentAssessmentCourseFilter);
            }
            return 'Course ' + currentAssessmentCourseFilter;
        }
        function toggleAssessmentDownloadDropdown() {
            const dropdown = document.getElementById('assessmentDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.btn-download.active').forEach(btn => {
                if (btn !== button) {
                    btn.classList.remove('active');
                }
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeAssessmentDownloadDropdown() {
            const dropdown = document.getElementById('assessmentDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        function toggleAssignDropdown() {
            const dropdown = document.getElementById('assignDropdown');
            const button = document.querySelector('.assign-dropdown .btn-assign');
            document.querySelectorAll('.assign-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.download-dropdown .btn-download').forEach(btn => {
                btn.classList.remove('active');
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeAssignDropdown() {
            const dropdown = document.getElementById('assignDropdown');
            const button = document.querySelector('.assign-dropdown .btn-assign');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        function toggleDistributionDownloadDropdown() {
            const dropdown = document.getElementById('distributionDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            document.querySelectorAll('.assign-dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
            document.querySelectorAll('.assign-dropdown .btn-assign').forEach(btn => {
                btn.classList.remove('active');
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeDistributionDownloadDropdown() {
            const dropdown = document.getElementById('distributionDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        function initializeRoleToggle() {
            let activeMenuItemId = 'studentAssignation';
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
            const allRoleHeaders = document.querySelectorAll('.role-header');
            const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
            const coordinatorMenu = document.querySelector('#coordinatorMenu');
            allRoleHeaders.forEach(header => {
                const roleType = header.getAttribute('data-role');
                if (roleType !== 'coordinator') {
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                }
            });
            if (coordinatorHeader && coordinatorMenu) {
                coordinatorHeader.classList.add('active-role');
                if (coordinatorMenu.classList.contains('expanded')) {
                    coordinatorHeader.classList.add('menu-expanded');
                    setActiveMenuItem(activeMenuItemId);
                } else {
                    coordinatorHeader.classList.remove('menu-expanded');
                }
            }
            const arrowContainers = document.querySelectorAll('.arrow-container');
            const handleRoleToggle = (header) => {
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');
                const isCoordinator = header.getAttribute('data-role') === 'coordinator';
                if (!targetMenu) return;
                const isExpanded = targetMenu.classList.contains('expanded');
                document.querySelectorAll('.menu-items').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.remove('expanded');
                        menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                    }
                });
                const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
                const coordinatorMenu = document.querySelector('#coordinatorMenu');
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
                document.querySelectorAll('.role-header').forEach(h => {
                    const roleType = h.getAttribute('data-role');
                    if (roleType !== 'coordinator') {
                        h.classList.remove('active-role');
                        h.classList.remove('menu-expanded');
                    }
                });
                document.querySelectorAll('.arrow-icon').forEach(icon => {
                    if (icon !== arrowIcon) {
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-right');
                    }
                });
                targetMenu.classList.toggle('expanded', !isExpanded);
                if (isCoordinator) {
                    header.classList.add('active-role');
                    const isNowExpanded = targetMenu.classList.contains('expanded');
                    if (isNowExpanded) {
                        header.classList.add('menu-expanded');
                        if (activeMenuItemId) {
                            setTimeout(() => {
                                setActiveMenuItem(activeMenuItemId);
                            }, 10);
                        }
                    } else {
                        header.classList.remove('menu-expanded');
                    }
                } else {
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
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
                const sidebar = document.getElementById("mySidebar");
                const isSidebarExpanded = sidebar.style.width === "220px";
                targetMenu.querySelectorAll('a').forEach(a => {
                    if(isSidebarExpanded) {
                         a.style.display = targetMenu.classList.contains('expanded') ? 'block' : 'none';
                    } else {
                        a.style.display = 'none';
                    }
                });
                if (isExpanded) {
                    arrowIcon.classList.remove('bi-chevron-down');
                    arrowIcon.classList.add('bi-chevron-right');
                } else {
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                }
            };
            arrowContainers.forEach(container => {
                const header = container.closest('.role-header');
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleRoleToggle(header);
                });
            });
            const coordinatorMenuItems = document.querySelectorAll('#coordinatorMenu a');
            coordinatorMenuItems.forEach(menuItem => {
                menuItem.addEventListener('click', (event) => {
                    const coordinatorMenu = document.querySelector('#coordinatorMenu');
                    const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
                    if (coordinatorMenu && coordinatorMenu.classList.contains('expanded')) {
                        const menuItemId = menuItem.getAttribute('id');
                        if (menuItemId) {
                            setActiveMenuItem(menuItemId);
                        }
                    }
                });
            });
        }
        function toggleDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.btn-download');
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                    const btn = menu.previousElementSibling;
                    if (btn && btn.classList.contains('btn-download')) {
                        btn.classList.remove('active');
                    }
                }
            });
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }
        function closeDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }
        function repositionOpenDropdown() {
            if (openDropdown) {
                const dropdownElement = document.getElementById(openDropdown);
                if (dropdownElement && dropdownElement.classList.contains('show')) {
                    const customDropdown = dropdownElement.closest('.custom-dropdown');
                    const button = customDropdown ? customDropdown.querySelector('.dropdown-btn') : null;
                    if (button) {
                        const rect = button.getBoundingClientRect();
                        const tableContainer = button.closest('.table-scroll-container');
                        const containerRect = tableContainer ? tableContainer.getBoundingClientRect() : null;
                        dropdownElement.style.position = 'fixed';
                        const maxDropdownHeight = 300; 
                        const spaceBelow = window.innerHeight - rect.bottom;
                        const spaceAbove = rect.top;
                        const gap = 4; 
                        const minRequiredSpace = maxDropdownHeight + gap + 10; 
                        let positionAbove = false;
                        if (spaceBelow < minRequiredSpace && spaceAbove >= minRequiredSpace) {
                            positionAbove = true;
                            dropdownElement.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
                            dropdownElement.style.top = 'auto';
                        } else {
                            dropdownElement.style.top = (rect.bottom + gap) + 'px';
                            dropdownElement.style.bottom = 'auto';
                        }
                        const minDropdownWidth = window.innerWidth <= 576 ? 150 : 200;
                        const maxDropdownWidth = 400;
                        const padding = 10;
                        let leftPosition = rect.left;
                        let dropdownWidth = Math.max(rect.width, minDropdownWidth);
                        if (containerRect) {
                            const containerLeft = containerRect.left + padding;
                            const containerRight = containerRect.right - padding;
                            const maxWidthInContainer = containerRight - containerLeft;
                            dropdownWidth = Math.min(dropdownWidth, maxWidthInContainer, maxDropdownWidth);
                        } else {
                            dropdownWidth = Math.min(dropdownWidth, maxDropdownWidth);
                        }
                        dropdownWidth = Math.min(dropdownWidth, window.innerWidth - (padding * 2));
                        const rightEdge = leftPosition + dropdownWidth;
                        const screenRight = containerRect ? containerRect.right - padding : window.innerWidth - padding;
                        const screenLeft = containerRect ? containerRect.left + padding : padding;
                        if (rightEdge > screenRight) {
                            leftPosition = screenRight - dropdownWidth;
                        }
                        if (leftPosition < screenLeft) {
                            leftPosition = screenLeft;
                            if (leftPosition + dropdownWidth > screenRight) {
                                dropdownWidth = screenRight - leftPosition;
                            }
                        }
                        dropdownElement.style.left = leftPosition + 'px';
                        dropdownElement.style.width = dropdownWidth + 'px';
                        dropdownElement.style.minWidth = dropdownWidth + 'px';
                        dropdownElement.style.maxWidth = dropdownWidth + 'px';
                    }
                }
            }
        }
        window.addEventListener('scroll', repositionOpenDropdown, true);
        window.addEventListener('resize', repositionOpenDropdown);
        document.addEventListener('click', function(event) {
            const allDownloadDropdowns = document.querySelectorAll('.download-dropdown');
            allDownloadDropdowns.forEach(dropdown => {
                if (!dropdown.contains(event.target)) {
                    const menu = dropdown.querySelector('.download-dropdown-menu');
                    const btn = dropdown.querySelector('.btn-download');
                    if (menu) {
                        menu.classList.remove('show');
                    }
                    if (btn) {
                        btn.classList.remove('active');
                    }
                }
            });
            const assignDropdownContainer = document.querySelector('.assign-dropdown');
            if (assignDropdownContainer && !assignDropdownContainer.contains(event.target)) {
                const assignDropdown = document.getElementById('assignDropdown');
                const assignButton = assignDropdownContainer.querySelector('.btn-assign');
                if (assignDropdown) {
                    assignDropdown.classList.remove('show');
                }
                if (assignButton) {
                    assignButton.classList.remove('active');
                }
            }
            if (openDropdown && !event.target.closest('.custom-dropdown')) {
                const openDropdownElement = document.getElementById(openDropdown);
                if (openDropdownElement) {
                    openDropdownElement.classList.remove('show');
                    openDropdownElement.style.position = '';
                    openDropdownElement.style.top = '';
                    openDropdownElement.style.bottom = '';
                    openDropdownElement.style.left = '';
                    openDropdownElement.style.width = '';
                    openDropdown = null;
                }
            }
            const modals = [
                document.getElementById('assignQuotaModal'),
                document.getElementById('clearAllModal'),
                document.getElementById('downloadModal'),
                document.getElementById('saveModal'),
                document.getElementById('resetModal')
            ];
            modals.forEach(modal => {
                if (modal && event.target === modal) {
                    closeModal(modal);
                }
            });
        });
        window.onload = function () {
            closeNav();
        };
    </script>
</body>
</html>