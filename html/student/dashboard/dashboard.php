<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
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
            <span id="nameSide">HI, NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN</span>
            <a href="../dashboard/dashboard.php" id="dashboard" class="focus"> <i class="bi bi-house-fill" style="padding-right: 10px;"></i>Dashboard</a>
            <a href="../fypInformation/fypInformation.php" id="fypInformation"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>FYP Information</a>
            <a href="../logbook/logbook.php" id="logbookSubmission"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>Logbook Submission</a>
            <a href="../notification/notification.php" id="notification"><i class="bi bi-bell-fill" style="padding-right: 10px;"></i>Notification</a>
            <a href="../signatureUpload/signatureUpload.php" id="signatureSubmission"><i class="bi bi-pen-fill" style="padding-right: 10px;"></i>Signature Submission</a>
          
            <a href="../../login/login.php" id="logout">
                <i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout
            </a>
        </div>
    </div>
    
    <div id="containerAtas" class="containerAtas">
        
        <a href="../dashboard/dashboard.php">
            <img src="../../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>
        
        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule">Student Module</div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode">SWE4949A</div>
                <div id="courseSession">2024/2025 - 2 </div>
            </div>
        </div>
    </div>

   <!-- CRITICAL FIX: Added id="main" here to match the JavaScript code -->
    <div id="main" class="main-grid">
        <!-- Top widgets would go here, maintaining vertical alignment with the section below -->
        <div class="metrics-grid">
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-check-circle"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Title Approval Status</span>
                    <span id="approvalStatus" class="widget-value status-approved">APPROVED</span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-book"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Logbook Progress</span>
                    <span id="logbookProgress" class="widget-value">4/10</span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-clock"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Pending Approval</span>
                    <span id="pendingCount" class="widget-value">2</span>
                </div>
            </div>
        </div>
        <!-- New Two-Column Layout Section -->
        <div class="task-reminder-section">

            <!-- LEFT COLUMN: Logbook Submission Task List -->
            <div class="evaluation-area">
                <h1 class="card-title">Logbook Submission</h1>

                <div class="evaluation-task-card">
                    <div class="tab-buttons">
                        <button class="task-tab active-tab" data-tab="logbook">Logbook Submission</button>
                    </div>
                    <div class="task-list-area">

                        <!-- Logbook Submission View (Active by default) -->
                        <div class="task-group active" data-group="logbook">
                            <div class="graph-container-wrapper">
                                <div id="graphContainer">
                                    <canvas id="barChart"></canvas>
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
                                <li>Reminder Item 1</li>
                                <li>Reminder Item 2</li>
                                <li>Reminder Item 3</li>
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
        // Initialize chart when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('barChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Submitted', 'Waiting for approval', 'Not submitted'],
                        datasets: [{
                            label: 'Logbook Progress (%)', 
                            data: [50, 33, 17], 
                            raw_data: [3, 2, 1], 
                            total_count: 6,
                            backgroundColor: [
                                '#4CAF50', 
                                '#FF9800',
                                '#F44336'
                            ],
                            borderColor: 'transparent', 
                            borderWidth: 0,
                            barPercentage: 0.8,
                            categoryPercentage: 0.8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { font: { size: 12 } },
                                border: { display: true, color: 'black', width: 1 },
                                title: {
                                    display: true,
                                    text: 'Submission',
                                    color: 'black',
                                    font: { size: 14, weight: 'bold' }
                                },
                            },
                            y: {
                                beginAtZero: true,
                                max: 100, 
                                ticks: { display: false },
                                border: { display: true, color: 'black', width: 1 },
                                grid: { display: false },
                                title: {
                                    display: true,
                                    text: 'Total(%)',
                                    color: 'black',
                                    font: { size: 14, weight: 'bold' }
                                },
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            datalabels: {
                                anchor: 'center',
                                align: 'center',
                                color: 'white',
                                font: { weight: 'bold' },
                                formatter: (value, context) => {
                                    return value > 0 ? value : '';
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
                                    const dataset = context.tooltip.dataPoints[0].dataset;
                                    const percentage = dataset.data[dataIndex];
                                    const totalSubmissions = dataset.total_count;
                                    const label = chart.data.labels[dataIndex]; // "Submitted", "Waiting for approval", or "Not submitted"
                                    
                                    let rawCount = 0;
                                    let indicatorColor = '#4CAF50';
                                    
                                    if (dataIndex < 3 && dataset.raw_data) {
                                        rawCount = parseFloat(dataset.raw_data[dataIndex].toFixed(2));
                                    }
                                    
                                    // Determine indicator color based on label
                                    if (label === 'Submitted') {
                                        indicatorColor = '#4CAF50'; // Green
                                    } else if (label === 'Waiting for approval') {
                                        indicatorColor = '#FF9800'; // Orange
                                    } else if (label === 'Not submitted') {
                                        indicatorColor = '#F44336'; // Red
                                    }

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

                                    // Set tooltip content with dark theme
                                    tooltipEl.innerHTML = `
                                        <div class="tooltip-header">${label}</div>
                                        <div class="tooltip-body">
                                            <div class="tooltip-indicator" style="background-color: ${indicatorColor};"></div>
                                            <div class="tooltip-content">
                                                <div>Logbook Submission</div>
                                                <div>Percentage: ${percentage}%</div>
                                                <div>Count: ${rawCount} logs</div>
                                            </div>
                                        </div>
                                    `;
                                }
                            }
                        }
                    }
                });
            }
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
            // Show role headers and other links
            if (links[i].classList.contains('role-header') || links[i].id === 'logout') {
                links[i].style.display = 'flex';
            } else if (links[i].id === 'close') {
                links[i].style.display = 'flex';
            } else {
                links[i].style.display = 'block';
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
   
    function updateApprovalStatusColor(){
        const status = document.getElementById('approvalStatus');
        if(!status) return;
        const statusText = status.textContent.trim().toUpperCase();
        status.className = 'widget-value';

        if(statusText.includes('APPROVED')){
            status.classList.add('status-approved');
        }
        else if(statusText.includes('REJECTED')){
            status.classList.add('status-rejected');
        }
        else{
            status.classList.add('status-pending');
        }
    }
    
    // Ensure the collapsed state is set immediately on page load
    window.onload = function () {
        document.getElementById("nameSide").style.display = "none";
        closeNav();
        updateApprovalStatusColor();
    };
</script>
</body>
</html>