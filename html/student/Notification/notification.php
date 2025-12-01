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

            <!-- Notification Item 1 - FYP Information (Title Approval) -->
            <div class="notification-item">
                <div class="notification-description">
                    <span class="notif-number">1.</span> Your FYP title has been approved by your supervisor.
                </div>
                <div class="notif-card">
                    <div class="notif-details">
                        <p><strong>Title</strong>: DEVELOPMENT OF AN AUTOMATED ASSESSMENT AND EVALUATION SYSTEM FOR BACHELOR PROJECTS (DEPARTMENT COORDINATORS AND STUDENT'S MODULE)</p>
                        <p><strong>Status</strong>: Approved</p>
                        <p><strong>Supervisor</strong>: DR. AZRINA BINTI KAMARUDDIN</p>
                        <p><strong>Date</strong>: 9 Aug 2025, Wed</p>
                    </div>
                    <div class="notif-action">
                        <button type="button" class="btn btn-outline-dark action-btn fyp-info-btn" onclick="window.location.href='../fypInformation/fypInformation.php'">
                            FYP Information
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notification Item 2 - Logbook Submission (Approved) -->
            <div class="notification-item">
                <div class="notification-description">
                    <span class="notif-number">2.</span> Your logbook submission has been approved by your supervisor.
                </div>
                <div class="notif-card">
                    <div class="notif-details">
                        <p><strong>Submission title</strong>: Week 4</p>
                        <p><strong>Status</strong>: Approved</p>
                        <p><strong>Supervisor</strong>: DR. AZRINA BINTI KAMARUDDIN</p>
                        <p><strong>Date</strong>: 10 Aug 2025, Thu</p>
                    </div>
                    <div class="notif-action">
                        <button type="button" class="btn btn-outline-dark action-btn logbook-btn" onclick="window.location.href='../logbook/logbook.php'">
                            Logbook
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notification Item 3 - FYP Information (Title Rejected) -->
            <div class="notification-item">
                <div class="notification-description">
                    <span class="notif-number">3.</span> Your FYP title has been rejected by your supervisor. Please submit a new title.
                </div>
                <div class="notif-card">
                    <div class="notif-details">
                        <p><strong>Title</strong>: DEVELOPMENT OF AN AUTOMATED ASSESSMENT AND EVALUATION SYSTEM FOR BACHELOR PROJECTS (DEPARTMENT COORDINATORS AND STUDENT'S MODULE)</p>
                        <p><strong>Status</strong>: Rejected</p>
                        <p><strong>Supervisor</strong>: DR. AZRINA BINTI KAMARUDDIN</p>
                        <p><strong>Date</strong>: 8 Aug 2025, Tue</p>
                    </div>
                    <div class="notif-action">
                        <button type="button" class="btn btn-outline-dark action-btn fyp-info-btn" onclick="window.location.href='../fypInformation/fypInformation.php'">
                            FYP Information
                        </button>
                    </div>
                </div>
            </div>

            <!-- Notification Item 4 - Logbook Submission (Declined) -->
            <div class="notification-item">
                <div class="notification-description">
                    <span class="notif-number">4.</span> Your logbook submission has been declined by your supervisor. Please resubmit your logbook.
                </div>
                <div class="notif-card">
                    <div class="notif-details">
                        <p><strong>Submission title</strong>: Week 3</p>
                        <p><strong>Status</strong>: Declined</p>
                        <p><strong>Supervisor</strong>: DR. AZRINA BINTI KAMARUDDIN</p>
                        <p><strong>Date</strong>: 7 Aug 2025, Mon</p>
                    </div>
                    <div class="notif-action">
                        <button type="button" class="btn btn-outline-dark action-btn logbook-btn" onclick="window.location.href='../logbook/logbook.php'">
                            Logbook
                        </button>
                    </div>
                </div>
            </div>

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

    </script>
</body>

</html>

