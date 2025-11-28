<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/student/fypInformation.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Include jsPDF library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body>
    
    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()">☰</button> 
        
        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>
            <span id="nameSide">HI, NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN</span>
            <a href="../dashboard/dashboard.php" id="dashboard"> <i class="bi bi-house-fill" style="padding-right: 10px;"></i>Dashboard</a>
            <a href="./fypInformation.php" id="fypInformation" class="focus"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>FYP Information</a>
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

<div id="main">
    <div class="info-card">
        <div class="tab-buttons">
            <button id="tabInfo" class="task-tab active-tab">FYP Information</button>
            <button id="tabPast" class="task-tab">Past Student's Title</button>
        </div>
        
        <div id="fypInfoSection" class="task-group active">
        <form id="fypInfoForm">
            <div class="row g-3">
                <h4 class="section-title">FYP Information</h4>
                <div class="col-md-6">
                    
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control readonly-field" value="NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Matric No</label>
                    <input type="text" class="form-control readonly-field" value="214673" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Programme</label>
                    <input type="text" class="form-control readonly-field" value="BACHELOR OF SOFTWARE ENGINEERING WITH HONOURS" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester</label>
                    <input type="text" class="form-control readonly-field" value="2-2024/2025" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supervisor</label>
                    <input type="text" class="form-control readonly-field" value="DR. AZRINA BINTI KAMARUDDIN" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label">FYP Title</label>
                    <textarea class="form-control readonly-field" id="fypTitle" rows="3" readonly>DEVELOPMENT OF AN AUTOMATED ASSESSMENT AND EVALUATION SYSTEM FOR BACHELOR PROJECTS (DEPARTMENT COORDINATORS AND STUDENT'S MODULE)</textarea>
                    <div class="mt-2 d-flex align-items-center">
                        <span id="titleStatus" class="status-note" style="display:none;">Status: Under consideration by supervisor</span>
                        <button type="button" id="changeTitleBtn" class="btn btn-outline-dark btn-sm ms-auto">Change Title</button>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <span class="small">Comment Section:</span>
                <a href="#" class="small" id="downloadPdfBtn">Download as PDF</a>
                <br>
                <span style="display: inline-block; margin-top: 16px;"></span>
            </div>
        </form>
        </div>

        <div id="pastTitlesSection" class="task-group">
            <div class="past-list">
                <h4 class="section-title">Past Student's Title</h4>
                
                <!-- Search and Sort Controls -->
                <div class="past-title-controls">
                    <div class="search-box">
                        <i class="bi bi-search"></i>
                        <input type="text" id="pastTitleSearch" class="search-input" placeholder="Search by name or title...">
                    </div>
                    <div class="sort-controls">
                        <label for="sortBySession" class="sort-label">Sort by:</label>
                        <select id="sortBySession" class="sort-select">
                            <option value="latest">Latest Session First</option>
                            <option value="oldest">Oldest Session First</option>
                        </select>
                    </div>
                </div>
                
                <div class="accordion" id="pastAccordion"></div>
            </div>
        </div>
    </div>
    </div>
    

    <script>
    // --- JAVASCRIPT LOGIC ---
    function openNav() {
        var fullWidth = "220px"; 
        
        // 1. Expand the Sidebar
        document.getElementById("mySidebar").style.width = fullWidth;
        
        // 2. Push the main content AND the header container to the right
        document.getElementById("main").style.marginLeft = fullWidth;
        document.getElementById("containerAtas").style.marginLeft = fullWidth;
        
        // 3. Show the name and the links
        document.getElementById("nameSide").style.display = "block"; // <-- SHOW THE NAME
        
        var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
        for (var i = 0; i < links.length; i++) {
            // Using flex for closebtn, block for others
            links[i].style.display = (links[i].id === 'close' ? 'flex' : 'block');
        }

        // 4. Hide the open icon
        document.getElementsByClassName("menu-icon")[0].style.display = "none";
    }

    function closeNav() {
        var collapsedWidth = "60px";

        // 1. Collapse the Sidebar
        document.getElementById("mySidebar").style.width = collapsedWidth;
        
        // 2. Move the main content AND the header container back
        document.getElementById("main").style.marginLeft = collapsedWidth;
        document.getElementById("containerAtas").style.marginLeft = collapsedWidth;

        // 3. Hide the name and the links
        document.getElementById("nameSide").style.display = "none"; // <-- HIDE THE NAME
        
        var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
        for (var i = 0; i < links.length; i++) {
            links[i].style.display = "none";
        }
        
        // 4. Show the open icon
        document.getElementsByClassName("menu-icon")[0].style.display = "block";
    }


    	window.onload = function() {
        // We need to set the initial style for #nameSide here
        document.getElementById("nameSide").style.display = "none";
        closeNav();
    	// Tabs toggle
    	var tabInfo = document.getElementById('tabInfo');
    	var tabPast = document.getElementById('tabPast');
    	var infoSection = document.getElementById('fypInfoSection');
    	var pastSection = document.getElementById('pastTitlesSection');
        
        // Declare renderPastTitlesNow function variable (will be defined later)
        var renderPastTitlesNow;

    	// Function to switch to FYP Information tab
    	function showFYPInfo() {
    		tabInfo.classList.add('active-tab');
    		tabPast.classList.remove('active-tab');
    		infoSection.classList.add('active');
    		pastSection.classList.remove('active');
    	}

    	// Function to switch to Past Titles tab
    	function showPastTitles() {
    		tabInfo.classList.remove('active-tab');
    		tabPast.classList.add('active-tab');
    		infoSection.classList.remove('active');
    		pastSection.classList.add('active');
    		// Re-render past titles when switching to this tab to ensure Bootstrap collapse works
    		setTimeout(function() {
    			if (renderPastTitlesNow) {
    				renderPastTitlesNow();
    			}
    		}, 100);
    	}

    	tabInfo.addEventListener('click', showFYPInfo);
    	tabPast.addEventListener('click', showPastTitles);
        
        // --- Title change flow ---
        var changeBtn = document.getElementById('changeTitleBtn');
        var fypTitleTextarea = document.getElementById('fypTitle');
        var statusNote = document.getElementById('titleStatus');

        // Build Edit Modal container
        var editModal = document.createElement('div');
        editModal.className = 'custom-modal';
        editModal.id = 'editTitleModal';
        document.body.appendChild(editModal);

        function openModal(modal){ modal.style.display = 'block'; }
        function closeModal(modal){ modal.style.display = 'none'; }

        // Renders the edit form view and wires up handlers
        function renderEditView() {
            editModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeEditModal">&times;</span>
                        <div class="modal-title-custom">Change FYP Title</div>
                        <div class="modal-message">Update your title below. It will be sent for supervisor consideration.</div>
                        <textarea id="editTitleInput" class="form-control" rows="4"></textarea>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelEdit" class="btn btn-light border" type="button">Cancel</button>
                            <button id="saveEdit" class="btn btn-success" type="button">Save</button>
                        </div>
                    </div>
                </div>`;

            // Prefill with current text
            var input = editModal.querySelector('#editTitleInput');
            input.value = fypTitleTextarea.value.trim();

            // Bind close/cancel
            editModal.querySelector('#closeEditModal').onclick = function(){ closeModal(editModal); };
            editModal.querySelector('#cancelEdit').onclick = function(){ closeModal(editModal); };

            // Bind save -> switch to confirmation view
            editModal.querySelector('#saveEdit').onclick = function(){
                statusNote.style.display = 'inline';
                fypTitleTextarea.value = input.value.trim();

                var contentHtml = `
                    <div class="modal-dialog">
                        <div class="modal-content-custom">
                            <span class="close-btn" id="closeEditModalAfter">&times;</span>
                            <div class="modal-icon"><i class="bi bi-send-check-fill"></i></div>
                            <div class="modal-title-custom">Title Submitted</div>
                            <div class="modal-message">Your fyp title is under consideration of your supervisor. The title will be changed once gotten the approval from the supervisor. Thank you!</div>
                            <div style="display:flex; justify-content:center;">
                                <button id="okSubmitted" class="btn btn-success" type="button">OK</button>
                            </div>
                        </div>
                    </div>`;

                editModal.innerHTML = contentHtml;
                editModal.querySelector('#okSubmitted').onclick = function(){
                    closeModal(editModal);
                    // Restore edit view for future changes
                    renderEditView();
                };
                editModal.querySelector('#closeEditModalAfter').onclick = function(){
                    closeModal(editModal);
                    renderEditView();
                };
            };
        }

        // Initial render of edit view
        renderEditView();

        changeBtn.addEventListener('click', function(){
            // Always ensure edit view is shown when opening
            renderEditView();
            openModal(editModal);
        });

        // --- Populate Past Titles grouped by Supervisor and Session ---
        var pastData = [
            {
                supervisor: 'Dr. Azrina Binti Kamaruddin',
                sessions: [
                    {
                        session: '2024/2025',
                        students: [
                            { name: 'Aiman Zulkifli', title: 'IoT-based Smart Greenhouse Monitoring System' },
                            { name: 'Nurul Izzah', title: 'Automated Grading System for Final Year Projects' }
                        ]
                    },
                    {
                        session: '2023/2024',
                        students: [
                            { name: 'Muhammad Hafiz', title: 'Web-based Library Management System' },
                            { name: 'Siti Aisyah', title: 'Mobile Application for Health Monitoring' }
                        ]
                    },
                    {
                        session: '2022/2023',
                        students: [
                            { name: 'Ahmad Firdaus', title: 'E-Learning Platform for Online Courses' },
                            { name: 'Nora Aziz', title: 'Inventory Management System for Retail Stores' }
                        ]
                    }
                ]
            },
            {
                supervisor: 'Prof. Ahmad Rahman',
                sessions: [
                    {
                        session: '2023/2024',
                        students: [
                            { name: 'Siti Aminah', title: 'Mobile Attendance Tracking Using NFC' },
                            { name: 'Daniel Chong', title: 'Machine Learning for Plant Disease Detection' }
                        ]
                    },
                    {
                        session: '2022/2023',
                        students: [
                            { name: 'Lim Wei Jie', title: 'Smart Home Automation System' }
                        ]
                    }
                ]
            },
            {
                supervisor: 'Dr. Siti Nurhaliza',
                sessions: [
                    {
                        session: '2024/2025',
                        students: [
                            { name: 'Sarah Abdullah', title: 'E-commerce Platform with Payment Integration' }
                        ]
                    },
                    {
                        session: '2023/2024',
                        students: [
                            { name: 'Lee Wei Ming', title: 'Cloud-based Document Management System' },
                            { name: 'Tan Mei Ling', title: 'Social Media Analytics Dashboard' }
                        ]
                    }
                ]
            }
        ];

        // Function to sort sessions
        function sortSessions(sessions, order) {
            var sorted = sessions.slice();
            sorted.sort(function(a, b) {
                var aYear = parseInt(a.session.split('/')[0]);
                var bYear = parseInt(b.session.split('/')[0]);
                return order === 'latest' ? bYear - aYear : aYear - bYear;
            });
            return sorted;
        }

        // Function to render past titles
        function renderPastTitles(data, sortOrder, searchTerm) {
            var acc = document.getElementById('pastAccordion');
            if (!acc) return;
            
            acc.innerHTML = '';
            var supervisorIndex = 0;
            var hasResults = false;
            
            data.forEach(function(group) {
                var supervisorHeaderId = 'supervisor-heading-' + supervisorIndex;
                var supervisorCollapseId = 'supervisor-collapse-' + supervisorIndex;
                
                // Filter and sort sessions
                var filteredSessions = group.sessions.map(function(session) {
                    if (!searchTerm) return session;
                    
                    var filteredStudents = session.students.filter(function(student) {
                        var searchLower = searchTerm.toLowerCase();
                        return student.name.toLowerCase().includes(searchLower) || 
                               student.title.toLowerCase().includes(searchLower);
                    });
                    
                    return filteredStudents.length > 0 ? {
                        session: session.session,
                        students: filteredStudents
                    } : null;
                }).filter(function(s) { return s !== null; });
                
                if (filteredSessions.length === 0) {
                    supervisorIndex++;
                    return;
                }
                
                hasResults = true;
                var sortedSessions = sortSessions(filteredSessions, sortOrder);
                
                // Build sessions HTML
                var sessionsHtml = '';
                sortedSessions.forEach(function(session, sessionIdx) {
                    var sessionHeaderId = 'session-heading-' + supervisorIndex + '-' + sessionIdx;
                    var sessionCollapseId = 'session-collapse-' + supervisorIndex + '-' + sessionIdx;
                    
                    var studentsHtml = session.students.map(function(s) {
                        return '<div class="student-item">' +
                            '<strong>' + s.name + ':</strong> ' + s.title +
                            '</div>';
                    }).join('');
                    
                    sessionsHtml += '<div class="session-item">' +
                        '<button class="session-btn" type="button" data-bs-toggle="collapse" data-bs-target="#' + sessionCollapseId + '" aria-expanded="false" aria-controls="' + sessionCollapseId + '">' +
                        '<span class="session-label">Session ' + session.session + '</span>' +
                        '<i class="bi bi-chevron-down session-arrow"></i>' +
                        '</button>' +
                        '<div id="' + sessionCollapseId + '" class="collapse session-content" data-bs-parent="#supervisor-' + supervisorIndex + '-sessions">' +
                        '<div class="students-list">' + studentsHtml + '</div>' +
                        '</div>' +
                        '</div>';
                });
                
                var itemHtml = '<div class="supervisor-item">' +
                    '<button class="supervisor-btn" type="button" data-bs-toggle="collapse" data-bs-target="#' + supervisorCollapseId + '" aria-expanded="false" aria-controls="' + supervisorCollapseId + '">' +
                    '<span>' + group.supervisor + '</span>' +
                    '<i class="bi bi-chevron-down"></i>' +
                    '</button>' +
                    '<div id="' + supervisorCollapseId + '" class="collapse supervisor-content" data-bs-parent="#pastAccordion">' +
                    '<div id="supervisor-' + supervisorIndex + '-sessions" class="sessions-container">' + sessionsHtml + '</div>' +
                    '</div>' +
                    '</div>';
                
                acc.insertAdjacentHTML('beforeend', itemHtml);
                supervisorIndex++;
            });
            
            // Show "no results" message if search returns nothing
            if (!hasResults && searchTerm) {
                acc.innerHTML = '<div class="no-results" style="text-align: center; padding: 40px 20px; color: #999; font-style: italic;">No results found for "' + searchTerm + '"</div>';
            }
        }

        // Initialize past titles variables
        var currentSortOrder = 'latest';
        var currentSearchTerm = '';
        
        // Function to render past titles (always render, the section visibility is handled by CSS)
        renderPastTitlesNow = function() {
            renderPastTitles(pastData, currentSortOrder, currentSearchTerm);
        };
        
        // Initial render
        renderPastTitlesNow();

        // Search functionality
        var searchInput = document.getElementById('pastTitleSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                currentSearchTerm = this.value.trim();
                renderPastTitlesNow();
            });
        }

        // Sort functionality
        var sortSelect = document.getElementById('sortBySession');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                currentSortOrder = this.value;
                renderPastTitlesNow();
            });
        }

        // PDF Download functionality
        var downloadPdfBtn = document.getElementById('downloadPdfBtn');
        if (downloadPdfBtn) {
            downloadPdfBtn.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get jsPDF from the UMD bundle
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Get form values
                var studentName = document.querySelector('input[value="NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN"]').value;
                var matricNo = document.querySelector('input[value="214673"]').value;
                var programme = document.querySelector('input[value="BACHELOR OF SOFTWARE ENGINEERING WITH HONOURS"]').value;
                var semester = document.querySelector('input[value="2-2024/2025"]').value;
                var supervisor = document.querySelector('input[value="DR. AZRINA BINTI KAMARUDDIN"]').value;
                var fypTitle = document.getElementById('fypTitle').value;
                
                // Load and add UPM logo
                var img = new Image();
                img.src = '../../../assets/UPMLogo.png';
                img.onload = function() {
                    // Add logo on the left (20mm from left, 10mm from top, 30mm wide, 20mm tall)
                    doc.addImage(img, 'PNG', 20, 10, 30, 20);
                    
                    // Set up PDF styling - title positioned to the right of logo
                    doc.setFontSize(18);
                    doc.setFont(undefined, 'bold');
                    doc.text('FYP Information', 105, 20, { align: 'center' });
                    
                    // Add line separator
                    doc.setLineWidth(0.5);
                    doc.line(20, 32, 190, 32);
                    
                    // Reset font for content
                    doc.setFontSize(12);
                    doc.setFont(undefined, 'normal');
                    
                    var yPos = 42;
                    var lineHeight = 10;
                    
                    // Add student information
                    doc.setFont(undefined, 'bold');
                    doc.text('Name:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(studentName, 60, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Matric No:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(matricNo, 60, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Programme:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    // Split long text if needed
                    var programmeLines = doc.splitTextToSize(programme, 130);
                    doc.text(programmeLines, 60, yPos);
                    yPos += lineHeight * programmeLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Semester:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(semester, 60, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Supervisor:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(supervisor, 60, yPos);
                    yPos += lineHeight + 5;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('FYP Title:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    // Split title text to fit page width
                    var titleLines = doc.splitTextToSize(fypTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length;
                    
                    // Add line separator after FYP Title
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 10;

                     doc.setFont(undefined, 'bold');
                    doc.text('Comment by Assessor 1 For SWE4949-A:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    var titleLines = doc.splitTextToSize(fypTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length+3;
                    
                       doc.setFont(undefined, 'bold');
                    doc.text('Comment by Assessor 2 For SWE4949-A:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    var titleLines = doc.splitTextToSize(fypTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length ;


                    // Add status if visible
                    var statusNote = document.getElementById('titleStatus');
                    if (statusNote && statusNote.style.display !== 'none') {
                        doc.setFontSize(10);
                        doc.setFont(undefined, 'italic');
                        doc.text('Status: Under consideration by supervisor', 20, yPos);
                    }
                    
                    // Add footer
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'normal');
                    doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                    doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                    
                    // Save the PDF
                    doc.save('FYP_Information_' + matricNo + '.pdf');
                };
                
                // If image fails to load, generate PDF without logo
                img.onerror = function() {
                    console.warn('Logo failed to load, generating PDF without logo');
                    generatePdfWithoutLogo();
                };
                
                // Fallback function to generate PDF without logo
                function generatePdfWithoutLogo() {
                    doc.setFontSize(18);
                    doc.setFont(undefined, 'bold');
                    doc.text('FYP Information', 105, 20, { align: 'center' });
                    
                    doc.setLineWidth(0.5);
                    doc.line(20, 25, 190, 25);
                    
                    doc.setFontSize(12);
                    doc.setFont(undefined, 'normal');
                    
                    var yPos = 35;
                    var lineHeight = 10;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Name:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(studentName, 60, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Matric No:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(matricNo, 60, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Programme:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    var programmeLines = doc.splitTextToSize(programme, 130);
                    doc.text(programmeLines, 60, yPos);
                    yPos += lineHeight * programmeLines.length;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Semester:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(semester, 60, yPos);
                    yPos += lineHeight;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('Supervisor:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    doc.text(supervisor, 60, yPos);
                    yPos += lineHeight + 5;
                    
                    doc.setFont(undefined, 'bold');
                    doc.text('FYP Title:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    var titleLines = doc.splitTextToSize(fypTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length ;
                    
                    // Add line separator after FYP Title
                    doc.setLineWidth(0.5);
                    doc.line(20, yPos, 190, yPos);
                    yPos += 0;
                    
                      doc.setFont(undefined, 'bold');
                    doc.text('Comment by Assessor 1:', 20, yPos);
                    doc.setFont(undefined, 'normal');
                    yPos += lineHeight;
                    var titleLines = doc.splitTextToSize(fypTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length + 10;
                    

                    var statusNote = document.getElementById('titleStatus');
                    if (statusNote && statusNote.style.display !== 'none') {
                        doc.setFontSize(10);
                        doc.setFont(undefined, 'italic');
                        doc.text('Status: Under consideration by supervisor', 20, yPos);
                    }
                    
                    doc.setFontSize(8);
                    doc.setFont(undefined, 'normal');
                    doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                    doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                    
                    doc.save('FYP_Information_' + matricNo + '.pdf');
                }
            });
        }
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>