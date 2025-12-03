<?php include '../../../php/coordinator_bootstrap.php'; ?>
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
                <a href="../studentAssignation/studentAssignation.php" id="studentAssignation"><i class="bi bi-people-fill icon-padding"></i> Student Assignation</a>
                <a href="../learningObjective/learningObjective.php" id="learningObjective"><i class="bi bi-book-fill icon-padding"></i> Learning Objective</a>
                <a href="markSubmission.php" id="markSubmission" class="active-menu-item"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Signature Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Date & Time Allocation</a>
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
                <button class="task-tab" data-tab="swe4949a">SWE4949-A</button>
                <button class="task-tab" data-tab="swe4949b">SWE4949-B</button>
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
                                    <button id="downloadButtonPdfFYP" class="btn-download btn-download-pdf" onclick="toggleDownloadDropdown('pdf', 'fyp', this)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        <span>Download as PDF</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownPdfFYP">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('fyp-title-submission', 'submissions'); closeDownloadDropdown('pdf', 'fyp');" class="download-option pdf-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download submissions</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('fyp-title-submission', 'submissions-comments'); closeDownloadDropdown('pdf', 'fyp');" class="download-option pdf-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF with comments</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button id="downloadButtonExcelFYP" class="btn-download btn-download-excel" onclick="toggleDownloadDropdown('excel', 'fyp', this)">
                                        <i class="bi bi-file-earmark-excel"></i>
                                        <span>Download as Excel</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownExcelFYP">
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('fyp-title-submission', 'submissions'); closeDownloadDropdown('excel', 'fyp');" class="download-option excel-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download submissions</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('fyp-title-submission', 'submissions-comments'); closeDownloadDropdown('excel', 'fyp');" class="download-option excel-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel with comments</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

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
                            </div>
                            <div class="download-actions">
                                <div class="download-dropdown">
                                    <button id="downloadButtonPdfA" class="btn-download btn-download-pdf" onclick="toggleDownloadDropdown('pdf', 'a', this)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        <span>Download as PDF</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownPdfA">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949a', 'marks'); closeDownloadDropdown('pdf', 'a');" class="download-option pdf-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download marks</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949a', 'marks-comments'); closeDownloadDropdown('pdf', 'a');" class="download-option pdf-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download marks with comments</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button id="downloadButtonExcelA" class="btn-download btn-download-excel" onclick="toggleDownloadDropdown('excel', 'a', this)">
                                        <i class="bi bi-file-earmark-excel"></i>
                                        <span>Download as Excel</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownExcelA">
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949a', 'marks'); closeDownloadDropdown('excel', 'a');" class="download-option excel-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download marks</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949a', 'marks-comments'); closeDownloadDropdown('excel', 'a');" class="download-option excel-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download marks with comments</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button id="downloadButtonNotifyA" class="btn-download btn-notify-group" onclick="toggleDownloadDropdown('notify', 'a', this)">
                                        <i class="bi bi-bell"></i>
                                        <span>Notify</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownNotifyA">
                                        <a href="javascript:void(0)" onclick="notifyGroup('swe4949a', 'assessors'); closeDownloadDropdown('notify', 'a');" class="download-option notify-option">
                                            <i class="bi bi-bell"></i>
                                            <span>Notify all assessors</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="notifyGroup('swe4949a', 'supervisors'); closeDownloadDropdown('notify', 'a');" class="download-option notify-option">
                                            <i class="bi bi-bell"></i>
                                            <span>Notify all supervisors</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="notifyGroup('swe4949a', 'both'); closeDownloadDropdown('notify', 'a');" class="download-option notify-option">
                                            <i class="bi bi-bell"></i>
                                            <span>Notify all roles</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-scroll-container">
                            <table class="mark-submission-table" id="markTableA">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Matric No.</th>
                                        <th>Name</th>
                                        <th>FYP Title</th>
                                        <th>Proposal Report(10%)</th>
                                        <th>Proposal Seminar Presentation(10%)</th>
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
                            </div>
                            <div class="download-actions">
                                <div class="download-dropdown">
                                    <button id="downloadButtonPdfB" class="btn-download btn-download-pdf" onclick="toggleDownloadDropdown('pdf', 'b', this)">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                        <span>Download as PDF</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownPdfB">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949b', 'marks'); closeDownloadDropdown('pdf', 'b');" class="download-option pdf-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download marks</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949b', 'marks-comments'); closeDownloadDropdown('pdf', 'b');" class="download-option pdf-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download marks with comments</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button id="downloadButtonExcelB" class="btn-download btn-download-excel" onclick="toggleDownloadDropdown('excel', 'b', this)">
                                        <i class="bi bi-file-earmark-excel"></i>
                                        <span>Download as Excel</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownExcelB">
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949b', 'marks'); closeDownloadDropdown('excel', 'b');" class="download-option excel-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download marks</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949b', 'marks-comments'); closeDownloadDropdown('excel', 'b');" class="download-option excel-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download marks with comments</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button id="downloadButtonNotifyB" class="btn-download btn-notify-group" onclick="toggleDownloadDropdown('notify', 'b', this)">
                                        <i class="bi bi-bell"></i>
                                        <span>Notify</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownNotifyB">
                                        <a href="javascript:void(0)" onclick="notifyGroup('swe4949b', 'assessors'); closeDownloadDropdown('notify', 'b');" class="download-option notify-option">
                                            <i class="bi bi-bell"></i>
                                            <span>Notify all assessors</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="notifyGroup('swe4949b', 'supervisors'); closeDownloadDropdown('notify', 'b');" class="download-option notify-option">
                                            <i class="bi bi-bell"></i>
                                            <span>Notify all supervisors</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="notifyGroup('swe4949b', 'both'); closeDownloadDropdown('notify', 'b');" class="download-option notify-option">
                                            <i class="bi bi-bell"></i>
                                            <span>Notify all roles</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-scroll-container">
                            <table class="mark-submission-table" id="markTableB">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Matric No.</th>
                                        <th>Name</th>
                                        <th>FYP Title</th>
                                        <th>Proposal Report(10%)</th>
                                        <th>Proposal Seminar Presentation(10%)</th>
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
                    supervisorName: student.supervisorName || '-'
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

        const marksData = {
            swe4949a: [
                { id: 1, matricNo: '214673', name: 'Aisyah Nur', fypTitle: 'QR Attendance System', proposalReport: 'N/A', proposalSeminar: '8.5', comments: 'Awaiting final submission' },
                { id: 2, matricNo: '214692', name: 'Hakim Firdaus', fypTitle: 'IoT Smart Farming', proposalReport: '7.1', proposalSeminar: '7.0', comments: 'Needs improvement on seminar' },
                { id: 3, matricNo: '214726', name: 'Nabila Zahra', fypTitle: 'E-Wallet App', proposalReport: '9.1', proposalSeminar: '8.1', comments: 'Good progress' },
                { id: 4, matricNo: '214673', name: 'Faris Iman', fypTitle: 'Smart Energy Monitor', proposalReport: '8.5', proposalSeminar: 'N/A', comments: 'Seminar pending' },
                { id: 5, matricNo: '214692', name: 'Siti Hajar', fypTitle: 'Food Waste App', proposalReport: 'N/A', proposalSeminar: '8.5', comments: 'Report pending' },
                { id: 6, matricNo: '214726', name: 'Amirul Danish', fypTitle: 'Gamified E-Learning', proposalReport: '7.1', proposalSeminar: '7.0', comments: 'Requires revision' },
                { id: 7, matricNo: '214673', name: 'Nurul Aina', fypTitle: 'AI Student Chatbot', proposalReport: '9.1', proposalSeminar: '8.1', comments: 'Excellent performance' },
                { id: 8, matricNo: '214692', name: 'Hafiz Rahman', fypTitle: 'Smart Parking System', proposalReport: '8.5', proposalSeminar: 'N/A', comments: 'Awaiting seminar' },
                { id: 9, matricNo: '214726', name: 'Balqis Syafiqah', fypTitle: 'Mental Health Tracker', proposalReport: '8.5', proposalSeminar: '8.1', comments: 'On track' }
            ],
            swe4949b: [
                { id: 1, matricNo: '214673', name: 'Aisyah Nur', fypTitle: 'QR Attendance System', proposalReport: 'N/A', proposalSeminar: '8.5', comments: 'Awaiting final submission' },
                { id: 2, matricNo: '214692', name: 'Hakim Firdaus', fypTitle: 'IoT Smart Farming', proposalReport: '7.1', proposalSeminar: '7.0', comments: 'Needs improvement on seminar' }
            ]
        };

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

        const currentView = {
            swe4949a: 'student-overview',
            swe4949b: 'student-overview'
        };

        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            populateCourseFilter();
            initializeFYPSearch();
            renderFYPTable();
            renderTable('swe4949a');
            renderTable('swe4949b');
            initializeRoleToggle();
            closeNav();

            // Hide notify buttons initially (student-overview is default)
            ['A', 'B'].forEach(suffix => {
                const notifyButton = document.getElementById(`downloadButtonNotify${suffix}`);
                const notifyContainer = notifyButton ? notifyButton.closest('.download-dropdown') : null;
                if (notifyContainer) {
                    notifyContainer.style.display = 'none';
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
        });

        function populateCourseFilter() {
            const courseFilter = document.getElementById('fypCourseFilter');
            if (!courseFilter) return;

            // Get unique FYP Session IDs from the data
            const uniqueSessions = [...new Set(fypTitleSubmissionData.map(s => s.fypSessionId))];
            
            // Clear existing options except "All Courses"
            courseFilter.innerHTML = '<option value="all">All Courses</option>';
            
            // Add option for each unique session
            uniqueSessions.forEach(sessionId => {
                if (sessionId) {
                    const option = document.createElement('option');
                    option.value = sessionId;
                    option.textContent = `Course ID: ${sessionId}`;
                    courseFilter.appendChild(option);
                }
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
                    // Pass ID as string to avoid type mismatch issues
                    downloadHTML = `<button class="btn-download-table" onclick="downloadFYPFormPDF('${item.id}')" title="Download as PDF">
                        <i class="bi bi-download"></i> Download
                    </button>`;
                } else {
                    downloadHTML = '<span class="no-submission">-</span>';
                }

                row.innerHTML = `
                    <td>${index + 1}.</td>
                    <td>${item.matricNo}</td>
                    <td>${item.name}</td>
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

        function changeView(tabName, viewType) {
            currentView[tabName] = viewType;
            renderTable(tabName);
            closeAllDropdowns();
            
            // Show/hide notify button based on view
            const tabSuffix = tabName.charAt(tabName.length - 1).toUpperCase();
            const notifyButton = document.getElementById(`downloadButtonNotify${tabSuffix}`);
            const notifyDropdown = document.getElementById(`downloadDropdownNotify${tabSuffix}`);
            const notifyContainer = notifyButton ? notifyButton.closest('.download-dropdown') : null;
            
            if (viewType === 'student-overview') {
                // Hide notify button in student marks overview
                if (notifyContainer) {
                    notifyContainer.style.display = 'none';
                }
            } else {
                // Show notify button in lecturer progress view
                if (notifyContainer) {
                    notifyContainer.style.display = 'block';
                }
            }
        }

        function formatStatus(status) {
            const normalized = (status || '').toLowerCase() === 'completed';
            return {
                text: normalized ? 'Completed' : 'Incomplete',
                className: normalized ? 'status-completed' : 'status-incomplete'
            };
        }

        function renderTable(tabName) {
            const tableIdSuffix = tabName.charAt(tabName.length - 1).toUpperCase();
            const table = document.getElementById(`markTable${tableIdSuffix}`);
            const tbody = document.getElementById(`markTableBody${tableIdSuffix}`);
            const thead = table ? table.querySelector('thead') : null;
            if (!table || !tbody || !thead) return;

            const viewType = currentView[tabName] || 'student-overview';

            if (viewType === 'lecturer-progress') {
                table.classList.add('lecturer-view');
                const data = lecturerProgressData[tabName] || [];
                thead.innerHTML = '';
                tbody.innerHTML = '';

                if (!data.length) {
                    thead.innerHTML = `
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            <th>Progress as Supervisor(%)</th>
                            <th>Progress as Assessor(%)</th>
                            <th>Notify</th>
                        </tr>
                    `;
                    tbody.innerHTML = `<tr><td colspan="5" class="empty-state">No lecturer data available</td></tr>`;
                    return;
                }

                const supervisorTaskNames = data[0].supervisorTasks?.map(task => task.task) || ['No tasks'];
                const assessorTaskNames = data[0].assessorTasks?.map(task => task.task) || ['No tasks'];

                const headerRow = document.createElement('tr');
                headerRow.innerHTML = `<th>No.</th><th>Name</th>`;
                
                supervisorTaskNames.forEach(name => {
                    const th = document.createElement('th');
                    th.textContent = `Supervisor - ${name}`;
                    headerRow.appendChild(th);
                });
                
                assessorTaskNames.forEach(name => {
                    const th = document.createElement('th');
                    th.textContent = `Assessor - ${name}`;
                    headerRow.appendChild(th);
                });
                
                const notifyTh = document.createElement('th');
                notifyTh.textContent = 'Notify';
                headerRow.appendChild(notifyTh);
                
                thead.appendChild(headerRow);

                data.forEach((item, index) => {
                    const row = document.createElement('tr');
                    let rowHTML = `<td>${index + 1}.</td><td>${item.name}</td>`;

                    const supervisorTasks = item.supervisorTasks || [];
                    supervisorTaskNames.forEach((_, taskIndex) => {
                        const task = supervisorTasks[taskIndex];
                        if (task) {
                            const statusInfo = formatStatus(task.status);
                            rowHTML += `<td><span class="status-badge ${statusInfo.className}">${statusInfo.text}</span></td>`;
                        } else {
                            rowHTML += '<td>-</td>';
                        }
                    });

                    const assessorTasks = item.assessorTasks || [];
                    assessorTaskNames.forEach((_, taskIndex) => {
                        const task = assessorTasks[taskIndex];
                        if (task) {
                            const statusInfo = formatStatus(task.status);
                            rowHTML += `<td><span class="status-badge ${statusInfo.className}">${statusInfo.text}</span></td>`;
                        } else {
                            rowHTML += '<td>-</td>';
                        }
                    });

                    rowHTML += `<td><button class="btn-notify" onclick="notifyLecturer('${tabName}', ${item.id})">Notify</button></td>`;
                    row.innerHTML = rowHTML;
                    tbody.appendChild(row);
                });
            } else {
                table.classList.remove('lecturer-view');
                const data = marksData[tabName] || [];
                thead.innerHTML = `
                    <tr>
                        <th>No.</th>
                        <th>Matric No.</th>
                        <th>Name</th>
                        <th>FYP Title</th>
                        <th>Proposal Report (10%)</th>
                        <th>Proposal Seminar Presentation (10%)</th>
                    </tr>
                `;
                tbody.innerHTML = '';

                if (!data.length) {
                    tbody.innerHTML = `<tr><td colspan="6" class="empty-state">No marks data available</td></tr>`;
                    return;
                }

                data.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}.</td>
                        <td>${item.matricNo}</td>
                        <td>${item.name}</td>
                        <td>${item.fypTitle}</td>
                        <td>${item.proposalReport}</td>
                        <td>${item.proposalSeminar}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }

        function notifyLecturer(tabName, lecturerId) {
            const data = lecturerProgressData[tabName] || [];
            const lecturer = data.find(item => item.id === lecturerId);
            const name = lecturer ? lecturer.name : 'the selected lecturer';
            closeAllDropdowns();
            openModal('Notification Sent', `Notification has been sent to ${name} for ${tabName.toUpperCase()}.`);
        }

        function notifyGroup(tabName, role) {
            let roleLabel = '';
            let message = '';
            switch (role) {
                case 'assessors':
                    roleLabel = 'all assessors';
                    message = `Notifications have been sent to all assessors for ${tabName.toUpperCase()}.`;
                    break;
                case 'supervisors':
                    roleLabel = 'all supervisors';
                    message = `Notifications have been sent to all supervisors for ${tabName.toUpperCase()}.`;
                    break;
                default:
                    roleLabel = 'all assessors and supervisors';
                    message = `Notifications have been sent to all assessors and supervisors for ${tabName.toUpperCase()}.`;
            }
            closeAllDropdowns();
            openModal('Notification Sent', message);
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.download-dropdown-menu').forEach(menu => menu.classList.remove('show'));
            document.querySelectorAll('.btn-download.active').forEach(btn => btn.classList.remove('active'));
        }

        function toggleDownloadDropdown(type, tab, button) {
            const idSuffix = `${type.charAt(0).toUpperCase() + type.slice(1)}${tab.toUpperCase()}`;
            const dropdown = document.getElementById(`downloadDropdown${idSuffix}`);
            const triggerButton = button || document.getElementById(`downloadButton${idSuffix}`);
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

        function buildLecturerExportRows(tabName) {
            const data = lecturerProgressData[tabName] || [];
            const supervisorTaskNames = (data[0]?.supervisorTasks?.length ? data[0].supervisorTasks.map(task => task.task) : ['No tasks']);
            const assessorTaskNames = (data[0]?.assessorTasks?.length ? data[0].assessorTasks.map(task => task.task) : ['No tasks']);
            const headers = ['No.', 'Name', ...supervisorTaskNames.map(name => `Supervisor - ${name}`), ...assessorTaskNames.map(name => `Assessor - ${name}`)];

            const rows = data.map((item, index) => {
                const supervisorTasks = supervisorTaskNames.map((_, idx) => {
                    const task = (item.supervisorTasks || [])[idx];
                    return task ? formatStatus(task.status).text : '-';
                });
                const assessorTasks = assessorTaskNames.map((_, idx) => {
                    const task = (item.assessorTasks || [])[idx];
                    return task ? formatStatus(task.status).text : '-';
                });
                return [index + 1, item.name, ...supervisorTasks, ...assessorTasks];
            });

            return { headers, rows };
        }

        function downloadAsPDF(tabName, type) {
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
                const exportData = buildLecturerExportRows(tabName);
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
                if (!data.length) {
                    openModal('Download Failed', 'No marks data available.');
                    return;
                }
                const includeComments = type === 'marks-comments';
                const headers = ['No.', 'Matric No.', 'Name', 'FYP Title', 'Proposal Report (10%)', 'Proposal Seminar Presentation (10%)'];
                if (includeComments) {
                    headers.push('Comments');
                }
                head = [headers];
                body = data.map((item, index) => {
                    const row = [index + 1, item.matricNo, item.name, item.fypTitle, item.proposalReport, item.proposalSeminar];
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

        function downloadAsExcel(tabName, type) {
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
                const exportData = buildLecturerExportRows(tabName);
                if (!exportData.rows.length) {
                    openModal('Download Failed', 'No lecturer progress data available.');
                    return;
                }
                headers = exportData.headers;
                rows = exportData.rows;
                filename = `lecturer-progress-${tabName}.csv`;
            } else {
                const data = marksData[tabName] || [];
                if (!data.length) {
                    openModal('Download Failed', 'No marks data available.');
                    return;
                }
                const includeComments = type === 'marks-comments';
                headers = ['No.', 'Matric No.', 'Name', 'FYP Title', 'Proposal Report (10%)', 'Proposal Seminar Presentation (10%)'];
                if (includeComments) {
                    headers.push('Comments');
                }
                rows = data.map((item, index) => {
                    const row = [index + 1, item.matricNo, item.name, item.fypTitle, item.proposalReport, item.proposalSeminar];
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

        function downloadAllFYPSubmissionsPDF() {
            const studentsWithSubmissions = fypTitleSubmissionData.filter(item => item.submission);
            
            if (studentsWithSubmissions.length === 0) {
                openModal('Download Failed', 'No submissions available to download.');
                return;
            }

            // Download each student's PDF
            studentsWithSubmissions.forEach((student, index) => {
                setTimeout(() => {
                    downloadFYPFormPDF(student.id);
                }, index * 500); // Stagger downloads by 500ms to avoid browser blocking
            });

            openModal('Download Started', `Downloading ${studentsWithSubmissions.length} PDF file(s). Please check your downloads folder.`);
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

        function downloadFYPFormPDF(studentId) {
            const student = fypTitleSubmissionData.find(item => String(item.id) === String(studentId));
            if (!student || !student.submission) {
                openModal('Error', 'Submission data not found.');
                return;
            }
            const doc = createFYPFormPDFDoc(student);
            const filename = `FYP_Registration_${student.matricNo}_${student.name.replace(/\s+/g, '_')}.pdf`;
            doc.save(filename);
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

