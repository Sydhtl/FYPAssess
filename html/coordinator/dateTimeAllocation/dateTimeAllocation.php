<?php 
include '../../../php/coordinator_bootstrap.php';

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

// Get course codes for sections (first two courses)
$courseCodeA = !empty($courses[0]) ? $courses[0]['Course_Code'] : 'SWE4949-A';
$courseCodeB = !empty($courses[1]) ? $courses[1]['Course_Code'] : 'SWE4949-B';
$courseIdA = !empty($courses[0]) ? $courses[0]['Course_ID'] : null;
$courseIdB = !empty($courses[1]) ? $courses[1]['Course_ID'] : null;

// Fetch assessments for each course
$assessmentsA = [];
$assessmentsB = [];

if ($courseIdA) {
    $assessmentsQuery = "SELECT Assessment_ID, Assessment_Name FROM assessment WHERE Course_ID = ? ORDER BY Assessment_Name";
    if ($stmt = $conn->prepare($assessmentsQuery)) {
        $stmt->bind_param("i", $courseIdA);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $assessmentsA[] = $row;
            }
        }
        $stmt->close();
    }
}

if ($courseIdB) {
    $assessmentsQuery = "SELECT Assessment_ID, Assessment_Name FROM assessment WHERE Course_ID = ? ORDER BY Assessment_Name";
    if ($stmt = $conn->prepare($assessmentsQuery)) {
        $stmt->bind_param("i", $courseIdB);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $assessmentsB[] = $row;
            }
        }
        $stmt->close();
    }
}

// Encode assessments as JSON for JavaScript
$assessmentsAJson = json_encode($assessmentsA);
$assessmentsBJson = json_encode($assessmentsB);
$courseIdAJson = json_encode($courseIdA);
$courseIdBJson = json_encode($courseIdB);
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Date & Time Allocation</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/dateTimeAllocation.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="date-time-allocation-page">

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
                <a href="../markSubmission/markSubmission.php" id="markSubmission"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Signature Submission</a>
                <a href="dateTimeAllocation.php" id="dateTimeAllocation" class="active-menu-item"><i class="bi bi-calendar-event-fill icon-padding"></i> Date & Time Allocation</a>
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
        <h1 class="page-title">Date &amp; Time Allocation</h1>

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

        <div class="allocation-container">
           

            <div class="tab-buttons" id="tabButtons">
                <!-- Tabs will be dynamically generated -->
            </div>

            <div class="allocation-top-actions">
                <button class="btn-add-task" onclick="addNewTask(activeTab)">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add New Task</span>
                </button>
            </div>

            <div class="table-scroll-container" id="tableContainer">
                <!-- Tables will be dynamically generated -->
            </div>

            <div class="allocation-footer">
                <div class="actions">
                    <button class="btn btn-light border" onclick="resetAllocations(activeTab)">Cancel</button>
                    <button class="btn btn-success" onclick="saveAllocations(activeTab)">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div id="successModal" class="custom-modal"></div>
    <div id="resetModal" class="custom-modal"></div>

    <script>
        // --- FILTER RELOAD FUNCTION ---
        function reloadPageWithFilters() {
            // Clear existing allocations
            Object.keys(dateTimeAllocations).forEach(key => {
                dateTimeAllocations[key] = [];
            });
            
            // Reload due dates with new filters
            loadDueDates();
        }

        const collapsedWidth = "60px";
        const expandedWidth = "220px";

        const dateTimeAllocations = {}; // Will be populated from database
        const courses = []; // Will be loaded from database
        const assessmentsByCourse = {}; // Cache assessments by course_id
        const courseIdMap = {}; // Maps course code to course_id
        let activeTab = null;
        let pendingResetTab = null;
        let nextTaskId = 1;

        // Load courses and initialize the page
        async function loadCoursesAndInitialize() {
            try {
                const response = await fetch('../../../php/phpCoordinator/fetch_courses.php');
                const data = await response.json();
                
                if (data.success && data.courses) {
                    courses.length = 0;
                    courses.push(...data.courses);
                    
                    // Create course ID map
                    courses.forEach(course => {
                        const tabKey = course.course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                        courseIdMap[tabKey] = course.course_id;
                        dateTimeAllocations[tabKey] = [];
                    });
                    
                    createTabs();
                    loadDueDates();
                } else {
                    console.error('Failed to load courses:', data.error);
                }
            } catch (error) {
                console.error('Error loading courses:', error);
            }
        }

        // Create tabs dynamically based on courses
        function createTabs() {
            const tabButtons = document.getElementById('tabButtons');
            const tableContainer = document.getElementById('tableContainer');
            
            tabButtons.innerHTML = '';
            tableContainer.innerHTML = '';
            
            courses.forEach((course, index) => {
                const tabKey = course.course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                
                // Create tab button
                const tabButton = document.createElement('button');
                tabButton.className = 'task-tab' + (index === 0 ? ' active-tab' : '');
                tabButton.setAttribute('data-tab', tabKey);
                tabButton.textContent = course.course_code;
                tabButton.addEventListener('click', () => setActiveTab(tabKey));
                tabButtons.appendChild(tabButton);
                
                // Create table
                const table = document.createElement('table');
                table.className = 'allocation-table';
                table.id = `allocationTable-${tabKey}`;
                table.style.display = index === 0 ? 'table' : 'none';
                
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Task</th>
                            <th>Start Date</th>
                            <th>Start Time</th>
                            <th>End Date</th>
                            <th>End Time</th>
                            <th>Role</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                `;
                tableContainer.appendChild(table);
            });
            
            if (courses.length > 0) {
                const firstTabKey = courses[0].course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                activeTab = firstTabKey;
                setActiveTab(firstTabKey);
            }
        }

        // Load assessments for a course
        async function loadAssessmentsForCourse(courseId) {
            if (assessmentsByCourse[courseId]) {
                return assessmentsByCourse[courseId];
            }
            
            try {
                const response = await fetch(`../../../php/phpCoordinator/fetch_assessments.php?course_id=${courseId}`);
                const data = await response.json();
                
                if (data.success && data.assessments) {
                    assessmentsByCourse[courseId] = data.assessments;
                    return data.assessments;
                }
            } catch (error) {
                console.error('Error loading assessments:', error);
            }
            
            return [];
        }

        // Get FYP_Session_ID from year and semester
        async function getFypSessionId(year, semester) {
            if (!year || !semester) {
                return null;
            }
            
            try {
                // Get FYP_Session_IDs for the selected year and semester
                // We'll use the first matching FYP_Session_ID for the coordinator's courses
                const response = await fetch(`../../../php/phpCoordinator/fetch_due_dates.php?year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}`);
                const data = await response.json();
                
                if (data.success && data.fyp_session_ids && data.fyp_session_ids.length > 0) {
                    // Use the first FYP_Session_ID that matches the selected year and semester
                    // Since all should be for the same year/semester, any one will work
                    return data.fyp_session_ids[0];
                }
            } catch (error) {
                console.error('Error getting FYP_Session_ID:', error);
            }
            return null;
        }

        // Load existing due dates from database
        async function loadDueDates() {
            try {
                // Clear existing allocations
                Object.keys(dateTimeAllocations).forEach(key => {
                    dateTimeAllocations[key] = [];
                });
                
                const year = document.getElementById('yearFilter')?.value || '';
                const semester = document.getElementById('semesterFilter')?.value || '';
                
                if (!year || !semester) {
                    // If no filters, show empty tables
                    courses.forEach(course => {
                        const tabKey = course.course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                        renderAllocationTable(tabKey);
                    });
                    return;
                }
                
                const response = await fetch(`../../../php/phpCoordinator/fetch_due_dates.php?year=${encodeURIComponent(year)}&semester=${encodeURIComponent(semester)}`);
                const data = await response.json();
                
                if (data.success && data.allocations) {
                    // Only show tasks that have due dates assigned
                    data.allocations.forEach(allocation => {
                        // Only process if there are due dates
                        if (!allocation.due_dates || allocation.due_dates.length === 0) {
                            return; // Skip assessments without due dates
                        }
                        
                        const tabKey = allocation.course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                        
                        if (!dateTimeAllocations[tabKey]) {
                            dateTimeAllocations[tabKey] = [];
                        }
                        
                        // Create task entry with due dates
                        const task = {
                            id: nextTaskId++,
                            assessment_id: allocation.assessment_id,
                            assessment_name: allocation.assessment_name,
                            course_id: allocation.course_id,
                            allocations: []
                        };
                        
                        // Add due dates
                        allocation.due_dates.forEach(dueDate => {
                            task.allocations.push({
                                due_id: dueDate.due_id || 0,
                                start_date: dueDate.start_date || '',
                                start_time: dueDate.start_time || '',
                                end_date: dueDate.end_date || '',
                                end_time: dueDate.end_time || '',
                                role: dueDate.role || ''
                            });
                        });
                        
                        dateTimeAllocations[tabKey].push(task);
                    });
                }
                
                // Render all tables (will show empty state if no data)
                courses.forEach(course => {
                    const tabKey = course.course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                    renderAllocationTable(tabKey);
                });
            } catch (error) {
                console.error('Error loading due dates:', error);
                // On error, show empty tables
                courses.forEach(course => {
                    const tabKey = course.course_code.toLowerCase().replace(/[^a-z0-9]/g, '');
                    renderAllocationTable(tabKey);
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            initializeRoleToggle();
            closeNav();
            loadCoursesAndInitialize();

            // Add event listeners for modals
            const successModal = document.getElementById('successModal');
            if (successModal) {
                successModal.addEventListener('click', function(e) {
                    if (e.target === successModal) {
                        closeSuccessModal();
                    }
                });
            }

            const resetModal = document.getElementById('resetModal');
            if (resetModal) {
                resetModal.addEventListener('click', function(e) {
                    if (e.target === resetModal) {
                        closeResetModal();
                    }
                });
            }

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeSuccessModal();
                    closeResetModal();
                }
            });
        });


        function setActiveTab(tabName) {
            activeTab = tabName;
            document.querySelectorAll('.task-tab').forEach(button => {
                button.classList.toggle('active-tab', button.getAttribute('data-tab') === tabName);
            });

            document.querySelectorAll('.allocation-table').forEach(table => {
                table.style.display = table.id === `allocationTable-${tabName}` ? 'table' : 'none';
            });
        }

        async function addNewTask(tabName) {
            if (!tabName || !courseIdMap[tabName]) {
                alert('Please select a course tab first');
                return;
            }
            
            const courseId = courseIdMap[tabName];
            const assessments = await loadAssessmentsForCourse(courseId);
            
            if (assessments.length === 0) {
                alert('No assessments available for this course');
                return;
            }
            
            const newTask = {
                id: nextTaskId++,
                assessment_id: assessments[0].assessment_id,
                assessment_name: assessments[0].assessment_name,
                course_id: courseId,
                allocations: [
                    {
                        due_id: 0,
                        start_date: '',
                        start_time: '',
                        end_date: '',
                        end_time: '',
                        role: ''
                    }
                ]
            };
            
            if (!dateTimeAllocations[tabName]) {
                dateTimeAllocations[tabName] = [];
            }
            
            dateTimeAllocations[tabName].push(newTask);
            renderAllocationTable(tabName);
        }

        function addAllocation(taskId, tabName) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task) {
                task.allocations.push({
                    due_id: 0,
                    start_date: '',
                    start_time: '',
                    end_date: '',
                    end_time: '',
                    role: ''
                });
                renderAllocationTable(tabName);
            }
        }

        function removeAllocation(taskId, allocationIndex, tabName) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task) {
                if (task.allocations.length > 1) {
                    task.allocations.splice(allocationIndex, 1);
                } else {
                    task.allocations[0] = {
                        due_id: 0,
                        start_date: '',
                        start_time: '',
                        end_date: '',
                        end_time: '',
                        role: ''
                    };
                }
                renderAllocationTable(tabName);
            }
        }

        function deleteTask(taskId, tabName) {
            const tabTasks = dateTimeAllocations[tabName];
            const index = tabTasks.findIndex(item => item.id === taskId);
            if (index !== -1) {
                tabTasks.splice(index, 1);
                renderAllocationTable(tabName);
            }
        }

        async function updateTaskName(taskId, tabName, value) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task && value) {
                const assessmentId = parseInt(value);
                const courseId = courseIdMap[tabName];
                const assessments = await loadAssessmentsForCourse(courseId);
                const selectedAssessment = assessments.find(a => a.assessment_id === assessmentId);
                
                if (selectedAssessment) {
                    task.assessment_id = selectedAssessment.assessment_id;
                    task.assessment_name = selectedAssessment.assessment_name;
                }
            }
        }

        function updateAllocationField(taskId, allocationIndex, field, value, tabName) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task && task.allocations[allocationIndex]) {
                task.allocations[allocationIndex][field] = value;
            }
        }

        async function renderAllocationTable(tabName) {
            const tableBody = document.querySelector(`#allocationTable-${tabName} tbody`);
            if (!tableBody) return;
            tableBody.innerHTML = '';

            const tasks = dateTimeAllocations[tabName] || [];

            if (tasks.length === 0) {
                const emptyRow = document.createElement('tr');
                const year = document.getElementById('yearFilter')?.value || '';
                const semester = document.getElementById('semesterFilter')?.value || '';
                
                if (year && semester) {
                    emptyRow.innerHTML = `<td colspan="8" class="empty-state">No due dates assigned for the selected year and semester. Click "Add New Task" to begin.</td>`;
                } else {
                    emptyRow.innerHTML = `<td colspan="8" class="empty-state">Please select Year and Semester to view due dates.</td>`;
                }
                tableBody.appendChild(emptyRow);
                return;
            }

            const courseId = courseIdMap[tabName];
            const assessments = await loadAssessmentsForCourse(courseId);

            for (let index = 0; index < tasks.length; index++) {
                const task = tasks[index];
                const rowspan = Math.max(task.allocations.length, 1);
                
                for (let allocationIndex = 0; allocationIndex < task.allocations.length; allocationIndex++) {
                    const allocation = task.allocations[allocationIndex];
                    const row = document.createElement('tr');

                    if (allocationIndex === 0) {
                        // Build assessment dropdown options
                        let assessmentOptions = '<option value="">Select assessment...</option>';
                        assessments.forEach(assessment => {
                            const selected = task.assessment_id === assessment.assessment_id ? 'selected' : '';
                            assessmentOptions += `<option value="${assessment.assessment_id}" ${selected}>${(assessment.assessment_name || '').replace(/"/g, '&quot;')}</option>`;
                        });
                        
                        row.innerHTML += `
                            <td rowspan="${rowspan}">${index + 1}.</td>
                            <td rowspan="${rowspan}">
                                <select class="task-select" onchange="updateTaskName(${task.id}, '${tabName}', this.value)">
                                    ${assessmentOptions}
                                </select>
                            </td>
                        `;
                    }

                    row.innerHTML += `
                        <td>
                            <input type="date" class="date-input" value="${allocation.start_date || ''}" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'start_date', this.value, '${tabName}')">
                        </td>
                        <td>
                            <input type="time" class="time-input" value="${allocation.start_time || ''}" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'start_time', this.value, '${tabName}')">
                        </td>
                        <td>
                            <input type="date" class="date-input" value="${allocation.end_date || ''}" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'end_date', this.value, '${tabName}')">
                        </td>
                        <td>
                            <input type="time" class="time-input" value="${allocation.end_time || ''}" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'end_time', this.value, '${tabName}')">
                        </td>
                        <td>
                            <select class="role-select" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'role', this.value, '${tabName}')">
                                <option value="" ${allocation.role === '' ? 'selected' : ''}>Select role...</option>
                                <option value="Coordinator" ${allocation.role === 'Coordinator' ? 'selected' : ''}>Coordinator</option>
                                <option value="Supervisor" ${allocation.role === 'Supervisor' ? 'selected' : ''}>Supervisor</option>
                                <option value="Student" ${allocation.role === 'Student' ? 'selected' : ''}>Student</option>
                                <option value="Assessor" ${allocation.role === 'Assessor' ? 'selected' : ''}>Assessor</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn-delete-allocation" onclick="removeAllocation(${task.id}, ${allocationIndex}, '${tabName}')" title="Remove allocation">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    `;

                    tableBody.appendChild(row);
                }

                const addRow = document.createElement('tr');
                addRow.classList.add('add-allocation-row');
                addRow.innerHTML = `
                    <td colspan="8">
                        <div class="allocation-row-actions">
                            <button class="btn-add-allocation" onclick="addAllocation(${task.id}, '${tabName}')">
                                <i class="bi bi-plus-circle"></i>
                                <span>Add Role Allocation</span>
                            </button>
                            <button class="btn-delete-task" onclick="deleteTask(${task.id}, '${tabName}')">
                                <i class="bi bi-trash"></i>
                                <span>Delete Task</span>
                            </button>
                        </div>
                    </td>
                `;
                tableBody.appendChild(addRow);
            }
        }

        function resetAllocations(tabName) {
            pendingResetTab = tabName;
            
            const resetModal = document.getElementById('resetModal');
            if (!resetModal) return;
            
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" onclick="closeResetModal()">&times;</span>
                        <div class="modal-title-custom">Reset Allocations</div>
                        <div class="modal-message">Are you sure you want to reset allocations for this tab?</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button class="btn btn-light border" onclick="closeResetModal()">Cancel</button>
                            <button class="btn btn-success" onclick="confirmReset()">Yes, Reset</button>
                        </div>
                    </div>
                </div>`;
            resetModal.style.display = 'flex';
        }

        function closeResetModal() {
            const resetModal = document.getElementById('resetModal');
            if (resetModal) {
                resetModal.style.display = 'none';
                resetModal.innerHTML = '';
            }
        }

        function confirmReset() {
            if (!pendingResetTab) {
                closeResetModal();
                return;
            }

            dateTimeAllocations[pendingResetTab] = [];
            renderAllocationTable(pendingResetTab);
            pendingResetTab = null;
            closeResetModal();
        }

        async function saveAllocations(tabName) {
            if (!tabName || !dateTimeAllocations[tabName]) {
                alert('No data to save');
                return;
            }
            
            const year = document.getElementById('yearFilter')?.value || '';
            const semester = document.getElementById('semesterFilter')?.value || '';
            
            if (!year || !semester) {
                alert('Please select both Year and Semester before saving.');
                return;
            }
            
            // Get FYP_Session_ID
            const fypSessionId = await getFypSessionId(year, semester);
            if (!fypSessionId) {
                alert('Unable to determine FYP Session. Please check your year and semester selection.');
                return;
            }
            
            const tasks = dateTimeAllocations[tabName];
            const allocationsToSave = [];
            
            tasks.forEach(task => {
                if (task.assessment_id && task.allocations && task.allocations.length > 0) {
                    const validDueDates = task.allocations.filter(allocation => 
                        allocation.start_date && allocation.start_time && 
                        allocation.end_date && allocation.end_time && 
                        allocation.role
                    );
                    
                    if (validDueDates.length > 0) {
                        allocationsToSave.push({
                            assessment_id: task.assessment_id,
                            due_dates: validDueDates
                        });
                    }
                }
            });
            
            if (allocationsToSave.length === 0) {
                alert('Please fill in all required fields (dates, times, and roles) before saving.');
                return;
            }
            
            try {
                const response = await fetch('../../../php/phpCoordinator/save_due_dates.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        fyp_session_id: fypSessionId,
                        allocations: allocationsToSave
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Show success modal
                    const successModal = document.getElementById('successModal');
                    if (successModal) {
                        successModal.innerHTML = `
                            <div class="modal-dialog">
                                <div class="modal-content-custom">
                                    <span class="close-btn" onclick="closeSuccessModal()">&times;</span>
                                    <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                                    <div class="modal-title-custom">Saved Successfully!</div>
                                    <div class="modal-message">Task allocations have been saved successfully.</div>
                                    <div style="display:flex; justify-content:center;">
                                        <button class="btn btn-success" onclick="closeSuccessModal()">OK</button>
                                    </div>
                                </div>
                            </div>`;
                        successModal.style.display = 'flex';
                    }
                    
                    // Reload data from database to get updated due_ids
                    await loadDueDates();
                } else {
                    alert('Error saving: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error saving allocations:', error);
                alert('Error saving allocations: ' + error.message);
            }
        }

        function closeSuccessModal() {
            const successModal = document.getElementById('successModal');
            if (successModal) {
                successModal.style.display = 'none';
                successModal.innerHTML = '';
            }
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

            setActiveMenuItem('dateTimeAllocation');

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
    </script>
</body>
</html>
