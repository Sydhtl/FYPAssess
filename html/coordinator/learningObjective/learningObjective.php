<?php 
include '../../../php/coordinator_bootstrap.php';
?>
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

// Fetch coordinator's department
$coordinatorDepartmentId = null;
$deptQuery = "SELECT Department_ID FROM lecturer WHERE Lecturer_ID = ? LIMIT 1";
if ($stmt = $conn->prepare($deptQuery)) {
    $stmt->bind_param("s", $userId);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $coordinatorDepartmentId = $row['Department_ID'];
        }
    }
    $stmt->close();
}

// Fetch courses with department information
$courseData = [];
if ($coordinatorDepartmentId) {
    $courseQuery = "SELECT c.Course_ID, c.Course_Code, d.Department_Name, d.Programme_Name 
                    FROM course c
                    INNER JOIN department d ON c.Department_ID = d.Department_ID
                    WHERE c.Department_ID = ?
                    ORDER BY c.Course_Code";
    if ($stmt = $conn->prepare($courseQuery)) {
        $stmt->bind_param("i", $coordinatorDepartmentId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $courseData[$row['Course_ID']] = $row;
            }
        }
        $stmt->close();
    }
}

// Derive base course code from first course (strip trailing section letter)
$baseCourseCode = '';
if (!empty($courseData)) {
    $firstCourse = reset($courseData);
    if (!empty($firstCourse['Course_Code'])) {
        $baseCourseCode = preg_replace('/[-_ ]?[A-Za-z]$/', '', $firstCourse['Course_Code']);
    }
}

// Fetch learning objective allocation data with all related information
$learningObjectiveData = [];
if ($coordinatorDepartmentId) {
    $loQuery = "SELECT 
                    loa.LO_Allocation_ID,
                    loa.Course_ID,
                    loa.Assessment_ID,
                    loa.Criteria_ID,
                    loa.LearningObjective_Code,
                    loa.Percentage,
                    a.Assessment_Name,
                    a.Total_Percentage,
                    lo.LearningObjective_Name,
                    ac.Criteria_Name
                FROM learning_objective_allocation loa
                INNER JOIN fyp_session fs ON loa.FYP_Session_ID = fs.FYP_Session_ID
                INNER JOIN assessment a ON loa.Assessment_ID = a.Assessment_ID
                INNER JOIN learning_objective lo ON loa.LearningObjective_Code = lo.LearningObjective_Code
                LEFT JOIN assessment_criteria ac ON loa.Criteria_ID = ac.Criteria_ID
                INNER JOIN course c ON loa.Course_ID = c.Course_ID
                WHERE c.Department_ID = ?
                  AND fs.FYP_Session = ?
                  AND fs.Semester = ?
                ORDER BY loa.Course_ID, a.Assessment_ID, loa.LearningObjective_Code";
    if ($stmt = $conn->prepare($loQuery)) {
        $stmt->bind_param("isi", $coordinatorDepartmentId, $selectedYear, $selectedSemester);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (!isset($learningObjectiveData[$row['Course_ID']])) {
                    $learningObjectiveData[$row['Course_ID']] = [];
                }
                if (!isset($learningObjectiveData[$row['Course_ID']][$row['Assessment_ID']])) {
                    $learningObjectiveData[$row['Course_ID']][$row['Assessment_ID']] = [
                        'assessment_name' => $row['Assessment_Name'],
                        'total_percentage' => $row['Total_Percentage'],
                        'learning_objectives' => []
                    ];
                }
                $learningObjectiveData[$row['Course_ID']][$row['Assessment_ID']]['learning_objectives'][] = [
                    'code' => $row['LearningObjective_Code'],
                    'name' => $row['LearningObjective_Name'],
                    'percentage' => $row['Percentage'],
                        'criteria_id' => $row['Criteria_ID'],
                        'criteria_name' => $row['Criteria_Name'],
                        'lo_allocation_id' => $row['LO_Allocation_ID']
                ];
            }
        }
        $stmt->close();
    }
}

// Fetch all available learning objectives for dropdown
$allLearningObjectives = [];
$loListQuery = "SELECT LearningObjective_Code, LearningObjective_Name FROM learning_objective ORDER BY LearningObjective_Code";
if ($result = $conn->query($loListQuery)) {
    while ($row = $result->fetch_assoc()) {
        $allLearningObjectives[] = [
            'code' => $row['LearningObjective_Code'],
            'name' => $row['LearningObjective_Name'],
            'display' => $row['LearningObjective_Code'] . ' - ' . $row['LearningObjective_Name']
        ];
    }
    $result->free();
}

// Fetch all assessments for each course
$courseAssessments = [];
if ($coordinatorDepartmentId) {
    $assessmentQuery = "SELECT a.Assessment_ID, a.Course_ID, a.Assessment_Name, a.Total_Percentage
                        FROM assessment a
                        INNER JOIN course c ON a.Course_ID = c.Course_ID
                        WHERE c.Department_ID = ?
                        ORDER BY a.Course_ID, a.Assessment_Name";
    if ($stmt = $conn->prepare($assessmentQuery)) {
        $stmt->bind_param("i", $coordinatorDepartmentId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (!isset($courseAssessments[$row['Course_ID']])) {
                    $courseAssessments[$row['Course_ID']] = [];
                }
                $courseAssessments[$row['Course_ID']][] = [
                    'assessment_id' => $row['Assessment_ID'],
                    'assessment_name' => $row['Assessment_Name'],
                    'total_percentage' => $row['Total_Percentage']
                ];
            }
        }
        $stmt->close();
    }
}

// Fetch assessment criteria for each assessment
$assessmentCriteria = [];
if ($coordinatorDepartmentId) {
    $criteriaQuery = "SELECT ac.Criteria_ID, ac.Assessment_ID, ac.Criteria_Name
                      FROM assessment_criteria ac
                      INNER JOIN assessment a ON ac.Assessment_ID = a.Assessment_ID
                      INNER JOIN course c ON a.Course_ID = c.Course_ID
                      WHERE c.Department_ID = ?
                      ORDER BY ac.Assessment_ID, ac.Criteria_Name";
    if ($stmt = $conn->prepare($criteriaQuery)) {
        $stmt->bind_param("i", $coordinatorDepartmentId);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                if (!isset($assessmentCriteria[$row['Assessment_ID']])) {
                    $assessmentCriteria[$row['Assessment_ID']] = [];
                }
                $assessmentCriteria[$row['Assessment_ID']][] = [
                    'criteria_id' => $row['Criteria_ID'],
                    'criteria_name' => $row['Criteria_Name']
                ];
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Learning Objective</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/learningObjective.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
</head>
<body class="learning-objective-page">

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
                <a href="../studentAssignation/studentAssignation.php" id="studentAssignation"><i class="bi bi-people-fill icon-padding"></i> Student Assignation</a>
                <a href="learningObjective.php" id="learningObjective" class="active-menu-item"><i class="bi bi-book-fill icon-padding"></i> Learning Objective</a>
                <a href="../markSubmission/markSubmission.php" id="markSubmission"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../signatureSubmission/signatureSubmission.php" id="signatureSubmission"><i class="bi bi-pen-fill icon-padding"></i> Signature Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Date and Time Allocation</a>
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
                <div id="courseCode"><?php echo htmlspecialchars($baseCourseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars($selectedYear . ' - ' . $selectedSemester); ?></div>
            </div>
        </div>
    </div>

    <div id="main" class="main-grid">
        <h1 class="page-title">Learning Objective</h1>

        <!-- Filters Section -->
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

        <!-- Tabs -->
        <div class="evaluation-task-card">
            <div class="tab-buttons">
                <?php 
                $isFirst = true;
                foreach ($courseData as $courseId => $course): 
                ?>
                    <button class="task-tab <?php echo $isFirst ? 'active-tab' : ''; ?>" 
                            data-tab="course-<?php echo htmlspecialchars($courseId); ?>"
                            data-course-id="<?php echo htmlspecialchars($courseId); ?>">
                        <?php echo htmlspecialchars($course['Course_Code']); ?>
                    </button>
                <?php 
                    $isFirst = false;
                endforeach; 
                ?>
            </div>

            <div class="task-list-area">
                <?php 
                $isFirstCourse = true;
                foreach ($courseData as $courseId => $course): 
                ?>
                <div class="task-group <?php echo $isFirstCourse ? 'active' : ''; ?>" 
                     data-group="course-<?php echo htmlspecialchars($courseId); ?>">
                    <div class="learning-objective-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-add-row" onclick="addNewRow('course-<?php echo htmlspecialchars($courseId); ?>')">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Add new row</span>
                                </button>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleDownloadDropdown(event, 'course-<?php echo htmlspecialchars($courseId); ?>')">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdown-course-<?php echo htmlspecialchars($courseId); ?>">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('course-<?php echo htmlspecialchars($courseId); ?>'); closeDownloadDropdown(event);" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('course-<?php echo htmlspecialchars($courseId); ?>'); closeDownloadDropdown(event);" class="download-option">
                                            <i class="bi bi-file-earmark-excel"></i>
                                            <span>Download as Excel</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Scrollable Table Container -->
                        <div class="table-scroll-container">
                            <table class="learning-objective-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>Assessment</th>
                                        <th>Learning Objective</th>
                                        <th>Criteria</th>
                                        <th>Percentage</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                                <tbody id="course-<?php echo htmlspecialchars($courseId); ?>TableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetLearningObjectives('course-<?php echo htmlspecialchars($courseId); ?>')">Cancel</button>
                                <button class="btn btn-success" onclick="saveLearningObjectives('course-<?php echo htmlspecialchars($courseId); ?>')">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php 
                    $isFirstCourse = false;
                endforeach; 
                ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="saveModal" class="custom-modal"></div>
    <div id="resetModal" class="custom-modal"></div>
    <div id="downloadModal" class="custom-modal"></div>

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
            window.location.href = 'learningObjective.php?' + params.toString();
        }

        // Learning objective data from database
        const learningObjectives = <?php 
            $jsData = [];
            foreach ($courseData as $courseId => $course) {
                $jsData['course-' . $courseId] = [];
                if (isset($learningObjectiveData[$courseId])) {
                    $rowNum = 1;
                    foreach ($learningObjectiveData[$courseId] as $assessmentId => $assessment) {
                        $jsData['course-' . $courseId][] = [
                            'id' => $rowNum++,
                            'assessment_id' => $assessmentId,
                            'task' => $assessment['assessment_name'],
                            'learningObjectives' => array_map(function($lo) {
                                return [
                                    'objective' => $lo['code'],
                                    'marks' => $lo['percentage'],
                                    'criteria_id' => $lo['criteria_id'],
                                    'criteria_name' => $lo['criteria_name'],
                                    'lo_allocation_id' => $lo['lo_allocation_id']
                                ];
                            }, $assessment['learning_objectives'])
                        ];
                    }
                }
            }
            echo json_encode($jsData);
        ?>;

        // Learning objective options from database
        const learningObjectiveOptions = <?php echo json_encode(array_map(function($lo) {
            return $lo['display'];
        }, $allLearningObjectives)); ?>;

        // Assessment options for each course from database
        const courseAssessments = <?php echo json_encode($courseAssessments); ?>;

        // Criteria options for each assessment from database
        const assessmentCriteria = <?php echo json_encode($assessmentCriteria); ?>;

        // Course metadata for headers
        const courseMeta = <?php echo json_encode(array_map(function($c){
            return ['code' => $c['Course_Code']];
        }, $courseData)); ?>;

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            // Render all course tables
            <?php foreach ($courseData as $courseId => $course): ?>
            renderTable('course-<?php echo $courseId; ?>');
            <?php endforeach; ?>
            initializeRoleToggle();
        });

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

        // Render table for a specific tab
        function renderTable(tabName) {
            const tbody = document.getElementById(`${tabName}TableBody`);
            if (!tbody) return;

            const data = learningObjectives[tabName] || [];
            tbody.innerHTML = '';

            // Get course ID from tabName
            const courseId = tabName.replace('course-', '');
            const assessments = courseAssessments[courseId] || [];

            data.forEach((item, index) => {
                if (!item.learningObjectives || item.learningObjectives.length === 0) {
                    item.learningObjectives = [{ objective: '', marks: '', criteria_id: null }];
                }
                const learningObjectivesList = item.learningObjectives;
                const maxRows = learningObjectivesList.length;
                
                // Create rows for each learning objective (or one empty row if none)
                learningObjectivesList.forEach((obj, i) => {
                    const row = document.createElement('tr');
                    const isFirstRow = i === 0;
                    const rowspan = maxRows;

                    if (isFirstRow) {
                        row.innerHTML += `
                            <td rowspan="${rowspan}">${index + 1}.</td>
                            <td rowspan="${rowspan}">
                                <select class="assessment-select" 
                                        data-id="${item.id}"
                                        data-tab="${tabName}"
                                        onchange="updateAssessment(${item.id}, '${tabName}', this.value, this.options[this.selectedIndex].text)">
                                    <option value="">Select Assessment...</option>
                                    ${generateAssessmentOptions(assessments, item.assessment_id)}
                                </select>
                            </td>
                        `;
                    }

                    row.innerHTML += `
                        <td>
                            <div class="learning-objective-input-wrapper">
                                <input type="text" 
                                       class="learning-objective-input" 
                                       value="${obj.objective || ''}" 
                                       placeholder="Select learning objective..."
                                       data-id="${item.id}"
                                       data-obj-index="${i}"
                                       data-tab="${tabName}"
                                       onclick="showLearningObjectiveDropdown(${item.id}, ${i}, '${tabName}')"
                                       readonly />
                                <div class="learning-objective-dropdown" id="dropdown-${tabName}-${item.id}-${i}">
                                    <div class="dropdown-search">
                                        <i class="bi bi-search"></i>
                                        <input type="text" placeholder="Search learning objective..." oninput="filterLearningObjectives(${item.id}, ${i}, '${tabName}', this.value)" />
                                    </div>
                                    <div class="dropdown-options" id="options-${tabName}-${item.id}-${i}">
                                        ${generateLearningObjectiveOptions(obj.objective, item.id, i, tabName)}
                                    </div>
                                </div>
                                <i class="bi bi-chevron-down dropdown-arrow-icon"></i>
                            </div>
                        </td>
                        <td>
                            <select class="criteria-select"
                                    data-id="${item.id}"
                                    data-obj-index="${i}"
                                    data-tab="${tabName}"
                                    ${item.assessment_id ? '' : 'disabled'}
                                    onchange="updateCriteria(${item.id}, ${i}, '${tabName}', this.value)">
                                <option value="">Select Criteria...</option>
                                ${generateCriteriaOptions(assessmentCriteria[item.assessment_id] || [], obj.criteria_id)}
                            </select>
                        </td>
                        <td>
                            <input type="number" 
                                   class="marks-input" 
                                   value="${obj.marks}" 
                                   min="0"
                                   step="0.5"
                                   placeholder="0"
                                   data-id="${item.id}"
                                   data-obj-index="${i}"
                                   data-tab="${tabName}"
                                   onchange="updateMarks(${item.id}, ${i}, '${tabName}', this.value)" />
                        </td>
                        <td class="delete-cell">
                            <button class="btn-delete-allocation" onclick="removeLearningObjective(${item.id}, ${i}, '${tabName}')" title="Delete learning objective">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Add row for adding learning objectives / deleting task
                const addRow = document.createElement('tr');
                addRow.className = 'add-objective-row';
                addRow.innerHTML = `
                    <td colspan="5">
                        <div class="allocation-row-actions">
                            <button class="btn-add-allocation" onclick="addLearningObjective(${item.id}, '${tabName}')">
                                <i class="bi bi-plus-circle"></i>
                                <span>Add Learning Objective</span>
                            </button>
                            <button class="btn-delete-task" onclick="deleteRow(${item.id}, '${tabName}')">
                                <i class="bi bi-trash"></i>
                                <span>Delete Task</span>
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(addRow);
            });
        }

        // Generate assessment options for dropdown
        function generateAssessmentOptions(assessments, selectedId) {
            let options = '';
            assessments.forEach(assessment => {
                const isSelected = assessment.assessment_id == selectedId;
                options += `<option value="${assessment.assessment_id}" ${isSelected ? 'selected' : ''}>${assessment.assessment_name}</option>`;
            });
            return options;
        }

        // Generate criteria options for dropdown
        function generateCriteriaOptions(criteriaList, selectedId) {
            let options = '';
            criteriaList.forEach(criteria => {
                const isSelected = criteria.criteria_id == selectedId;
                options += `<option value="${criteria.criteria_id}" ${isSelected ? 'selected' : ''}>${criteria.criteria_name}</option>`;
            });
            return options;
        }

        // Generate learning objective options
        function generateLearningObjectiveOptions(selectedValue, itemId, objIndex, tabName) {
            let options = '';
            learningObjectiveOptions.forEach(option => {
                const isSelected = option === selectedValue;
                options += `
                    <div class="dropdown-option ${isSelected ? 'selected' : ''}" 
                         onclick="selectLearningObjective('${option.replace(/'/g, "\\'")}', ${itemId}, ${objIndex}, '${tabName}')">
                        ${option}
                    </div>
                `;
            });
            return options;
        }

        // Show learning objective dropdown
        let openDropdown = null;
        function showLearningObjectiveDropdown(itemId, objIndex, tabName) {
            const dropdownId = `dropdown-${tabName}-${itemId}-${objIndex}`;
            const dropdown = document.getElementById(dropdownId);

            // Close all other dropdowns
            document.querySelectorAll('.learning-objective-dropdown.show').forEach(menu => {
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
                        filterLearningObjectives(itemId, objIndex, tabName, '');
                    }
                } else {
                    openDropdown = null;
                }
            }
        }

        // Filter learning objectives
        function filterLearningObjectives(itemId, objIndex, tabName, searchTerm) {
            const optionsContainer = document.getElementById(`options-${tabName}-${itemId}-${objIndex}`);
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

        // Select learning objective
        function selectLearningObjective(value, itemId, objIndex, tabName) {
            // Update data
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item) {
                if (!item.learningObjectives) {
                    item.learningObjectives = [];
                }
                if (item.learningObjectives[objIndex]) {
                    item.learningObjectives[objIndex].objective = value;
                } else {
                    item.learningObjectives.push({ objective: value, marks: '' });
                }
            }

            // Close dropdown
            const dropdown = document.getElementById(`dropdown-${tabName}-${itemId}-${objIndex}`);
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            openDropdown = null;

            // Re-render the table to show updated structure
            renderTable(tabName);
        }

        // Remove learning objective
        function removeLearningObjective(itemId, objIndex, tabName) {
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item && item.learningObjectives) {
                if (item.learningObjectives.length > 1) {
                    item.learningObjectives.splice(objIndex, 1);
                } else {
                    item.learningObjectives[0] = { objective: '', marks: '', criteria_id: null, criteria_name: '' };
                }
                renderTable(tabName);
            }
        }
        
        // Add learning objective to a task
        function addLearningObjective(itemId, tabName) {
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item) {
                if (!item.learningObjectives) {
                    item.learningObjectives = [];
                }
                item.learningObjectives.push({ objective: '', marks: '', criteria_id: null, criteria_name: '' });
                renderTable(tabName);
            }
        }
        
        // Update assessment selection
        function updateAssessment(itemId, tabName, assessmentId, assessmentName) {
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item) {
                item.assessment_id = parseInt(assessmentId);
                item.task = assessmentName;
                // Reset criteria selections when assessment changes
                if (item.learningObjectives) {
                    item.learningObjectives = item.learningObjectives.map(lo => ({
                        ...lo,
                        criteria_id: null,
                        criteria_name: ''
                    }));
                }
                renderTable(tabName);
            }
        }

        // Update criteria selection
        function updateCriteria(itemId, objIndex, tabName, criteriaId) {
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item && item.learningObjectives && item.learningObjectives[objIndex]) {
                item.learningObjectives[objIndex].criteria_id = criteriaId ? parseInt(criteriaId) : null;
                const critList = assessmentCriteria[item.assessment_id] || [];
                const found = critList.find(c => c.criteria_id == criteriaId);
                item.learningObjectives[objIndex].criteria_name = found ? found.criteria_name : '';
            }
        }

        // Update marks
        function updateMarks(itemId, objIndex, tabName, value) {
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item && item.learningObjectives && item.learningObjectives[objIndex]) {
                item.learningObjectives[objIndex].marks = value || '';
            }
        }

        // Add new row
        function addNewRow(tabName) {
            const data = learningObjectives[tabName] || [];
            const newId = data.length > 0 ? Math.max(...data.map(i => i.id)) + 1 : 1;
            data.push({
                id: newId,
                task: '',
                assessment_id: null,
                learningObjectives: []
            });
            renderTable(tabName);
        }

        // Delete row
        function deleteRow(itemId, tabName) {
            const data = learningObjectives[tabName];
            const index = data.findIndex(i => i.id === itemId);
            if (index > -1) {
                data.splice(index, 1);
                renderTable(tabName);
            }
        }

        // Save learning objectives
        function saveLearningObjectives(tabName) {
            const data = learningObjectives[tabName];
            
            // Extract course ID from tabName (format: "course-123")
            const courseId = tabName.replace('course-', '');
            const year = document.getElementById('yearFilter').value;
            const semester = document.getElementById('semesterFilter').value;
            
            // Validate data before sending
            if (!data || data.length === 0) {
                alert('No data to save. Please add at least one assessment with learning objectives.');
                return;
            }
            
            // Filter out empty assessments (no assessment_id means it's a new row that hasn't been linked to an assessment)
            const validData = data.filter(item => {
                return item.assessment_id && item.learningObjectives && item.learningObjectives.length > 0;
            });
            
            if (validData.length === 0) {
                alert('Please ensure all assessments have learning objectives assigned.');
                return;
            }
            
            // Prepare data for backend - ensure we're sending ALL learning objectives
            const saveData = {
                courseId: courseId,
                year: year,
                semester: semester,
                learningObjectives: validData.map(item => ({
                    assessment_id: item.assessment_id,
                    task: item.task,
                    learningObjectives: item.learningObjectives
                        .filter(lo => lo.objective && lo.objective.trim() !== '' && lo.criteria_id)
                        .map(lo => ({
                            objective: lo.objective,
                            marks: lo.marks,
                            criteria_id: lo.criteria_id || null
                        }))
                }))
            };
            
            console.log('Saving ALL learning objectives for course:', saveData);
            console.log('Total assessments:', saveData.learningObjectives.length);
            console.log('Total LOs:', saveData.learningObjectives.reduce((sum, item) => sum + item.learningObjectives.length, 0));
            
            // Send data to backend
            fetch('../../../php/phpCoordinator/save_learning_objectives.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(saveData)
            })
            .then(response => response.json())
            .then(result => {
                const saveModal = document.getElementById('saveModal');
                if (result.success) {
                    // Show success modal
                    saveModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content-custom">
                                <span class="close-btn" onclick="closeModal(saveModal)">&times;</span>
                                <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                                <div class="modal-title-custom">Saved Successfully</div>
                                <div class="modal-message">${result.message || 'Learning objectives have been saved successfully!'}</div>
                                <div style="display:flex; justify-content:center;">
                                    <button class="btn btn-success" onclick="closeModal(saveModal); location.reload();">OK</button>
                                </div>
                            </div>
                        </div>`;
                } else {
                    // Show error modal
                    saveModal.innerHTML = `
                        <div class="modal-dialog">
                            <div class="modal-content-custom">
                                <span class="close-btn" onclick="closeModal(saveModal)">&times;</span>
                                <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                                <div class="modal-title-custom">Save Failed</div>
                                <div class="modal-message">${result.message || 'An error occurred while saving. Please try again.'}</div>
                                <div style="display:flex; justify-content:center;">
                                    <button class="btn btn-success" onclick="closeModal(saveModal)">OK</button>
                                </div>
                            </div>
                        </div>`;
                }
                saveModal.style.display = 'flex';
            })
            .catch(error => {
                console.error('Error saving learning objectives:', error);
                const saveModal = document.getElementById('saveModal');
                saveModal.innerHTML = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" onclick="closeModal(saveModal)">&times;</span>
                            <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                            <div class="modal-title-custom">Save Failed</div>
                            <div class="modal-message">An error occurred while saving. Please try again.</div>
                            <div style="display:flex; justify-content:center;">
                                <button class="btn btn-success" onclick="closeModal(saveModal)">OK</button>
                            </div>
                        </div>
                    </div>`;
                saveModal.style.display = 'flex';
            });
        }

        // Reset learning objectives
        function resetLearningObjectives(tabName) {
            const resetModal = document.getElementById('resetModal');
            resetModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" onclick="closeModal(resetModal)">&times;</span>
                        <div class="modal-title-custom">Reset Learning Objectives</div>
                        <div class="modal-message">Are you sure you want to reset all changes? All unsaved changes will be lost.</div>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button class="btn btn-light border" onclick="closeModal(resetModal)">Cancel</button>
                            <button class="btn btn-success" onclick="confirmReset('${tabName}')">Reset</button>
                        </div>
                    </div>
                </div>`;
            resetModal.style.display = 'flex';
        }

        function confirmReset(tabName) {
            // Reload page to reset
            location.reload();
        }

        // Download as PDF
        function downloadAsPDF(tabName) {
            try {
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                const data = learningObjectives[tabName] || [];
                const courseId = tabName.replace('course-', '');
                const courseCode = (courseMeta[courseId] && courseMeta[courseId].code) ? courseMeta[courseId].code : tabName.toUpperCase();
                const year = document.getElementById('yearFilter').value;
                const semester = document.getElementById('semesterFilter').value;

                // Title
                doc.setFontSize(16);
                doc.setTextColor(120, 0, 0);
                doc.text('Learning Objective', 14, 20);
                
                // Course and session info
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Course: ${courseCode}`, 14, 30);
                doc.text(`Year: ${year}`, 14, 35);
                doc.text(`Semester: ${semester}`, 14, 40);

                // Prepare table data - flatten multiple learning objectives per task
                const tableData = [];
                data.forEach((item, index) => {
                    if (item.learningObjectives && item.learningObjectives.length > 0) {
                        item.learningObjectives.forEach((obj, objIndex) => {
                            tableData.push([
                                objIndex === 0 ? (index + 1) : '',
                                objIndex === 0 ? (item.task || '-') : '',
                                obj.objective || '-',
                                obj.criteria_name || '-',
                                obj.marks || '-'
                            ]);
                        });
                    } else {
                        tableData.push([
                            index + 1,
                            item.task || '-',
                            '-',
                            '-',
                            '-'
                        ]);
                    }
                });

                // Add table
                doc.autoTable({
                    startY: 45,
                    head: [['No.', 'Task', 'Learning Objective', 'Criteria', 'Marks']],
                    body: tableData,
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

                doc.save(`learning-objective-${tabName}.pdf`);

                // Show success modal
                showDownloadSuccess();
            } catch (error) {
                console.error('Error generating PDF:', error);
                showDownloadError();
            }
        }

        // Download as Excel
        function downloadAsExcel(tabName) {
            const data = learningObjectives[tabName] || [];
            const year = document.getElementById('yearFilter').value;
            const semester = document.getElementById('semesterFilter').value;

            let csvContent = 'No.,Task,Learning Objective,Marks\n';
            
            data.forEach((item, index) => {
                if (item.learningObjectives && item.learningObjectives.length > 0) {
                    item.learningObjectives.forEach((obj, objIndex) => {
                        csvContent += `${objIndex === 0 ? (index + 1) : ''},"${objIndex === 0 ? (item.task || '') : ''}","${obj.objective || ''}","${obj.marks || ''}"\n`;
                    });
                } else {
                    csvContent += `${index + 1},"${item.task || ''}","",""\n`;
                }
            });

            csvContent += `\nCourse,${tabName.toUpperCase()}\n`;
            csvContent += `Year,${year}\n`;
            csvContent += `Semester,${semester}\n`;

            // Create blob and download
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `learning-objective-${tabName}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            // Show success modal
            showDownloadSuccess();
        }

        // Modal functions
        function closeModal(modal) {
            modal.style.display = 'none';
        }

        function showDownloadSuccess() {
            const downloadModal = document.getElementById('downloadModal');
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" onclick="closeModal(downloadModal)">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Download Successful</div>
                        <div class="modal-message">File downloaded successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button class="btn btn-success" onclick="closeModal(downloadModal)">OK</button>
                        </div>
                    </div>
                </div>`;
            downloadModal.style.display = 'flex';
        }

        function showDownloadError() {
            const downloadModal = document.getElementById('downloadModal');
            downloadModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" onclick="closeModal(downloadModal)">&times;</span>
                        <div class="modal-icon" style="color: #dc3545;"><i class="bi bi-exclamation-triangle-fill"></i></div>
                        <div class="modal-title-custom">Download Failed</div>
                        <div class="modal-message">An error occurred while generating the file. Please try again.</div>
                        <div style="display:flex; justify-content:center;">
                            <button class="btn btn-success" onclick="closeModal(downloadModal)">OK</button>
                        </div>
                    </div>
                </div>`;
            downloadModal.style.display = 'flex';
        }

        // Download dropdown functions
        function toggleDownloadDropdown(event, courseId) {
            event.stopPropagation();
            const dropdownId = 'downloadDropdown-' + courseId;
            const dropdown = document.getElementById(dropdownId);
            
            // Close all other dropdowns
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu.id !== dropdownId) {
                    menu.classList.remove('show');
                }
            });
            
            // Toggle current dropdown
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
        }

        function closeDownloadDropdown(event) {
            event.stopPropagation();
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
            });
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            // Handle download dropdowns
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

            // Handle learning objective dropdowns
            if (openDropdown && !event.target.closest('.learning-objective-input-wrapper')) {
                const openDropdownElement = document.getElementById(openDropdown);
                if (openDropdownElement) {
                    openDropdownElement.classList.remove('show');
                    openDropdown = null;
                }
            }

            // Handle modals
            const modals = [
                document.getElementById('saveModal'),
                document.getElementById('resetModal'),
                document.getElementById('downloadModal')
            ];

            modals.forEach(modal => {
                if (modal && event.target === modal) {
                    closeModal(modal);
                }
            });
        });

        // Sidebar navigation functions
        const collapsedWidth = "60px";
        const expandedWidth = "220px";

        function openNav() {
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main");
            var menuIcon = document.querySelector(".menu-icon");

            sidebar.style.width = expandedWidth;
            if (mainContent) mainContent.style.marginLeft = expandedWidth;
            if (header) header.style.marginLeft = expandedWidth;

            document.getElementById("nameSide").style.display = "block";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
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
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main");
            var menuIcon = document.querySelector(".menu-icon");

            sidebar.style.width = collapsedWidth;
            if (mainContent) mainContent.style.marginLeft = collapsedWidth;
            if (header) header.style.marginLeft = collapsedWidth;

            document.getElementById("nameSide").style.display = "none";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                links[i].style.display = "none";
            }

            if (menuIcon) menuIcon.style.display = "block";
        }

        // Initialize role toggle (same as dashboard)
        function initializeRoleToggle() {
            let activeMenuItemId = 'learningObjective';
            
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

            // Set active menu item
            setActiveMenuItem('learningObjective');

            // Role header toggle functionality
            const roleHeaders = document.querySelectorAll('.role-header');
            roleHeaders.forEach(header => {
                header.addEventListener('click', function(e) {
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

        // Ensure the collapsed state is set immediately on page load
        window.onload = function () {
            closeNav();
        };
    </script>
</body>
</html>

