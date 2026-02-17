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
    // $courseCode = $sessionRow['Course_Code'];
    $courseCode = "SWE4949"; // Always display base course code without A/B suffix
    $courseSession = $sessionRow['FYP_Session'] . " - " . $sessionRow['Semester'];
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

// C. Fetch Notifications - Project Title Requests
$titleNotifications = [];
if ($activeRole === 'supervisor' && $currentUserID) {
    // Get the latest session ID
    $sqlLatestSession = "SELECT FYP_Session_ID FROM fyp_session ORDER BY FYP_Session_ID DESC LIMIT 1";
    $resultLatestSession = $conn->query($sqlLatestSession);
    $latestSessionID = 1;
    if ($resultLatestSession && $rowSession = $resultLatestSession->fetch_assoc()) {
        $latestSessionID = $rowSession['FYP_Session_ID'];
    }

    $sqlTitleNotif = "
        SELECT 
            fp.Project_ID,
            s.Student_Name,
            fp.Project_Title,
            fp.Proposed_Title,
            fp.Title_Status
        FROM fyp_project fp
        JOIN student_enrollment se ON fp.Student_ID = se.Student_ID
        JOIN student s ON se.Student_ID = s.Student_ID AND se.FYP_Session_ID = s.FYP_Session_ID
        WHERE se.Supervisor_ID = ?
            AND se.FYP_Session_ID = ?
            AND fp.Proposed_Title IS NOT NULL 
            AND fp.Proposed_Title != ''
            AND fp.Title_Status = 'Waiting For Approval'
        ORDER BY fp.Project_ID DESC
    ";

    $stmtTitle = $conn->prepare($sqlTitleNotif);
    $stmtTitle->bind_param("ii", $currentUserID, $latestSessionID);
    $stmtTitle->execute();
    $resultTitle = $stmtTitle->get_result();

    while ($row = $resultTitle->fetch_assoc()) {
        $titleNotifications[] = $row;
    }
    $stmtTitle->close();
}

// D. Fetch Notifications - Logbook Submissions
$logbookNotifications = [];
if ($activeRole === 'supervisor' && $currentUserID) {
    $sqlLogbookNotif = "
        SELECT 
            l.Logbook_ID,
            s.Student_Name,
            l.Logbook_Name,
            l.Logbook_Date,
            l.Logbook_Status
        FROM logbook l
        JOIN student s ON l.Student_ID = s.Student_ID AND l.FYP_Session_ID = s.FYP_Session_ID
        WHERE l.Supervisor_ID = ?
            AND l.Logbook_Status = 'Waiting for Approval'
        ORDER BY l.Logbook_Date DESC
    ";

    $stmtLogbook = $conn->prepare($sqlLogbookNotif);
    $stmtLogbook->bind_param("i", $currentUserID);
    $stmtLogbook->execute();
    $resultLogbook = $stmtLogbook->get_result();

    while ($row = $resultLogbook->fetch_assoc()) {
        $logbookNotifications[] = $row;
    }
    $stmtLogbook->close();
}

// Combine notifications
$allNotifications = array_merge($titleNotifications, $logbookNotifications);
$totalNotifications = count($allNotifications);
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

            <span id="nameSide">Hi, <?php echo ucwords(strtolower($lecturerName)); ?></span>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'supervisor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items <?php echo ($activeRole == 'supervisor') ? 'expanded' : ''; ?>">
                <a href="dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-bell-fill icon-padding"></i> Notification
                </a>

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

            <a href="../login.php" id="logout" style="display: none;"><i class="bi bi-box-arrow-left"
                    style="padding-right: 10px;"></i>
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


    <div id="main">
        <div class="notification-container">
            <h1 class="page-title">Notification</h1>

            <?php if ($totalNotifications == 0): ?>
                <div class="notification-item" style="text-align: center; padding: 40px;">
                    <p style="color: #666; font-size: 16px;">No pending notifications at this time.</p>
                </div>
            <?php else: ?>
                <?php
                $notifNum = 1;

                // Display Project Title Requests
                foreach ($titleNotifications as $titleNotif):
                    ?>
                    <div class="notification-item">
                        <div class="notification-description">
                            <span class="notif-number"><?php echo $notifNum++; ?>.</span>
                            <strong><?php echo htmlspecialchars($titleNotif['Student_Name']); ?></strong> has submitted a new
                            project title proposal for your review.
                        </div>
                        <div class="notif-card">
                            <div class="notif-details">
                                <p><strong>Current Title</strong>:
                                    <?php echo htmlspecialchars($titleNotif['Project_Title'] ?? 'Not set'); ?>
                                </p>
                                <p><strong>Proposed Title</strong>:
                                    <?php echo htmlspecialchars($titleNotif['Proposed_Title']); ?>
                                </p>
                                <p><strong>Status</strong>: <?php echo htmlspecialchars($titleNotif['Title_Status']); ?></p>
                            </div>
                            <div class="notif-action">
                                <select class="form-select action-dropdown" data-type="title"
                                    data-id="<?php echo $titleNotif['Project_ID']; ?>" onchange="updateStatus(this)">
                                    <option value="Waiting For Approval" selected>Waiting For Approval</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php
                // Display Logbook Submissions
                foreach ($logbookNotifications as $logbookNotif):
                    ?>
                    <div class="notification-item">
                        <div class="notification-description">
                            <span class="notif-number"><?php echo $notifNum++; ?>.</span>
                            <strong><?php echo htmlspecialchars($logbookNotif['Student_Name']); ?></strong> has submitted a
                            logbook for your approval.
                        </div>
                        <div class="notif-card">
                            <div class="notif-details">
                                <p><strong>Logbook</strong>: <?php echo htmlspecialchars($logbookNotif['Logbook_Name']); ?></p>
                                <p><strong>Date</strong>: <?php echo date('d M Y', strtotime($logbookNotif['Logbook_Date'])); ?>
                                </p>
                                <p><a href="view_logbook_pdf.php?id=<?php echo $logbookNotif['Logbook_ID']; ?>" target="_blank"
                                        style="color: #007bff; text-decoration: underline;">(See more)</a></p>
                            </div>
                            <div class="notif-action">
                                <select class="form-select action-dropdown" data-type="logbook"
                                    data-id="<?php echo $logbookNotif['Logbook_ID']; ?>" onchange="updateStatus(this)">
                                    <option value="Waiting for Approval" selected>Waiting for Approval</option>
                                    <option value="Approved">Approved</option>
                                    <option value="Rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>

    <!-- Acknowledgement Modal -->
    <div id="acknowledgementModal" class="custom-modal">
        <div class="modal-dialog">
            <div class="modal-content-custom">
                <span class="close-btn" id="closeAcknowledgementModal">&times;</span>
                <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="modal-title-custom" id="modalTitle">Logbook is approved!</div>
                <div class="modal-message" id="modalMessage">The logbook approval is recorded successfully.</div>
                <div style="display:flex; justify-content:center;">
                    <button id="okAcknowledgementBtn" class="btn btn-success">OK</button>
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

        // ==========================================
        // MODAL FUNCTIONS
        // ==========================================
        const acknowledgementModal = document.getElementById('acknowledgementModal');
        const closeAcknowledgementModalBtn = document.getElementById('closeAcknowledgementModal');
        const okAcknowledgementBtn = document.getElementById('okAcknowledgementBtn');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');

        function openModal(modal) { modal.style.display = 'block'; }
        function closeModal(modal) { modal.style.display = 'none'; }

        // Close modal event listeners
        if (closeAcknowledgementModalBtn) {
            closeAcknowledgementModalBtn.addEventListener('click', () => {
                closeModal(acknowledgementModal);
                window.location.reload();
            });
        }

        if (okAcknowledgementBtn) {
            okAcknowledgementBtn.addEventListener('click', () => {
                closeModal(acknowledgementModal);
                window.location.reload();
            });
        }

        // Close modal on backdrop click
        acknowledgementModal?.addEventListener('click', (e) => {
            if (e.target.id === 'acknowledgementModal') {
                closeModal(acknowledgementModal);
                window.location.reload();
            }
        });

        // ==========================================
        // UPDATE STATUS LOGIC
        // ==========================================
        function updateStatus(selectElement) {
            const newStatus = selectElement.value;
            const notifType = selectElement.getAttribute('data-type');
            const recordId = selectElement.getAttribute('data-id');

            if (newStatus === 'Waiting For Approval' || newStatus === 'Waiting for Approval') {
                return; // No action needed for default status
            }

            // Send AJAX request to update status
            fetch('update_notification_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `type=${notifType}&id=${recordId}&status=${encodeURIComponent(newStatus)}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Set modal message based on notification type and action
                        if (notifType === 'logbook') {
                            if (newStatus === 'Approved') {
                                modalTitle.textContent = 'Logbook is approved!';
                                modalMessage.textContent = 'The logbook approval is recorded successfully.';
                            } else if (newStatus === 'Rejected') {
                                modalTitle.textContent = 'Logbook is rejected!';
                                modalMessage.textContent = 'The logbook rejection is recorded successfully.';
                            }
                        } else if (notifType === 'title') {
                            if (newStatus === 'Approved') {
                                modalTitle.textContent = 'Project title is approved!';
                                modalMessage.textContent = 'The project title approval is recorded successfully.';
                            } else if (newStatus === 'Rejected') {
                                modalTitle.textContent = 'Project title is rejected!';
                                modalMessage.textContent = 'The project title rejection is recorded successfully.';
                            }
                        }
                        
                        // Show modal instead of alert
                        openModal(acknowledgementModal);
                    } else {
                        alert('Error: ' + data.message);
                        selectElement.value = notifType === 'title' ? 'Waiting For Approval' : 'Waiting for Approval';
                    }
                })
                .catch(error => {
                    alert('Error updating status. Please try again.');
                    console.error('Error:', error);
                    selectElement.value = notifType === 'title' ? 'Waiting For Approval' : 'Waiting for Approval';
                });
        }

    </script>
</body>

</html>