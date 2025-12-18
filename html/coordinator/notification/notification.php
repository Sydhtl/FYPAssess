<?php 
include '../../../php/coordinator_bootstrap.php'; 

// Get the latest session from database for this department
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
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Coordinator Notification</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/notification.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
                <a href="#" id="NotificationSupervisor"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Industry Collaboration</a>
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
                <a href="#" id="DashboardAssessor"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="#" id="NotificationAssessor"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="EvaluationFormAssessor"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
            </div>

            <a href="#coordinatorMenu" class="role-header active-role menu-expanded" data-role="coordinator">
                <span class="role-text">Coordinator</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-down arrow-icon"></i>
                </span>
            </a>

            <div id="coordinatorMenu" class="menu-items expanded">
                <a href="../dashboard/dashboardCoordinator.php" id="coordinatorDashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../studentAssignation/studentAssignation.php" id="studentAssignation"><i class="bi bi-people-fill icon-padding"></i> Student Assignment</a>
                <a href="../learningObjective/learningObjective.php" id="learningObjective"><i class="bi bi-book-fill icon-padding"></i> Learning Objective</a>
                <a href="../markSubmission/markSubmission.php" id="markSubmission"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Signature Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Date and Time Allocation</a>
            </div>

            <a href="../../login/login.php" id="logout">
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

    <div id="main" class="main-grid">
        <div class="notification-container">
            <h1 class="page-title">Notification</h1>
            <div id="notificationsList">
                <!-- Notifications will be dynamically loaded here -->
            </div>
        </div>
    </div>

    <script>
        const collapsedWidth = "60px";
        const expandedWidth = "220px";

        document.addEventListener('DOMContentLoaded', function() {
            initializeRoleToggle();
            closeNav();
            
            // Initialize notifications
            initializeNotifications();
            startNotificationPolling();
        });

        function openNav() {
            const sidebar = document.getElementById("mySidebar");
            const header = document.getElementById("containerAtas");
            const mainContent = document.getElementById("main");
            const menuIcon = document.querySelector(".menu-icon");

            sidebar.style.width = expandedWidth;
            if (mainContent) mainContent.style.marginLeft = expandedWidth;
            if (header) header.style.marginLeft = expandedWidth;

            document.getElementById("nameSide").style.display = "block";

            const links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (let i = 0; i < links.length; i++) {
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
            const sidebar = document.getElementById("mySidebar");
            const header = document.getElementById("containerAtas");
            const mainContent = document.getElementById("main");
            const menuIcon = document.querySelector(".menu-icon");

            sidebar.style.width = collapsedWidth;
            if (mainContent) mainContent.style.marginLeft = collapsedWidth;
            if (header) header.style.marginLeft = collapsedWidth;

            document.getElementById("nameSide").style.display = "none";

            const links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (let i = 0; i < links.length; i++) {
                links[i].style.display = "none";
            }

            if (menuIcon) menuIcon.style.display = "block";
        }

        function initializeRoleToggle() {
            let activeMenuItemId = 'coordinatorNotification';

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
            allRoleHeaders.forEach(header => {
                if (header.getAttribute('data-role') === 'coordinator') {
                    header.classList.add('active-role');
                    header.classList.add('menu-expanded');
                } else {
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                }
            });

            setActiveMenuItem('coordinatorNotification');

            const roleHeaders = document.querySelectorAll('.role-header');
            roleHeaders.forEach(header => {
                header.addEventListener('click', function(e) {
                    e.preventDefault();
                    const role = this.getAttribute('data-role');
                    const menuId = `${role}Menu`;
                    const menu = document.getElementById(menuId);

                    if (!menu) return;

                    const isExpanded = menu.classList.contains('expanded');
                    const arrow = this.querySelector('.arrow-icon');

                    // Collapse all other menus and reset their arrows
                    document.querySelectorAll('.menu-items').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.remove('expanded');
                            const otherHeader = document.querySelector(`.role-header[data-role="${otherMenu.id.replace('Menu', '')}"]`);
                            if (otherHeader) {
                                const otherArrow = otherHeader.querySelector('.arrow-icon');
                                if (otherArrow) {
                                    otherArrow.classList.remove('bi-chevron-down');
                                    otherArrow.classList.add('bi-chevron-right');
                                }
                            }
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
                        }
                    }

                    // Remove active-role from all non-coordinator roles (they shouldn't be highlighted on coordinator pages)
                    document.querySelectorAll('.role-header').forEach(h => {
                        const roleType = h.getAttribute('data-role');
                        // Only keep active-role for coordinator on coordinator pages
                        if (roleType !== 'coordinator') {
                            h.classList.remove('active-role');
                            h.classList.remove('menu-expanded');
                        }
                    });

                    // Toggle current menu
                    if (isExpanded) {
                        menu.classList.remove('expanded');
                        this.classList.remove('menu-expanded');
                        if (arrow) {
                            arrow.classList.remove('bi-chevron-down');
                            arrow.classList.add('bi-chevron-right');
                        }
                    } else {
                        menu.classList.add('expanded');
                        if (role === 'coordinator') {
                            this.classList.add('menu-expanded');
                        }
                        if (arrow) {
                            arrow.classList.remove('bi-chevron-right');
                            arrow.classList.add('bi-chevron-down');
                        }
                    }

                    // IMPORTANT: After toggling other roles, ensure coordinator header state is maintained
                    // This ensures coordinator stays white when its menu is collapsed, even when other roles are clicked
                    if (coordinatorHeader && coordinatorMenu && role !== 'coordinator') {
                        coordinatorHeader.classList.add('active-role');
                        if (!coordinatorMenu.classList.contains('expanded')) {
                            coordinatorHeader.classList.remove('menu-expanded');
                        } else {
                            coordinatorHeader.classList.add('menu-expanded');
                        }
                    }

                    // Show/hide child links for the current menu (only when sidebar is expanded)
                    const sidebar = document.getElementById("mySidebar");
                    const isSidebarExpanded = sidebar.style.width === expandedWidth;

                    menu.querySelectorAll('a').forEach(a => {
                        if (isSidebarExpanded) {
                            a.style.display = menu.classList.contains('expanded') ? 'block' : 'none';
                        } else {
                            a.style.display = 'none';
                        }
                    });
                });
            });
        }
        
        // --- REAL-TIME NOTIFICATION UPDATES ---
        let notificationUpdateInterval = null;
        let currentNotificationsHash = '';
        
        // Helper function to format remaining days
        function formatRemainingDays(days) {
            if (days === null || days === undefined) return 'N/A';
            if (days < 0) return 'Overdue (' + Math.abs(days) + ' days)';
            if (days === 0) return 'Due today';
            if (days === 1) return '1 day remaining';
            return days + ' days remaining';
        }
        
        // Helper function to format date
        function formatDate(dateStr) {
            if (!dateStr) return 'N/A';
            const date = new Date(dateStr);
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dayName = days[date.getDay()];
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            return day + ' ' + month + ' ' + year + ', ' + dayName;
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Function to generate a hash for notifications array
        function getNotificationsHash(notifications) {
            return JSON.stringify(notifications.map(n => ({
                lecturer_id: n.lecturer_id,
                tasks: n.tasks.map(t => ({
                    assessment_id: t.assessment_id,
                    role: t.role
                }))
            })));
        }
        
        // Function to render notifications
        function renderNotifications(notifications) {
            const container = document.getElementById('notificationsList');
            if (!container) return;
            
            if (!notifications || notifications.length === 0) {
                container.innerHTML = '<div class="notification-item">' +
                    '<div class="notification-description">No incomplete tasks for lecturers at this time.</div>' +
                    '</div>';
                return;
            }
            
            let html = '';
            notifications.forEach((notif, index) => {
                const lecturerName = escapeHtml(notif.lecturer_name || 'N/A');
                
                // Group tasks by role
                const supervisorTasks = notif.tasks.filter(t => t.role === 'Supervisor');
                const assessorTasks = notif.tasks.filter(t => t.role === 'Assessor');
                
                html += '<div class="notification-item">' +
                    '<div class="notification-description">' +
                    '<span class="notif-number">' + (index + 1) + '.</span> ' +
                    'Lecturer ' + lecturerName + ' has incomplete assessment tasks.' +
                    '</div>' +
                    '<div class="notif-card">' +
                    '<div class="notif-details">' +
                    '<p><strong>Lecturer</strong>: ' + lecturerName + '</p>';
                
                // Supervisor tasks
                if (supervisorTasks.length > 0) {
                    html += '<div style="margin-top: 10px;"><strong>Supervisor Tasks:</strong></div>';
                    supervisorTasks.forEach(task => {
                        html += '<div style="margin-left: 20px; margin-top: 5px;">' +
                            '<p style="margin: 0;">' + escapeHtml(task.assessment_name) + '</p>' +
                            '</div>';
                    });
                }
                
                // Assessor tasks
                if (assessorTasks.length > 0) {
                    html += '<div style="margin-top: 10px;"><strong>Assessor Tasks:</strong></div>';
                    assessorTasks.forEach(task => {
                        html += '<div style="margin-left: 20px; margin-top: 5px;">' +
                            '<p style="margin: 0;">' + escapeHtml(task.assessment_name) + '</p>' +
                            '</div>';
                    });
                }
                
                html += '</div>' +
                    '<div class="notif-action">' +
                    '<button type="button" class="btn btn-outline-dark action-btn" onclick="window.location.href=\'../markSubmission/markSubmission.php\'">' +
                    'View Details' +
                    '</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
            });
            
            container.innerHTML = html;
        }
        
        // Function to fetch notifications from server
        function fetchNotifications() {
            const year = <?php echo json_encode($currentYear); ?>;
            const semester = <?php echo json_encode($currentSemester); ?>;
            const params = new URLSearchParams({
                year: year,
                semester: semester
            });
            fetch('../../../php/phpCoordinator/fetch_lecturer_notifications.php?' + params.toString())
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.notifications) {
                        const newHash = getNotificationsHash(data.notifications);
                        // Only update if there are changes
                        if (newHash !== currentNotificationsHash) {
                            currentNotificationsHash = newHash;
                            renderNotifications(data.notifications);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }
        
        // Initialize notifications (first load)
        function initializeNotifications() {
            fetchNotifications();
        }
        
        // Start polling for updates every 5 seconds
        function startNotificationPolling() {
            // Set up interval to check every 5 seconds
            notificationUpdateInterval = setInterval(fetchNotifications, 5000);
        }
        
        // Stop polling when page is hidden (to save resources)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                if (notificationUpdateInterval) {
                    clearInterval(notificationUpdateInterval);
                    notificationUpdateInterval = null;
                }
            } else {
                if (!notificationUpdateInterval) {
                    startNotificationPolling();
                }
            }
        });
    </script>
</body>
</html>
