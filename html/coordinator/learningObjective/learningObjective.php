<?php include '../../../php/coordinator_bootstrap.php'; ?>
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
                <button class="task-tab active-tab" data-tab="swe4949a">SWE4949-A</button>
                <button class="task-tab" data-tab="swe4949b">SWE4949-B</button>
            </div>

            <div class="task-list-area">
                <!-- SWE4949-A Tab -->
               
                <div class="task-group active" data-group="swe4949a">
                    <div class="learning-objective-container">
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-add-row" onclick="addNewRow('swe4949a')">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Add new row</span>
                                </button>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleDownloadDropdown()">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdown">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949a'); closeDownloadDropdown();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949a'); closeDownloadDropdown();" class="download-option">
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
                                        <th>Task</th>
                                        <th>Learning Objective</th>
                                        <th>Marks</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                                <tbody id="swe4949aTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetLearningObjectives('swe4949a')">Cancel</button>
                                <button class="btn btn-success" onclick="saveLearningObjectives('swe4949a')">Save</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SWE4949-B Tab -->
                <div class="task-group" data-group="swe4949b">
                    <div class="learning-objective-container">
                       
                        <!-- Top Action Bar -->
                        <div class="top-action-bar">
                            <!-- Right Actions: Buttons -->
                            <div class="right-actions">
                                <button class="btn-add-row" onclick="addNewRow('swe4949b')">
                                    <i class="bi bi-plus-circle"></i>
                                    <span>Add new row</span>
                                </button>
                                <div class="download-dropdown">
                                    <button class="btn-download" onclick="toggleDownloadDropdownB()">
                                        <i class="bi bi-download"></i>
                                        <span>Download as...</span>
                                        <i class="bi bi-chevron-down dropdown-arrow"></i>
                                    </button>
                                    <div class="download-dropdown-menu" id="downloadDropdownB">
                                        <a href="javascript:void(0)" onclick="downloadAsPDF('swe4949b'); closeDownloadDropdownB();" class="download-option">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                            <span>Download as PDF</span>
                                        </a>
                                        <a href="javascript:void(0)" onclick="downloadAsExcel('swe4949b'); closeDownloadDropdownB();" class="download-option">
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
                                        <th>Task</th>
                                        <th>Learning Objective</th>
                                        <th>Marks</th>
                                        <th>Delete</th>
                                    </tr>
                                </thead>
                                <tbody id="swe4949bTableBody">
                                    <!-- Table rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>

                        <!-- Sticky Footer -->
                        <div class="table-footer">
                            <div class="actions">
                                <button class="btn btn-light border" onclick="resetLearningObjectives('swe4949b')">Cancel</button>
                                <button class="btn btn-success" onclick="saveLearningObjectives('swe4949b')">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
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

        // Sample learning objective data - each task can have multiple learning objectives
        const learningObjectives = {
            swe4949a: [
                { id: 1, task: "Proposal Report", learningObjectives: [{ objective: "CPS 7(LL)", marks: "" }] },
                { id: 2, task: "Proposal Seminar Presentation", learningObjectives: [{ objective: "CPS 1(C)", marks: "" }] },
                { id: 3, task: "Borang Kemajuan Pelajar", learningObjectives: [] }
            ],
            swe4949b: [
                { id: 1, task: "Final Report", learningObjectives: [] },
                { id: 2, task: "Final Presentation", learningObjectives: [] }
            ]
        };

        // Learning objective options (sample data)
        const learningObjectiveOptions = [
            "CPS 1(C)", "CPS 2(CT)", "CPS 3(PS)", "CPS 4(TS)", "CPS 5(EM)",
            "CPS 6(LL)", "CPS 7(LL)", "CPS 8(ES)", "CPS 9(LS)", "CPS 10(CS)"
        ];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializeTabs();
            renderTable('swe4949a');
            renderTable('swe4949b');
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

            data.forEach((item, index) => {
                if (!item.learningObjectives || item.learningObjectives.length === 0) {
                    item.learningObjectives = [{ objective: '', marks: '' }];
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
                                <input type="text" 
                                       class="task-input" 
                                       value="${(item.task || '').replace(/"/g, '&quot;')}" 
                                       placeholder="Enter task name..."
                                       data-id="${item.id}"
                                       data-tab="${tabName}"
                                       onchange="updateTask(${item.id}, '${tabName}', this.value)" />
                            </td>
                        `;
                    }

                    row.innerHTML += `
                        <td>
                            <div class="learning-objective-input-wrapper">
                                <input type="text" 
                                       class="learning-objective-input" 
                                       value="${obj.objective}" 
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
                    item.learningObjectives[0] = { objective: '', marks: '' };
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
                item.learningObjectives.push({ objective: '', marks: '' });
                renderTable(tabName);
            }
        }
        
        // Update task name
        function updateTask(itemId, tabName, value) {
            const item = learningObjectives[tabName].find(i => i.id === itemId);
            if (item) {
                item.task = value;
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
            console.log('Saving learning objectives:', data);
            
            // Show success modal
            const saveModal = document.getElementById('saveModal');
            saveModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" onclick="closeModal(saveModal)">&times;</span>
                        <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                        <div class="modal-title-custom">Saved Successfully</div>
                        <div class="modal-message">Learning objectives have been saved successfully!</div>
                        <div style="display:flex; justify-content:center;">
                            <button class="btn btn-success" onclick="closeModal(saveModal)">OK</button>
                        </div>
                    </div>
                </div>`;
            saveModal.style.display = 'flex';
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
                const year = document.getElementById('yearFilter').value;
                const semester = document.getElementById('semesterFilter').value;

                // Title
                doc.setFontSize(16);
                doc.setTextColor(120, 0, 0);
                doc.text('Learning Objective', 14, 20);
                
                // Course and session info
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text(`Course: ${tabName.toUpperCase()}`, 14, 30);
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
                                obj.marks || '-'
                            ]);
                        });
                    } else {
                        tableData.push([
                            index + 1,
                            item.task || '-',
                            '-',
                            '-'
                        ]);
                    }
                });

                // Add table
                doc.autoTable({
                    startY: 45,
                    head: [['No.', 'Task', 'Learning Objective', 'Marks']],
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
        function toggleDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }

        function closeDownloadDropdown() {
            const dropdown = document.getElementById('downloadDropdown');
            const button = document.querySelector('.download-dropdown .btn-download');
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
        }

        function toggleDownloadDropdownB() {
            const dropdown = document.getElementById('downloadDropdownB');
            const button = document.querySelectorAll('.download-dropdown .btn-download')[1];
            
            document.querySelectorAll('.download-dropdown-menu.show').forEach(menu => {
                if (menu !== dropdown) {
                    menu.classList.remove('show');
                }
            });
            
            if (dropdown) {
                dropdown.classList.toggle('show');
            }
            if (button) {
                button.classList.toggle('active');
            }
        }

        function closeDownloadDropdownB() {
            const dropdown = document.getElementById('downloadDropdownB');
            const button = document.querySelectorAll('.download-dropdown .btn-download')[1];
            if (dropdown) {
                dropdown.classList.remove('show');
            }
            if (button) {
                button.classList.remove('active');
            }
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

