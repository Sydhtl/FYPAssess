<?php
include '../../../php/mysqlConnect.php';
session_start();

// Prevent caching to avoid back button access after logout
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
<?php

$userId = $_SESSION['upmId'];
$coordinatorName = 'Coordinator';

// Try to get name from lecturer table (most coordinators are lecturers)
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

// Get filter values from URL or set defaults
$selectedYear = isset($_GET['year']) ? $_GET['year'] : '';
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : '';

// Fetch distinct FYP Sessions (years) from fyp_session table
$yearOptions = [];
$yearQuery = "SELECT DISTINCT FYP_Session FROM fyp_session ORDER BY FYP_Session DESC";
if ($yearResult = $conn->query($yearQuery)) {
    while ($row = $yearResult->fetch_assoc()) {
        $yearOptions[] = $row['FYP_Session'];
    }
    $yearResult->free();
}

// Set default year if not selected
if (empty($selectedYear) && !empty($yearOptions)) {
    $selectedYear = $yearOptions[0];
}

// Fetch distinct Semesters from fyp_session table
$semesterOptions = [];
$semesterQuery = "SELECT DISTINCT Semester FROM fyp_session ORDER BY Semester";
if ($semesterResult = $conn->query($semesterQuery)) {
    while ($row = $semesterResult->fetch_assoc()) {
        $semesterOptions[] = $row['Semester'];
    }
    $semesterResult->free();
}

// Set default semester if not selected
if (empty($selectedSemester) && !empty($semesterOptions)) {
    $selectedSemester = $semesterOptions[0];
}

// Get all FYP_Session_IDs for the selected year and semester in the coordinator's department
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

// Use only ONE FYP_Session_ID for display (not summing across multiple courses)
// But keep all FYP_Session_IDs for saving purposes
$displayFypSessionId = !empty($fypSessionIds) ? $fypSessionIds[0] : null;

// Fetch total student count from student table based on ONE FYP_Session_ID only
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

// Fetch supervisors (lecturers) from the same department with their quota history
// Use only ONE FYP_Session_ID for display (not summing - quota is the same for all courses)
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
                    'remaining_quota' => (int)$row['Quota'] // Will be calculated based on actual assignments
                ];
            }
        }
        $stmt->close();
    }
}

// Fetch assessor data (lecturer to assessor ID mapping) for JavaScript
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

// Encode lecturer data as JSON for JavaScript
$lecturerDataJson = json_encode($lecturerData);
$assessorDataJson = json_encode($assessorData);

// Fetch students for the selected year/semester across ALL matching FYP_Session_IDs
// (i.e., across all Course_IDs in the coordinator's department for that year/semester)
// Also load existing supervisor and assessor names from student_enrollment
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
        // Bind all FYP_Session_IDs and then userId
        $types = str_repeat('i', count($fypSessionIds)) . 's';
        $params = array_merge($fypSessionIds, [$userId]);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $seenCourses = [];
            while ($row = $result->fetch_assoc()) {
                // Build course filter options (unique Course_ID/Course_Code)
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

// Fetch assessment data (Course_ID and Assessment_Name mapping)
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
    // Prevent back button after logout
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
                <a href="#" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i>
                    Industry Collaboration</a>
                <a href="#" id="evaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
                <a href="#" id="superviseesReport"><i class="bi bi-bar-chart-fill icon-padding"></i> Supervisees' Report</a>
                <a href="#" id="logbookSubmission"><i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission</a>
            </div>

            <a href="#assessorMenu" class="role-header" data-role="assessor">
                <span class="role-text">Assessor</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-right arrow-icon"></i>
                </span>
            </a>

            <div id="assessorMenu" class="menu-items">
                <a href="#" id="Dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="#" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="EvaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
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
                <div id="courseCode">SWE4949</div>
                <div id="courseSession">2024/2025 - 2</div>
            </div>
        </div>
    </div>

    <div id="main" class="main-grid student-assignation-main">
        <h1 class="page-title">Student Assignment Page</h1>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filter-group">
                <label for="yearFilter">Year</label>
                <select id="yearFilter" onchange="reloadPageWithFilters()">
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
                <select id="semesterFilter" onchange="reloadPageWithFilters()">
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
                                    <span>Assign Remaining Quota</span>
                                </button>
                                <div class="button-group">
                                    <button class="btn btn-outline-dark" onclick="followPastQuota()" style="background-color: white; color: black; border-color: black;" onmouseover="this.style.backgroundColor='white'; this.style.color='black';" onmouseout="this.style.backgroundColor='white'; this.style.color='black';">Follow Past Quota</button>
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
        // --- FILTER RELOAD FUNCTION ---
        function reloadPageWithFilters() {
            const yearFilter = document.getElementById('yearFilter').value;
            const semesterFilter = document.getElementById('semesterFilter').value;
            
            // Build URL with query parameters
            const params = new URLSearchParams();
            if (yearFilter) params.append('year', yearFilter);
            if (semesterFilter) params.append('semester', semesterFilter);
            
            // Reload page with new parameters
            window.location.href = 'studentAssignation.php?' + params.toString();
        }

        // --- SIDEBAR FUNCTIONS ---
        var collapsedWidth = "60px";

        function openNav() {
            var fullWidth = "220px";
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
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
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
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

        // --- LECTURER QUOTA ASSIGNATION ---
        // Load lecturer data from PHP
        const lecturers = <?php echo $lecturerDataJson; ?>;
        
        let filteredLecturers = [...lecturers];

        // --- STUDENT DISTRIBUTION ---
        // Real student data from backend based on selected sessions and department
        const students = <?php echo $studentsDataJson; ?>;
        const courseFilterOptions = <?php echo $courseFilterOptionsJson ?? '[]'; ?>;
        const assessorData = <?php echo $assessorDataJson ?? '[]'; ?>;
        // Selected year & semester from PHP (used in exports)
        const selectedYear = <?php echo json_encode($selectedYear); ?>;
        const selectedSemester = <?php echo json_encode($selectedSemester); ?>;

        let filteredStudents = [...students];
        let currentCourseFilter = 'both'; // 'both' or specific Course_ID
        // total students for this year+semester across all courses is taken from the distribution list
        let totalStudents = students.length;

        // Follow past quota: fetch previous session/semester quotas and apply
        function followPastQuota() {
            try {
                const year = document.getElementById('yearFilter')?.value || selectedYear;
                const semester = document.getElementById('semesterFilter')?.value || selectedSemester;

                const payload = {
                    year: year,
                    semester: semester,
                    lecturer_ids: (lecturers || []).map(l => l.id)
                };

                // Show loading state
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
                        // Restore on error
                        if (remainingStudentEl) {
                            remainingStudentEl.textContent = oldText;
                            remainingStudentEl.style.opacity = '1';
                        }
                        return;
                    }
                    const map = new Map();
                    (data.quotas || []).forEach(q => map.set(String(q.supervisor_id), Number(q.quota)));

                    // Apply to current lecturers
                    (lecturers || []).forEach(l => {
                        const q = map.get(String(l.id));
                        if (typeof q === 'number' && !Number.isNaN(q)) {
                            l.quota = q;
                        }
                    });

                    // Refresh UI and remaining counts in real-time
                    updateAllRemainingQuotas();
                    renderLecturerTable();
                    
                    // Update remaining student count with animation
                    updateRemainingStudent();
                    if (remainingStudentEl) {
                        remainingStudentEl.style.opacity = '1';
                        remainingStudentEl.style.transition = 'opacity 0.3s ease-in-out';
                    }
                    
                    // Log the update for confirmation
                    console.log('Past quotas applied successfully. Remaining students: ' + (remainingStudentEl ? remainingStudentEl.textContent : 'N/A'));
                })
                .catch(err => {
                    console.error('Error fetching past quotas:', err);
                    // Restore on error
                    if (remainingStudentEl) {
                        remainingStudentEl.textContent = oldText;
                        remainingStudentEl.style.opacity = '1';
                    }
                })
                .finally(() => {
                    // Ensure opacity is reset
                    if (remainingStudentEl) {
                        remainingStudentEl.style.opacity = '1';
                    }
                });
            } catch (e) {
                console.error('followPastQuota() error:', e);
            }
        }
        let openDropdown = null; // Track which dropdown is currently open

        // --- ASSESSMENT SESSION ---
        // Assessment data from PHP (Course_ID -> Assessment_Name mapping)
        const assessmentData = <?php echo $assessmentDataJson; ?>;
        
        // Base venue options
        const baseVenueOptions = [
            'KP1 Lab',
            'iSpace,Block C',
            'Seminar Room A',
            'Putra Future Classroom',
        ];
        
        // Get all venue options (base + custom from localStorage)
        function getAllVenueOptions() {
            const customVenues = JSON.parse(localStorage.getItem('customVenues') || '[]');
            return [...baseVenueOptions, ...customVenues];
        }
        
        // Save custom venue to localStorage
        function addCustomVenue(venueName) {
            if (!venueName || venueName.trim() === '') return;
            const customVenues = JSON.parse(localStorage.getItem('customVenues') || '[]');
            if (!customVenues.includes(venueName.trim())) {
                customVenues.push(venueName.trim());
                localStorage.setItem('customVenues', JSON.stringify(customVenues));
            }
        }
        
        // Assessment session data - initialize from students with supervisor and assessor info
        // Will be loaded from database after page loads
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
            assessment_name: assessmentData[student.course_id] ? assessmentData[student.course_id][0] : 'Assessment' // Get first assessment name for the course
        }));
        
        // Load assessment session data from database
        function loadAssessmentSessionsFromDatabase() {
            fetch(`../../../php/phpCoordinator/fetch_assessment_sessions.php?year=${encodeURIComponent(selectedYear)}&semester=${encodeURIComponent(selectedSemester)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.sessions) {
                        // Update assessmentSessionData with data from database
                        data.sessions.forEach(session => {
                            const assessmentStudent = assessmentSessionData.find(s => String(s.id) === String(session.student_id));
                            if (assessmentStudent) {
                                assessmentStudent.date = session.date || '';
                                assessmentStudent.time = session.time || '';
                                assessmentStudent.venue = session.venue || '';
                            }
                        });
                        
                        // Update filtered students and re-render table
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
        let currentAssessmentCourseFilter = 'both'; // 'both' or specific Course_ID

        // Loading modal functions
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

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            renderLecturerTable();
            updateRemainingStudent();
            initializeTabs();
            initializeSearch();
            initializeRoleToggle();
            initializeStudentDistribution();
            initializeAssessmentSession();
            // Load assessment session data from database
            loadAssessmentSessionsFromDatabase();
        });

        // Render lecturer table
        function renderLecturerTable() {
            const tbody = document.getElementById('lecturerTableBody');
            tbody.innerHTML = '';

            filteredLecturers.forEach((lecturer, index) => {
                const row = document.createElement('tr');
                // Calculate remaining quota based on actual student assignments
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

        // Update quota for a lecturer
        function updateQuota(lecturerId, newQuota) {
            const lecturer = lecturers.find(l => l.id === lecturerId);
            if (lecturer) {
                const quotaValue = parseInt(newQuota) || 0;
                lecturer.quota = quotaValue;
                // Initialize remaining quota to quota when quota is set
                // Then update based on actual student assignments
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remaining_quota = quotaValue - assignedCount;
                updateRemainingQuota(lecturerId);
                updateRemainingStudent();
                // Re-render student table to update supervisor dropdowns
                if (document.querySelector('.task-group[data-group="distribution"].active')) {
                    renderStudentTable();
                }
            }
        }

        // Update remaining quota for a lecturer based on actual student assignments
        function updateRemainingQuota(lecturerId) {
            const lecturer = lecturers.find(l => l.id === lecturerId);
            if (lecturer) {
                // Count how many students have this lecturer as supervisor
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remaining_quota = lecturer.quota - assignedCount;
                
                const element = document.getElementById(`remaining-${lecturerId}`);
                if (element) {
                    element.textContent = lecturer.remaining_quota;
                }
            }
        }

        // Update all remaining quotas based on student distribution
        function updateAllRemainingQuotas() {
            lecturers.forEach(lecturer => {
                // Count how many students have this lecturer as supervisor
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                // Calculate remaining quota
                lecturer.remaining_quota = lecturer.quota - assignedCount;
                
                // Update display in lecturer quota table (if visible)
                const element = document.getElementById(`remaining-${lecturer.id}`);
                if (element) {
                    element.textContent = lecturer.remaining_quota;
                }
            });
        }

        // Update remaining student count
        function updateRemainingStudent() {
            const totalQuota = lecturers.reduce((sum, lecturer) => sum + (parseInt(lecturer.quota) || 0), 0);
            const remaining = Math.max(0, totalStudents - totalQuota);
            document.getElementById('remainingStudent').textContent = remaining;
        }

        // General Modal Functions
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

        // Assign Remaining Quota Modal
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

        // Clear All Modal
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

        // Download Success Modal
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

        // Save Success Modal
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

        // Reset Quotas Modal
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

        // Assign remaining quota to lecturers who haven't been filled (quota = 0)
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

            // Show confirmation modal
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

        // Perform the actual assignment
        function performAssignQuota() {
            const remaining = parseInt(document.getElementById('remainingStudent').textContent);
            const lecturersWithoutQuota = lecturers.filter(lecturer => lecturer.quota === 0);

            // Distribute remaining students evenly among lecturers without quota
            const quotaPerLecturer = Math.floor(remaining / lecturersWithoutQuota.length);
            const remainder = remaining % lecturersWithoutQuota.length;

            let distributed = 0;
            lecturersWithoutQuota.forEach((lecturer, index) => {
                const lecturerIndex = lecturers.findIndex(l => l.id === lecturer.id);
                
                if (lecturerIndex !== -1) {
                    // Give each lecturer base quota, and first few lecturers get +1 if there's remainder
                    const quotaToAssign = quotaPerLecturer + (index < remainder ? 1 : 0);
                    lecturers[lecturerIndex].quota = quotaToAssign;
                    // Initialize remaining quota to quota when quota is assigned
                    lecturers[lecturerIndex].remaining_quota = quotaToAssign;
                    distributed += quotaToAssign;
                    
                    // Update the input field
                    const input = document.querySelector(`input[data-lecturer-id="${lecturer.id}"]`);
                    if (input) {
                        input.value = quotaToAssign;
                    }
                    
                    updateRemainingQuota(lecturer.id);
                }
            });

            updateRemainingStudent();

            // Show success modal
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

        // Clear all quotas (set all to 0)
        function clearAllQuotas() {
            // Show confirmation modal
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

        // Perform the actual clear all
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
            // Re-render student table to update supervisor dropdowns
            if (document.querySelector('.task-group[data-group="distribution"].active')) {
                renderStudentTable();
            }

            // Show success modal
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

        // Reset quotas (cancel changes - reload page)
        function resetQuotas() {
            // Show confirmation modal
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
                // Reload the page to reset to original values
                location.reload();
            };
            openResetModal();
        }

        // Download as PDF (Lecturer Quota Assignation)
        function downloadAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                
                // Create PDF document
                const doc = new jsPDF();
                
                // Set font
                doc.setFont('helvetica');
                
                // Add title
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0); // #780000 color
                doc.text('Lecturer Quota Assignation Report', 14, 20);
                
                // Add summary information (Year, Semester, Course, totals)
                const courseLabel = getCurrentCourseLabel();
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0); // Black color
                doc.text(`Year: ${selectedYear}    Semester: ${selectedSemester}`, 14, 30);
                doc.text(`Course: ${courseLabel}`, 14, 36);
                doc.text(`Total Lecturer: ${lecturers.length}`, 14, 42);
                doc.text(`Total Students (all courses): ${totalStudents}`, 14, 48);
                doc.text(`Remaining Students: ${document.getElementById('remainingStudent').textContent}`, 14, 54);
                
                // Prepare table data
                const tableData = lecturers.map((lecturer, index) => [
                    index + 1,
                    lecturer.name,
                    String(lecturer.quota != null ? lecturer.quota : 0),
                    String(lecturer.remaining_quota != null ? lecturer.remaining_quota : 0)
                ]);
                
                // Add table using autoTable plugin
                doc.autoTable({
                    startY: 60,
                    head: [['No.', 'Name', 'Quota', 'Remaining Quota']],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [120, 0, 0], // #780000 color
                        textColor: [255, 255, 255], // White text
                        fontStyle: 'bold',
                        fontSize: 11
                    },
                    bodyStyles: {
                        fontSize: 10
                    },
                    alternateRowStyles: {
                        fillColor: [253, 240, 213] // #fdf0d5 color
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
                
                // Get final Y position after table
                const finalY = doc.lastAutoTable.finalY;
                
                // Add date and time at the bottom
                doc.setFontSize(9);
                doc.setTextColor(128, 128, 128); // Gray color
                const now = new Date();
                const dateTime = now.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                doc.text(`Generated on: ${dateTime}`, 14, finalY + 15);
                
                // Save PDF
                doc.save('lecturer-quota-assignation.pdf');

                // Show success modal
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
                
                // Show error modal
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

        // Download as Excel (Lecturer Quota Assignation)
        function downloadAsExcel() {
            // Build table data
            const tableData = lecturers.map((lecturer, index) => ({
                no: index + 1,
                name: lecturer.name,
                quota: lecturer.quota,
                remaining: lecturer.remainingQuota
            }));

            const courseLabel = getCurrentCourseLabel();

            // Create CSV content
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

            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'lecturer-quota-assignation.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Show success modal
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

        // Save quotas
        function saveQuotas() {
            // Get current year and semester from filters
            const year = document.getElementById('yearFilter').value;
            const semester = document.getElementById('semesterFilter').value;
            
            // Prepare quota data
            const quotaData = lecturers.map(lecturer => ({
                supervisor_id: lecturer.id,
                quota: lecturer.quota
            }));

            // Prepare request data
            const requestData = {
                year: year,
                semester: semester,
                quotas: quotaData
            };

            // Show loading state (optional - you can add a loading spinner here)
            
            // Make AJAX call to save quotas
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
                    // Update remaining quotas after successful save
                    updateAllRemainingQuotas();
                    
                    // Re-render student table if Student Distribution tab is active
                    if (document.querySelector('.task-group[data-group="distribution"].active')) {
                        renderStudentTable();
                    }
                    
                    // Also re-render lecturer table to update remaining quota display
                    renderLecturerTable();
                    
                    // Show success modal
                    showSaveSuccess(data.is_latest);
                } else {
                    // Show error modal
                    showSaveError(data.message || 'Failed to save quotas');
                }
            })
            .catch(error => {
                console.error('Error saving quotas:', error);
                showSaveError('An error occurred while saving quotas. Please try again.');
            });
        }

        // Show save success modal
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

        // Show save error modal
        function showSaveError(errorMessage) {
            saveModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeSaveModal">&times;</span>
                        <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="modal-title-custom">Save Failed</div>
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

        // Initialize tabs
        function initializeTabs() {
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.getAttribute('data-tab');

                    // Update active tab style
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');

                    // Switch active task group
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            group.classList.add('active');
                            // Render assessment table if switching to assessment tab
                            if (tabName === 'assessment') {
                                // Sync assessment session data with current student distribution
                                syncAssessmentSessionData();
                                // Reload assessment session data from database to show current state
                                loadAssessmentSessionsFromDatabase();
                            }
                        } else {
                            group.classList.remove('active');
                        }
                    });
                    
                    // Close all dropdowns when switching tabs
                    closeAllDropdowns();
                });
            });
        }
        
        // Close all dropdowns helper
        function closeAllDropdowns() {
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.btn-download.active').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.assign-dropdown-menu.show').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.assign-dropdown .btn-assign.active').forEach(btn => btn.classList.remove('active'));
            // Close date filter dropdown
            closeAssessmentDateFilterDropdown();
            if (openDropdown) {
                const openDropdownElement = document.getElementById(openDropdown);
                if (openDropdownElement) {
                    openDropdownElement.classList.remove('show');
                    openDropdown = null;
                }
            }
        }

        // Initialize search
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

        // --- STUDENT DISTRIBUTION FUNCTIONS ---
        
        // Initialize Student Distribution
        function initializeStudentDistribution() {
            // Update remaining quotas based on current student assignments
            updateAllRemainingQuotas();
            // Default: show all courses
            currentCourseFilter = 'both';
            applyStudentFilters();
            initializeStudentSearch();
            updateTotalStudentCount();
            // Ensure event listeners are attached
            attachDropdownEventListeners();

            // Initialize custom course filter dropdown
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

        // Get display label for current course filter (for headers/exports)
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

        // Apply search + course filters to students
        function applyStudentFilters() {
            const courseIdFilter = currentCourseFilter;
            const searchInput = document.getElementById('studentSearch');
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

            filteredStudents = students.filter(student => {
                // Course filter
                if (courseIdFilter !== 'both') {
                    if (String(student.course_id) !== String(courseIdFilter)) {
                        return false;
                    }
                }
                // Name search filter
                if (searchTerm) {
                    return (student.name || '').toLowerCase().includes(searchTerm);
                }
                return true;
            });

            renderStudentTable();
        }

        // Toggle course filter dropdown
        function toggleCourseFilterDropdown() {
            const dropdown = document.getElementById('courseFilterMenu');
            const button = document.querySelector('.course-filter-dropdown .btn-download');

            // Close other download dropdowns
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

        // Render student table
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
            
            // After rendering, attach event listeners using event delegation for better reliability
            attachDropdownEventListeners();
        }
        
        // Attach event listeners using event delegation
        function attachDropdownEventListeners() {
            // Use event delegation for dropdown options
            const tbody = document.getElementById('studentTableBody');
            if (tbody) {
                // Remove existing listener if any
                tbody.removeEventListener('click', handleDropdownOptionClick);
                // Add new listener
                tbody.addEventListener('click', handleDropdownOptionClick);
            }
        }
        
        // Handle dropdown option clicks
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

        // Generate lecturer options for dropdown
        function generateLecturerOptions(studentId, role, excludeSupervisor) {
            const student = students.find(s => s.id === studentId);
            if (!student) {
                console.error('Student not found:', studentId);
                return '<div class="dropdown-option disabled">Student not found</div>';
            }

            // Ensure lecturers array exists and has data
            if (!lecturers || lecturers.length === 0) {
                console.error('No lecturers available');
                return '<div class="dropdown-option disabled">No lecturers available</div>';
            }

            let options = '';
            let optionCount = 0;
            
            lecturers.forEach(lecturer => {
                if (!lecturer || !lecturer.name) {
                    return; // Skip invalid lecturer entries
                }

                // For supervisor role, check quota and remaining quota
                if (role === 'supervisor') {
                    // Calculate remaining quota on the fly to ensure accuracy
                    // Count how many students have this lecturer as supervisor
                    const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                    const currentRemainingQuota = Math.max(0, lecturer.quota - assignedCount);
                    
                    // Show lecturer if:
                    // 1. Lecturer has quota > 0 AND remaining quota > 0
                    // OR 2. This lecturer is already selected as supervisor for this student (to allow changing/keeping)
                    // OR 3. Lecturer has quota = 0 but is already selected (edge case)
                    if (lecturer.quota <= 0 && student.supervisor !== lecturer.name) {
                        return; // No quota assigned and not currently selected
                    }
                    
                    if (currentRemainingQuota <= 0 && student.supervisor !== lecturer.name) {
                        return; // No remaining quota and not currently selected
                    }
                }
                
                // For assessors, show all lecturers (no quota restriction)
                // But exclude certain lecturers based on other rules below
                
                // Exclude supervisor from assessor dropdowns
                if (excludeSupervisor && lecturer.name === excludeSupervisor) {
                    return;
                }
                
                // Exclude already selected assessors from other assessor dropdown
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

                // Use data attributes for better reliability instead of inline onclick
                // Escape HTML to prevent XSS
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

        // Toggle lecturer dropdown
        function toggleLecturerDropdown(studentId, role) {
            // Convert studentId to string for consistency
            const studentIdStr = String(studentId);
            const dropdownId = `dropdown-${role}-${studentIdStr}`;
            const dropdown = document.getElementById(dropdownId);

            if (!dropdown) {
                console.error('Dropdown not found:', dropdownId);
                return;
            }

            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });

            // Toggle current dropdown
            const isOpening = !dropdown.classList.contains('show');
            dropdown.classList.toggle('show');
            
            if (dropdown.classList.contains('show')) {
                openDropdown = dropdownId;
                
                // Position dropdown using fixed positioning to float above table
                const customDropdown = dropdown.closest('.custom-dropdown');
                const button = customDropdown ? customDropdown.querySelector('.dropdown-btn') : null;
                if (button) {
                    const rect = button.getBoundingClientRect();
                    const tableContainer = button.closest('.table-scroll-container');
                    const containerRect = tableContainer ? tableContainer.getBoundingClientRect() : null;
                    
                    dropdown.style.position = 'fixed';
                    
                    // Check if there's enough space below, otherwise position above
                    const maxDropdownHeight = 300; // Match CSS max-height
                    const spaceBelow = window.innerHeight - rect.bottom;
                    const spaceAbove = rect.top;
                    const gap = 4; // Gap between button and dropdown
                    const minRequiredSpace = maxDropdownHeight + gap + 10; // Add some buffer
                    
                    let positionAbove = false;
                    if (spaceBelow < minRequiredSpace && spaceAbove >= minRequiredSpace) {
                        // Not enough space below but enough above - position above
                        positionAbove = true;
                        // Position dropdown above: bottom edge of dropdown is (button top - gap) from top of viewport
                        // In fixed positioning, bottom = window.innerHeight - (rect.top - gap)
                        dropdown.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
                        dropdown.style.top = 'auto';
                    } else {
                        // Default: position below
                        dropdown.style.top = (rect.bottom + gap) + 'px';
                        dropdown.style.bottom = 'auto';
                    }
                    
                    // Calculate available width - adjust for screen size
                    const minDropdownWidth = window.innerWidth <= 576 ? 150 : 200;
                    const maxDropdownWidth = 400;
                    const padding = 10; // Padding from edges
                    
                    let leftPosition = rect.left;
                    let dropdownWidth = Math.max(rect.width, minDropdownWidth);
                    
                    // Constrain width to container if available
                    if (containerRect) {
                        const containerLeft = containerRect.left + padding;
                        const containerRight = containerRect.right - padding;
                        const maxWidthInContainer = containerRight - containerLeft;
                        dropdownWidth = Math.min(dropdownWidth, maxWidthInContainer, maxDropdownWidth);
                    } else {
                        dropdownWidth = Math.min(dropdownWidth, maxDropdownWidth);
                    }
                    
                    // Also constrain to viewport width to prevent overflow
                    dropdownWidth = Math.min(dropdownWidth, window.innerWidth - (padding * 2));
                    
                    // Adjust position to stay within container/screen bounds
                    const rightEdge = leftPosition + dropdownWidth;
                    const screenRight = containerRect ? containerRect.right - padding : window.innerWidth - padding;
                    const screenLeft = containerRect ? containerRect.left + padding : padding;
                    
                    // If dropdown would exceed right edge, shift it left
                    if (rightEdge > screenRight) {
                        leftPosition = screenRight - dropdownWidth;
                    }
                    
                    // If dropdown would exceed left edge, align to left
                    if (leftPosition < screenLeft) {
                        leftPosition = screenLeft;
                        // Adjust width if needed to fit
                        if (leftPosition + dropdownWidth > screenRight) {
                            dropdownWidth = screenRight - leftPosition;
                        }
                    }
                    
                    dropdown.style.left = leftPosition + 'px';
                    dropdown.style.width = dropdownWidth + 'px';
                    dropdown.style.minWidth = dropdownWidth + 'px';
                    dropdown.style.maxWidth = dropdownWidth + 'px';
                }
                
                // Update remaining quotas before showing dropdown to ensure accurate options
                updateAllRemainingQuotas();
                
                // Refresh the options in the dropdown to show current remaining quota
                // Convert studentId to match the type used in students array
                const student = students.find(s => String(s.id) === studentIdStr || s.id === studentId);
                const excludeSupervisor = role === 'supervisor' ? null : (student ? student.supervisor : null);
                const optionsContainer = document.getElementById(`options-${role}-${studentIdStr}`);
                
                if (optionsContainer) {
                    const newOptions = generateLecturerOptions(studentId, role, excludeSupervisor);
                    optionsContainer.innerHTML = newOptions;
                } else {
                    console.error('Options container not found:', `options-${role}-${studentIdStr}`);
                }
                
                // Reset search when opening
                const searchInput = dropdown.querySelector('.dropdown-search input');
                if (searchInput) {
                    searchInput.value = '';
                    filterDropdownLecturers(studentId, role, '');
                }
            } else {
                openDropdown = null;
                // Reset positioning when closed
                dropdown.style.position = '';
                dropdown.style.top = '';
                dropdown.style.left = '';
                dropdown.style.width = '';
            }
        }

        // Filter dropdown lecturers
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

        // Select lecturer for student
        function selectLecturer(studentId, role, lecturerName) {
            // Convert studentId to string for consistency in finding student
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

            // Store previous supervisor to handle quota updates correctly
            const previousSupervisor = student.supervisor;

            // Update student data
            if (role === 'supervisor') {
                // If changing supervisor, update quotas
                student.supervisor = lecturerName;
                // Clear assessors if they were the supervisor
                if (student.assessor1 === lecturerName) {
                    student.assessor1 = null;
                }
                if (student.assessor2 === lecturerName) {
                    student.assessor2 = null;
                }
            } else if (role === 'assessor1') {
                // Don't allow if it's the supervisor
                if (student.supervisor === lecturerName) {
                    return;
                }
                // Don't allow if it's assessor2
                if (student.assessor2 === lecturerName) {
                    return;
                }
                student.assessor1 = lecturerName;
            } else if (role === 'assessor2') {
                // Don't allow if it's the supervisor
                if (student.supervisor === lecturerName) {
                    return;
                }
                // Don't allow if it's assessor1
                if (student.assessor1 === lecturerName) {
                    return;
                }
                student.assessor2 = lecturerName;
            }

            // Update remaining quotas based on actual student assignments
            // This will recalculate remaining quota for all lecturers
            updateAllRemainingQuotas();

            // Close dropdown
            const dropdownId = `dropdown-${role}-${studentId}`;
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.remove('show');
                openDropdown = null;
            }

            // Re-render the entire table to update all dropdowns with new remaining quotas
            // This ensures other rows show updated available lecturers
            renderStudentTable();
        }


        // Clear all assignments
        function clearAllAssignments() {
            // Show confirmation modal
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

        // Perform clear all assignments
        function performClearAllAssignments() {
            students.forEach(student => {
                student.supervisor = null;
                student.assessor1 = null;
                student.assessor2 = null;
            });
            
            // Update remaining quotas after clearing
            updateAllRemainingQuotas();
            renderStudentTable();

            // Show success modal
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

        // Assign remaining automatically with type: 'supervisor', 'assessor', or 'both'
        function assignAutomatically(type) {
            let message = '';
            if (type === 'supervisor') {
                message = 'This will automatically assign remaining supervisors to students. Students will be grouped into 3 groups. Continue?';
            } else if (type === 'assessor') {
                message = 'This will automatically assign remaining assessors to students. Each student will be assessed by supervisors from the other 2 groups. If insufficient, any available lecturer (excluding supervisor) will be assigned. Continue?';
            } else {
                message = 'This will automatically assign remaining supervisors and assessors. Students will be grouped into 3 groups, with each group assessed by supervisors from the other 2 groups. If insufficient, any available lecturer (excluding supervisor) will be assigned. Continue?';
            }

            // Show confirmation modal
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

        // Perform automatic assignment
        function performAutoAssign(type) {
            // Get lecturers who have quota > 0 (assigned students)
            const lecturersWithQuota = lecturers.filter(l => l.quota > 0);
            
            // Get all lecturers (for assessor assignment when insufficient)
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

            if (type === 'supervisor' || type === 'both') {
                // Assign supervisors RESPECTING lecturer quotas
                // 1. Recalculate remaining quotas based on current assignments
                updateAllRemainingQuotas();

                // 2. Build a working list of lecturers with remaining quota > 0
                let supervisorsWithRemaining = lecturersWithQuota
                    .map(l => ({
                        ref: l,
                        remaining: l.remaining_quota || 0
                    }))
                    .filter(x => x.remaining > 0);

                if (supervisorsWithRemaining.length === 0) {
                    // No available quota to assign supervisors
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

                // 3. Assign supervisors only to students who don't have one yet
                const studentsNeedingSupervisor = students.filter(s => !s.supervisor);
                let supIndex = 0;

                studentsNeedingSupervisor.forEach(student => {
                    // Find next lecturer with remaining quota > 0
                    let attempts = 0;
                    let assigned = false;
                    while (attempts < supervisorsWithRemaining.length) {
                        const idx = supIndex % supervisorsWithRemaining.length;
                        const supEntry = supervisorsWithRemaining[idx];
                        if (supEntry.remaining > 0) {
                            // Assign this supervisor
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
                    // If we couldn't assign anyone (all quotas exhausted), stop trying
                    if (!assigned) {
                        return;
                    }
                });

                // 4. After assignment, update remaining_quota for display
                updateAllRemainingQuotas();
            }

            if (type === 'assessor' || type === 'both') {
                // Assign assessors based on 3-group logic
                // Group 1 is assessed by Group 2 and Group 3 supervisors
                // Group 2 is assessed by Group 1 and Group 3 supervisors
                // Group 3 is assessed by Group 1 and Group 2 supervisors
                // If insufficient lecturers in other groups, use any available lecturer (excluding supervisor)
                
                // Determine which group each student belongs to based on their supervisor
                let lecturerGroups = [];
                let studentGroups = [[], [], []]; // 3 groups for students
                
                if (lecturersWithQuota.length >= 3) {
                    // Group lecturers into 3 groups
                    const groupSize = Math.floor(lecturersWithQuota.length / 3);
                    for (let i = 0; i < 3; i++) {
                        const start = i * groupSize;
                        const end = i === 2 ? lecturersWithQuota.length : (i + 1) * groupSize;
                        lecturerGroups.push(lecturersWithQuota.slice(start, end).map(l => l.name));
                    }
                } else {
                    // Less than 3 lecturers - create groups with available lecturers
                    for (let i = 0; i < lecturersWithQuota.length; i++) {
                        lecturerGroups.push([lecturersWithQuota[i].name]);
                    }
                    // Fill remaining groups with empty arrays
                    while (lecturerGroups.length < 3) {
                        lecturerGroups.push([]);
                    }
                }
                
                // Assign students to groups based on their supervisor
                students.forEach((student, index) => {
                    if (student.supervisor) {
                        // Find which group the supervisor belongs to
                        let supervisorGroupIndex = -1;
                        for (let i = 0; i < lecturerGroups.length; i++) {
                            if (lecturerGroups[i].includes(student.supervisor)) {
                                supervisorGroupIndex = i;
                                break;
                            }
                        }
                        
                        // If supervisor is found in a group, assign student to that group
                        // Otherwise, assign based on student index
                        const groupIndex = supervisorGroupIndex >= 0 ? supervisorGroupIndex : (index % 3);
                        studentGroups[groupIndex].push(student);
                    } else {
                        // No supervisor assigned, assign based on student index
                        studentGroups[index % 3].push(student);
                    }
                });
                
                // Now assign assessors: each group is assessed by supervisors from the other 2 groups
                studentGroups.forEach((group, groupIndex) => {
                    // Get supervisors from the other 2 groups
                    const otherGroupIndices = [0, 1, 2].filter(idx => idx !== groupIndex);
                    const assessorGroup1 = lecturerGroups[otherGroupIndices[0]] || [];
                    const assessorGroup2 = lecturerGroups[otherGroupIndices[1]] || [];
                    
                    group.forEach((student, studentIndexInGroup) => {
                        // Try to assign from other groups first
                        if (assessorGroup1.length > 0 && assessorGroup2.length > 0) {
                            // Both groups have supervisors
                            const assessor1Index = studentIndexInGroup % assessorGroup1.length;
                            const assessor2Index = studentIndexInGroup % assessorGroup2.length;
                            student.assessor1 = assessorGroup1[assessor1Index];
                            student.assessor2 = assessorGroup2[assessor2Index];
                        } else if (assessorGroup1.length > 0) {
                            // Only first group has supervisors
                            const assessor1Index = studentIndexInGroup % assessorGroup1.length;
                            student.assessor1 = assessorGroup1[assessor1Index];
                            
                            // For assessor2, use any available lecturer (excluding supervisor and assessor1)
                            let availableForAssessor2 = allLecturers.filter(l => 
                                l !== student.supervisor && l !== student.assessor1
                            );
                            if (availableForAssessor2.length > 0) {
                                student.assessor2 = availableForAssessor2[studentIndexInGroup % availableForAssessor2.length];
                            } else {
                                student.assessor2 = null;
                            }
                        } else if (assessorGroup2.length > 0) {
                            // Only second group has supervisors
                            const assessor2Index = studentIndexInGroup % assessorGroup2.length;
                            student.assessor2 = assessorGroup2[assessor2Index];
                            
                            // For assessor1, use any available lecturer (excluding supervisor and assessor2)
                            let availableForAssessor1 = allLecturers.filter(l => 
                                l !== student.supervisor && l !== student.assessor2
                            );
                            if (availableForAssessor1.length > 0) {
                                student.assessor1 = availableForAssessor1[studentIndexInGroup % availableForAssessor1.length];
                            } else {
                                student.assessor1 = null;
                            }
                        } else {
                            // Neither group has supervisors - use any available lecturer (excluding supervisor)
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
                                
                                // Make sure assessor2 is different from assessor1
                                if (assessor2Index === assessor1Index) {
                                    assessor2Index = (studentIndexInGroup + 2) % availableLecturers.length;
                                }
                                
                                student.assessor1 = availableLecturers[assessor1Index];
                                if (assessor2Index !== assessor1Index) {
                                    student.assessor2 = availableLecturers[assessor2Index];
                                } else {
                                    // Find a different lecturer
                                    const otherLecturers = availableLecturers.filter((_, idx) => idx !== assessor1Index);
                                    student.assessor2 = otherLecturers.length > 0 ? otherLecturers[0] : null;
                                }
                            }
                        }
                    });
                });
            }

            // Update remaining quotas after assignment
            updateAllRemainingQuotas();
            renderStudentTable();

            // Show success modal
            let successMessage = '';
            if (type === 'supervisor') {
                successMessage = 'Remaining supervisors have been automatically assigned to students. Students are grouped into 3 groups.';
            } else if (type === 'assessor') {
                successMessage = 'Remaining assessors have been automatically assigned. Students are grouped into 3 groups, with each group assessed by supervisors from the other 2 groups. If insufficient lecturers were available, any available lecturer (excluding supervisor) was assigned.';
            } else {
                successMessage = 'Remaining supervisors and assessors have been automatically assigned. Students are grouped into 3 groups, with each group assessed by supervisors from the other 2 groups. If insufficient lecturers were available, any available lecturer (excluding supervisor) was assigned.';
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

        // Initialize student search
        function initializeStudentSearch() {
            const searchInput = document.getElementById('studentSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function() {
                applyStudentFilters();
            });
        }

        // Update total student count
        function updateTotalStudentCount() {
            const countElement = document.getElementById('totalStudentCount');
            if (countElement) {
                // show how many students are currently visible in the distribution table
                countElement.textContent = filteredStudents.length;
            }
            // keep the top summary widget in sync with total students across all courses
            const totalTop = document.getElementById('totalStudents');
            if (totalTop) {
                totalTop.textContent = totalStudents;
            }
        }

        // Reset assignments
        function resetAssignments() {
            // Show confirmation modal
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

        // Helper: get supervisor ID by lecturer name from lecturers array
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

        // Helper: get assessor ID by lecturer name from assessor data
        function getAssessorIdByName(lecturerName) {
            if (!lecturerName || !assessorData || assessorData.length === 0) return null;
            const match = assessorData.find(a => a && a.name === lecturerName);
            return match ? match.assessor_id : null;
        }

        // Save assignments
        function saveAssignments() {
            // Build payload with both names and IDs for supervisor/assessors
            const assignmentData = students.map(student => {
                const supervisorId = getSupervisorIdByName(student.supervisor);
                // Use getAssessorIdByName for assessors (not getSupervisorIdByName)
                const assessor1Id = getAssessorIdByName(student.assessor1);
                const assessor2Id = getAssessorIdByName(student.assessor2);

                // Debug logging
                if (student.supervisor) {
                    console.log(`Student: ${student.id}, Supervisor: ${student.supervisor}, Supervisor_ID: ${supervisorId}`);
                }

                return {
                    id: student.id,
                    name: student.name,
                    supervisor: student.supervisor,
                    assessor1: student.assessor1,
                    assessor2: student.assessor2,
                    supervisor_id: supervisorId,
                    assessor1_id: assessor1Id,
                    assessor2_id: assessor2Id
                };
            });

            // Show loading modal
            showLoadingModal('Saving assignments and sending emails. Please wait.');

            // Debug: log the full assignment payload
            console.log('Assignment payload:', JSON.stringify(assignmentData, null, 2));

            // Make AJAX call to save assignments to backend
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
                    // On success, optionally refresh quotas and table
                    updateAllRemainingQuotas();
                    renderStudentTable();

                    // Show success modal
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
                    // Show error using existing error modal helper
                    showSaveError(data.message || 'Failed to save student assignments.');
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

        // Download Distribution as PDF
        function downloadDistributionAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                
                const doc = new jsPDF();
                doc.setFont('helvetica');
                
                // Add title
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0);
                doc.text('Student Distribution Report', 14, 20);
                
                // Add year, semester, and course summary (follow current page filter)
                const courseLabel = getCurrentCourseLabel();
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Year: ${selectedYear}    Semester: ${selectedSemester}`, 14, 32);
                doc.text(`Course: ${courseLabel}`, 14, 38);
                doc.text(`Total Students (current view): ${filteredStudents.length}`, 14, 44);
                
                // Prepare table data from currently visible students (filteredStudents)
                const tableData = filteredStudents.map((student, index) => [
                    index + 1,
                    student.name,
                    student.supervisor || '-',
                    student.assessor1 || '-',
                    student.assessor2 || '-'
                ]);
                
                // Add table
                doc.autoTable({
                    startY: 52,
                    head: [['No.', 'Name', 'Supervisor', 'Assessor 1', 'Assessor 2']],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [120, 0, 0],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        fontSize: 11
                    },
                    bodyStyles: {
                        fontSize: 9
                    },
                    alternateRowStyles: {
                        fillColor: [253, 240, 213]
                    },
                    styles: {
                        cellPadding: 4,
                        fontSize: 9
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
                
                doc.save('student-distribution.pdf');

                // Show success modal
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

        // Download Distribution as Excel
        function downloadDistributionAsExcel() {
            // Create CSV content based on current view (filteredStudents)
            const courseLabel = getCurrentCourseLabel();
            let csvContent = '';
            // Header info
            csvContent += `Year,${selectedYear}\n`;
            csvContent += `Semester,${selectedSemester}\n`;
            csvContent += `Course,${courseLabel}\n`;
            csvContent += `Total Students (current view),${filteredStudents.length}\n\n`;

            // Table header
            csvContent += 'No.,Name,Supervisor,Assessor 1,Assessor 2\n';
            
            filteredStudents.forEach((student, index) => {
                const sup = student.supervisor || '-';
                const a1  = student.assessor1 || '-';
                const a2  = student.assessor2 || '-';
                // Quote the text fields to be safe
                csvContent += `${index + 1},"${student.name || ''}","${sup}","${a1}","${a2}"\n`;
            });

            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'student-distribution.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Show success modal
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

        // --- ASSESSMENT SESSION FUNCTIONS ---
        
        // Initialize Assessment Session
        function initializeAssessmentSession() {
            currentAssessmentCourseFilter = 'both';
            applyAssessmentFilters();
            initializeAssessmentSearch();
            updateAssessmentStudentCount();
            
            // Initialize course filter dropdown
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
            
            // Initialize date filter dropdown
            populateDateSortDropdown();
            currentAssessmentDateFilter = ''; // Default to "All Dates"
            document.getElementById('assessmentDateFilterLabel').textContent = 'All Dates';
        }

        // Track selected date filter
        let currentAssessmentDateFilter = ''; // '' for "All Dates" or specific date string

        // Apply search + course + date filters to assessment students
        function applyAssessmentFilters() {
            const courseIdFilter = currentAssessmentCourseFilter;
            const searchInput = document.getElementById('assessmentStudentSearch');
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
            const dateFilter = currentAssessmentDateFilter;

            filteredAssessmentStudents = assessmentSessionData.filter(student => {
                // Course filter
                if (courseIdFilter !== 'both') {
                    if (String(student.course_id) !== String(courseIdFilter)) {
                        return false;
                    }
                }
                // Name search filter
                if (searchTerm) {
                    if (!(student.name || '').toLowerCase().includes(searchTerm)) {
                        return false;
                    }
                }
                // Date filter
                if (dateFilter) {
                    if (student.date !== dateFilter) {
                        return false;
                    }
                }
                return true;
            });

            // Repopulate date dropdown when filters change
            populateDateSortDropdown();
            
            renderAssessmentTable();
            updateAssessmentStudentCount();
            // Apply sorting after filtering
            sortAssessmentTable();
        }

        // Initialize assessment search
        function initializeAssessmentSearch() {
            const searchInput = document.getElementById('assessmentStudentSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function() {
                applyAssessmentFilters();
            });
        }

        // Render assessment table
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

            // Get all venue options (including custom ones)
            const venueOptions = getAllVenueOptions();

            filteredAssessmentStudents.forEach((student, index) => {
                const row = document.createElement('tr');
                const studentId = student.id;
                
                // Build venue dropdown with search and add custom option
                const currentVenue = student.venue || '';
                const isCustomVenue = currentVenue && !venueOptions.includes(currentVenue);
                
                // If student has a custom venue not in the list, add it temporarily
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

        // Update assessment date
        function updateAssessmentDate(studentId, date) {
            const student = assessmentSessionData.find(s => String(s.id) === String(studentId));
            if (student) {
                student.date = date;
                // Repopulate date dropdown when date changes
                populateDateSortDropdown();
            }
        }

        // Update assessment time
        function updateAssessmentTime(studentId, time) {
            const student = assessmentSessionData.find(s => String(s.id) === String(studentId));
            if (student) {
                student.time = time;
            }
        }

        // Handle venue select dropdown
        function handleVenueSelect(studentId, value) {
            if (value === '__CUSTOM__') {
                // Show custom input field
                const customInput = document.getElementById(`venue-custom-${studentId}`);
                const select = document.getElementById(`venue-select-${studentId}`);
                if (customInput && select) {
                    customInput.style.display = 'block';
                    customInput.focus();
                    select.value = ''; // Reset select
                }
            } else {
                // Hide custom input and update venue
                const customInput = document.getElementById(`venue-custom-${studentId}`);
                if (customInput) {
                    customInput.style.display = 'none';
                    customInput.value = '';
                }
                updateAssessmentVenue(studentId, value);
            }
        }
        
        // Handle custom venue input
        function handleCustomVenueInput(studentId) {
            const customInput = document.getElementById(`venue-custom-${studentId}`);
            const select = document.getElementById(`venue-select-${studentId}`);
            
            if (!customInput || !select) return;
            
            const customVenue = customInput.value.trim();
            if (customVenue) {
                // Add to localStorage
                addCustomVenue(customVenue);
                
                // Update student venue
                updateAssessmentVenue(studentId, customVenue);
                
                // Update select dropdown to include new venue
                const venueOptions = getAllVenueOptions();
                let optionsHTML = '<option value="">Select Venue</option>';
                venueOptions.forEach(venue => {
                    const selected = customVenue === venue ? 'selected' : '';
                    optionsHTML += `<option value="${venue}" ${selected}>${venue}</option>`;
                });
                optionsHTML += '<option value="__CUSTOM__">+ Add Custom Venue</option>';
                select.innerHTML = optionsHTML;
                select.value = customVenue;
                
                // Hide custom input
                customInput.style.display = 'none';
                customInput.value = '';
                
                // Re-render the entire table to update all venue dropdowns with the new venue
                renderAssessmentTable();
            } else {
                // If empty, hide input and reset select
                customInput.style.display = 'none';
                select.value = '';
            }
        }
        
        // Update assessment venue
        function updateAssessmentVenue(studentId, venue) {
            const student = assessmentSessionData.find(s => String(s.id) === String(studentId));
            if (student) {
                student.venue = venue;
            }
        }

        // Update assessment student count
        function updateAssessmentStudentCount() {
            const countElement = document.getElementById('assessmentStudentCount');
            if (countElement) {
                countElement.textContent = filteredAssessmentStudents.length;
            }
        }

        // Toggle assessment course filter dropdown
        function toggleAssessmentCourseFilterDropdown() {
            const dropdown = document.getElementById('assessmentCourseFilterMenu');
            const button = document.querySelector('.course-filter-dropdown .btn-download');

            // Close other download dropdowns
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

        // Populate date sort dropdown with unique dates from assessment session data
        function populateDateSortDropdown() {
            const dateFilterMenu = document.getElementById('assessmentDateFilterMenu');
            if (!dateFilterMenu) return;
            
            // Get all unique dates from assessment session data
            const uniqueDates = [...new Set(assessmentSessionData
                .map(s => s.date)
                .filter(d => d))].sort();
            
            // Clear existing options except "All Dates"
            dateFilterMenu.innerHTML = '<a href="javascript:void(0)" class="download-option" data-date-value=""><span>All Dates</span></a>';
            
            // Add date options
            uniqueDates.forEach(date => {
                const option = document.createElement('a');
                option.href = 'javascript:void(0)';
                option.className = 'download-option';
                option.setAttribute('data-date-value', date);
                
                // Format date for display (e.g., "2024-01-15" -> "Jan 15, 2024")
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
            
            // Attach click handlers to date options
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
        
        // Toggle assessment date filter dropdown
        function toggleAssessmentDateFilterDropdown() {
            const dropdown = document.getElementById('assessmentDateFilterMenu');
            const button = document.querySelector('.sort-section .course-filter-dropdown .btn-download');

            // Close other download dropdowns
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

        // Sort assessment table
        let assessmentSortOrder = 'asc'; // 'asc' or 'desc'
        function sortAssessmentTable() {
            // Note: Date filtering is now done in applyAssessmentFilters()
            // This function only handles sorting
            
            // Sort by date (from start to end), then by time, then by name
            filteredAssessmentStudents.sort((a, b) => {
                let comparison = 0;
                
                // Sort by date first
                if (a.date && b.date) {
                    comparison = a.date.localeCompare(b.date);
                } else if (a.date && !b.date) {
                    comparison = -1;
                } else if (!a.date && b.date) {
                    comparison = 1;
                } else {
                    comparison = 0;
                }
                
                // If same date, sort by time
                if (comparison === 0 && a.time && b.time) {
                    comparison = a.time.localeCompare(b.time);
                } else if (comparison === 0 && a.time && !b.time) {
                    comparison = -1;
                } else if (comparison === 0 && !a.time && b.time) {
                    comparison = 1;
                }
                
                // If same date and time, sort by name
                if (comparison === 0) {
                    comparison = (a.name || '').localeCompare(b.name || '');
                }
                
                return assessmentSortOrder === 'asc' ? comparison : -comparison;
            });
            
            renderAssessmentTable();
        }

        // Assign Assessment Sessions Modal
        var assignAssessmentModal = document.createElement('div');
        assignAssessmentModal.className = 'custom-modal';
        assignAssessmentModal.id = 'assignAssessmentModal';
        document.body.appendChild(assignAssessmentModal);

        function openAssignAssessmentModal() {
            // Count students without complete assessment session data
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

            // Get all venue options for selection
            const allVenueOptions = getAllVenueOptions();
            
            // Build venue selection checkboxes
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
            
            // Show modal with date range inputs and venue selection
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

            // Handle "Select All" checkbox
            const selectAllCheckbox = assignAssessmentModal.querySelector('#selectAllVenues');
            const venueCheckboxes = assignAssessmentModal.querySelectorAll('.venue-checkbox');
            
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    venueCheckboxes.forEach(cb => {
                        cb.checked = selectAllCheckbox.checked;
                    });
                });
            }
            
            // Handle individual checkbox changes
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
                
                // Get selected venues
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

        // Sync assessment session data with current student distribution data
        // This ensures assessment sessions use the latest supervisor and assessor assignments
        function syncAssessmentSessionData() {
            assessmentSessionData.forEach(assessmentStudent => {
                const currentStudent = students.find(s => String(s.id) === String(assessmentStudent.id));
                if (currentStudent) {
                    // Update supervisor and assessors from current student distribution
                    assessmentStudent.supervisor = currentStudent.supervisor;
                    assessmentStudent.assessor1 = currentStudent.assessor1;
                    assessmentStudent.assessor2 = currentStudent.assessor2;
                }
            });
        }

        // Perform automatic assignment of assessment sessions following student distribution 3-group logic
        function performAssignAssessmentSessions(startDate, endDate, selectedVenues = null) {
            // IMPORTANT: Sync assessmentSessionData with current students data
            // This ensures we use the latest supervisor and assessor assignments from student distribution
            syncAssessmentSessionData();
            
            // Get students without complete assessment session data
            const studentsNeedingAssignment = assessmentSessionData.filter(s => !s.date || !s.time || !s.venue);
            
            if (studentsNeedingAssignment.length === 0) {
                closeAssignAssessmentModal();
                return;
            }

            // Get unique supervisors from students who need assignment
            const supervisorNames = [...new Set(studentsNeedingAssignment
                .map(s => s.supervisor)
                .filter(s => s))];
            
            if (supervisorNames.length === 0) {
                alert('No supervisors found for students needing assignment. Please assign supervisors first.');
                closeAssignAssessmentModal();
                return;
            }

            // Use the SAME 3-group logic as student distribution
            // Group supervisors into 3 groups (same as student distribution logic)
            let lecturerGroups = [];
            if (supervisorNames.length >= 3) {
                // Group supervisors into 3 groups
                const groupSize = Math.floor(supervisorNames.length / 3);
                for (let i = 0; i < 3; i++) {
                    const start = i * groupSize;
                    const end = i === 2 ? supervisorNames.length : (i + 1) * groupSize;
                    lecturerGroups.push(supervisorNames.slice(start, end));
                }
            } else {
                // Less than 3 supervisors - create groups with available supervisors
                for (let i = 0; i < supervisorNames.length; i++) {
                    lecturerGroups.push([supervisorNames[i]]);
                }
                // Fill remaining groups with empty arrays
                while (lecturerGroups.length < 3) {
                    lecturerGroups.push([]);
                }
            }

            // Group students by their supervisor's group (same as student distribution)
            // This ensures we follow the student distribution grouping
            const studentGroupsByDistribution = [[], [], []]; // 3 groups matching student distribution
            
            studentsNeedingAssignment.forEach(student => {
                if (student.supervisor) {
                    // Find which group the supervisor belongs to (same logic as student distribution)
                    let supervisorGroupIndex = -1;
                    for (let i = 0; i < lecturerGroups.length; i++) {
                        if (lecturerGroups[i].includes(student.supervisor)) {
                            supervisorGroupIndex = i;
                            break;
                        }
                    }
                    // Assign student to supervisor's group (matching student distribution)
                    const groupIndex = supervisorGroupIndex >= 0 ? supervisorGroupIndex : 0;
                    studentGroupsByDistribution[groupIndex].push(student);
                } else {
                    // No supervisor, assign to first group
                    studentGroupsByDistribution[0].push(student);
                }
            });

            // Now group students by supervisor within each distribution group
            // All students with the same supervisor must present on the same day, same venue
            // All students in the same 3-supervisor group (from distribution) must present on the same day
            const assessmentGroups = [];
            
            studentGroupsByDistribution.forEach((distributionGroup, distGroupIndex) => {
                if (distributionGroup.length === 0) return;
                
                // Get supervisors from the other 2 groups (for assessor reference)
                const otherGroupIndices = [0, 1, 2].filter(idx => idx !== distGroupIndex);
                const assessorGroup1 = lecturerGroups[otherGroupIndices[0]] || [];
                const assessorGroup2 = lecturerGroups[otherGroupIndices[1]] || [];
                
                // Group students by supervisor within this distribution group
                const studentsBySupervisor = {};
                distributionGroup.forEach(student => {
                    const supervisorName = student.supervisor || 'No Supervisor';
                    if (!studentsBySupervisor[supervisorName]) {
                        studentsBySupervisor[supervisorName] = [];
                    }
                    studentsBySupervisor[supervisorName].push(student);
                });
                
                // All students in this distribution group (from all supervisors) should be on the same day
                // because they assess each other (assessors are already assigned from student_enrollment)
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

            // Venue options - use selected venues if provided, otherwise use all available venues
            const venueOptions = selectedVenues && selectedVenues.length > 0 
                ? selectedVenues 
                : getAllVenueOptions();

            // Helper function to get time slots based on day of week
            // Each presentation is 20 minutes, back-to-back (no gap)
            // Break from 1 PM to 2 PM (13:00 to 14:00)
            // Friday: only until 12 PM (12:00)
            function getTimeSlotsForDate(dateStr) {
                const date = new Date(dateStr);
                const dayOfWeek = date.getDay(); // 0 = Sunday, 5 = Friday
                const isFriday = dayOfWeek === 5;
                
                // Morning slots: 9:00 to 12:00 (every 20 minutes)
                const morningSlots = [
                    '09:00', '09:20', '09:40', '10:00', '10:20', '10:40',
                    '11:00', '11:20', '11:40', '12:00'
                ];
                
                // Afternoon slots: 2:00 PM (14:00) to 5:00 PM (17:00) (every 20 minutes)
                const afternoonSlots = [
                    '14:00', '14:20', '14:40', '15:00', '15:20', '15:40',
                    '16:00', '16:20', '16:40', '17:00'
                ];
                
                if (isFriday) {
                    // Friday: only morning slots until 12:00
                    return morningSlots;
                } else {
                    // Other days: morning + afternoon (with break from 1 PM to 2 PM)
                    return [...morningSlots, ...afternoonSlots];
                }
            }
            
            // Get all possible time slots (will be filtered by date later)
            const allTimeSlots = [
                '09:00', '09:20', '09:40', '10:00', '10:20', '10:40',
                '11:00', '11:20', '11:40', '12:00',
                '14:00', '14:20', '14:40', '15:00', '15:20', '15:40',
                '16:00', '16:20', '16:40', '17:00'
            ];

            // Convert dates to Date objects for calculation
            const start = new Date(startDate);
            const end = new Date(endDate);
            const daysDiff = Math.ceil((end - start) / (1000 * 60 * 60 * 24)) + 1;

            // Track assessor schedules to avoid conflicts
            // Format: { assessorName: { date_time: true } }
            const assessorSchedule = {};
            
            // Track venue schedules to avoid conflicts
            // Format: { venue: { date_time: true } }
            const venueSchedule = {};

            // Initialize schedules with existing assignments (to avoid conflicts with already assigned sessions)
            assessmentSessionData.forEach(student => {
                if (student.date && student.time) {
                    const dateTimeKey = `${student.date}_${student.time}`;
                    
                    // Add existing assessor assignments
                    if (student.assessor1) {
                        if (!assessorSchedule[student.assessor1]) {
                            assessorSchedule[student.assessor1] = {};
                        }
                        assessorSchedule[student.assessor1][dateTimeKey] = student.time; // Store time for overlap checking
                    }
                    if (student.assessor2) {
                        if (!assessorSchedule[student.assessor2]) {
                            assessorSchedule[student.assessor2] = {};
                        }
                        assessorSchedule[student.assessor2][dateTimeKey] = student.time; // Store time for overlap checking
                    }
                    
                    // Add existing venue assignments
                    if (student.venue) {
                        if (!venueSchedule[student.venue]) {
                            venueSchedule[student.venue] = {};
                        }
                        venueSchedule[student.venue][dateTimeKey] = student.time; // Store time for overlap checking
                    }
                }
            });

            // Helper function to convert time string to minutes for comparison
            function timeToMinutes(timeStr) {
                const [hours, minutes] = timeStr.split(':').map(Number);
                return hours * 60 + minutes;
            }
            
            // Helper function to check if two time slots overlap
            // Each presentation is 20 minutes, so we need to check if times overlap within 20 minutes
            function timesOverlap(time1, time2) {
                const minutes1 = timeToMinutes(time1);
                const minutes2 = timeToMinutes(time2);
                const presentationDuration = 20; // 20 minutes
                
                // Check if time1 overlaps with time2's presentation window
                // time2's window: time2 to time2 + 20 minutes
                return (minutes1 >= minutes2 && minutes1 < minutes2 + presentationDuration) ||
                       (minutes2 >= minutes1 && minutes2 < minutes1 + presentationDuration);
            }
            
            // Helper function to check if a slot conflicts with existing assignments
            function hasConflict(date, time, assessor1, assessor2, venue) {
                // Check assessor conflicts - need to check for overlapping times, not just exact matches
                if (assessor1 && assessorSchedule[assessor1]) {
                    for (const existingTimeKey in assessorSchedule[assessor1]) {
                        const [existingDate, existingTime] = existingTimeKey.split('_');
                        if (existingDate === date && timesOverlap(time, existingTime)) {
                            return true;
                        }
                    }
                }
                if (assessor2 && assessorSchedule[assessor2]) {
                    for (const existingTimeKey in assessorSchedule[assessor2]) {
                        const [existingDate, existingTime] = existingTimeKey.split('_');
                        if (existingDate === date && timesOverlap(time, existingTime)) {
                            return true;
                        }
                    }
                }
                
                // Check venue conflicts - venues can't overlap at the same time
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

            // Helper function to reserve a slot
            // Store the actual time (not just date_time key) to check for overlaps
            function reserveSlot(date, time, assessor1, assessor2, venue) {
                const dateTimeKey = `${date}_${time}`;
                
                if (assessor1) {
                    if (!assessorSchedule[assessor1]) {
                        assessorSchedule[assessor1] = {};
                    }
                    assessorSchedule[assessor1][dateTimeKey] = time; // Store time for overlap checking
                }
                
                if (assessor2) {
                    if (!assessorSchedule[assessor2]) {
                        assessorSchedule[assessor2] = {};
                    }
                    assessorSchedule[assessor2][dateTimeKey] = time; // Store time for overlap checking
                }
                
                if (venue) {
                    if (!venueSchedule[venue]) {
                        venueSchedule[venue] = {};
                    }
                    venueSchedule[venue][dateTimeKey] = time; // Store time for overlap checking
                }
            }

            // Assign assessment sessions following student distribution groups
            // Use assessors already assigned from student_enrollment table
            // All students in the same distribution group (3 supervisors) present on the same day
            // All students with the same supervisor present together (same day, same venue, one after another)
            
            // Calculate total students needing assignment for fair distribution
            const totalStudentsNeedingAssignment = assessmentGroups.reduce((sum, group) => sum + group.students.length, 0);
            const studentsPerDay = Math.ceil(totalStudentsNeedingAssignment / daysDiff);
            
            // Track how many students have been assigned to each day for fair distribution
            const studentsPerDayCount = new Array(daysDiff).fill(0);
            
            // Track which dates have been assigned at least one student
            const datesWithStudents = new Set();
            
            // Fairly distribute groups across days, ensuring even distribution AND every date gets at least one student
            assessmentGroups.forEach((assessmentGroup, groupIndex) => {
                if (assessmentGroup.students.length === 0) return;
                
                const { students, studentsBySupervisor, supervisors } = assessmentGroup;
                
                // Find the day with the least students assigned so far
                // Prioritize dates that have zero students to ensure every date gets at least one
                let minDayIndex = 0;
                let minCount = studentsPerDayCount[0];
                let hasZeroStudents = studentsPerDayCount[0] === 0;
                
                for (let d = 0; d < daysDiff; d++) {
                    // If this date has zero students, prioritize it
                    if (studentsPerDayCount[d] === 0 && !hasZeroStudents) {
                        minDayIndex = d;
                        minCount = 0;
                        hasZeroStudents = true;
                    } else if (!hasZeroStudents && studentsPerDayCount[d] < minCount) {
                        minCount = studentsPerDayCount[d];
                        minDayIndex = d;
                    }
                }
                
                // Use the day with least students (prioritizing dates with zero students)
                // This ensures every date gets at least one student
                const dayIndex = minDayIndex;
                const currentDate = new Date(start);
                currentDate.setDate(start.getDate() + dayIndex);
                const targetDate = currentDate.toISOString().split('T')[0];
                
                // Update count for this day
                studentsPerDayCount[dayIndex] += students.length;
                datesWithStudents.add(targetDate);
                
                // Try to find a slot for this entire group on the target date
                let groupSlot = null;
                const timeSlotsForDate = getTimeSlotsForDate(targetDate);
                
                // Try each venue
                for (let v = 0; v < venueOptions.length && !groupSlot; v++) {
                    const venue = venueOptions[v];
                    
                    // Try to find a starting time that can accommodate ALL students from this group
                    // Need consecutive time slots for all students in the group
                    for (let startTimeIndex = 0; startTimeIndex <= timeSlotsForDate.length - students.length && !groupSlot; startTimeIndex++) {
                        // Check if we can assign consecutive slots starting from this index
                        let canAssign = true;
                        const tempAssessorSchedule = {};
                        
                        // Check each student from this group
                        for (let i = 0; i < students.length; i++) {
                            const student = students[i];
                            const timeIndex = startTimeIndex + i;
                            
                            if (timeIndex >= timeSlotsForDate.length) {
                                canAssign = false;
                                break;
                            }
                            
                            const time = timeSlotsForDate[timeIndex];
                            
                            // Use assessors already assigned from student_enrollment table
                            const assessor1 = student.assessor1;
                            const assessor2 = student.assessor2;
                            
                            // Check conflicts with existing schedule (from other groups or already assigned sessions)
                            if (hasConflict(targetDate, time, assessor1, assessor2, venue)) {
                                canAssign = false;
                                break;
                            }
                            
                            // Check conflicts within this group's students (assessors can't have overlapping times)
                            // This ensures no assessor is in two places at once
                            if (assessor1 && tempAssessorSchedule[assessor1]) {
                                for (const existingTimeKey in tempAssessorSchedule[assessor1]) {
                                    const [existingDate, existingTime] = existingTimeKey.split('_');
                                    if (existingDate === targetDate && timesOverlap(time, existingTime)) {
                                        canAssign = false;
                                        break;
                                    }
                                }
                                if (!canAssign) break;
                            }
                            if (assessor2 && tempAssessorSchedule[assessor2]) {
                                for (const existingTimeKey in tempAssessorSchedule[assessor2]) {
                                    const [existingDate, existingTime] = existingTimeKey.split('_');
                                    if (existingDate === targetDate && timesOverlap(time, existingTime)) {
                                        canAssign = false;
                                        break;
                                    }
                                }
                                if (!canAssign) break;
                            }
                            
                            // Reserve in temp schedule
                            const dateTimeKey = `${targetDate}_${time}`;
                            if (assessor1) {
                                if (!tempAssessorSchedule[assessor1]) tempAssessorSchedule[assessor1] = {};
                                tempAssessorSchedule[assessor1][dateTimeKey] = time;
                            }
                            if (assessor2) {
                                if (!tempAssessorSchedule[assessor2]) tempAssessorSchedule[assessor2] = {};
                                tempAssessorSchedule[assessor2][dateTimeKey] = time;
                            }
                        }
                        
                        if (canAssign) {
                            groupSlot = {
                                date: targetDate,
                                venue: venue,
                                startTimeIndex: startTimeIndex
                            };
                        }
                    }
                }
                
                // If no slot found on target date, try other dates (fallback)
                if (!groupSlot) {
                    for (let d = 0; d < daysDiff && !groupSlot; d++) {
                        const currentDate = new Date(start);
                        currentDate.setDate(start.getDate() + d);
                        const dateStr = currentDate.toISOString().split('T')[0];
                        const timeSlotsForDate = getTimeSlotsForDate(dateStr);
                        
                        for (let v = 0; v < venueOptions.length && !groupSlot; v++) {
                            const venue = venueOptions[v];
                            
                            for (let startTimeIndex = 0; startTimeIndex <= timeSlotsForDate.length - students.length && !groupSlot; startTimeIndex++) {
                                let canAssign = true;
                                const tempAssessorSchedule = {};
                                
                                for (let i = 0; i < students.length; i++) {
                                    const student = students[i];
                                    const timeIndex = startTimeIndex + i;
                                    
                                    if (timeIndex >= timeSlotsForDate.length) {
                                        canAssign = false;
                                        break;
                                    }
                                    
                                    const time = timeSlotsForDate[timeIndex];
                                    // Use assessors already assigned from student_enrollment table
                                    const assessor1 = student.assessor1;
                                    const assessor2 = student.assessor2;
                                    
                                    if (hasConflict(dateStr, time, assessor1, assessor2, venue)) {
                                        canAssign = false;
                                        break;
                                    }
                                    
                                    if (assessor1 && tempAssessorSchedule[assessor1]) {
                                        for (const existingTimeKey in tempAssessorSchedule[assessor1]) {
                                            const [existingDate, existingTime] = existingTimeKey.split('_');
                                            if (existingDate === dateStr && timesOverlap(time, existingTime)) {
                                                canAssign = false;
                                                break;
                                            }
                                        }
                                        if (!canAssign) break;
                                    }
                                    if (assessor2 && tempAssessorSchedule[assessor2]) {
                                        for (const existingTimeKey in tempAssessorSchedule[assessor2]) {
                                            const [existingDate, existingTime] = existingTimeKey.split('_');
                                            if (existingDate === dateStr && timesOverlap(time, existingTime)) {
                                                canAssign = false;
                                                break;
                                            }
                                        }
                                        if (!canAssign) break;
                                    }
                                    
                                    const dateTimeKey = `${dateStr}_${time}`;
                                    if (assessor1) {
                                        if (!tempAssessorSchedule[assessor1]) tempAssessorSchedule[assessor1] = {};
                                        tempAssessorSchedule[assessor1][dateTimeKey] = time;
                                    }
                                    if (assessor2) {
                                        if (!tempAssessorSchedule[assessor2]) tempAssessorSchedule[assessor2] = {};
                                        tempAssessorSchedule[assessor2][dateTimeKey] = time;
                                    }
                                }
                                
                                if (canAssign) {
                                    groupSlot = {
                                        date: dateStr,
                                        venue: venue,
                                        startTimeIndex: startTimeIndex
                                    };
                                }
                            }
                        }
                    }
                }
                
                // Assign consecutive time slots to ALL students from this distribution group
                // Students with the same supervisor are grouped together and present one after another
                if (groupSlot) {
                    const timeSlotsForDate = getTimeSlotsForDate(groupSlot.date);
                    
                    // Sort students by supervisor so students with same supervisor are together
                    const sortedStudents = [...students].sort((a, b) => {
                        const supA = a.supervisor || '';
                        const supB = b.supervisor || '';
                        return supA.localeCompare(supB);
                    });
                    
                    sortedStudents.forEach((student, studentIndex) => {
                        // Use assessors already assigned from student_enrollment table
                        const assessor1 = student.assessor1;
                        const assessor2 = student.assessor2;
                        
                        const timeIndex = groupSlot.startTimeIndex + studentIndex;
                        const time = timeSlotsForDate[timeIndex];
                        
                        // ALL students from this distribution group have same date and venue
                        // Students with same supervisor are consecutive (one after another)
                        student.date = groupSlot.date;
                        student.time = time;
                        student.venue = groupSlot.venue;
                        
                        // Reserve the slot (assessors are already assigned from student_enrollment)
                        reserveSlot(groupSlot.date, time, assessor1, assessor2, groupSlot.venue);
                    });
                } else {
                    // If no group slot found, assign individually (fallback)
                    // Still try to keep students with same supervisor together
                    students.forEach((student, studentIndexInGroup) => {
                        // Use assessors already assigned from student_enrollment table
                        const assessor1 = student.assessor1;
                        const assessor2 = student.assessor2;
                        
                        // Try to find a non-conflicting slot
                        let assigned = false;
                        for (let d = 0; d < daysDiff && !assigned; d++) {
                            const currentDate = new Date(start);
                            currentDate.setDate(start.getDate() + d);
                            const dateStr = currentDate.toISOString().split('T')[0];
                            const timeSlotsForDate = getTimeSlotsForDate(dateStr);
                            
                            for (let t = 0; t < timeSlotsForDate.length && !assigned; t++) {
                                const time = timeSlotsForDate[t];
                                
                                for (let v = 0; v < venueOptions.length && !assigned; v++) {
                                    const venue = venueOptions[v];
                                    
                                    if (!hasConflict(dateStr, time, assessor1, assessor2, venue)) {
                                        student.date = dateStr;
                                        student.time = time;
                                        student.venue = venue;
                                        
                                        // Reserve the slot (assessors are already assigned from student_enrollment)
                                        reserveSlot(dateStr, time, assessor1, assessor2, venue);
                                        assigned = true;
                                    }
                                }
                            }
                        }
                        
                        // If still not assigned, assign the first available slot from first day
                        if (!assigned) {
                            const currentDate = new Date(start);
                            const dateStr = currentDate.toISOString().split('T')[0];
                            const timeSlotsForDate = getTimeSlotsForDate(dateStr);
                            student.date = dateStr;
                            student.time = timeSlotsForDate.length > 0 ? timeSlotsForDate[0] : '09:00';
                            student.venue = venueOptions[0];
                            
                            // Reserve the slot (assessors are already assigned from student_enrollment)
                            reserveSlot(dateStr, student.time, assessor1, assessor2, venueOptions[0]);
                        }
                    });
                }
            });

            // Final check: Ensure every date in the range has at least one student assigned
            // If any date has zero students, assign at least one student to it
            for (let d = 0; d < daysDiff; d++) {
                const currentDate = new Date(start);
                currentDate.setDate(start.getDate() + d);
                const dateStr = currentDate.toISOString().split('T')[0];
                
                // Check if this date has any students assigned
                const studentsOnThisDate = assessmentSessionData.filter(s => s.date === dateStr && s.time && s.venue);
                
                if (studentsOnThisDate.length === 0) {
                    // This date has no students - find an unassigned student and assign them to this date
                    const unassignedStudent = assessmentSessionData.find(s => !s.date || !s.time || !s.venue);
                    
                    if (unassignedStudent) {
                        const timeSlotsForDate = getTimeSlotsForDate(dateStr);
                        const assessor1 = unassignedStudent.assessor1;
                        const assessor2 = unassignedStudent.assessor2;
                        
                        // Try to find a slot for this student on this date
                        let assigned = false;
                        for (let t = 0; t < timeSlotsForDate.length && !assigned; t++) {
                            const time = timeSlotsForDate[t];
                            
                            for (let v = 0; v < venueOptions.length && !assigned; v++) {
                                const venue = venueOptions[v];
                                
                                if (!hasConflict(dateStr, time, assessor1, assessor2, venue)) {
                                    unassignedStudent.date = dateStr;
                                    unassignedStudent.time = time;
                                    unassignedStudent.venue = venue;
                                    
                                    // Reserve the slot
                                    reserveSlot(dateStr, time, assessor1, assessor2, venue);
                                    assigned = true;
                                }
                            }
                        }
                        
                        // If still not assigned, assign to first available slot (force assignment)
                        if (!assigned && timeSlotsForDate.length > 0) {
                            unassignedStudent.date = dateStr;
                            unassignedStudent.time = timeSlotsForDate[0];
                            unassignedStudent.venue = venueOptions[0];
                            
                            // Reserve the slot
                            reserveSlot(dateStr, unassignedStudent.time, assessor1, assessor2, venueOptions[0]);
                        }
                    }
                }
            }

            // Repopulate date dropdown after assignment
            populateDateSortDropdown();
            
            // Re-render table to show updated data
            renderAssessmentTable();
            closeAssignAssessmentModal();

            // Show success modal
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

        // Clear all assessment sessions
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

        // Perform clear all assessment sessions
        function performClearAllAssessmentSessions() {
            assessmentSessionData.forEach(student => {
                student.date = '';
                student.time = '';
                student.venue = '';
            });
            
            // Repopulate date dropdown after clearing
            populateDateSortDropdown();
            
            renderAssessmentTable();

            // Show success modal
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

        // Reset assessment sessions
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

        // Save assessment sessions
        function saveAssessmentSessions() {
            // Prepare assessment session data with all required information
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

            // Show loading modal
            showLoadingModal('Saving assessment sessions and sending emails. Please wait.');

            // Make AJAX call to save assessment sessions
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
                    // Reload assessment session data from database after saving
                    loadAssessmentSessionsFromDatabase();
                    
                    // Show success modal
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
                    showSaveError(data.message || 'Failed to save assessment session data.');
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

        // Download Assessment as PDF
        function downloadAssessmentAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                
                const doc = new jsPDF();
                doc.setFont('helvetica');
                
                // Add title
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0);
                doc.text('Assessment Session Report', 14, 20);
                
                // Add year, semester, and course summary
                const courseLabel = getAssessmentCourseLabel();
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Year: ${selectedYear}    Semester: ${selectedSemester}`, 14, 32);
                doc.text(`Course: ${courseLabel}`, 14, 38);
                doc.text(`Total Students (current view): ${filteredAssessmentStudents.length}`, 14, 44);
                
                // Prepare table data
                const tableData = filteredAssessmentStudents.map((student, index) => [
                    index + 1,
                    student.name,
                    student.date || '-',
                    student.time || '-',
                    student.venue || '-'
                ]);
                
                // Add table
                doc.autoTable({
                    startY: 52,
                    head: [['No.', 'Student Name', 'Date', 'Time', 'Venue']],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [120, 0, 0],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        fontSize: 11
                    },
                    bodyStyles: {
                        fontSize: 9
                    },
                    alternateRowStyles: {
                        fillColor: [253, 240, 213]
                    },
                    styles: {
                        cellPadding: 4,
                        fontSize: 9
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
                
                doc.save('assessment-session.pdf');

                // Show success modal
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

        // Download Assessment as Excel
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

            // Show success modal
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

        // Get display label for current assessment course filter
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

        // Toggle assessment download dropdown
        function toggleAssessmentDownloadDropdown() {
            const dropdown = document.getElementById('assessmentDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            
            // Close all other dropdowns
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


        // Toggle assign dropdown
        function toggleAssignDropdown() {
            const dropdown = document.getElementById('assignDropdown');
            const button = document.querySelector('.assign-dropdown .btn-assign');
            
            // Close all other dropdowns
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

        // Toggle distribution download dropdown
        function toggleDistributionDownloadDropdown() {
            const dropdown = document.getElementById('distributionDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            
            // Close all other dropdowns
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

        // Initialize role toggle (same as dashboard)
        function initializeRoleToggle() {
            // Store the active menu item ID to restore when menu expands again
            let activeMenuItemId = 'studentAssignation';
            
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
        }

        // Download Dropdown Functions
        function toggleDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.btn-download');
            
            // Close all other dropdowns if any
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                    const btn = menu.previousElementSibling;
                    if (btn && btn.classList.contains('btn-download')) {
                        btn.classList.remove('active');
                    }
                }
            });
            
            // Toggle current dropdown
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

        // Function to reposition open dropdown (used on scroll/resize)
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
                        
                        // Check if there's enough space below, otherwise position above
                        const maxDropdownHeight = 300; // Match CSS max-height
                        const spaceBelow = window.innerHeight - rect.bottom;
                        const spaceAbove = rect.top;
                        const gap = 4; // Gap between button and dropdown
                        const minRequiredSpace = maxDropdownHeight + gap + 10; // Add some buffer
                        
                        let positionAbove = false;
                        if (spaceBelow < minRequiredSpace && spaceAbove >= minRequiredSpace) {
                            // Not enough space below but enough above - position above
                            positionAbove = true;
                            // Position dropdown above: bottom edge of dropdown is (button top - gap) from top of viewport
                            // In fixed positioning, bottom = window.innerHeight - (rect.top - gap)
                            dropdownElement.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
                            dropdownElement.style.top = 'auto';
                        } else {
                            // Default: position below
                            dropdownElement.style.top = (rect.bottom + gap) + 'px';
                            dropdownElement.style.bottom = 'auto';
                        }
                        
                        // Calculate available width - adjust for screen size
                        const minDropdownWidth = window.innerWidth <= 576 ? 150 : 200;
                        const maxDropdownWidth = 400;
                        const padding = 10;
                        
                        let leftPosition = rect.left;
                        let dropdownWidth = Math.max(rect.width, minDropdownWidth);
                        
                        // Constrain width to container if available
                        if (containerRect) {
                            const containerLeft = containerRect.left + padding;
                            const containerRight = containerRect.right - padding;
                            const maxWidthInContainer = containerRight - containerLeft;
                            dropdownWidth = Math.min(dropdownWidth, maxWidthInContainer, maxDropdownWidth);
                        } else {
                            dropdownWidth = Math.min(dropdownWidth, maxDropdownWidth);
                        }
                        
                        // Also constrain to viewport width to prevent overflow
                        dropdownWidth = Math.min(dropdownWidth, window.innerWidth - (padding * 2));
                        
                        // Adjust position to stay within container/screen bounds
                        const rightEdge = leftPosition + dropdownWidth;
                        const screenRight = containerRect ? containerRect.right - padding : window.innerWidth - padding;
                        const screenLeft = containerRect ? containerRect.left + padding : padding;
                        
                        // If dropdown would exceed right edge, shift it left
                        if (rightEdge > screenRight) {
                            leftPosition = screenRight - dropdownWidth;
                        }
                        
                        // If dropdown would exceed left edge, align to left
                        if (leftPosition < screenLeft) {
                            leftPosition = screenLeft;
                            // Adjust width if needed to fit
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
        
        // Add scroll and resize handlers to reposition dropdown
        window.addEventListener('scroll', repositionOpenDropdown, true);
        window.addEventListener('resize', repositionOpenDropdown);

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            // Handle all download dropdowns (including course filter)
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

            // Handle assign dropdown
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

            // Handle custom lecturer dropdowns
            if (openDropdown && !event.target.closest('.custom-dropdown')) {
                const openDropdownElement = document.getElementById(openDropdown);
                if (openDropdownElement) {
                    openDropdownElement.classList.remove('show');
                    // Reset positioning
                    openDropdownElement.style.position = '';
                    openDropdownElement.style.top = '';
                    openDropdownElement.style.bottom = '';
                    openDropdownElement.style.left = '';
                    openDropdownElement.style.width = '';
                    openDropdown = null;
                }
            }
            


            // Handle modals - close when clicking on modal backdrop
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

        // Ensure the collapsed state is set immediately on page load
        window.onload = function () {
            closeNav();
        };
    </script>
</body>
</html>

