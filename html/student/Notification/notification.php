<?php
include '../../../php/mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../../login/Login.php");
    exit();
}

$studentId = $_SESSION['upmId'];

$query = "SELECT 
    s.Student_ID,
    s.Student_Name,
    s.Semester,
    fs.FYP_Session,
    c.Course_Code
FROM student s
LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
LEFT JOIN course c ON fs.Course_ID = c.Course_ID
WHERE s.Student_ID = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$studentName = $student['Student_Name'] ?? 'N/A';
$courseCode = $student['Course_Code'] ?? 'N/A';
$semesterRaw = $student['Semester'] ?? 'N/A';
$fypSession = $student['FYP_Session'] ?? 'N/A';

// Initialize session for tracking notification changes
if (!isset($_SESSION['last_notifications'])) {
    $_SESSION['last_notifications'] = [];
}

// Fetch notifications: Title submissions and Logbook submissions
$notifications = [];

// 1. Fetch Title notifications from fyp_project
// Only show if Proposed_Title exists and Title_Status is set
$titleQuery = "
    SELECT fp.Proposed_Title, fp.Title_Status, fp.Project_ID,
           l.Lecturer_Name as Supervisor_Name
    FROM fyp_project fp
    INNER JOIN student s ON fp.Student_ID = s.Student_ID
    LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
    LEFT JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
    WHERE fp.Student_ID = ?
      AND fp.Proposed_Title IS NOT NULL 
      AND fp.Proposed_Title != ''
      AND fp.Title_Status IS NOT NULL
      AND fp.Title_Status != ''
    ORDER BY fp.Project_ID DESC
    LIMIT 1
";
$titleStmt = $conn->prepare($titleQuery);
if ($titleStmt) {
    $titleStmt->bind_param("s", $studentId);
    $titleStmt->execute();
    $titleResult = $titleStmt->get_result();
    if ($titleRow = $titleResult->fetch_assoc()) {
        $projectId = (int)($titleRow['Project_ID'] ?? 0);
        $status = $titleRow['Title_Status'] ?? '';
        $notifKey = 'title_' . $projectId;
        
        // Check if this notification has changed since last view
        $isNew = false;
        $changeTime = null;
        
        if (isset($_SESSION['last_notifications'][$notifKey])) {
            $lastData = $_SESSION['last_notifications'][$notifKey];
            $lastStatus = $lastData['status'] ?? '';
            $lastChangeTime = $lastData['change_time'] ?? null;
            
            if ($lastStatus !== $status) {
                // Status just changed - record the change time
                $isNew = true;
                $changeTime = time();
            } else {
                // Status unchanged - use existing change time if available
                $changeTime = $lastChangeTime;
                // Keep showing as "new" if changed in this session
                $isNew = ($lastChangeTime !== null);
            }
        } else {
            // First time seeing this notification
            $isNew = true;
            $changeTime = time();
        }
        
        // Use change time for sorting - new/changed notifications stay at top
        if ($changeTime !== null) {
            $sortDateStr = date('Y-m-d H:i:s', $changeTime);
        } else {
            // Old notifications use a past date
            $sortDateStr = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00';
        }
        
        $notifications[] = [
            'type' => 'title',
            'title' => $titleRow['Proposed_Title'],
            'status' => $titleRow['Title_Status'],
            'supervisor' => $titleRow['Supervisor_Name'] ?? 'N/A',
            'date' => date('Y-m-d'), // Display date
            'sort_date' => $sortDateStr,
            'project_id' => $projectId,
            'notif_key' => $notifKey,
            'is_new' => $isNew
        ];
        
        // Update session with current status and change time
        $_SESSION['last_notifications'][$notifKey] = [
            'status' => $status,
            'change_time' => $changeTime
        ];
    }
    $titleStmt->close();
}

// 2. Fetch Logbook notifications from logbook table
$logbookQuery = "
    SELECT l.Logbook_Name, l.Logbook_Status, l.Logbook_Date,
           lec.Lecturer_Name as Supervisor_Name
    FROM logbook l
    INNER JOIN student s ON l.Student_ID = s.Student_ID
    LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
    LEFT JOIN lecturer lec ON sup.Lecturer_ID = lec.Lecturer_ID
    WHERE l.Student_ID = ?
      AND l.Logbook_Status IS NOT NULL
      AND l.Logbook_Status != ''
    ORDER BY l.Logbook_Date DESC, l.Logbook_ID DESC
";
$logbookStmt = $conn->prepare($logbookQuery);
if ($logbookStmt) {
    $logbookStmt->bind_param("s", $studentId);
    $logbookStmt->execute();
    $logbookResult = $logbookStmt->get_result();
    $logbookIndex = 0;
    while ($logRow = $logbookResult->fetch_assoc()) {
        $logbookName = $logRow['Logbook_Name'];
        $logbookDate = $logRow['Logbook_Date'];
        $status = $logRow['Logbook_Status'];
        $notifKey = 'logbook_' . $logbookName . '_' . $logbookDate;
        
        // Check if this logbook notification has changed since last view
        $isNew = false;
        $changeTime = null;
        
        if (isset($_SESSION['last_notifications'][$notifKey])) {
            $lastData = $_SESSION['last_notifications'][$notifKey];
            $lastStatus = $lastData['status'] ?? '';
            $lastChangeTime = $lastData['change_time'] ?? null;
            
            if ($lastStatus !== $status) {
                // Status just changed - record the change time
                $isNew = true;
                $changeTime = time();
            } else {
                // Status unchanged - use existing change time if available
                $changeTime = $lastChangeTime;
                // Keep showing as "new" if changed in this session
                $isNew = ($lastChangeTime !== null);
            }
        } else {
            // First time seeing this notification
            $isNew = true;
            $changeTime = time();
        }
        
        // Use change time for sorting - new/changed notifications stay at top
        if ($changeTime !== null) {
            $sortDateStr = date('Y-m-d H:i:s', $changeTime - $logbookIndex); // Slightly offset to maintain order
        } else {
            // Old notifications use a past date
            $sortDateStr = date('Y-m-d', strtotime('-30 days')) . ' ' . date('H:i:s', strtotime($logbookDate));
        }
        
        $notifications[] = [
            'type' => 'logbook',
            'title' => $logbookName,
            'status' => $status,
            'supervisor' => $logRow['Supervisor_Name'] ?? 'N/A',
            'date' => $logbookDate,
            'sort_date' => $sortDateStr,
            'notif_key' => $notifKey,
            'is_new' => $isNew
        ];
        
        // Update session with current status and change time
        $_SESSION['last_notifications'][$notifKey] = [
            'status' => $status,
            'change_time' => $changeTime
        ];
        $logbookIndex++;
    }
    $logbookStmt->close();
}

// Sort notifications by date (most recent first)
// Compare dates properly - actual date determines order, not type
usort($notifications, function($a, $b) {
    // Convert dates to timestamps for proper comparison
    $dateA = strtotime($a['sort_date']);
    $dateB = strtotime($b['sort_date']);
    
    // Most recent first
    if ($dateB != $dateA) {
        return $dateB - $dateA;
    }
    
    // If dates are equal, use Project_ID for tie-breaking (higher = more recent)
    if (isset($a['project_id']) && isset($b['project_id'])) {
        return $b['project_id'] - $a['project_id'];
    }
    
    // If dates are equal and only one has project_id, they're equal priority
    return 0;
});

// Helper function to get status color and display text
function getStatusStyle($status) {
    $statusLower = strtolower($status);
    if (strpos($statusLower, 'approved') !== false || $statusLower === 'approved') {
        return [
            'color' => '#2e7d32',
            'bg_color' => '#e8f5e9',
            'text' => 'Approved'
        ];
    } elseif (strpos($statusLower, 'rejected') !== false || strpos($statusLower, 'declined') !== false || $statusLower === 'rejected' || $statusLower === 'declined') {
        return [
            'color' => '#c62828',
            'bg_color' => '#ffebee',
            'text' => ($statusLower === 'rejected') ? 'Rejected' : 'Declined'
        ];
    } else {
        // Waiting For Approval, Waiting for approval, etc.
        return [
            'color' => '#f57c00',
            'bg_color' => '#fffbea',
            'text' => 'Waiting For Approval'
        ];
    }
}

// Helper function to format date
function formatNotificationDate($dateStr) {
    if (empty($dateStr)) return 'N/A';
    $date = new DateTime($dateStr);
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $dayName = $days[(int)$date->format('w')];
    $day = $date->format('j');
    $month = $months[(int)$date->format('n') - 1];
    $year = $date->format('Y');
    return "$day $month $year, $dayName";
}

// Helper function to get notification description
function getNotificationDescription($type, $status) {
    $statusLower = strtolower($status);
    if ($type === 'title') {
        if (strpos($statusLower, 'approved') !== false || $statusLower === 'approved') {
            return "Your FYP title has been approved by your supervisor.";
        } elseif (strpos($statusLower, 'rejected') !== false || $statusLower === 'rejected') {
            return "Your FYP title has been rejected by your supervisor. Please submit a new title.";
        } else {
            return "Your FYP title is pending approval from your supervisor.";
        }
    } else { // logbook
        if (strpos($statusLower, 'approved') !== false || $statusLower === 'approved') {
            return "Your logbook submission has been approved by your supervisor.";
        } elseif (strpos($statusLower, 'declined') !== false || strpos($statusLower, 'rejected') !== false || $statusLower === 'declined' || $statusLower === 'rejected') {
            return "Your logbook submission has been declined by your supervisor. Please resubmit your logbook.";
        } else {
            return "Your logbook submission is pending approval from your supervisor.";
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/student/notification.css">
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
            <span id="nameSide">HI, <?php echo htmlspecialchars($studentName); ?></span>
            <a href="../dashboard/dashboard.php" id="dashboard"> <i class="bi bi-house-fill" style="padding-right: 10px;"></i>Dashboard</a>
            <a href="../fypInformation/fypInformation.php" id="fypInformation"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>FYP Information</a>
            <a href="../logbook/logbook.php" id="logbookSubmission"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>Logbook Submission</a>
            <a href="../Notification/notification.php" id="notification" class="focus"><i class="bi bi-bell-fill" style="padding-right: 10px;"></i>Notification</a>
            <a href="../signatureUpload/signatureUpload.php" id="signatureSubmission"><i class="bi bi-pen-fill" style="padding-right: 10px;"></i>Signature Submission</a>

            <a href="../../login/login.php" id="logout">
                <i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout
            </a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">

        <a href="../dashboard/dashboard.php">
            <img src="../../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>

        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule">Student Module</div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode"><?php echo htmlspecialchars($courseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars($fypSession . ' - ' . $semesterRaw); ?></div>
            </div>
        </div>

    </div>

    <div id="main">
        <div class="notification-container">
            <h1 class="page-title">Notification</h1>

            <?php if (empty($notifications)): ?>
                <div class="notification-item">
                    <div class="notification-description">
                        No notifications available at this time.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $index => $notif): 
                    $statusStyle = getStatusStyle($notif['status']);
                    $description = getNotificationDescription($notif['type'], $notif['status']);
                    $formattedDate = formatNotificationDate($notif['date']);
                ?>
                    <div class="notification-item">
                        <div class="notification-description">
                            <span class="notif-number"><?php echo ($index + 1); ?>.</span> 
                            <?php echo htmlspecialchars($description); ?>
                        </div>
                        <div class="notif-card">
                            <div class="notif-details">
                                <?php if ($notif['type'] === 'title'): ?>
                                    <p><strong>Title</strong>: <?php echo htmlspecialchars($notif['title']); ?></p>
                                <?php else: // logbook ?>
                                    <p><strong>Submission title</strong>: <?php echo htmlspecialchars($notif['title']); ?></p>
                                <?php endif; ?>
                                <p><strong>Status</strong>: <span style="color: <?php echo $statusStyle['color']; ?>; font-weight: bold;"><?php echo htmlspecialchars($statusStyle['text']); ?></span></p>
                                <p><strong>Supervisor</strong>: <?php echo htmlspecialchars($notif['supervisor']); ?></p>
                                <p><strong>Date</strong>: <?php echo htmlspecialchars($formattedDate); ?></p>
                            </div>
                            <div class="notif-action">
                                <?php if ($notif['type'] === 'title'): ?>
                                    <button type="button" class="btn btn-outline-dark action-btn fyp-info-btn" onclick="window.location.href='../fypInformation/fypInformation.php'">
                                        FYP Information
                                    </button>
                                <?php else: // logbook ?>
                                    <button type="button" class="btn btn-outline-dark action-btn logbook-btn" onclick="window.location.href='../logbook/logbook.php'">
                                        Logbook
                                    </button>
                                <?php endif; ?>
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

            // 1. Expand the Sidebar
            document.getElementById("mySidebar").style.width = fullWidth;

            // 2. Push the main content AND the header container to the right
            document.getElementById("main").style.marginLeft = fullWidth;
            document.getElementById("containerAtas").style.marginLeft = fullWidth;

            // 3. Show the links
            document.getElementById("nameSide").style.display = "block"; // <-- SHOW THE NAME

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                // Using flex for closebtn, block for others
                links[i].style.display = (links[i].id === 'close' ? 'flex' : 'block');
            }

            // 4. Hide the open icon
            document.getElementsByClassName("menu-icon")[0].style.display = "none";
        }

        function closeNav() {
            var collapsedWidth = "60px";

            // 1. Collapse the Sidebar
            document.getElementById("mySidebar").style.width = collapsedWidth;

            // 2. Move the main content AND the header container back
            document.getElementById("main").style.marginLeft = collapsedWidth;
            document.getElementById("containerAtas").style.marginLeft = collapsedWidth;

            // 3. Hide the name and the links
            document.getElementById("nameSide").style.display = "none"; // <-- HIDE THE NAME

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                links[i].style.display = "none";
            }

            // 4. Show the open icon
            document.getElementsByClassName("menu-icon")[0].style.display = "block";
        }

        // Ensure the collapsed state is set immediately on page load
        window.onload = function () {
            closeNav();
        };

        // --- REAL-TIME NOTIFICATION UPDATES ---
        let notificationUpdateInterval = null;
        let currentNotificationsHash = '';

        // Helper function to get status color
        function getStatusColor(status) {
            const statusLower = status.toLowerCase();
            if (statusLower.includes('approved') || statusLower === 'approved') {
                return '#2e7d32';
            } else if (statusLower.includes('rejected') || statusLower.includes('declined') || statusLower === 'rejected' || statusLower === 'declined') {
                return '#c62828';
            } else {
                return '#f57c00';
            }
        }

        // Helper function to get status text
        function getStatusText(status) {
            const statusLower = status.toLowerCase();
            if (statusLower.includes('approved') || statusLower === 'approved') {
                return 'Approved';
            } else if (statusLower.includes('rejected') || statusLower === 'rejected') {
                return 'Rejected';
            } else if (statusLower.includes('declined') || statusLower === 'declined') {
                return 'Declined';
            } else {
                return 'Waiting For Approval';
            }
        }

        // Helper function to get notification description
        function getNotificationDescription(type, status) {
            const statusLower = status.toLowerCase();
            if (type === 'title') {
                if (statusLower.includes('approved') || statusLower === 'approved') {
                    return "Your FYP title has been approved by your supervisor.";
                } else if (statusLower.includes('rejected') || statusLower === 'rejected') {
                    return "Your FYP title has been rejected by your supervisor. Please submit a new title.";
                } else {
                    return "Your FYP title is pending approval from your supervisor.";
                }
            } else {
                if (statusLower.includes('approved') || statusLower === 'approved') {
                    return "Your logbook submission has been approved by your supervisor.";
                } else if (statusLower.includes('declined') || statusLower.includes('rejected') || statusLower === 'declined' || statusLower === 'rejected') {
                    return "Your logbook submission has been declined by your supervisor. Please resubmit your logbook.";
                } else {
                    return "Your logbook submission is pending approval from your supervisor.";
                }
            }
        }

        // Helper function to format date
        function formatNotificationDate(dateStr) {
            if (!dateStr || dateStr === 'N/A') return 'N/A';
            const date = new Date(dateStr);
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const dayName = days[date.getDay()];
            const day = date.getDate();
            const month = months[date.getMonth()];
            const year = date.getFullYear();
            return day + ' ' + month + ' ' + year + ', ' + dayName;
        }

        // Function to generate a hash for notifications array
        function getNotificationsHash(notifications) {
            return JSON.stringify(notifications.map(n => ({
                type: n.type,
                status: n.status,
                date: n.date,
                project_id: n.project_id || null
            })));
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Function to render notifications
        function renderNotifications(notifications) {
            const container = document.querySelector('.notification-container');
            if (!container) return;

            let html = '<h1 class="page-title">Notification</h1>';

            if (!notifications || notifications.length === 0) {
                html += '<div class="notification-item">' +
                    '<div class="notification-description">No notifications available at this time.</div>' +
                    '</div>';
            } else {
                notifications.forEach((notif, index) => {
                    const statusColor = getStatusColor(notif.status);
                    const statusText = getStatusText(notif.status);
                    const description = escapeHtml(getNotificationDescription(notif.type, notif.status));
                    const formattedDate = escapeHtml(formatNotificationDate(notif.date));
                    const actionLink = notif.type === 'title' 
                        ? '../fypInformation/fypInformation.php' 
                        : '../logbook/logbook.php';
                    const actionText = notif.type === 'title' 
                        ? 'FYP Information' 
                        : 'Logbook';
                    const titleLabel = notif.type === 'title' ? 'Title' : 'Submission title';
                    const titleText = escapeHtml(notif.title || 'N/A');
                    const supervisorText = escapeHtml(notif.supervisor || 'N/A');

                    html += '<div class="notification-item">' +
                        '<div class="notification-description">' +
                        '<span class="notif-number">' + (index + 1) + '.</span> ' + description +
                        '</div>' +
                        '<div class="notif-card">' +
                        '<div class="notif-details">' +
                        '<p><strong>' + escapeHtml(titleLabel) + '</strong>: ' + titleText + '</p>' +
                        '<p><strong>Status</strong>: <span style="color: ' + statusColor + '; font-weight: bold;">' + escapeHtml(statusText) + '</span></p>' +
                        '<p><strong>Supervisor</strong>: ' + supervisorText + '</p>' +
                        '<p><strong>Date</strong>: ' + formattedDate + '</p>' +
                        '</div>' +
                        '<div class="notif-action">' +
                        '<button type="button" class="btn btn-outline-dark action-btn" onclick="window.location.href=\'' + actionLink + '\'">' +
                        escapeHtml(actionText) +
                        '</button>' +
                        '</div>' +
                        '</div>' +
                        '</div>';
                });
            }

            // Update container content
            const oldTitle = container.querySelector('.page-title');
            const oldItems = container.querySelectorAll('.notification-item');
            oldTitle?.remove();
            oldItems.forEach(item => item.remove());
            container.insertAdjacentHTML('afterbegin', html);
        }

        // Function to fetch notifications from server
        function fetchNotifications() {
            fetch('../../../php/phpStudent/fetch_notifications.php')
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

        // Start polling for updates every 5 seconds
        function startNotificationPolling() {
            // Initial fetch
            fetchNotifications();
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

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Get initial hash from current notifications
            const initialNotifications = <?php echo json_encode($notifications); ?>;
            currentNotificationsHash = getNotificationsHash(initialNotifications);
            
            // Start polling
            startNotificationPolling();
        });

    </script>
</body>

</html>

