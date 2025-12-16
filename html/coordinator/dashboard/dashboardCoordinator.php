<?php include '../../../php/coordinator_bootstrap.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Coordinator</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>

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
                <a href="#" id="dashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
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
                <a href="dashboardCoordinator.php" id="coordinatorDashboard" class="active-menu-item"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../studentAssignation/studentAssignation.php" id="studentAssignation"><i class="bi bi-people-fill icon-padding"></i> Student Assignment</a>
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
        <a href="dashboardCoordinator.php">
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
        <!-- Top widgets -->
        <div class="metrics-grid">
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-file-circle-check"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Title Submissions</span>
                    <span id="titleSubmissions" class="widget-value">45/50</span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-chart-line"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Overall Progress</span>
                    <span id="overallProgress" class="widget-value">85%</span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-users"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Total Students</span>
                    <span id="totalStudents" class="widget-value">50</span>
                </div>
            </div>
        </div>

        <!-- Chart Section -->
        <div class="task-reminder-section">
            <div class="evaluation-area">
                <h1 class="card-title">Submission Progress</h1>

                <div class="evaluation-task-card">
                    <div class="tab-buttons">
                        <button class="task-tab active-tab" data-tab="title">FYP Title Submission</button>
                        <button class="task-tab" data-tab="swe4949a">SWE4949A</button>
                        <button class="task-tab" data-tab="swe4949b">SWE4949B</button>
                    </div>
                    <div class="task-list-area">

                        <!-- FYP Title Submission Tab -->
                        <div class="task-group active" data-group="title">
                            <div class="graph-container-wrapper">
                                <div id="titleChartContainer" class="chart-container">
                                    <canvas id="titleChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- SWE4949A Tab -->
                        <div class="task-group" data-group="swe4949a">
                            <div class="graph-container-wrapper">
                                <div id="swe4949aChartContainer" class="chart-container">
                                    <canvas id="swe4949aChart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- SWE4949B Tab -->
                        <div class="task-group" data-group="swe4949b">
                            <div class="graph-container-wrapper">
                                <div id="swe4949bChartContainer" class="chart-container">
                                    <canvas id="swe4949bChart"></canvas>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Reminder Section -->
            <div class="reminder-area">
                <h1 class="card-title">Reminder</h1>
                <div class="reminder-card">
                    <div class="reminder-card-content">
                        <div class="reminder-item">
                            <p class="reminder-date">4 August 2025</p>
                            <ul>
                                <li>Mark entry process starts for all supervisors and assessors.</li>
                            </ul>
                        </div>
                        <hr class="reminder-separator">
                        <div class="reminder-item">
                            <p class="reminder-date">22 August 2025</p>
                            <ul>
                                <li>Mark entry process ends for all supervisors and assessors.</li>
                            </ul>
                        </div>
                        <hr class="reminder-separator">
                        <div class="reminder-item">
                            <p class="reminder-date">Reminders</p>
                            <ul>
                                <li>Review pending submissions</li>
                                <li>Update student assignments</li>
                                <li>Schedule evaluation sessions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        .custom-tooltip {
            opacity: 0;
            position: absolute;
            background: transparent;
            pointer-events: none;
            transition: opacity 0.3s;
            z-index: 1000;
            font-family: 'Montserrat', sans-serif;
            min-width: 180px;
        }
        .tooltip-header {
            background-color: #363636;
            color: #ffffff;
            padding: 10px 15px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 4px 4px 0 0;
        }
        .tooltip-body {
            background-color: #1a2a1a;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 0 0 4px 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tooltip-indicator {
            width: 12px;
            height: 12px;
            background-color: #4CAF50;
            border: 1px solid #e0e0e0;
            border-radius: 2px;
            flex-shrink: 0;
        }
        .tooltip-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 13px;
            color: #e0e0e0;
        }
        .tooltip-content div {
            color: #e0e0e0;
        }
    </style>
    <script>
        // Sample data - Replace with actual data from backend
        const titleSubmissionData = {
            titles: ['Title Submission'],
            submitted: [20],
            notSubmitted: [14],
            total: [34]
        };

        const swe4949aData = {
            titles: ['Proposal Report (SV)', 'Seminar Proposal (Assessor)'],
            data: [
                {
                    name: 'Proposal Report (SV)',
                    submitted: 13,
                    notSubmitted: 12,
                    total: 25
                },
                {
                    name: 'Seminar Proposal (Assessor)',
                    submitted: 14,
                    notSubmitted: 11,
                    total: 25
                }
            ]
        };

        const swe4949bData = {
            titles: ['Thesis (SV)', 'Project Demonstration (Assessor)', 'Project Demonstration (SV)', 'Final Seminar Presentation (Assessor)', 'Logbook (SV)'],
            data: [
                {
                    name: 'Thesis (SV)',
                    submitted: 14,
                    notSubmitted: 11,
                    total: 25
                },
                {
                    name: 'Project Demonstration (Assessor)',
                    submitted: 14,
                    notSubmitted: 11,
                    total: 25
                },
                {
                    name: 'Project Demonstration (SV)',
                    submitted: 14,
                    notSubmitted: 11,
                    total: 25
                },
                {
                    name: 'Final Seminar Presentation (Assessor)',
                    submitted: 14,
                    notSubmitted: 11,
                    total: 25
                },
                {
                    name: 'Logbook (SV)',
                    submitted: 14,
                    notSubmitted: 11,
                    total: 25
                }
            ]
        };

        // Function to create bar chart where each title has its own bars with gaps
        function createGroupedBarChart(canvasId, chartData) {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return null;

            // Extract labels and data
            const labels = chartData.titles || chartData.data.map(item => item.name);
            const data = chartData.data || [chartData];

            // Check if this is SWE4949-A or B chart to use Complete/Incomplete labels
            const isSWE4949 = canvasId.includes('swe4949a') || canvasId.includes('swe4949b');
            const positiveLabel = isSWE4949 ? 'Complete' : 'Submitted';
            const negativeLabel = isSWE4949 ? 'Incomplete' : 'Not Submitted';

            // Create datasets for submitted and not submitted
            const submittedData = data.map(item => item.submitted);
            const notSubmittedData = data.map(item => item.notSubmitted);
            const totals = data.map(item => item.total);

            const datasets = [
                {
                    label: positiveLabel,
                    data: submittedData,
                    backgroundColor: '#4CAF50',
                    borderColor: '#4CAF50',
                    borderWidth: 1,
                    barThickness: 'flex',
                    maxBarThickness: 50,
                    categoryPercentage: 0.5,
                    barPercentage: 0.7
                },
                {
                    label: negativeLabel,
                    data: notSubmittedData,
                    backgroundColor: '#F44336',
                    borderColor: '#F44336',
                    borderWidth: 1,
                    barThickness: 'flex',
                    maxBarThickness: 50,
                    categoryPercentage: 0.5,
                    barPercentage: 0.7
                }
            ];

            return new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    layout: {
                        padding: {
                            left: 10,
                            right: 10
                        }
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'point',
                        intersect: true
                    },
                    scales: {
                        x: {
                            stacked: false,
                            grid: { display: false },
                            ticks: { 
                                font: { size: 11 },
                                maxRotation: 45,
                                minRotation: 45
                            },
                            border: { display: true, color: '#000', width: 1 }
                        },
                        y: {
                            beginAtZero: true,
                            max: Math.max(...totals) + 5,
                            ticks: { 
                                stepSize: 5,
                                font: { size: 12 },
                                precision: 0
                            },
                            border: { display: true, color: '#000', width: 1 },
                            grid: { display: false },
                            title: {
                                display: true,
                                text: 'Number of Students',
                                font: { size: 12, weight: 'bold' }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: { size: 12 },
                                padding: 12,
                                boxWidth: 15,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            enabled: false, // Disable default tooltip
                            external: function(context) {
                                // Custom tooltip implementation
                                let tooltipEl = document.getElementById('chartjs-tooltip');
                                
                                // Create tooltip if it doesn't exist
                                if (!tooltipEl) {
                                    tooltipEl = document.createElement('div');
                                    tooltipEl.id = 'chartjs-tooltip';
                                    tooltipEl.className = 'custom-tooltip';
                                    document.body.appendChild(tooltipEl);
                                }
                                
                                // Hide tooltip if no data
                                if (!context.tooltip || context.tooltip.opacity === 0 || !context.tooltip.dataPoints || context.tooltip.dataPoints.length === 0) {
                                    tooltipEl.style.opacity = '0';
                                    tooltipEl.style.pointerEvents = 'none';
                                    return;
                                }

                                const chart = context.chart;
                                const dataPoint = context.tooltip.dataPoints[0];
                                const dataIndex = dataPoint.dataIndex;
                                const datasetIndex = dataPoint.datasetIndex;
                                const dataset = chart.data.datasets[datasetIndex];
                                const value = dataPoint.parsed.y;
                                const total = totals[dataIndex];
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(0) : 0;
                                const count = value.toFixed(2);
                                const statusLabel = dataset.label; // "Submitted"/"Complete" or "Not Submitted"/"Incomplete"
                                const titleLabel = chart.data.labels[dataIndex]; // Title like "Thesis (SV)", "Proposal Report (SV)", etc.

                                // Position tooltip
                                const position = chart.canvas.getBoundingClientRect();
                                const left = position.left + context.tooltip.caretX + window.scrollX;
                                const top = position.top + context.tooltip.caretY + window.scrollY;
                                
                                // Set tooltip position
                                tooltipEl.style.opacity = '1';
                                tooltipEl.style.left = left + 'px';
                                tooltipEl.style.top = top + 'px';
                                tooltipEl.style.transform = 'translate(-50%, -100%)';
                                tooltipEl.style.marginTop = '-10px';
                                tooltipEl.style.pointerEvents = 'none';

                                // Determine indicator color based on status
                                const indicatorColor = (statusLabel === 'Submitted' || statusLabel === 'Complete') ? '#4CAF50' : '#F44336';

                                // Set tooltip content with dark theme - title in header, status in body
                                tooltipEl.innerHTML = `
                                    <div class="tooltip-header">${statusLabel}</div>
                                    <div class="tooltip-body">
                                        <div class="tooltip-indicator" style="background-color: ${indicatorColor};"></div>
                                        <div class="tooltip-content">
                                            <div>${titleLabel}</div>
                                            <div>Percentage: ${percentage}%</div>
                                            <div>Count: ${count} logs</div>
                                        </div>
                                    </div>
                                `;
                            }
                        },
                        datalabels: {
                            display: false
                        }
                    }
                }
            });
        }

        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // FYP Title Submission Chart
            createGroupedBarChart('titleChart', {
                titles: titleSubmissionData.titles,
                data: [{
                    name: titleSubmissionData.titles[0],
                    submitted: titleSubmissionData.submitted[0],
                    notSubmitted: titleSubmissionData.notSubmitted[0],
                    total: titleSubmissionData.total[0]
                }]
            });

            // SWE4949A Chart - 2 tasks
            createGroupedBarChart('swe4949aChart', swe4949aData);

            // SWE4949B Chart - 5 tasks
            createGroupedBarChart('swe4949bChart', swe4949bData);
        });
    </script>

    <script>
        // --- JAVASCRIPT LOGIC ---
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
            var collapsedWidth = "60px";
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

        // Ensure the collapsed state is set immediately on page load
        window.onload = function () {
            closeNav();
        };

        // --- Role Toggle Logic ---
        document.addEventListener('DOMContentLoaded', () => {
            // Store the active menu item ID to restore when menu expands again
            let activeMenuItemId = null;
            
            // Function to set active menu item based on current page
            function setActiveMenuItemBasedOnPage() {
                const currentPage = window.location.pathname;
                const fileName = currentPage.split('/').pop();
                
                // Map page files to menu item IDs
                const pageToMenuItemMap = {
                    'dashboardCoordinator.php': 'coordinatorDashboard',
                    'studentAssignation.php': 'studentAssignation',
                    'learningObjective/learningObjective.php': 'learningObjective',
                    'notification/notification.php': 'coordinatorNotification',
                    'dateTimeAllocation/dateTimeAllocation.php': 'dateTimeAllocation',
                    'markSubmission/markSubmission.php': 'markSubmission',
                    'signatureSubmission.php': 'signatureSubmission',
                    'dateTimeAllocation.php': 'dateTimeAllocation'
                };
                
                // Check if we're in a subdirectory
                if (currentPage.includes('dashboard/')) {
                    return 'coordinatorDashboard';
                } else if (currentPage.includes('studentAssignation/')) {
                    return 'studentAssignation';
                } else if (currentPage.includes('learningObjective/')) {
                    return 'learningObjective';
                } else if (currentPage.includes('notification/')) {
                    return 'coordinatorNotification';
                } else if (currentPage.includes('dateTimeAllocation/')) {
                    return 'dateTimeAllocation';
                } else if (currentPage.includes('markSubmission/')) {
                    return 'markSubmission';
                }
                
                // Get the menu item ID for current page, default to dashboard
                return pageToMenuItemMap[fileName] || 'coordinatorDashboard';
            }
            
            // Initialize active menu item based on current page
            activeMenuItemId = setActiveMenuItemBasedOnPage();
            
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

            // --- Tab Switching Logic ---
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');

            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    const tabName = e.target.getAttribute('data-tab');

                    // 1. Update active tab style
                    tabs.forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');

                    // 2. Switch active task group
                    taskGroups.forEach(group => {
                        if (group.getAttribute('data-group') === tabName) {
                            group.classList.add('active');
                        } else {
                            group.classList.remove('active');
                        }
                    });
                });
            });

            // Default to 'title' tab view on load
            document.querySelector('.task-group[data-group="title"]').classList.add('active');
        });
    </script>
</body>
</html>

