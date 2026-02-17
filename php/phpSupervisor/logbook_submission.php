<?php
session_start();
include '../db_connect.php';

// 1. CAPTURE ROLE & USER ID
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'supervisor';

// 2. PREPARE MODULE TITLE
$moduleTitle = ucfirst($activeRole) . " Module";

// 3. FETCH CURRENT SEMESTER INFO (Session Year & Semester)
$courseCode = "SWE4949"; // <--- ADD THIS LINE (Generic code for both A & B)
$currentSessionYear = "";
$currentSemester = "";
$sessionIDs = []; // Array to hold IDs for both Course A and B

// First, find the latest session by FYP_Session_ID
$sqlInfo = "SELECT FYP_Session_ID, FYP_Session, Semester 
            FROM fyp_session 
            ORDER BY FYP_Session_ID DESC 
            LIMIT 1";

$resInfo = $conn->query($sqlInfo);
if ($resInfo && $resInfo->num_rows > 0) {
    $row = $resInfo->fetch_assoc();
    $latestSessionID = $row['FYP_Session_ID'];
    $currentSessionYear = $row['FYP_Session'];
    $currentSemester = $row['Semester'];
    $courseSession = $currentSessionYear . " - " . $currentSemester;

    // Now, fetch ALL Session IDs for this semester (both A and B courses)
    $sqlIDs = "SELECT FYP_Session_ID FROM fyp_session 
               WHERE FYP_Session = '$currentSessionYear' 
               AND Semester = '$currentSemester'
               ORDER BY FYP_Session_ID DESC";
    
    $resIDs = $conn->query($sqlIDs);
    while($rowID = $resIDs->fetch_assoc()) {
        $sessionIDs[] = $rowID['FYP_Session_ID'];
    }
}

// A. Get Login ID 
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

// --- 2. DATA FETCHING ---
$groupedData = [
    'SWE4949A' => [], 
    'SWE4949B' => []
];

// B. GET SUPERVISOR ID
$supervisorID = 0;
$sqlSup = "SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ? LIMIT 1";
$stmtSup = $conn->prepare($sqlSup);
$stmtSup->bind_param("s", $loginID);
$stmtSup->execute();
$resSup = $stmtSup->get_result();
if ($rowSup = $resSup->fetch_assoc()) {
    $supervisorID = $rowSup['Supervisor_ID'];
}
$stmtSup->close();

// B. FETCH STUDENTS & LOGBOOKS
if ($supervisorID > 0 && !empty($sessionIDs)) {
    
    // Create a comma-separated string of IDs for the query: e.g., "10,11"
    $idsPlaceholder = implode(',', array_fill(0, count($sessionIDs), '?'));
    $types = "i" . str_repeat('i', count($sessionIDs)); // e.g., "iii"
    
    // Fetch all students with ALL their logbooks
    // Display based on Course_ID: Course 1 = SWE4949A, Course 2 = SWE4949B
    $sql = "SELECT 
                se.Student_ID,
                s.Student_Name,
                l.Logbook_ID,
                l.Logbook_Name,
                l.Logbook_Date,
                l.Logbook_Status,
                l.Course_ID as Logbook_Course_ID
            FROM student_enrollment se
            JOIN student s ON se.Student_ID = s.Student_ID AND se.FYP_Session_ID = s.FYP_Session_ID
            LEFT JOIN logbook l ON se.Student_ID = l.Student_ID
            WHERE se.Supervisor_ID = ? 
            AND se.FYP_Session_ID IN ($idsPlaceholder) 
            ORDER BY se.Student_ID, l.Logbook_Date DESC";

    $stmt = $conn->prepare($sql);
    
    // Bind parameters dynamically
    $params = array_merge([$supervisorID], $sessionIDs);
    $types = "i" . str_repeat('i', count($sessionIDs));
    $stmt->bind_param($types, ...$params);
    
    $stmt->execute();
    $result = $stmt->get_result();

    // First, collect all unique students
    $allStudents = [];
    while ($row = $result->fetch_assoc()) {
        $sID = $row['Student_ID'];
        
        // Store student info
        if (!isset($allStudents[$sID])) {
            $allStudents[$sID] = [
                'name' => $row['Student_Name'],
                'logbooks' => []
            ];
        }
        
        // Add logbook if exists
        if (!empty($row['Logbook_ID'])) {
            $allStudents[$sID]['logbooks'][] = [
                'id'     => $row['Logbook_ID'],
                'name'   => $row['Logbook_Name'],
                'date'   => $row['Logbook_Date'],
                'status' => $row['Logbook_Status'],
                'course_id' => $row['Logbook_Course_ID']
            ];
        }
    }
    $stmt->close();
    
    // Now populate BOTH course sections with all students
    foreach ($allStudents as $sID => $studentData) {
        // Add student to BOTH SWE4949A and SWE4949B
        foreach (['SWE4949A' => 1, 'SWE4949B' => 2] as $courseKey => $courseID) {
            $groupedData[$courseKey][$sID] = [
                'name'      => $studentData['name'],
                'id'        => $sID,
                'logbooks'  => [],
                'stats'     => ['submitted' => 0, 'approved' => 0]
            ];
            
            // Filter logbooks based on Course_ID only
            // Course_ID = 1 → Display under SWE4949A
            // Course_ID = 2 → Display under SWE4949B
            foreach ($studentData['logbooks'] as $logbook) {
                if ($logbook['course_id'] == $courseID) {
                    $groupedData[$courseKey][$sID]['logbooks'][] = $logbook;
                    $groupedData[$courseKey][$sID]['stats']['submitted']++;
                    if ($logbook['status'] === 'Approved') {
                        $groupedData[$courseKey][$sID]['stats']['approved']++;
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Logbook Submission</title>
    <link rel="stylesheet" href="../../css/supervisor/logbookSubmission.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

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
                onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items <?php echo ($activeRole == 'supervisor') ? 'expanded' : ''; ?>">
                <a href="dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
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
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>
                <a href="logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>

                <a href="signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="project_title.php?role=supervisor" id="projectTitle"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Project Title
                </a>
            </div>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'assessor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('assessorMenu', this)">
                <span class="role-text">Assessor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="assessorMenu" class="menu-items <?php echo ($activeRole == 'assessor') ? 'expanded' : ''; ?>">
                <a href="../phpAssessor/dashboard.php?role=assessor" id="Dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="../phpAssessor/notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ? : ''; ?>">
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

            <a href="../login.php" id="logout"><i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout</a>
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


    <div id="main">
        <div class="logbook-container">
            <h1 class="page-title">Logbook Submission</h1>
            <p style="margin: 0; color: #666; font-size: 14px;">
                    <i class="bi bi-info-circle"></i> Student must have at least 6 logbooks approved for each semesters.
                </p>

            <div class="accordion" id="logbookAccordion">
                
                <?php foreach ($groupedData as $courseCode => $students): ?>
                    <?php 
                        $collapseID = "collapse" . $courseCode;
                        // Open both A and B by default
                        $isShow = 'show';
                    ?>
                    
                    <div class="course-section">
                        <div class="course-header" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseID; ?>">
                            <i class="bi <?php echo $isShow ? 'bi-chevron-down' : 'bi-chevron-right'; ?> toggle-icon me-2"></i>
                            <span><?php echo $courseCode; ?></span>
                        </div>

                        <div id="<?php echo $collapseID; ?>" class="collapse <?php echo $isShow; ?>">
                            <div class="logbook-table-wrapper">
                                <div class="logbook-grid-container">

                                    <div class="grid-header student-col">Student</div>
                                    <div class="grid-header meeting-col">Meeting Progress</div>
                                    <div class="grid-header status-col">Status</div>
                                    <div class="grid-header submitted-col">Submitted</div>
                                    <div class="grid-header approved-col">Approved</div>

                                    <?php if (empty($students)): ?>
                                        <div class="no-data-row">No students found.</div>
                                    <?php else: ?>
                                        <?php foreach ($students as $sID => $data): ?>
                                            <?php 
                                                // Calculate Span
                                                $count = count($data['logbooks']);
                                                $rowSpan = ($count > 0) ? $count : 1;
                                            ?>
                                            
                                            <div class="student-group-wrapper" style="display: contents;">
                                                
                                                <div class="grid-cell student-col spanned-cell" style="grid-row: span <?php echo $rowSpan; ?>;">
                                                    <strong><?php echo htmlspecialchars($data['name']); ?></strong>
                                                    <div class="text-muted small"><?php echo $data['id']; ?></div>
                                                </div>

                                                <?php if ($count === 0): ?>
                                                    <div class="grid-cell meeting-col text-muted">No submissions</div>
                                                    <div class="grid-cell status-col">-</div>
                                                    <div class="grid-cell submitted-col spanned-cell" style="grid-row: span <?php echo $rowSpan; ?>;">
                                                        <?php echo $data['stats']['submitted']; ?>
                                                    </div>
                                                    <div class="grid-cell approved-col spanned-cell" style="grid-row: span <?php echo $rowSpan; ?>;">
                                                        <?php echo $data['stats']['approved']; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?php 
                                                        $isFirst = true;
                                                        foreach ($data['logbooks'] as $lb): 
                                                    ?>
                                                        <?php if ($isFirst): ?>
                                                            <div class="grid-cell meeting-col">
                                                                <a href="javascript:void(0)" onclick="viewLogbookPDF(<?php echo $lb['id']; ?>)" class="pdf-link">
                                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                                    <?php echo htmlspecialchars($lb['name']); ?>
                                                                </a>
                                                                <span class="date-small"><?php echo $lb['date']; ?></span>
                                                            </div>

                                                            <div class="grid-cell status-col">
                                                                <select class="form-select form-select-sm status-select" 
                                                                        onchange="updateStatus(<?php echo $lb['id']; ?>, this)"
                                                                        data-status="<?php echo $lb['status']; ?>"
                                                                        data-previous-status="<?php echo $lb['status']; ?>">
                                                                    <option value="Waiting for Approval" <?php echo ($lb['status'] == 'Waiting for Approval') ? 'selected' : ''; ?>>Waiting for Approval</option>
                                                                    <option value="Approved" <?php echo ($lb['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="Rejected" <?php echo ($lb['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                                </select>
                                                            </div>
                                                            
                                                            <div class="grid-cell submitted-col spanned-cell" style="grid-row: span <?php echo $rowSpan; ?>;">
                                                                <?php echo $data['stats']['submitted']; ?>
                                                            </div>
                                                            <div class="grid-cell approved-col spanned-cell" style="grid-row: span <?php echo $rowSpan; ?>;">
                                                                <?php echo $data['stats']['approved']; ?>
                                                            </div>
                                                            <?php $isFirst = false; ?>
                                                        <?php else: ?>
                                                            <div class="grid-cell meeting-col">
                                                                <a href="javascript:void(0)" onclick="viewLogbookPDF(<?php echo $lb['id']; ?>)" class="pdf-link">
                                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                                    <?php echo htmlspecialchars($lb['name']); ?>
                                                                </a>
                                                                <span class="date-small"><?php echo $lb['date']; ?></span>
                                                            </div>

                                                            <div class="grid-cell status-col">
                                                                <select class="form-select form-select-sm status-select" 
                                                                        onchange="updateStatus(<?php echo $lb['id']; ?>, this)"
                                                                        data-status="<?php echo $lb['status']; ?>"
                                                                        data-previous-status="<?php echo $lb['status']; ?>">
                                                                    <option value="Waiting for Approval" <?php echo ($lb['status'] == 'Waiting for Approval') ? 'selected' : ''; ?>>Waiting for Approval</option>
                                                                    <option value="Approved" <?php echo ($lb['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="Rejected" <?php echo ($lb['status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                                </select>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>

                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

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
            const arrowContainers = document.querySelectorAll('.arrow-container');

            // Function to handle the role menu toggle
            const handleRoleToggle = (header) => {
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');

                if (!targetMenu) return;

                const isExpanded = targetMenu.classList.contains('expanded');

                // Collapse all other menus and reset their arrows
                document.querySelectorAll('.menu-items').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.remove('expanded');
                        menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                    }
                });
                document.querySelectorAll('.role-header').forEach(h => {
                    if (h !== header) h.classList.remove('active-role');
                });
                document.querySelectorAll('.arrow-icon').forEach(icon => {
                    if (icon !== arrowIcon) {
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-right');
                    }
                });

                // Toggle current menu
                targetMenu.classList.toggle('expanded', !isExpanded);
                header.classList.toggle('active-role', !isExpanded);

                // Show/hide child links for the current menu (only when sidebar is expanded)
                const sidebar = document.getElementById("mySidebar");
                const isSidebarExpanded = sidebar.style.width === "220px";

                targetMenu.querySelectorAll('a').forEach(a => {
                    if (isSidebarExpanded) {
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
            }


            arrowContainers.forEach(container => {
                // Attach event listener to the role header itself
                const header = container.closest('.role-header');
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleRoleToggle(header);
                });
            });
            var courseHeaders = document.querySelectorAll('.course-header');

            courseHeaders.forEach(function (header) {
                var icon = header.querySelector('.toggle-icon');

                // Use Bootstrap's collapse events on the target element
                var targetCollapse = document.querySelector(header.getAttribute('data-bs-target'));

                if (targetCollapse) {
                    targetCollapse.addEventListener('show.bs.collapse', function () {
                        icon.classList.remove('bi-chevron-right');
                        icon.classList.add('bi-chevron-down');
                    });

                    targetCollapse.addEventListener('hide.bs.collapse', function () {
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-right');
                    });
                }
            });

        });


        // Color Logic
        function updateSelectColor(select) {
            const val = select.value;
            select.classList.remove('status-approved', 'status-declined', 'status-pending');
            if(val === 'Approved') select.classList.add('status-approved');
            else if(val === 'Declined') select.classList.add('status-declined');
            else select.classList.add('status-pending');
        }
        document.querySelectorAll('.status-select').forEach(updateSelectColor);

        // AJAX Update
        function updateStatus(id, el) {
            const previousStatus = el.getAttribute('data-previous-status') || '';
            const status = el.value;
            updateSelectColor(el);
            el.disabled = true;

            fetch('update_logbook_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&status=${encodeURIComponent(status)}`
            })
            .then(r => r.json())
            .then(data => {
                el.disabled = false;
                if(data.success) {
                    // Update the approved count in real-time
                    const studentWrapper = el.closest('.student-group-wrapper');
                    if (studentWrapper) {
                        const approvedCell = studentWrapper.querySelector('.approved-col');
                        if (approvedCell) {
                            let currentCount = parseInt(approvedCell.textContent.trim()) || 0;
                            
                            // If changing FROM approved TO something else: decrement
                            if (previousStatus === 'Approved' && status !== 'Approved') {
                                currentCount = Math.max(0, currentCount - 1);
                            }
                            // If changing FROM non-approved TO approved: increment
                            else if (previousStatus !== 'Approved' && status === 'Approved') {
                                currentCount++;
                            }
                            
                            approvedCell.textContent = currentCount;
                        }
                    }

                    // Update the data attribute to track the new status
                    el.setAttribute('data-previous-status', status);
                } else {
                    alert('Failed: ' + data.message);
                }
            })
            .catch(e => {
                el.disabled = false;
                alert('Connection error');
            });
        }

        // View Logbook PDF Function
        function viewLogbookPDF(logbookID) {
            // Fetch logbook data
            fetch('get_logbook_data.php?id=' + logbookID)
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        alert('Failed to load logbook data: ' + data.message);
                        return;
                    }
                    
                    const logbook = data.logbook;
                    const agendas = data.agendas;
                    
                    // Generate PDF
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF('p', 'mm', 'a4');
                    
                    const pageWidth = doc.internal.pageSize.getWidth();
                    const pageHeight = doc.internal.pageSize.getHeight();
                    const margin = 20;
                    let yPosition = 20;
                    
                    // Add UPM Logo centered
                    const logoWidth = 50;
                    const logoHeight = 30;
                    const logoX = (pageWidth - logoWidth) / 2;
                    doc.addImage('../../assets/UPMLogo.png', 'PNG', logoX, yPosition, logoWidth, logoHeight);
                    yPosition += logoHeight + 10;
                    
                    // Title
                    doc.setFontSize(14);
                    doc.setFont('helvetica', 'bold');
                    doc.setTextColor(51, 51, 51);
                    const title = 'FINAL YEAR PROJECT LOGBOOK';
                    const titleWidth = doc.getTextWidth(title);
                    doc.text(title, (pageWidth - titleWidth) / 2, yPosition);
                    yPosition += 10;
                    
                    // Logbook Name
                    doc.setFontSize(12);
                    doc.setFont('helvetica', 'normal');
                    const logbookName = logbook.Logbook_Name;
                    const nameWidth = doc.getTextWidth(logbookName);
                    doc.text(logbookName, (pageWidth - nameWidth) / 2, yPosition);
                    yPosition += 8;
                    
                    // Divider line
                    doc.setDrawColor(0, 0, 0);
                    doc.setLineWidth(0.5);
                    doc.line(margin, yPosition, pageWidth - margin, yPosition);
                    yPosition += 10;
                    
                    // Student Details
                    doc.setFontSize(10);
                    
                    // Student Name (with text wrapping)
                    doc.setFont('helvetica', 'bold');
                    doc.text('Student Name:', margin, yPosition);
                    doc.setFont('helvetica', 'normal');
                    const maxNameWidth = pageWidth / 2 - margin - 50; // Leave space before Student ID
                    const nameLines = doc.splitTextToSize(logbook.Student_Name, maxNameWidth);
                    doc.text(nameLines, margin + 40, yPosition);
                    
                    // Student ID (aligned to right side)
                    doc.setFont('helvetica', 'bold');
                    doc.text('Student ID:', pageWidth / 2 + 10, yPosition);
                    doc.setFont('helvetica', 'normal');
                    doc.text(logbook.Student_ID, pageWidth / 2 + 40, yPosition);
                    
                    // Adjust yPosition based on name lines
                    const nameHeight = nameLines.length * 5;
                    yPosition += Math.max(7, nameHeight);
                    
                    // Date
                    doc.setFont('helvetica', 'bold');
                    doc.text('Date:', margin, yPosition);
                    doc.setFont('helvetica', 'normal');
                    doc.text(logbook.Logbook_Date, margin + 40, yPosition);
                    
                    // Status
                    doc.setFont('helvetica', 'bold');
                    doc.text('Status:', pageWidth / 2 + 10, yPosition);
                    doc.setFont('helvetica', 'normal');
                    doc.text(logbook.Logbook_Status, pageWidth / 2 + 40, yPosition);
                    yPosition += 15;
                    
                    // Agenda Section Title
                    doc.setFontSize(12);
                    doc.setFont('helvetica', 'bold');
                    doc.text('Agenda & Progress', margin, yPosition);
                    yPosition += 5;
                    
                    // Divider line
                    doc.setDrawColor(0, 0, 0);
                    doc.line(margin, yPosition, pageWidth - margin, yPosition);
                    yPosition += 10;
                    
                    // Agenda Items
                    doc.setFontSize(10);
                    if (agendas.length > 0) {
                        agendas.forEach((agenda, index) => {
                            // Check if we need a new page
                            if (yPosition > pageHeight - 40) {
                                doc.addPage();
                                yPosition = 20;
                            }
                            
                            // Agenda Title
                            doc.setFont('helvetica', 'bold');
                            doc.text('Item: ' + agenda.Agenda_Title, margin, yPosition);
                            yPosition += 7;
                            
                            // Agenda Content
                            doc.setFont('helvetica', 'normal');
                            const content = agenda.Agenda_Content || '';
                            const lines = doc.splitTextToSize(content, pageWidth - 2 * margin - 10);
                            
                            lines.forEach(line => {
                                if (yPosition > pageHeight - 20) {
                                    doc.addPage();
                                    yPosition = 20;
                                }
                                doc.text(line, margin + 5, yPosition);
                                yPosition += 5;
                            });
                            
                            yPosition += 5;
                        });
                    } else {
                        doc.setFont('helvetica', 'italic');
                        doc.text('No details recorded for this meeting.', margin, yPosition);
                    }
                    
                    // Save PDF
                    const fileName = 'Logbook_' + logbook.Logbook_Name.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_-]/g, '') + '.pdf';
                    doc.save(fileName);
                })
                .catch(e => {
                    alert('Error loading logbook: ' + e.message);
                });
        }
    </script>
</body>
</html>