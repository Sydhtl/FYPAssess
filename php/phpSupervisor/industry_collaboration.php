<?php
session_start();
include '../db_connect.php';

// 1. CAPTURE ROLE & ID
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'supervisor';
if (isset($_SESSION['upmId'])) {
    $loginID = $_SESSION['upmId'];
} else {
    $loginID = 'hazura';
}

// 2. GET NUMERIC SUPERVISOR ID
$currentUserID = null;
$stmt = $conn->prepare("SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ?");
$stmt->bind_param("s", $loginID);
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) {
    $currentUserID = $row['Supervisor_ID'];
}

// 3. GET CURRENT SESSION (For new submissions)
// We get the current active session to tag any NEW form submission.
$currentSessionID = null;
$sqlSession = "SELECT FYP_Session_ID FROM fyp_session ORDER BY FYP_Session DESC, Semester DESC LIMIT 1";
$resSession = $conn->query($sqlSession);
if ($resSession && $row = $resSession->fetch_assoc()) {
    $currentSessionID = $row['FYP_Session_ID'];
}

// 4. FETCH SUBMISSION HISTORY (For Sidebar)
$submissionHistory = [];
if ($currentUserID) {
    // We select DISTINCT FYP_Session so we display "2024/2025" regardless of Sem 1 or 2
    $sqlHist = "SELECT c.Collaboration_ID, fs.FYP_Session
                FROM collaboration c
                LEFT JOIN fyp_session fs ON c.FYP_Session_ID = fs.FYP_Session_ID
                WHERE c.Supervisor_ID = ?
                ORDER BY fs.FYP_Session DESC"; // Newest years first

    if ($stmtHist = $conn->prepare($sqlHist)) {
        $stmtHist->bind_param("i", $currentUserID);
        $stmtHist->execute();
        $resHist = $stmtHist->get_result();
        while ($row = $resHist->fetch_assoc()) {
            // Label: "2024/2025" (Covers both semesters)
            $label = $row['FYP_Session'] ? $row['FYP_Session'] : "Form #" . $row['Collaboration_ID'];

            $submissionHistory[] = [
                'id' => $row['Collaboration_ID'],
                'label' => $label
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>IndustryCollaboration_Supervisor</title>
    <link rel="stylesheet" href="../../css/supervisor/dashboard.css">
    <link rel="stylesheet" href="../../css/supervisor/industryCollaboration.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css">
    <link rel="stylesheet" href="../../css/dashboard.css">
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

            <span id="nameSide">HI, <?php echo strtoupper($loginID); ?></span>

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
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-bell-fill icon-padding"></i> Notification
                </a>
                <a href="industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Industry Collaboration
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
                <a href="../phpAssessor/dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="../phpAssessor/notification.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Notification
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
            </div>

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
                <div id="containerModule">Supervisor Module</div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode">SWE4949A</div>
                <div id="courseSession">2024/2025 - 2 </div>
            </div>
        </div>

    </div>

    </div>

    <div id="main">
        <div class="industryCollaboration-container">
            <h1 class="page-title">Industry Collaboration</h1>
            <div id="main-content-area">

                <!-- 1. LEFT SECTION: STEP INDICATOR (Smaller, light-yellow panel) -->

                <div id="step-indicator-area">


                    <div class="progress-container">

                        <!-- Step 1: Topic selection -->
                        <div id="step-topic-selection" class="step-indicator active" data-step="topic" data-order="1">
                            <span class="step-dot">
                                <!-- <i class="bi bi-check"></i> -->
                            </span>
                            Topic selection
                        </div>

                        <!-- Step 2: Industry collaboration (Radio) -->
                        <div id="step-industry-collaboration" class="step-indicator" data-step="collaboration"
                            data-order="2">
                            <span class="step-dot">
                                <!-- <i class="bi bi-check"></i> -->
                            </span>
                            Industry collaboration
                        </div>

                        <!-- Step 3: Industry information (Dynamic) -->
                        <div id="step-industry-info" class="step-indicator hidden" data-step="info" data-order="3">
                            <span class="step-dot">
                                <!-- <i class="bi bi-check"></i> -->
                            </span>
                            Industry information
                        </div>

                        <!-- Step 4: Industry supervisor details (Dynamic) -->
                        <div id="step-supervisor-details" class="step-indicator hidden" data-step="supervisor"
                            data-order="4">
                            <span class="step-dot">
                                <!-- <i class="bi bi-check"></i> -->
                            </span>
                            Industry supervisor details
                        </div>

                        <!-- Step 5: Industry requirements (Dynamic) -->
                        <div id="step-requirements" class="step-indicator hidden" data-step="requirements"
                            data-order="5">
                            <span class="step-dot">
                                <!-- <i class="bi bi-check"></i> -->
                            </span>
                            Industry requirements
                        </div>

                        <!-- Step 6: Industry topic selection (Dynamic) -->
                        <div id="step-topic-selection-ext" class="step-indicator hidden" data-step="topic_ext"
                            data-order="6">
                            <span class="step-dot">
                                <!-- <i class="bi bi-check"></i> -->
                            </span>
                            Industry topic selection
                        </div>
                    </div>
                    <div class="history-section">
                        <div class="history-header">Past Sessions</div>

                        <?php if (empty($submissionHistory)): ?>
                            <div class="text-muted small fst-italic">No forms submitted yet.</div>
                        <?php else: ?>
                            <?php foreach ($submissionHistory as $item): ?>
                                <a href="view_collaboration_pdf.php?id=<?php echo $item['id']; ?>" target="_blank"
                                    class="session-link">
                                    <i class="bi bi-file-earmark-pdf-fill pdf-icon"></i>
                                    <div>
                                        <div class="fw-bold"><?php echo $item['label']; ?></div>
                                        <div class="small text-muted" style="font-size: 0.75rem;">Click to view PDF</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. RIGHT SECTION: FORM AREA (Wider, white panel) -->
                <div id="form-area">
                    <!-- Year Selector and Download PDF -->
                    <div class="year-selector-container">
                        <div>
                            <label for="session-year">Year</label>
                            <select id="session-year" class="form-select d-inline-block">
                                <option value="">Loading...</option>
                            </select>
                        </div>
                        <button type="button" id="download-pdf-btn" class="btn btn-download-pdf">
                            <i class="bi bi-download"></i> Download as PDF
                        </button>
                    </div>

                    <form id="industry-form">

                        <!-- ============================================== -->
                        <!-- SECTION 1: TOPIC SELECTION (ALWAYS VISIBLE) -->
                        <!-- ============================================== -->
                        <h2 class="form-section-title" data-step-target="topic">Topic selection</h2>
                        <div class="mb-4">
                            <label class="form-label">List of topics for Bachelor Project</label>
                            <div id="topic-list-container" class="space-y-2">
                                <div class="input-group mb-2">
                                    <span class="input-group-text fixed-width-addon">1.</span>
                                    <input type="text" name="topic[]" class="form-control topic-required" placeholder=""
                                        required>
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text fixed-width-addon">2.</span>
                                    <input type="text" name="topic[]" class="form-control topic-required" placeholder=""
                                        required>
                                </div>
                                <div class="input-group mb-2">
                                    <span class="input-group-text fixed-width-addon">3.</span>
                                    <input type="text" name="topic[]" class="form-control topic-required" placeholder=""
                                        required>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- SECTION 2: INDUSTRY COLLABORATION (RADIO BUTTON) -->
                        <!-- ============================================== -->
                        <h2 class="form-section-title" data-step-target="collaboration">Industry collaboration</h2>
                        <div class="mb-4 d-flex gap-4">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="collaboration_choice" value="yes"
                                    id="collaboration-yes" required>
                                <label class="form-check-label fw-medium" for="collaboration-yes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="collaboration_choice" value="no"
                                    id="collaboration-no" required>
                                <label class="form-check-label fw-medium" for="collaboration-no">No</label>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- EXTENDED FIELDS (CONDITIONAL) -->
                        <!-- ============================================== -->
                        <div id="extended-fields" class="d-none">

                            <!-- SECTION 3: INDUSTRY INFORMATION -->
                            <h2 class="form-section-title" data-step-target="info">Industry information</h2>
                            <div class="mb-3">
                                <label class="form-label">Company name</label>
                                <input type="text" name="company_name" placeholder=""
                                    class="form-control collaboration-required">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company email address</label>
                                <input type="email" name="company_email" placeholder=""
                                    class="form-control collaboration-required">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Company address</label>

                                <div class="mb-3">
                                    <input type="text" name="company_address" placeholder=""
                                        class="form-control collaboration-required" required>
                                </div>

                                <div class="row g-3">

                                    <div class="col-md-4">
                                        <label for="company_postcode" class="form-label">Postcode</label>
                                        <input type="text" name="company_postcode" id="company_postcode"
                                            class="form-control collaboration-required" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="company_city" class="form-label">City</label>
                                        <input type="text" name="company_city" id="company_city"
                                            class="form-control collaboration-required" required>
                                    </div>

                                    <div class="col-md-4">
                                        <label for="company_state" class="form-label">State</label>
                                        <select id="company_state" name="company_state"
                                            class="form-select collaboration-required" required>
                                            <option value="">Select</option>
                                            <option value="JOHOR">Johor</option>
                                            <option value="KEDAH">Kedah</option>
                                            <option value="KELANTAN">Kelantan</option>
                                            <option value="MELAKA">Melaka</option>
                                            <option value="NEGERI SEMBILAN">Negeri Sembilan</option>
                                            <option value="PAHANG">Pahang</option>
                                            <option value="PERAK">Perak</option>
                                            <option value="PERLIS">Perlis</option>
                                            <option value="PULAU PINANG">Pulau Pinang</option>
                                            <option value="SABAH">Sabah</option>
                                            <option value="SARAWAK">Sarawak</option>
                                            <option value="SELANGOR">Selangor</option>
                                            <option value="TERENGGANU">Terengganu</option>
                                            <option value="WP KUALA LUMPUR">WP Kuala Lumpur</option>
                                            <option value="WP LABUAN">WP Labuan</option>
                                            <option value="WP PUTRAJAYA">WP Putrajaya</option>
                                        </select>
                                    </div>



                                </div>
                            </div>

                            <!-- SECTION 4: INDUSTRY SUPERVISOR DETAILS -->
                            <h2 class="form-section-title" data-step-target="supervisor">Industry supervisor details
                            </h2>

                            <div id="supervisor-container" class="mb-3">
                                <div class="card p-0 mb-3 border-0 supervisor-card">

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <label class="fw-normal text-secondary supervisor-number-label">Industry
                                            Supervisor 1 </label>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove d-none">
                                            Remove <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" name="ind_supervisor_name[]" placeholder=""
                                            class="form-control collaboration-required">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Email address</label>
                                        <input type="email" name="ind_supervisor_email[]" placeholder=""
                                            class="form-control collaboration-required">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Phone No</label>
                                        <input type="tel" name="ind_supervisor_phone[]" placeholder=""
                                            class="form-control collaboration-required" pattern="^01[0-9]-[0-9]{7,8}$">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Role/Position</label>
                                        <input type="text" name="ind_supervisor_role[]" placeholder=""
                                            class="form-control collaboration-required">
                                    </div>

                                </div>

                            </div>
                            <div class="mt-0 d-flex justify-content-end">
                                <button type="button" id="add-supervisor-btn"
                                    class="btn btn-outline-primary btn-sm btn-add">
                                    <i class="bi bi-plus-circle me-1"></i> Add industry supervisor
                                </button>
                            </div>

                            <!-- SECTION 5: INDUSTRY REQUIREMENTS -->
                            <h2 class="form-section-title" data-step-target="requirements">Industry requirements</h2>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Student Quota</label>
                                    <select name="num_students" class="form-select collaboration-required">
                                        <option value="">Select</option>
                                        <option value="1">1</option>
                                        <option value="2">2</option>
                                        <option value="3">3</option>
                                        <option value="4">4</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Academic qualification</label>
                                    <select name="academic_qualification" class="form-select collaboration-required">
                                        <option value="">Select</option>
                                        <option value="At least 3.00 CGPA">At least 3.00 CGPA</option>
                                        <option value="At least 3.50 CGPA">At least 3.50 CGPA</option>
                                        <option value="At least 3.75 CGPA">At least 3.75 CGPA</option>
                                        <option value="No requirement">No requirement</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Required skills</label>
                                <div id="skill-list-container" class="space-y-2">

                                    <div class="input-group mb-2 skill-input-group">
                                        <span class="input-group-text fixed-width-addon skill-number">1.</span>
                                        <input type="text" name="required_skill[]"
                                            class="form-control collaboration-required" placeholder="" required>
                                    </div>

                                </div>
                                <div class="mt-0 d-flex justify-content-end">
                                    <button type="button" id="add-skill-btn"
                                        class="btn btn-outline-primary btn-sm btn-add">
                                        <i class="bi bi-plus-circle me-1"></i> Add required skill
                                    </button>
                                </div>
                            </div>

                            <!-- SECTION 6: INDUSTRY TOPIC SELECTION -->
                            <h2 class="form-section-title" data-step-target="topic_ext">Industry topic selection</h2>
                            <div class="mb-4">
                                <label class="form-label">List of topic by industry</label>
                                <div id="ind-topic-list-container" class="space-y-2">

                                    <div class="input-group mb-2 ind-topic-input-group">
                                        <span class="input-group-text fixed-width-addon skill-number">1.</span>
                                        <input type="text" name="ind_topic[]"
                                            class="form-control collaboration-required" placeholder="" required>
                                    </div>

                                </div>
                                <div class="mt-0 d-flex justify-content-end">
                                    <button type="button" id="add-ind-topic-btn"
                                        class="btn btn-outline-primary btn-sm btn-add">
                                        <i class="bi bi-plus-circle me-1"></i> Add industry topic
                                    </button>
                                </div>


                            </div>

                        </div>

                        <!-- ACTION BUTTONS -->
                        <div class="action-buttons pt-4 mt-5 border-top d-flex justify-content-end gap-3">
                            <button type="button" class="btn btn-light border fw-medium">
                                Cancel
                            </button>
                            <button type="submit" id="submit-button" class="btn btn-success btn-submit fw-medium">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
    <div id="acknowledgementModal" class="modal-backdrop-custom d-none">
        <div class="modal-wrapper">
            <div class="modal-content-custom">
                <button type="button" class="btn-close btn-close-black modal-close-btn" aria-label="Close"></button>
                <div class="modal-body-custom text-center">
                    <div class="modal-icon-container">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h3 class="modal-title-custom mt-3">Information saved successfully!</h3>
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

        // =================================================================
        // FORM AND PROGRESS INDICATOR LOGIC
        // =================================================================
        document.addEventListener('DOMContentLoaded', () => {

            const form = document.getElementById('industry-form');
            const extendedFields = document.getElementById('extended-fields');
            const radioYes = document.getElementById('collaboration-yes');
            const radioNo = document.getElementById('collaboration-no');
            const progressLineFill = document.getElementById('progress-line-fill');
            const roleHeaders = document.querySelectorAll('.role-header');
            const sessionYearDropdown = document.getElementById('session-year');
            const downloadPdfBtn = document.getElementById('download-pdf-btn');
            let currentCollaborationID = null; // Track if we're editing

            let stepIndicators = Array.from(document.querySelectorAll('.step-indicator')).map((el, index) => ({
                element: el,
                name: el.getAttribute('data-step'),
                order: index + 1,
            }));

            let collaborationRequiredFields = document.querySelectorAll('.collaboration-required');

            // ============================================
            // LOAD FYP SESSIONS (For Semester 1 only)
            // ============================================
            async function loadSessions() {
                try {
                    const response = await fetch('fetch_sessions.php');
                    const data = await response.json();

                    sessionYearDropdown.innerHTML = '';

                    if (data.status === 'success' && data.sessions.length > 0) {
                        data.sessions.forEach((session, index) => {
                            const option = document.createElement('option');
                            option.value = session.FYP_Session_ID;
                            option.textContent = session.FYP_Session;
                            if (index === 0) option.selected = true; // Select newest
                            sessionYearDropdown.appendChild(option);
                        });

                        // Load data for the first session
                        loadCollaborationData(data.sessions[0].FYP_Session_ID);
                    } else {
                        sessionYearDropdown.innerHTML = '<option value="">No sessions available</option>';
                    }
                } catch (error) {
                    console.error('Error loading sessions:', error);
                    sessionYearDropdown.innerHTML = '<option value="">Error loading sessions</option>';
                }
            }

            // ============================================
            // LOAD COLLABORATION DATA FOR SELECTED SESSION
            // ============================================
            async function loadCollaborationData(sessionID) {
                if (!sessionID) return;

                try {
                    const response = await fetch(`fetch_collaboration.php?session_id=${sessionID}`);
                    const result = await response.json();

                    if (result.status === 'success' && result.data) {
                        const data = result.data;
                        console.log('Loaded data:', data); // Debug log
                        currentCollaborationID = data.Collaboration_ID;

                        // Load collaboration status
                        if (data.Collaboration_Status === 'Yes') {
                            radioYes.checked = true;
                            extendedFields.classList.remove('d-none');
                            toggleRequiredFields(true);

                            // Load company info
                            document.querySelector('[name="company_name"]').value = data.Company_Name || '';
                            document.querySelector('[name="company_email"]').value = data.Company_Email || '';
                            document.querySelector('[name="company_address"]').value = data.Company_Address || '';
                            document.querySelector('[name="company_postcode"]').value = data.Company_Postcode || '';
                            document.querySelector('[name="company_city"]').value = data.Company_City || '';
                            document.querySelector('[name="company_state"]').value = data.Company_State || '';

                            // Load quota and academic qualification
                            document.querySelector('[name="num_students"]').value = data.Student_Quota || '';
                            const academicQual = data.Academic_Qualification || '';
                            if (academicQual) {
                                const selectBox = document.querySelector('[name="academic_qualification"]');
                                if (selectBox) selectBox.value = academicQual;
                            }

                            // Load supervisor topics (from Topic_List array)
                            loadTopics(data.Topic_List || []);

                            // Load industry topics (from Ind_Topic_List array)
                            loadIndTopics(data.Ind_Topic_List || []);

                            // Load required skills (from Skill_List array)
                            loadSkills(data.Skill_List || []);

                            // Load supervisor details (from Supervisor_List array)
                            loadSupervisorsFromList(data.Supervisor_List || []);

                        } else {
                            radioNo.checked = true;
                            extendedFields.classList.add('d-none');
                            toggleRequiredFields(false);
                            currentCollaborationID = data.Collaboration_ID;
                        }

                        updateStepIndicators();
                    } else {
                        // No data for this session - clear form
                        clearForm();
                        currentCollaborationID = null;
                    }
                } catch (error) {
                    console.error('Error loading collaboration data:', error);
                }
            }

            function loadTopics(topics) {
                const container = document.getElementById('topic-list-container');
                container.innerHTML = ''; // Clear existing

                topics.forEach((topic, index) => {
                    const cleanTopic = topic.replace(/^\d+\.\s*/, '').trim(); // Remove numbering
                    const inputGroup = document.createElement('div');
                    inputGroup.className = 'input-group mb-2';
                    inputGroup.innerHTML = `
                        <span class="input-group-text fixed-width-addon">${index + 1}.</span>
                        <input type="text" name="topic[]" class="form-control topic-required" value="${cleanTopic}" required>
                    `;
                    container.appendChild(inputGroup);
                });

                // Ensure minimum 3 fields
                while (container.children.length < 3) {
                    const index = container.children.length;
                    const inputGroup = document.createElement('div');
                    inputGroup.className = 'input-group mb-2';
                    inputGroup.innerHTML = `
                        <span class="input-group-text fixed-width-addon">${index + 1}.</span>
                        <input type="text" name="topic[]" class="form-control topic-required" required>
                    `;
                    container.appendChild(inputGroup);
                }
            }

            function loadSkills(skills) {
                const container = document.getElementById('skill-list-container');
                container.innerHTML = '';

                if (skills.length === 0) skills = [''];

                skills.forEach((skill, index) => {
                    const inputGroup = document.createElement('div');
                    inputGroup.className = 'input-group mb-2 skill-input-group';
                    inputGroup.innerHTML = `
                        <span class="input-group-text fixed-width-addon">${index + 1}.</span>
                        <input type="text" name="required_skill[]" class="form-control collaboration-required" value="${skill}">
                        ${index > 0 ? '<button type="button" class="btn btn-sm btn-outline-danger input-group-text remove-skill-btn"><i class="bi bi-x"></i></button>' : ''}
                    `;
                    container.appendChild(inputGroup);

                    // Attach remove listener
                    if (index > 0) {
                        inputGroup.querySelector('.remove-skill-btn').addEventListener('click', function () {
                            inputGroup.remove();
                            updateSkillNumbers();
                        });
                    }
                });
            }

            function loadIndTopics(indTopics) {
                const container = document.getElementById('ind-topic-list-container');
                container.innerHTML = '';

                if (indTopics.length === 0) indTopics = [''];

                indTopics.forEach((topic, index) => {
                    const inputGroup = document.createElement('div');
                    inputGroup.className = 'input-group mb-2 ind-topic-input-group';
                    inputGroup.innerHTML = `
                        <span class="input-group-text fixed-width-addon">${index + 1}.</span>
                        <input type="text" name="ind_topic[]" class="form-control collaboration-required" value="${topic}">
                        ${index > 0 ? '<button type="button" class="btn btn-sm btn-outline-danger input-group-text remove-ind-topic-btn"><i class="bi bi-x"></i></button>' : ''}
                    `;
                    container.appendChild(inputGroup);

                    // Attach remove listener
                    if (index > 0) {
                        inputGroup.querySelector('.remove-ind-topic-btn').addEventListener('click', function () {
                            inputGroup.remove();
                            updateIndTopicNumbers();
                        });
                    }
                });
            }

            function loadSupervisorsFromList(supervisorList) {
                const container = document.getElementById('supervisor-container');
                container.innerHTML = '';

                // If no supervisors, create at least one empty card
                if (!supervisorList || supervisorList.length === 0) {
                    supervisorList = [{ name: '', email: '', phone: '', role: '' }];
                }

                supervisorList.forEach((supervisor, i) => {
                    const card = document.createElement('div');
                    card.className = 'card p-0 mb-3 border-0 supervisor-card';
                    card.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <label class="fw-normal text-secondary supervisor-number-label">Industry Supervisor ${i + 1}</label>
                            ${i > 0 ? '<button type="button" class="btn btn-outline-danger btn-sm btn-remove"><i class="bi bi-x"></i> Remove</button>' : '<button type="button" class="btn btn-outline-danger btn-sm btn-remove d-none"><i class="bi bi-x"></i> Remove</button>'}
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="ind_supervisor_name[]" class="form-control collaboration-required" value="${supervisor.name || ''}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email address</label>
                            <input type="email" name="ind_supervisor_email[]" class="form-control collaboration-required" value="${supervisor.email || ''}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone No</label>
                            <input type="tel" name="ind_supervisor_phone[]" class="form-control collaboration-required" value="${supervisor.phone || ''}" pattern="^01[0-9]-[0-9]{7,8}$">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role/Position</label>
                            <input type="text" name="ind_supervisor_role[]" class="form-control collaboration-required" value="${supervisor.role || ''}">
                        </div>
                    `;
                    container.appendChild(card);

                    // Attach remove listener
                    if (i > 0) {
                        card.querySelector('.btn-remove').addEventListener('click', function () {
                            card.remove();
                            updateSupervisorLabels();
                        });
                    }
                });
            }

            function clearForm() {
                form.reset();
                radioNo.checked = false;
                radioYes.checked = false;
                extendedFields.classList.add('d-none');
                toggleRequiredFields(false);
                currentCollaborationID = null;

                // Reset topics to 3 empty fields
                const topicContainer = document.getElementById('topic-list-container');
                topicContainer.innerHTML = '';
                for (let i = 0; i < 3; i++) {
                    const inputGroup = document.createElement('div');
                    inputGroup.className = 'input-group mb-2';
                    inputGroup.innerHTML = `
                        <span class="input-group-text fixed-width-addon">${i + 1}.</span>
                        <input type="text" name="topic[]" class="form-control topic-required" required>
                    `;
                    topicContainer.appendChild(inputGroup);
                }

                updateStepIndicators();
            }

            // Year dropdown change handler
            sessionYearDropdown.addEventListener('change', (e) => {
                loadCollaborationData(e.target.value);
            });

            // Download PDF handler
            downloadPdfBtn.addEventListener('click', () => {
                const sessionID = sessionYearDropdown.value;
                if (!sessionID || !currentCollaborationID) {
                    alert('No submission found for this session.');
                    return;
                }
                window.open(`view_collaboration_pdf.php?id=${currentCollaborationID}`, '_blank');
            });

            // Initialize: Load sessions on page load
            loadSessions();


            // --- Helper Functions ---

            /** Toggles the 'required' attribute on conditional fields. */
            function toggleRequiredFields(required) {
                // Re-select as new fields might have been added dynamically
                collaborationRequiredFields = document.querySelectorAll('.collaboration-required');
                collaborationRequiredFields.forEach(field => {
                    if (required) {
                        field.setAttribute('required', 'required');
                    } else {
                        field.removeAttribute('required');
                    }
                });
            }

            /** Checks if a step is completed based on its form fields. */
            function checkStepCompletion(step) {
                const formElements = form.elements;
                const isCollaboration = radioYes.checked;

                switch (step.name) {
                    case 'topic':
                        const topicInputs = document.querySelectorAll('#topic-list-container input[type="text"]');
                        // Min 3 required, and all visible fields must be filled
                        return topicInputs.length >= 3 && Array.from(topicInputs).every(input => input.value.trim() !== '');
                    case 'collaboration':
                        return radioYes.checked || radioNo.checked;
                    case 'info':
                        if (!isCollaboration) return false;
                        const companyFields = ['company_name', 'company_email', 'company_address'];
                        return companyFields.every(name => {
                            const input = formElements.namedItem(name);
                            return input && input.value.trim() !== '';
                        });
                    case 'supervisor':
                        if (!isCollaboration) return false;
                        const supInputs = document.querySelectorAll('#supervisor-container .card:first-child input.collaboration-required');
                        // Ensure the primary supervisor fields are all filled
                        return Array.from(supInputs).every(input => input.value.trim() !== '');
                    case 'requirements':
                        if (!isCollaboration) return false;
                        const reqFields = ['num_students', 'academic_qualification'];
                        const skillInputs = document.querySelectorAll('#skill-list-container input[type="text"]');
                        // Check selects and at least the first skill input
                        return reqFields.every(name => formElements.namedItem(name) && formElements.namedItem(name).value !== '') &&
                            (skillInputs.length === 0 || skillInputs[0].value.trim() !== '');
                    case 'topic_ext':
                        if (!isCollaboration) return false;
                        const indTopicInputs = document.querySelectorAll('#ind-topic-list-container input[type="text"]');
                        // At least one industry topic must be provided
                        return indTopicInputs.length > 0 && indTopicInputs[0].value.trim() !== '';
                    default:
                        return false;
                }
            }

            /** Updates the step indicators and the vertical progress line. */
            function updateStepIndicators() {
                const isCollaboration = radioYes.checked;
                let foundActive = false;
                let lastCompletedStepElement = null;

                // 1. Handle visibility and completion status
                stepIndicators.forEach(step => {
                    const isDynamic = step.order > 2;
                    const shouldBeVisible = !isDynamic || isCollaboration;

                    step.element.classList.toggle('hidden', !shouldBeVisible);

                    if (shouldBeVisible) {
                        const isCompleted = checkStepCompletion(step);

                        step.element.classList.remove('active', 'completed');

                        if (isCompleted) {
                            step.element.classList.add('completed');
                            lastCompletedStepElement = step.element;
                        } else if (!foundActive) {
                            // The first visible, incomplete step becomes active
                            step.element.classList.add('active');
                            foundActive = true;
                        }
                    }
                });


            }

            /** Handles the creation of dynamic input fields. */
            function addDynamicField(containerId, templateHtml) {
                const container = document.getElementById(containerId);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = templateHtml.trim();
                const newElement = tempDiv.firstElementChild;
                container.appendChild(newElement);

                // Attach input listener to the new fields and update required status
                newElement.querySelectorAll('input, select, textarea').forEach(input => {
                    input.addEventListener('input', updateStepIndicators);
                    if (radioYes.checked && input.classList.contains('collaboration-required')) {
                        input.setAttribute('required', 'required');
                    }
                });

                // Re-calculate the list of required fields
                collaborationRequiredFields = document.querySelectorAll('.collaboration-required');
                updateStepIndicators();
            }

            // --- Event Listeners ---

            // 1. Conditional Display Logic (Yes/No Radio)
            [radioYes, radioNo].forEach(radio => {
                radio.addEventListener('change', () => {
                    const isYes = radioYes.checked;

                    if (isYes) {
                        extendedFields.classList.remove('d-none');
                        toggleRequiredFields(true);
                    } else {
                        extendedFields.classList.add('d-none');
                        toggleRequiredFields(false);
                    }
                    updateStepIndicators();
                });
            });

            // 2. Step Indicator Update Logic (on input change)
            form.addEventListener('input', updateStepIndicators);

            // Function to update the numbering of all existing supervisor cards
            const updateSupervisorLabels = () => {
                const cards = document.querySelectorAll('#supervisor-container .supervisor-card');
                cards.forEach((card, index) => {
                    const numberLabel = card.querySelector('.supervisor-number-label');
                    if (numberLabel) {
                        // Update the label text (e.g., "Industry Supervisor 2")
                        numberLabel.textContent = `Industry Supervisor ${index + 1}`;
                    }
                    const removeBtn = card.querySelector('.btn-remove');
                    if (removeBtn) {
                        // Only hide the remove button if it is the first (required) supervisor
                        removeBtn.classList.toggle('d-none', index === 0);
                    }
                });
                updateStepIndicators(); // Important: Recalculate completion after adding/removing
            };

            // Function to attach the remove listener to a specific card
            const attachRemoveListener = (cardElement) => {
                const removeBtn = cardElement.querySelector('.btn-remove');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        cardElement.remove(); // Remove the entire card/supervisor element
                        updateSupervisorLabels(); // Re-number the remaining cards
                    });
                }
            }

            document.getElementById('add-supervisor-btn').addEventListener('click', () => {
                const container = document.getElementById('supervisor-container');

                // 1. Clone the first existing supervisor card
                const existingCard = container.querySelector('.supervisor-card');
                const newCard = existingCard.cloneNode(true);

                // 2. Clear the input values in the cloned card
                newCard.querySelectorAll('input').forEach(input => input.value = '');

                // 3. Ensure the remove button is visible and has a listener
                const removeBtn = newCard.querySelector('.btn-remove');
                if (removeBtn) {
                    removeBtn.classList.remove('d-none'); // Show the button if it was hidden
                    removeBtn.addEventListener('click', function () {
                        newCard.remove();
                        updateSupervisorLabels();
                    });
                }

                // 4. Append the new card and update all labels
                container.appendChild(newCard);
                updateSupervisorLabels();
            });

            /**
 * [skill] Re-numbers the skill fields inside the container and updates the placeholders.
 */
            const updateSkillNumbers = () => {

                const fields = document.querySelectorAll('#skill-list-container .skill-input-group');
                fields.forEach((field, index) => {
                    const number = index + 1;

                    // Update the number in the Bootstrap addon (the '1.' span)
                    const numberSpan = field.querySelector('.fixed-width-addon');
                    if (numberSpan) {
                        numberSpan.textContent = `${number}.`;
                    }

                });
            };

            document.getElementById('add-skill-btn').addEventListener('click', () => {
                const container = document.getElementById('skill-list-container');

                // 1. Get the last field element (the template to clone)
                const existingField = container.querySelector('.skill-input-group:last-child');

                // 2. Clone the existing field (deep clone: true)
                const newField = existingField.cloneNode(true);

                // 3. Clear the input value in the cloned field
                const input = newField.querySelector('input[name="required_skill[]"]');
                input.value = '';

                // 4. Create and append the trash icon button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-outline-danger input-group-text remove-skill-btn';
                removeBtn.innerHTML = '<i class="bi bi-x"></i>';

                // Remove any existing trash button before appending the new one 
                newField.querySelector('.remove-skill-btn')?.remove();

                // Append the trash button next to the input
                newField.appendChild(removeBtn);

                // 5. Attach remove event listener
                removeBtn.addEventListener('click', function () {
                    newField.remove(); // Remove the entire input group
                    updateSkillNumbers(); // Re-number remaining fields and update placeholders
                });

                // 6. Append the new field and update all numbers and placeholders
                container.appendChild(newField);
                updateSkillNumbers();
            });

            //[Ind topic] Re-numbers the skill fields inside the container and updates the placeholders.

            const updateIndTopicNumbers = () => {

                const fields = document.querySelectorAll('#ind-topic-list-container .ind-topic-input-group');
                fields.forEach((field, index) => {
                    const number = index + 1;

                    // Update the number in the Bootstrap addon (the '1.' span)
                    const numberSpan = field.querySelector('.fixed-width-addon');
                    if (numberSpan) {
                        numberSpan.textContent = `${number}.`;
                    }

                });
            };

            document.getElementById('add-ind-topic-btn').addEventListener('click', () => {
                const container = document.getElementById('ind-topic-list-container');

                // 1. Get the last field element (the template to clone)
                const existingField = container.querySelector('.ind-topic-input-group:last-child');

                // 2. Clone the existing field (deep clone: true)
                const newField = existingField.cloneNode(true);

                // 3. Clear the input value in the cloned field
                const input = newField.querySelector('input[name="ind_topic[]"]');
                input.value = '';

                // 4. Create and append the trash icon button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn btn-sm btn-outline-danger input-group-text remove-ind-topic-btn';
                removeBtn.innerHTML = '<i class="bi bi-x"></i>';

                // Remove any existing trash button before appending the new one 
                newField.querySelector('.remove-ind-topic-btn')?.remove();

                // Append the trash button next to the input
                newField.appendChild(removeBtn);

                // 5. Attach remove event listener
                removeBtn.addEventListener('click', function () {
                    newField.remove(); // Remove the entire input group
                    updateIndTopicNumbers(); // Re-number remaining fields and update placeholders
                });

                // 6. Append the new field and update all numbers and placeholders
                container.appendChild(newField);
                updateIndTopicNumbers();
            });

            // 4. Role Toggle Logic
            roleHeaders.forEach(header => {
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    const menuId = header.getAttribute('href');
                    const targetMenu = document.querySelector(menuId);
                    const arrowIcon = header.querySelector('.arrow-icon');

                    if (!targetMenu) return;

                    const isExpanded = targetMenu.classList.contains('expanded');
                    const isSidebarExpanded = document.getElementById("mySidebar").style.width === EXPANDED_WIDTH;

                    // Collapse all other menus
                    document.querySelectorAll('.menu-items').forEach(menu => {
                        if (menu !== targetMenu) {
                            menu.classList.remove('expanded');
                            menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                        }
                    });
                    document.querySelectorAll('.role-header').forEach(h => {
                        if (h !== header) h.classList.remove('active-role');
                        h.querySelector('.arrow-icon').classList.replace('bi-chevron-down', 'bi-chevron-right');
                    });


                    // Toggle current menu
                    targetMenu.classList.toggle('expanded', !isExpanded);
                    header.classList.toggle('active-role', !isExpanded);

                    if (isExpanded) {
                        // Collapse it
                        arrowIcon.classList.replace('bi-chevron-down', 'bi-chevron-right');
                        targetMenu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                    } else {
                        // Expand it
                        arrowIcon.classList.replace('bi-chevron-right', 'bi-chevron-down');
                        if (isSidebarExpanded) {
                            targetMenu.querySelectorAll('a').forEach(a => a.style.display = 'block');
                        }
                    }
                });
            });

            // 5. Submission Handler
            form.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (form.checkValidity()) {
                    const statusVal = radioYes.checked ? 'Yes' : 'No';

                    // Helper for Arrays
                    const getArrayVal = (name) => {
                        return Array.from(document.querySelectorAll(`input[name="${name}[]"]`))
                            .map(input => input.value)
                            .filter(val => val.trim() !== '');
                    };

                    const payload = {
                        collaboration_id: currentCollaborationID, // Include ID for update
                        collaboration_status: statusVal,
                        session_id: sessionYearDropdown.value,

                        // Array Data
                        topic: getArrayVal('topic'),
                        required_skill: getArrayVal('required_skill'),
                        ind_topic: getArrayVal('ind_topic'),

                        // Company Fields
                        company_name: document.querySelector('input[name="company_name"]')?.value || '',
                        company_address: document.querySelector('input[name="company_address"]')?.value || '',
                        company_postcode: document.querySelector('input[name="company_postcode"]')?.value || '',
                        company_city: document.querySelector('input[name="company_city"]')?.value || '',
                        company_state: document.querySelector('select[name="company_state"]')?.value || '',
                        company_email: document.querySelector('input[name="company_email"]')?.value || '',

                        // Dropdowns
                        student_quota: document.querySelector('select[name="num_students"]')?.value || '',
                        academic_qualification: document.querySelector('select[name="academic_qualification"]')?.value || '',

                        // Supervisor Arrays (using correct field names from HTML)
                        supervisor_name: getArrayVal('ind_supervisor_name'),
                        supervisor_email: getArrayVal('ind_supervisor_email'),
                        supervisor_phone: getArrayVal('ind_supervisor_phone'),
                        supervisor_role: getArrayVal('ind_supervisor_role')
                    };

                    const submitBtn = document.querySelector('.btn-submit');
                    const originalText = submitBtn.innerText;
                    submitBtn.innerText = "Saving...";
                    submitBtn.disabled = true;

                    try {
                        const response = await fetch('submit_collaboration.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });

                        const data = await response.json();

                        if (data.status === 'success') {
                            acknowledgementModal.classList.remove('d-none');
                            // Reload data to get the new ID if it was an insert
                            setTimeout(() => {
                                loadCollaborationData(sessionYearDropdown.value);
                            }, 500);
                        } else {
                            alert("Error saving data: " + (data.message || 'Unknown error'));
                        }
                    } catch (err) {
                        console.error("Network Error:", err);
                        alert("Connection failed. Please check console.");
                    } finally {
                        submitBtn.innerText = originalText;
                        submitBtn.disabled = false;
                    }

                } else {
                    console.error("Form validation failed.");
                    form.reportValidity();
                }
            });

            // Get references to modal elements
            const acknowledgementModal = document.getElementById('acknowledgementModal');
            const closeModalBtns = document.querySelectorAll('.modal-close-btn');

            // Add event listeners to close the modal
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    acknowledgementModal.classList.add('d-none');
                });
            });

            // --- Initial Setup ---
            acknowledgementModal.classList.add('d-none');

            // Set initial state based on radio (which defaults to 'No' in HTML)
            extendedFields.classList.add('d-none');
            toggleRequiredFields(false);

            // Add initial listeners for topic fields
            document.querySelectorAll('.topic-required').forEach(input => input.addEventListener('input', updateStepIndicators));

            // Wait for the DOM and styles to settle before calculating positions
            window.onload = function () {
                closeNav(); // Initialize in collapsed state as requested
                updateStepIndicators();
            };
        });
    </script>
</body>

</html>