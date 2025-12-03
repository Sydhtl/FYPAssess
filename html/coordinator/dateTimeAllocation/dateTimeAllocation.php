<?php include '../../../php/coordinator_bootstrap.php'; ?>
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
           

            <div class="tab-buttons">
                <button class="task-tab active-tab" data-tab="swe4949a">SWE4949-A</button>
                <button class="task-tab" data-tab="swe4949b">SWE4949-B</button>
            </div>

            <div class="allocation-top-actions">
                <button class="btn-add-task" onclick="addNewTask(activeTab)">
                    <i class="bi bi-plus-circle"></i>
                    <span>Add New Task</span>
                </button>
            </div>

            <div class="table-scroll-container">
                <table class="allocation-table" id="allocationTable-swe4949a">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Task</th>
                            <th>Start Date &amp; Time</th>
                            <th>End Date &amp; Time</th>
                            <th>Role</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <table class="allocation-table" id="allocationTable-swe4949b" style="display:none;">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Task</th>
                            <th>Start Date &amp; Time</th>
                            <th>End Date &amp; Time</th>
                            <th>Role</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
            const yearFilter = document.getElementById('yearFilter').value;
            const semesterFilter = document.getElementById('semesterFilter').value;
            
            // Build URL with query parameters
            const params = new URLSearchParams();
            if (yearFilter) params.append('year', yearFilter);
            if (semesterFilter) params.append('semester', semesterFilter);
            
            // Reload page with new parameters
            window.location.href = 'dateTimeAllocation.php?' + params.toString();
        }

        const collapsedWidth = "60px";
        const expandedWidth = "220px";

        const dateTimeAllocations = {
            swe4949a: [
                {
                    id: 1,
                    task: 'Proposal Submission',
                    allocations: [
                        {
                            start: '2025-08-09T09:00',
                            end: '2025-08-09T10:00',
                            role: 'Coordinator'
                        },
                        {
                            start: '2025-08-09T13:00',
                            end: '2025-08-09T14:00',
                            role: 'Assessor'
                        }
                    ]
                },
                {
                    id: 2,
                    task: 'Progress Presentation',
                    allocations: [
                        {
                            start: '2025-08-15T10:00',
                            end: '2025-08-15T12:00',
                            role: 'Assessor'
                        }
                    ]
                }
            ],
            swe4949b: [
                {
                    id: 3,
                    task: 'Proposal Submission',
                    allocations: [
                        {
                            start: '2025-08-10T09:00',
                            end: '2025-08-10T10:30',
                            role: 'Coordinator'
                        }
                    ]
                }
            ]
        };

        let activeTab = 'swe4949a';
        let pendingResetTab = null;
        let nextTaskId = 4;

        document.addEventListener('DOMContentLoaded', function () {
            initializeRoleToggle();
            closeNav();
            initializeTabs();
            renderAllocationTable('swe4949a');
            renderAllocationTable('swe4949b');

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

        function initializeTabs() {
            const tabButtons = document.querySelectorAll('.task-tab');
            tabButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const tabName = this.getAttribute('data-tab');
                    setActiveTab(tabName);
                });
            });
        }

        function setActiveTab(tabName) {
            activeTab = tabName;
            document.querySelectorAll('.task-tab').forEach(button => {
                button.classList.toggle('active-tab', button.getAttribute('data-tab') === tabName);
            });

            document.getElementById('allocationTable-swe4949a').style.display = tabName === 'swe4949a' ? 'table' : 'none';
            document.getElementById('allocationTable-swe4949b').style.display = tabName === 'swe4949b' ? 'table' : 'none';
        }

        function addNewTask(tabName) {
            const newTask = {
                id: nextTaskId++,
                task: '',
                allocations: [
                    {
                        start: '',
                        end: '',
                        role: ''
                    }
                ]
            };
            dateTimeAllocations[tabName].push(newTask);
            renderAllocationTable(tabName);
        }

        function addAllocation(taskId, tabName) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task) {
                task.allocations.push({ start: '', end: '', role: '' });
                renderAllocationTable(tabName);
            }
        }

        function removeAllocation(taskId, allocationIndex, tabName) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task) {
                if (task.allocations.length > 1) {
                    task.allocations.splice(allocationIndex, 1);
                } else {
                    task.allocations[0] = { start: '', end: '', role: '' };
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

        function updateTaskName(taskId, tabName, value) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task) {
                task.task = value;
            }
        }

        function updateAllocationField(taskId, allocationIndex, field, value, tabName) {
            const task = dateTimeAllocations[tabName].find(item => item.id === taskId);
            if (task && task.allocations[allocationIndex]) {
                task.allocations[allocationIndex][field] = value;
            }
        }

        function renderAllocationTable(tabName) {
            const tableBody = document.querySelector(`#allocationTable-${tabName} tbody`);
            if (!tableBody) return;
            tableBody.innerHTML = '';

            const tasks = dateTimeAllocations[tabName];

            if (tasks.length === 0) {
                const emptyRow = document.createElement('tr');
                emptyRow.innerHTML = `<td colspan="6" class="empty-state">No tasks added yet. Click "Add New Task" to begin.</td>`;
                tableBody.appendChild(emptyRow);
                return;
            }

            tasks.forEach((task, index) => {
                const rowspan = Math.max(task.allocations.length, 1);
                task.allocations.forEach((allocation, allocationIndex) => {
                    const row = document.createElement('tr');

                    if (allocationIndex === 0) {
                        row.innerHTML += `
                            <td rowspan="${rowspan}">${index + 1}.</td>
                            <td rowspan="${rowspan}">
                                <input type="text" class="task-input" value="${(task.task || '').replace(/"/g, '&quot;')}" placeholder="Enter task name..." onchange="updateTaskName(${task.id}, '${tabName}', this.value)">
                            </td>
                        `;
                    }

                    row.innerHTML += `
                        <td>
                            <input type="datetime-local" class="datetime-input" value="${allocation.start || ''}" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'start', this.value, '${tabName}')">
                        </td>
                        <td>
                            <input type="datetime-local" class="datetime-input" value="${allocation.end || ''}" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'end', this.value, '${tabName}')">
                        </td>
                        <td>
                            <select class="role-select" onchange="updateAllocationField(${task.id}, ${allocationIndex}, 'role', this.value, '${tabName}')">
                                <option value="" ${allocation.role === '' ? 'selected' : ''}>Select role...</option>
                                <option value="Coordinator" ${allocation.role === 'Coordinator' ? 'selected' : ''}>Coordinator</option>
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
                });

                const addRow = document.createElement('tr');
                addRow.classList.add('add-allocation-row');
                addRow.innerHTML = `
                    <td colspan="6">
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
            });
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

        function saveAllocations(tabName) {
            console.log('Saving allocations for', tabName, JSON.parse(JSON.stringify(dateTimeAllocations[tabName])));
            
            // Show success modal
            const successModal = document.getElementById('successModal');
            if (!successModal) return;
            
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
