
<?php
// Prevent caching to stop back button access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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
    fs.FYP_Session_ID,
    c.Course_Code,
    fp.Title_Status
FROM student s
LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
LEFT JOIN course c ON fs.Course_ID = c.Course_ID
LEFT JOIN fyp_project fp ON fp.Student_ID = s.Student_ID
WHERE s.Student_ID = ?
ORDER BY fs.FYP_Session_ID DESC
LIMIT 1";

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
$titleStatus = trim($student['Title_Status'] ?? '');

// Map DB title status to display text for widget
if ($titleStatus === 'Approved') {
    $approvalDisplay = 'APPROVED';
} elseif ($titleStatus === 'Rejected' || strcasecmp($titleStatus, 'Declined') === 0) {
    $approvalDisplay = 'REJECTED';
} elseif ($titleStatus === '') {
    $approvalDisplay = 'NO FYP TITLE';
} else {
    // Any other status (e.g., Waiting For Approval) treated as pending
    $approvalDisplay = 'PENDING';
}

// Logbook progress counts (Approved / Waiting / Rejected)
$approvedCount = 0;
$waitingCount = 0;
$rejectedCount = 0;
$requiredTotal = 6; // Fixed total required logbooks

$logbookStatusQuery = "SHOW TABLES LIKE 'logbook'";
$tableExists = $conn->query($logbookStatusQuery);
if ($tableExists && $tableExists->num_rows > 0) {
    $statusQuery = "SELECT Logbook_Status FROM logbook WHERE Student_ID = ? AND Fyp_Session_ID = ?";
    $stmtStatus = $conn->prepare($statusQuery);
    if ($stmtStatus) {
        $stmtStatus->bind_param("si", $studentId, $student['FYP_Session_ID']);
        $stmtStatus->execute();
        $resultStatus = $stmtStatus->get_result();
        while ($rowStatus = $resultStatus->fetch_assoc()) {
            $status = trim($rowStatus['Logbook_Status'] ?? '');
            if ($status === 'Approved') {
                $approvedCount++;
            } elseif (strcasecmp($status, 'Declined') === 0 || $status === 'Rejected') {
                $rejectedCount++;
            } else {
                $waitingCount++;
            }
        }
        $stmtStatus->close();
    }
}

// Get assessment session date and time for countdown (first session only for countdown)
$assessmentDate = null;
$assessmentTime = null;
$assessmentVenue = null;
$assessmentDateTime = null;

$assessmentQuery = "SELECT as_session.Date, as_session.Time, as_session.Venue
                    FROM student_session ss
                    INNER JOIN assessment_session as_session ON ss.Session_ID = as_session.Session_ID
                    INNER JOIN student s ON ss.Student_ID = s.Student_ID
                    WHERE ss.Student_ID = ? AND ss.FYP_Session_ID = ? AND s.FYP_Session_ID = ?
                    ORDER BY as_session.Date ASC, as_session.Time ASC
                    LIMIT 1";

$assessmentStmt = $conn->prepare($assessmentQuery);
if ($assessmentStmt) {
    $assessmentStmt->bind_param("sii", $studentId, $student['FYP_Session_ID'], $student['FYP_Session_ID']);
    $assessmentStmt->execute();
    $assessmentResult = $assessmentStmt->get_result();
    if ($assessmentRow = $assessmentResult->fetch_assoc()) {
        $assessmentDate = $assessmentRow['Date'];
        $assessmentTime = $assessmentRow['Time'];
        $assessmentVenue = $assessmentRow['Venue'];
        
        // Combine date and time for countdown calculation
        if ($assessmentDate && $assessmentTime) {
            $assessmentDateTime = $assessmentDate . ' ' . $assessmentTime;
        } elseif ($assessmentDate) {
            $assessmentDateTime = $assessmentDate . ' 00:00:00';
        }
    }
    $assessmentStmt->close();
}

// Get the latest assessment session with assessor names for reminder section
// Only fetch the most recent assessment for the latest FYP_Session_ID
$assessmentSessions = [];
$assessmentSessionsQuery = "SELECT 
                                as_session.Date, 
                                as_session.Time, 
                                as_session.Venue,
                                l1.Lecturer_Name AS Assessor1_Name,
                                l2.Lecturer_Name AS Assessor2_Name
                            FROM student_session ss
                            INNER JOIN assessment_session as_session ON ss.Session_ID = as_session.Session_ID
                            INNER JOIN student s ON ss.Student_ID = s.Student_ID
                            LEFT JOIN student_enrollment se ON s.Student_ID = se.Student_ID AND s.FYP_Session_ID = se.Fyp_Session_ID
                            LEFT JOIN assessor a1 ON se.Assessor_ID_1 = a1.Assessor_ID
                            LEFT JOIN lecturer l1 ON a1.Lecturer_ID = l1.Lecturer_ID
                            LEFT JOIN assessor a2 ON se.Assessor_ID_2 = a2.Assessor_ID
                            LEFT JOIN lecturer l2 ON a2.Lecturer_ID = l2.Lecturer_ID
                            WHERE ss.Student_ID = ? AND ss.FYP_Session_ID = ? AND s.FYP_Session_ID = ?
                            ORDER BY as_session.Date ASC, as_session.Time ASC
                            LIMIT 1";

$assessmentSessionsStmt = $conn->prepare($assessmentSessionsQuery);
if ($assessmentSessionsStmt) {
    $assessmentSessionsStmt->bind_param("sii", $studentId, $student['FYP_Session_ID'], $student['FYP_Session_ID']);
    $assessmentSessionsStmt->execute();
    $assessmentSessionsResult = $assessmentSessionsStmt->get_result();
    while ($sessionRow = $assessmentSessionsResult->fetch_assoc()) {
        // Keep assessor names as an array for better display
        $assessors = [];
        if (!empty($sessionRow['Assessor1_Name'])) {
            $assessors[] = $sessionRow['Assessor1_Name'];
        }
        if (!empty($sessionRow['Assessor2_Name'])) {
            $assessors[] = $sessionRow['Assessor2_Name'];
        }
        
        $assessmentSessions[] = [
            'date' => $sessionRow['Date'],
            'time' => $sessionRow['Time'],
            'venue' => $sessionRow['Venue'] ?? 'N/A',
            'assessors' => $assessors // Keep as array for bullet point display
        ];
    }
    $assessmentSessionsStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    
    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()">☰</button> 
        
        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>
            <span id="nameSide">HI, <?php echo htmlspecialchars($studentName); ?></span>
            <a href="../dashboard/dashboard.php" id="dashboard" class="focus"> <i class="bi bi-house-fill" style="padding-right: 10px;"></i>Dashboard</a>
            <a href="../fypInformation/fypInformation.php" id="fypInformation"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>FYP Information</a>
            <a href="../logbook/logbook.php" id="logbookSubmission"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>Logbook Submission</a>
            <a href="../notification/notification.php" id="notification"><i class="bi bi-bell-fill" style="padding-right: 10px;"></i>Notification</a>
            <a href="../signatureUpload/signatureUpload.php" id="signatureSubmission"><i class="bi bi-pen-fill" style="padding-right: 10px;"></i>Signature Submission</a>
          
            <a href="../../logout.php" id="logout">
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

   <!-- CRITICAL FIX: Added id="main" here to match the JavaScript code -->
    <div id="main" class="main-grid">
        <!-- Top widgets would go here, maintaining vertical alignment with the section below -->
        <div class="metrics-grid">
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-check-circle"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Title Approval Status</span>
                    <span id="approvalStatus" class="widget-value"><?php echo htmlspecialchars($approvalDisplay); ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-book"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Logbook Progress</span>
                    <span id="logbookProgress" class="widget-value"><?php echo htmlspecialchars($approvedCount . '/' . $requiredTotal); ?></span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-clock"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Assessment Countdown</span>
                    <span id="assessmentCountdown" class="widget-value">
                        <?php 
                        if ($assessmentDateTime) {
                            echo 'Loading...';
                        } else {
                            echo 'No Assessment Scheduled';
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <!-- New Two-Column Layout Section -->
        <div class="task-reminder-section">

            <!-- LEFT COLUMN: Logbook Submission Task List -->
            <div class="evaluation-area">
                <h1 class="card-title">Logbook Submission</h1>

                <div class="evaluation-task-card">
                    <div class="tab-buttons">
                        <button class="task-tab active-tab" data-tab="logbook">Logbook Submission</button>
                    </div>
                    <div class="task-list-area">

                        <!-- Logbook Submission View (Active by default) -->
                        <div class="task-group active" data-group="logbook">
                            <div class="student-click-hint" data-tab="logbook">
                                Click on a bar to jump to filtered logbooks.<br><br>
                                Hover over the graph to see more details.
                            </div>
                            <div class="graph-container-wrapper">
                                <div id="graphContainer">
                                    <canvas id="barChart"></canvas>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Reminder Section -->
            <div class="reminder-area">
                <h1 class="card-title">Reminder</h1>
                <div class="reminder-card">
                    <div class="reminder-card-content">
                        <?php if (!empty($assessmentSessions)): ?>
                            <?php foreach ($assessmentSessions as $index => $session): ?>
                                <?php if ($index > 0): ?>
                                    <hr class="reminder-separator">
                                <?php endif; ?>
                                <div class="reminder-item">
                                    <?php
                                    // Format date for display (e.g., "15 January 2025")
                                    $formattedDate = 'N/A';
                                    if (!empty($session['date'])) {
                                        $dateObj = DateTime::createFromFormat('Y-m-d', $session['date']);
                                        if ($dateObj) {
                                            $formattedDate = $dateObj->format('d F Y');
                                        }
                                    }
                                    
                                    // Format time for display (e.g., "09:00 AM")
                                    $formattedTime = 'N/A';
                                    if (!empty($session['time'])) {
                                        $timeObj = DateTime::createFromFormat('H:i:s', $session['time']);
                                        if ($timeObj) {
                                            $formattedTime = $timeObj->format('h:i A');
                                        } else {
                                            $timeObj = DateTime::createFromFormat('H:i', $session['time']);
                                            if ($timeObj) {
                                                $formattedTime = $timeObj->format('h:i A');
                                            }
                                        }
                                    }
                                    ?>
                                    <p class="reminder-date"><?php echo htmlspecialchars($formattedDate); ?></p>
                                    <ul>
                                        <li><strong>Time:</strong> <?php echo htmlspecialchars($formattedTime); ?></li>
                                        <li><strong>Venue:</strong> <?php echo htmlspecialchars($session['venue']); ?></li>
                                        <li><strong>Assessor(s):</strong>
                                            <?php if (!empty($session['assessors'])): ?>
                                                <ul style="margin-top: 5px; padding-left: 20px;">
                                                    <?php foreach ($session['assessors'] as $assessor): ?>
                                                        <li><?php echo htmlspecialchars($assessor); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="reminder-item">
                                <p class="reminder-date">No Assessment Scheduled</p>
                                <ul>
                                    <li>No assessment sessions have been assigned yet.</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Logbook Submission Reminder -->
                        <?php if (!empty($assessmentSessions)): ?>
                            <hr class="reminder-separator">
                        <?php endif; ?>
                        <div class="reminder-item">
                            <p class="reminder-date">Logbook Submission</p>
                            <ul>
                                <li>Please complete all logbook submissions for <strong><?php echo htmlspecialchars($courseCode); ?></strong> before the semester ends.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        .custom-tooltip {
            opacity: 0;
            position: absolute;
            background: transparent;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 1000;
            font-family: 'Montserrat', sans-serif;
            min-width: 180px;
        }
        .student-click-hint {
            margin: 8px 0 12px 0;
            padding: 10px 12px;
            background: #f5f0ea;
            border-left: 4px solid #780000;
            border-radius: 6px;
            color: #3a2a2a;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
        }
        .tooltip-header {
            background-color: #363636;
            color: #ffffff;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px 4px 0 0;
        }
        .tooltip-body {
            background-color: #1a2a1a;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 0 0 4px 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tooltip-indicator {
            width: 12px;
            height: 12px;
            background-color: #4CAF50;
            border: 1px solid #e0e0e0;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .tooltip-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 13px;
            color: #e0e0e0;
        }
        .tooltip-content div {
            color: #e0e0e0;
        }
    </style>
    <script>
        let barChartInstance = null;
        let assessmentDateTimeValue = <?php echo $assessmentDateTime ? json_encode($assessmentDateTime) : 'null'; ?>;
        let assessmentCountdownInterval = null;

        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('barChart');
            if (ctx) {
                // Inject server-derived counts
                var approvedCount = <?php echo json_encode($approvedCount); ?>;
                var waitingCount = <?php echo json_encode($waitingCount); ?>;
                var rejectedCount = <?php echo json_encode($rejectedCount); ?>;
                var totalRequired = <?php echo json_encode($requiredTotal); ?>;

                function toPct(count) {
                    return totalRequired ? Math.round((count / totalRequired) * 100) : 0;
                }

                barChartInstance = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Approved', 'Waiting for approval', 'Rejected'],
                        datasets: [{
                            label: 'Logbook Progress (%)',
                            data: [toPct(approvedCount), toPct(waitingCount), toPct(rejectedCount)],
                            raw_data: [approvedCount, waitingCount, rejectedCount],
                            total_count: totalRequired,
                            backgroundColor: [
                                '#4CAF50',
                                '#FF9800',
                                '#F44336'
                            ],
                            borderColor: 'transparent',
                            borderWidth: 0,
                            barPercentage: 0.8,
                            categoryPercentage: 0.8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        onHover: (event, activeElements) => {
                            event.native.target.style.cursor = activeElements.length > 0 ? 'pointer' : 'default';
                        },
                        onClick: (event, activeElements) => {
                            if (activeElements.length > 0) {
                                const dataIndex = activeElements[0].index;
                                const labels = ['Approved', 'Waiting for approval', 'Rejected'];
                                const statusMap = {
                                    'Approved': 'approved',
                                    'Waiting for approval': 'pending',
                                    'Rejected': 'rejected'
                                };
                                const selectedLabel = labels[dataIndex];
                                const statusFilter = statusMap[selectedLabel];
                                const fypSessionId = <?php echo json_encode($student['FYP_Session_ID'] ?? null); ?>;
                                
                                let url = '../logbook/logbook.php?status=' + encodeURIComponent(statusFilter);
                                if (fypSessionId) {
                                    url += '&fyp_session_id=' + encodeURIComponent(fypSessionId);
                                }
                                window.location.href = url;
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 12 } },
                                border: { display: true, color: 'black', width: 1 },
                                title: {
                                    display: true,
                                    text: 'Submission',
                                    color: 'black',
                                    font: { size: 14, weight: 'bold' }
                                },
                            },
                            y: {
                                beginAtZero: true,
                                max: 100, 
                                ticks: { display: false },
                                border: { display: true, color: 'black', width: 1 },
                                grid: { display: false },
                                title: {
                                    display: true,
                                    text: 'Total(%)',
                                    color: 'black',
                                    font: { size: 14, weight: 'bold' }
                                },
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            datalabels: {
                                anchor: 'center',
                                align: 'center',
                                color: 'white',
                                font: { weight: 'bold' },
                                formatter: (value) => {
                                    return value > 0 ? value : '';
                                }
                            },
                            tooltip: {
                                enabled: false,
                                external: function(context) {
                                    let tooltipEl = document.getElementById('chartjs-tooltip');
                                    if (!tooltipEl) {
                                        tooltipEl = document.createElement('div');
                                        tooltipEl.id = 'chartjs-tooltip';
                                        tooltipEl.className = 'custom-tooltip';
                                        document.body.appendChild(tooltipEl);
                                    }

                                    if (!context.tooltip || context.tooltip.opacity === 0 || !context.tooltip.dataPoints || context.tooltip.dataPoints.length === 0) {
                                        tooltipEl.style.opacity = '0';
                                        tooltipEl.style.pointerEvents = 'none';
                                        return;
                                    }

                                    const chart = context.chart;
                                    const dataPoint = context.tooltip.dataPoints[0];
                                    const dataIndex = dataPoint.dataIndex;
                                    const dataset = context.tooltip.dataPoints[0].dataset;
                                    const percentage = dataset.data[dataIndex];
                                    const label = chart.data.labels[dataIndex];

                                    let rawCount = 0;
                                    let indicatorColor = '#4CAF50';

                                    if (dataIndex < 3 && dataset.raw_data) {
                                        rawCount = parseFloat(dataset.raw_data[dataIndex].toFixed(2));
                                    }

                                    if (label === 'Waiting for approval') {
                                        indicatorColor = '#FF9800';
                                    } else if (label === 'Rejected') {
                                        indicatorColor = '#F44336';
                                    }

                                    const position = chart.canvas.getBoundingClientRect();
                                    const left = position.left + context.tooltip.caretX + window.scrollX;
                                    const top = position.top + context.tooltip.caretY + window.scrollY;

                                    tooltipEl.style.opacity = '1';
                                    tooltipEl.style.left = left + 'px';
                                    tooltipEl.style.top = top + 'px';
                                    tooltipEl.style.transform = 'translate(-50%, -100%)';
                                    tooltipEl.style.marginTop = '-10px';
                                    tooltipEl.style.pointerEvents = 'none';

                                    tooltipEl.innerHTML = `
                                        <div class="tooltip-header">${label}</div>
                                        <div class="tooltip-body">
                                            <div class="tooltip-indicator" style="background-color: ${indicatorColor};"></div>
                                            <div class="tooltip-content">
                                                <div>Logbook Submission</div>
                                                <div>Percentage: ${percentage}%</div>
                                                <div>Count: ${rawCount} logs</div>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
    

    <script>
    // --- JAVASCRIPT LOGIC ---
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
            // Show role headers and other links
            if (links[i].classList.contains('role-header') || links[i].id === 'logout') {
                links[i].style.display = 'flex';
            } else if (links[i].id === 'close') {
                links[i].style.display = 'flex';
            } else {
                links[i].style.display = 'block';
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
   
    function updateApprovalStatusColor(){
        const status = document.getElementById('approvalStatus');
        if(!status) return;
        const statusText = status.textContent.trim().toUpperCase();
        status.className = 'widget-value';

        if(statusText.includes('APPROVED')){
            status.classList.add('status-approved');
        }
        else if(statusText.includes('REJECTED')){
            status.classList.add('status-rejected');
        }
        else{
            status.classList.add('status-pending');
        }
    }

    function startOrUpdateCountdown(newDateTime){
        const countdownElement = document.getElementById('assessmentCountdown');
        if (!countdownElement) return;

        // Clear existing interval
        if (assessmentCountdownInterval) {
            clearInterval(assessmentCountdownInterval);
            assessmentCountdownInterval = null;
        }

        assessmentDateTimeValue = newDateTime;

        if (!assessmentDateTimeValue) {
            countdownElement.textContent = 'No Assessment Scheduled';
            return;
        }

        function updateCountdown() {
            const now = new Date().getTime();
            const assessmentDate = new Date(assessmentDateTimeValue).getTime();

            if (isNaN(assessmentDate)) {
                countdownElement.textContent = 'Invalid Date';
                return;
            }

            const distance = assessmentDate - now;

            if (distance < 0) {
                countdownElement.textContent = 'Assessment Passed';
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            if (days === 0 && hours === 0 && minutes === 0 && seconds === 0) {
                countdownElement.textContent = 'DONE!';
                return;
            }

            let countdownText = '';
            if (days > 0) countdownText += days + 'd ';
            if (hours > 0 || days > 0) countdownText += hours + 'h ';
            if (minutes > 0 || hours > 0 || days > 0) countdownText += minutes + 'm ';
            countdownText += seconds + 's';
            countdownElement.textContent = countdownText.trim();
        }

        updateCountdown();
        assessmentCountdownInterval = setInterval(updateCountdown, 1000);
    }

    // Ensure the collapsed state is set immediately on page load
    window.onload = function () {
        document.getElementById("nameSide").style.display = "none";
        closeNav();
        updateApprovalStatusColor();
        startOrUpdateCountdown(assessmentDateTimeValue);
    };

    // --- REALTIME DASHBOARD POLLING ---
    let dashboardPollInterval = null;
    let dashboardDataHash = '';

    function hashDashboard(data) {
        try {
            return JSON.stringify({
                approval: data.approvalDisplay,
                approved: data.approvedCount,
                waiting: data.waitingCount,
                rejected: data.rejectedCount,
                total: data.requiredTotal,
                asmt: data.assessmentDateTime
            });
        } catch (e) {
            return '';
        }
    }

    function applyDashboardData(data) {
        if (!data || !data.success) return;

        const approvalEl = document.getElementById('approvalStatus');
        if (approvalEl && data.approvalDisplay) {
            approvalEl.textContent = data.approvalDisplay;
            updateApprovalStatusColor();
        }

        const logbookEl = document.getElementById('logbookProgress');
        if (logbookEl && data.requiredTotal) {
            logbookEl.textContent = `${data.approvedCount}/${data.requiredTotal}`;
        }

        if (barChartInstance && data.requiredTotal) {
            const toPct = (val) => data.requiredTotal ? Math.round((val / data.requiredTotal) * 100) : 0;
            const ds = barChartInstance.data.datasets[0];
            ds.data = [toPct(data.approvedCount), toPct(data.waitingCount), toPct(data.rejectedCount)];
            ds.raw_data = [data.approvedCount, data.waitingCount, data.rejectedCount];
            ds.total_count = data.requiredTotal;
            barChartInstance.update();
        }

        if (data.hasOwnProperty('assessmentDateTime')) {
            startOrUpdateCountdown(data.assessmentDateTime);
        }
    }

    function fetchDashboardMetrics() {
        fetch('../../../php/phpStudent/fetch_dashboard_metrics.php')
            .then(resp => resp.json())
            .then(data => {
                const newHash = hashDashboard(data);
                if (newHash !== dashboardDataHash) {
                    dashboardDataHash = newHash;
                    applyDashboardData(data);
                }
            })
            .catch(err => console.error('Dashboard poll failed:', err));
    }

    function startDashboardPolling() {
        fetchDashboardMetrics();
        dashboardPollInterval = setInterval(fetchDashboardMetrics, 1000);
    }

    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            if (dashboardPollInterval) { clearInterval(dashboardPollInterval); dashboardPollInterval = null; }
        } else {
            if (!dashboardPollInterval) { startDashboardPolling(); }
        }
    });

    // Initialize hash from server-rendered values
    (function initDashboardRealtime(){
        const initial = {
            success: true,
            approvalDisplay: <?php echo json_encode($approvalDisplay); ?>,
            approvedCount: <?php echo json_encode($approvedCount); ?>,
            waitingCount: <?php echo json_encode($waitingCount); ?>,
            rejectedCount: <?php echo json_encode($rejectedCount); ?>,
            requiredTotal: <?php echo json_encode($requiredTotal); ?>,
            assessmentDateTime: <?php echo $assessmentDateTime ? json_encode($assessmentDateTime) : 'null'; ?>
        };
        dashboardDataHash = hashDashboard(initial);
        startDashboardPolling();
    })();

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
</body>
</html>