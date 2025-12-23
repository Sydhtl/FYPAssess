<?php
include '../../../php/mysqlConnect.php';
session_start();

// Prevent caching to avoid back button access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (!isset($_SESSION['upmId']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../../login/Login.php");
    exit();
}
$studentId = $_SESSION['upmId'];
$query = "SELECT s.Student_Name, s.Semester, fs.FYP_Session, fs.FYP_Session_ID, c.Course_Code FROM student s LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID LEFT JOIN course c ON fs.Course_ID = c.Course_ID WHERE s.Student_ID = ? ORDER BY fs.FYP_Session_ID DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();
$stmt->close();
$studentName = $student['Student_Name'] ?? 'N/A';
$courseCode = $student['Course_Code'] ?? 'N/A';
$semesterRaw = $student['Semester'] ?? 'N/A';
$fypSession = $student['FYP_Session'] ?? 'N/A';
$section = isset($_GET['section']) && ($_GET['section']=='A' || $_GET['section']=='B') ? $_GET['section'] : 'A';
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Logbook - FYPAssess</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/student/logbook.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .fullscreen-logbook-wrapper { padding: 25px 35px; }
        .fullscreen-header { margin-bottom:25px; }
        .fullscreen-header h3 { text-align:center; font-weight:800; font-family: 'Montserrat', Arial, sans-serif; margin:0 0 15px 0; }
        .fullscreen-header .btn { background-color:white !important; color:black !important; border:1px solid black !important; width: fit-content; margin-left: auto; display: block; }
        .agenda-items { min-height:500px; border:2px solid white; padding:10px; border-radius:6px; background:#E6E6E6; overflow-y:auto; }
        .back-link { text-decoration:none; }
        /* Ensure layout height and scroll behavior */
        #main { padding-right: 10px; }
        .fullscreen-logbook-wrapper .logbook-modal-grid { min-height: 65vh; }
        .logbook-modal-col.right-col { 
            display:flex; 
            flex-direction:column;
        }
        .right-col-footer { display:none; }
        .form-action-buttons { margin-top:20px; display: flex; gap: 10px; justify-content:flex-end;  padding-bottom: 10px; }
        .agenda-item { display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px; padding:8px; background:white; border-radius:4px; }
        .agenda-item-content { flex:1; }
        .agenda-item .btn-danger { flex-shrink:0; }
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

    <div id="main">
    <div class="logbook-container">
    <div class="fullscreen-logbook-wrapper">
        <div class="fullscreen-header">
            <h3>Add New Logbook (Section <?php echo $courseCode; ?>)</h3>
            <button type="button" id="headerAddAgendaBtn" class="btn add-agenda-btn" style="white-space:nowrap;">
                <i class="bi bi-plus-circle" style="margin-right:6px;"></i>Add Agenda
            </button>
        </div>
        <form id="fullscreenAddLogbookForm" class="mb-4">
            <div class="logbook-modal-grid" style="grid-template-columns:1fr 1fr;">
                <div class="logbook-modal-col">
                    <div class="modal-field mb-3">
                        <label class="modal-label">Name</label>
                        <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($studentName); ?>" readonly>
                    </div>
                    <div class="modal-field mb-3">
                        <label class="modal-label">Logbook Entry Title</label>
                        <input type="text" id="fullscreenLogbookEntryInput" class="form-control" placeholder="Enter logbook entry title" required>
                    </div>
                    <div class="modal-field mb-3">
                        <label class="modal-label">Date</label>
                        <input type="date" id="fullscreenLogbookDateInput" class="form-control" required>
                    </div>
                </div>
                <div class="logbook-modal-col right-col">
                    <div class="right-col-body" style="flex:1;">
                        <label class="modal-label">Agendas</label>
                        <div class="agenda-items" id="fullscreenAgendaItems"></div>
                    </div>
                    <div class="form-action-buttons">
                        <button type="button" class="btn btn-light border" onclick="window.location.href='logbook.php'">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Logbook</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    </div>
    </div>

    <!-- Agenda Popup -->
    <div id="fullscreenAgendaModal" class="custom-modal" style="display:none;">
        <div class="modal-dialog">
            <div class="modal-content-custom">
                <span class="close-btn" id="closeFullscreenAgendaModal">&times;</span>
                <div class="modal-title-custom">Add Agenda</div>
                <form id="fullscreenAddAgendaForm">
                    <div class="modal-field">
                        <label class="modal-label">Agenda Name:</label>
                        <input type="text" id="fullscreenAgendaNameInput" class="form-control" required>
                    </div>
                    <div class="modal-field">
                        <label class="modal-label">Agenda Explanation:</label>
                        <textarea id="fullscreenAgendaExplanationInput" class="form-control" rows="4" required></textarea>
                    </div>
                    <div class="modal-buttons">
                        <button type="button" id="cancelFullscreenAgendaBtn" class="btn btn-light border">Cancel</button>
                        <button type="submit" class="btn btn-success">Add</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    // Sidebar functionality (same behavior as other pages)
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

    var currentSection = '<?php echo $section; ?>';
    var fullscreenAgendaList = [];
    var agendaItemsContainer = document.getElementById('fullscreenAgendaItems');
    var agendaModal = document.getElementById('fullscreenAgendaModal');

    function openModal(m){ m.style.display='block'; }
    function closeModal(m){ m.style.display='none'; }

    document.getElementById('headerAddAgendaBtn').addEventListener('click', function(){
        document.getElementById('fullscreenAddAgendaForm').reset();
        openModal(agendaModal);
    });
    document.getElementById('closeFullscreenAgendaModal').onclick = function(){ closeModal(agendaModal); };
    document.getElementById('cancelFullscreenAgendaBtn').onclick = function(){ closeModal(agendaModal); };

    document.getElementById('fullscreenAddAgendaForm').addEventListener('submit', function(e){
        e.preventDefault();
        var name = document.getElementById('fullscreenAgendaNameInput').value.trim();
        var explanation = document.getElementById('fullscreenAgendaExplanationInput').value.trim();
        if(!name || !explanation){ alert('Please fill all agenda fields'); return; }
        if(explanation.length > 1000){ explanation = explanation.substring(0,1000) + '...'; }
        fullscreenAgendaList.push({ id: Date.now(), name: name, explanation: explanation });
        renderAgendaList();
        closeModal(agendaModal);
    });

    function renderAgendaList(){
        agendaItemsContainer.innerHTML='';
        if(fullscreenAgendaList.length===0){
            agendaItemsContainer.innerHTML='<div class="text-muted" style="font-size:13px;">No agendas added yet.</div>';
            return;
        }
        fullscreenAgendaList.forEach(function(a){
            var div=document.createElement('div');
            div.className='agenda-item';
            var contentDiv = document.createElement('div');
            contentDiv.className='agenda-item-content';
            contentDiv.innerHTML='<div class="agenda-item-title">'+a.name+'</div>'+
                '<div class="agenda-item-explanation">'+a.explanation+'</div>';
            var deleteBtn = document.createElement('button');
            deleteBtn.type='button';
            deleteBtn.className='btn btn-sm btn-danger';
            deleteBtn.setAttribute('data-id', a.id);
            deleteBtn.innerHTML='<i class="bi bi-trash" style="color:red;"></i>';
            deleteBtn.style.border = 'none';
            deleteBtn.style.background = 'transparent';
            deleteBtn.addEventListener('click', function(){
                var id=parseInt(this.getAttribute('data-id'));
                fullscreenAgendaList = fullscreenAgendaList.filter(function(x){return x.id!==id;});
                renderAgendaList();
            });
            div.appendChild(contentDiv);
            div.appendChild(deleteBtn);
            agendaItemsContainer.appendChild(div);
        });
    }

    document.getElementById('fullscreenAddLogbookForm').addEventListener('submit', function(e){
        e.preventDefault();
        var entryTitle = document.getElementById('fullscreenLogbookEntryInput').value.trim();
        var entryDate = document.getElementById('fullscreenLogbookDateInput').value;
        if(!entryTitle){ alert('Enter logbook entry title'); return; }
        if(!entryDate){ alert('Please select a date'); return; }
        if(fullscreenAgendaList.length === 0){ alert('Please add at least one agenda before saving the logbook'); return; }
        
        // Send data to PHP backend
        var formData = new FormData();
        formData.append('logbook_title', entryTitle);
        formData.append('logbook_date', entryDate);
        formData.append('agendas', JSON.stringify(fullscreenAgendaList));
        formData.append('course_id', '<?php echo $courseId; ?>');
        formData.append('student_id', '<?php echo $studentId; ?>');
        
        // Show loading modal
        showLoadingModal('Saving logbook and sending notification to supervisor. Please wait...');
        
        fetch('save_logbook.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.success){
                window.location.href = 'logbook.php';
            } else {
                hideLoadingModal();
                alert('Error: ' + (data.error || 'Failed to save logbook'));
            }
        })
        .catch(error => {
            hideLoadingModal();
            console.error('Error:', error);
            alert('An error occurred while saving the logbook');
        });
    });

    // Loading modal functions
    function showLoadingModal(message) {
        let loadingModal = document.getElementById('loadingModal');
        if (!loadingModal) {
            loadingModal = document.createElement('div');
            loadingModal.id = 'loadingModal';
            loadingModal.className = 'custom-modal';
            loadingModal.style.display = 'none';
            loadingModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content-custom">
                        <div class="modal-icon" style="color: #007bff;"><i class="bi bi-hourglass-split" style="animation: spin 1s linear infinite; font-size: 48px;"></i></div>
                        <div class="modal-title-custom">Processing...</div>
                        <div class="modal-message" id="loadingModalMessage">${message || 'Saving data and sending emails. Please wait.'}</div>
                    </div>
                </div>
            `;
            document.body.appendChild(loadingModal);
        }
        const messageEl = document.getElementById('loadingModalMessage');
        if (messageEl && message) {
            messageEl.textContent = message;
        }
        loadingModal.style.display = 'flex';
    }
    
    function hideLoadingModal() {
        const loadingModal = document.getElementById('loadingModal');
        if (loadingModal) {
            loadingModal.style.display = 'none';
        }
    }
    
    // Add spinner animation
    if (!document.getElementById('loadingModalStyles')) {
        const style = document.createElement('style');
        style.id = 'loadingModalStyles';
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    }

    // Initialize sidebar state like other pages
    window.onload = function(){
        var nameSide = document.getElementById('nameSide');
        if (nameSide) nameSide.style.display = 'none';
        closeNav();
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