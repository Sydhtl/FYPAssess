<?php include '../../../php/coordinator_bootstrap.php'; ?>
<script>
// Prevent back button after logout
window.history.pushState(null, "", window.location.href);
window.onpopstate = function() {
    window.history.pushState(null, "", window.location.href);
};

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
<?php

// Get coordinator's department ID
$departmentId = null;
if ($stmt = $conn->prepare("SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1")) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $departmentId = $row['Department_ID'];
        }
    }
    $stmt->close();
}

// Fetch courses for this department
$courses = [];
if ($departmentId) {
    $coursesQuery = "SELECT Course_ID, Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_Code";
    if ($stmt = $conn->prepare($coursesQuery)) {
        $stmt->bind_param("i", $departmentId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $courses[] = $row;
            }
        }
        $stmt->close();
    }
}

// Create a mapping of course codes to course IDs for JavaScript
$courseMapping = [];
foreach ($courses as $course) {
    $courseTab = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $course['Course_Code']));
    $courseMapping[$courseTab] = [
        'course_id' => $course['Course_ID'],
        'course_code' => $course['Course_Code']
    ];
}

// Use the first course code as the base display (strip trailing section letter)
$courseCodeA = !empty($courses[0]) ? $courses[0]['Course_Code'] : '';
$baseCourseCode = $courseCodeA ? preg_replace('/[-_ ]?[A-Za-z]$/', '', $courseCodeA) : '';

// Pass course mapping and filter values to JavaScript
$courseMappingJson = json_encode($courseMapping);
$selectedYearJson = json_encode($selectedYear ?? '');
$selectedSemesterJson = json_encode($selectedSemester ?? '');
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Mark Submission</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/markSubmission.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body class="mark-submission-page">

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
                <a href="../../../php/phpSupervisor/dashboard.php" id="dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../../../php/phpSupervisor/notification.php" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../../../php/phpSupervisor/industry_collaboration.php" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i>
                    Industry Collaboration</a>
                <a href="../../../php/phpAssessor_Supervisor/evaluation_form.php" id="evaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
                <a href="../../../php/phpSupervisor/report.php" id="superviseesReport"><i class="bi bi-bar-chart-fill icon-padding"></i> Supervisees' Report</a>
                <a href="../../../php/phpSupervisor/logbook_submission.php" id="logbookSubmission"><i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission</a>
            </div>

            <a href="#assessorMenu" class="role-header" data-role="assessor">
                <span class="role-text">Assessor</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-right arrow-icon"></i>
                </span>
            </a>

            <div id="assessorMenu" class="menu-items">
                <a href="../../../php/phpAssessor/dashboard.php" id="Dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../../../php/phpAssessor/notification.php" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../../../php/phpAssessor_Supervisor/evaluation_form.php" id="EvaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
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
                <a href="markSubmission.php" id="markSubmission" class="active-menu-item"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Stamp Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Deadline Allocation</a>
            </div>

            <a href="../../logout.php" id="logout">
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
                <div id="courseCode"><?php echo htmlspecialchars($baseCourseCode ?: $courseCodeA); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars(($selectedYear ?? '') . ' - ' . ($selectedSemester ?? '')); ?></div>
            </div>
        </div>
    </div>

    <div id="main" class="main-grid">
        <h1 class="page-title">Progress Submission</h1>

        <div class="filters-section">
            <div class="filter-group">
                <label for="yearFilter">Year</label>
                <select id="yearFilter" class="filter-select" onchange="reloadPageWithFilters()">
                    <?php foreach ($yearOptions as $year): ?>
                        <option value="<?php echo htmlspecialchars($year); ?>" 
                                <?php echo ($year == $selectedYear) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($year); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label for="semesterFilter">Semester</label>
                <select id="semesterFilter" class="filter-select" onchange="reloadPageWithFilters()">
                    <?php foreach ($semesterOptions as $semester): ?>
                        <option value="<?php echo htmlspecialchars($semester); ?>" 
                                <?php echo ($semester == $selectedSemester) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($semester); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mark-submission-container">
            <div class="tab-buttons">
                <button class="task-tab active-tab" data-tab="fyp-title-submission">FYP Title Submission</button>
                <?php 
                // Generate course tab buttons dynamically
                if (!empty($courses)) {
                    // Take first 2 courses (matching the original structure)
                    $course1 = $courses[0] ?? null;
                    $course2 = $courses[1] ?? null;
                    
                    // Normalize course code for use in data-tab attribute (lowercase, replace special chars)
                    if ($course1) {
                        $course1Tab = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $course1['Course_Code']));
                        echo '<button class="task-tab" data-tab="' . htmlspecialchars($course1Tab) . '">' . htmlspecialchars($course1['Course_Code']) . '</button>';
                    }
                    
                    if ($course2) {
                        $course2Tab = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $course2['Course_Code']));
                        echo '<button class="task-tab" data-tab="' . htmlspecialchars($course2Tab) . '">' . htmlspecialchars($course2['Course_Code']) . '</button>';
                    }
                } else {
                    // Fallback if no courses found
                    echo '<button class="task-tab" data-tab="swe4949a">SWE4949-A</button>';
                    echo '<button class="task-tab" data-tab="swe4949b">SWE4949-B</button>';
                }
                ?>
            </div>

            <div class="task-list-area">
                <!-- FYP Title Submission Tab -->
                <div class="task-group active" data-group="fyp-title-submission">
                    <div class="mark-content-container">
                        <div class="container-header">
                            <div class="view-dropdown-wrapper">
                                <div class="search-sort-container">
                                    <div class="search-wrapper">
                                        <i class="bi bi-search search-icon"></i>
                                        <input type="text" id="fypStudentSearch" class="search-input" placeholder="Search by name or matric no...">
                                    </div>
                                    <div class="sort-wrapper">
                                        <select id="fypCourseFilter" class="sort-dropdown" onchange="filterFYPTable()">
                                            <option value="all">All Courses</option>
                                        </select>
                                        <select id="fypStatusFilter" class="sort-dropdown" onchange="filterFYPTable()">
                                            <option value="all">All Status</option>
                                            <option value="approved">Approved</option>
                                            <option value="waiting">Waiting for Approval</option>
                                            <option value="rejected">Rejected</option>
                                            <option value="not submitted">Not Submitted</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="download-actions">
                                <div class="download-dropdown">
                                    <button id="downloadButtonFYP" class="btn-download" onclick="toggleDownloadDropdown('combined', 'fyp', this)">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownCombinedFYP">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('fyp-title-submission', 'submissions'); closeDownloadDropdown('combined', 'fyp');" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('fyp-title-submission', 'submissions'); closeDownloadDropdown('combined', 'fyp');" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="student-click-hint" data-tab="fyp-title-submission">Click on a student name to view their supervisor and assessors.</div>

                        <div class="table-scroll-container">
                            <table class="mark-submission-table" id="markTableFYP">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Matric No.</th>
                                        <th>Name</th>
                                        <th>Status Submission</th>
                                        <th>Submission</th>
                                        <th>Download as PDF</th>
                                    </tr>
                                </thead>
                                <tbody id="markTableBodyFYP">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SWE4949-A Tab -->
                <div class="task-group" data-group="swe4949a">
                    <div class="mark-content-container">
                        <div class="container-header">
                            <div class="view-dropdown-wrapper">
                                <select class="view-dropdown" id="viewDropdownA" onchange="changeView('swe4949a', this.value)">
                                    <option value="student-overview">Student's marks overview</option>
                                    <option value="lecturer-progress">Lecturer progress</option>
                                </select>
                                <select class="view-dropdown" id="assessmentFilterA" style="display:none; min-width: 250px;" onchange="filterAssessment('swe4949a', this.value)">
                                    <option value="all">All Assessments</option>
                                </select>
                            </div>
                            <div class="download-actions">
                                <div class="download-dropdown">
                                    <button id="downloadButtonA" class="btn-download" onclick="toggleDownloadDropdown('combined', 'a', this)">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownCombinedA">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949a', 'marks'); closeDownloadDropdown('combined', 'a');" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949a', 'marks'); closeDownloadDropdown('combined', 'a');" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                                <button id="downloadButtonNotifyA" class="btn-download btn-notify-group" onclick="notifyAllIncomplete('swe4949a')">
                                        <i class="bi bi-bell"></i>
                                    <span>Notify All</span>
                                    </button>
                            </div>
                        </div>

                                                    <div class="student-click-hint" data-tab="swe4949a">
                                                        Click on the Student's Marks Overview dropdown to switch to Lecturer Progress.
                                                        <br><br>Click on a student name to view their supervisor and assessors. 
                                                        <br><br>
                                                        Hover over the assessment to see more details.
                                                    </div>
                                                    <div class="lecturer-click-hint" data-tab="swe4949a">
                                                    Click on the Lecturer Progress dropdown to switch to Student's Marks Overview.<br><br>    
                                                    Click on a lecturer name to view the students they supervise or assess.
                                                        <br><br>
                                                      Hover over the assessment to see more details.
                                                    </div>
                        <div class="table-scroll-container">
                          
                            <table class="mark-submission-table" id="markTableA">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Matric No.</th>
                                        <th>Name</th>
                                        <th>FYP Title</th>
                                        <!-- Assessment columns will be dynamically generated by JavaScript -->
                                    </tr>
                                </thead>
                                <tbody id="markTableBodyA">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div class="table-footer">
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetMarks('swe4949a')">Cancel</button>
                                <button class="btn btn-success" onclick="saveMarks('swe4949a')">Save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SWE4949-B Tab -->
                <div class="task-group" data-group="swe4949b">
                    <div class="mark-content-container">
                        <div class="container-header">
                            <div class="view-dropdown-wrapper">
                                <select class="view-dropdown" id="viewDropdownB" onchange="changeView('swe4949b', this.value)">
                                    <option value="student-overview">Student's marks overview</option>
                                    <option value="lecturer-progress">Lecturer progress</option>
                                </select>
                                <select class="view-dropdown" id="assessmentFilterB" style="display:none; min-width: 250px;" onchange="filterAssessment('swe4949b', this.value)">
                                    <option value="all">All Assessments</option>
                                </select>
                            </div>
                            <div class="download-actions">
                                <div class="download-dropdown">
                                    <button id="downloadButtonB" class="btn-download" onclick="toggleDownloadDropdown('combined', 'b', this)">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownCombinedB">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949b', 'marks'); closeDownloadDropdown('combined', 'b');" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949b', 'marks'); closeDownloadDropdown('combined', 'b');" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                                <button id="downloadButtonNotifyB" class="btn-download btn-notify-group" onclick="notifyAllIncomplete('swe4949b')">
                                        <i class="bi bi-bell"></i>
                                    <span>Notify All</span>
                                    </button>
                
                        </div>
                        </div>
                         <div class="student-click-hint" data-tab="swe4949b">
                            Click on the Student's Marks Overview dropdown to switch to Lecturer Progress.<br><br>
                            Click on a student name to view their supervisor and assessors.
                            <br><br>
                                                        Hover over the assessment to see more details.
                         </div>
                         <div class="lecturer-click-hint" data-tab="swe4949b">
                            Click on the Lecturer Progress dropdown to switch to Student's Marks Overview.<br><br>
                            Click on a lecturer name to view the students they supervise or assess.
                            <br><br>
                                                        Hover over the assessment to see more details.
                                                   
                         </div>
                        <div class="table-scroll-container">
                          
                            <table class="mark-submission-table" id="markTableB">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Matric No.</th>
                                        <th>Name</th>
                                        <th>FYP Title</th>
                                        <!-- Assessment columns will be dynamically generated by JavaScript -->
                                    </tr>
                                </thead>
                                <tbody id="markTableBodyB">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <div class="table-footer">
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetMarks('swe4949b')">Cancel</button>
                                <button class="btn btn-success" onclick="saveMarks('swe4949b')">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="notifyModal" class="custom-modal"></div>
    <div id="fypFormModal" class="custom-modal"></div>
    <div id="studentDetailsModal" class="custom-modal"></div>

    <script>
        // --- FILTER RELOAD FUNCTION ---
        function reloadPageWithFilters() {
            const yearFilter = document.getElementById('yearFilter').value;
            const semesterFilter = document.getElementById('semesterFilter').value;
            
            // Build URL with query parameters
            const params = new URLSearchParams();
            if (yearFilter) params.append('year', yearFilter);
            if (semesterFilter) params.append('semester', semesterFilter);
            
            // Reload page with new parameters
            window.location.href = 'markSubmission.php?' + params.toString();
        }

        const collapsedWidth = "60px";
        const expandedWidth = "220px";

        // Load student data from PHP backend (filtered by year and semester)
        const studentsDataFromBackend = <?php echo $studentsDataJson; ?>;
        
        // Get unique courses for filter dropdown
        const courseOptions = <?php echo json_encode($fypSessionIds); ?>;
        
        // Course mapping for dynamic course tabs
        const courseMapping = <?php echo $courseMappingJson; ?>;
        const selectedYear = <?php echo $selectedYearJson; ?>;
        const selectedSemester = <?php echo $selectedSemesterJson; ?>;
        
        // Transform backend data to match the existing structure
        const fypTitleSubmissionData = studentsDataFromBackend.map((student, index) => {
            // Determine status based on backend data
            let status = 'not submitted';
            let statusDisplay = 'Not Submitted';
            
            if (student.status === 'Approved') {
                status = 'approved';
                statusDisplay = 'Approved';
            } else if (student.status === 'Rejected') {
                status = 'rejected';
                statusDisplay = 'Rejected';
            } else if (student.status === 'Waiting for Approval') {
                status = 'waiting';
                statusDisplay = 'Waiting for Approval';
            }
            
            const hasSubmission = student.status !== 'Not Submitted';
            
            return {
                id: student.id,
                matricNo: student.id, // Using Student_ID as matricNo
                name: student.name,
                status: status,
                statusDisplay: statusDisplay,
                fypSessionId: student.fypSessionId,
                // extra raw fields from backend for PDF generation
                courseCode: student.courseCode || '',
                semester: student.semester || '',
                fypSession: student.fypSession || '',
                address: student.address || '',
                phone: student.phone || '',
                programme: student.programme || '',
                minor: student.minor || '',
                cgpa: student.cgpa || '',
                titleStatus: student.titleStatus || '',
                supervisorName: student.supervisorName || '',
                assessorNames: student.assessorNames || '',
                comments: hasSubmission ? `Status: ${statusDisplay}` : 'No submission received yet.',
                submission: hasSubmission ? {
                    courseTitle: 'Final Year Project',
                    courseCode: student.courseCode || 'N/A',
                    creditHour: '4',
                    semester: student.semester || '<?php echo htmlspecialchars($selectedSemester); ?>',
                    fypSession: student.fypSession || '<?php echo htmlspecialchars($selectedYear); ?>',
                    studentName: student.name,
                    currentAddress: student.address || '-',
                    telNo: student.phone || '-',
                    programme: student.programme || 'N/A',
                    minor: student.minor || 'N/A',
                    cgpa: student.cgpa !== null && student.cgpa !== undefined ? String(student.cgpa) : '-',
                    currentTitle: student.projectTitle || '-',
                    proposedTitle: student.proposedTitle || '-',
                    titleStatus: student.titleStatus || '-',
                    supervisorName: student.supervisorName || '-',
                    assessorNames: student.assessorNames || '-'
                } : null
            };
        });

        // Store original data for filtering
        let filteredFYPData = [...fypTitleSubmissionData];

        // Keep the original hardcoded data structure for SWE4949-A and SWE4949-B
        const fypTitleSubmissionDataOld = [
            { 
                id: 1, 
                matricNo: '214673', 
                name: 'Aisyah Nur', 
                status: 'submitted',
                comments: 'Good submission, well-structured project proposal.',
                submission: {
                    courseTitle: 'Final Year Project',
                    courseCode: 'SWE4949',
                    creditHour: '4',
                    semester: '2',
                    studentName: 'Aisyah Nur binti Ahmad',
                    currentAddress: '123 Jalan Universiti, 43400 Serdang, Selangor',
                    telNo: '012-3456789',
                    programme: 'Software Engineering',
                    minor: 'N/A',
                    cgpa: '3.75',
                    dissertationTitle: 'QR Attendance System',
                    firstChoice: 'QR Attendance System',
                    secondChoice: 'IoT Smart Farming',
                    thirdChoice: 'E-Wallet App'
                }
            },
            { 
                id: 2, 
                matricNo: '214692', 
                name: 'Hakim Firdaus', 
                status: 'submitted',
                comments: 'Project needs more technical details.',
                submission: {
                    courseTitle: 'Final Year Project',
                    courseCode: 'SWE4949',
                    creditHour: '4',
                    semester: '2',
                    studentName: 'Hakim Firdaus bin Ismail',
                    currentAddress: '456 Jalan Putra, 43400 Serdang, Selangor',
                    telNo: '012-9876543',
                    programme: 'Software Engineering',
                    minor: 'N/A',
                    cgpa: '3.68',
                    dissertationTitle: 'IoT Smart Farming',
                    firstChoice: 'IoT Smart Farming',
                    secondChoice: 'Smart Parking System',
                    thirdChoice: 'Food Waste App'
                }
            },
            { 
                id: 3, 
                matricNo: '214726', 
                name: 'Nabila Zahra', 
                status: 'submitted',
                comments: 'Excellent proposal with clear objectives.',
                submission: {
                    courseTitle: 'Final Year Project',
                    courseCode: 'SWE4949',
                    creditHour: '4',
                    semester: '2',
                    studentName: 'Nabila Zahra binti Hassan',
                    currentAddress: '789 Jalan Serdang, 43400 Serdang, Selangor',
                    telNo: '012-1112223',
                    programme: 'Software Engineering',
                    minor: 'N/A',
                    cgpa: '3.82',
                    dissertationTitle: 'E-Wallet App',
                    firstChoice: 'E-Wallet App',
                    secondChoice: 'AI Student Chatbot',
                    thirdChoice: 'Mental Health Tracker'
                }
            },
            { 
                id: 4, 
                matricNo: '214673', 
                name: 'Faris Iman', 
                status: 'not submitted',
                comments: 'Submission pending. Please remind student to submit.',
                submission: null
            },
            { 
                id: 5, 
                matricNo: '214692', 
                name: 'Siti Hajar', 
                status: 'not submitted',
                comments: 'No submission received yet.',
                submission: null
            }
        ];

        // Dynamic marks data - will be populated via API calls
        const marksData = {};
        const lecturerProgressCache = {};
        // Store assessment columns for each tab
        const assessmentColumns = {};
        // Fetch lecturer progress data (supervisor/assessor completion)
        async function fetchLecturerProgress(tabName, forceRefresh = false) {
            // If force refresh, clear cache
            if (forceRefresh) {
                delete lecturerProgressCache[tabName];
            } else if (lecturerProgressCache[tabName]) {
                return lecturerProgressCache[tabName];
            }

            let courseId = null;
            const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
            if (tabButton && tabButton.dataset.courseId) {
                courseId = parseInt(tabButton.dataset.courseId);
            } else if (courseMapping[tabName]) {
                courseId = courseMapping[tabName].course_id;
            }
            if (!courseId) return { assessments: { Supervisor: [], Assessor: [] }, lecturers: [] };

            try {
                const params = new URLSearchParams({
                    course_id: courseId,
                    year: selectedYear,
                    semester: selectedSemester
                });
                const resp = await fetch(`../../../php/phpCoordinator/fetch_lecturer_progress.php?${params}`);
                const result = await resp.json();
                if (result.success) {
                    lecturerProgressCache[tabName] = result;
                    return result;
                }
            } catch (err) {
                console.error('Error fetching lecturer progress', err);
            }
            return { assessments: { Supervisor: [], Assessor: [] }, lecturers: [] };
        }
        
        // Function to fetch assessment columns for a course
        async function fetchAssessmentColumns(tabName, forceRefresh = false) {
            // Check if columns are already cached (unless force refresh)
            if (!forceRefresh && assessmentColumns[tabName]) {
                return assessmentColumns[tabName];
            }
            
            // If force refresh, clear cache
            if (forceRefresh) {
                delete assessmentColumns[tabName];
            }
            
            // Get course ID from button or mapping
            let courseId = null;
            
            const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
            if (tabButton && tabButton.dataset.courseId) {
                courseId = parseInt(tabButton.dataset.courseId);
            } else {
                const courseInfo = courseMapping[tabName];
                if (courseInfo) {
                    courseId = courseInfo.course_id;
                }
            }
            
            if (!courseId) {
                console.error('Course ID not found for tab:', tabName);
                return [];
            }
            
            try {
                const params = new URLSearchParams({
                    course_id: courseId,
                    year: selectedYear,
                    semester: selectedSemester
                });
                
                const response = await fetch(`../../../php/phpCoordinator/fetch_assessment_columns.php?${params}`);
                const result = await response.json();
                
                if (result.success && result.columns) {
                    assessmentColumns[tabName] = result.columns;
                    return result.columns;
                } else {
                    console.error('Error fetching assessment columns:', result.error);
                    return [];
                }
            } catch (error) {
                console.error('Error fetching assessment columns:', error);
                return [];
            }
        }
        
        // Function to fetch student marks data for a course
        async function fetchStudentMarks(tabName, forceRefresh = false) {
            // Get course ID from button or mapping
            let courseId = null;
            
            const tabButton = document.querySelector(`[data-tab="${tabName}"]`);
            if (tabButton && tabButton.dataset.courseId) {
                courseId = parseInt(tabButton.dataset.courseId);
            } else {
                const courseInfo = courseMapping[tabName];
                if (courseInfo) {
                    courseId = courseInfo.course_id;
                }
            }
            
            if (!courseId) {
                console.error('Course ID not found for tab:', tabName);
                return { students: [], columns: [] };
            }
            
            // Check if data is already cached (unless force refresh)
            if (!forceRefresh && marksData[tabName] && assessmentColumns[tabName]) {
                return {
                    students: marksData[tabName],
                    columns: assessmentColumns[tabName]
                };
            }
            
            // If force refresh, clear cache
            if (forceRefresh) {
                delete marksData[tabName];
                delete assessmentColumns[tabName];
            }
            
            try {
                // Fetch both students and assessment columns in parallel
                const [studentsResult, columnsResult] = await Promise.all([
                    fetch(`../../../php/phpCoordinator/fetch_student_marks.php?${new URLSearchParams({
                        course_id: courseId,
                        year: selectedYear,
                        semester: selectedSemester
                    })}`).then(r => r.json()),
                    fetchAssessmentColumns(tabName, forceRefresh)
                ]);
                
                if (studentsResult.success && studentsResult.students) {
                    // Transform the data to match the expected structure
                    // Initialize marks object for each assessment+LO combination
                    const columns = columnsResult || [];
                    marksData[tabName] = studentsResult.students.map((student, index) => {
                        const studentData = {
                            // Use actual student identifier for modal lookups
                            id: student.student_id,
                            matricNo: student.student_id,
                            name: student.name,
                            fypTitle: student.project_title,
                            comments: ''
                        };
                        
                        // Initialize marks for each column using evaluation data
                        columns.forEach(column => {
                            // Include criteria_id in the key so same LO code with different criteria gets its own cell
                            const criteriaIdStr = column.criteria_id ?? 'NULL';
                            const key = `assessment_${column.assessment_id}_criteria_${criteriaIdStr}_lo_${column.learning_objective_code}`;
                            const evalKey = `${column.assessment_id}_${criteriaIdStr}_${column.learning_objective_code}`;
                            if (student.evaluations && student.evaluations[evalKey] !== undefined) {
                                studentData[key] = parseFloat(student.evaluations[evalKey]).toFixed(2);
                            } else {
                                studentData[key] = '-';
                            }
                        });
                        
                        return studentData;
                    });
                    
                    assessmentColumns[tabName] = columns;
                    return {
                        students: marksData[tabName],
                        columns: columns
                    };
                } else {
                    console.error('Error fetching student marks:', studentsResult.error);
                    return { students: [], columns: columnsResult || [] };
                }
            } catch (error) {
                console.error('Error fetching student marks:', error);
                return { students: [], columns: [] };
            }
        }

        const lecturerProgressData = {
            swe4949a: [
                { 
                    id: 1, 
                    name: 'Dr. Ahmad Faiz bin Ismail', 
                    supervisorProgress: 10, 
                    assessorProgress: 100,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'completed' }
                    ]
                },
                { 
                    id: 2, 
                    name: 'Prof. Madya Dr. Noraini binti Hassan', 
                    supervisorProgress: 70, 
                    assessorProgress: 10,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ]
                },
                { 
                    id: 3, 
                    name: 'Dr. Khairul Anuar bin Salleh', 
                    supervisorProgress: 19, 
                    assessorProgress: 23,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ]
                },
                { 
                    id: 4, 
                    name: 'Pn. Siti Mariam binti Abdullah', 
                    supervisorProgress: 69, 
                    assessorProgress: 0,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ]
                },
                { 
                    id: 5, 
                    name: 'En. Mohd Hafiz bin Omar', 
                    supervisorProgress: 67, 
                    assessorProgress: 85,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'completed' }
                    ]
                },
                { 
                    id: 6, 
                    name: 'Dr. Zulkifli bin Rahman', 
                    supervisorProgress: 70, 
                    assessorProgress: 10,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ]
                },
                { 
                    id: 7, 
                    name: 'Prof. Dr. Faridah binti Ahmad', 
                    supervisorProgress: 19, 
                    assessorProgress: 23,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ]
                },
                { 
                    id: 8, 
                    name: 'Pn. Nurul Aini binti Zakaria', 
                    supervisorProgress: 69, 
                    assessorProgress: 0,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ]
                },
                { 
                    id: 9, 
                    name: 'En. Syahrul Nizam bin Musa', 
                    supervisorProgress: 67, 
                    assessorProgress: 85,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'completed' }
                    ]
                }
            ],
            swe4949b: [
                { 
                    id: 1, 
                    name: 'Dr. Ahmad Faiz bin Ismail', 
                    supervisorProgress: 10, 
                    assessorProgress: 100,
                    supervisorTasks: [
                        { task: 'Proposal Report', status: 'incomplete' },
                        { task: 'Proposal Seminar', status: 'incomplete' }
                    ],
                    assessorTasks: [
                        { task: 'Proposal Report', status: 'completed' },
                        { task: 'Proposal Seminar', status: 'completed' }
                    ]
                }
            ]
        };

        // Initialize currentView for all course tabs dynamically
        const currentView = {};
        const currentAssessmentFilter = {};
        
        // Initialize views for dynamic course tabs
        Object.keys(courseMapping).forEach(tabName => {
            currentView[tabName] = 'student-overview';
            currentAssessmentFilter[tabName] = 'all';
        });
        
        // Keep backward compatibility with hardcoded tabs
        if (!currentView['swe4949a']) currentView['swe4949a'] = 'student-overview';
        if (!currentView['swe4949b']) currentView['swe4949b'] = 'student-overview';
        if (!currentAssessmentFilter['swe4949a']) currentAssessmentFilter['swe4949a'] = 'all';
        if (!currentAssessmentFilter['swe4949b']) currentAssessmentFilter['swe4949b'] = 'all';

        document.addEventListener('DOMContentLoaded', async function() {
            initializeTabs();
            populateCourseFilter();
            initializeFYPSearch();
            
            // Check for URL parameters and apply filters
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            const viewParam = urlParams.get('view');
            const statusParam = urlParams.get('status');
            const assessmentParam = urlParams.get('assessment');
            
            // Apply tab selection if provided
            if (tabParam) {
                const tabButton = document.querySelector(`[data-tab="${tabParam}"]`);
                if (tabButton) {
                    // Trigger tab click to switch to the correct tab
                    tabButton.click();
                    
                    // If view parameter is provided, switch to that view (for course tabs)
                    if (viewParam && tabParam !== 'fyp-title-submission') {
                        setTimeout(async () => {
                            // Determine tab suffix based on tab name
                            let tabSuffix = 'A';
                            if (tabParam === 'swe4949a') {
                                tabSuffix = 'A';
                            } else if (tabParam === 'swe4949b') {
                                tabSuffix = 'B';
                            } else {
                                // For dynamic course tabs, find the index
                                const courseTabs = Object.keys(courseMapping);
                                const tabIndex = courseTabs.indexOf(tabParam);
                                if (tabIndex === 0) {
                                    tabSuffix = 'A';
                                } else if (tabIndex === 1) {
                                    tabSuffix = 'B';
                                }
                            }
                            
                            const viewDropdown = document.getElementById(`viewDropdown${tabSuffix}`);
                            if (viewDropdown) {
                                viewDropdown.value = viewParam;
                                await changeView(tabParam, viewParam);
                            }
                        }, 200);
                    }
                }
            }
            
            // Apply FYP title submission status filter if provided
            if (statusParam && tabParam === 'fyp-title-submission') {
                setTimeout(() => {
                    const statusFilter = document.getElementById('fypStatusFilter');
                    if (statusFilter) {
                        // Map status values
                        let statusValue = statusParam.toLowerCase();
                        if (statusValue === 'waiting') {
                            statusValue = 'waiting';
                        } else if (statusValue === 'approved') {
                            statusValue = 'approved';
                        } else if (statusValue === 'rejected') {
                            statusValue = 'rejected';
                        } else if (statusValue === 'not submitted' || statusValue === 'notsubmitted') {
                            statusValue = 'not submitted';
                        }
                        
                        if (statusValue === 'approved' || statusValue === 'waiting' || statusValue === 'rejected' || statusValue === 'not submitted') {
                            statusFilter.value = statusValue;
                            filterFYPTable();
                        }
                    }
                }, 100);
            }
            
            renderFYPTable();
            
            // Render tables for all course tabs dynamically
            const courseTabs = Object.keys(courseMapping);
            for (const tabName of courseTabs) {
                await renderTable(tabName);
            }
            
            // Render legacy tabs if they exist
            if (courseMapping['swe4949a']) {
                await renderTable('swe4949a');
            }
            if (courseMapping['swe4949b']) {
                await renderTable('swe4949b');
            }
            
            initializeRoleToggle();
            closeNav();
            
            // Add event delegation for download buttons
            // This handles all download button clicks using data attributes
            const fypTableBody = document.getElementById('markTableBodyFYP');
            if (fypTableBody) {
                fypTableBody.addEventListener('click', function(e) {
                    // Check if clicked element is the download button or its child (icon)
                    const downloadBtn = e.target.closest('.btn-download-table');
                    if (downloadBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Get student ID from data attribute
                        const studentId = downloadBtn.getAttribute('data-student-id');
                        
                        if (studentId) {
                            downloadFYPFormPDF(studentId);
                        } else {
                            console.error('Could not find student ID for download button');
                            openModal('Error', 'Could not find student information for download.');
                        }
                    }
                });
            }

            // Hide notify buttons initially (student-overview is default)
            ['A', 'B'].forEach(suffix => {
                const notifyButton = document.getElementById(`downloadButtonNotify${suffix}`);
                if (notifyButton) {
                    notifyButton.style.display = 'none';
                }
            });

            const modal = document.getElementById('notifyModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeModal();
                    closeFYPFormModal();
                    closeStudentDetailsModal();
                    closeAllDropdowns();
                }
            });

            const fypModal = document.getElementById('fypFormModal');
            if (fypModal) {
                fypModal.addEventListener('click', function(e) {
                    if (e.target === fypModal) {
                        closeFYPFormModal();
                    }
                });
            }
            
            const studentDetailsModal = document.getElementById('studentDetailsModal');
            if (studentDetailsModal) {
                studentDetailsModal.addEventListener('click', function(e) {
                    if (e.target === studentDetailsModal) {
                        closeStudentDetailsModal();
                    }
                });
            }
            
            // Start real-time polling for all active tabs
            startMarkSubmissionPolling();
            
            // Start real-time polling for FYP title submissions
            startFYPTitleSubmissionPolling();
            
            // Hide tooltip on window scroll or resize
            window.addEventListener('scroll', hideTooltip, true);
            window.addEventListener('resize', hideTooltip);
        });

        function populateCourseFilter() {
            const courseFilter = document.getElementById('fypCourseFilter');
            if (!courseFilter) return;

            // Build a map of FYP_Session_ID -> Course_Code so we can
            // show the actual course code in the dropdown while still
            // filtering by the underlying session ID.
            const sessionToCourseCode = new Map();
            fypTitleSubmissionData.forEach(s => {
                if (s.fypSessionId && s.courseCode && !sessionToCourseCode.has(s.fypSessionId)) {
                    sessionToCourseCode.set(s.fypSessionId, s.courseCode);
                }
            });

            // Clear existing options and keep only the "All Courses" default
            courseFilter.innerHTML = '<option value="all">All Courses</option>';

            // Add option for each unique session, displaying the course code
            sessionToCourseCode.forEach((courseCode, sessionId) => {
                const option = document.createElement('option');
                option.value = sessionId;        // still filter by session ID
                option.textContent = courseCode; // show course code to the user
                courseFilter.appendChild(option);
            });
        }

        function initializeFYPSearch() {
            const searchInput = document.getElementById('fypStudentSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function(e) {
                filterFYPTable();
            });
        }

        function filterFYPTable() {
            const searchTerm = document.getElementById('fypStudentSearch')?.value.toLowerCase() || '';
            const courseFilter = document.getElementById('fypCourseFilter')?.value || 'all';
            const statusFilter = document.getElementById('fypStatusFilter')?.value || 'all';

            filteredFYPData = fypTitleSubmissionData.filter(item => {
                // Search filter
                const matchesSearch = !searchTerm || 
                    item.name.toLowerCase().includes(searchTerm) || 
                    item.matricNo.toLowerCase().includes(searchTerm);

                // Course filter
                const matchesCourse = courseFilter === 'all' || 
                    item.fypSessionId == courseFilter;

                // Status filter
                const matchesStatus = statusFilter === 'all' || 
                    item.status === statusFilter;

                return matchesSearch && matchesCourse && matchesStatus;
            });

            renderFYPTable();
        }

        function renderFYPTable() {
            const tbody = document.getElementById('markTableBodyFYP');
            if (!tbody) return;

            tbody.innerHTML = '';

            if (!filteredFYPData.length) {
                tbody.innerHTML = `<tr><td colspan="6" class="empty-state">No FYP title submission data available</td></tr>`;
                return;
            }

            filteredFYPData.forEach((item, index) => {
                const row = document.createElement('tr');
                
                // Determine status class and text
                let statusClass = '';
                let statusText = '';
                
                if (item.status === 'approved') {
                    statusClass = 'status-approved';
                    statusText = 'Approved';
                } else if (item.status === 'rejected') {
                    statusClass = 'status-rejected';
                    statusText = 'Rejected';
                } else if (item.status === 'waiting') {
                    statusClass = 'status-waiting';
                    statusText = 'Waiting for Approval';
                } else {
                    statusClass = 'status-not-submitted';
                    statusText = 'Not Submitted';
                }
                
                let submissionHTML = '';
                if (item.submission) {
                    submissionHTML = `<a href="javascript:void(0)" onclick="openFYPFormModal('${item.id}')" class="submission-link">
                        <i class="bi bi-file-earmark-pdf"></i> View Submission
                    </a>`;
                } else {
                    submissionHTML = '<span class="no-submission">No submission yet</span>';
                }

                let downloadHTML = '';
                if (item.submission) {
                    // Escape the ID to prevent issues with special characters
                    const escapedId = String(item.id).replace(/"/g, '&quot;');
                    // Use only data attribute - event delegation will handle the click
                    downloadHTML = `<button class="btn-download-table" data-student-id="${escapedId}" title="Download as PDF">
                        <i class="bi bi-download"></i> Download
                    </button>`;
                } else {
                    downloadHTML = '<span class="no-submission">-</span>';
                }

                row.innerHTML = `
                    <td>${index + 1}.</td>
                    <td>${item.matricNo}</td>
                    <td><a href="javascript:void(0)" onclick="showStudentDetailsModal('${item.id}')" class="student-name-link" title="View student details">${item.name}</a></td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td class="submission-cell">${submissionHTML}</td>
                    <td class="download-cell">${downloadHTML}</td>
                `;
                tbody.appendChild(row);
            });
        }

        function initializeTabs() {
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.getAttribute('data-tab');
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            group.classList.add('active');
                        } else {
                            group.classList.remove('active');
                        }
                    });
                    closeAllDropdowns();
                });
            });
        }

        async function changeView(tabName, viewType) {
            currentView[tabName] = viewType;
            currentAssessmentFilter[tabName] = 'all'; // Reset filter when changing views
            
            // Get the table suffix for finding the correct dropdown
            let tableIdSuffix = '';
            if (tabName === 'swe4949a') {
                tableIdSuffix = 'A';
            } else if (tabName === 'swe4949b') {
                tableIdSuffix = 'B';
            }
            
            // Show/hide assessment filter dropdown
            const filterDropdown = document.getElementById(`assessmentFilter${tableIdSuffix}`);
            if (filterDropdown) {
                if (viewType === 'lecturer-progress') {
                    filterDropdown.style.display = 'inline-block';
                    // Populate the dropdown with assessment options
                    await populateAssessmentFilter(tabName, tableIdSuffix);
                } else {
                    filterDropdown.style.display = 'none';
                }
            }
            
            await renderTable(tabName);
            closeAllDropdowns();
            
            // Update hash for the new view type
            try {
                if (viewType === 'student-overview') {
                    const result = await fetchStudentMarks(tabName, false);
                    dataHashes[tabName] = hashData(result);
                } else {
                    const result = await fetchLecturerProgress(tabName, false);
                    dataHashes[tabName] = hashData(result);
                }
            } catch (error) {
                console.error(`Error updating hash after view change for tab ${tabName}:`, error);
            }
            
            // Toggle student-name hint visibility per tab
            document.querySelectorAll(`.student-click-hint[data-tab="${tabName}"]`).forEach(hint => {
                hint.style.display = viewType === 'student-overview' ? 'block' : 'none';
            });

            // Toggle lecturer-name hint visibility per tab
            document.querySelectorAll(`.lecturer-click-hint[data-tab="${tabName}"]`).forEach(hint => {
                hint.style.display = viewType === 'lecturer-progress' ? 'block' : 'none';
            });

            // Show/hide notify button based on view
            const tabSuffix = tabName.charAt(tabName.length - 1).toUpperCase();
            const notifyButton = document.getElementById(`downloadButtonNotify${tabSuffix}`);
            
            // Get the download actions container to toggle dropdown alignment
            const taskGroup = document.querySelector(`.task-group[data-group="${tabName}"]`);
            const downloadActions = taskGroup?.querySelector('.download-actions');
            
            if (viewType === 'student-overview') {
                // Hide notify button in student marks overview
                if (notifyButton) {
                    notifyButton.style.display = 'none';
                }
                // Remove lecturer-view class for dropdown alignment
                if (downloadActions) {
                    downloadActions.classList.remove('lecturer-view-actions');
                }
            } else {
                // Show notify button in lecturer progress view
                if (notifyButton) {
                    notifyButton.style.display = 'flex';
                }
                // Add lecturer-view class for dropdown alignment
                if (downloadActions) {
                    downloadActions.classList.add('lecturer-view-actions');
                }
            }
        }

        async function populateAssessmentFilter(tabName, tableIdSuffix) {
            const filterDropdown = document.getElementById(`assessmentFilter${tableIdSuffix}`);
            if (!filterDropdown) return;
            
            const progressData = await fetchLecturerProgress(tabName);
            const supAssess = progressData.assessments?.Supervisor || [];
            const assAssess = progressData.assessments?.Assessor || [];
            const lecturers = progressData.lecturers || [];
            
            // Clear existing options except the first "All Assessments"
            while (filterDropdown.options.length > 1) {
                filterDropdown.remove(1);
            }
            
            // Collect all unique statuses for each assessment
            const supervisorStatuses = {};
            const assessorStatuses = {};
            
            // Track which statuses exist for supervisor assessments
            supAssess.forEach(assessment => {
                supervisorStatuses[assessment.assessment_id] = new Set();
            });
            
            // Track which statuses exist for assessor assessments
            assAssess.forEach(assessment => {
                assessorStatuses[assessment.assessment_id] = new Set();
            });
            
            // Iterate through lecturers to find actual statuses
            lecturers.forEach(lec => {
                // Check supervisor assessments
                Object.entries(lec.status?.Supervisor || {}).forEach(([assessmentId, status]) => {
                    if (supervisorStatuses[assessmentId] && status.toLowerCase() !== 'n/a') {
                        supervisorStatuses[assessmentId].add(status.toLowerCase());
                    }
                });
                
                // Check assessor assessments
                Object.entries(lec.status?.Assessor || {}).forEach(([assessmentId, status]) => {
                    if (assessorStatuses[assessmentId] && status.toLowerCase() !== 'n/a') {
                        assessorStatuses[assessmentId].add(status.toLowerCase());
                    }
                });
            });
            
            // Add Supervisor assessments with their statuses
            supAssess.forEach(assessment => {
                const statuses = Array.from(supervisorStatuses[assessment.assessment_id] || []).sort();
                statuses.forEach(status => {
                    const option = document.createElement('option');
                    const statusText = status === 'completed' ? 'Complete' : 'Incomplete';
                    option.value = `supervisor_${assessment.assessment_id}_${status}`;
                    option.textContent = `${statusText} - ${assessment.assessment_name} (Supervisor)`;
                    filterDropdown.appendChild(option);
                });
            });
            
            // Add Assessor assessments with their statuses
            assAssess.forEach(assessment => {
                const statuses = Array.from(assessorStatuses[assessment.assessment_id] || []).sort();
                statuses.forEach(status => {
                    const option = document.createElement('option');
                    const statusText = status === 'completed' ? 'Complete' : 'Incomplete';
                    option.value = `assessor_${assessment.assessment_id}_${status}`;
                    option.textContent = `${statusText} - ${assessment.assessment_name} (Assessor)`;
                    filterDropdown.appendChild(option);
                });
            });
        }

        async function filterAssessment(tabName, filterValue) {
            currentAssessmentFilter[tabName] = filterValue;
            await renderTable(tabName);
        }

        function formatStatus(status) {
            const normalized = (status || '').toLowerCase() === 'completed';
            return {
                text: normalized ? 'Completed' : 'Incomplete',
                className: normalized ? 'status-completed' : 'status-incomplete'
            };
        }

        async function renderTable(tabName) {
            // Map tab names to table suffixes
            // Handle both hardcoded ('swe4949a', 'swe4949b') and dynamic course tabs
            let tableIdSuffix = '';
            
            if (tabName === 'swe4949a') {
                tableIdSuffix = 'A';
            } else if (tabName === 'swe4949b') {
                tableIdSuffix = 'B';
            } else {
                // For dynamic course tabs, map to first two available tables (A and B)
                const courseTabs = Object.keys(courseMapping);
                const tabIndex = courseTabs.indexOf(tabName);
                if (tabIndex === 0) {
                    tableIdSuffix = 'A';
                } else if (tabIndex === 1) {
                    tableIdSuffix = 'B';
                } else {
                    // Fallback: use first available or default to 'A'
                    tableIdSuffix = 'A';
                }
            }
            
            const table = document.getElementById(`markTable${tableIdSuffix}`);
            const tbody = document.getElementById(`markTableBody${tableIdSuffix}`);
            const thead = table ? table.querySelector('thead') : null;
            if (!table || !tbody || !thead) {
                console.error('Table elements not found for tab:', tabName);
                return;
            }

            const viewType = currentView[tabName] || 'student-overview';

            if (viewType === 'lecturer-progress') {
                table.classList.add('lecturer-view');
                thead.innerHTML = '';
                tbody.innerHTML = '';

                const progressData = await fetchLecturerProgress(tabName);
                const supAssess = progressData.assessments?.Supervisor || [];
                const assAssess = progressData.assessments?.Assessor || [];
                const lecturers = progressData.lecturers || [];

                // Apply assessment filter
                const filterValue = currentAssessmentFilter[tabName] || 'all';
                let filteredSupAssess = supAssess;
                let filteredAssAssess = assAssess;
                let filterStatus = null; // Track the filter status
                
                if (filterValue !== 'all') {
                    const parts = filterValue.split('_');
                    const role = parts[0]; // 'supervisor' or 'assessor'
                    const assessmentId = parts[1];
                    filterStatus = parts[2]; // 'completed' or 'incomplete'
                    
                    if (role === 'supervisor') {
                        filteredSupAssess = supAssess.filter(a => a.assessment_id == assessmentId);
                        filteredAssAssess = [];
                    } else if (role === 'assessor') {
                        filteredAssAssess = assAssess.filter(a => a.assessment_id == assessmentId);
                        filteredSupAssess = [];
                    }
                }

                if (!lecturers.length) {
                    const totalCols = 2 + (filteredSupAssess.length || 0) + (filteredAssAssess.length || 0) + 1; // No., Name, Supervisor cols, Assessor cols, Notify
                    thead.innerHTML = `
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            ${filteredSupAssess.length ? `<th colspan="${filteredSupAssess.length}">Supervisor</th>` : ''}
                            ${filteredAssAssess.length ? `<th colspan="${filteredAssAssess.length}">Assessor</th>` : ''}
                            <th>Notify</th>
                        </tr>
                        <tr><th colspan="${totalCols}">No lecturer data available</th></tr>
                    `;
                    tbody.innerHTML = `<tr><td colspan="${totalCols}" class="empty-state">No lecturer data available</td></tr>`;
                    return;
                }

                // Build two-row header
                let headerRow1 = '<tr>';
                headerRow1 += '<th rowspan="2">No.</th>';
                headerRow1 += '<th rowspan="2">Name</th>';
                if (filteredSupAssess.length) {
                    headerRow1 += `<th colspan="${filteredSupAssess.length}">Supervisor</th>`;
                }
                if (filteredAssAssess.length) {
                    headerRow1 += `<th colspan="${filteredAssAssess.length}">Assessor</th>`;
                }
                headerRow1 += '<th rowspan="2">Notify</th>';
                headerRow1 += '</tr>';

                let headerRow2 = '<tr>';
                filteredSupAssess.forEach(a => { headerRow2 += `<th>${a.assessment_name}</th>`; });
                filteredAssAssess.forEach(a => { headerRow2 += `<th>${a.assessment_name}</th>`; });
                headerRow2 += '</tr>';

                thead.innerHTML = headerRow1 + headerRow2;

                // Filter lecturers based on status if a specific assessment/status is selected
                let filteredLecturers = lecturers;
                if (filterValue !== 'all' && filterStatus) {
                    const parts = filterValue.split('_');
                    const role = parts[0];
                    const assessmentId = parts[1];
                    
                    filteredLecturers = lecturers.filter(lec => {
                        if (role === 'supervisor') {
                            const status = lec.status?.Supervisor?.[assessmentId];
                            return status && status.toLowerCase() === filterStatus;
                        } else if (role === 'assessor') {
                            const status = lec.status?.Assessor?.[assessmentId];
                            return status && status.toLowerCase() === filterStatus;
                        }
                        return false;
                    });
                }

                // Render rows
                filteredLecturers.forEach((lec, idx) => {
                    const row = document.createElement('tr');
                    const originalIndex = lecturers.indexOf(lec);
                    const lookupIndex = originalIndex >= 0 ? originalIndex : idx;
                    let rowHTML = `<td>${idx + 1}.</td><td><a href="javascript:void(0)" class="student-name-link" title="View students" onclick="showLecturerStudentsModal('${tabName}', ${lookupIndex})">${lec.name}</a></td>`;

                    filteredSupAssess.forEach(a => {
                        const status = lec.status?.Supervisor?.[a.assessment_id] || 'N/A';
                        const statusInfo = formatStatus(status.toLowerCase() === 'completed' ? 'completed' : status.toLowerCase() === 'incomplete' ? 'incomplete' : 'incomplete');
                        const text = status === 'N/A' ? 'N/A' : statusInfo.text;
                        const cls = status === 'N/A' ? '' : statusInfo.className;
                        
                        // Build tooltip text for lecturer progress (include lecturer name)
                        let tooltipText = `Lecturer: ${lec.name}\nRole: Supervisor\nAssessment: ${a.assessment_name}`;
                        if (a.criteria && a.criteria.length > 0) {
                            tooltipText += '\n\nCriteria:';
                            a.criteria.forEach(crit => {
                                const critName = crit.criteria_name || `Criteria ${crit.criteria_id}`;
                                tooltipText += `\n  • ${critName} (ID: ${crit.criteria_id})`;
                            });
                        } else {
                            tooltipText += '\n\nNo criteria assigned';
                        }
                        const tooltipAttr = status !== 'N/A' ? ` class="table-cell-tooltip" data-tooltip="${tooltipText.replace(/"/g, '&quot;')}"` : '';
                        
                        rowHTML += `<td${tooltipAttr}>${status === 'N/A' ? 'N/A' : `<span class="status-badge ${cls}">${text}</span>`}</td>`;
                    });

                    filteredAssAssess.forEach(a => {
                        const status = lec.status?.Assessor?.[a.assessment_id] || 'N/A';
                        const statusInfo = formatStatus(status.toLowerCase() === 'completed' ? 'completed' : status.toLowerCase() === 'incomplete' ? 'incomplete' : 'incomplete');
                        const text = status === 'N/A' ? 'N/A' : statusInfo.text;
                        const cls = status === 'N/A' ? '' : statusInfo.className;
                        
                        // Build tooltip text for lecturer progress (include lecturer name)
                        let tooltipText = `Lecturer: ${lec.name}\nRole: Assessor\nAssessment: ${a.assessment_name}`;
                        if (a.criteria && a.criteria.length > 0) {
                            tooltipText += '\n\nCriteria:';
                            a.criteria.forEach(crit => {
                                const critName = crit.criteria_name || `Criteria ${crit.criteria_id}`;
                                tooltipText += `\n  • ${critName} (ID: ${crit.criteria_id})`;
                            });
                        } else {
                            tooltipText += '\n\nNo criteria assigned';
                        }
                        const tooltipAttr = status !== 'N/A' ? ` class="table-cell-tooltip" data-tooltip="${tooltipText.replace(/"/g, '&quot;')}"` : '';
                        
                        rowHTML += `<td${tooltipAttr}>${status === 'N/A' ? 'N/A' : `<span class="status-badge ${cls}">${text}</span>`}</td>`;
                    });

                    // Add notify button column
                    const lecturerIdentifier = lec.name.replace(/'/g, "\\'").replace(/"/g, '&quot;'); // Escape quotes
                    rowHTML += `<td class="notify-cell">
                        <button class="btn-notify" onclick="notifyLecturer('${tabName}', '${lecturerIdentifier}')" title="Notify ${lec.name.replace(/'/g, "\\'")}">
                            <i class="bi bi-bell"></i> Notify
                        </button>
                    </td>`;

                    row.innerHTML = rowHTML;
                    tbody.appendChild(row);
                });
                
                // Initialize tooltips for lecturer progress
                initializeTooltips(table);
            } else {
                // Student marks overview - fetch data dynamically
                table.classList.remove('lecturer-view');
                
                // Show loading state
                tbody.innerHTML = `<tr><td colspan="6" class="empty-state">Loading student data...</td></tr>`;
                
                // Fetch student marks data (includes columns)
                const result = await fetchStudentMarks(tabName);
                const data = result.students || [];
                const columns = result.columns || [];
                
                // Group columns by assessment
                const assessmentGroups = {};
                columns.forEach(column => {
                    if (!assessmentGroups[column.assessment_id]) {
                        assessmentGroups[column.assessment_id] = {
                            assessment_name: column.assessment_name,
                            learning_objectives: []
                        };
                    }
                    // Include criteria_id and criteria_name in the learning objectives array
                    assessmentGroups[column.assessment_id].learning_objectives.push({
                        ...column,
                        criteria_id: column.criteria_id,
                        criteria_name: column.criteria_name
                    });
                });
                
                // Build multi-row header
                // Row 1: Base headers + Assessment names (with rowspan)
                let headerRow1 = '<tr>';
                headerRow1 += '<th rowspan="2">No.</th>';
                headerRow1 += '<th rowspan="2">Matric No.</th>';
                headerRow1 += '<th rowspan="2">Name</th>';
                headerRow1 += '<th rowspan="2">FYP Title</th>';
                
                // Add assessment headers with colspan = number of LOs for that assessment
                Object.keys(assessmentGroups).forEach(assessmentId => {
                    const group = assessmentGroups[assessmentId];
                    const colspan = group.learning_objectives.length;
                    headerRow1 += `<th colspan="${colspan}">${group.assessment_name}</th>`;
                });
                headerRow1 += '</tr>';
                
                // Row 2: Learning Objective codes and percentages (stacked vertically)
                // Also include criteria name/ID if available
                let headerRow2 = '<tr>';
                Object.keys(assessmentGroups).forEach(assessmentId => {
                    const group = assessmentGroups[assessmentId];
                    group.learning_objectives.forEach(lo => {
                        // Include criteria name if it exists, otherwise show criteria ID
                        let criteriaText = '';
                        if (lo.criteria_name) {
                            criteriaText = `<div style="font-size: 11px; font-weight: 500; margin-bottom: 2px;">${lo.criteria_name}</div>`;
                        } else if (lo.criteria_id) {
                            criteriaText = `<div style="font-size: 11px; font-weight: 500; margin-bottom: 2px;">Criteria ${lo.criteria_id}</div>`;
                        }
                        headerRow2 += `<th><div style="display: flex; flex-direction: column; align-items: center; line-height: 1.4;">${criteriaText}<div>${lo.learning_objective_code}</div><div>(${parseFloat(lo.percentage).toFixed(2)}%)</div></div></th>`;
                    });
                });
                headerRow2 += '</tr>';
                
                thead.innerHTML = headerRow1 + headerRow2;
                tbody.innerHTML = '';

                if (!data || !data.length) {
                    const colspan = 4 + columns.length;
                    tbody.innerHTML = `<tr><td colspan="${colspan}" class="empty-state">No student data available for this course</td></tr>`;
                    return;
                }

                // Render rows with dynamic columns
                data.forEach((item, index) => {
                    const row = document.createElement('tr');
                    let rowHTML = `
                        <td>${index + 1}.</td>
                        <td>${item.matricNo}</td>
                        <td><a href="javascript:void(0)" class="student-name-link" title="View student details" onclick="showStudentDetailsModal('${item.id}')">${item.name}</a></td>
                        <td>${item.fypTitle}</td>
                    `;
                    
                    // Add cells for each assessment+LO column in order
                    columns.forEach(column => {
                        const criteriaIdStr = column.criteria_id ?? 'NULL';
                        const key = `assessment_${column.assessment_id}_criteria_${criteriaIdStr}_lo_${column.learning_objective_code}`;
                        const value = item[key] || '-';
                        
                        // Build tooltip text (include student name)
                        const criteriaText = column.criteria_name || (column.criteria_id ? `Criteria ${column.criteria_id}` : 'No Criteria');
                        const tooltipText = `Student: ${item.name}\nAssessment: ${column.assessment_name}\nCriteria: ${criteriaText}\nLearning Objective: ${column.learning_objective_code}\nPercentage: ${parseFloat(column.percentage).toFixed(2)}%`;
                        
                        rowHTML += `<td class="table-cell-tooltip" data-tooltip="${tooltipText.replace(/"/g, '&quot;')}">${value}</td>`;
                    });
                    
                    row.innerHTML = rowHTML;
                    tbody.appendChild(row);
                });
                
                // Initialize tooltips for student marks overview
                initializeTooltips(table);
            }
        }

        // Real-time polling for marks and progress
        let pollingInterval = null;
        let pollingPaused = false;
        const POLLING_INTERVAL = 1000; // Poll every second
        
        // Store hash of current data for comparison
        const dataHashes = {};
        
        // Simple hash function for data comparison
        function hashData(data) {
            return JSON.stringify(data);
        }
        
        // Check if data has changed and update UI if needed
        async function checkAndUpdateData(tabName) {
            try {
                const viewType = currentView[tabName] || 'student-overview';
                
                if (viewType === 'student-overview') {
                    // Fetch fresh student marks data
                    const result = await fetchStudentMarks(tabName, true); // Force refresh
                    const newHash = hashData(result);
                    
                    if (dataHashes[tabName] !== newHash) {
                        dataHashes[tabName] = newHash;
                        // Data changed, re-render the table
                        await renderTable(tabName);
                    }
                } else if (viewType === 'lecturer-progress') {
                    // Fetch fresh lecturer progress data
                    const result = await fetchLecturerProgress(tabName, true); // Force refresh
                    const newHash = hashData(result);
                    
                    if (dataHashes[tabName] !== newHash) {
                        dataHashes[tabName] = newHash;
                        // Data changed, re-render the table
                        await renderTable(tabName);
                    }
                }
            } catch (error) {
                console.error(`Error checking data for tab ${tabName}:`, error);
            }
        }
        
        // Start polling for all active course tabs
        function startMarkSubmissionPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            
            // Initial hash calculation for all tabs
            const courseTabs = Object.keys(courseMapping);
            courseTabs.forEach(async (tabName) => {
                try {
                    const viewType = currentView[tabName] || 'student-overview';
                    if (viewType === 'student-overview') {
                        const result = await fetchStudentMarks(tabName, false);
                        dataHashes[tabName] = hashData(result);
                    } else {
                        const result = await fetchLecturerProgress(tabName, false);
                        dataHashes[tabName] = hashData(result);
                    }
                } catch (error) {
                    console.error(`Error initializing hash for tab ${tabName}:`, error);
                }
            });
            
            // Set up polling interval
            pollingInterval = setInterval(() => {
                if (!pollingPaused && document.visibilityState === 'visible') {
                    const courseTabs = Object.keys(courseMapping);
                    courseTabs.forEach((tabName) => {
                        // Only poll for the currently active/visible tab, or poll all tabs
                        checkAndUpdateData(tabName);
                    });
                    
                    // Also poll legacy tabs if they exist
                    if (courseMapping['swe4949a']) {
                        checkAndUpdateData('swe4949a');
                    }
                    if (courseMapping['swe4949b']) {
                        checkAndUpdateData('swe4949b');
                    }
                }
            }, POLLING_INTERVAL);
        }
        
        // Pause/resume polling based on page visibility
        document.addEventListener('visibilitychange', function() {
            pollingPaused = document.hidden;
            fypTitlePollingPaused = document.hidden;
        });
        
        // Cleanup polling on page unload
        window.addEventListener('beforeunload', function() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
            }
            if (fypTitlePollingInterval) {
                clearInterval(fypTitlePollingInterval);
            }
        });
        
        // Real-time polling for FYP Title Submissions
        let fypTitlePollingInterval = null;
        let fypTitlePollingPaused = false;
        const FYPTITLE_POLLING_INTERVAL = 1000; // Poll every second
        let currentFYPTitleHash = '';
        
        // Helper function to transform backend data to frontend format
        function transformFYPTitleData(studentsData) {
            return studentsData.map((student, index) => {
                // Determine status based on backend data
                let status = 'not submitted';
                let statusDisplay = 'Not Submitted';
                
                if (student.status === 'Approved') {
                    status = 'approved';
                    statusDisplay = 'Approved';
                } else if (student.status === 'Rejected') {
                    status = 'rejected';
                    statusDisplay = 'Rejected';
                } else if (student.status === 'Waiting for Approval') {
                    status = 'waiting';
                    statusDisplay = 'Waiting for Approval';
                }
                
                const hasSubmission = student.status !== 'Not Submitted';
                
                return {
                    id: student.id,
                    matricNo: student.id, // Using Student_ID as matricNo
                    name: student.name,
                    status: status,
                    statusDisplay: statusDisplay,
                    fypSessionId: student.fypSessionId,
                    // extra raw fields from backend for PDF generation
                    courseCode: student.courseCode || '',
                    semester: student.semester || '',
                    fypSession: student.fypSession || '',
                    address: student.address || '',
                    phone: student.phone || '',
                    programme: student.programme || '',
                    minor: student.minor || '',
                    cgpa: student.cgpa !== null && student.cgpa !== undefined ? student.cgpa : null,
                    submission: hasSubmission ? {
                        courseTitle: 'Final Year Project',
                        courseCode: student.courseCode || 'N/A',
                        creditHour: '4',
                        semester: student.semester || selectedSemester,
                        fypSession: student.fypSession || selectedYear,
                        studentName: student.name,
                        currentAddress: student.address || '-',
                        telNo: student.phone || '-',
                        programme: student.programme || 'N/A',
                        minor: student.minor || 'N/A',
                        cgpa: student.cgpa !== null && student.cgpa !== undefined ? String(student.cgpa) : '-',
                        currentTitle: student.projectTitle || '-',
                        proposedTitle: student.proposedTitle || '-',
                        titleStatus: student.titleStatus || '-',
                        supervisorName: student.supervisorName || '-'
                    } : null
                };
            });
        }

        // Hash function for FYP title data
        function hashFYPTitleData(data) {
            return JSON.stringify(data.map(item => ({
                id: item.id,
                status: item.status,
                statusDisplay: item.statusDisplay,
                proposedTitle: item.submission?.proposedTitle || null,
                projectTitle: item.submission?.currentTitle || null
            })));
        }
        
        // Fetch FYP title submissions from API
        async function fetchFYPTitleSubmissions() {
            try {
                const params = new URLSearchParams({
                    year: selectedYear || '',
                    semester: selectedSemester || ''
                });
                
                const response = await fetch(`../../../php/phpCoordinator/fetch_title_submissions.php?${params}`);
                const result = await response.json();
                
                if (result.success && result.students) {
                    const transformedData = transformFYPTitleData(result.students);
                    const newHash = hashFYPTitleData(transformedData);
                    
                    // Only update if there are changes
                    if (newHash !== currentFYPTitleHash) {
                        currentFYPTitleHash = newHash;
                        // Update the global data
                        fypTitleSubmissionData.length = 0;
                        fypTitleSubmissionData.push(...transformedData);
                        // Update filtered data and re-render table
                        filteredFYPData = [...fypTitleSubmissionData];
                        renderFYPTable();
                    }
                }
            } catch (error) {
                console.error('Error fetching FYP title submissions:', error);
            }
        }
        
        // Start polling for FYP title submissions
        function startFYPTitleSubmissionPolling() {
            if (fypTitlePollingInterval) {
                clearInterval(fypTitlePollingInterval);
            }
            
            // Initial hash calculation from existing data
            currentFYPTitleHash = hashFYPTitleData(fypTitleSubmissionData);
            
            // Set up polling interval
            fypTitlePollingInterval = setInterval(() => {
                if (!fypTitlePollingPaused && document.visibilityState === 'visible') {
                    fetchFYPTitleSubmissions();
                }
            }, FYPTITLE_POLLING_INTERVAL);
        }
        
        
        // Tooltip management functions
        let tooltipElement = null;
        
        function createTooltipElement() {
            if (!tooltipElement) {
                tooltipElement = document.createElement('div');
                tooltipElement.className = 'table-tooltip';
                const tooltipContent = document.createElement('div');
                tooltipContent.className = 'table-tooltip-content';
                const arrow = document.createElement('div');
                arrow.className = 'table-tooltip-arrow';
                tooltipElement.appendChild(tooltipContent);
                tooltipElement.appendChild(arrow);
                document.body.appendChild(tooltipElement);
            }
            return tooltipElement;
        }
        
        function initializeTooltips(table) {
            const tooltipCells = table.querySelectorAll('.table-cell-tooltip[data-tooltip]');
            
            tooltipCells.forEach(cell => {
                cell.addEventListener('mouseenter', function(e) {
                    showTooltip(this, e);
                });
                
                cell.addEventListener('mouseleave', function() {
                    hideTooltip();
                });
                
                cell.addEventListener('mousemove', function(e) {
                    updateTooltipPosition(this, e);
                });
            });
        }
        
        function showTooltip(cell, event) {
            const tooltip = createTooltipElement();
            const tooltipText = cell.getAttribute('data-tooltip');
            if (!tooltipText) return;
            
            // Clear any existing hide timeout
            if (tooltip.hideTimeout) {
                clearTimeout(tooltip.hideTimeout);
                tooltip.hideTimeout = null;
            }
            
            // Set tooltip content in the content div
            const content = tooltip.querySelector('.table-tooltip-content');
            if (content) {
                content.textContent = tooltipText;
            }
            
            // Calculate and set position
            updateTooltipPosition(cell, event);
        }
        
        function updateTooltipPosition(cell, event) {
            if (!tooltipElement) return;
            
            const tooltip = tooltipElement;
            const tooltipText = cell.getAttribute('data-tooltip');
            if (!tooltipText) return;
            
            // Ensure content is set
            const content = tooltip.querySelector('.table-tooltip-content');
            if (content && content.textContent !== tooltipText) {
                content.textContent = tooltipText;
            }
            
            // Temporarily show to measure
            tooltip.style.visibility = 'hidden';
            tooltip.style.display = 'block';
            tooltip.style.opacity = '0';
            tooltip.style.position = 'fixed';
            
            const tooltipRect = tooltip.getBoundingClientRect();
            const cellRect = cell.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const padding = 12; // Space from viewport edges
            const arrowOffset = 8; // Space between cell and tooltip
            
            // Calculate default position (below, centered)
            let top = cellRect.bottom + arrowOffset;
            let left = cellRect.left + (cellRect.width / 2) - (tooltipRect.width / 2);
            let position = 'below';
            
            // Check right overflow
            if (left + tooltipRect.width > viewportWidth - padding) {
                left = viewportWidth - tooltipRect.width - padding;
            }
            
            // Check left overflow
            if (left < padding) {
                left = padding;
            }
            
            // Check bottom overflow - try above
            if (top + tooltipRect.height > viewportHeight - padding) {
                const topAbove = cellRect.top - tooltipRect.height - arrowOffset;
                if (topAbove >= padding) {
                    top = topAbove;
                    position = 'above';
                } else {
                    // If above doesn't fit, constrain to bottom
                    top = viewportHeight - tooltipRect.height - padding;
                }
            }
            
            // For right-edge cells, align tooltip to left edge of cell
            const rightEdgeThreshold = viewportWidth - 300;
            if (cellRect.right > rightEdgeThreshold) {
                left = cellRect.right - tooltipRect.width;
                if (left < padding) {
                    left = padding;
                }
            }
            
            // For left-edge cells, align tooltip to right edge of cell
            const leftEdgeThreshold = 300;
            if (cellRect.left < leftEdgeThreshold && cellRect.right < viewportWidth / 2) {
                left = cellRect.left;
                if (left + tooltipRect.width > viewportWidth - padding) {
                    left = viewportWidth - tooltipRect.width - padding;
                }
            }
            
            // Final bounds check
            left = Math.max(padding, Math.min(left, viewportWidth - tooltipRect.width - padding));
            top = Math.max(padding, Math.min(top, viewportHeight - tooltipRect.height - padding));
            
            // Calculate arrow position to point to the cell center
            const cellCenterX = cellRect.left + (cellRect.width / 2);
            const tooltipCenterX = left + (tooltipRect.width / 2);
            const arrowOffsetX = cellCenterX - left; // Distance from tooltip left edge to cell center
            const arrowOffsetPercent = (arrowOffsetX / tooltipRect.width) * 100;
            
            // Apply position with fixed positioning
            tooltip.style.position = 'fixed';
            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
            tooltip.className = 'table-tooltip show ' + position;
            
            // Position arrow to point to the cell center
            const arrow = tooltip.querySelector('.table-tooltip-arrow');
            if (arrow) {
                // Clamp arrow position to stay within tooltip bounds (10% to 90%)
                const clampedPercent = Math.max(10, Math.min(90, arrowOffsetPercent));
                
                if (position === 'below') {
                    // Arrow positioned horizontally based on cell center relative to tooltip
                    arrow.style.top = '-12px';
                    arrow.style.left = clampedPercent + '%';
                    arrow.style.right = 'auto';
                    arrow.style.bottom = 'auto';
                    arrow.style.transform = 'translateX(-50%)';
                    arrow.style.borderTopColor = 'transparent';
                    arrow.style.borderRightColor = 'transparent';
                    arrow.style.borderLeftColor = 'transparent';
                    arrow.style.borderBottomColor = '#333';
                } else if (position === 'above') {
                    // Arrow positioned horizontally based on cell center
                    arrow.style.bottom = '-12px';
                    arrow.style.left = clampedPercent + '%';
                    arrow.style.right = 'auto';
                    arrow.style.top = 'auto';
                    arrow.style.transform = 'translateX(-50%)';
                    arrow.style.borderBottomColor = 'transparent';
                    arrow.style.borderRightColor = 'transparent';
                    arrow.style.borderLeftColor = 'transparent';
                    arrow.style.borderTopColor = '#333';
                } else {
                    // For left/right positions, center vertically
                    arrow.style.top = '50%';
                    arrow.style.transform = 'translateY(-50%)';
                }
            }
            
            // Clear any hide timeout when showing
            if (tooltip.hideTimeout) {
                clearTimeout(tooltip.hideTimeout);
                tooltip.hideTimeout = null;
            }
            
            // Show tooltip immediately
            tooltip.style.visibility = 'visible';
            tooltip.style.display = 'block';
            tooltip.style.opacity = '';
            // Force reflow to ensure transition works
            tooltip.offsetHeight;
            tooltip.classList.add('show');
        }
        
        function hideTooltip() {
            if (tooltipElement) {
                // Clear any existing hide timeout
                if (tooltipElement.hideTimeout) {
                    clearTimeout(tooltipElement.hideTimeout);
                }
                
                // Remove show class to trigger fade-out
                tooltipElement.classList.remove('show');
                
                // Ensure complete removal after transition
                tooltipElement.hideTimeout = setTimeout(() => {
                    if (tooltipElement && !tooltipElement.classList.contains('show')) {
                        tooltipElement.style.visibility = 'hidden';
                        tooltipElement.style.display = 'none';
                        tooltipElement.style.opacity = '0';
            }
                }, 100); // Match transition duration
            }
        }

        function notifyLecturer(tabName, lecturerName) {
            closeAllDropdowns();
            
            // Show loading modal
            showLoadingModal(`Sending notification to ${lecturerName}...`);
            
            // Call email API
            fetch('../../../php/phpCoordinator/send_notification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    lecturer_name: lecturerName,
                    course_code: tabName.toUpperCase(),
                    year: selectedYear,
                    semester: selectedSemester,
                    page: 'mark_submission'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    openModal('Notification Sent', `Notification has been sent to ${lecturerName} for ${tabName.toUpperCase()}. ${data.message || ''}`);
                } else {
                    openModal('Error', `Failed to send notification: ${data.message || 'Unknown error'}`);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                openModal('Error', 'An error occurred while sending the notification. Please try again.');
            })
            .finally(() => {
                hideLoadingModal();
            });
        }

        async function notifyAllIncomplete(tabName) {
            closeAllDropdowns();
            
            // Show loading modal
            showLoadingModal('Preparing to send notifications to lecturers with incomplete tasks...');
            
            try {
                // Fetch lecturer progress data to get lecturer names and status
                const progressData = await fetchLecturerProgress(tabName);
                const lecturers = progressData.lecturers || [];
                const supAssess = progressData.assessments?.Supervisor || [];
                const assAssess = progressData.assessments?.Assessor || [];
                
                if (lecturers.length === 0) {
                    openModal('Error', 'No lecturers found for this course.');
                    return;
                }
                
                // Filter lecturers who have incomplete tasks
                const lecturersToNotify = lecturers.filter(lec => {
                    // Check if lecturer has any incomplete assessments
                    let hasIncomplete = false;
                    
                    // Check supervisor assessments
                    supAssess.forEach(a => {
                        const status = lec.status?.Supervisor?.[a.assessment_id];
                        if (status && status.toLowerCase() === 'incomplete') {
                            hasIncomplete = true;
                        }
                    });
                    
                    // Check assessor assessments
                    assAssess.forEach(a => {
                        const status = lec.status?.Assessor?.[a.assessment_id];
                        if (status && status.toLowerCase() === 'incomplete') {
                            hasIncomplete = true;
                        }
                    });
                    
                    return hasIncomplete;
                });
                
                if (lecturersToNotify.length === 0) {
                    hideLoadingModal();
                    openModal('Info', 'All lecturers have completed their tasks. No notifications to send.');
                    return;
                }
                
                // Update loading message
                showLoadingModal(`Sending notifications to ${lecturersToNotify.length} lecturer(s)...`);
                
                // Send emails to all lecturers with incomplete tasks
                const emailPromises = lecturersToNotify.map(lec => {
                    return fetch('../../../php/phpCoordinator/send_notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            lecturer_name: lec.name,
                            course_code: tabName.toUpperCase(),
                            year: selectedYear,
                            semester: selectedSemester,
                            page: 'mark_submission'
                        })
                    }).then(response => response.json());
                });
                
                // Wait for all emails to be sent
                const results = await Promise.all(emailPromises);
                const successful = results.filter(r => r.success).length;
                const failed = results.filter(r => !r.success).length;
                
                hideLoadingModal();
                if (failed === 0) {
                    openModal('Notification Sent', `Successfully sent notifications to ${successful} lecturer(s) with incomplete tasks for ${tabName.toUpperCase()}.`);
                } else {
                    openModal('Partial Success', `Sent to ${successful} lecturer(s), ${failed} failed. Check console for details.`);
                    console.error('Failed notifications:', results.filter(r => !r.success));
                }
            } catch (error) {
                hideLoadingModal();
                console.error('Error sending notifications:', error);
                openModal('Error', 'An error occurred while sending notifications. Please try again.');
            }
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.download-dropdown-menu').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.btn-download.active').forEach(btn => btn.classList.remove('active'));
        }

        function toggleDownloadDropdown(type, tab, button) {
            const idSuffix = type === 'combined' ? `Combined${tab.toUpperCase()}` : `${type.charAt(0).toUpperCase() + type.slice(1)}${tab.toUpperCase()}`;
            const dropdown = document.getElementById(`downloadDropdown${idSuffix}`);
            const triggerButton = button || document.getElementById(`downloadButton${type === 'combined' ? tab.toUpperCase() : idSuffix}`);
            const wasOpen = dropdown?.classList.contains('show');

            closeAllDropdowns();

            if (dropdown && !wasOpen) {
                dropdown.classList.add('show');
                if (triggerButton) {
                    triggerButton.classList.add('active');
                }
            }
        }

        function closeDownloadDropdown() {
            closeAllDropdowns();
        }

        async function buildLecturerProgressExport(tabName) {
            const progress = await fetchLecturerProgress(tabName);
            const supAssess = progress.assessments?.Supervisor || [];
            const assAssess = progress.assessments?.Assessor || [];
            const lecturers = progress.lecturers || [];

            const headers = ['No.', 'Name', ...supAssess.map(a => `Supervisor - ${a.assessment_name}`), ...assAssess.map(a => `Assessor - ${a.assessment_name}`)];

            const toStatusText = (status) => {
                if (!status || status === 'N/A') return '-';
                const lower = String(status).toLowerCase();
                if (lower === 'completed') return 'Completed';
                if (lower === 'incomplete') return 'Incomplete';
                return status;
            };

            const rows = lecturers.map((lec, index) => {
                const supervisorStatuses = supAssess.map(a => toStatusText(lec.status?.Supervisor?.[a.assessment_id]));
                const assessorStatuses = assAssess.map(a => toStatusText(lec.status?.Assessor?.[a.assessment_id]));
                return [index + 1, lec.name, ...supervisorStatuses, ...assessorStatuses];
            });

            return { headers, rows };
        }

        async function downloadAsPDF(tabName, type) {
            // Special handling for FYP Title Submission - download all individual student PDFs
            if (tabName === 'fyp-title-submission' && type === 'submissions') {
                downloadAllFYPSubmissionsPDF();
                return;
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('l', 'pt', 'a4');
            const viewType = currentView[tabName];
            let head = [];
            let body = [];
            let title = '';
            let filename = '';

            if (tabName === 'fyp-title-submission') {
                const data = fypTitleSubmissionData || [];
                if (!data.length) {
                    openModal('Download Failed', 'No FYP title submission data available.');
                    return;
                }
                const includeComments = type === 'submissions-comments';
                const headers = ['No.', 'Matric No.', 'Name', 'Status Submission', 'Submission'];
                if (includeComments) {
                    headers.push('Comments');
                }
                head = [headers];
                body = data.map((item, index) => {
                    const row = [
                        index + 1,
                        item.matricNo,
                        item.name,
                        item.statusDisplay || 'Not Submitted',
                        item.submission ? 'Yes' : 'No'
                    ];
                    if (includeComments) {
                        row.push(item.comments || '-');
                    }
                    return row;
                });
                title = `FYP Title Submission${includeComments ? ' - With Comments' : ''}`;
                filename = `fyp-title-submission${includeComments ? '-with-comments' : ''}.pdf`;
            } else if (viewType === 'lecturer-progress') {
                const exportData = await buildLecturerProgressExport(tabName);
                if (!exportData.rows.length) {
                    openModal('Download Failed', 'No lecturer progress data available.');
                    return;
                }
                head = [exportData.headers];
                body = exportData.rows;
                title = `Lecturer Progress (${tabName.toUpperCase()})`;
                filename = `lecturer-progress-${tabName}.pdf`;
            } else {
                const data = marksData[tabName] || [];
                const columns = assessmentColumns[tabName] || [];
                
                if (!data.length) {
                    openModal('Download Failed', 'No marks data available.');
                    return;
                }
                
                const includeComments = type === 'marks-comments';
                const headers = ['No.', 'Matric No.', 'Name', 'FYP Title'];
                
                // Add dynamic assessment columns
                // For exports, use format: Assessment Name - Criteria Name - LO Code (Percentage%)
                columns.forEach(column => {
                    const criteriaPart = column.criteria_name ? ` - ${column.criteria_name}` : (column.criteria_id ? ` - Criteria ${column.criteria_id}` : '');
                    const exportTitle = `${column.assessment_name}${criteriaPart} - ${column.learning_objective_code} (${parseFloat(column.percentage).toFixed(2)}%)`;
                    headers.push(exportTitle);
                });
                
                if (includeComments) {
                    headers.push('Comments');
                }
                
                head = [headers];
                body = data.map((item, index) => {
                    const row = [index + 1, item.matricNo, item.name, item.fypTitle];
                    
                        // Add marks for each assessment+criteria+LO column
                        columns.forEach(column => {
                            const criteriaIdStr = column.criteria_id ?? 'NULL';
                            const key = `assessment_${column.assessment_id}_criteria_${criteriaIdStr}_lo_${column.learning_objective_code}`;
                            row.push(item[key] || '-');
                        });
                    
                    if (includeComments) {
                        row.push(item.comments || '-');
                    }
                    return row;
                });
                
                title = `Student Marks Overview (${tabName.toUpperCase()})${includeComments ? ' - With Comments' : ''}`;
                filename = `student-marks-${tabName}${includeComments ? '-with-comments' : ''}.pdf`;
            }

            doc.setFontSize(16);
            doc.text(title, 40, 40);
            doc.autoTable({
                head,
                body,
                startY: 60,
                styles: { font: 'helvetica', fontSize: 10 },
                headStyles: { fillColor: [120, 0, 0], textColor: 255 },
                alternateRowStyles: { fillColor: [245, 234, 234] },
                margin: { left: 40, right: 40 }
            });
            doc.save(filename);
            closeAllDropdowns();
        }

        async function downloadAsExcel(tabName, type) {
            const viewType = currentView[tabName];
            let headers = [];
            let rows = [];
            let filename = '';

            if (tabName === 'fyp-title-submission') {
                const data = fypTitleSubmissionData || [];
                if (!data.length) {
                    openModal('Download Failed', 'No FYP title submission data available.');
                    return;
                }
                const includeComments = type === 'submissions-comments';
                headers = ['No.', 'Matric No.', 'Name', 'Status Submission', 'Submission'];
                if (includeComments) {
                    headers.push('Comments');
                }
                rows = data.map((item, index) => {
                    const row = [
                        index + 1,
                        item.matricNo,
                        item.name,
                        item.statusDisplay || 'Not Submitted',
                        item.submission ? 'Yes' : 'No'
                    ];
                    if (includeComments) {
                        row.push(item.comments || '-');
                    }
                    return row;
                });
                filename = `fyp-title-submission${includeComments ? '-with-comments' : ''}.csv`;
            } else if (viewType === 'lecturer-progress') {
                const exportData = await buildLecturerProgressExport(tabName);
                if (!exportData.rows.length) {
                    openModal('Download Failed', 'No lecturer progress data available.');
                    return;
                }
                headers = exportData.headers;
                rows = exportData.rows;
                filename = `lecturer-progress-${tabName}.csv`;
            } else {
                const data = marksData[tabName] || [];
                const columns = assessmentColumns[tabName] || [];
                
                if (!data.length) {
                    openModal('Download Failed', 'No marks data available.');
                    return;
                }
                
                const includeComments = type === 'marks-comments';
                headers = ['No.', 'Matric No.', 'Name', 'FYP Title'];
                
                // Add dynamic assessment columns
                // For exports, use format: Assessment Name - Criteria Name - LO Code (Percentage%)
                columns.forEach(column => {
                    const criteriaPart = column.criteria_name ? ` - ${column.criteria_name}` : (column.criteria_id ? ` - Criteria ${column.criteria_id}` : '');
                    const exportTitle = `${column.assessment_name}${criteriaPart} - ${column.learning_objective_code} (${parseFloat(column.percentage).toFixed(2)}%)`;
                    headers.push(exportTitle);
                });
                
                if (includeComments) {
                    headers.push('Comments');
                }
                
                rows = data.map((item, index) => {
                    const row = [index + 1, item.matricNo, item.name, item.fypTitle];
                    
                // Add marks for each assessment+criteria+LO column
                columns.forEach(column => {
                    const criteriaIdStr = column.criteria_id ?? 'NULL';
                    const key = `assessment_${column.assessment_id}_criteria_${criteriaIdStr}_lo_${column.learning_objective_code}`;
                    row.push(item[key] || '-');
                });
                    
                    if (includeComments) {
                        row.push(item.comments || '-');
                    }
                    return row;
                });
                
                filename = `student-marks-${tabName}${includeComments ? '-with-comments' : ''}.csv`;
            }

            const csvLines = [];
            const escapeValue = (value) => `"${String(value).replace(/"/g, '""')}"`;
            csvLines.push(headers.map(escapeValue).join(','));
            rows.forEach(row => {
                csvLines.push(row.map(escapeValue).join(','));
            });

            const csvContent = csvLines.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            closeAllDropdowns();
        }

        // Loading modal functions
        function showLoadingModal(message) {
            const modal = document.getElementById('notifyModal');
            if (modal) {
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <div class="modal-icon" style="color: #007bff;"><i class="bi bi-hourglass-split" style="font-size: 48px;"></i></div>
                            <div class="modal-title-custom">Processing...</div>
                            <div class="modal-message">${message || 'Saving data and sending emails. Please wait.'}</div>
                        </div>
                    </div>
                `;
                modal.style.display = 'flex';
            }
        }
        
        function hideLoadingModal() {
            const modal = document.getElementById('notifyModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        // Loading modal styling removed (no animation)


        function openModal(title, message) {
            const modal = document.getElementById('notifyModal');
            if (!modal) return;

            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" onclick="closeModal()">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">${title || 'Notification Sent'}</div>
                        <div class="modal-message">${message || 'Notification has been sent.'}</div>
                        <div style="display:flex; justify-content:center;">
                            <button class="btn btn-success" onclick="closeModal()">OK</button>
                        </div>
                    </div>
                </div>`;
            modal.style.display = 'flex';
        }

        function closeModal() {
            const modal = document.getElementById('notifyModal');
            if (modal) {
                modal.style.display = 'none';
                modal.innerHTML = '';
            }
        }

        function openFYPFormModal(studentId) {
            const student = fypTitleSubmissionData.find(item => item.id === studentId);
            if (!student || !student.submission) {
                openModal('Error', 'Submission data not found.');
                return;
            }
            
            // Generate and open PDF in new tab
            generateFYPSubmissionPdf(studentId);
        }
        
        function generateFYPSubmissionPdf(studentId) {
            // Convert studentId to string for consistent comparison
            const studentIdStr = String(studentId);
            const student = fypTitleSubmissionData.find(item => String(item.id) === studentIdStr);
            if (!student || !student.submission) {
                openModal('Error', 'Submission data not found.');
                return;
            }
            
            // Use the unified PDF generation function
            generateFYPSubmissionPDFContent(student, function(doc, error) {
                if (error) {
                    console.error('Error generating PDF:', error);
                    openModal('Error', `Failed to generate PDF. ${error}`);
                    return;
                }
                
                if (!doc) {
                    openModal('Error', 'Failed to generate PDF.');
                    return;
                }
                
                // Open PDF in new window
                window.open(doc.output('bloburl'), '_blank');
            });
        }
        
        function showStudentDetailsModal(studentId) {
            // Convert studentId to string for consistent comparison
            const studentIdStr = String(studentId);
            
            // Search in all data
            let student = fypTitleSubmissionData.find(item => String(item.id) === studentIdStr);
            
            if (!student) {
                openModal('Error', 'Student data not found.');
                return;
            }
            
            const modal = document.getElementById('studentDetailsModal');
            if (!modal) return;
            
            // Prepare data to display
            const fypTitle = student.submission?.currentTitle || student.projectTitle || '-';
            const supervisor = student.supervisorName || '-';
            
            // Split assessors by comma and create separate rows for each
            const assessorsArray = student.assessorNames 
                ? student.assessorNames.split(',').map(name => name.trim()).filter(name => name)
                : [];
            
            let assessorsHTML = '';
            if (assessorsArray.length > 0) {
                assessorsArray.forEach((assessor, index) => {
                    assessorsHTML += `
                        <div class="detail-field">
                            <label class="detail-label">Assessor ${index + 1}:</label>
                            <span class="detail-value">${assessor}</span>
                        </div>`;
                });
            } else {
                assessorsHTML = `
                    <div class="detail-field">
                        <label class="detail-label">Assessors:</label>
                        <span class="detail-value">-</span>
                    </div>`;
            }
            
            modal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" style="color: #fff;" onclick="closeStudentDetailsModal()">&times;</span>
                        <h2 style="margin-top: 0; margin-bottom: 20px; color: #333; font-size: 20px; font-weight: 700;">Student Details</h2>
                        
                        <div class="student-details-section">
                            <div class="detail-field">
                                <label class="detail-label">Name:</label>
                                <span class="detail-value">${student.name}</span>
                            </div>
                            
                            <div class="detail-field">
                                <label class="detail-label">Matric No:</label>
                                <span class="detail-value">${student.matricNo}</span>
                            </div>
                            
                            <div class="detail-field">
                                <label class="detail-label">FYP Title:</label>
                                <span class="detail-value">${fypTitle}</span>
                            </div>
                            
                            <div class="detail-field">
                                <label class="detail-label">Supervisor:</label>
                                <span class="detail-value">${supervisor}</span>
                            </div>
                            
                            ${assessorsHTML}
                        </div>
                        
                        <div class="modal-footer" style="margin-top: 20px; text-align: center;">
                            <button class="btn btn-secondary" onclick="closeStudentDetailsModal()">Close</button>
                        </div>
                    </div>
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        // Show students supervised/assessed by a lecturer in lecturer-progress view
        async function showLecturerStudentsModal(tabName, lecturerIndex) {
            try {
                // Prefer cached data; fallback to fresh fetch
                let progress = lecturerProgressCache[tabName];
                if (!progress) {
                    progress = await fetchLecturerProgress(tabName, false);
                }

                const lecturer = progress?.lecturers?.[lecturerIndex];
                if (!lecturer) {
                    openModal('Error', 'Lecturer data not found.');
                    return;
                }

                const modal = document.getElementById('studentDetailsModal');
                if (!modal) return;

                const supervised = lecturer.students_supervise || [];
                const assessed = lecturer.students_assess || [];

                const supervisedList = supervised.length
                    ? supervised.map(s => `<li>${s.name} (${s.id})</li>`).join('')
                    : '<li>-</li>';

                const assessedList = assessed.length
                    ? assessed.map(s => `<li>${s.name} (${s.id})</li>`).join('')
                    : '<li>-</li>';

                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" style="color: #fff;" onclick="closeStudentDetailsModal()">&times;</span>
                            <h2 style="margin-top: 0; margin-bottom: 20px; color: #333; font-size: 20px; font-weight: 700;">Lecturer Students</h2>

                            <div class="student-details-section">
                                <div class="detail-field">
                                    <label class="detail-label">Lecturer:</label>
                                    <span class="detail-value">${lecturer.name}</span>
                                </div>

                                <div class="detail-field">
                                    <label class="detail-label">Supervises:</label>
                                    <span class="detail-value" style="display:block;">
                                        <ul style="margin: 4px 0 0 16px; padding: 0; list-style: disc;">
                                            ${supervisedList}
                                        </ul>
                                    </span>
                                </div>

                                <div class="detail-field">
                                    <label class="detail-label">Assesses:</label>
                                    <span class="detail-value" style="display:block;">
                                        <ul style="margin: 4px 0 0 16px; padding: 0; list-style: disc;">
                                            ${assessedList}
                                        </ul>
                                    </span>
                                </div>
                            </div>

                            <div class="modal-footer" style="margin-top: 20px; text-align: center;">
                                <button class="btn btn-secondary" onclick="closeStudentDetailsModal()">Close</button>
                            </div>
                        </div>
                    </div>
                `;

                modal.style.display = 'flex';
            } catch (err) {
                console.error('Error showing lecturer students modal:', err);
                openModal('Error', 'Unable to load lecturer student list.');
            }
        }
        
        function closeStudentDetailsModal() {
            const modal = document.getElementById('studentDetailsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        // Keep the old implementation as fallback (will be removed after testing)
        function generateFYPSubmissionPdf_OLD(studentId) {
            const student = fypTitleSubmissionData.find(item => item.id === studentId);
            if (!student || !student.submission) {
                openModal('Error', 'Submission data not found.');
                return;
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const sub = student.submission;
            
            // Load and add UPM logo
            var img = new Image();
            img.src = '../../../assets/UPMLogo.png';
            img.onload = function() {
                // Add logo centered at top
                var logoWidth = 30;
                var logoHeight = 20;
                var pageWidth = 210;
                var xPos = (pageWidth - logoWidth) / 2;
                doc.addImage(img, 'PNG', xPos, 10, logoWidth, logoHeight);
                
                // Title
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('FYP Title Submission', 105, 35, { align: 'center' });
                
                // Line separator
                doc.setLineWidth(0.5);
                doc.line(20, 40, 190, 40);
                
                // Content
                doc.setFontSize(12);
                doc.setFont(undefined, 'normal');
                
                var yPos = 50;
                var lineHeight = 10;
                
                // Course Information Section
                doc.setFont(undefined, 'bold');
                var courseInfoWidth = doc.getTextWidth('Course Information');
                doc.text('Course Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + courseInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Course Code:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.courseCode || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Semester:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text((sub.fypSession || 'N/A') + ' - ' + (sub.semester || 'N/A'), 70, yPos);
                yPos += lineHeight + 5;
                
                // Line separator
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;
                
                // Student Information Section
                doc.setFont(undefined, 'bold');
                var studentInfoWidth = doc.getTextWidth('Student Information');
                doc.text('Student Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + studentInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Student Name:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.studentName || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Matric No:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(student.matricNo || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Current Address:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var addressLines = doc.splitTextToSize(sub.currentAddress || 'N/A', 120);
                doc.text(addressLines, 70, yPos);
                yPos += lineHeight * addressLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Tel. No:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.telNo || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Programme:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var programmeLines = doc.splitTextToSize(sub.programme || 'N/A', 120);
                doc.text(programmeLines, 70, yPos);
                yPos += lineHeight * programmeLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Minor:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.minor || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('CGPA:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text((sub.cgpa ? sub.cgpa.toString() : 'N/A'), 70, yPos);
                yPos += lineHeight + 5;
                
                // Line separator
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;
                
                // Project Information Section
                doc.setFont(undefined, 'bold');
                var projectInfoWidth = doc.getTextWidth('Project Information');
                doc.text('Project Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + projectInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Current Title:', 20, yPos);
                doc.setFont(undefined, 'normal');
                // Use the current title that comes from fyp_project.Project_Title via backend (student.projectTitle)
                var currentTitleLines = doc.splitTextToSize(sub.currentTitle || 'N/A', 120);
                doc.text(currentTitleLines, 70, yPos);
                yPos += lineHeight * currentTitleLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Proposed Title:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var proposedTitleLines = doc.splitTextToSize(sub.proposedTitle || 'N/A', 120);
                doc.text(proposedTitleLines, 70, yPos);
                yPos += lineHeight * proposedTitleLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Status:', 20, yPos);
                doc.setFont(undefined, 'normal');
                
                // Set color based on status
                if (sub.titleStatus === 'Approved') {
                    doc.setTextColor(40, 167, 69); // Green
                } else if (sub.titleStatus === 'Rejected') {
                    doc.setTextColor(220, 53, 69); // Red
                } else {
                    doc.setTextColor(212, 175, 55); // Gold for Waiting
                }
                doc.setFont(undefined, 'bold');
                doc.text(student.statusDisplay || 'N/A', 70, yPos);
                doc.setTextColor(0, 0, 0); // Reset to black
                doc.setFont(undefined, 'normal');
                yPos += lineHeight + 5;
                
                // Line separator
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;
                
                // Supervisor Information Section
                doc.setFont(undefined, 'bold');
                var supervisorInfoWidth = doc.getTextWidth('Supervisor Information');
                doc.text('Supervisor Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + supervisorInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Supervisor Name:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.supervisorName || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                // Footer
                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                
                // Open PDF in new window
                window.open(doc.output('bloburl'), '_blank');
            };
            
            // Fallback without logo
            img.onerror = function() {
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('FYP Title Submission', 105, 20, { align: 'center' });
                
                doc.setLineWidth(0.5);
                doc.line(20, 25, 190, 25);
                
                doc.setFontSize(12);
                doc.setFont(undefined, 'normal');
                
                var yPos = 35;
                var lineHeight = 10;
                
                // Course Information
                doc.setFont(undefined, 'bold');
                var courseInfoWidth = doc.getTextWidth('Course Information');
                doc.text('Course Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + courseInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Course Code:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.courseCode || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Semester:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text((sub.fypSession || 'N/A') + ' - ' + (sub.semester || 'N/A'), 70, yPos);
                yPos += lineHeight + 5;
                
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;
                
                // Student Information
                doc.setFont(undefined, 'bold');
                var studentInfoWidth = doc.getTextWidth('Student Information');
                doc.text('Student Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + studentInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Student Name:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.studentName || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Matric No:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(student.matricNo || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Current Address:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var addressLines = doc.splitTextToSize(sub.currentAddress || 'N/A', 120);
                doc.text(addressLines, 70, yPos);
                yPos += lineHeight * addressLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Tel. No:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.telNo || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Programme:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var programmeLines = doc.splitTextToSize(sub.programme || 'N/A', 120);
                doc.text(programmeLines, 70, yPos);
                yPos += lineHeight * programmeLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Minor:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.minor || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('CGPA:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text((sub.cgpa ? sub.cgpa.toString() : 'N/A'), 70, yPos);
                yPos += lineHeight + 5;
                
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;
                
                // Project Information
                doc.setFont(undefined, 'bold');
                var projectInfoWidth = doc.getTextWidth('Project Information');
                doc.text('Project Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + projectInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Current Title:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var currentTitleLines = doc.splitTextToSize(sub.projectTitle || 'N/A', 120);
                doc.text(currentTitleLines, 70, yPos);
                yPos += lineHeight * currentTitleLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Proposed Title:', 20, yPos);
                doc.setFont(undefined, 'normal');
                var proposedTitleLines = doc.splitTextToSize(sub.proposedTitle || 'N/A', 120);
                doc.text(proposedTitleLines, 70, yPos);
                yPos += lineHeight * proposedTitleLines.length;
                
                doc.setFont(undefined, 'bold');
                doc.text('Status:', 20, yPos);
                doc.setFont(undefined, 'normal');
                
                // Set color based on status
                if (sub.titleStatus === 'Approved') {
                    doc.setTextColor(40, 167, 69);
                } else if (sub.titleStatus === 'Rejected') {
                    doc.setTextColor(220, 53, 69);
                } else {
                    doc.setTextColor(212, 175, 55);
                }
                doc.setFont(undefined, 'bold');
                doc.text(student.statusDisplay || 'N/A', 70, yPos);
                doc.setTextColor(0, 0, 0);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight + 5;
                
                doc.setLineWidth(0.5);
                doc.line(20, yPos, 190, yPos);
                yPos += 10;
                
                // Supervisor Information
                doc.setFont(undefined, 'bold');
                var supervisorInfoWidth = doc.getTextWidth('Supervisor Information');
                doc.text('Supervisor Information', 20, yPos);
                doc.setLineWidth(0.3);
                doc.line(20, yPos + 1, 20 + supervisorInfoWidth, yPos + 1);
                doc.setFont(undefined, 'normal');
                yPos += lineHeight;
                
                doc.setFont(undefined, 'bold');
                doc.text('Supervisor Name:', 20, yPos);
                doc.setFont(undefined, 'normal');
                doc.text(sub.supervisorName || 'N/A', 70, yPos);
                yPos += lineHeight;
                
                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                
                window.open(doc.output('bloburl'), '_blank');
            };
        }
        
        function oldOpenFYPFormModalCode(studentId) {
            const modal = document.getElementById('fypFormModal');
            if (!modal) return;

            const student = fypTitleSubmissionData.find(item => item.id === studentId);
            if (!student || !student.submission) {
                openModal('Error', 'Submission data not found.');
                return;
            }

            const sub = student.submission;
            modal.innerHTML = `
                <div class="modal-dialog fyp-form-dialog">
                    <div class="fyp-form-container">
                        <span class="close-btn" onclick="closeFYPFormModal()">&times;</span>
                        <div class="fyp-form-header-box">
                            <div class="fyp-form-header">
                                <div class="fyp-form-header-left">
                                    <div class="upm-logo-placeholder">UPM</div>
                                    <div class="upm-name">UNIVERSITI PUTRA MALAYSIA</div>
                                    <div class="upm-motto">BERILMU BERBAKTI</div>
                                </div>
                                <div class="fyp-form-header-right">
                                    <div>PERKHIDMATAN UTAMA</div>
                                    <div>PRASISWAZAH</div>
                                    <div>PEJABAT TIMBALAN NAIB CANSELOR</div>
                                    <div>(AKADEMIK & ANTARABANGSA)</div>
                                    <div>Kod Dokumen: PU/PS/BR06/AJR</div>
                                </div>
                            </div>
                            <div class="fyp-form-title-box">
                                <div class="fyp-form-title">BACHELOR DISSERTATION/PROJECT REGISTRATION FORM</div>
                                <div class="fyp-form-semester">(SEMESTER ${sub.semester} SESSION 2024/2025)</div>
                            </div>
                        </div>
                        <div class="fyp-form-content">
                            <div class="fyp-form-section">
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Course Title:</span>
                                    <span class="fyp-form-line">${sub.courseTitle}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Course Code:</span>
                                    <span class="fyp-form-line">${sub.courseCode}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Credit Hour:</span>
                                    <span class="fyp-form-line">${sub.creditHour}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Semester:</span>
                                    <span class="fyp-form-line">${sub.semester}</span>
                                </div>
                            </div>
                            <div class="fyp-form-divider"></div>
                            <div class="fyp-form-section">
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Student's Name:</span>
                                    <span class="fyp-form-line">${sub.studentName}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Matric No.:</span>
                                    <span class="fyp-form-line">${student.matricNo}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Current Address:</span>
                                    <span class="fyp-form-line">${sub.currentAddress}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Tel. No.:</span>
                                    <span class="fyp-form-line">${sub.telNo}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Programme:</span>
                                    <span class="fyp-form-line">${sub.programme}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Minor (If Related):</span>
                                    <span class="fyp-form-line">${sub.minor}</span>
                                </div>
                            </div>
                            <div class="fyp-form-divider"></div>
                            <div class="fyp-form-section">
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">CGPA:</span>
                                    <span class="fyp-form-line">${sub.cgpa}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Dissertation/Project Title:</span>
                                    <span class="fyp-form-line">${sub.dissertationTitle}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">First Choice:</span>
                                    <span class="fyp-form-line">${sub.firstChoice}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Second Choice:</span>
                                    <span class="fyp-form-line">${sub.secondChoice}</span>
                                </div>
                                <div class="fyp-form-field">
                                    <span class="fyp-form-label">Third Choice:</span>
                                    <span class="fyp-form-line">${sub.thirdChoice}</span>
                                </div>
                            </div>
                            <div class="fyp-form-divider"></div>
                            <div class="fyp-form-section">
                                <div class="fyp-form-signature-row">
                                    <div class="fyp-form-signature-field">
                                        <span class="fyp-form-label">Student' Signature:</span>
                                        <span class="fyp-form-line"></span>
                                    </div>
                                    <div class="fyp-form-signature-field">
                                        <span class="fyp-form-label">Date:</span>
                                        <span class="fyp-form-line"></span>
                                    </div>
                                </div>
                                <div class="fyp-form-signature-row">
                                    <div class="fyp-form-signature-field">
                                        <span class="fyp-form-label">Lecturer's/SV's Signature:</span>
                                        <span class="fyp-form-line"></span>
                                    </div>
                                    <div class="fyp-form-signature-field">
                                        <span class="fyp-form-label">Coordinator's Signature:</span>
                                        <span class="fyp-form-line"></span>
                                    </div>
                                </div>
                                <div class="fyp-form-signature-row">
                                    <div class="fyp-form-signature-field">
                                        <span class="fyp-form-label">Lecturer's Name:</span>
                                        <span class="fyp-form-line"></span>
                                    </div>
                                    <div class="fyp-form-signature-field">
                                        <span class="fyp-form-label">Date:</span>
                                        <span class="fyp-form-line"></span>
                                    </div>
                                </div>
                                <div class="fyp-form-signature-field">
                                    <span class="fyp-form-label">Date:</span>
                                    <span class="fyp-form-line"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            modal.style.display = 'flex';
        }

        function closeFYPFormModal() {
            const modal = document.getElementById('fypFormModal');
            if (modal) {
                modal.style.display = 'none';
                modal.innerHTML = '';
            }
        }

        // Helper function to generate PDF as blob (for ZIP creation)
        function generatePDFBlob(student) {
            return new Promise((resolve, reject) => {
                if (!student || !student.submission) {
                    reject(new Error('Student or submission data not found.'));
                    return;
                }
                
                generateFYPSubmissionPDFContent(student, function(doc, error) {
                    if (error) {
                        reject(new Error(error));
                        return;
                    }
                    
                    if (!doc) {
                        reject(new Error('Failed to generate PDF.'));
                        return;
                    }
                    
                    try {
                        // Get PDF as array buffer
                        const pdfBlob = doc.output('arraybuffer');
                        const safeName = (student.name || 'Unknown').replace(/[^a-zA-Z0-9_]/g, '_');
                        const safeMatric = (student.matricNo || 'Unknown').replace(/[^a-zA-Z0-9_]/g, '_');
                        const filename = `FYP_Registration_${safeMatric}_${safeName}.pdf`;
                        
                        resolve({ blob: pdfBlob, filename: filename });
                    } catch (err) {
                        reject(err);
                    }
                });
            });
        }

        function downloadAllFYPSubmissionsPDF() {
            // Use filtered data if available (respects current search/filter settings), otherwise use all data
            const dataToUse = filteredFYPData && filteredFYPData.length > 0 ? filteredFYPData : fypTitleSubmissionData;
            const studentsWithSubmissions = dataToUse.filter(item => item && item.submission && item.submission !== null && item.id);
            
            if (studentsWithSubmissions.length === 0) {
                openModal('Download Failed', 'No submissions available to download.');
                return;
            }

            // Show initial modal with progress
            const modal = document.getElementById('notifyModal');
            if (modal) {
                modal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" onclick="closeModal()">&times;</span>
                            <div class="modal-icon"><i class="bi bi-download"></i></div>
                            <div class="modal-title-custom">Generating ZIP File</div>
                            <div class="modal-message">Generating ${studentsWithSubmissions.length} PDF file(s) and creating ZIP archive. Please wait...</div>
                            <div style="display:flex; justify-content:center;">
                                <button class="btn btn-success" onclick="closeModal()" style="display:none;" id="closeModalBtn">OK</button>
                            </div>
                        </div>
                    </div>`;
                modal.style.display = 'flex';
            }

            // Generate all PDFs and create ZIP file
            const pdfPromises = studentsWithSubmissions.map(student => {
                // Ensure student can be found in the full data
                const studentToDownload = fypTitleSubmissionData.find(item => String(item.id) === String(student.id)) || student;
                return generatePDFBlob(studentToDownload).catch(error => {
                    console.error(`Error generating PDF for student ${student.id}:`, error);
                    return null; // Return null for failed PDFs
                });
            });

            // Wait for all PDFs to be generated
            Promise.all(pdfPromises).then(results => {
                try {
                    // Filter out failed PDFs (null values)
                    const successfulPDFs = results.filter(result => result !== null);
                    
                    if (successfulPDFs.length === 0) {
                        closeModal();
                        openModal('Download Failed', 'No PDFs could be generated. Please check the console for errors.');
                        return;
                    }

                    // Create ZIP file
                    const zip = new JSZip();
                    
                    successfulPDFs.forEach(({ blob, filename }) => {
                        zip.file(filename, blob);
                    });

                    // Generate ZIP file and download
                    zip.generateAsync({ type: 'blob' }).then(function(content) {
                        // Create download link
                        const url = window.URL.createObjectURL(content);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = `FYP_Submissions_${new Date().toISOString().split('T')[0]}.zip`;
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        window.URL.revokeObjectURL(url);

                        // Close modal and show success
                        closeModal();
                        const errorCount = studentsWithSubmissions.length - successfulPDFs.length;
                        openModal('Download Complete', 
                            `Successfully downloaded ZIP file containing ${successfulPDFs.length} PDF file(s).${errorCount > 0 ? ` ${errorCount} file(s) could not be included.` : ''}`);
                    }).catch(function(error) {
                        console.error('Error creating ZIP file:', error);
                        closeModal();
                        openModal('Download Failed', 'An error occurred while creating the ZIP file. Please try again.');
                    });
                } catch (error) {
                    console.error('Error processing PDFs:', error);
                    closeModal();
                    openModal('Download Failed', 'An error occurred while processing PDFs. Please try again.');
                }
            }).catch(error => {
                console.error('Error generating PDFs:', error);
                closeModal();
                openModal('Download Failed', 'An error occurred while generating PDFs. Please try again.');
            });
        }

        function createFYPFormPDFDoc(student) {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'pt', 'a4');
            const sub = student.submission;
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            let yPos = 40;
            const margin = 40;
            const lineHeight = 20;
            const sectionSpacing = 15;

            // Set font
            doc.setFont('helvetica');
            doc.setFontSize(10);

            // Header Box
            doc.setDrawColor(0, 0, 0);
            doc.setLineWidth(1);
            doc.rect(margin, yPos, pageWidth - (margin * 2), 120);

            // UPM Logo placeholder (circle)
            doc.setFillColor(120, 0, 0);
            doc.circle(margin + 50, yPos + 50, 25, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(12);
            doc.setFont('helvetica', 'bold');
            doc.text('UPM', margin + 50, yPos + 55, { align: 'center' });

            // University name and motto
            doc.setTextColor(0, 0, 0);
            doc.setFontSize(9);
            doc.setFont('helvetica', 'bold');
            doc.text('UNIVERSITI PUTRA MALAYSIA', margin + 100, yPos + 30);
            doc.setFont('helvetica', 'italic');
            doc.setFontSize(8);
            doc.text('BERILMU BERBAKTI', margin + 100, yPos + 45);

            // Right side header
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            const rightText = [
                'PERKHIDMATAN UTAMA',
                'PRASISWAZAH',
                'PEJABAT TIMBALAN NAIB CANSELOR',
                '(AKADEMIK & ANTARABANGSA)',
                'Kod Dokumen: PU/PS/BR06/AJR'
            ];
            let rightY = yPos + 20;
            rightText.forEach(text => {
                doc.text(text, pageWidth - margin - 150, rightY, { align: 'center' });
                rightY += 15;
            });

            // Form Title
            yPos += 130;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(12);
            const titleText = 'BACHELOR DISSERTATION/PROJECT REGISTRATION FORM';
            const titleWidth = doc.getTextWidth(titleText);
            doc.text(titleText, (pageWidth - titleWidth) / 2, yPos);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            const semesterText = `(SEMESTER ${sub.semester} SESSION ${sub.fypSession})`;
            const semesterWidth = doc.getTextWidth(semesterText);
            doc.text(semesterText, (pageWidth - semesterWidth) / 2, yPos + 15);

            yPos += 35;

            // Helper function to draw form field
            function drawFormField(label, value, y) {
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(9);
                doc.text(label + ':', margin, y);
                const labelWidth = doc.getTextWidth(label + ':');
                const lineStart = margin + labelWidth + 10;
                const lineEnd = pageWidth - margin;
                doc.setLineWidth(0.5);
                doc.line(lineStart, y + 3, lineEnd, y + 3);
                if (value) {
                    doc.setFont('helvetica', 'normal');
                    doc.text(value, lineStart + 5, y);
                }
            }

            // Course Information
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            var courseInfoWidth = doc.getTextWidth('Course Information');
            doc.text('Course Information', margin, yPos);
            doc.setLineWidth(0.3);
            doc.line(margin, yPos + 2, margin + courseInfoWidth, yPos + 2);
            yPos += lineHeight;
            doc.setFontSize(9);
            drawFormField('Course Title', sub.courseTitle, yPos);
            yPos += lineHeight;
            drawFormField('Course Code', sub.courseCode, yPos);
            yPos += lineHeight;
            drawFormField('Credit Hour', sub.creditHour, yPos);
            yPos += lineHeight;
            drawFormField('Semester', sub.semester, yPos);
            yPos += sectionSpacing;

            // Divider
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += sectionSpacing;

            // Student Information
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            var studentInfoWidth = doc.getTextWidth('Student Information');
            doc.text('Student Information', margin, yPos);
            doc.setLineWidth(0.3);
            doc.line(margin, yPos + 2, margin + studentInfoWidth, yPos + 2);
            yPos += lineHeight;
            doc.setFontSize(9);
            drawFormField('Student\'s Name', sub.studentName, yPos);
            yPos += lineHeight;
            drawFormField('Matric No.', student.matricNo, yPos);
            yPos += lineHeight;
            drawFormField('Current Address', sub.currentAddress, yPos);
            yPos += lineHeight;
            drawFormField('Tel. No.', sub.telNo, yPos);
            yPos += lineHeight;
            drawFormField('Programme', sub.programme, yPos);
            yPos += lineHeight;
            drawFormField('Minor (If Related)', sub.minor, yPos);
            yPos += lineHeight;
            drawFormField('CGPA', sub.cgpa, yPos);
            yPos += sectionSpacing;

            // Divider
            doc.line(margin, yPos, pageWidth - margin, yPos);
            yPos += sectionSpacing;

            // Project Information
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            var projectInfoWidth = doc.getTextWidth('Project Information');
            doc.text('Project Information', margin, yPos);
            doc.setLineWidth(0.3);
            doc.line(margin, yPos + 2, margin + projectInfoWidth, yPos + 2);
            yPos += lineHeight;
            doc.setFontSize(9);
            drawFormField('Current Title', sub.currentTitle, yPos);
            yPos += lineHeight;
            drawFormField('Proposed Title', sub.proposedTitle, yPos);
            yPos += lineHeight;
            drawFormField('Status of Proposed Title', sub.titleStatus, yPos);
            yPos += lineHeight;
            drawFormField('Supervisor Name', sub.supervisorName, yPos);

            return doc;
        }

        function viewFYPFormPDF(studentId) {
            const student = fypTitleSubmissionData.find(item => String(item.id) === String(studentId));
            if (!student || !student.submission) {
                openModal('Error', 'Submission data not found.');
                return;
            }
            const doc = createFYPFormPDFDoc(student);
            const pdfUrl = doc.output('bloburl');
            window.open(pdfUrl, '_blank');
        }

        // Helper function to generate PDF content (same style as view submission)
        function generateFYPSubmissionPDFContent(student, callback) {
            if (!student || !student.submission) {
                if (callback) callback(null, 'Student or submission data not found.');
                return;
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            const sub = student.submission;
            
            // Load and add UPM logo
            var img = new Image();
            img.src = '../../../assets/UPMLogo.png';
            
            img.onload = function() {
                try {
                    // Add logo centered at top
                    var logoWidth = 30;
                    var logoHeight = 20;
                    var pageWidth = 210;
                    var xPos = (pageWidth - logoWidth) / 2;
                    doc.addImage(img, 'PNG', xPos, 10, logoWidth, logoHeight);
                    
                    // Title
                    doc.setFontSize(18);
                    doc.setFont(undefined, 'bold');
                    doc.text('FYP Title Submission', 105, 35, { align: 'center' });
                    
                    // Line separator
                    doc.setLineWidth(0.5);
                    doc.line(20, 40, 190, 40);
                    
                    // Content
                    doc.setFontSize(12);
                    doc.setFont(undefined, 'normal');
                    
                    var yPos = 50;
                    var lineHeight = 10;
                    
                    // Course Information Section
                    doc.setFont(undefined, 'bold');
                    var courseInfoWidth = doc.getTextWidth('Course Information');
                    doc.text('Course Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + courseInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Course Code:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.courseCode || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Semester:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text((sub.fypSession || 'N/A') + ' - ' + (sub.semester || 'N/A'), 70, yPos);
                    yPos += lineHeight + 5;
                    
                    // Line separator
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;
                    
                    // Student Information Section
                    doc.setFont(undefined, 'bold');
                    var studentInfoWidth = doc.getTextWidth('Student Information');
                    doc.text('Student Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + studentInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Student Name:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    // Handle long names and special characters properly
                    var studentNameLines = doc.splitTextToSize(sub.studentName || 'N/A', 120);
                    doc.text(studentNameLines, 70, yPos);
                    yPos += lineHeight * studentNameLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Matric No:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(student.matricNo || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Current Address:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var addressLines = doc.splitTextToSize(sub.currentAddress || 'N/A', 120);
                    doc.text(addressLines, 70, yPos);
                    yPos += lineHeight * addressLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Tel. No:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.telNo || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Programme:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var programmeLines = doc.splitTextToSize(sub.programme || 'N/A', 120);
                    doc.text(programmeLines, 70, yPos);
                    yPos += lineHeight * programmeLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Minor:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.minor || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('CGPA:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text((sub.cgpa ? sub.cgpa.toString() : 'N/A'), 70, yPos);
                    yPos += lineHeight + 5;
                    
                    // Line separator
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;
                    
                    // Project Information Section
                    doc.setFont(undefined, 'bold');
                    var projectInfoWidth = doc.getTextWidth('Project Information');
                    doc.text('Project Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + projectInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Current Title:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var currentTitleLines = doc.splitTextToSize(sub.currentTitle || 'N/A', 120);
                    doc.text(currentTitleLines, 70, yPos);
                    yPos += lineHeight * currentTitleLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Proposed Title:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var proposedTitleLines = doc.splitTextToSize(sub.proposedTitle || 'N/A', 120);
                    doc.text(proposedTitleLines, 70, yPos);
                    yPos += lineHeight * proposedTitleLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Status:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    
                    // Set color based on status
                    if (sub.titleStatus === 'Approved') {
                        doc.setTextColor(40, 167, 69); // Green
                    } else if (sub.titleStatus === 'Rejected') {
                        doc.setTextColor(220, 53, 69); // Red
                    } else {
                        doc.setTextColor(212, 175, 55); // Gold for Waiting
                    }
                    doc.setFont(undefined, 'bold');
                    doc.text(student.statusDisplay || 'N/A', 70, yPos);
                    doc.setTextColor(0, 0, 0); // Reset to black
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight + 5;
                    
                    // Line separator
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;
                    
                    // Supervisor Information Section
                    doc.setFont(undefined, 'bold');
                    var supervisorInfoWidth = doc.getTextWidth('Supervisor Information');
                    doc.text('Supervisor Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + supervisorInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Supervisor Name:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.supervisorName || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    // Footer
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'normal');
                    doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                    doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                    
                    if (callback) callback(doc, null);
                } catch (error) {
                    console.error('Error generating PDF content:', error);
                    if (callback) callback(null, error.message || 'Failed to generate PDF content.');
                }
            };
            
            // Fallback without logo if image fails to load
            img.onerror = function() {
                try {
                    doc.setFontSize(18);
                    doc.setFont(undefined, 'bold');
                    doc.text('FYP Title Submission', 105, 20, { align: 'center' });
                    
                    doc.setLineWidth(0.5);
                    doc.line(20, 25, 190, 25);
                    
                    doc.setFontSize(12);
                    doc.setFont(undefined, 'normal');
                    
                    var yPos = 35;
                    var lineHeight = 10;
                    
                    // Course Information
                    doc.setFont(undefined, 'bold');
                    var courseInfoWidth = doc.getTextWidth('Course Information');
                    doc.text('Course Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + courseInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Course Code:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.courseCode || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Semester:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text((sub.fypSession || 'N/A') + ' - ' + (sub.semester || 'N/A'), 70, yPos);
                    yPos += lineHeight + 5;
                    
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;
                    
                    // Student Information
                    doc.setFont(undefined, 'bold');
                    var studentInfoWidth = doc.getTextWidth('Student Information');
                    doc.text('Student Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + studentInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Student Name:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    // Handle long names and special characters properly
                    var studentNameLines = doc.splitTextToSize(sub.studentName || 'N/A', 120);
                    doc.text(studentNameLines, 70, yPos);
                    yPos += lineHeight * studentNameLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Matric No:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(student.matricNo || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Current Address:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var addressLines = doc.splitTextToSize(sub.currentAddress || 'N/A', 120);
                    doc.text(addressLines, 70, yPos);
                    yPos += lineHeight * addressLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Tel. No:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.telNo || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Programme:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var programmeLines = doc.splitTextToSize(sub.programme || 'N/A', 120);
                    doc.text(programmeLines, 70, yPos);
                    yPos += lineHeight * programmeLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Minor:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.minor || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('CGPA:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text((sub.cgpa ? sub.cgpa.toString() : 'N/A'), 70, yPos);
                    yPos += lineHeight + 5;
                    
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;
                    
                    // Project Information
                    doc.setFont(undefined, 'bold');
                    var projectInfoWidth = doc.getTextWidth('Project Information');
                    doc.text('Project Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + projectInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Current Title:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var currentTitleLines = doc.splitTextToSize(sub.currentTitle || 'N/A', 120);
                    doc.text(currentTitleLines, 70, yPos);
                    yPos += lineHeight * currentTitleLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Proposed Title:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var proposedTitleLines = doc.splitTextToSize(sub.proposedTitle || 'N/A', 120);
                    doc.text(proposedTitleLines, 70, yPos);
                    yPos += lineHeight * proposedTitleLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Status:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    
                    // Set color based on status
                    if (sub.titleStatus === 'Approved') {
                        doc.setTextColor(40, 167, 69);
                    } else if (sub.titleStatus === 'Rejected') {
                        doc.setTextColor(220, 53, 69);
                    } else {
                        doc.setTextColor(212, 175, 55);
                    }
                    doc.setFont(undefined, 'bold');
                    doc.text(student.statusDisplay || 'N/A', 70, yPos);
                    doc.setTextColor(0, 0, 0);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight + 5;
                    
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;
                    
                    // Supervisor Information
                    doc.setFont(undefined, 'bold');
                    var supervisorInfoWidth = doc.getTextWidth('Supervisor Information');
                    doc.text('Supervisor Information', 20, yPos);
                    doc.setLineWidth(0.3);
                    doc.line(20, yPos + 1, 20 + supervisorInfoWidth, yPos + 1);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Supervisor Name:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(sub.supervisorName || 'N/A', 70, yPos);
                    yPos += lineHeight;
                    
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'normal');
                    doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                    doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                    
                    if (callback) callback(doc, null);
                } catch (error) {
                    console.error('Error generating PDF (fallback):', error);
                    if (callback) callback(null, error.message || 'Failed to generate PDF.');
                }
            };
        }
        
        function downloadFYPFormPDF(studentId) {
            // Convert studentId to string for consistent comparison
            const studentIdStr = String(studentId);
            
            // Search in filtered data first (current view), then in full data
            let student = null;
            if (filteredFYPData && filteredFYPData.length > 0) {
                student = filteredFYPData.find(item => String(item.id) === studentIdStr);
            }
            
            // If not found in filtered data, search in full data
            if (!student) {
                student = fypTitleSubmissionData.find(item => String(item.id) === studentIdStr);
            }
            
            if (!student) {
                openModal('Error', `Student with ID ${studentId} not found.`);
                console.error('Student not found:', studentId);
                return;
            }
            
            if (!student.submission) {
                openModal('Error', `No submission data available for student ${student.name || studentId}.`);
                console.warn('No submission for student:', student);
                return;
            }
            
            // Use the same PDF generation as view submission
            generateFYPSubmissionPDFContent(student, function(doc, error) {
                if (error) {
                    console.error('Error generating PDF:', error);
                    openModal('Error', `Failed to generate PDF for student ${student.name || studentId}. ${error}`);
                    return;
                }
                
                if (!doc) {
                    openModal('Error', `Failed to generate PDF for student ${student.name || studentId}.`);
                    return;
                }
                
                try {
                    const safeName = (student.name || 'Unknown').replace(/[^a-zA-Z0-9_]/g, '_');
                    const safeMatric = (student.matricNo || 'Unknown').replace(/[^a-zA-Z0-9_]/g, '_');
                    const filename = `FYP_Registration_${safeMatric}_${safeName}.pdf`;
                    doc.save(filename);
                } catch (saveError) {
                    console.error('Error saving PDF:', saveError);
                    openModal('Error', `Failed to save PDF for student ${student.name || studentId}. Please try again.`);
                }
            });
        }

        function saveMarks(tabName) {
            console.log('Saving marks for', tabName);
        }

        function resetMarks(tabName) {
            console.log('Resetting marks for', tabName);
            renderTable(tabName);
        }

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
            function setActiveMenuItem(menuItemId) {
                const coordinatorMenuItems = document.querySelectorAll('#coordinatorMenu a');
                coordinatorMenuItems.forEach(item => {
                    item.classList.remove('active-menu-item');
                });

                const activeItem = document.querySelector(`#${menuItemId}`);
                if (activeItem) {
                    activeItem.classList.add('active-menu-item');
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

            setActiveMenuItem('markSubmission');

            const roleHeaders = document.querySelectorAll('.role-header');
            roleHeaders.forEach(header => {
                header.addEventListener('click', function (e) {
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

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.download-dropdown')) {
                closeAllDropdowns();
            }
        });
    </script>
</body>
</html>

