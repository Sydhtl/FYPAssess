<!DOCTYPE html>
<html>

<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../css/assessor/dashboard.css">
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
        <button class="menu-icon" onclick="openNav()"><i class="bi bi-list"></i></button>

        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>

            <span id="nameSide">HI, SAZLINAH BINTI HASSAN</span>

            <a href="#supervisorMenu" class="role-header" data-role="supervisor">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-right arrow-icon"></i>
                </span>
            </a>

            <div id="supervisorMenu" class="menu-items">
                <a href="../supervisor/dashboard.html" id="dashboard"><i class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="#" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i>
                    Industry
                    Collaboration</a>
                <a href="#" id="evaluationForm"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation
                    Form</a>
                <a href="#" id="superviseesReport"><i class="bi bi-bar-chart-fill icon-padding"></i> Supervisees'
                    Report</a>
                <a href="#" id="logbookSubmission"><i class="bi bi-calendar-check-fill icon-padding"></i> Logbook
                    Submission</a>
            </div>

            <a href="#assessorMenu" class="role-header menu-expanded" data-role="assessor">
                <span class="role-text">Assessor</span>
                <span class="arrow-container">
                    <i class="bi bi-chevron-right arrow-icon"></i>
                </span>
            </a>

            <div id="assessorMenu" class="menu-items expanded">
                <a href="dashboard.html" class="active-menu-item active-page" id="Dashboard"><i
                        class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="notification.html" id="Notification"><i class="bi bi-bell-fill icon-padding"></i>
                    Notification</a>
                <a href="evaluationForm.html" id="EvaluationForm"><i
                        class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation
                    Form</a>
            </div>
            <a href="#" id="logout">
                <i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout
            </a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">

        <a href="../dashboard/dashboard.html">
            <img src="../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>

        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule">Assessor Module</div>
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
                <span class="widget-icon"><i class="fa-solid fa-bars-progress"></i></span>
                <div class="widget-content">
                    <span class="widget-title">In-progress task</span>
                    <span id="in-progress-count" class="widget-value">0</span>
                </div>
            </div>
            <div class="widget due-widget">
                <span class="widget-icon"><i class="fa-solid fa-arrow-right"></i></span>
                <div class="widget-content due-content">
                    <span class="widget-title">Upcoming task</span>
                    <span id="due-soon-count" class="widget-value">2</span>
                </div>
            </div>
            <div class="widget">
                <span class="widget-icon"><i class="fa-solid fa-clock"></i></span>
                <div class="widget-content">
                    <span class="widget-title">Evaluation due</span>
                    <span id="completed-count" class="widget-value">10 days</span>
                </div>
            </div>
        </div>
        <!-- Two-Column Layout Section -->
        <div class="task-reminder-section">
            <!-- LEFT COLUMN: Evaluation Task List -->
            <div class="evaluation-area">
                <h1 class="card-title">Evaluation task</h1>
                <div class="evaluation-task-card">
                    <div class="tab-buttons">
                        <button class="task-tab active-tab" data-tab="inprogress">In-progress</button>
                        <button class="task-tab" data-tab="upcoming">Upcoming</button>
                        <button class="task-tab" data-tab="completed">Completed</button>
                    </div>
                    <div class="task-list-area">

                        <div class="task-group active" data-group="inprogress">
                            <div class="task-row header-row four-col-grid">
                                <span class="col-supervisee">Supervisee</span>
                                <span class="col-project-title">Project title</span>
                                <span class="col-assessment-type">Assessment type</span>
                                <span class="col-due-date">Due date</span>
                            </div>
                            <div class="task-row data-row four-col-grid">
                                <span class="col-supervisee">Amirul Afiq Bin Muhammad</span>
                                <span class="col-project-title">Development of an Automated Assessment and Evaluation
                                    for
                                    Bachelor Projects</span>
                                <span class="col-assessment-type">Demonstration<br>Thesis and logbook</span>
                                <span class="col-due-date">24 Nov 2025<br>3 Jan 2026</span>
                            </div>
                            <div class="task-row data-row four-col-grid">
                                <span class="col-supervisee">Harraz Firdaus Bin Hisyam</span>
                                <span class="col-project-title">Virtual Reality Mobile Application for Interactive
                                    Museum
                                    Viewing Experience</span>
                                <span class="col-assessment-type">Proposal report<br>Thesis and logbook</span>
                                <span class="col-due-date">24 Aug 2025<br>3 Jan 2026</span>
                            </div>
                        </div>

                        <div class="task-group" data-group="upcoming">
                            <div class="task-group-header" data-target="#list-aug-9">
                                <i class="fas fa-chevron-right toggle-icon"></i>
                                9 Aug 2025, Wed - Bilik Kuliah A
                            </div>
                            <div id="list-aug-9" class="task-list-details">
                                <div class="task-row header-row five-col-grid">
                                    <span class="col-student">Student</span>
                                    <span class="col-time">Time</span>
                                    <span class="col-project-title">Project title</span>
                                    <span class="col-assessment-type">Assessment type</span>
                                    <span class="col-assessor">Assessor</span>
                                </div>
                                <div class="task-row data-row five-col-grid">
                                    <span class="col-student">Siti Athirah Binti Othman</span>
                                    <span class="col-time">9.00 am</span>
                                    <span class="col-project-title">Development of an Automated Assessment and
                                        Evaluation
                                        for Bachelor Projects</span>
                                    <span class="col-assessment-type">Seminar Proposal</span>
                                    <span class="col-assessor"><i class="fas fa-user-tie"></i></span>
                                </div>
                            </div>

                            <div class="task-group-header" data-target="#list-aug-10">
                                <i class="fas fa-chevron-right toggle-icon"></i>
                                10 Aug 2025, Thu - Bilik Kuliah A
                            </div>
                            <div id="list-aug-10" class="task-list-details">
                                <div class="task-row header-row five-col-grid">
                                    <span class="col-student">Student</span>
                                    <span class="col-time">Time</span>
                                    <span class="col-project-title">Project title</span>
                                    <span class="col-assessment-type">Assessment type</span>
                                    <span class="col-assessor">Assessor</span>
                                </div>
                                <div class="task-row data-row five-col-grid">
                                    <span class="col-student">Atiya Aisya Binti Aiman</span>
                                    <span class="col-time">9.30 am</span>
                                    <span class="col-project-title">Virtual Reality Mobile Application for Interactive
                                        Museum Viewing Experience</span>
                                    <span class="col-assessment-type">Seminar Proposal</span>
                                    <span class="col-assessor"><i class="fas fa-user-tie"></i></span>
                                </div>
                            </div>
                        </div>

                        <div class="task-group" data-group="completed">
                            <div class="task-group-header" data-target="#list-aug-8">
                                <i class="fas fa-chevron-right toggle-icon"></i>
                                8 Aug 2025, Wed - Bilik Kuliah A
                            </div>
                            <div id="list-aug-8" class="task-list-details">
                                <div class="task-row header-row five-col-grid">
                                    <span class="col-student">Student</span>
                                    <span class="col-time">Time</span>
                                    <span class="col-project-title">Project title</span>
                                    <span class="col-assessment-type">Assessment type</span>
                                    <span class="col-assessor">Assessor</span>
                                </div>
                                <div class="task-row data-row five-col-grid">
                                    <span class="col-student">Siti Athirah Binti Othman</span>
                                    <span class="col-time">9.00 am</span>
                                    <span class="col-project-title">Development of an Automated Assessment and
                                        Evaluation
                                        for Bachelor Projects</span>
                                    <span class="col-assessment-type">Seminar Proposal</span>
                                    <span class="col-assessor"><i class="fas fa-user-tie"></i></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Reminder -->
            <div class="reminder-area">
                <h1 class="card-title">Reminder</h1>
                <div class="reminder-card">
                    <div class="reminder-card-content">
                        <div class="reminder-item">
                            <p class="reminder-date">28 October 2025</p>
                            <ul>
                                <li>Assessment rubric for Seminar Demonstration (Assessor) has been updated</li>
                            </ul>
                        </div>
                        <hr class="reminder-separator">
                        <div class="reminder-item">
                            <p class="reminder-date">29 October 2025</p>
                            <ul>
                                <li>Supervisor may modify submitted marks before 12 November 2025</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- State Variables ---
        let selectedStudentId = null;
        let selectedAssessmentKey = null;
        // --- JAVASCRIPT LOGIC ---
        function openNav() {
            var fullWidth = "220px";
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            // CRITICAL FIX: Targets the main content area (now with id="main")
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
            // CRITICAL FIX: Targets the main content area (now with id="main")
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
            const roleHeaders = document.querySelectorAll('.role-header');

            // Function to check if a role has an active menu item
            const hasActiveMenuItem = (menu) => {
                return menu.querySelector('.active-menu-item') !== null;
            };

            // Function to update role header highlighting based on active menu items and expansion state
            const updateRoleHeaderHighlighting = () => {
                document.querySelectorAll('.role-header').forEach(header => {
                    const menuId = header.getAttribute('href');
                    const targetMenu = document.querySelector(menuId);

                    if (!targetMenu) return;

                    // Check if this specific menu contains the currently active page/link
                    const hasActiveLink = targetMenu.querySelector('.active-menu-item') !== null;

                    // Check if this menu is currently expanded (open)
                    const isExpanded = targetMenu.classList.contains('expanded');

                    // LOGIC: 
                    // Only highlight the Role Header if it holds the active page 
                    // BUT the menu is currently collapsed (hidden).
                    if (hasActiveLink && !isExpanded) {
                        header.classList.add('active-role');
                    } else {
                        header.classList.remove('active-role');
                    }
                });
            };

            // Function to handle the role menu toggle
            const handleRoleToggle = (header) => {
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                const arrowIcon = header.querySelector('.arrow-icon');

                if (!targetMenu) return;

                const isExpanded = targetMenu.classList.contains('expanded');

                // Collapse all other menus
                document.querySelectorAll('.menu-items').forEach(menu => {
                    if (menu !== targetMenu) {
                        menu.classList.remove('expanded');
                        menu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                    }
                });

                // Toggle current menu
                targetMenu.classList.toggle('expanded', !isExpanded);

                if (targetMenu.classList.contains('expanded')) {
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                    // Only show child links if sidebar is expanded
                    if (document.getElementById("mySidebar").style.width === "220px") {
                        targetMenu.querySelectorAll('a').forEach(a => a.style.display = 'block');
                    }
                } else {
                    arrowIcon.classList.remove('bi-chevron-down');
                    arrowIcon.classList.add('bi-chevron-right');
                    targetMenu.querySelectorAll('a').forEach(a => a.style.display = 'none');
                }

                // Update all role header highlighting based on current state
                updateRoleHeaderHighlighting();
            };

            roleHeaders.forEach(header => {
                header.addEventListener('click', (event) => {
                    event.preventDefault();
                    handleRoleToggle(header);
                });

                // Initial arrow state based on 'expanded' class in HTML
                const menuId = header.getAttribute('href');
                const targetMenu = document.querySelector(menuId);
                if (targetMenu && targetMenu.classList.contains('expanded')) {
                    const arrowIcon = header.querySelector('.arrow-icon');
                    arrowIcon.classList.remove('bi-chevron-right');
                    arrowIcon.classList.add('bi-chevron-down');
                }
            });


            // --- Task List Accordion Logic ---
            const tabs = document.querySelectorAll('.task-tab');
            const taskGroups = document.querySelectorAll('.task-group');
            const groupHeaders = document.querySelectorAll('.task-group-header');

            // --- Tab Switching Logic (Client-side simulation) ---
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

            // --- Task Group Expand/Collapse Logic (Accordion) ---
            groupHeaders.forEach(header => {
                header.addEventListener('click', () => {
                    const targetId = header.getAttribute('data-target');
                    const targetList = document.querySelector(targetId);
                    const toggleIcon = header.querySelector('.toggle-icon');

                    if (targetList) {
                        // 1. Check what the new state will be
                        const isExpanding = !targetList.classList.contains('expanded');

                        // 2. Toggle the content
                        targetList.classList.toggle('expanded', isExpanding);

                        // 3. Toggle the icon classes based on the new state
                        toggleIcon.classList.toggle('fa-chevron-right', !isExpanding); // Add 'right' if it is NOT expanding (i.e., collapsing)
                        toggleIcon.classList.toggle('fa-chevron-down', isExpanding);  // Add 'down' if it IS expanding
                    }
                });
            });

            // Default to 'inprogress' tab view on load
            document.querySelector('.task-group[data-group="inprogress"]').classList.add('active');
        });

    </script>
</body>

</html>