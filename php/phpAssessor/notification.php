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

// B. Lookup Numeric ID
$currentUserID = null;
if ($activeRole === 'assessor') {
    $stmt = $conn->prepare("SELECT Assessor_ID FROM assessor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc())
        $currentUserID = $row['Assessor_ID'];
}

// C. Fetch Presentation Assessment Notifications
$presentationNotifications = [];
if ($activeRole === 'assessor' && $currentUserID) {
    // HARDCODED: Using session 1 (no need to fetch)

    // Fetch presentation schedules for assessor (Assessment IDs 2 and 3)
    $sqlPresentationNotif = "
        SELECT DISTINCT
            asess.Session_ID,
            asess.Date,
            asess.Time,
            asess.Venue,
            s.Student_ID,
            s.Student_Name,
            a.Assessment_Name,
            a.Assessment_ID
        FROM student_enrollment se
        JOIN student s ON se.Student_ID = s.Student_ID
        JOIN student_session ss ON s.Student_ID = ss.Student_ID
        JOIN assessment_session asess ON ss.Session_ID = asess.Session_ID
        JOIN assessment a ON asess.Assessment_ID = a.Assessment_ID
        WHERE (se.Assessor_ID_1 = ? OR se.Assessor_ID_2 = ?)
            AND a.Assessment_ID IN (2, 3)
            AND asess.Date >= CURDATE()
        ORDER BY asess.Date ASC, asess.Time ASC
    ";

    $stmtPresentation = $conn->prepare($sqlPresentationNotif);
    $stmtPresentation->bind_param("ii", $currentUserID, $currentUserID);
    $stmtPresentation->execute();
    $resultPresentation = $stmtPresentation->get_result();

    while ($row = $resultPresentation->fetch_assoc()) {
        $presentationNotifications[] = $row;
    }
    $stmtPresentation->close();
}

$totalNotifications = count($presentationNotifications);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Notification</title>
    <!-- <link rel="stylesheet" href="../../css/assessor/dashboard.css"> -->
    <link rel="stylesheet" href="../../css/assessor/notification.css">
    <link rel="stylesheet" href="../../css/background.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                onclick="toggleMenu('supervisorMenu', this)">
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
                onclick="toggleMenu('assessorMenu', this)">
                <span class="role-text">Assessor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="assessorMenu" class="menu-items expanded">
                <a href="../phpAssessor/dashboard.php?role=assessor" id="Dashboard"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>

                <a href="notification.php?role=assessor" id="Notification"
                    class="<?php echo ($activeRole == 'assessor') ? 'active-menu-item active-page' : ''; ?>">
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

    </div>

    <div id="main">
        <div class="notification-container">
            <h1 class="page-title">Notification</h1>

            <?php if ($totalNotifications == 0): ?>
                <div class="notification-item" style="text-align: center; padding: 40px;">
                    <p style="color: #666; font-size: 16px;">No upcoming presentation assessments at this time.</p>
                </div>
            <?php else: ?>
                <?php
                $notifNum = 1;
                foreach ($presentationNotifications as $presentationNotif):
                    $dateFormatted = date('d M Y, D', strtotime($presentationNotif['Date']));
                    $timeFormatted = date('g.i a', strtotime($presentationNotif['Time']));
                    ?>
                    <div class="notification-item">
                        <div class="notification-description">
                            <span class="notif-number"><?php echo $notifNum++; ?>.</span>
                            You have been assigned to evaluate
                            <strong><?php echo htmlspecialchars($presentationNotif['Student_Name']); ?></strong> for
                            <strong><?php echo htmlspecialchars($presentationNotif['Assessment_Name']); ?></strong>.
                        </div>
                        <div class="notif-card">
                            <div class="notif-details">
                                <p><strong>Date</strong>: <?php echo $dateFormatted; ?></p>
                                <p><strong>Time</strong>: <?php echo $timeFormatted; ?></p>
                                <p><strong>Venue</strong>: <?php echo htmlspecialchars($presentationNotif['Venue'] ?? 'TBA'); ?>
                                </p>
                                <p><strong>Student</strong>: <?php echo htmlspecialchars($presentationNotif['Student_Name']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

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
                    const menuId = header.getAttribute('href');
                    const targetMenu = document.querySelector(menuId);

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
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
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
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                if (targetMenu && targetMenu.classList.contains('expanded')) {
                    const arrowIcon = header.querySelector('.arrow-icon');
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                }
            });
        });
    </script>
</body>

</html>