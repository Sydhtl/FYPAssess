<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/student/logbook.css">
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
            <a href="../fypInformation/fypInformation.php" id="fypInformation"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>FYP Information</a>
            <a href="./logbook.php" id="logbookSubmission" class="focus"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>Logbook Submission</a>
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
    <div class="logbook-container">
        <div class="tab-buttons">
            <button id="tabSweA" class="task-tab active-tab">SWE4949A</button>
            <button id="tabSweB" class="task-tab">SWE4949B</button>
        </div>
        
        <div id="sweASection" class="task-group active">
            <div style="margin-top: 15px;">
                <h4 class="section-title" id="sectionTitleA">Logbook Submission SWE4949A</h4>
                
                <div class="name-section">
                    <div class="name-field-wrapper">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control readonly-field" value="NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN" readonly>
                    </div>
                    <button type="button" id="addNewRowBtnA" class="btn btn-outline-dark add-row-btn" style="background-color: white; color: black;">
                        <i class="bi bi-plus-circle" style="margin-right: 8px; color: black;"></i>Add new row
                    </button>
                </div>

                <div class="table-wrapper">
                    <table class="table logbook-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Logbook Entry</th>
                                <th>Status</th>
                                <th>Logbook Uploaded</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody id="logbookTableBodyA">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="remaining-logbook">
                    <span>Remaining Logbook</span>
                    <div class="remaining-box" id="remainingCountA">10</div>
                </div>
            </div>
        </div>

        <div id="sweBSection" class="task-group">
            <div style="margin-top: 15px;">
                <h4 class="section-title" id="sectionTitleB">Logbook Submission SWE4949B</h4>
                
                <div class="name-section">
                    <div class="name-field-wrapper">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control readonly-field" value="NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN" readonly>
                    </div>
                    <button type="button" id="addNewRowBtnB" class="btn btn-outline-dark add-row-btn" style="background-color: white; color: black;">
                        <i class="bi bi-plus-circle" style="margin-right: 8px; color: black;"></i>Add new row
                    </button>
                </div>

                <div class="table-wrapper">
                    <table class="table logbook-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Logbook Entry</th>
                                <th>Status</th>
                                <th>Logbook Uploaded</th>
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody id="logbookTableBodyB">
                            <!-- Table rows will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>

                <div class="remaining-logbook">
                    <span>Remaining Logbook</span>
                    <div class="remaining-box" id="remainingCountB">10</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add New Row Modal -->
<div id="addRowModal" class="custom-modal">
    <div class="modal-dialog">
        <div class="modal-content-custom">
            <span class="close-btn" id="closeAddModal">&times;</span>
            <div class="modal-title-custom">Add New Logbook</div>
            <form id="addLogbookForm">
                <div class="modal-field">
                    <label class="modal-label">Logbook Entry:</label>
                    <input type="text" id="logbookEntryInput" class="form-control" placeholder="Enter logbook entry title" required>
                </div>
                <div class="modal-field">
                    <label class="modal-label">Logbook:</label>
                    <div class="agenda-container" id="agendaContainer">
                        <!-- Agenda items will be added here dynamically -->
                    </div>
                    <button type="button" id="addAgendaBtn" class="btn btn-outline-primary" style="width: 100%; margin-top: 10px;">
                        <i class="bi bi-plus-circle" style="margin-right: 5px;"></i>Add an Agenda
                    </button>
                </div>
                <div class="modal-buttons">
                    <button type="button" id="cancelAddBtn" class="btn btn-light border">Cancel</button>
                    <button type="submit" id="saveAddBtn" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Agenda Modal -->
<div id="addAgendaModal" class="custom-modal">
    <div class="modal-dialog">
        <div class="modal-content-custom">
            <span class="close-btn" id="closeAgendaModal">&times;</span>
            <div class="modal-title-custom">Add Agenda</div>
            <form id="addAgendaForm">
                <div class="modal-field">
                    <label class="modal-label">Name of Agenda:</label>
                    <input type="text" id="agendaNameInput" class="form-control" placeholder="Enter agenda name" required>
                </div>
                <div class="modal-field">
                    <label class="modal-label">Agenda's Explanation:</label>
                    <textarea id="agendaExplanationInput" class="form-control" rows="4" placeholder="Enter agenda explanation" required></textarea>
                </div>
                <div class="modal-buttons">
                    <button type="button" id="cancelAgendaBtn" class="btn btn-light border">Cancel</button>
                    <button type="submit" id="saveAgendaBtn" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PDF Viewer Modal -->
<div id="pdfViewerModal" class="custom-modal">
    <div class="modal-dialog pdf-modal-dialog">
        <div class="modal-content-custom pdf-viewer-content">
            <span class="close-btn" id="closePdfModal">&times;</span>
            <div class="modal-title-custom">Logbook PDF</div>
            <iframe id="pdfViewer" src="" style="width: 100%; height: 600px; border: none;"></iframe>
        </div>
    </div>
</div>

<script>
    // Sidebar functionality
    function openNav() {
        var fullWidth = "220px"; 
        document.getElementById("mySidebar").style.width = fullWidth;
        document.getElementById("main").style.marginLeft = fullWidth;
        document.getElementById("containerAtas").style.marginLeft = fullWidth;
        document.getElementById("nameSide").style.display = "block";
        var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
        for (var i = 0; i < links.length; i++) {
            links[i].style.display = (links[i].id === 'close' ? 'flex' : 'block');
        }
        document.getElementsByClassName("menu-icon")[0].style.display = "none";
    }

    function closeNav() {
        var collapsedWidth = "60px";
        document.getElementById("mySidebar").style.width = collapsedWidth;
        document.getElementById("main").style.marginLeft = collapsedWidth;
        document.getElementById("containerAtas").style.marginLeft = collapsedWidth;
        document.getElementById("nameSide").style.display = "none";
        var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
        for (var i = 0; i < links.length; i++) {
            links[i].style.display = "none";
        }
        document.getElementsByClassName("menu-icon")[0].style.display = "block";
    }

    // Logbook data storage - separate for each section
    var logbookDataA = [
        { id: 1, entry: 'Logbook 1', status: 'Approved', file: 'logbook1.pdf' },
        { id: 2, entry: 'Logbook 2', status: 'Approved', file: 'logbook2.pdf' },
        { id: 3, entry: 'Logbook 3', status: 'Approved', file: 'logbook3.pdf' },
        { id: 4, entry: 'Logbook 4', status: 'Approved', file: 'logbook4.pdf' },
        { id: 5, entry: 'Logbook 5', status: 'Approved', file: 'logbook5.pdf' },
        { id: 6, entry: 'Logbook 6', status: 'Declined', file: 'logbook6.pdf' },
        { id: 7, entry: 'Logbook 7', status: 'Declined', file: 'logbook7.pdf' },
        { id: 8, entry: 'Logbook 8', status: 'Waiting for approval', file: 'logbook8.pdf' },
        { id: 9, entry: 'Logbook 9', status: 'Waiting for approval', file: 'logbook9.pdf' }
    ];

    var logbookDataB = [
        { id: 1, entry: 'Logbook 1', status: 'Approved', file: 'logbook1.pdf' },
        { id: 2, entry: 'Logbook 2', status: 'Waiting for approval', file: 'logbook2.pdf' }
    ];

    var totalLogbooks = 10;
    var nextLogbookIdA = 10;
    var nextLogbookIdB = 3;
    var currentSection = 'A';
    var agendaList = [];
    var editingAgendaId = null;

    function updateRemainingCount(section) {
        var logbookData = section === 'A' ? logbookDataA : logbookDataB;
        var approvedCount = logbookData.filter(function(item) { return item.status === 'Approved'; }).length;
        var remaining = totalLogbooks - approvedCount;
        var countElement = section === 'A' ? document.getElementById('remainingCountA') : document.getElementById('remainingCountB');
        countElement.textContent = remaining;
    }

    function getStatusClass(status) {
        if (status === 'Approved') return 'status-approved';
        if (status === 'Declined') return 'status-declined';
        return 'status-waiting';
    }

    function renderTable(section) {
        var logbookData = section === 'A' ? logbookDataA : logbookDataB;
        var tbodyId = section === 'A' ? 'logbookTableBodyA' : 'logbookTableBodyB';
        var tbody = document.getElementById(tbodyId);
        tbody.innerHTML = '';
        logbookData.forEach(function(item) {
            var row = document.createElement('tr');
            row.innerHTML = 
                '<td>' + item.id + '</td>' +
                '<td>' + item.entry + '</td>' +
                '<td><span class="status-badge ' + getStatusClass(item.status) + '">' + item.status + '</span></td>' +
                '<td><a href="#" class="logbook-link" data-id="' + item.id + '" data-entry="' + item.entry + '" data-status="' + item.status + '" data-section="' + section + '">' + item.entry + '</a></td>' +
                '<td><button class="btn-delete" data-id="' + item.id + '" data-section="' + section + '"><i class="bi bi-trash"></i></button></td>';
            tbody.appendChild(row);
        });
        
        // Attach event listeners for this section only
        var sectionSelector = section === 'A' ? '#sweASection' : '#sweBSection';
        var sectionElement = document.querySelector(sectionSelector);
        sectionElement.querySelectorAll('.logbook-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                var id = parseInt(this.getAttribute('data-id'));
                var entry = this.getAttribute('data-entry');
                var status = this.getAttribute('data-status');
                var section = this.getAttribute('data-section');
                generateLogbookPdf(id, entry, status, section);
            });
        });
        
        sectionElement.querySelectorAll('.btn-delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = parseInt(this.getAttribute('data-id'));
                var section = this.getAttribute('data-section');
                deleteLogbook(id, section);
            });
        });
        
        updateRemainingCount(section);
    }

    function showPdfViewer(fileName) {
        var modal = document.getElementById('pdfViewerModal');
        var iframe = document.getElementById('pdfViewer');
        // In a real app, this would be the actual file path
        iframe.src = '../../../assets/logbooks/' + fileName;
        modal.style.display = 'block';
    }

    function deleteLogbook(id, section) {
        if (confirm('Are you sure you want to delete this logbook?')) {
            if (section === 'A') {
                logbookDataA = logbookDataA.filter(function(item) { return item.id !== id; });
            } else {
                logbookDataB = logbookDataB.filter(function(item) { return item.id !== id; });
            }
            renderTable(section);
        }
    }

    function generateLogbookPdf(id, entry, status, section) {
        // Get jsPDF from the UMD bundle
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        var studentName = "NURUL SAIDAHTUL FATIHA BINTI SHAHARUDIN";
        var courseCode = section === 'A' ? 'SWE4949A' : 'SWE4949B';
        var courseName = section === 'A' ? 'Final Year Project A' : 'Final Year Project B';
        var session = '2024/2025 - 2';
        
        // Get logbook data with agendas
        var logbookData = section === 'A' ? logbookDataA : logbookDataB;
        var logbookEntry = logbookData.find(function(item) { return item.id === id; });
        var agendas = logbookEntry && logbookEntry.agendas ? logbookEntry.agendas : [];
        
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
            doc.text('Logbook Entry', 105, 35, { align: 'center' });
            
            // Line separator
            doc.setLineWidth(0.5);
            doc.line(20, 40, 190, 40);
            
            // Content
            doc.setFontSize(12);
            doc.setFont(undefined, 'normal');
            
            var yPos = 50;
            var lineHeight = 10;
            
            // Student info
            doc.setFont(undefined, 'bold');
            doc.text('Student Name:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(studentName, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Course Code:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(courseCode, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Course Name:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(courseName, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Session:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(session, 70, yPos);
            yPos += lineHeight + 5;
            
            doc.setFont(undefined, 'bold');
            doc.text('Logbook Entry:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(entry, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Status:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(status, 70, yPos);
            yPos += lineHeight + 5;
            
            // Line separator
            doc.setLineWidth(0.5);
            doc.line(20, yPos, 190, yPos);
            yPos += 10;
            
            // Logbook content section
            doc.setFont(undefined, 'bold');
            doc.text('Logbook Content:', 20, yPos);
            doc.setFont(undefined, 'normal');
            yPos += lineHeight;
            
            // Display agendas
            if (agendas.length > 0) {
                agendas.forEach(function(agenda, index) {
                    doc.setFont(undefined, 'bold');
                    var agendaTitle = (index + 1) + '. ' + agenda.name;
                    var titleLines = doc.splitTextToSize(agendaTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length;
                    
                    doc.setFont(undefined, 'normal');
                    var explanationLines = doc.splitTextToSize(agenda.explanation, 170);
                    doc.text(explanationLines, 20, yPos);
                    yPos += lineHeight * explanationLines.length + 5;
                });
            } else {
                var contentText = 'No agenda items added.';
                doc.text(contentText, 20, yPos);
                yPos += lineHeight;
            }
            
            // Footer
            doc.setFontSize(8);
            doc.setFont(undefined, 'normal');
            doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
            doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
            
            // Open PDF in new window for viewing
            window.open(doc.output('bloburl'), '_blank');
        };
        
        // Fallback without logo
        img.onerror = function() {
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('Logbook Entry', 105, 20, { align: 'center' });
            
            doc.setLineWidth(0.5);
            doc.line(20, 25, 190, 25);
            
            doc.setFontSize(12);
            doc.setFont(undefined, 'normal');
            
            var yPos = 35;
            var lineHeight = 10;
            
            doc.setFont(undefined, 'bold');
            doc.text('Student Name:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(studentName, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Course Code:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(courseCode, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Course Name:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(courseName, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Session:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(session, 70, yPos);
            yPos += lineHeight + 5;
            
            doc.setFont(undefined, 'bold');
            doc.text('Logbook Entry:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(entry, 70, yPos);
            yPos += lineHeight;
            
            doc.setFont(undefined, 'bold');
            doc.text('Status:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(status, 70, yPos);
            yPos += lineHeight + 5;
            
            doc.setLineWidth(0.5);
            doc.line(20, yPos, 190, yPos);
            yPos += 10;
            
            doc.setFont(undefined, 'bold');
            doc.text('Logbook Content:', 20, yPos);
            doc.setFont(undefined, 'normal');
            yPos += lineHeight;
            
            // Display agendas
            if (agendas.length > 0) {
                agendas.forEach(function(agenda, index) {
                    doc.setFont(undefined, 'bold');
                    var agendaTitle = (index + 1) + '. ' + agenda.name;
                    var titleLines = doc.splitTextToSize(agendaTitle, 170);
                    doc.text(titleLines, 20, yPos);
                    yPos += lineHeight * titleLines.length;
                    
                    doc.setFont(undefined, 'normal');
                    var explanationLines = doc.splitTextToSize(agenda.explanation, 170);
                    doc.text(explanationLines, 20, yPos);
                    yPos += lineHeight * explanationLines.length + 5;
                });
            } else {
                var contentText = 'No agenda items added.';
                doc.text(contentText, 20, yPos);
                yPos += lineHeight;
            }
            
            doc.setFontSize(8);
            doc.setFont(undefined, 'normal');
            doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
            doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
            
            window.open(doc.output('bloburl'), '_blank');
        };
    }

    // Modal functions
    function openModal(modal) { modal.style.display = 'block'; }
    function closeModal(modal) { modal.style.display = 'none'; }

    // Tab toggle functionality
    var tabSweA = document.getElementById('tabSweA');
    var tabSweB = document.getElementById('tabSweB');
    var sweASection = document.getElementById('sweASection');
    var sweBSection = document.getElementById('sweBSection');

    tabSweA.addEventListener('click', function(){
        tabSweA.classList.add('active-tab');
        tabSweB.classList.remove('active-tab');
        sweASection.classList.add('active');
        sweBSection.classList.remove('active');
        currentSection = 'A';
    });

    tabSweB.addEventListener('click', function(){
        tabSweB.classList.add('active-tab');
        tabSweA.classList.remove('active-tab');
        sweBSection.classList.add('active');
        sweASection.classList.remove('active');
        currentSection = 'B';
    });

    // Add new row modal
    var addModal = document.getElementById('addRowModal');
    var agendaModal = document.getElementById('addAgendaModal');
    var agendaContainer = document.getElementById('agendaContainer');

    document.getElementById('addNewRowBtnA').addEventListener('click', function() {
        currentSection = 'A';
        document.getElementById('addLogbookForm').reset();
        agendaList = [];
        agendaContainer.innerHTML = '';
        openModal(addModal);
    });

    document.getElementById('addNewRowBtnB').addEventListener('click', function() {
        currentSection = 'B';
        document.getElementById('addLogbookForm').reset();
        agendaList = [];
        agendaContainer.innerHTML = '';
        openModal(addModal);
    });

    document.getElementById('closeAddModal').onclick = function() { closeModal(addModal); };
    document.getElementById('cancelAddBtn').onclick = function() { closeModal(addModal); };

    // Add Agenda button
    document.getElementById('addAgendaBtn').addEventListener('click', function(e) {
        e.preventDefault();
        editingAgendaId = null;
        document.getElementById('addAgendaForm').reset();
        document.querySelector('#addAgendaModal .modal-title-custom').textContent = 'Add Agenda';
        openModal(agendaModal);
    });

    document.getElementById('closeAgendaModal').onclick = function() { closeModal(agendaModal); };
    document.getElementById('cancelAgendaBtn').onclick = function() { closeModal(agendaModal); };

    // Save agenda
    document.getElementById('addAgendaForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var agendaName = document.getElementById('agendaNameInput').value;
        var agendaExplanation = document.getElementById('agendaExplanationInput').value;
        
        if (!agendaName || !agendaExplanation) {
            alert('Please fill in all fields');
            return;
        }
        
        if (editingAgendaId !== null) {
            // Update existing agenda
            var agendaIndex = agendaList.findIndex(function(a) { return a.id === editingAgendaId; });
            if (agendaIndex !== -1) {
                agendaList[agendaIndex].name = agendaName;
                agendaList[agendaIndex].explanation = agendaExplanation;
            }
            editingAgendaId = null;
        } else {
            // Add new agenda
            var agenda = {
                id: Date.now(),
                name: agendaName,
                explanation: agendaExplanation
            };
            agendaList.push(agenda);
        }
        
        renderAgendaList();
        closeModal(agendaModal);
        this.reset();
    });

    function renderAgendaList() {
        agendaContainer.innerHTML = '';
        agendaList.forEach(function(agenda) {
            var agendaItem = document.createElement('div');
            agendaItem.className = 'agenda-item';
            agendaItem.style.cssText = 'border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; border-radius: 5px; background-color: #f9f9f9;';
            agendaItem.innerHTML = 
                '<div style="display: flex; justify-content: space-between; align-items: start;">' +
                    '<div style="flex: 1; word-wrap: break-word; overflow-wrap: break-word; min-width: 0;">' +
                        '<strong style="display: block; margin-bottom: 5px; word-wrap: break-word;">' + agenda.name + '</strong>' +
                        '<p style="margin: 0; font-size: 14px; color: #666; word-wrap: break-word; white-space: pre-wrap;">' + agenda.explanation + '</p>' +
                    '</div>' +
                    '<div style="display: flex; gap: 5px; flex-shrink: 0; margin-left: 10px;">' +
                        '<button type="button" class="btn btn-sm btn-primary edit-agenda-btn" data-id="' + agenda.id + '">' +
                            '<i class="bi bi-pencil"></i>' +
                        '</button>' +
                        '<button type="button" class="btn btn-sm btn-danger delete-agenda-btn" data-id="' + agenda.id + '">' +
                            '<i class="bi bi-trash"></i>' +
                        '</button>' +
                    '</div>' +
                '</div>';
            agendaContainer.appendChild(agendaItem);
        });
        
        // Attach edit event listeners
        document.querySelectorAll('.edit-agenda-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = parseInt(this.getAttribute('data-id'));
                editAgenda(id);
            });
        });
        
        // Attach delete event listeners
        document.querySelectorAll('.delete-agenda-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = parseInt(this.getAttribute('data-id'));
                deleteAgenda(id);
            });
        });
    }

    function editAgenda(id) {
        var agenda = agendaList.find(function(a) { return a.id === id; });
        if (agenda) {
            editingAgendaId = id;
            document.getElementById('agendaNameInput').value = agenda.name;
            document.getElementById('agendaExplanationInput').value = agenda.explanation;
            document.querySelector('#addAgendaModal .modal-title-custom').textContent = 'Edit Agenda';
            openModal(agendaModal);
        }
    }

    function deleteAgenda(id) {
        if (confirm('Are you sure you want to delete this agenda?')) {
            agendaList = agendaList.filter(function(agenda) { return agenda.id !== id; });
            renderAgendaList();
        }
    }

    document.getElementById('addLogbookForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var entry = document.getElementById('logbookEntryInput').value;
        
        if (!entry) {
            alert('Please enter logbook entry title');
            return;
        }
        
        if (agendaList.length === 0) {
            alert('Please add at least one agenda');
            return;
        }
        
        var newEntry;
        if (currentSection === 'A') {
            newEntry = {
                id: nextLogbookIdA++,
                entry: entry,
                status: 'Waiting for approval',
                file: entry.replace(/\s+/g, '_') + '.pdf',
                agendas: agendaList
            };
            logbookDataA.push(newEntry);
        } else {
            newEntry = {
                id: nextLogbookIdB++,
                entry: entry,
                status: 'Waiting for approval',
                file: entry.replace(/\s+/g, '_') + '.pdf',
                agendas: agendaList
            };
            logbookDataB.push(newEntry);
        }
        
        renderTable(currentSection);
        closeModal(addModal);
        this.reset();
        agendaList = [];
        agendaContainer.innerHTML = '';
    });

    // PDF viewer modal
    document.getElementById('closePdfModal').onclick = function() {
        document.getElementById('pdfViewer').src = '';
        closeModal(document.getElementById('pdfViewerModal'));
    };

    // Initialize
    window.onload = function() {
        document.getElementById("nameSide").style.display = "none";
        closeNav();
        renderTable('A');
        renderTable('B');
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

