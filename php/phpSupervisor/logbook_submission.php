<?php


include '../db_connect.php';

// --- 1b. GET SUPERVISOR ID ---
$supervisorID = null;

// Option A: If your session already holds the integer Supervisor_ID
// $supervisorID = $_SESSION['supervisor_id'] ?? 0;

// Option B: If loginID is a username/staffID (e.g., 'hazura') and you need to look up the integer ID:
$sqlSup = "SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ? LIMIT 1"; 
$stmtSup = $conn->prepare($sqlSup);
$stmtSup->bind_param("s", $loginID);
$stmtSup->execute();
$resSup = $stmtSup->get_result();

if ($rowSup = $resSup->fetch_assoc()) {
    $supervisorID = $rowSup['Supervisor_ID'];
} else {
    // Fallback for testing or if not found
    $supervisorID = 0; 
}
$stmtSup->close();

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
if (isset($_SESSION['upmId'])) {
    $loginID = $_SESSION['upmId'];
} else {
    $loginID = 'hazura'; // Fallback
}

// --- 2. DATA FETCHING ---
$groupedData = [
    'SWE4949A' => [], 
    'SWE4949B' => []
];

// A. GET SUPERVISOR ID
// (Assumes $loginID is the Lecturer_ID, e.g., 'hazura')
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
if ($supervisorID > 0) {
    // We fetch based on Enrollment so we get the correct Course (A or B) for each entry.
    // We JOIN on Course_ID to ensure logbooks for Course A don't show up under Course B.
    $sql = "SELECT 
                se.Student_ID,
                s.Student_Name,
                fs.Course_ID, 
                l.Logbook_ID,
                l.Logbook_Name,
                l.Logbook_Date,
                l.Logbook_Status
            FROM student_enrollment se
            JOIN student s ON se.Student_ID = s.Student_ID
            JOIN fyp_session fs ON se.FYP_Session_ID = fs.FYP_Session_ID
            LEFT JOIN logbook l ON (se.Student_ID = l.Student_ID AND fs.Course_ID = l.Course_ID)
            WHERE se.Supervisor_ID = ?
            ORDER BY se.Student_ID, l.Logbook_Date DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supervisorID);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // 1. Determine which list this row belongs to (A or B)
        // Course_ID 1 = SWE4949A, Course_ID 2 = SWE4949B
        $courseKey = ($row['Course_ID'] == 2) ? 'SWE4949B' : 'SWE4949A';
        $sID = $row['Student_ID'];

        // 2. Create the Student Entry if it doesn't exist yet for this specific Course
        if (!isset($groupedData[$courseKey][$sID])) {
            $groupedData[$courseKey][$sID] = [
                'name'      => $row['Student_Name'],
                'id'        => $sID,
                'logbooks'  => [],
                'stats'     => ['submitted' => 0, 'approved' => 0]
            ];
        }

        // 3. Add Logbook Data (if the student has submitted any)
        if (!empty($row['Logbook_ID'])) {
            $groupedData[$courseKey][$sID]['logbooks'][] = [
                'id'     => $row['Logbook_ID'],
                'name'   => $row['Logbook_Name'],
                'date'   => $row['Logbook_Date'],
                'status' => $row['Logbook_Status'] 
            ];

            // 4. Update Statistics
            $groupedData[$courseKey][$sID]['stats']['submitted']++;
            if ($row['Logbook_Status'] === 'Approved') {
                $groupedData[$courseKey][$sID]['stats']['approved']++;
            }
        }
    }
    $stmt->close();
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
        <div class="logbook-container">
            <h1 class="page-title">Logbook Submission</h1>

            <div class="accordion" id="logbookAccordion">
                
                <?php foreach ($groupedData as $courseCode => $students): ?>
                    <?php 
                        $collapseID = "collapse" . $courseCode;
                        // Open 'A' by default
                        $isShow = ($courseCode === 'SWE4949A') ? 'show' : '';
                    ?>
                    
                    <div class="course-section">
                        <div class="course-header" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseID; ?>">
                            <i class="bi <?php echo $isShow ? 'bi-chevron-down' : 'bi-chevron-right'; ?> toggle-icon me-2"></i>
                            <span><?php echo $courseCode; ?></span>
                        </div>

                        <div id="<?php echo $collapseID; ?>" class="collapse <?php echo $isShow; ?>" data-bs-parent="#logbookAccordion">
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
                                            
                                            <div class="student-group-wrapper">
                                                
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
                                                                <a href="view_logbook_pdf.php?id=<?php echo $lb['id']; ?>" target="_blank" class="pdf-link">
                                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                                    <?php echo htmlspecialchars($lb['name']); ?>
                                                                </a>
                                                                <span class="date-small"><?php echo $lb['date']; ?></span>
                                                            </div>

                                                            <div class="grid-cell status-col">
                                                                <select class="form-select form-select-sm status-select" 
                                                                        onchange="updateStatus(<?php echo $lb['id']; ?>, this)"
                                                                        data-status="<?php echo $lb['status']; ?>">
                                                                    <option value="Waiting for Approval" <?php echo ($lb['status'] == 'Waiting for Approval') ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="Approved" <?php echo ($lb['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="Declined" <?php echo ($lb['status'] == 'Declined') ? 'selected' : ''; ?>>Declined</option>
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
                                                                <a href="view_logbook_pdf.php?id=<?php echo $lb['id']; ?>" target="_blank" class="pdf-link">
                                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                                    <?php echo htmlspecialchars($lb['name']); ?>
                                                                </a>
                                                                <span class="date-small"><?php echo $lb['date']; ?></span>
                                                            </div>

                                                            <div class="grid-cell status-col">
                                                                <select class="form-select form-select-sm status-select" 
                                                                        onchange="updateStatus(<?php echo $lb['id']; ?>, this)"
                                                                        data-status="<?php echo $lb['status']; ?>">
                                                                    <option value="Waiting for Approval" <?php echo ($lb['status'] == 'Waiting for Approval') ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="Approved" <?php echo ($lb['status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                                                                    <option value="Declined" <?php echo ($lb['status'] == 'Declined') ? 'selected' : ''; ?>>Declined</option>
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
                if(!data.success) alert('Failed: ' + data.message);
            })
            .catch(e => {
                el.disabled = false;
                alert('Connection error');
            });
        }
    </script>
</body>
</html>