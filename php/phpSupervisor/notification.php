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
    <title>Notification_Assessor</title>
    <!-- <link rel="stylesheet" href="../../css/assessor/dashboard.css"> -->
    <link rel="stylesheet" href="../../css/assessor/notification.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css">
    <!-- <link rel="stylesheet" href="../../../css/dashboard.css"> -->
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

            <span id="nameSide">HI, <?php echo strtoupper($loginID); ?></span>

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
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
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
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
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
                <a href="../phpAssessor/dashboard.php?role=supervisor" id="Dashboard"
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
        <div class="notification-container">
            <h1 class="page-title">Notification</h1>

            <!-- Notification Item 1 -->
            <div class="notification-item">
                <div class="notification-description">
                    <span class="notif-number">1.</span>You have been assigned to a new evaluation task. Please review
                    the details and accept the task.
                </div>
                <div class="notif-card">
                    <div class="notif-details">
                        <p><strong>Date</strong>: 9 Aug 2025, Wed</p>
                        <p><strong>Time</strong>: 9.00 am</p>
                        <p><strong>Venue</strong>: Bilik Kuliah A</p>
                        <p><strong>Student</strong>: Siti Athirah Binti Othman</p>
                    </div>
                    <div class="notif-action">
                        <select class="form-select action-dropdown">
                            <option value="accept">Accept</option>
                            <option value="reject">Reject</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Notification Item 2 -->
            <div class="notification-item">
                <div class="notification-description">
                    <span class="notif-number">2.</span>You have been assigned to a new evaluation task. Please review
                    the details and accept the task.
                </div>
                <div class="notif-card">
                    <div class="notif-details">
                        <p><strong>Date</strong>: 10 Aug 2025, Thu</p>
                        <p><strong>Time</strong>: 9.30 am</p>
                        <p><strong>Venue</strong>: Bilik Kuliah A</p>
                        <p><strong>Student</strong>: Atiya Aisya Bin Aiman</p>
                    </div>
                    <div class="notif-action">
                        <select class="form-select action-dropdown">
                            <option value="accept">Accept</option>
                            <option value="reject">Reject</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // ==========================================
        // SIDEBAR LOGIC
        // ==========================================

        // 1. Toggle Menu (Accordion)
        function toggleMenu(menuId, headerElement) {
            const menu = document.getElementById(menuId);
            if (!menu) return;

            // Collapse all other menus
            document.querySelectorAll('.menu-items').forEach(m => {
                if (m !== menu) {
                    m.classList.remove('expanded');
                    // Find header associated with this menu to remove highlighting
                    const header = document.querySelector(`.role-header[onclick*="${m.id}"]`);
                    if (header) header.classList.remove('menu-expanded');
                }
            });

            // Toggle current menu
            menu.classList.toggle('expanded');
            headerElement.classList.toggle('menu-expanded');

            updateRoleHeaderHighlighting();
        }

        function updateRoleHeaderHighlighting() {
            document.querySelectorAll('.role-header').forEach(header => {
                const onclickAttr = header.getAttribute('onclick');
                if (!onclickAttr) return;

                // Extract menu ID from onclick attribute
                const match = onclickAttr.match(/toggleMenu\('(\w+)'/);
                if (!match) return;

                const menuId = match[1];
                const targetMenu = document.getElementById(menuId);
                if (!targetMenu) return;

                // Check if this menu contains the active page
                const hasActiveLink = targetMenu.querySelector('.active-menu-item') !== null;

                // Check if this menu is currently expanded
                const isExpanded = targetMenu.classList.contains('expanded');

                // Logic: Highlight role header ONLY when it contains active page BUT menu is collapsed
                if (hasActiveLink && !isExpanded) {
                    header.classList.add('active-role');
                } else {
                    header.classList.remove('active-role');
                }
            });
        }

        // 2. Open Sidebar
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
            document.getElementById("containerAtas").style.marginLeft = "250px";

            document.getElementById("nameSide").style.display = "block";
            document.getElementById("close").style.display = "block";
            document.getElementById("logout").style.display = "flex";

            const links = document.querySelectorAll("#sidebarLinks a");
            links.forEach(l => l.style.display = 'flex');

            document.querySelector(".menu-icon").style.display = "none";
        }

        // 3. Close Sidebar
        function closeNav() {
            document.getElementById("mySidebar").style.width = "60px";
            document.getElementById("main").style.marginLeft = "60px";
            document.getElementById("containerAtas").style.marginLeft = "60px";

            document.getElementById("nameSide").style.display = "none";
            document.getElementById("close").style.display = "none";
            document.getElementById("logout").style.display = "none";

            const links = document.querySelectorAll("#sidebarLinks a");
            links.forEach(l => l.style.display = 'none');

            document.querySelector(".menu-icon").style.display = "block";
        }

    </script>
</body>

</html>