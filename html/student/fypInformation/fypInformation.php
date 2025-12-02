<?php
include '../../../php/mysqlConnect.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../../login/Login.php");
    exit();
}

$studentId = $_SESSION['upmId'];

// --- 1. GET CURRENT STUDENT INFO ---
// UPDATED: Added fp.Proposed_Title to the query
$query = "SELECT 
    s.Student_ID,
    s.Student_Name,
    s.Semester,
    s.Department_ID,
    d.Programme_Name,
    fs.FYP_Session,
    c.Course_Code,
    l.Lecturer_Name,
    fp.Project_Title,
    fp.Proposed_Title 
FROM student s
LEFT JOIN department d ON s.Department_ID = d.Department_ID
LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
LEFT JOIN course c ON fs.Course_ID = c.Course_ID
LEFT JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
LEFT JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
LEFT JOIN fyp_project fp ON s.Student_ID = fp.Student_ID
WHERE s.Student_ID = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: ../../login/Login.php");
    exit();
}

$student = $result->fetch_assoc();
$stmt->close();

// Set default values
$studentName = $student['Student_Name'] ?? 'N/A';
$matricNo = $student['Student_ID'] ?? 'N/A';
$programmeName = $student['Programme_Name'] ?? 'N/A';
$departmentId = isset($student['Department_ID']) ? (int)$student['Department_ID'] : null;
$semesterRaw = $student['Semester'] ?? 'N/A';
$fypSession = $student['FYP_Session'] ?? 'N/A';
$courseCode = $student['Course_Code'] ?? 'N/A';
$supervisorName = $student['Lecturer_Name'] ?? 'N/A';
$projectTitle = $student['Project_Title'] ?? 'No title assigned';
// Check if there is a proposed title pending
$proposedTitle = $student['Proposed_Title'] ?? '';
$hasPendingTitle = !empty($proposedTitle);

$semesterDisplay = $semesterRaw . '-' . $fypSession;

// --- 2. GET PAST TITLES (DYNAMIC) ---
$pastTitlesBase = "SELECT 
    l.Lecturer_Name as supervisor_name,
    fs.FYP_Session as session,
    s.Student_Name as student_name,
    fp.Project_Title as title
FROM fyp_project fp
JOIN student s ON fp.Student_ID = s.Student_ID
JOIN supervisor sup ON s.Supervisor_ID = sup.Supervisor_ID
JOIN lecturer l ON sup.Lecturer_ID = l.Lecturer_ID
JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
WHERE fp.Project_Title IS NOT NULL AND fp.Project_Title != '' AND l.Department_ID = ?";

// If current session looks like YYYY/YYYY, filter to earlier sessions only
$hasValidSession = isset($fypSession) && preg_match('/^\d{4}\/\d{4}$/', $fypSession);
$pastTitlesQuery = $pastTitlesBase . ($hasValidSession ? " AND fs.FYP_Session < ?" : "") . " ORDER BY l.Lecturer_Name ASC, fs.FYP_Session DESC";

$pastStmt = $conn->prepare($pastTitlesQuery);
if ($pastStmt) {
    if ($hasValidSession) {
        $pastStmt->bind_param("is", $departmentId, $fypSession);
    } else {
        $pastStmt->bind_param("i", $departmentId);
    }
    $pastStmt->execute();
    $pastResult = $pastStmt->get_result();
    $pastStmt->close();
} else {
    $pastResult = false;
}

$pastDataStructure = [];
$tempData = [];

if ($pastResult && $pastResult->num_rows > 0) {
    while($row = $pastResult->fetch_assoc()) {
        $supName = $row['supervisor_name'];
        $session = $row['session'];
        
        if (!isset($tempData[$supName])) {
            $tempData[$supName] = [];
        }
        if (!isset($tempData[$supName][$session])) {
            $tempData[$supName][$session] = [];
        }
        
        $tempData[$supName][$session][] = [
            'name' => $row['student_name'],
            'title' => $row['title']
        ];
    }
}

foreach ($tempData as $supervisor => $sessions) {
    $sessionList = [];
    foreach ($sessions as $sessName => $students) {
        $sessionList[] = [
            'session' => $sessName,
            'students' => $students
        ];
    }
    $pastDataStructure[] = [
        'supervisor' => $supervisor,
        'sessions' => $sessionList
    ];
}

// --- 3. GET COMMENTS FOR THIS STUDENT ---
$commentsData = [];
$commentStmt = $conn->prepare("SELECT Comment_ID, Given_Comment FROM `comment` WHERE Student_ID = ? ORDER BY Comment_ID ASC");
if ($commentStmt) {
    $commentStmt->bind_param("s", $studentId);
    $commentStmt->execute();
    $commentResult = $commentStmt->get_result();
    while ($cRow = $commentResult->fetch_assoc()) {
        $commentsData[] = [
            'id' => (int)$cRow['Comment_ID'],
            'text' => $cRow['Given_Comment'] ?? ''
        ];
    }
    $commentStmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/student/fypInformation.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        /* CSS for the pending title alert */
        .pending-title-box {
            margin-top: 10px;
            padding: 10px;
            background-color: #fff3cd;
            border: 1px solid #ffecb5;
            border-radius: 5px;
            color: #856404;
            font-size: 0.9em;
        }
        .pending-label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
    </style>
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
                <div id="courseCode"><?php echo htmlspecialchars($courseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars($fypSession . ' - ' . $semesterRaw); ?></div>
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
                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($studentName); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Matric No</label>
                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($matricNo); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Programme</label>
                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($programmeName); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Semester</label>
                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($semesterDisplay); ?>" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Supervisor</label>
                    <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($supervisorName); ?>" readonly>
                </div>
                <div class="col-12">
                    <label class="form-label">Current FYP Title</label>
                    <textarea class="form-control readonly-field" id="fypTitle" rows="3" readonly><?php echo htmlspecialchars($projectTitle); ?></textarea>
                    
                    <div id="pendingContainer" class="pending-title-box" style="display: <?php echo $hasPendingTitle ? 'block' : 'none'; ?>;">
                        <span class="pending-label"><i class="bi bi-hourglass-split"></i> Proposed Title (Under Consideration):</span>
                        <div id="pendingTitleText"><?php echo htmlspecialchars($proposedTitle); ?></div>
                        <small class="text-muted">This title will replace the current title once approved by your supervisor.</small>
                    </div>

                    <div class="mt-2 d-flex align-items-center">
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

    window.onload = function() {
        document.getElementById("nameSide").style.display = "none";
        closeNav();
        
        var tabInfo = document.getElementById('tabInfo');
        var tabPast = document.getElementById('tabPast');
        var infoSection = document.getElementById('fypInfoSection');
        var pastSection = document.getElementById('pastTitlesSection');
        var renderPastTitlesNow;

        function showFYPInfo() {
            tabInfo.classList.add('active-tab');
            tabPast.classList.remove('active-tab');
            infoSection.classList.add('active');
            pastSection.classList.remove('active');
        }

        function showPastTitles() {
            tabInfo.classList.remove('active-tab');
            tabPast.classList.add('active-tab');
            infoSection.classList.remove('active');
            pastSection.classList.add('active');
            setTimeout(function() {
                if (renderPastTitlesNow) { renderPastTitlesNow(); }
            }, 100);
        }

        tabInfo.addEventListener('click', showFYPInfo);
        tabPast.addEventListener('click', showPastTitles);
        
        // --- Title change flow ---
        var changeBtn = document.getElementById('changeTitleBtn');
        var fypTitleTextarea = document.getElementById('fypTitle');
        var pendingContainer = document.getElementById('pendingContainer');
        var pendingText = document.getElementById('pendingTitleText');

        // Build Edit Modal container
        var editModal = document.createElement('div');
        editModal.className = 'custom-modal';
        editModal.id = 'editTitleModal';
        document.body.appendChild(editModal);

        function openModal(modal){ modal.style.display = 'block'; }
        function closeModal(modal){ modal.style.display = 'none'; }

        function renderEditView() {
            editModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <span class="close-btn" id="closeEditModal">&times;</span>
                        <div class="modal-title-custom">Change FYP Title</div>
                        <div class="modal-message">Enter your new proposed title below. An email will be sent to your supervisor for approval.</div>
                        <textarea id="editTitleInput" class="form-control" rows="4"></textarea>
                        <div style="margin-top:15px; display:flex; gap:10px; justify-content:flex-end;">
                            <button id="cancelEdit" class="btn btn-light border" type="button">Cancel</button>
                            <button id="saveEdit" class="btn btn-success" type="button">Save & Send to Supervisor</button>
                        </div>
                    </div>
                </div>`;

            var input = editModal.querySelector('#editTitleInput');
            // Use existing pending title if available, otherwise current title
            var currentPending = pendingText.innerText.trim();
            input.value = currentPending !== '' ? currentPending : fypTitleTextarea.value.trim();

            editModal.querySelector('#closeEditModal').onclick = function(){ closeModal(editModal); };
            editModal.querySelector('#cancelEdit').onclick = function(){ closeModal(editModal); };

            // CLICK SAVE
            editModal.querySelector('#saveEdit').onclick = function(){
                var newTitleVal = input.value.trim();
                var saveBtn = this;
                
                if(newTitleVal === "") {
                    alert("Title cannot be empty.");
                    return;
                }

                // UI loading state
                saveBtn.innerHTML = "Sending...";
                saveBtn.disabled = true;

                // Send to backend via AJAX
                var formData = new FormData();
                formData.append('title', newTitleVal);

                fetch('submit_title_change.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // UPDATE UI: Show Pending Box
                        pendingText.innerText = newTitleVal;
                        pendingContainer.style.display = 'block';
                        
                        // Show Success Modal
                        var contentHtml = `
                            <div class="modal-dialog">
                                <div class="modal-content-custom">
                                    <span class="close-btn" id="closeEditModalAfter">&times;</span>
                                    <div class="modal-icon"><i class="bi bi-send-check-fill"></i></div>
                                    <div class="modal-title-custom">Request Sent</div>
                                    <div class="modal-message">Your proposed title has been sent to your supervisor. It is now under consideration.</div>
                                    <div style="display:flex; justify-content:center;">
                                        <button id="okSubmitted" class="btn btn-success" type="button">OK</button>
                                    </div>
                                </div>
                            </div>`;

                        editModal.innerHTML = contentHtml;
                        editModal.querySelector('#okSubmitted').onclick = function(){
                            closeModal(editModal);
                            // Reload the page to show updated data
                            window.location.reload();
                        };
                        editModal.querySelector('#closeEditModalAfter').onclick = function(){
                            closeModal(editModal);
                            // Reload the page to show updated data
                            window.location.reload();
                        };
                    } else {
                        alert("Error: " + (data.message || "Failed to submit."));
                        saveBtn.innerHTML = "Save & Send to Supervisor";
                        saveBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert("An error occurred connecting to the server.");
                    saveBtn.innerHTML = "Save & Send to Supervisor";
                    saveBtn.disabled = false;
                });
            };
        }

        renderEditView();

        changeBtn.addEventListener('click', function(){
            renderEditView();
            openModal(editModal);
        });

        // --- PAST TITLES LOGIC ---
        var pastData = <?php echo json_encode($pastDataStructure); ?>;
        // --- COMMENTS DATA ---
        var commentsData = <?php echo json_encode($commentsData); ?>;

        function sortSessions(sessions, order) {
            var sorted = sessions.slice();
            sorted.sort(function(a, b) {
                var aYear = parseInt(a.session.split('/')[0]);
                var bYear = parseInt(b.session.split('/')[0]);
                return order === 'latest' ? bYear - aYear : aYear - bYear;
            });
            return sorted;
        }

       function renderPastTitles(data, sortOrder, searchTerm) {
    var acc = document.getElementById('pastAccordion');
    if (!acc) return;

    acc.innerHTML = '';
    var supervisorIndex = 0;
    var hasResults = false;

    data.forEach(function(group) {
        var supervisorCollapseId = 'supervisor-collapse-' + supervisorIndex;

        // Filter sessions/students based on search
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

        var studentsHtml = '';
        sortedSessions.forEach(function(session) {
            session.students.forEach(function(s) {
                studentsHtml += '<div class="student-item row">' +
                    '<div class="col-md-4"><strong>' + s.name + '</strong></div>' +
                    '<div class="col-md-5">' + s.title + '</div>' +
                    '<div class="col-md-3">' + session.session + '</div>' +
                    '</div>';
            });
        });

        var itemHtml = '<div class="supervisor-item">' +
            '<button class="supervisor-btn" type="button" data-bs-toggle="collapse" data-bs-target="#' + supervisorCollapseId + '" aria-expanded="false" aria-controls="' + supervisorCollapseId + '">' +
            '<span>' + group.supervisor + '</span>' +
            '<i class="bi bi-chevron-down"></i>' +
            '</button>' +
            '<div id="' + supervisorCollapseId + '" class="collapse supervisor-content" data-bs-parent="#pastAccordion">' +
            '<div class="students-list row g-2" style="padding:10px;">' +
            '<div class="row font-weight-bold" style="margin-bottom:5px;">' +
            '</div>' + studentsHtml +
            '</div>' +
            '</div>' +
            '</div>';

        acc.insertAdjacentHTML('beforeend', itemHtml);
        supervisorIndex++;
    });

    if (!hasResults && searchTerm) {
        acc.innerHTML = '<div class="no-results text-center p-4 text-muted fst-italic">No results found for "' + searchTerm + '"</div>';
    } else if (!hasResults && !searchTerm) {
        acc.innerHTML = '<div class="no-results text-center p-4 text-muted fst-italic">No past titles found in database.</div>';
    }
}


        var currentSortOrder = 'latest';
        var currentSearchTerm = '';
        renderPastTitlesNow = function() { renderPastTitles(pastData, currentSortOrder, currentSearchTerm); };
        renderPastTitlesNow();

        var searchInput = document.getElementById('pastTitleSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                currentSearchTerm = this.value.trim();
                renderPastTitlesNow();
            });
        }

        var sortSelect = document.getElementById('sortBySession');
        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                currentSortOrder = this.value;
                renderPastTitlesNow();
            });
        }

        // PDF Download functionality (Unchanged logic, just simplified variable access)
        var downloadPdfBtn = document.getElementById('downloadPdfBtn');
        if (downloadPdfBtn) {
            downloadPdfBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                var studentName = '<?php echo addslashes($studentName); ?>';
                var matricNo = '<?php echo addslashes($matricNo); ?>';
                var programme = '<?php echo addslashes($programmeName); ?>';
                var semester = '<?php echo addslashes($semesterDisplay); ?>';
                var supervisor = '<?php echo addslashes($supervisorName); ?>';
                var fypTitle = document.getElementById('fypTitle').value || '<?php echo addslashes($projectTitle); ?>';
                var proposedTitle = '<?php echo addslashes($proposedTitle); ?>';

                function renderPdf(doc) {
                    var pageWidth = 210;
                    var yPos = 10;

                    function ensureSpace(extraHeight) {
                        if (yPos + extraHeight > 285) {
                            doc.addPage();
                            yPos = 20;
                        }
                    }

                    var img = new Image();
                    img.src = '../../../assets/UPMLogo.png';
                    img.onload = function() {
                        var logoWidth = 30;
                        var logoHeight = 20;
                        var xPos = (pageWidth - logoWidth) / 2;
                        doc.addImage(img, 'PNG', xPos, yPos, logoWidth, logoHeight);
                        yPos += 25;

                        doc.setFontSize(18);
                        doc.setFont(undefined, 'bold');
                        doc.text('FYP Information', 105, yPos, { align: 'center' });
                        yPos += 7;

                        doc.setLineWidth(0.5);
                        doc.line(20, yPos, 190, yPos);
                        yPos += 10;

                        doc.setFontSize(12);
                        doc.setFont(undefined, 'bold');
                        doc.text('Student Name:', 20, yPos);
                        doc.setFont(undefined, 'normal');
                        doc.text(studentName, 60, yPos);
                        yPos += 8;

                        doc.setFont(undefined, 'bold');
                        doc.text('Matric No:', 20, yPos);
                        doc.setFont(undefined, 'normal');
                        doc.text(matricNo, 60, yPos);
                        yPos += 8;

                        doc.setFont(undefined, 'bold');
                        doc.text('Programme:', 20, yPos);
                        doc.setFont(undefined, 'normal');
                        doc.text(programme, 60, yPos);
                        yPos += 8;

                        doc.setFont(undefined, 'bold');
                        doc.text('Semester:', 20, yPos);
                        doc.setFont(undefined, 'normal');
                        doc.text(semester, 60, yPos);
                        yPos += 8;

                        doc.setFont(undefined, 'bold');
                        doc.text('Supervisor:', 20, yPos);
                        doc.setFont(undefined, 'normal');
                        doc.text(supervisor, 60, yPos);
                        yPos += 12;

                        doc.setLineWidth(0.5);
                        doc.line(20, yPos, 190, yPos);
                        yPos += 10;

                        doc.setFont(undefined, 'bold');
                        doc.text('Current FYP Title:', 20, yPos);
                        doc.setFont(undefined, 'normal');
                        var titleLines = doc.splitTextToSize(fypTitle, 170);
                        ensureSpace(7 + (titleLines.length * 8));
                        doc.text(titleLines, 20, yPos + 7);
                        yPos += 7 + (titleLines.length * 8);

                        if (proposedTitle && proposedTitle.trim() !== '') {
                            doc.setFont(undefined, 'bold');
                            doc.text('Proposed Title (Under Consideration):', 20, yPos);
                            doc.setFont(undefined, 'normal');
                            var proposedLines = doc.splitTextToSize(proposedTitle, 170);
                            ensureSpace(7 + (proposedLines.length * 8));
                            doc.text(proposedLines, 20, yPos + 7);
                            yPos += 7 + (proposedLines.length * 8);
                        }

                        // Comments section
                        doc.setLineWidth(0.5);
                        doc.line(20, yPos, 190, yPos);
                        yPos += 10;
                        doc.setFont(undefined, 'bold');
                        doc.text('Comments:', 20, yPos);
                        yPos += 7;
                        doc.setFont(undefined, 'normal');
                        if (Array.isArray(commentsData) && commentsData.length > 0) {
                            commentsData.forEach(function(c, idx) {
                                var cText = ((idx + 1) + '. ' + (c.text || '')).trim();
                                var cLines = doc.splitTextToSize(cText, 170);
                                ensureSpace(cLines.length * 8);
                                doc.text(cLines, 20, yPos);
                                yPos += (cLines.length * 8) + 3;
                            });
                        } else {
                            ensureSpace(8);
                            doc.text('(no comments)', 20, yPos);
                            yPos += 8;
                        }

                        doc.setFontSize(8);
                        doc.setFont(undefined, 'normal');
                        doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                        doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });

                        doc.save('FYP_Information_' + matricNo + '.pdf');
                    };

                    img.onerror = function() {
                        // Fallback without logo
                        doc.setFontSize(18);
                        doc.setFont(undefined, 'bold');
                        doc.text('FYP Information', 105, 20, { align: 'center' });
                        doc.setLineWidth(0.5);
                        doc.line(20, 25, 190, 25);

                        doc.setFontSize(12);
                        var y = 35;
                        doc.setFont(undefined, 'bold'); doc.text('Student Name:', 20, y); doc.setFont(undefined, 'normal'); doc.text(studentName, 60, y); y += 8;
                        doc.setFont(undefined, 'bold'); doc.text('Matric No:', 20, y); doc.setFont(undefined, 'normal'); doc.text(matricNo, 60, y); y += 8;
                        doc.setFont(undefined, 'bold'); doc.text('Programme:', 20, y); doc.setFont(undefined, 'normal'); doc.text(programme, 60, y); y += 8;
                        doc.setFont(undefined, 'bold'); doc.text('Semester:', 20, y); doc.setFont(undefined, 'normal'); doc.text(semester, 60, y); y += 8;
                        doc.setFont(undefined, 'bold'); doc.text('Supervisor:', 20, y); doc.setFont(undefined, 'normal'); doc.text(supervisor, 60, y); y += 12;

                        doc.setLineWidth(0.5);
                        doc.line(20, y, 190, y); y += 10;
                        doc.setFont(undefined, 'bold'); doc.text('Current FYP Title:', 20, y); y += 7;
                        doc.setFont(undefined, 'normal');
                        var fLines = doc.splitTextToSize(fypTitle, 170);
                        doc.text(fLines, 20, y);
                        y += (fLines.length * 8);

                        if (proposedTitle && proposedTitle.trim() !== '') {
                            doc.setFont(undefined, 'bold'); doc.text('Proposed Title (Under Consideration):', 20, y); y += 7;
                            doc.setFont(undefined, 'normal');
                            var pLines = doc.splitTextToSize(proposedTitle, 170);
                            doc.text(pLines, 20, y);
                            y += (pLines.length * 8);
                        }

                        // Comments section (fallback)
                        doc.setLineWidth(0.5); doc.line(20, y, 190, y); y += 10;
                        doc.setFont(undefined, 'bold'); doc.text('Comments:', 20, y); y += 7; doc.setFont(undefined, 'normal');
                        if (Array.isArray(commentsData) && commentsData.length > 0) {
                            commentsData.forEach(function(c, idx){
                                var cText = ((idx + 1) + '. ' + (c.text || '')).trim();
                                var cLines = doc.splitTextToSize(cText, 170);
                                doc.text(cLines, 20, y);
                                y += (cLines.length * 8) + 3;
                            });
                        } else {
                            doc.text('(no comments)', 20, y); y += 8;
                        }

                        doc.setFontSize(8);
                        doc.setFont(undefined, 'normal');
                        doc.text('Generated on: ' + new Date().toLocaleString(), 105, 280, { align: 'center' });
                        doc.text('FYPAssess - Final Year Project Assessment System', 105, 285, { align: 'center' });
                        doc.save('FYP_Information_' + matricNo + '.pdf');
                    };
                }

                renderPdf(doc);
            });
        }
    };
    </script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>