<?php
session_start();
include '../db_connect.php';

// 1. CAPTURE ROLE
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'supervisor';

// 2. PREPARE MODULE TITLE
$moduleTitle = ucfirst($activeRole) . " Module";

// 3. FETCH COURSE INFO FROM DATABASE
// HARDCODED: Using FYP_Session_ID 1 and 2 for 2024/2025 sessions
$courseCode = "SWE4949";
$courseSession = "2024/2025 - 1";
$latestSessionID = 1; // Hardcoded to session 1

// =================================================================================
// 4. FETCH DYNAMIC STUDENT DATA (PHP Logic)
// =================================================================================
$studentDataPHP = [];

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

// C. Fetch Students (latest session only)
if ($currentUserID) {
    $sql = "SELECT s.Student_ID, s.Student_Name, s.Course_ID, fp.Project_Title, l.Lecturer_Name as Supervisor_Name
            FROM student_enrollment se
            JOIN student s ON se.Student_ID = s.Student_ID
            LEFT JOIN fyp_project fp ON s.Student_ID = fp.Student_ID
            JOIN supervisor sup ON se.Supervisor_ID = sup.Supervisor_ID
            JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
            WHERE s.FYP_Session_ID IN (1, 2) AND ";

    if ($activeRole === 'supervisor') {
        $sql .= "se.Supervisor_ID = ?";
        $stmtStudents = $conn->prepare($sql);
        $stmtStudents->bind_param("i", $currentUserID);
    } else {
        $sql .= "(se.Assessor_ID_1 = ? OR se.Assessor_ID_2 = ?)";
        $stmtStudents = $conn->prepare($sql);
        $stmtStudents->bind_param("ii", $currentUserID, $currentUserID);
    }

    $stmtStudents->execute();
    $result = $stmtStudents->get_result();

    while ($row = $result->fetch_assoc()) {
        $studentDataPHP[$row['Student_ID']] = [
            'matric' => $row['Student_ID'],
            'name' => $row['Student_Name'],
            //'programme' => "Course " . $row['Course_ID'],
            'supervisor' => $row['Supervisor_Name'],
            'projectTitle' => $row['Project_Title'] ?? 'No Title Yet'
        ];
    }
    $stmtStudents->close();
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../css/<?php echo $activeRole; ?>/evaluationForm.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../../css/background.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
</head>

<body>

    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()" style="display:none;"><i class="bi bi-list"></i></button>

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
                <a href="../phpSupervisor/dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="../phpSupervisor/notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-bell-fill icon-padding"></i> Notification
                </a>
                <a href="../phpSupervisor/industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Industry Collaboration
                </a>

                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=supervisor" id="evaluationForm"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>

                <a href="../phpSupervisor/report.php?role=supervisor" id="superviseesReport"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>
                <a href="../phpSupervisor/logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>
                <a href="../phpSupervisor/signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="../phpSupervisor/project_title.php?role=supervisor" id="projectTitle"
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
                    class="<?php echo ($activeRole == 'assessor') ? 'active-menu-item active-page' : ''; ?>">
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
        <div class="evaluation-container">
            <h1 class="page-title">Evaluation Form</h1>

            <div class="evaluation-card">
                <div class="form-field">
                    <p class="form-label"><strong>Select student</strong></p>
                    <div class="evaluation-action">
                        <select class="form-select action-dropdown" id="studentSelect">
                            <option value="" disabled selected>Select a student...</option>
                        </select>
                    </div>
                </div>

                <div class="form-field">
                    <p class="form-label"><strong>Select assessment</strong></p>
                    <div class="evaluation-action">
                        <select class="form-select action-dropdown" id="assessmentSelect">
                            <option value="" disabled selected>Loading assessments...</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="dynamicFormContent" class="hidden-content">
                <div class="student-details" id="studentDetails"></div>
                <div class="report-table-wrapper">
                    <div class="report-grid-container" id="evaluationTableBody"></div>
                </div>
                <div class="feedback-section-wrapper">
                    <div class="feedback-header">Comment/feedback</div>
                    <div class="feedback-body">
                        <textarea class="form-control" rows="3" placeholder="Enter your feedback here..."></textarea>
                    </div>
                </div>
                <div class="submit-container">
                    <button type="submit" class="btn btn-success">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Acknowledgement Modal -->
    <div id="acknowledgementModal" class="custom-modal">
        <div class="modal-dialog">
            <div class="modal-content-custom">
                <span class="close-btn" id="closeAcknowledgementModal">&times;</span>
                <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="modal-title-custom">Evaluation is saved!</div>
                <div class="modal-message">The evaluation marks have been saved successfully.</div>
                <div style="display:flex; justify-content:center;">
                    <button id="okAcknowledgementBtn" class="btn btn-success" type="button">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const currentActiveRole = "<?php echo $activeRole; ?>";
        const studentData = <?php echo json_encode($studentDataPHP); ?>;

        let rubrics = {};
        let assessmentMeta = {};
        let selectedStudentId = null;
        let selectedAssessmentKey = null;
        let logbookCounts = { SWE4949A: 0, SWE4949B: 0 }; // Store logbook approval counts

        // ==========================================
        // SIDEBAR LOGIC START
        // ==========================================

        // 1. Toggle Menu (Accordion)
        function toggleMenu(menuId, headerElement) {
            const menu = document.getElementById(menuId);
            if (!menu) return;

            const isCurrentlyExpanded = menu.classList.contains('expanded');

            // Collapse all other menus first
            document.querySelectorAll('.menu-items').forEach(m => {
                if (m !== menu) {
                    m.classList.remove('expanded');
                    const header = document.querySelector(`.role-header[onclick*="${m.id}"]`);
                    if (header) header.classList.remove('menu-expanded');
                }
            });

            // Toggle current menu
            menu.classList.toggle('expanded');
            headerElement.classList.toggle('menu-expanded');

            // Update highlighting
            updateRoleHeaderHighlighting();
        }

        // Function to update role header highlighting based on expansion state
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

        // 2. Open Sidebar (Full View)
        function openNav() {
            // Set widths
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
            document.getElementById("containerAtas").style.marginLeft = "250px";

            // Show Text Elements
            document.getElementById("nameSide").style.display = "block";
            document.getElementById("close").style.display = "block";

            // Show Logout
            document.getElementById("logout").style.display = "flex";

            // Show Headers and Links
            const links = document.querySelectorAll("#sidebarLinks a");
            links.forEach(l => l.style.display = 'flex');

            // Hide the Open Button
            document.querySelector(".menu-icon").style.display = "none";
        }

        // 3. Close Sidebar (Collapsed View)
        function closeNav() {
            // Set widths
            document.getElementById("mySidebar").style.width = "60px";
            document.getElementById("main").style.marginLeft = "60px";
            document.getElementById("containerAtas").style.marginLeft = "60px";

            // Hide Text Elements
            document.getElementById("nameSide").style.display = "none";
            document.getElementById("close").style.display = "none";

            // HIDE LOGOUT SPECIFICALLY
            document.getElementById("logout").style.display = "none";

            // Hide all links (Icons will be handled by CSS or specific logic if needed, 
            // but for 'Backup' style often links hide completely except for hamburger)
            const links = document.querySelectorAll("#sidebarLinks a");
            links.forEach(l => l.style.display = 'none');

            // Show the Open Button
            document.querySelector(".menu-icon").style.display = "block";
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            closeNav(); // Start closed
            updateRoleHeaderHighlighting(); // Set initial highlighting state
            initStudentDropdown();
            loadRubricsFromDB();
        });
        // ==========================================
        // SIDEBAR LOGIC END
        // ==========================================

        function initStudentDropdown() {
            const select = document.getElementById('studentSelect');
            const keys = Object.keys(studentData);

            if (keys.length === 0) {
                select.innerHTML = '<option value="" disabled selected>No students found.</option>';
                return;
            }
            select.innerHTML = '<option value="" disabled selected>Select a student...</option>';
            keys.forEach(matric => {
                const s = studentData[matric];
                const option = document.createElement('option');
                option.value = matric;
                option.textContent = `${matric} - ${s.name}`;
                select.appendChild(option);
            });
            select.addEventListener('change', function () {
                selectedStudentId = this.value;
                updateStudentDetails(selectedStudentId);
                fetchLogbookCounts(selectedStudentId); // Fetch logbook counts
                checkFormVisibility();
                if (selectedAssessmentKey) {
                    loadExistingMarks(); // Load marks if assessment already selected
                }
            });
        }

        function updateStudentDetails(matricNumber) {
            const detailsContainer = document.getElementById('studentDetails');
            const student = studentData[matricNumber];
            if (!student) return;
            detailsContainer.innerHTML = `
                <p><strong>Matric Number</strong>: ${student.matric}</p>
                <p><strong>Name</strong>: ${student.name}</p>
                
                <p><strong>Supervisor</strong>: ${student.supervisor}</p>
                <p><strong>Project title</strong>: ${student.projectTitle}</p>
            `;
        }

        // Fetch logbook approval counts for the selected student
        async function fetchLogbookCounts(studentId) {
            try {
                const response = await fetch(`fetch_logbook_counts.php?student_id=${studentId}`);
                const data = await response.json();

                if (data.status === 'success') {
                    logbookCounts = data.counts;
                    // Regenerate table if assessment is already selected to show updated counts
                    if (selectedAssessmentKey) {
                        generateEvaluationTable(selectedAssessmentKey);
                    }
                }
            } catch (error) {
                console.error('Error fetching logbook counts:', error);
                logbookCounts = { SWE4949A: 0, SWE4949B: 0 };
            }
        }

        function checkFormVisibility() {
            const content = document.getElementById('dynamicFormContent');
            if (selectedStudentId && selectedAssessmentKey) {
                content.classList.remove('hidden-content');
            } else {
                content.classList.add('hidden-content');
            }
        }

        async function loadRubricsFromDB() {
            try {
                const response = await fetch(`fetch_rubric.php?role=${currentActiveRole}`);
                const data = await response.json();
                if (data._meta) {
                    assessmentMeta = data._meta;
                    delete data._meta;
                }
                rubrics = data;

                const select = document.getElementById('assessmentSelect');

                // Check if any assessments are available
                const availableAssessments = Object.keys(assessmentMeta);

                if (availableAssessments.length === 0) {
                    // =======================================================================
                    // NOTE: This will trigger when due date checking is enabled
                    // =======================================================================
                    select.innerHTML = '<option value="" disabled selected>No assessments available at this time</option>';
                    return;
                }

                select.innerHTML = '<option value="" disabled selected>Select an assessment type...</option>';
                for (const [slug, details] of Object.entries(assessmentMeta)) {
                    const option = document.createElement('option');
                    option.value = slug;
                    option.textContent = details.name;

                    // =======================================================================
                    // NOTE: When due_date is enabled, uncomment to show due date info:
                    /*
                    if (details.due_date_info) {
                        const endDate = new Date(details.due_date_info.end_date + ' ' + details.due_date_info.end_time);
                        option.textContent += ` (Due: ${endDate.toLocaleString()})`;
                    }
                    */
                    // =======================================================================

                    select.appendChild(option);
                }
                select.addEventListener('change', function () {
                    selectedAssessmentKey = this.value;
                    generateEvaluationTable(selectedAssessmentKey);
                    checkFormVisibility();
                    loadExistingMarks(); // Load existing marks when assessment is selected
                });
            } catch (error) {
                console.error("Error:", error);
                alert("Failed to load assessments.");
            }
        }

        // Fetch existing marks for selected student and assessment
        async function loadExistingMarks() {
            if (!selectedStudentId || !selectedAssessmentKey) return;

            const realAssessmentId = assessmentMeta[selectedAssessmentKey].id;

            // Clear all existing values first
            clearForm();

            try {
                const response = await fetch(`fetch_existing_marks.php?student_id=${selectedStudentId}&assessment_id=${realAssessmentId}&role=${currentActiveRole}`);
                const data = await response.json();

                if (data.status === 'success') {
                    // Populate marks only if they exist
                    if (data.marks && data.marks.length > 0) {
                        data.marks.forEach(mark => {
                            let selector;
                            if (mark.subcriteria_id) {
                                // Find subcriteria dropdown
                                selector = `.mark-selector[data-criteria-id="${mark.criteria_id}"][data-element-id="${mark.subcriteria_id}"]`;
                            } else {
                                // Find criteria dropdown
                                selector = `.mark-selector[data-criteria-id="${mark.criteria_id}"][data-type="criteria"]`;
                            }

                            const dropdown = document.querySelector(selector);
                            if (dropdown) {
                                dropdown.value = mark.given_marks;
                            }
                        });
                    }

                    // Populate comment only if it exists
                    const commentBox = document.querySelector('.feedback-body textarea');
                    if (commentBox) {
                        commentBox.value = data.comment || '';
                    }

                    // Recalculate live scores
                    calculateLiveScore();
                }
            } catch (error) {
                console.error("Error loading existing marks:", error);
            }
        }

        // Clear all form values
        function clearForm() {
            // Reset all dropdowns to default "Select..." option
            document.querySelectorAll('.mark-selector').forEach(select => {
                select.selectedIndex = 0;
                select.style.border = ""; // Remove any validation styling
            });

            // Clear comment textarea
            const commentBox = document.querySelector('.feedback-body textarea');
            if (commentBox) commentBox.value = '';

            // Reset all score displays to 0.00
            document.querySelectorAll('.marks-input').forEach(input => {
                input.value = '0.00';
            });

            // Reset grand total
            const grandDisplay = document.getElementById('grand-total-display');
            if (grandDisplay) grandDisplay.value = '0.00';
        }

        function calculateLiveScore() {
            const allSelects = document.querySelectorAll('.mark-selector');
            let criteriaGroups = {};

            allSelects.forEach(select => {
                const cID = select.getAttribute('data-criteria-id');
                if (!criteriaGroups[cID]) criteriaGroups[cID] = [];
                criteriaGroups[cID].push(select);
            });

            let grandTotal = 0;

            for (const [cID, dropdowns] of Object.entries(criteriaGroups)) {
                let criteriaTotal = 0;

                // ======================================================
                // CRITERIA 10: REPORT ASSESSMENT (Per-Item Calculation)
                // ======================================================
                if (cID == '10') {
                    dropdowns.forEach(d => {
                        const subId = d.getAttribute('data-element-id');
                        const val = parseFloat(d.value) || 0;

                        // Formula A: Sub 1,2,7 -> (Score / 15) * 5
                        if (['1', '2', '7'].includes(subId)) {
                            criteriaTotal += (val / 15) * 5;
                        }
                        // Formula B: Sub 3,4,5,6 -> (Score / 20) * 15
                        else if (['3', '4', '5', '6'].includes(subId)) {
                            criteriaTotal += (val / 20) * 15;
                        }
                        // Formula C: Sub 8 -> Direct
                        else if (subId == '8') {
                            criteriaTotal += val;
                        }
                    });
                }
                // ======================================================
                // CRITERIA 12: SENSE OF RESPONSIBILITY (Per-Item /2)
                // ======================================================
                else if (cID == '12') {
                    dropdowns.forEach(d => {
                        const val = parseFloat(d.value) || 0;
                        criteriaTotal += val / 2; // Each item divided by 2
                    });
                }
                // ======================================================
                // CRITERIA 5, 6, 9: DOUBLE WEIGHT (Per-Item *2)
                // ======================================================
                else if (['5', '6', '9'].includes(cID)) {
                    dropdowns.forEach(d => {
                        const val = parseFloat(d.value) || 0;
                        criteriaTotal += val * 2; // Each item doubled
                    });
                }
                // ======================================================
                // STANDARD / DIRECT (Sum scores directly)
                // ======================================================
                else {
                    dropdowns.forEach(d => criteriaTotal += parseFloat(d.value) || 0);
                }

                const displayEl = document.getElementById(`display-total-${cID}`);
                if (displayEl) displayEl.value = criteriaTotal.toFixed(2);
                grandTotal += criteriaTotal;
            }
            const grandDisplay = document.getElementById('grand-total-display');
            if (grandDisplay) grandDisplay.value = grandTotal.toFixed(2);
        }

        function generateEvaluationTable(assessmentKey) {
            const tableBody = document.getElementById('evaluationTableBody');
            const assessmentRubric = rubrics[assessmentKey];

            if (!assessmentRubric || assessmentRubric.length === 0) {
                tableBody.innerHTML = `<div class="p-4 text-center">No criteria found.</div>`;
                return;
            }

            let htmlContent = `
        <div class="grid-cell header-row1">Evaluation Criteria</div>
        <div class="grid-cell header-row">Learning Objective</div>
        <div class="grid-cell header-row">Marks (%)</div>`;

            assessmentRubric.forEach((criterion, index) => {
                // Calculate the number (Index starts at 0, so we add 1)
                const criteriaNumber = index + 1;

                if (criterion.sub_criteria && criterion.sub_criteria.length > 0) {
                    // --- SCENARIO A: HAS SUB-CRITERIA (e.g. Thesis) ---
                    htmlContent += `
            <div class="grid-cell criteria-cell" style="background-color: #FFFFFFFF; border-bottom: none;">
                <h5 class="criteria-title" style="margin:0; color:#333;">${criteriaNumber}. ${criterion.title}</h5>
            </div>
            <div class="grid-cell lo-cell" style="background-color: #FFFFFFFF; border-bottom: none;">
                <strong>${criterion.outcomes}</strong>
            </div>
            <div class="grid-cell mark-cell" style="background-color: #FFFFFFFF; border-bottom: none;">
                <input type="text" class="form-control marks-input main-score-display" id="display-total-${criterion.id}" disabled readonly value="0.00" style="font-weight:bold;">
            </div>`;

                    criterion.sub_criteria.forEach((sub) => {
                        const subPoints = sub.description.map(p => `<li>${p}</li>`).join('');
                        let options = `<option value="" disabled selected>Select the mark...</option>`;
                        if (sub.marks_options && sub.marks_options.length > 0) {
                            sub.marks_options.forEach(opt => {
                                const val = opt.split(' - ')[0];
                                options += `<option value="${val}">${opt}</option>`;
                            });
                        } else {
                            for (let i = 0; i <= sub.max_marks; i++) {
                                options += `<option value="${i}">${i}</option>`;
                            }
                        }
                        htmlContent += `
                <div class="grid-cell criteria-cell" style="padding-left: 60px; padding-bottom: 15px; border-bottom: none;">
                    <h6 style="color:#333; margin-bottom: 5px;">${sub.name}</h6>
                    <ul class="criteria-list small text-muted">${subPoints}</ul>
                    <div class="evaluation-action">
                        <select class="form-select action-dropdown mark-selector sub-selector" 
                                data-criteria-id="${criterion.id}" 
                                data-type="subcriteria"
                                data-element-id="${sub.id}">
                            ${options}
                        </select>
                    </div>
                </div>
                <div class="grid-cell lo-cell"; style="border-bottom: none";></div>
                <div class="grid-cell mark-cell"; style="border-bottom: none";></div>`;
                    });
                } else {
                    // --- SCENARIO B: STANDARD CRITERIA ---
                    const dropdownOptions = criterion.marks.map((mark) => {
                        const markValue = mark.split(' - ')[0];
                        return `<option value="${markValue}">${mark}</option>`;
                    }).join('');
                    const criteriaList = criterion.criteria_points.map(c => `<li>${c}</li>`).join('');

                    htmlContent += `
            <div class="grid-cell criteria-cell" style="border-top: 1px solid #e9ecef;">
                <h4 class="criteria-title">${criteriaNumber}. ${criterion.title}</h4>
                <ul class="criteria-list">${criteriaList}</ul>
                <div class="evaluation-action">
                    <select class="form-select action-dropdown mark-selector" 
                            data-criteria-id="${criterion.id}" 
                            data-type="criteria"
                            data-element-id="${criterion.id}">
                        <option value="" disabled selected>Select the mark...</option>
                        ${dropdownOptions}
                    </select>
                </div>
            </div>
            <div class="grid-cell lo-cell" style="border-top: 1px solid #e9ecef;"><strong>${criterion.outcomes}</strong></div>
            <div class="grid-cell mark-cell" style="border-top: 1px solid #e9ecef;">
                <input type="text" class="form-control marks-input" id="display-total-${criterion.id}" disabled readonly value="0.00">
            </div>`;

                    // Add logbook count notice for criteria ID 14
                    if (criterion.id == 14) {
                        htmlContent += `
            <div class="grid-cell" style="grid-column: 1 / -1; background-color: #fff8e1; border: 2px solid #ffc107; border-radius: 8px; padding: 15px; margin: 10px 0;">
                <div style="color: #856404; font-weight: bold; font-size: 14px; margin-bottom: 8px;">
                    <i class="bi bi-info-circle-fill" style="margin-right: 5px;"></i>Note for rubric 5. Management:
                </div>
                <ol style="margin: 0; padding-left: 20px; color: #856404; font-size: 13px;">
                    <li>Student has <strong>${logbookCounts.SWE4949A}</strong> approved logbook${logbookCounts.SWE4949A !== 1 ? 's' : ''} for SWE4949A.</li>
                    <li>Student has <strong>${logbookCounts.SWE4949B}</strong> approved logbook${logbookCounts.SWE4949B !== 1 ? 's' : ''} for SWE4949B.</li>
                </ol>
            </div>`;
                    }
                }
            });
            // #e9ecef

            htmlContent += `
                <div class="grid-cell criteria-cell" style="background-color: #FFFFFFFF; border-top: 7px solid #780000; font-weight: bold; text-align: right; padding-right: 20px;">Total Marks (%)</div>
                <div class="grid-cell lo-cell" style="background-color: #FFFFFFFF; border-top: 7px solid #780000;"></div>
                <div class="grid-cell mark-cell" style="background-color: #FFFFFFFF; border-top: 7px solid #780000;">
                    <input type="text" id="grand-total-display" class="form-control text-center" disabled readonly value="0.00" style="font-weight:bold;">
                </div>`;
            tableBody.innerHTML = htmlContent;

            document.querySelectorAll('.mark-selector').forEach(select => {
                select.addEventListener('change', calculateLiveScore);
            });
        }

        const submitBtn = document.querySelector('.submit-container button');
        submitBtn.addEventListener('click', function () {
            if (!selectedStudentId || !selectedAssessmentKey) {
                alert("Please select student and assessment.");
                return;
            }
            const realAssessmentId = assessmentMeta[selectedAssessmentKey].id;
            let marksData = [];
            let allFilled = true;
            document.querySelectorAll('.mark-selector').forEach((select) => {
                if (!select.value) {
                    allFilled = false;
                    select.style.border = "2px solid red";
                } else {
                    select.style.border = "";
                    marksData.push({
                        criteria_id: select.getAttribute('data-criteria-id'),
                        score: select.value,
                        type: select.getAttribute('data-type'),
                        element_id: select.getAttribute('data-element-id')
                    });
                }
            });
            if (!allFilled) { alert("Please complete all fields."); return; }
            const commentBox = document.querySelector('.feedback-body textarea');
            const commentVal = commentBox ? commentBox.value : "";
            const payload = {
                student_id: selectedStudentId,
                assessment_id: realAssessmentId,
                marks: marksData,
                comment: commentVal,
                role: currentActiveRole
            };
            submitBtn.innerText = "Saving...";
            submitBtn.disabled = true;
            fetch('submit_evaluation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        submitBtn.innerText = "Submit";
                        submitBtn.disabled = false;
                        showAcknowledgementModal();
                    } else {
                        alert("Error: " + data.message);
                        submitBtn.innerText = "Submit";
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Server connection failed.");
                    submitBtn.innerText = "Submit";
                    submitBtn.disabled = false;
                });
        });

        // Acknowledgement Modal Functions
        const acknowledgementModal = document.getElementById('acknowledgementModal');
        const closeAcknowledgementModalBtn = document.getElementById('closeAcknowledgementModal');
        const okAcknowledgementBtn = document.getElementById('okAcknowledgementBtn');

        function openModal(modal) { modal.style.display = 'block'; }
        function closeModal(modal) { modal.style.display = 'none'; }

        function showAcknowledgementModal() {
            openModal(acknowledgementModal);
        }

        // Close modal on X button click
        closeAcknowledgementModalBtn?.addEventListener('click', () => {
            closeModal(acknowledgementModal);
        });

        // Close modal on OK button click
        okAcknowledgementBtn?.addEventListener('click', () => {
            closeModal(acknowledgementModal);
            window.location.reload();
        });

        // Close modal on backdrop click
        acknowledgementModal?.addEventListener('click', (e) => {
            if (e.target.id === 'acknowledgementModal') {
                closeModal(acknowledgementModal);
            }
        });
    </script>
</body>

</html>