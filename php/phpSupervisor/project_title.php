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

// Fetch distinct FYP Sessions for the Sidebar Filter (Semester 2 only)
$session_sql = "SELECT DISTINCT FYP_Session FROM fyp_session WHERE Semester = '2' ORDER BY FYP_Session DESC";
$session_result = $conn->query($session_sql);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Project Title</title>
    <!-- <link rel="stylesheet" href="../../css/supervisor/dashboard.css"> -->
    <link rel="stylesheet" href="../../css/<?php echo $activeRole; ?>/projectTitle.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css?v=<?php echo time(); ?>">
    <!-- <link rel="stylesheet" href="../../css/dashboard.css"> -->
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
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
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

            <a href="../login.php" id="logout"><i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i>
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
        <div class="projectTitle-container">
            <h1 class="page-title">Project Title Search</h1>
            <div class="search-layout-grid">

                <div class="filter-column">
                    <div class="filter-box">
                        <h3><i class="fas fa-filter"></i> Filter</h3>
                        <ul class="filter-options">
                            <?php
                            if ($session_result && $session_result->num_rows > 0) {
                                while ($row = $session_result->fetch_assoc()) {
                                    $sess = $row['FYP_Session'];
                                    $id = "year" . preg_replace('/[^a-zA-Z0-9]/', '', $sess);
                                    ?>
                                    <li>
                                        <div class="form-check">
                                            <input class="form-check-input session-filter" type="checkbox"
                                                value="<?php echo htmlspecialchars($sess); ?>" id="<?php echo $id; ?>">
                                            <label class="form-check-label" for="<?php echo $id; ?>">
                                                <?php echo htmlspecialchars($sess); ?>
                                            </label>
                                        </div>
                                    </li>
                                    <?php
                                }
                            } else {
                                echo "<li>No sessions found</li>";
                            }
                            ?>
                        </ul>
                    </div>
                </div>

                <div class="results-column">
                    <div class="search-bar-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control"
                            placeholder="Search by Title or Student or Supervisor..">
                    </div>

                    <div class="search-summary">
                        Search results [<span id="resultCount">0</span> Project Title found]
                    </div>

                    <div class="results-list" id="resultsList">
                        <div class="text-center mt-4">Loading projects...</div>
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


        // ==========================================
        // MAIN EXECUTION
        // ==========================================
        document.addEventListener('DOMContentLoaded', () => {

            // A. Sidebar Init
            closeNav();
            updateRoleHeaderHighlighting();


            // B. Search Logic
            const searchInput = document.getElementById('searchInput');
            const checkboxes = document.querySelectorAll('.session-filter');
            const resultsList = document.getElementById('resultsList');
            const resultCount = document.getElementById('resultCount');

            function fetchProjects() {
                const query = searchInput.value.trim(); // Get search text
                const selectedSessions = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);

                // --- LOGIC UPDATE START ---
                // If BOTH search is empty AND no sessions are ticked, stop and clear results.
                if (query === '' && selectedSessions.length === 0) {
                    resultsList.innerHTML = '<p class="text-muted text-center">Please enter a keyword or select a session to view projects.</p>';
                    resultCount.textContent = '0';
                    return;
                }
                // --- LOGIC UPDATE END ---

                const payload = {
                    search: query,
                    sessions: selectedSessions
                };

                // Show loading state
                resultsList.innerHTML = '<div class="text-center mt-4">Loading projects...</div>';

                fetch('fetch_project_title.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                    .then(response => response.json())
                    .then(data => {
                        renderProjects(data);
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        resultsList.innerHTML = '<p class="text-danger">Error loading data.</p>';
                    });
            }

            function renderProjects(projects) {
                resultsList.innerHTML = '';
                resultCount.textContent = projects.length;

                if (projects.length === 0) {
                    resultsList.innerHTML = '<p class="text-muted text-center">No projects found matching your criteria.</p>';
                    return;
                }

                projects.forEach(project => {
                    const card = document.createElement('div');
                    card.className = 'result-card';
                    card.innerHTML = `
                        <h4>${project.title}</h4>
                        <p><strong>Student :</strong> ${project.student}</p>
                        <p><strong>Supervisor :</strong> ${project.supervisor}</p>
                        <p><strong>Year :</strong> ${project.year}</p>
                    `;
                    resultsList.appendChild(card);
                });
            }

            // Attach Event Listeners
            searchInput.addEventListener('keyup', fetchProjects);
            checkboxes.forEach(cb => cb.addEventListener('change', fetchProjects));

            // Initial Load
            fetchProjects();
        });
    </script>
</body>

</html>