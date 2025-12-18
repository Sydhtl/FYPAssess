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

// 3. FETCH COURSE INFO
$courseCode = "SWE4949A";
$courseSession = "2024/2025 - 2";

$sqlSession = "SELECT fs.FYP_Session, fs.Semester, c.Course_Code 
               FROM fyp_session fs
               JOIN course c ON fs.Course_ID = c.Course_ID
               ORDER BY fs.FYP_Session DESC, fs.Semester DESC
               LIMIT 1";

$resultSession = $conn->query($sqlSession);
if ($resultSession && $resultSession->num_rows > 0) {
    $sessionRow = $resultSession->fetch_assoc();
    $courseCode = $sessionRow['Course_Code'];
    $courseSession = $sessionRow['FYP_Session'] . " - " . $sessionRow['Semester'];
}

// A. Get Login ID 
if (isset($_SESSION['user_id'])) {
    $loginID = $_SESSION['user_id'];
} else {
    $loginID = 'hazura'; // Fallback
}

// B. Lookup Numeric ID
$currentUserID = null;
if ($activeRole === 'supervisor') {
    $stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc())
        $currentUserID = $row['Supervisor_ID'];
} elseif ($activeRole === 'assessor') {
    $stmt = $conn->prepare("SELECT Assessor_ID FROM assessor WHERE Lecturer_ID = ?");
    $stmt->bind_param("s", $loginID);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc())
        $currentUserID = $row['Assessor_ID'];
}

// Fetch distinct FYP Sessions for the Sidebar Filter
$session_sql = "SELECT DISTINCT FYP_Session FROM fyp_session ORDER BY FYP_Session DESC";
$session_result = $conn->query($session_sql);
?>

<!DOCTYPE html>
<html>

<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../css/supervisor/report.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css?v=<?php echo time(); ?>">
    <!-- <link rel="stylesheet" href="../../../css/dashboard.css"> -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
</head>

<body>

    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()"><i class="bi bi-list"></i></button>

        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>

            <span id="nameSide">HI, <?php echo strtoupper($loginID); ?></span>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'supervisor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items <?php echo ($activeRole == 'supervisor') ? 'expanded' : ''; ?>">
                <a href="../dashboard/dashboard.html" id="dashboard"><i class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-bell-fill icon-padding"></i> Notification
                </a>
                <a href="industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Industry Collaboration
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=supervisor" id="evaluationForm"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
                <a href="report.php?role=supervisor" id="superviseesReport"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>
        
                <a href="logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>
                <a href="signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="project_title.php?role=supervisor" id="projectTitle"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
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
                <a href="../dashboard/dashboard.html" id="Dashboard"><i class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="../notification/notification.html" id="Notification"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
            </div>

            <a href="#" id="logout"><i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout</a>
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
        <div class="evaluation-container">
            <h1 class="page-title">Supervisees' Report</h1>

            <div class="evaluation-card">
                <!-- Student Selection Field -->
                <div class="form-field">
                    <p class="form-label"><strong>Select student</strong></p>
                    <div class="evaluation-action">
                        <select class="form-select action-dropdown">
                            <option value="student">214668 - Siti Athirah Binti Othman</option>
                            <option value="student">215332 - Atiya Aisya Binti Aiman</option>
                        </select>
                    </div>
                </div>

                <!-- Assessment Selection Field -->
                <div class="form-field">
                    <p class="form-label"><strong>Select assessment</strong></p>
                    <div class="evaluation-action">
                        <select class="form-select action-dropdown">
                            <option value="proposal">Proposal Seminar</option>
                            <option value="demonstration">Seminar Demonstration</option>
                        </select>
                    </div>
                </div>
            </div>
            <!-- Student details -->
            <div class="student-details">
                <p><strong>Matric Number</strong>: 214668</p>
                <p><strong>Name</strong>: Siti Athirah Binti Othman</p>
                <p><strong>Programme Code</strong>: 42-D BKP</p>
                <p><strong>Supervisor</strong>: Dr Azrina Binti Kamaruddin</p>
                <p><strong>Project title</strong>: FYPAssess : Development of an Automated Assessment and
                    Evaluation for Bachelor Projects</p>
            </div>
            <div class="report-table-wrapper">
                <div class="report-grid-container">

                    <!-- <div class="grid-cell header-row criteria-col">Evaluation criteria</div>
            <div class="grid-cell header-row lo-col">Learning outcome</div>
            <div class="grid-cell header-row mark-col">Assessment mark</div> -->

                    <div class="grid-cell report-header-main" style="grid-column: 1 / 3;">Assessment type</div>
                    <div class="grid-cell report-header-main" style="grid-column: 3 / 4;">Total Mark (%)</div>

                    <div class="grid-cell assessment-header collapsed" style="grid-column: 1 / 3;"
                        data-bs-toggle="collapse" data-bs-target=".criteria-group-1">
                        <i class="fas fa-chevron-right toggle-icon"></i>
                        Proposal report
                    </div>
                    <div class="grid-cell assessment-total" style="grid-column: 3 / 4;">[10/10]</div>

                    <div class="criteria-row collapse criteria-group-1 criteria-header">
                        <div class="grid-cell header-row">Evaluation criteria</div>
                        <div class="grid-cell header-row">Learning outcome</div>
                        <div class="grid-cell header-row">Assessment mark</div>
                    </div>

                    <div class="criteria-row collapse criteria-group-1">
                        <div class="grid-cell criteria-cell">1. Content of the proposal</div>
                        <div class="grid-cell lo-cell">CPS7 (LL)</div>
                        <div class="grid-cell mark-cell">5</div>
                    </div>

                    <div class="criteria-row collapse criteria-group-1">
                        <div class="grid-cell criteria-cell">2. Content of the proposal (Commercial potential)</div>
                        <div class="grid-cell lo-cell">CPS1 (C5)</div>
                        <div class="grid-cell mark-cell">5</div>
                    </div>

                    <div class="grid-cell assessment-header collapsed" style="grid-column: 1 / 3;"
                        data-bs-toggle="collapse" data-bs-target=".criteria-group-2">
                        <i class="fas fa-chevron-right toggle-icon"></i>
                        Seminar proposal
                    </div>
                    <div class="grid-cell assessment-total" style="grid-column: 3 / 4;">[10/10]</div>

                    <div class="criteria-row collapse criteria-group-2 criteria-header">
                        <div class="grid-cell">Evaluation criteria</div>
                        <div class="grid-cell">Learning outcome</div>
                        <div class="grid-cell">Assessment mark</div>
                    </div>

                    <div class="criteria-row collapse criteria-group-2">
                        <div class="grid-cell criteria-cell">1. Content of the proposal</div>
                        <div class="grid-cell lo-cell">CPS1 (C5)</div>
                        <div class="grid-cell mark-cell">5</div>
                    </div>

                </div>
                <!--KIV Evaluation table -->

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
                // --- Assessment Sub-Accordion Icon Logic ---
            // This script just toggles the '.collapsed' class for the icon CSS
            var assessmentHeaders = document.querySelectorAll('.assessment-header');
            
            assessmentHeaders.forEach(function(header) {
                // Set the initial state of the icon on page load
                // Bootstrap's JS runs after this, so we check the 'collapse' class
                const targetSelector = header.getAttribute('data-bs-target');
                const targetElement = document.querySelector(targetSelector);
                
                // If the target is NOT shown by default, add 'collapsed'
                if (targetElement && !targetElement.classList.contains('show')) {
                    header.classList.add('collapsed');
                }

                // Add click listener to toggle the icon class
                header.addEventListener('click', function() {
                    // This toggles the class for the CSS rotation
                    header.classList.toggle('collapsed');
                });
            });
            });
            // --- Assessment Toggle Logic ---
            function setupAssessmentToggle() {
                document.querySelectorAll('.assessment-toggle').forEach(header => {
                    header.addEventListener('click', () => {
                        const details = header.nextElementSibling; // The div.assessment-details
                        const icon = header.querySelector('.toggle-icon');

                        if (details.style.display === 'none' || details.style.display === '') {
                            // Expand
                            details.style.display = 'block';
                            icon.classList.add('rotated');
                        } else {
                            // Collapse
                            details.style.display = 'none';
                            icon.classList.remove('rotated');
                        }
                    });
                });

                // Set initial state for all details to collapsed
                document.querySelectorAll('.assessment-details').forEach(details => {
                    details.style.display = 'none';
                });
            }

            // Ensure the collapsed state is set immediately on page load
            window.onload = function () {
                closeNav();
            };



        </script>
</body>

</html>