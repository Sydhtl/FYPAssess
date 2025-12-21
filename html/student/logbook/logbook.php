<?php
include '../../../php/mysqlConnect.php';
session_start();

// Prevent caching to avoid back button access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../../login/Login.php");
    exit();
}

$studentId = $_SESSION['upmId'];

// Check if a specific FYP session is passed in the URL (from dashboard graph click)
$urlFypSessionId = isset($_GET['fyp_session_id']) ? (int)$_GET['fyp_session_id'] : null;

$query = "SELECT 
    s.Student_ID,
    s.Student_Name,
    s.Semester,
    s.Department_ID,
    fs.FYP_Session,
    fs.FYP_Session_ID,
    c.Course_Code
FROM student s
LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
LEFT JOIN course c ON fs.Course_ID = c.Course_ID
WHERE s.Student_ID = ?";

// If a specific session is passed from the dashboard, use it; otherwise get the latest
if ($urlFypSessionId) {
    $query .= " AND s.FYP_Session_ID = ?";
} else {
    $query .= " ORDER BY fs.FYP_Session_ID DESC LIMIT 1";
}

$stmt = $conn->prepare($query);

if ($urlFypSessionId) {
    $stmt->bind_param("si", $studentId, $urlFypSessionId);
} else {
    $stmt->bind_param("s", $studentId);
}

$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();

$studentName = $student['Student_Name'] ?? 'N/A';
$courseCode = $student['Course_Code'] ?? 'N/A';
$semesterRaw = $student['Semester'] ?? 'N/A';
$fypSession = $student['FYP_Session'] ?? 'N/A';
$departmentId = $student['Department_ID'] ?? null;

// Get courses for this department
$coursesQuery = "SELECT Course_ID, Course_Code FROM course WHERE Department_ID = ? ORDER BY Course_ID";
$stmtCourses = $conn->prepare($coursesQuery);
$stmtCourses->bind_param("i", $departmentId);
$stmtCourses->execute();
$coursesResult = $stmtCourses->get_result();
$departmentCourses = [];
while ($row = $coursesResult->fetch_assoc()) {
    $departmentCourses[] = $row;
}
$stmtCourses->close();

// Ensure we have at least 2 courses for the sections
$course1 = $departmentCourses[0] ?? ['Course_ID' => null, 'Course_Code' => 'N/A'];
$course2 = $departmentCourses[1] ?? ['Course_ID' => null, 'Course_Code' => 'N/A'];

// Check if student is registered for each course
$isRegisteredCourse1 = false;
$isRegisteredCourse2 = false;

if ($course1['Course_ID']) {
    $checkReg1 = $conn->prepare("SELECT 1 FROM fyp_session fs JOIN student s ON s.FYP_Session_ID = fs.FYP_Session_ID WHERE s.Student_ID = ? AND fs.Course_ID = ?");
    $checkReg1->bind_param("si", $studentId, $course1['Course_ID']);
    $checkReg1->execute();
    $isRegisteredCourse1 = $checkReg1->get_result()->num_rows > 0;
    $checkReg1->close();
}

if ($course2['Course_ID']) {
    $checkReg2 = $conn->prepare("SELECT 1 FROM fyp_session fs JOIN student s ON s.FYP_Session_ID = fs.FYP_Session_ID WHERE s.Student_ID = ? AND fs.Course_ID = ?");
    $checkReg2->bind_param("si", $studentId, $course2['Course_ID']);
    $checkReg2->execute();
    $isRegisteredCourse2 = $checkReg2->get_result()->num_rows > 0;
    $checkReg2->close();
}

// Fetch logbook entries for this student
// Check if logbook table exists first
$logbookEntries = [];
$checkTableQuery = "SHOW TABLES LIKE 'logbook'";
$tableExists = $conn->query($checkTableQuery);

if ($tableExists && $tableExists->num_rows > 0) {
    $logbookQuery = "SELECT Logbook_ID, course_id, Logbook_Name, Logbook_Status, Logbook_Date FROM logbook WHERE Student_ID = ? ORDER BY Logbook_Date DESC";
    $stmtLogbook = $conn->prepare($logbookQuery);
    if ($stmtLogbook) {
        $stmtLogbook->bind_param("s", $studentId);
        $stmtLogbook->execute();
        $logbookResult = $stmtLogbook->get_result();
        while ($row = $logbookResult->fetch_assoc()) {
            // Fetch agendas for this logbook
            $agendas = [];
            $agendaQuery = "SELECT Agenda_Title, Agenda_Content FROM logbook_agenda WHERE Logbook_ID = ? ORDER BY Agenda_ID";
            $stmtAgenda = $conn->prepare($agendaQuery);
            if ($stmtAgenda) {
                $stmtAgenda->bind_param("i", $row['Logbook_ID']);
                $stmtAgenda->execute();
                $agendaResult = $stmtAgenda->get_result();
                while ($agendaRow = $agendaResult->fetch_assoc()) {
                    $agendas[] = [
                        'name' => $agendaRow['Agenda_Title'],
                        'explanation' => $agendaRow['Agenda_Content']
                    ];
                }
                $stmtAgenda->close();
            }
            $row['agendas'] = $agendas;
            $logbookEntries[] = $row;
        }
        $stmtLogbook->close();
    }
}
?>
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
            <span id="nameSide">HI, <?php echo htmlspecialchars($studentName); ?></span>
            <a href="../dashboard/dashboard.php" id="dashboard"> <i class="bi bi-house-fill" style="padding-right: 10px;"></i>Dashboard</a>
            <a href="../fypInformation/fypInformation.php" id="fypInformation"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>FYP Information</a>
            <a href="./logbook.php" id="logbookSubmission" class="focus"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>Logbook Submission</a>
            <a href="../notification/notification.php" id="notification"><i class="bi bi-bell-fill" style="padding-right: 10px;"></i>Notification</a>
            <a href="../signatureUpload/signatureUpload.php" id="signatureSubmission"><i class="bi bi-pen-fill" style="padding-right: 10px;"></i>Signature Submission</a>
          
            <a href="../../logout.php" id="logout">
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
                <div id="courseCode"><?php echo htmlspecialchars($courseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars($fypSession . ' - ' . $semesterRaw); ?></div>
            </div>
        </div>
    </div>

<div id="main" style="overflow-x: hidden; max-width: 100%; box-sizing: border-box;">
    <div class="logbook-container">
        <div class="tab-buttons">
            <button id="tabSweA" class="task-tab active-tab"><?php echo htmlspecialchars($course1['Course_Code']); ?></button>
            <button id="tabSweB" class="task-tab"><?php echo htmlspecialchars($course2['Course_Code']); ?></button>
        </div>
        
        <div id="sweASection" class="task-group active">
            <div style="margin-top: 15px;">
                <h4 class="section-title" id="sectionTitleA">Logbook Submission <?php echo htmlspecialchars($course1['Course_Code']); ?></h4>
                
                <div class="name-section">
                    <div class="filter-inline-group">
                        <span class="filter-inline-label">Filter by status:</span>
                        <select id="statusFilterA" class="status-filter-dropdown" onchange="filterLogbookTable('A')">
                            <option value="all">All</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <?php if ($isRegisteredCourse1): ?>
                    <a href="add_logbook.php?section=A&course_id=<?php echo $course1['Course_ID']; ?>" class="btn btn-outline-dark add-row-btn" style="background-color: white; color: black;">
                        <i class="bi bi-plus-circle" style="margin-right: 8px; color: black;"></i>Add new row
                    </a>
                    <?php endif; ?>
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
                    <div class="remaining-box" id="remainingCountA">6</div>
                </div>
            </div>
        </div>

        <div id="sweBSection" class="task-group">
            <div style="margin-top: 15px;">
                <h4 class="section-title" id="sectionTitleB">Logbook Submission <?php echo htmlspecialchars($course2['Course_Code']); ?></h4>
                
                <div class="name-section">
                    <div class="filter-inline-group">
                        <span class="filter-inline-label">Filter by status:</span>
                        <select id="statusFilterB" class="status-filter-dropdown" onchange="filterLogbookTable('B')">
                            <option value="all">All</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <?php if ($isRegisteredCourse2): ?>
                    <a href="add_logbook.php?section=B&course_id=<?php echo $course2['Course_ID']; ?>" class="btn btn-outline-dark add-row-btn" style="background-color: white; color: black;">
                        <i class="bi bi-plus-circle" style="margin-right: 8px; color: black;"></i>Add new row
                    </a>
                    <?php endif; ?>
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
                    <div class="remaining-box" id="remainingCountB">6</div>
                </div>
            </div>
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

    // Logbook data storage - separate for each section (populated from PHP)
    var course1Id = <?php echo json_encode($course1['Course_ID']); ?>;
    var course2Id = <?php echo json_encode($course2['Course_ID']); ?>;
    
    var logbookDataA = <?php 
        $dataA = [];
        $counterA = 1;
        foreach ($logbookEntries as $entry) {
            if ($entry['course_id'] == $course1['Course_ID']) {
                $dataA[] = [
                    'id' => $counterA++,
                    'logbook_id' => $entry['Logbook_ID'],
                    'entry' => $entry['Logbook_Name'],
                    'status' => $entry['Logbook_Status'] ?? 'Waiting for approval',
                    'file' => 'logbook' . $entry['Logbook_ID'] . '.pdf',
                    'agendas' => $entry['agendas'] ?? []
                ];
            }
        }
        echo json_encode($dataA);
    ?>;

    var logbookDataB = <?php 
        $dataB = [];
        $counterB = 1;
        foreach ($logbookEntries as $entry) {
            if ($entry['course_id'] == $course2['Course_ID']) {
                $dataB[] = [
                    'id' => $counterB++,
                    'logbook_id' => $entry['Logbook_ID'],
                    'entry' => $entry['Logbook_Name'],
                    'status' => $entry['Logbook_Status'] ?? 'Waiting for approval',
                    'file' => 'logbook' . $entry['Logbook_ID'] . '.pdf',
                    'agendas' => $entry['agendas'] ?? []
                ];
            }
        }
        echo json_encode($dataB);
    ?>;

    var totalLogbooks = 6;
    var nextLogbookIdA = 6;
    var nextLogbookIdB = 6;
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

    function filterLogbookTable(section) {
        var filterElement = section === 'A' ? document.getElementById('statusFilterA') : document.getElementById('statusFilterB');
        var selectedStatus = filterElement.value;
        renderTable(section, selectedStatus);
    }
    
    // Check for status filter from URL parameter and apply it on page load
    function applyStatusFilterFromURL() {
        var urlParams = new URLSearchParams(window.location.search);
        var statusFilter = urlParams.get('status');
        
        if (statusFilter) {
            // Apply filter to both sections
            var filterElementA = document.getElementById('statusFilterA');
            var filterElementB = document.getElementById('statusFilterB');
            
            // Map status values (normalize to lowercase)
            var statusValue = statusFilter.toLowerCase();
            
            // Validate status value
            if (statusValue === 'approved' || statusValue === 'rejected' || statusValue === 'pending') {
                if (filterElementA) {
                    filterElementA.value = statusValue;
                    renderTable('A', statusValue);
                }
                
                if (filterElementB) {
                    filterElementB.value = statusValue;
                    renderTable('B', statusValue);
                }
            }
        }
    }

    function getStatusClass(status) {
        if (status === 'Approved') return 'status-approved';
        if (status === 'Rejected') return 'status-rejected';
        return 'status-waiting';
    }

    function renderTable(section, statusFilter) {
        var logbookData = section === 'A' ? logbookDataA : logbookDataB;
        var tbodyId = section === 'A' ? 'logbookTableBodyA' : 'logbookTableBodyB';
        var tbody = document.getElementById(tbodyId);
        tbody.innerHTML = '';
        
        // Filter data based on status
        var filteredData = logbookData;
        if (statusFilter && statusFilter !== 'all') {
            filteredData = logbookData.filter(function(item) {
                var itemStatus = item.status.toLowerCase();
                if (statusFilter === 'pending') {
                    return itemStatus === 'waiting for approval' || itemStatus === 'pending';
                }
                return itemStatus === statusFilter;
            });
        }
        
        filteredData.forEach(function(item) {
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
            // Get the actual Logbook_ID from the data
            var logbookData = section === 'A' ? logbookDataA : logbookDataB;
            var logbookEntry = logbookData.find(function(item) { return item.id === id; });
            
            if (!logbookEntry) {
                alert('Logbook entry not found');
                return;
            }
            
            var formData = new FormData();
            formData.append('logbook_id', logbookEntry.logbook_id);
            
            fetch('delete_logbook.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove from local data and re-render
                    if (section === 'A') {
                        logbookDataA = logbookDataA.filter(function(item) { return item.id !== id; });
                    } else {
                        logbookDataB = logbookDataB.filter(function(item) { return item.id !== id; });
                    }
                    renderTable(section);
                } else {
                    alert('Error deleting logbook: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the logbook');
            });
        }
    }

    // Inject server-derived student/course/session info
    var studentNameDB = <?php echo json_encode($studentName); ?>;
    var sessionDB = <?php echo json_encode(($fypSession ? $fypSession : 'N/A') . ' - ' . ($semesterRaw !== 'N/A' ? $semesterRaw : '')); ?>;
    var courseCodeA = <?php echo json_encode($course1['Course_Code']); ?>;
    var courseCodeB = <?php echo json_encode($course2['Course_Code']); ?>;

    function generateLogbookPdf(id, entry, status, section) {
        // Get jsPDF from the UMD bundle
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        var studentName = studentNameDB || 'N/A';
        var courseCode = section === 'A' ? (courseCodeA || 'N/A') : (courseCodeB || 'N/A');
        var session = sessionDB || 'N/A';
        
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
            doc.text('Session:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(session, 70, yPos);
            yPos += lineHeight ;
            
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
            doc.text('Session:', 20, yPos);
            doc.setFont(undefined, 'normal');
            doc.text(session, 70, yPos);
            yPos += lineHeight ;
            
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

    // Load extra entries saved via fullscreen add page
    function loadExtraEntries() {
        // Clear old localStorage data - now using database
        localStorage.removeItem('logbookExtraA');
        localStorage.removeItem('logbookExtraB');
    }

    // PDF viewer modal
    document.getElementById('closePdfModal').onclick = function() {
        document.getElementById('pdfViewer').src = '';
        closeModal(document.getElementById('pdfViewerModal'));
    };

    // Initialize
    window.onload = function() {
        document.getElementById("nameSide").style.display = "none";
        closeNav();
        loadExtraEntries();
        
        // Check for status filter from URL parameter
        var urlParams = new URLSearchParams(window.location.search);
        var statusFilter = urlParams.get('status');
        
        if (statusFilter) {
            // Apply status filter from URL parameter
            applyStatusFilterFromURL();
        } else {
            // No filter, show all
            renderTable('A', 'all');
            renderTable('B', 'all');
        }

        // Start real-time polling for logbook updates
        startLogbookPolling();
    };

    // --- REAL-TIME LOGBOOK UPDATES ---
    var logbookUpdateInterval = null;
    var currentLogbookHash = '';

    function getLogbookHash(entries) {
        try {
            return JSON.stringify(entries.map(function(e){
                return {
                    id: e.Logbook_ID,
                    status: (e.Logbook_Status || '').toLowerCase(),
                    course: e.course_id,
                    name: e.Logbook_Name
                };
            }));
        } catch (e) { return ''; }
    }

    function mapEntriesToSectionData(entries, courseId) {
        var list = [];
        var counter = 1;
        entries.forEach(function(e){
            if (e.course_id === courseId) {
                list.push({
                    id: counter++,
                    logbook_id: e.Logbook_ID,
                    entry: e.Logbook_Name,
                    status: e.Logbook_Status || 'Waiting for approval',
                    file: 'logbook' + e.Logbook_ID + '.pdf',
                    agendas: Array.isArray(e.agendas) ? e.agendas : []
                });
            }
        });
        return list;
    }

    function fetchLogbooks() {
        fetch('../../../php/phpStudent/fetch_logbook.php')
            .then(function(resp){ return resp.json(); })
            .then(function(data){
                if (!data || !data.success || !Array.isArray(data.entries)) return;
                var newHash = getLogbookHash(data.entries);
                if (newHash !== currentLogbookHash) {
                    currentLogbookHash = newHash;
                    // Update A/B datasets
                    logbookDataA = mapEntriesToSectionData(data.entries, course1Id);
                    logbookDataB = mapEntriesToSectionData(data.entries, course2Id);

                    // Respect current filters
                    var filterA = (document.getElementById('statusFilterA') || { value: 'all' }).value;
                    var filterB = (document.getElementById('statusFilterB') || { value: 'all' }).value;
                    renderTable('A', filterA || 'all');
                    renderTable('B', filterB || 'all');
                }
            })
            .catch(function(err){ console.error('Error fetching logbooks:', err); });
    }

    function startLogbookPolling() {
        fetchLogbooks();
        logbookUpdateInterval = setInterval(fetchLogbooks, 5000);
    }

    document.addEventListener('visibilitychange', function(){
        if (document.hidden) {
            if (logbookUpdateInterval) { clearInterval(logbookUpdateInterval); logbookUpdateInterval = null; }
        } else {
            if (!logbookUpdateInterval) { startLogbookPolling(); }
        }
    });

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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

