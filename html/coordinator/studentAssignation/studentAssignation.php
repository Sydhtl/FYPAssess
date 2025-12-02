<?php
include '../../../php/mysqlConnect.php';
session_start();

if (!isset($_SESSION['upmId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Coordinator') {
    header("Location: ../../login/Login.php");
    exit();
}

$userId = $_SESSION['upmId'];
$coordinatorName = 'Coordinator';

// Try to get name from lecturer table (most coordinators are lecturers)
if ($stmt = $conn->prepare("SELECT Lecturer_Name FROM lecturer WHERE Lecturer_ID = ? LIMIT 1")) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $coordinatorName = $row['Lecturer_Name'] ?: $coordinatorName;
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Student Assignation</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/studentAssignation.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="student-assignation-page">

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
                <a href="#" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i>
                    Industry Collaboration</a>
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
                <a href="#" id="Dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="#" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="EvaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form</a>
            </div>

            <a href="#coordinatorMenu" class="role-header active-role menu-expanded" data-role="coordinator">
                <span class="role-text">Coordinator</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-down arrow-icon"></i>
                </span>
            </a>

            <div id="coordinatorMenu" class="menu-items expanded">
                <a href="../dashboard/dashboardCoordinator.php" id="coordinatorDashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="studentAssignation.php" id="studentAssignation" class="active-menu-item"><i class="bi bi-people-fill icon-padding"></i> Student Assignation</a>
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

    <div id="main" class="main-grid student-assignation-main">
        <h1 class="page-title">Student Assignation Page</h1>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filter-group">
                <label for="yearFilter">Year</label>
                <select id="yearFilter">
                    <option value="2024/2025" selected>2024/2025</option>
                    <option value="2023/2024">2023/2024</option>
                    <option value="2025/2026">2025/2026</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="semesterFilter">Semester</label>
                <select id="semesterFilter">
                    <option value="1">1</option>
                    <option value="2" selected>2</option>
                </select>
            </div>
        </div>

        <!-- Summary Containers -->
        <div class="summary-container">
            <div class="summary-box widget">
                <span class="widget-icon"><i class="fa-solid fa-user-tie"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Total Lecturer</span>
                    <span class="widget-value" id="totalLecturer">20</span>
                </div>
            </div>
            <div class="summary-box widget">
                <span class="widget-icon"><i class="fa-solid fa-users"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Total Students</span>
                    <span class="widget-value" id="totalStudents">129</span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="evaluation-task-card">
            <div class="tab-buttons">
                <button class="task-tab active-tab" data-tab="quota">Lecturer Quota Assignation</button>
                <button class="task-tab" data-tab="distribution">Student Distribution</button>
            </div>

            <div class="task-list-area">
                <!-- Lecturer Quota Assignation Tab -->
                <div class="task-group active" data-group="quota">
                    <div class="quota-table-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Left Actions: Search -->
                            <div class="left-actions">
                                <div class="search-section">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="lecturerSearch" placeholder="Search lecturer name..." />
                                </div>
                            </div>
                            
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                        
                                <button class="btn-clear-all" onclick="clearAllQuotas()">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Clear All</span>
                                </button>
                                <button class="btn-assign" onclick="assignRemainingQuota()">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Assign Remaining Quota</span>
                                </button>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleDownloadDropdown()">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdown">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF(); closeDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel(); closeDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Table Container -->
                        <div class="table-scroll-container">
                            <table class="quota-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>Quota</th>
                                        <th>Remaining Quota</th>
                                    </tr>
                                </thead>
                                <tbody id="lecturerTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="remaining-student">
                                Remaining Student: <span id="remainingStudent">129</span>
                            </div>
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetQuotas()">Cancel</button>
                                <button class="btn btn-success" onclick="saveQuotas()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student Distribution Tab -->
                <div class="task-group" data-group="distribution">
                    <div class="quota-table-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Left Actions: Search -->
                            <div class="left-actions">
                                <div class="search-section">
                                    <i class="bi bi-search"></i>
                                    <input type="text" id="studentSearch" placeholder="Search student name..." />
                                </div>
                            </div>
                            
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-clear-all" onclick="clearAllAssignments()">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Clear All</span>
                                </button>
                                <div class="assign-dropdown">
                                    <button class="btn-assign" onclick="toggleAssignDropdown()">
                                        <i class="bi bi-arrow-repeat"></i>
                                        <span>Assign Remaining Automatically</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="assign-dropdown-menu" id="assignDropdown">
                                        <a href="javascript:void(0)" onclick="assignAutomatically('supervisor'); closeAssignDropdown();" class="assign-option">
                                            <i class="bi bi-person-check"></i>
                                            <span>Assign Supervisor Only</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="assignAutomatically('assessor'); closeAssignDropdown();" class="assign-option">
                                            <i class="bi bi-people"></i>
                                            <span>Assign Assessor Only</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="assignAutomatically('both'); closeAssignDropdown();" class="assign-option">
                                            <i class="bi bi-arrow-repeat"></i>
                                            <span>Assign Both</span>
                                        </a>
                                    </div>
                                </div>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleDistributionDownloadDropdown()">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="distributionDownloadDropdown">
                                        <a href="javascript:void(0)" onclick="downloadDistributionAsPDF(); closeDistributionDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadDistributionAsExcel(); closeDistributionDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Table Container -->
                        <div class="table-scroll-container">
                            <table class="quota-table" id="studentDistributionTable">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Name</th>
                                        <th>Supervisor</th>
                                        <th>Assessor 1</th>
                                        <th>Assessor 2</th>
                                    </tr>
                                </thead>
                                <tbody id="studentTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="remaining-student">
                                Total Students: <span id="totalStudentCount">129</span>
                            </div>
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetAssignments()">Cancel</button>
                                <button class="btn btn-success" onclick="saveAssignments()">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- SIDEBAR FUNCTIONS ---
        var collapsedWidth = "60px";

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

        // --- LECTURER QUOTA ASSIGNATION ---
        // Sample lecturer data
        // Note: quota starts at 0 and coordinator can set it manually
        // remainingQuota is calculated based on quota and actual student assignments
        const lecturers = [
            { id: 1, name: "Dr. Ahmad Faiz bin Ismail", quota: 0, remainingQuota: 0 },
            { id: 2, name: "Prof. Madya Dr. Noraini binti Hassan", quota: 0, remainingQuota: 0 },
            { id: 3, name: "Dr. Siti Nurhaliza binti Ahmad", quota: 0, remainingQuota: 0 },
            { id: 4, name: "Dr. Muhammad Hafiz bin Abdullah", quota: 0, remainingQuota: 0 },
            { id: 5, name: "Dr. Nurul Izzah binti Mohd", quota: 0, remainingQuota: 0 },
            { id: 6, name: "Dr. Azman bin Hassan", quota: 0, remainingQuota: 0 },
            { id: 7, name: "Dr. Rosnah binti Ramli", quota: 0, remainingQuota: 0 },
            { id: 8, name: "Dr. Khairul Azmi bin Ismail", quota: 0, remainingQuota: 0 },
            { id: 9, name: "Dr. Zuraida binti Ahmad", quota: 0, remainingQuota: 0 },
            { id: 10, name: "Dr. Nor Azman bin Hashim", quota: 0, remainingQuota: 0 },
            { id: 11, name: "Dr. Fadzilah binti Mohd", quota: 0, remainingQuota: 0 },
            { id: 12, name: "Dr. Hafizul Fahri bin Hanafi", quota: 0, remainingQuota: 0 },
            { id: 13, name: "Dr. Siti Aisyah binti Yusof", quota: 0, remainingQuota: 0 },
            { id: 14, name: "Dr. Mohd Azlan bin Ali", quota: 0, remainingQuota: 0 },
            { id: 15, name: "Dr. Nurul Hidayah binti Kamarudin", quota: 0, remainingQuota: 0 },
            { id: 16, name: "Dr. Ahmad Farhan bin Mohd", quota: 0, remainingQuota: 0 },
            { id: 17, name: "Dr. Siti Fatimah binti Abdul", quota: 0, remainingQuota: 0 },
            { id: 18, name: "Dr. Muhammad Haziq bin Razak", quota: 0, remainingQuota: 0 },
            { id: 19, name: "Dr. Nurul Aini binti Hashim", quota: 0, remainingQuota: 0 },
            { id: 20, name: "Dr. Azhar bin Ismail", quota: 0, remainingQuota: 0 }
        ];

        let filteredLecturers = [...lecturers];
        let totalStudents = 129;

        // --- STUDENT DISTRIBUTION ---
        // Sample student data - Generate more students for demonstration
        // In production, this would come from the backend
        const studentNames = [
            "Aiman Hakim bin Roslan", "Nurul Izzah binti Rahim", "Hafizuddin bin Karim",
            "Siti Aisyah binti Ahmad", "Muhammad Firdaus bin Ismail", "Nora Aziz binti Hassan",
            "Ahmad Zulkifli bin Abdullah", "Siti Fatimah binti Mohd", "Nurul Huda binti Rahman",
            "Muhammad Haziq bin Razak", "Ahmad Firdaus bin Hassan", "Nurul Aini binti Ahmad",
            "Hafizul Fahri bin Hanafi", "Siti Nurhaliza binti Ismail", "Muhammad Azlan bin Ali",
            "Nora Aisyah binti Mohd", "Ahmad Farhan bin Abdullah", "Nurul Izzati binti Razak",
            "Hafizuddin bin Karim", "Siti Aisyah binti Ahmad", "Muhammad Firdaus bin Ismail"
        ];
        
        const students = [];
        for (let i = 0; i < studentNames.length; i++) {
            students.push({
                id: i + 1,
                name: studentNames[i],
                supervisor: null,
                assessor1: null,
                assessor2: null,
                selected: false
            });
        }

        let filteredStudents = [...students];
        let openDropdown = null; // Track which dropdown is currently open

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            renderLecturerTable();
            updateRemainingStudent();
            initializeTabs();
            initializeSearch();
            initializeRoleToggle();
            initializeStudentDistribution();
        });

        // Render lecturer table
        function renderLecturerTable() {
            const tbody = document.getElementById('lecturerTableBody');
            tbody.innerHTML = '';

            filteredLecturers.forEach((lecturer, index) => {
                const row = document.createElement('tr');
                // Calculate remaining quota based on actual student assignments
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remainingQuota = Math.max(0, lecturer.quota - assignedCount);
                
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${lecturer.name}</td>
                    <td>
                        <input type="number" 
                               class="quota-input" 
                               value="${lecturer.quota}" 
                               min="0"
                               data-lecturer-id="${lecturer.id}"
                               onchange="updateQuota(${lecturer.id}, this.value)"
                               oninput="updateRemainingStudent()" />
                    </td>
                    <td class="remaining-quota" id="remaining-${lecturer.id}">${lecturer.remainingQuota}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Update quota for a lecturer
        function updateQuota(lecturerId, newQuota) {
            const lecturer = lecturers.find(l => l.id === lecturerId);
            if (lecturer) {
                const quotaValue = parseInt(newQuota) || 0;
                lecturer.quota = quotaValue;
                // Initialize remaining quota to quota when quota is set
                // Then update based on actual student assignments
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remainingQuota = Math.max(0, quotaValue - assignedCount);
                updateRemainingQuota(lecturerId);
                updateRemainingStudent();
                // Re-render student table to update supervisor dropdowns
                if (document.querySelector('.task-group[data-group="distribution"].active')) {
                    renderStudentTable();
                }
            }
        }

        // Update remaining quota for a lecturer based on actual student assignments
        function updateRemainingQuota(lecturerId) {
            const lecturer = lecturers.find(l => l.id === lecturerId);
            if (lecturer) {
                // Count how many students have this lecturer as supervisor
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remainingQuota = Math.max(0, lecturer.quota - assignedCount);
                
                const element = document.getElementById(`remaining-${lecturerId}`);
                if (element) {
                    element.textContent = lecturer.remainingQuota;
                }
            }
        }

        // Update all remaining quotas based on student distribution
        function updateAllRemainingQuotas() {
            lecturers.forEach(lecturer => {
                const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                lecturer.remainingQuota = Math.max(0, lecturer.quota - assignedCount);
                
                const element = document.getElementById(`remaining-${lecturer.id}`);
                if (element) {
                    element.textContent = lecturer.remainingQuota;
                }
            });
        }

        // Update remaining student count
        function updateRemainingStudent() {
            const totalQuota = lecturers.reduce((sum, lecturer) => sum + (parseInt(lecturer.quota) || 0), 0);
            const remaining = Math.max(0, totalStudents - totalQuota);
            document.getElementById('remainingStudent').textContent = remaining;
        }

        // General Modal Functions
        var currentModal = null;

        function openModal(modal) {
            if (modal) {
                modal.style.display = 'block';
                currentModal = modal;
            }
        }

        function closeModal(modal) {
            if (modal) {
                modal.style.display = 'none';
                currentModal = null;
            }
        }

        // Assign Remaining Quota Modal
        var assignModal = document.createElement('div');
        assignModal.className = 'custom-modal';
        assignModal.id = 'assignQuotaModal';
        document.body.appendChild(assignModal);

        function openAssignModal() {
            openModal(assignModal);
        }

        function closeAssignModal() {
            closeModal(assignModal);
        }

        // Clear All Modal
        var clearAllModal = document.createElement('div');
        clearAllModal.className = 'custom-modal';
        clearAllModal.id = 'clearAllModal';
        document.body.appendChild(clearAllModal);

        function openClearAllModal() {
            openModal(clearAllModal);
        }

        function closeClearAllModal() {
            closeModal(clearAllModal);
        }

        // Download Success Modal
        var downloadModal = document.createElement('div');
        downloadModal.className = 'custom-modal';
        downloadModal.id = 'downloadModal';
        document.body.appendChild(downloadModal);

        function openDownloadModal() {
            openModal(downloadModal);
        }

        function closeDownloadModal() {
            closeModal(downloadModal);
        }

        // Save Success Modal
        var saveModal = document.createElement('div');
        saveModal.className = 'custom-modal';
        saveModal.id = 'saveModal';
        document.body.appendChild(saveModal);

        function openSaveModal() {
            openModal(saveModal);
        }

        function closeSaveModal() {
            closeModal(saveModal);
        }

        // Reset Quotas Modal
        var resetModal = document.createElement('div');
        resetModal.className = 'custom-modal';
        resetModal.id = 'resetModal';
        document.body.appendChild(resetModal);

        function openResetModal() {
            openModal(resetModal);
        }

        function closeResetModal() {
            closeModal(resetModal);
        }

        // Assign remaining quota to lecturers who haven't been filled (quota = 0)
        function assignRemainingQuota() {
            const remaining = parseInt(document.getElementById('remainingStudent').textContent);
            const lecturersWithoutQuota = lecturers.filter(lecturer => lecturer.quota === 0);

            if (remaining <= 0) {
                assignModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">No Remaining Students</div>
                            <div class="modal-message">There are no remaining students to assign. Please adjust quotas or total students first.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okNoRemaining" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                assignModal.querySelector('#okNoRemaining').onclick = closeAssignModal;
                openAssignModal();
                return;
            }

            if (lecturersWithoutQuota.length === 0) {
                assignModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">All Lecturers Have Quotas</div>
                            <div class="modal-message">All lecturers already have quotas assigned. Please increase total students or reduce some quotas to assign remaining students.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okAllAssigned" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                assignModal.querySelector('#okAllAssigned').onclick = closeAssignModal;
                openAssignModal();
                return;
            }

            // Show confirmation modal
            const quotaPerLecturer = Math.floor(remaining / lecturersWithoutQuota.length);
            const remainder = remaining % lecturersWithoutQuota.length;
            const message = `Assign ${remaining} remaining students to ${lecturersWithoutQuota.length} lecturers without quota?<br><br>
                Each lecturer will receive approximately ${quotaPerLecturer} ${quotaPerLecturer !== 1 ? 'students' : 'student'}.`;

            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModal">&times;</span>
                        <div class="modal-title-custom">Assign Remaining Quota</div>
                        <div class="modal-message">${message}</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelAssign" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmAssign" class="btn btn-success" type="button">Assign</button>
                        </div>
                    </div>
                </div>`;

            assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
            assignModal.querySelector('#cancelAssign').onclick = closeAssignModal;
            assignModal.querySelector('#confirmAssign').onclick = function() {
                performAssignQuota();
            };
            openAssignModal();
        }

        // Perform the actual assignment
        function performAssignQuota() {
            const remaining = parseInt(document.getElementById('remainingStudent').textContent);
            const lecturersWithoutQuota = lecturers.filter(lecturer => lecturer.quota === 0);

            // Distribute remaining students evenly among lecturers without quota
            const quotaPerLecturer = Math.floor(remaining / lecturersWithoutQuota.length);
            const remainder = remaining % lecturersWithoutQuota.length;

            let distributed = 0;
            lecturersWithoutQuota.forEach((lecturer, index) => {
                const lecturerIndex = lecturers.findIndex(l => l.id === lecturer.id);
                
                if (lecturerIndex !== -1) {
                    // Give each lecturer base quota, and first few lecturers get +1 if there's remainder
                    const quotaToAssign = quotaPerLecturer + (index < remainder ? 1 : 0);
                    lecturers[lecturerIndex].quota = quotaToAssign;
                    // Initialize remaining quota to quota when quota is assigned
                    lecturers[lecturerIndex].remainingQuota = quotaToAssign;
                    distributed += quotaToAssign;
                    
                    // Update the input field
                    const input = document.querySelector(`input[data-lecturer-id="${lecturer.id}"]`);
                    if (input) {
                        input.value = quotaToAssign;
                    }
                    
                    updateRemainingQuota(lecturer.id);
                }
            });

            updateRemainingStudent();

            // Show success modal
            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModalSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quota Assigned Successfully</div>
                        <div class="modal-message">Remaining ${distributed} students have been assigned to ${lecturersWithoutQuota.length} lecturers without quota.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okAssigned" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            assignModal.querySelector('#closeAssignModalSuccess').onclick = closeAssignModal;
            assignModal.querySelector('#okAssigned').onclick = closeAssignModal;
        }

        // Clear all quotas (set all to 0)
        function clearAllQuotas() {
            // Show confirmation modal
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllModal">&times;</span>
                        <div class="modal-title-custom">Clear All Quotas</div>
                        <div class="modal-message">Are you sure you want to clear all quotas? All quota assignments will be removed.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelClearAll" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmClearAll" class="btn btn-success" type="button">Clear All</button>
                        </div>
                    </div>
                </div>`;

            clearAllModal.querySelector('#closeClearAllModal').onclick = closeClearAllModal;
            clearAllModal.querySelector('#cancelClearAll').onclick = closeClearAllModal;
            clearAllModal.querySelector('#confirmClearAll').onclick = function() {
                performClearAll();
            };
            openClearAllModal();
        }

        // Perform the actual clear all
        function performClearAll() {
            lecturers.forEach(lecturer => {
                lecturer.quota = 0;
                lecturer.remainingQuota = 0;
                const input = document.querySelector(`input[data-lecturer-id="${lecturer.id}"]`);
                if (input) {
                    input.value = 0;
                }
                updateRemainingQuota(lecturer.id);
            });
            updateRemainingStudent();
            // Re-render student table to update supervisor dropdowns
            if (document.querySelector('.task-group[data-group="distribution"].active')) {
                renderStudentTable();
            }

            // Show success modal
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quotas Cleared</div>
                        <div class="modal-message">All quotas have been cleared successfully.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okCleared" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            clearAllModal.querySelector('#closeClearAllSuccess').onclick = closeClearAllModal;
            clearAllModal.querySelector('#okCleared').onclick = closeClearAllModal;
        }

        // Reset quotas (cancel changes - reload page)
        function resetQuotas() {
            // Show confirmation modal
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeResetModal">&times;</span>
                        <div class="modal-title-custom">Reset Quotas</div>
                        <div class="modal-message">Are you sure you want to cancel all changes? This will reset quotas to their previous values.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelReset" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmReset" class="btn btn-success" type="button">Reset</button>
                        </div>
                    </div>
                </div>`;

            resetModal.querySelector('#closeResetModal').onclick = closeResetModal;
            resetModal.querySelector('#cancelReset').onclick = closeResetModal;
            resetModal.querySelector('#confirmReset').onclick = function() {
                // Reload the page to reset to original values
                location.reload();
            };
            openResetModal();
        }

        // Download as PDF
        function downloadAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                
                // Create PDF document
                const doc = new jsPDF();
                
                // Set font
                doc.setFont('helvetica');
                
                // Add title
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0); // #780000 color
                doc.text('Lecturer Quota Assignation Report', 14, 20);
                
                // Add summary information
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0); // Black color
                doc.text(`Total Lecturer: ${lecturers.length}`, 14, 35);
                doc.text(`Total Students: ${totalStudents}`, 14, 42);
                doc.text(`Remaining Students: ${document.getElementById('remainingStudent').textContent}`, 14, 49);
                
                // Prepare table data
                const tableData = lecturers.map((lecturer, index) => [
                    index + 1,
                    lecturer.name,
                    lecturer.quota.toString(),
                    lecturer.remainingQuota.toString()
                ]);
                
                // Add table using autoTable plugin
                doc.autoTable({
                    startY: 55,
                    head: [['No.', 'Name', 'Quota', 'Remaining Quota']],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [120, 0, 0], // #780000 color
                        textColor: [255, 255, 255], // White text
                        fontStyle: 'bold',
                        fontSize: 11
                    },
                    bodyStyles: {
                        fontSize: 10
                    },
                    alternateRowStyles: {
                        fillColor: [253, 240, 213] // #fdf0d5 color
                    },
                    styles: {
                        cellPadding: 5,
                        fontSize: 10
                    },
                    columnStyles: {
                        0: { cellWidth: 20, halign: 'center' },
                        1: { cellWidth: 'auto', halign: 'left' },
                        2: { cellWidth: 30, halign: 'center' },
                        3: { cellWidth: 50, halign: 'center' }
                    }
                });
                
                // Get final Y position after table
                const finalY = doc.lastAutoTable.finalY;
                
                // Add date and time at the bottom
                doc.setFontSize(9);
                doc.setTextColor(128, 128, 128); // Gray color
                const now = new Date();
                const dateTime = now.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                doc.text(`Generated on: ${dateTime}`, 14, finalY + 15);
                
                // Save PDF
                doc.save('lecturer-quota-assignation.pdf');

                // Show success modal
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="modal-title-custom">Download Successful</div>
                            <div class="modal-message">PDF file downloaded successfully!</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;

                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            } catch (error) {
                console.error('Error generating PDF:', error);
                
                // Show error modal
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">Download Failed</div>
                            <div class="modal-message">An error occurred while generating the PDF. Please try again.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;

                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            }
        }

        // Download as Excel
        function downloadAsExcel() {
            // Create CSV content
            const tableData = lecturers.map((lecturer, index) => ({
                no: index + 1,
                name: lecturer.name,
                quota: lecturer.quota,
                remaining: lecturer.remainingQuota
            }));

            // Create CSV content
            let csvContent = 'No.,Name,Quota,Remaining Quota\n';
            
            tableData.forEach(row => {
                csvContent += `${row.no},"${row.name}",${row.quota},${row.remaining}\n`;
            });

            // Add summary at the end
            csvContent += `\nTotal Lecturer,${lecturers.length}\n`;
            csvContent += `Total Students,${totalStudents}\n`;
            csvContent += `Remaining Students,${document.getElementById('remainingStudent').textContent}\n`;

            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'lecturer-quota-assignation.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Show success modal
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeDownloadModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Download Successful</div>
                        <div class="modal-message">Excel file (CSV) downloaded successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okDownload" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
            downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
            openDownloadModal();
        }

        // Save quotas
        function saveQuotas() {
            // Here you would typically send data to server
            const quotaData = lecturers.map(lecturer => ({
                id: lecturer.id,
                name: lecturer.name,
                quota: lecturer.quota
            }));

            console.log('Saving quotas:', quotaData);
            
            // In a real application, you would make an AJAX call here
            // fetch('/api/save-quotas', { method: 'POST', body: JSON.stringify(quotaData) })
            //     .then(response => response.json())
            //     .then(data => {
            //         // Update remaining quotas after successful save
            //         updateAllRemainingQuotas();
            //         // Re-render student table if Student Distribution tab is active
            //         if (document.querySelector('.task-group[data-group="distribution"].active')) {
            //             renderStudentTable();
            //         }
            //         showSaveSuccess();
            //     })
            //     .catch(error => {
            //         showSaveError(error);
            //     });
            
            // For now, update remaining quotas and re-render student table immediately
            // First, ensure all remaining quotas are properly initialized
            lecturers.forEach(lecturer => {
                // If quota is set but remainingQuota is not properly initialized, initialize it
                if (lecturer.quota > 0 && lecturer.remainingQuota === undefined) {
                    const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                    lecturer.remainingQuota = Math.max(0, lecturer.quota - assignedCount);
                }
            });
            
            // Update remaining quotas based on current student assignments
            updateAllRemainingQuotas();
            
            // Re-render student table if Student Distribution tab is active
            // This will update supervisor dropdowns to show only lecturers with remaining quota
            if (document.querySelector('.task-group[data-group="distribution"].active')) {
                renderStudentTable();
            }
            
            // Also re-render lecturer table to update remaining quota display
            renderLecturerTable();
            
            // Show success modal
            showSaveSuccess();
        }

        // Show save success modal
        function showSaveSuccess() {
            saveModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeSaveModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Quotas Saved</div>
                        <div class="modal-message">Quotas saved successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okSave" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            saveModal.querySelector('#closeSaveModal').onclick = closeSaveModal;
            saveModal.querySelector('#okSave').onclick = closeSaveModal;
            openSaveModal();
        }

        // Initialize tabs
        function initializeTabs() {
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.getAttribute('data-tab');

                    // Update active tab style
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');

                    // Switch active task group
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            group.classList.add('active');
                        } else {
                            group.classList.remove('active');
                        }
                    });
                });
            });
        }

        // Initialize search
        function initializeSearch() {
            const searchInput = document.getElementById('lecturerSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase().trim();
                    
                    if (searchTerm === '') {
                        filteredLecturers = [...lecturers];
                    } else {
                        filteredLecturers = lecturers.filter(lecturer => 
                            lecturer.name.toLowerCase().includes(searchTerm)
                        );
                    }
                    
                    renderLecturerTable();
                });
            }
        }

        // --- STUDENT DISTRIBUTION FUNCTIONS ---
        
        // Initialize Student Distribution
        function initializeStudentDistribution() {
            // Update remaining quotas based on current student assignments
            updateAllRemainingQuotas();
            renderStudentTable();
            initializeStudentSearch();
            updateTotalStudentCount();
        }

        // Render student table
        function renderStudentTable() {
            const tbody = document.getElementById('studentTableBody');
            if (!tbody) return;
            
            tbody.innerHTML = '';

            filteredStudents.forEach((student, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${student.name}</td>
                    <td>
                        <div class="custom-dropdown">
                            <button class="dropdown-btn" type="button" onclick="toggleLecturerDropdown(${student.id}, 'supervisor')">
                                <span class="dropdown-text">${student.supervisor || 'Select Supervisor'}</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-supervisor-${student.id}">
                                <div class="dropdown-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search lecturer..." oninput="filterDropdownLecturers(${student.id}, 'supervisor', this.value)" />
                                </div>
                                <div class="dropdown-options" id="options-supervisor-${student.id}">
                                    ${generateLecturerOptions(student.id, 'supervisor', null)}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="custom-dropdown">
                            <button class="dropdown-btn" type="button" onclick="toggleLecturerDropdown(${student.id}, 'assessor1')">
                                <span class="dropdown-text">${student.assessor1 || 'Select Assessor 1'}</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-assessor1-${student.id}">
                                <div class="dropdown-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search lecturer..." oninput="filterDropdownLecturers(${student.id}, 'assessor1', this.value)" />
                                </div>
                                <div class="dropdown-options" id="options-assessor1-${student.id}">
                                    ${generateLecturerOptions(student.id, 'assessor1', student.supervisor)}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="custom-dropdown">
                            <button class="dropdown-btn" type="button" onclick="toggleLecturerDropdown(${student.id}, 'assessor2')">
                                <span class="dropdown-text">${student.assessor2 || 'Select Assessor 2'}</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdown-assessor2-${student.id}">
                                <div class="dropdown-search">
                                    <i class="bi bi-search"></i>
                                    <input type="text" placeholder="Search lecturer..." oninput="filterDropdownLecturers(${student.id}, 'assessor2', this.value)" />
                                </div>
                                <div class="dropdown-options" id="options-assessor2-${student.id}">
                                    ${generateLecturerOptions(student.id, 'assessor2', student.supervisor)}
                                </div>
                            </div>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        // Generate lecturer options for dropdown
        function generateLecturerOptions(studentId, role, excludeSupervisor) {
            const student = students.find(s => s.id === studentId);
            if (!student) return '';

            let options = '';
            lecturers.forEach(lecturer => {
                // For supervisor role, only show lecturers with quota > 0 and remaining quota > 0
                if (role === 'supervisor') {
                    // Calculate remaining quota on the fly to ensure accuracy
                    const assignedCount = students.filter(s => s.supervisor === lecturer.name).length;
                    const currentRemainingQuota = Math.max(0, lecturer.quota - assignedCount);
                    
                    // Only show if lecturer has quota > 0 and remaining quota > 0
                    // OR if this lecturer is already selected as supervisor (to allow changing)
                    if (lecturer.quota <= 0 || (currentRemainingQuota <= 0 && student.supervisor !== lecturer.name)) {
                        return;
                    }
                }
                
                // Exclude supervisor from assessor dropdowns
                if (excludeSupervisor && lecturer.name === excludeSupervisor) {
                    return;
                }
                
                // Exclude already selected assessors from other assessor dropdown
                if (role === 'assessor1' && student.assessor2 === lecturer.name) {
                    return;
                }
                if (role === 'assessor2' && student.assessor1 === lecturer.name) {
                    return;
                }
                if (role === 'supervisor' && (student.assessor1 === lecturer.name || student.assessor2 === lecturer.name)) {
                    return;
                }

                const isSelected = 
                    (role === 'supervisor' && student.supervisor === lecturer.name) ||
                    (role === 'assessor1' && student.assessor1 === lecturer.name) ||
                    (role === 'assessor2' && student.assessor2 === lecturer.name);

                options += `
                    <div class="dropdown-option ${isSelected ? 'selected' : ''}" 
                         onclick="selectLecturer(${studentId}, '${role}', '${lecturer.name.replace(/'/g, "\\'")}')">
                        ${lecturer.name}
                    </div>
                `;
            });

            if (options === '') {
                options = '<div class="dropdown-option disabled">No options available</div>';
            }

            return options;
        }

        // Toggle lecturer dropdown
        function toggleLecturerDropdown(studentId, role) {
            const dropdownId = `dropdown-${role}-${studentId}`;
            const dropdown = document.getElementById(dropdownId);

            // Close all other dropdowns
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });

            // Toggle current dropdown
            if (dropdown) {
                dropdown.classList.toggle('show');
                if (dropdown.classList.contains('show')) {
                    openDropdown = dropdownId;
                    // Reset search when opening
                    const searchInput = dropdown.querySelector('.dropdown-search input');
                    if (searchInput) {
                        searchInput.value = '';
                        filterDropdownLecturers(studentId, role, '');
                    }
                } else {
                    openDropdown = null;
                }
            }
        }

        // Filter dropdown lecturers
        function filterDropdownLecturers(studentId, role, searchTerm) {
            const optionsContainer = document.getElementById(`options-${role}-${studentId}`);
            if (!optionsContainer) return;

            const searchLower = searchTerm.toLowerCase();
            const options = optionsContainer.querySelectorAll('.dropdown-option');

            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(searchLower)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        }

        // Select lecturer for student
        function selectLecturer(studentId, role, lecturerName) {
            const student = students.find(s => s.id === studentId);
            if (!student) return;

            // Update student data
            if (role === 'supervisor') {
                student.supervisor = lecturerName;
                // Clear assessors if they were the supervisor
                if (student.assessor1 === lecturerName) {
                    student.assessor1 = null;
                }
                if (student.assessor2 === lecturerName) {
                    student.assessor2 = null;
                }
            } else if (role === 'assessor1') {
                // Don't allow if it's the supervisor
                if (student.supervisor === lecturerName) {
                    return;
                }
                // Don't allow if it's assessor2
                if (student.assessor2 === lecturerName) {
                    return;
                }
                student.assessor1 = lecturerName;
            } else if (role === 'assessor2') {
                // Don't allow if it's the supervisor
                if (student.supervisor === lecturerName) {
                    return;
                }
                // Don't allow if it's assessor1
                if (student.assessor1 === lecturerName) {
                    return;
                }
                student.assessor2 = lecturerName;
            }

            // Update remaining quotas based on actual student assignments
            updateAllRemainingQuotas();

            // Close dropdown
            const dropdownId = `dropdown-${role}-${studentId}`;
            const dropdown = document.getElementById(dropdownId);
            if (dropdown) {
                dropdown.classList.remove('show');
                openDropdown = null;
            }

            // Re-render the row to update dropdowns
            renderStudentTable();
        }


        // Clear all assignments
        function clearAllAssignments() {
            // Show confirmation modal
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllModal">&times;</span>
                        <div class="modal-title-custom">Clear All Assignments</div>
                        <div class="modal-message">Are you sure you want to clear all supervisor and assessor assignments? All assignments will be removed.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelClearAll" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmClearAll" class="btn btn-success" type="button">Clear All</button>
                        </div>
                    </div>
                </div>`;

            clearAllModal.querySelector('#closeClearAllModal').onclick = closeClearAllModal;
            clearAllModal.querySelector('#cancelClearAll').onclick = closeClearAllModal;
            clearAllModal.querySelector('#confirmClearAll').onclick = function() {
                performClearAllAssignments();
            };
            openClearAllModal();
        }

        // Perform clear all assignments
        function performClearAllAssignments() {
            students.forEach(student => {
                student.supervisor = null;
                student.assessor1 = null;
                student.assessor2 = null;
            });
            
            // Update remaining quotas after clearing
            updateAllRemainingQuotas();
            renderStudentTable();

            // Show success modal
            clearAllModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeClearAllSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assignments Cleared</div>
                        <div class="modal-message">All supervisor and assessor assignments have been cleared successfully.</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okCleared" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            clearAllModal.querySelector('#closeClearAllSuccess').onclick = closeClearAllModal;
            clearAllModal.querySelector('#okCleared').onclick = closeClearAllModal;
        }

        // Assign remaining automatically with type: 'supervisor', 'assessor', or 'both'
        function assignAutomatically(type) {
            let message = '';
            if (type === 'supervisor') {
                message = 'This will automatically assign remaining supervisors to students. Students will be grouped into 3 groups. Continue?';
            } else if (type === 'assessor') {
                message = 'This will automatically assign remaining assessors to students. Each student will be assessed by supervisors from the other 2 groups. If insufficient, any available lecturer (excluding supervisor) will be assigned. Continue?';
            } else {
                message = 'This will automatically assign remaining supervisors and assessors. Students will be grouped into 3 groups, with each group assessed by supervisors from the other 2 groups. If insufficient, any available lecturer (excluding supervisor) will be assigned. Continue?';
            }

            // Show confirmation modal
            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModal">&times;</span>
                        <div class="modal-title-custom">Assign Remaining Automatically</div>
                        <div class="modal-message">${message}</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelAssign" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmAssign" class="btn btn-success" type="button">Assign</button>
                        </div>
                    </div>
                </div>`;

            assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
            assignModal.querySelector('#cancelAssign').onclick = closeAssignModal;
            assignModal.querySelector('#confirmAssign').onclick = function() {
                performAutoAssign(type);
            };
            openAssignModal();
        }

        // Perform automatic assignment
        function performAutoAssign(type) {
            // Get lecturers who have quota > 0 (assigned students)
            const lecturersWithQuota = lecturers.filter(l => l.quota > 0);
            
            // Get all lecturers (for assessor assignment when insufficient)
            const allLecturers = lecturers.map(l => l.name);
            
            if (lecturersWithQuota.length === 0 && (type === 'supervisor' || type === 'both')) {
                assignModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeAssignModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">No Lecturers with Quota</div>
                            <div class="modal-message">No lecturers have assigned quotas. Please assign quotas first in the "Lecturer Quota Assignation" tab.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okAssign" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;
                assignModal.querySelector('#closeAssignModal').onclick = closeAssignModal;
                assignModal.querySelector('#okAssign').onclick = closeAssignModal;
                return;
            }

            if (type === 'supervisor' || type === 'both') {
                // Assign supervisors
                if (lecturersWithQuota.length >= 3) {
                    // Group lecturers into 3 groups (divide evenly)
                    const groupSize = Math.floor(lecturersWithQuota.length / 3);
                    const groups = [];
                    for (let i = 0; i < 3; i++) {
                        const start = i * groupSize;
                        const end = i === 2 ? lecturersWithQuota.length : (i + 1) * groupSize;
                        groups.push(lecturersWithQuota.slice(start, end));
                    }

                    // Assign supervisors to students
                    let studentIndex = 0;
                    students.forEach(student => {
                        const groupIndex = studentIndex % 3;
                        const supervisorGroup = groups[groupIndex];
                        
                        if (supervisorGroup.length > 0) {
                            const supervisorIndex = Math.floor(studentIndex / 3) % supervisorGroup.length;
                            student.supervisor = supervisorGroup[supervisorIndex].name;
                        }
                        
                        studentIndex++;
                    });
                } else {
                    // Less than 3 lecturers - distribute evenly
                    let studentIndex = 0;
                    students.forEach(student => {
                        const lecturerIndex = studentIndex % lecturersWithQuota.length;
                        student.supervisor = lecturersWithQuota[lecturerIndex].name;
                        studentIndex++;
                    });
                }
            }

            if (type === 'assessor' || type === 'both') {
                // Assign assessors based on 3-group logic
                // Group 1 is assessed by Group 2 and Group 3 supervisors
                // Group 2 is assessed by Group 1 and Group 3 supervisors
                // Group 3 is assessed by Group 1 and Group 2 supervisors
                // If insufficient lecturers in other groups, use any available lecturer (excluding supervisor)
                
                // Determine which group each student belongs to based on their supervisor
                let lecturerGroups = [];
                let studentGroups = [[], [], []]; // 3 groups for students
                
                if (lecturersWithQuota.length >= 3) {
                    // Group lecturers into 3 groups
                    const groupSize = Math.floor(lecturersWithQuota.length / 3);
                    for (let i = 0; i < 3; i++) {
                        const start = i * groupSize;
                        const end = i === 2 ? lecturersWithQuota.length : (i + 1) * groupSize;
                        lecturerGroups.push(lecturersWithQuota.slice(start, end).map(l => l.name));
                    }
                } else {
                    // Less than 3 lecturers - create groups with available lecturers
                    for (let i = 0; i < lecturersWithQuota.length; i++) {
                        lecturerGroups.push([lecturersWithQuota[i].name]);
                    }
                    // Fill remaining groups with empty arrays
                    while (lecturerGroups.length < 3) {
                        lecturerGroups.push([]);
                    }
                }
                
                // Assign students to groups based on their supervisor
                students.forEach((student, index) => {
                    if (student.supervisor) {
                        // Find which group the supervisor belongs to
                        let supervisorGroupIndex = -1;
                        for (let i = 0; i < lecturerGroups.length; i++) {
                            if (lecturerGroups[i].includes(student.supervisor)) {
                                supervisorGroupIndex = i;
                                break;
                            }
                        }
                        
                        // If supervisor is found in a group, assign student to that group
                        // Otherwise, assign based on student index
                        const groupIndex = supervisorGroupIndex >= 0 ? supervisorGroupIndex : (index % 3);
                        studentGroups[groupIndex].push(student);
                    } else {
                        // No supervisor assigned, assign based on student index
                        studentGroups[index % 3].push(student);
                    }
                });
                
                // Now assign assessors: each group is assessed by supervisors from the other 2 groups
                studentGroups.forEach((group, groupIndex) => {
                    // Get supervisors from the other 2 groups
                    const otherGroupIndices = [0, 1, 2].filter(idx => idx !== groupIndex);
                    const assessorGroup1 = lecturerGroups[otherGroupIndices[0]] || [];
                    const assessorGroup2 = lecturerGroups[otherGroupIndices[1]] || [];
                    
                    group.forEach((student, studentIndexInGroup) => {
                        // Try to assign from other groups first
                        if (assessorGroup1.length > 0 && assessorGroup2.length > 0) {
                            // Both groups have supervisors
                            const assessor1Index = studentIndexInGroup % assessorGroup1.length;
                            const assessor2Index = studentIndexInGroup % assessorGroup2.length;
                            student.assessor1 = assessorGroup1[assessor1Index];
                            student.assessor2 = assessorGroup2[assessor2Index];
                        } else if (assessorGroup1.length > 0) {
                            // Only first group has supervisors
                            const assessor1Index = studentIndexInGroup % assessorGroup1.length;
                            student.assessor1 = assessorGroup1[assessor1Index];
                            
                            // For assessor2, use any available lecturer (excluding supervisor and assessor1)
                            let availableForAssessor2 = allLecturers.filter(l => 
                                l !== student.supervisor && l !== student.assessor1
                            );
                            if (availableForAssessor2.length > 0) {
                                student.assessor2 = availableForAssessor2[studentIndexInGroup % availableForAssessor2.length];
                            } else {
                                student.assessor2 = null;
                            }
                        } else if (assessorGroup2.length > 0) {
                            // Only second group has supervisors
                            const assessor2Index = studentIndexInGroup % assessorGroup2.length;
                            student.assessor2 = assessorGroup2[assessor2Index];
                            
                            // For assessor1, use any available lecturer (excluding supervisor and assessor2)
                            let availableForAssessor1 = allLecturers.filter(l => 
                                l !== student.supervisor && l !== student.assessor2
                            );
                            if (availableForAssessor1.length > 0) {
                                student.assessor1 = availableForAssessor1[studentIndexInGroup % availableForAssessor1.length];
                            } else {
                                student.assessor1 = null;
                            }
                        } else {
                            // Neither group has supervisors - use any available lecturer (excluding supervisor)
                            let availableLecturers = allLecturers.filter(l => l !== student.supervisor);
                            
                            if (availableLecturers.length === 0) {
                                student.assessor1 = null;
                                student.assessor2 = null;
                            } else if (availableLecturers.length === 1) {
                                student.assessor1 = availableLecturers[0];
                                student.assessor2 = null;
                            } else {
                                const assessor1Index = studentIndexInGroup % availableLecturers.length;
                                let assessor2Index = (studentIndexInGroup + 1) % availableLecturers.length;
                                
                                // Make sure assessor2 is different from assessor1
                                if (assessor2Index === assessor1Index) {
                                    assessor2Index = (studentIndexInGroup + 2) % availableLecturers.length;
                                }
                                
                                student.assessor1 = availableLecturers[assessor1Index];
                                if (assessor2Index !== assessor1Index) {
                                    student.assessor2 = availableLecturers[assessor2Index];
                                } else {
                                    // Find a different lecturer
                                    const otherLecturers = availableLecturers.filter((_, idx) => idx !== assessor1Index);
                                    student.assessor2 = otherLecturers.length > 0 ? otherLecturers[0] : null;
                                }
                            }
                        }
                    });
                });
            }

            // Update remaining quotas after assignment
            updateAllRemainingQuotas();
            renderStudentTable();

            // Show success modal
            let successMessage = '';
            if (type === 'supervisor') {
                successMessage = 'Remaining supervisors have been automatically assigned to students. Students are grouped into 3 groups.';
            } else if (type === 'assessor') {
                successMessage = 'Remaining assessors have been automatically assigned. Students are grouped into 3 groups, with each group assessed by supervisors from the other 2 groups. If insufficient lecturers were available, any available lecturer (excluding supervisor) was assigned.';
            } else {
                successMessage = 'Remaining supervisors and assessors have been automatically assigned. Students are grouped into 3 groups, with each group assessed by supervisors from the other 2 groups. If insufficient lecturers were available, any available lecturer (excluding supervisor) was assigned.';
            }

            assignModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeAssignModalSuccess">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assignment Completed</div>
                        <div class="modal-message">${successMessage}</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okAssigned" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            assignModal.querySelector('#closeAssignModalSuccess').onclick = closeAssignModal;
            assignModal.querySelector('#okAssigned').onclick = closeAssignModal;
        }

        // Initialize student search
        function initializeStudentSearch() {
            const searchInput = document.getElementById('studentSearch');
            if (!searchInput) return;

            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                
                if (searchTerm === '') {
                    filteredStudents = [...students];
                } else {
                    filteredStudents = students.filter(student => 
                        student.name.toLowerCase().includes(searchTerm)
                    );
                }
                
                renderStudentTable();
            });
        }

        // Update total student count
        function updateTotalStudentCount() {
            const countElement = document.getElementById('totalStudentCount');
            if (countElement) {
                countElement.textContent = students.length;
            }
        }

        // Reset assignments
        function resetAssignments() {
            // Show confirmation modal
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeResetModal">&times;</span>
                        <div class="modal-title-custom">Reset Assignments</div>
                        <div class="modal-message">Are you sure you want to cancel all changes? This will reset all assignments to their previous values.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelReset" class="btn btn-light border" type="button">Cancel</button>
                            <button id="confirmReset" class="btn btn-success" type="button">Reset</button>
                        </div>
                    </div>
                </div>`;

            resetModal.querySelector('#closeResetModal').onclick = closeResetModal;
            resetModal.querySelector('#cancelReset').onclick = closeResetModal;
            resetModal.querySelector('#confirmReset').onclick = function() {
                location.reload();
            };
            openResetModal();
        }

        // Save assignments
        function saveAssignments() {
            const assignmentData = students.map(student => ({
                id: student.id,
                name: student.name,
                supervisor: student.supervisor,
                assessor1: student.assessor1,
                assessor2: student.assessor2
            }));

            console.log('Saving assignments:', assignmentData);
            
            // Show success modal
            saveModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeSaveModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Assignments Saved</div>
                        <div class="modal-message">Student assignments saved successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okSave" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            saveModal.querySelector('#closeSaveModal').onclick = closeSaveModal;
            saveModal.querySelector('#okSave').onclick = closeSaveModal;
            openSaveModal();
        }

        // Download Distribution as PDF
        function downloadDistributionAsPDF() {
            try {
                const { jsPDF } = window.jspdf;
                
                const doc = new jsPDF();
                doc.setFont('helvetica');
                
                // Add title
                doc.setFontSize(18);
                doc.setTextColor(120, 0, 0);
                doc.text('Student Distribution Report', 14, 20);
                
                // Add summary
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Total Students: ${students.length}`, 14, 35);
                
                // Prepare table data
                const tableData = students.map((student, index) => [
                    index + 1,
                    student.name,
                    student.supervisor || '-',
                    student.assessor1 || '-',
                    student.assessor2 || '-'
                ]);
                
                // Add table
                doc.autoTable({
                    startY: 45,
                    head: [['No.', 'Name', 'Supervisor', 'Assessor 1', 'Assessor 2']],
                    body: tableData,
                    theme: 'striped',
                    headStyles: {
                        fillColor: [120, 0, 0],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        fontSize: 11
                    },
                    bodyStyles: {
                        fontSize: 9
                    },
                    alternateRowStyles: {
                        fillColor: [253, 240, 213]
                    },
                    styles: {
                        cellPadding: 4,
                        fontSize: 9
                    }
                });
                
                const finalY = doc.lastAutoTable.finalY;
                doc.setFontSize(9);
                doc.setTextColor(128, 128, 128);
                const now = new Date();
                const dateTime = now.toLocaleString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                doc.text(`Generated on: ${dateTime}`, 14, finalY + 15);
                
                doc.save('student-distribution.pdf');

                // Show success modal
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                            <div class="modal-title-custom">Download Successful</div>
                            <div class="modal-message">PDF file downloaded successfully!</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;

                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            } catch (error) {
                console.error('Error generating PDF:', error);
                
                downloadModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeDownloadModal">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">Download Failed</div>
                            <div class="modal-message">An error occurred while generating the PDF. Please try again.</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okDownload" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;

                downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
                downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
                openDownloadModal();
            }
        }

        // Download Distribution as Excel
        function downloadDistributionAsExcel() {
            // Create CSV content
            let csvContent = 'No.,Name,Supervisor,Assessor 1,Assessor 2\n';
            
            students.forEach((student, index) => {
                csvContent += `${index + 1},"${student.name}",${student.supervisor || '-'},${student.assessor1 || '-'},${student.assessor2 || '-'}\n`;
            });

            csvContent += `\nTotal Students,${students.length}\n`;

            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'student-distribution.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Show success modal
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeDownloadModal">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Download Successful</div>
                        <div class="modal-message">Excel file (CSV) downloaded successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button id="okDownload" class="btn btn-success" type="button">OK</button>
                        </div>
                    </div>
                </div>`;

            downloadModal.querySelector('#closeDownloadModal').onclick = closeDownloadModal;
            downloadModal.querySelector('#okDownload').onclick = closeDownloadModal;
            openDownloadModal();
        }


        // Toggle assign dropdown
        function toggleAssignDropdown() {
            const dropdown = document.getElementById('assignDropdown');
            const button = document.querySelector('.assign-dropdown .btn-assign');
            
            // Close all other dropdowns
            document.querySelectorAll('.assign-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
            
            document.querySelectorAll('.download-dropdown .btn-download').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }

        function closeAssignDropdown() {
            const dropdown = document.getElementById('assignDropdown');
            const button = document.querySelector('.assign-dropdown .btn-assign');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }

        // Toggle distribution download dropdown
        function toggleDistributionDownloadDropdown() {
            const dropdown = document.getElementById('distributionDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            
            // Close all other dropdowns
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            
            document.querySelectorAll('.assign-dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
            
            document.querySelectorAll('.assign-dropdown .btn-assign').forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }

        function closeDistributionDownloadDropdown() {
            const dropdown = document.getElementById('distributionDownloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }

        // Initialize role toggle (same as dashboard)
        function initializeRoleToggle() {
            // Store the active menu item ID to restore when menu expands again
            let activeMenuItemId = 'studentAssignation';
            
            // Function to set active menu item
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
            
            // Ensure coordinator header always has active-role class on coordinator pages
            // And ensure other roles do NOT have active-role on coordinator pages
            const allRoleHeaders = document.querySelectorAll('.role-header');
            const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
            const coordinatorMenu = document.querySelector('#coordinatorMenu');
            
            // Remove active-role from all non-coordinator roles (since we're on coordinator pages)
            allRoleHeaders.forEach(header => {
                const roleType = header.getAttribute('data-role');
                if (roleType !== 'coordinator') {
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                }
            });
            
            if (coordinatorHeader && coordinatorMenu) {
                coordinatorHeader.classList.add('active-role');
                
                // If coordinator menu is expanded, add menu-expanded class to header and set active item
                if (coordinatorMenu.classList.contains('expanded')) {
                    coordinatorHeader.classList.add('menu-expanded');
                    // Set active menu item based on current page
                    setActiveMenuItem(activeMenuItemId);
                } else {
                    coordinatorHeader.classList.remove('menu-expanded');
                }
            }
            
            const arrowContainers = document.querySelectorAll('.arrow-container');
            
            // Function to handle the role menu toggle
            const handleRoleToggle = (header) => {
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');
                const isCoordinator = header.getAttribute('data-role') === 'coordinator';
                
                if (!targetMenu) return;

                const isExpanded = targetMenu.classList.contains('expanded');

                // Collapse all other menus and reset their arrows
                document.querySelectorAll('.menu-items').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.remove('expanded');
                        menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
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
                        // Restore active menu item if menu is expanded
                        if (activeMenuItemId) {
                            setTimeout(() => {
                                setActiveMenuItem(activeMenuItemId);
                            }, 10);
                        }
                    }
                }
                
                // Remove active-role from all non-coordinator roles (they shouldn't be highlighted on coordinator pages)
                document.querySelectorAll('.role-header').forEach(h => {
                    const roleType = h.getAttribute('data-role');
                    // Only keep active-role for coordinator on coordinator pages
                    if (roleType !== 'coordinator') {
                        h.classList.remove('active-role');
                        // Also remove menu-expanded class from other roles
                        h.classList.remove('menu-expanded');
                    }
                });
                document.querySelectorAll('.arrow-icon').forEach(icon => {
                    if (icon !== arrowIcon) {
                        icon.classList.remove('bi-chevron-down');
                        icon.classList.add('bi-chevron-right');
                    }
                });

                // Toggle current menu
                targetMenu.classList.toggle('expanded', !isExpanded);
                
                // Handle coordinator header styling based on menu state
                if (isCoordinator) {
                    // Coordinator header always has active-role on coordinator pages
                    header.classList.add('active-role');
                    
                    // Check menu state AFTER toggle
                    const isNowExpanded = targetMenu.classList.contains('expanded');
                    
                    // Add/remove menu-expanded class based on menu state AFTER toggle
                    if (isNowExpanded) {
                        // Menu is now expanded - remove white background from header
                        header.classList.add('menu-expanded');
                        // Restore active menu item when menu expands (ensure it's visible and styled)
                        if (activeMenuItemId) {
                            // Small delay to ensure menu is fully expanded before setting active item
                            setTimeout(() => {
                                setActiveMenuItem(activeMenuItemId);
                            }, 10);
                        }
                    } else {
                        // Menu is now collapsed - add white background to header
                        header.classList.remove('menu-expanded');
                        // Keep active menu item class for when menu expands again
                        // The activeMenuItemId variable already stores the current active item
                    }
                } else {
                    // For other roles, DO NOT add active-role class
                    // Other roles should only be highlighted when actually on their pages
                    // Just toggle the menu, but don't highlight the header
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                    
                    // IMPORTANT: After toggling other roles, ensure coordinator header state is maintained
                    // This ensures coordinator stays white when its menu is collapsed, even when other roles are clicked
                    if (coordinatorHeader && coordinatorMenu) {
                        coordinatorHeader.classList.add('active-role');
                        if (!coordinatorMenu.classList.contains('expanded')) {
                            coordinatorHeader.classList.remove('menu-expanded');
                        } else {
                            coordinatorHeader.classList.add('menu-expanded');
                            if (activeMenuItemId) {
                                setTimeout(() => {
                                    setActiveMenuItem(activeMenuItemId);
                                }, 10);
                            }
                        }
                    }
                }
                
                // Show/hide child links for the current menu (only when sidebar is expanded)
                const sidebar = document.getElementById("mySidebar");
                const isSidebarExpanded = sidebar.style.width === "220px";

                targetMenu.querySelectorAll('a').forEach(a => {
                    if(isSidebarExpanded) {
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
            };

            arrowContainers.forEach(container => {
                // Attach event listener to the role header itself
                const header = container.closest('.role-header');
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleRoleToggle(header);
                });
            });

            // --- Menu Item Click Handlers ---
            // Handle clicks on coordinator menu items
            const coordinatorMenuItems = document.querySelectorAll('#coordinatorMenu a');
            coordinatorMenuItems.forEach(menuItem => {
                menuItem.addEventListener('click', (event) => {
                    const coordinatorMenu = document.querySelector('#coordinatorMenu');
                    const coordinatorHeader = document.querySelector('.role-header[data-role="coordinator"]');
                    
                    // Only set active menu item if coordinator menu is expanded
                    if (coordinatorMenu && coordinatorMenu.classList.contains('expanded')) {
                        // Store the clicked menu item ID
                        const menuItemId = menuItem.getAttribute('id');
                        if (menuItemId) {
                            setActiveMenuItem(menuItemId);
                        }
                    }
                });
            });
        }

        // Download Dropdown Functions
        function toggleDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.btn-download');
            
            // Close all other dropdowns if any
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                    const btn = menu.previousElementSibling;
                    if (btn && btn.classList.contains('btn-download')) {
                        btn.classList.remove('active');
                    }
                }
            });
            
            // Toggle current dropdown
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }

        function closeDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            // Handle all download dropdowns
            const allDownloadDropdowns = document.querySelectorAll('.download-dropdown');
            allDownloadDropdowns.forEach(dropdown => {
                if (!dropdown.contains(event.target)) {
                    const menu = dropdown.querySelector('.download-dropdown-menu');
                    const btn = dropdown.querySelector('.btn-download');
                    if (menu) {
                        menu.classList.remove('show');
                    }
                    if (btn) {
                        btn.classList.remove('active');
                    }
                }
            });

            // Handle assign dropdown
            const assignDropdownContainer = document.querySelector('.assign-dropdown');
            if (assignDropdownContainer && !assignDropdownContainer.contains(event.target)) {
                const assignDropdown = document.getElementById('assignDropdown');
                const assignButton = assignDropdownContainer.querySelector('.btn-assign');
                if (assignDropdown) {
                    assignDropdown.classList.remove('show');
                }
                if (assignButton) {
                    assignButton.classList.remove('active');
                }
            }

            // Handle custom lecturer dropdowns
            if (openDropdown && !event.target.closest('.custom-dropdown')) {
                const openDropdownElement = document.getElementById(openDropdown);
                if (openDropdownElement) {
                    openDropdownElement.classList.remove('show');
                    openDropdown = null;
                }
            }

            // Handle modals - close when clicking on modal backdrop
            const modals = [
                document.getElementById('assignQuotaModal'),
                document.getElementById('clearAllModal'),
                document.getElementById('downloadModal'),
                document.getElementById('saveModal'),
                document.getElementById('resetModal')
            ];

            modals.forEach(modal => {
                if (modal && event.target === modal) {
                    closeModal(modal);
                }
            });
        });

        // Ensure the collapsed state is set immediately on page load
        window.onload = function () {
            closeNav();
        };
    </script>
</body>
</html>

