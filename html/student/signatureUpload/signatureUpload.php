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

$query = "SELECT 
    s.Student_ID,
    s.Student_Name,
    s.Semester,
    fs.FYP_Session,
    fs.FYP_Session_ID,
    c.Course_Code
FROM student s
LEFT JOIN fyp_session fs ON s.FYP_Session_ID = fs.FYP_Session_ID
LEFT JOIN course c ON fs.Course_ID = c.Course_ID
WHERE s.Student_ID = ?
ORDER BY fs.FYP_Session_ID DESC
LIMIT 1";

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
?>
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Signature Submission</title>
    <link rel="stylesheet" href="../../../css/student/dashboard.css">
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/student/signatureUpload.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            <a href="../logbook/logbook.php" id="logbookSubmission"><i class="bi bi-file-earmark-text-fill" style="padding-right: 10px;"></i>Logbook Submission</a>
            <a href="../notification/notification.php" id="notification"><i class="bi bi-bell-fill" style="padding-right: 10px;"></i>Notification</a>
            <a href="./signatureUpload.php" id="signatureSubmission" class="focus"><i class="bi bi-pen-fill" style="padding-right: 10px;"></i>Signature Submission</a>
          
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
    <div class="signature-container">
        <h4 class="section-title">Signature Submission</h4>
        
        <form id="signatureForm">
            <div class="upload-section">
                <label class="upload-label">Signature</label>
                <div class="file-upload-area" id="signatureUploadArea">
                    <input type="file" id="signatureFileInput" accept="image/*" style="display: none;">
                    <div id="uploadPrompt" style="display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <i class="bi bi-arrow-up-circle file-upload-icon"></i>
                        <p class="file-upload-text">Upload File Here</p>
                    </div>
                    <div id="filePreview" style="display: none; width: 100%; height: 100%; position: relative;">
                        <img id="previewImage" alt="Signature preview">
                        <div class="preview-overlay">
                            <p class="preview-file-name" id="previewFileName"></p>
                            <button type="button" class="btn-change-file" id="changeFileBtn">Change File</button>
                        </div>
                    </div>
                </div>
                <p class="upload-instruction">Note: The file must be an image file (JPG, JPEG, PNG, or any other image format).</p>
            </div>
            
            <div class="form-buttons">
                <button type="button" id="cancelBtn" class="btn btn-light border">Cancel</button>
                <button type="submit" id="saveBtn" class="btn btn-success">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload Success Modal -->
<div id="uploadSuccessModal" class="custom-modal">
    <div class="modal-dialog">
        <div class="modal-content-custom">
            <span class="close-btn" id="closeUploadModal">&times;</span>
            <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div class="modal-title-custom">Upload Successful!</div>
            <div class="modal-message">Your signature has been uploaded successfully.</div>
            <div style="display:flex; justify-content:center;">
                <button id="okUploadBtn" class="btn btn-success" type="button">OK</button>
            </div>
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

    // File upload functionality
    var signatureUploadArea = document.getElementById('signatureUploadArea');
    var signatureFileInput = document.getElementById('signatureFileInput');
    var signatureFileName = document.getElementById('signatureFileName');

    signatureUploadArea.addEventListener('click', function() {
        signatureFileInput.click();
    });

    signatureUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        signatureUploadArea.style.backgroundColor = '#e8e8e8';
    });

    signatureUploadArea.addEventListener('dragleave', function() {
        signatureUploadArea.style.backgroundColor = '#ffffff';
    });

    signatureUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        signatureUploadArea.style.backgroundColor = '#ffffff';
        var files = e.dataTransfer.files;
        if (files.length > 0) {
            signatureFileInput.files = files;
            var file = files[0];
            uploadedFile = file;
            displayFilePreview(file);
        }
    });

    signatureFileInput.addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            var file = e.target.files[0];
            // Update preview immediately when new file is selected
            uploadedFile = file;
            displayFilePreview(file);
        }
    });

    // Modal functions
    function openModal(modal) { modal.style.display = 'block'; }
    function closeModal(modal) { modal.style.display = 'none'; }

    // Store uploaded file
    var uploadedFile = null;
    var uploadedFileUrl = null;
    var fileSaved = false; // Track if file has been saved

    // Display file preview
    function displayFilePreview(file) {
        var uploadPrompt = document.getElementById('uploadPrompt');
        var filePreview = document.getElementById('filePreview');
        var previewImage = document.getElementById('previewImage');
        var previewFileName = document.getElementById('previewFileName');

        uploadPrompt.style.display = 'none';
        filePreview.style.display = 'block';

        // Display image preview
        var reader = new FileReader();
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            previewImage.style.display = 'block';
        };
        reader.readAsDataURL(file);

        previewFileName.textContent = file.name;
        uploadedFile = file;
    }

    // Reset to upload prompt
    function resetToUploadPrompt() {
        var uploadPrompt = document.getElementById('uploadPrompt');
        var filePreview = document.getElementById('filePreview');
        uploadPrompt.style.display = 'flex';
        filePreview.style.display = 'none';
        uploadedFile = null;
        uploadedFileUrl = null;
        fileSaved = false;
    }

    // Change file button
    document.getElementById('changeFileBtn').addEventListener('click', function(e) {
        e.stopPropagation();
        signatureFileInput.click();
    });

    // Form submission
    var uploadModal = document.getElementById('uploadSuccessModal');
    
    document.getElementById('signatureForm').addEventListener('submit', function(e) {
        e.preventDefault();
        var file = signatureFileInput.files[0];
        
        if (!file && !uploadedFile) {
            alert('Please upload a signature file');
            return;
        }
        
        var formData = new FormData();
        formData.append('signature_file', file || uploadedFile);

        var saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.textContent = 'Uploading...';

        fetch('save_signature.php', { method: 'POST', body: formData })
            .then(async r => {
                const raw = await r.text();
                let data = {};
                try { data = raw ? JSON.parse(raw) : {}; } catch(e) { throw new Error('Invalid JSON: ' + raw.substring(0,300)); }
                if (!r.ok || !data.success) { throw new Error(data.error || ('HTTP ' + r.status)); }
                return data;
            })
            .then(() => {
                openModal(uploadModal);
                fileSaved = true;
            })
            .catch(err => { alert('Upload error: ' + err.message); })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            });
    });

    // Close upload modal and show preview
    document.getElementById('closeUploadModal').onclick = function() {
        closeModal(uploadModal);
        if (uploadedFile) {
            displayFilePreview(uploadedFile);
            fileSaved = true; // Mark as saved after clicking OK
        }
    };
    document.getElementById('okUploadBtn').onclick = function() {
        closeModal(uploadModal);
        if (uploadedFile) {
            displayFilePreview(uploadedFile);
            fileSaved = true; // Mark as saved after clicking OK
        }
    };

    document.getElementById('cancelBtn').addEventListener('click', function() {
        // If no file has been saved yet, reset to upload prompt
        if (!fileSaved) {
            document.getElementById('signatureForm').reset();
            resetToUploadPrompt();
            uploadedFile = null;
        }
    });

    // Initialize
    window.onload = function() {
        document.getElementById("nameSide").style.display = "none";
        closeNav();
        // Preload existing signature (image/pdf/any file)
        fetch('get_signature.php')
            .then(r => {
                if (!r.ok) throw new Error('No signature');
                return r.blob();
            })
            .then(blob => {
                var url = URL.createObjectURL(blob);
                var type = blob.type || '';
                var uploadPrompt = document.getElementById('uploadPrompt');
                var filePreview = document.getElementById('filePreview');
                var previewImage = document.getElementById('previewImage');
                var previewFileName = document.getElementById('previewFileName');
                uploadPrompt.style.display = 'none';
                filePreview.style.display = 'block';
                if (type.startsWith('image/')) {
                    previewImage.src = url;
                    previewImage.style.display = 'block';
                    previewFileName.textContent = 'Existing signature (' + type + ')';
                } else {
                    // For non-image, show a simple link
                    previewImage.style.display = 'none';
                    previewFileName.innerHTML = 'Existing file (' + type + ') - <a href="'+url+'" target="_blank">Open/Download</a>';
                }
                fileSaved = true;
            })
            .catch(() => {
                // No existing signature; keep prompt
            });
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
