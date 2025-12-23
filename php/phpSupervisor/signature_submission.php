<?php


include '../db_connect.php';

// --- 1b. GET SUPERVISOR ID ---
$supervisorID = null;

// Option A: If your session already holds the integer Supervisor_ID
// $supervisorID = $_SESSION['supervisor_id'] ?? 0;

// Option B: If loginID is a username/staffID (e.g., 'hazura') and you need to look up the integer ID:
$sqlSup = "SELECT Supervisor_ID FROM supervisor WHERE Lecturer_ID = ? LIMIT 1";
$stmtSup = $conn->prepare($sqlSup);
$stmtSup->bind_param("s", $loginID);
$stmtSup->execute();
$resSup = $stmtSup->get_result();

if ($rowSup = $resSup->fetch_assoc()) {
    $supervisorID = $rowSup['Supervisor_ID'];
} else {
    // Fallback for testing or if not found
    $supervisorID = 0;
}
$stmtSup->close();

// 1. CAPTURE ROLE & USER ID
// Check if loginID is in session, otherwise default to 'GUEST'
$loginID = isset($_SESSION['loginID']) ? $_SESSION['loginID'] : 'USER';
$activeRole = isset($_GET['role']) ? $_GET['role'] : 'supervisor';

// 2. PREPARE MODULE TITLE
$moduleTitle = ucfirst($activeRole) . " Module";

// 3. FETCH COURSE INFO
$courseCode = "SWE4949A";
$courseSession = "2024/2025 - 2";

$sqlSession = "SELECT fs.FYP_Session, fs.Semester, c.Course_Code 
               FROM fyp_session fs
               JOIN course c ON fs.Course_ID = c.Course_ID
               ORDER BY fs.FYP_Session DESC, fs.Semester DESC
               LIMIT 1";

$resultSession = $conn->query($sqlSession);
if ($resultSession && $resultSession->num_rows > 0) {
    $sessionRow = $resultSession->fetch_assoc();
    $courseCode = $sessionRow['Course_Code'];
    $courseSession = $sessionRow['FYP_Session'] . " - " . $sessionRow['Semester'];
}
// ... (Your existing code for Course Info)

// 4. FETCH EXISTING SIGNATURE
$existingSignature = null;
$sqlSig = "SELECT Signature_File FROM signature_lecturer WHERE Lecturer_ID = ? LIMIT 1";
$stmtSig = $conn->prepare($sqlSig);
$stmtSig->bind_param("s", $loginID);
$stmtSig->execute();
$stmtSig->store_result();

if ($stmtSig->num_rows > 0) {
    $stmtSig->bind_result($signatureBlob);
    $stmtSig->fetch();
    // Convert BLOB to Base64 to display inline
    if ($signatureBlob) {
        $existingSignature = 'data:image/jpeg;base64,' . base64_encode($signatureBlob);
    }
}
$stmtSig->close();

// A. Get Login ID 
if (isset($_SESSION['user_id'])) {
    $loginID = $_SESSION['user_id'];
} else {
    $loginID = 'hazura'; // Fallback
} ?>
<!DOCTYPE html>
<html>

<head>
    <title>Signature Submission</title>
    <link rel="stylesheet" href="../../css/background.css?v=<?php echo time(); ?>">
    <!-- <link rel="stylesheet" href="../../../css/supervisor/dashboard.css"> -->
    <link rel="stylesheet" href="../../css/<?php echo $activeRole; ?>/SignatureSubmission.css?v=<?php echo time(); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat&family=Overlock" rel="stylesheet">
</head>

<body>

    <div id="mySidebar" class="sidebar">
        <button class="menu-icon" onclick="openNav()"><i class="bi bi-list"></i></button>

        <div id="sidebarLinks">
            <a href="javascript:void(0)" class="closebtn" id="close" onclick="closeNav()">
                Close <span class="x-symbol">x</span>
            </a>

            <span id="nameSide">HI, <?php echo strtoupper($loginID); ?></span>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'supervisor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('supervisorMenu', this)">
                <span class="role-text">Supervisor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="supervisorMenu" class="menu-items <?php echo ($activeRole == 'supervisor') ? 'expanded' : ''; ?>">
                <a href="dashboard.php?role=supervisor" id="dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-house-fill icon-padding"></i> Dashboard
                </a>
                <a href="../notification/notification.html" id="Notification"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="industry_collaboration.php?role=supervisor" id="industryCollaboration"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Industry Collaboration
                </a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=supervisor" id="evaluationForm"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
                <a href="report.php?role=supervisor" id="superviseesReport"
                    class="<?php echo ($activeRole == 'supervisor') ? : ''; ?>">
                    <i class="bi bi-bar-chart-fill icon-padding"></i> Supervisee's Report
                </a>
                <a href="logbook_submission.php?role=supervisor" id="logbookSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Logbook Submission
                </a>

                <a href="signature_submission.php?role=supervisor" id="signatureSubmission"
                    class="<?php echo ($activeRole == 'supervisor') ? 'active-menu-item active-page' : ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Signature Submission
                </a>

                <a href="project_title.php?role=supervisor" id="projectTitle"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>">
                    <i class="bi bi-calendar-check-fill icon-padding"></i> Project Title
                </a>
            </div>

            <a href="javascript:void(0)"
                class="role-header <?php echo ($activeRole == 'assessor') ? 'menu-expanded' : ''; ?>"
                onclick="toggleMenu('assessorMenu', this)">
                <span class="role-text">Assessor</span>
                <span class="arrow-container"><i class="bi bi-chevron-right arrow-icon"></i></span>
            </a>

            <div id="assessorMenu" class="menu-items <?php echo ($activeRole == 'assessor') ? 'expanded' : ''; ?>">
                <a href="../phpAssessor/dashboard.php?role=supervisor" id="Dashboard"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-house-fill icon-padding"></i>
                    Dashboard</a>
                <a href="../phpAssessor/notification.php?role=supervisor" id="Notification"
                    class="<?php echo ($activeRole == 'supervisor') ?: ''; ?>"><i
                        class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="../phpAssessor_Supervisor/evaluation_form.php?role=assessor" id="AssessorEvaluationForm"
                    class="<?php echo ($activeRole == 'assessor') ? : ''; ?>">
                    <i class="bi bi-file-earmark-text-fill icon-padding"></i> Evaluation Form
                </a>
            </div>

            <a href="../login.php" id="logout"><i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout</a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">
        <a href="../dashboard/dashboard.html">
            <img src="../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>
        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule"><?php echo $moduleTitle; ?></div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode"><?php echo $courseCode; ?></div>
                <div id="courseSession"><?php echo $courseSession; ?></div>
            </div>
        </div>
    </div>

    <div id="main">
        <div class="signature-container">
            <h4 class="section-title">Signature Submission</h4>

            <form id="signatureForm">
                <div class="upload-section">
                    <label class="upload-label" for="signatureFileInput">Signature</label>
                    <div class="file-upload-area" id="signatureUploadArea">
                        <input type="file" id="signatureFileInput" accept="image/*" style="display: none;">
                        <div class="upload-prompt">
                            <i class="bi bi-arrow-up-circle file-upload-icon"></i>
                            <p class="file-upload-text">Upload File Here</p>
                        </div>
                        <div class="file-preview" style="display: none;">
                            <img class="preview-image" alt="Signature preview">
                            <div class="preview-overlay">
                                <p class="preview-file-name"></p>
                                <button type="button" class="btn-change-file">Change File</button>
                            </div>
                        </div>
                    </div>
                    <p class="upload-instruction">Note: The file must be an image file (JPG, JPEG, PNG, or any other
                        image format).</p>
                </div>

                <div class="form-buttons">
                    <button type="button" id="cancelBtn" class="btn btn-light border">Cancel</button>
                    <button type="submit" id="saveBtn" class="btn btn-success">Save</button>
                </div>
            </form>
        </div>
    </div>

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
        var collapsedWidth = "60px";

        // 1. Toggle Menu (Accordion)
        function toggleMenu(menuId, headerElement) {
            const menu = document.getElementById(menuId);
            if (!menu) return;

            // Collapse all other menus
            document.querySelectorAll('.menu-items').forEach(m => {
                if (m !== menu) {
                    m.classList.remove('expanded');
                    // Find header associated with this menu to remove highlighting
                    const header = document.querySelector(`.role-header[onclick*="${m.id}"]`);
                    if (header) header.classList.remove('menu-expanded');
                }
            });

            // Toggle current menu
            menu.classList.toggle('expanded');
            headerElement.classList.toggle('menu-expanded');

            updateRoleHeaderHighlighting();
        }

        function updateRoleHeaderHighlighting() {
            document.querySelectorAll('.role-header').forEach(header => {
                const onclickAttr = header.getAttribute('onclick');
                if (!onclickAttr) return;

                // Extract menu ID from onclick attribute
                const match = onclickAttr.match(/toggleMenu\('(\w+)'/);
                if (!match) return;

                const menuId = match[1];
                const targetMenu = document.getElementById(menuId);
                if (!targetMenu) return;

                // Check if this menu contains the active page
                const hasActiveLink = targetMenu.querySelector('.active-menu-item') !== null;

                // Check if this menu is currently expanded
                const isExpanded = targetMenu.classList.contains('expanded');

                // Logic: Highlight role header ONLY when it contains active page BUT menu is collapsed
                if (hasActiveLink && !isExpanded) {
                    header.classList.add('active-role');
                } else {
                    header.classList.remove('active-role');
                }
            });
        }

        // 2. Open Sidebar
        function openNav() {
            document.getElementById("mySidebar").style.width = "250px";
            document.getElementById("main").style.marginLeft = "250px";
            document.getElementById("containerAtas").style.marginLeft = "250px";

            document.getElementById("nameSide").style.display = "block";
            document.getElementById("close").style.display = "block";
            document.getElementById("logout").style.display = "flex";

            const links = document.querySelectorAll("#sidebarLinks a");
            links.forEach(l => l.style.display = 'flex');

            document.querySelector(".menu-icon").style.display = "none";
        }

        // 3. Close Sidebar
        function closeNav() {
            document.getElementById("mySidebar").style.width = "60px";
            document.getElementById("main").style.marginLeft = "60px";
            document.getElementById("containerAtas").style.marginLeft = "60px";

            document.getElementById("nameSide").style.display = "none";
            document.getElementById("close").style.display = "none";
            document.getElementById("logout").style.display = "none";

            const links = document.querySelectorAll("#sidebarLinks a");
            links.forEach(l => l.style.display = 'none');

            document.querySelector(".menu-icon").style.display = "block";
        }


        var uploadSections = ['signature'];
        var uploadState = {
            signature: {
                uploadedFile: null,
                fileSaved: false,
                hasUnsavedChanges: false,
                savedPreview: null,
                savedFileName: ''
            }
        };
        var uploadDom = {};

        function setupUploadSection(section) {
            var area = document.getElementById(section + 'UploadArea');
            var input = document.getElementById(section + 'FileInput');
            var uploadPrompt = area.querySelector('.upload-prompt');
            var filePreview = area.querySelector('.file-preview');
            var previewImage = area.querySelector('.preview-image');
            var previewFileName = area.querySelector('.preview-file-name');
            var changeBtn = area.querySelector('.btn-change-file');

            uploadDom[section] = {
                area: area,
                input: input,
                uploadPrompt: uploadPrompt,
                filePreview: filePreview,
                previewImage: previewImage,
                previewFileName: previewFileName
            };

            area.addEventListener('click', function () {
                input.click();
            });

            area.addEventListener('dragover', function (e) {
                e.preventDefault();
                area.style.backgroundColor = '#e8e8e8';
            });

            area.addEventListener('dragleave', function () {
                area.style.backgroundColor = '#ffffff';
            });

            area.addEventListener('drop', function (e) {
                e.preventDefault();
                area.style.backgroundColor = '#ffffff';
                var files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    handleNewFile(section, files[0]);
                }
            });

            input.addEventListener('change', function (e) {
                if (e.target.files.length > 0) {
                    handleNewFile(section, e.target.files[0]);
                }
            });

            changeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                input.click();
            });
        }

        function handleNewFile(section, file) {
            if (!file) return;
            var state = uploadState[section];
            state.uploadedFile = file;
            state.hasUnsavedChanges = true;
            displayFilePreviewFromFile(section, file);
        }

        function displayFilePreviewFromFile(section, file) {
            var dom = uploadDom[section];
            if (!dom) return;

            dom.uploadPrompt.style.display = 'none';
            dom.filePreview.style.display = 'block';

            var reader = new FileReader();
            reader.onload = function (e) {
                dom.previewImage.src = e.target.result;
                dom.previewImage.style.display = 'block';
            };
            reader.readAsDataURL(file);

            dom.previewFileName.textContent = file.name;
        }

        function showSavedPreview(section) {
            var dom = uploadDom[section];
            var state = uploadState[section];
            if (!dom || !state.savedPreview) {
                resetSection(section, false);
                return;
            }

            dom.uploadPrompt.style.display = 'none';
            dom.filePreview.style.display = 'block';
            dom.previewImage.src = state.savedPreview;
            dom.previewImage.style.display = 'block';
            dom.previewFileName.textContent = state.savedFileName;
            dom.input.value = '';
            state.uploadedFile = null;
            state.hasUnsavedChanges = false;
        }

        function resetSection(section, preserveSaved) {
            var dom = uploadDom[section];
            if (!dom) return;
            var state = uploadState[section];

            dom.uploadPrompt.style.display = 'flex';
            dom.filePreview.style.display = 'none';
            dom.previewImage.src = '';
            dom.previewFileName.textContent = '';
            dom.input.value = '';

            state.uploadedFile = null;
            state.hasUnsavedChanges = false;

            if (!preserveSaved) {
                state.fileSaved = false;
                state.savedPreview = null;
                state.savedFileName = '';
            }
        }

        uploadSections.forEach(setupUploadSection);

        var uploadModal = document.getElementById('uploadSuccessModal');

        function openModal(modal) { modal.style.display = 'block'; }
        function closeModal(modal) { modal.style.display = 'none'; }

        document.getElementById('signatureForm').addEventListener('submit', function (e) {
            e.preventDefault();

            var signatureState = uploadState.signature;
            if (!signatureState.uploadedFile && !signatureState.fileSaved) {
                alert('Please upload the signature file.');
                return;
            }

            // If there's a new file to upload, send it to the server
            if (signatureState.uploadedFile) {
                var formData = new FormData();
                formData.append('signature_file', signatureState.uploadedFile);

                var saveBtn = document.getElementById('saveBtn');
                saveBtn.disabled = true;
                saveBtn.textContent = 'Uploading...';

                fetch('../../save_signature.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(async r => {
                        const raw = await r.text();
                        let data = {};
                        try {
                            data = raw ? JSON.parse(raw) : {};
                        } catch (e) {
                            throw new Error('Invalid JSON: ' + raw.substring(0, 300));
                        }
                        if (!r.ok || !data.success) {
                            throw new Error(data.error || ('HTTP ' + r.status));
                        }
                        return data;
                    })
                    .then(() => {
                        openModal(uploadModal);
                        signatureState.fileSaved = true;
                        // Reload the signature from server to show the saved version
                        loadExistingSignature();
                    })
                    .catch(err => {
                        alert('Upload error: ' + err.message);
                    })
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Save';
                    });
            } else {
                // File already saved, just show success
                openModal(uploadModal);
            }
        });

        document.getElementById('closeUploadModal').onclick = function () {
            closeModal(uploadModal);
            uploadSections.forEach(function (section) {
                storeSavedState(section);
            });
        };

        document.getElementById('okUploadBtn').onclick = function () {
            closeModal(uploadModal);
            uploadSections.forEach(function (section) {
                storeSavedState(section);
            });
        };

        document.getElementById('cancelBtn').addEventListener('click', function () {
            uploadSections.forEach(function (section) {
                var state = uploadState[section];
                if (state.hasUnsavedChanges) {
                    if (state.fileSaved && state.savedPreview) {
                        state.uploadedFile = null;
                        state.hasUnsavedChanges = false;
                        showSavedPreview(section);
                    } else {
                        resetSection(section, false);
                    }
                }
            });
        });

        function storeSavedState(section) {
            var state = uploadState[section];
            var dom = uploadDom[section];
            if (!state.uploadedFile || !dom) return;

            state.savedPreview = dom.previewImage.src;
            state.savedFileName = state.uploadedFile.name;
            state.fileSaved = true;
            state.hasUnsavedChanges = false;
            state.uploadedFile = null;
            dom.input.value = '';
        }

        function loadExistingSignature() {
            fetch('get_signature.php')
                .then(r => {
                    if (!r.ok) throw new Error('No signature');
                    return r.blob();
                })
                .then(blob => {
                    var url = URL.createObjectURL(blob);
                    var type = blob.type || '';
                    var dom = uploadDom.signature;
                    var state = uploadState.signature;

                    dom.uploadPrompt.style.display = 'none';
                    dom.filePreview.style.display = 'block';

                    if (type.startsWith('image/')) {
                        dom.previewImage.src = url;
                        dom.previewImage.style.display = 'block';
                        dom.previewFileName.textContent = 'Existing signature (' + type + ')';
                    } else {
                        dom.previewImage.style.display = 'none';
                        dom.previewFileName.innerHTML = 'Existing file (' + type + ') - <a href="' + url + '" target="_blank">Open/Download</a>';
                    }

                    state.fileSaved = true;
                    state.savedPreview = url;
                    state.savedFileName = 'Existing signature';
                })
                .catch(() => {
                    // No existing signature; keep prompt
                });
        }

        window.onload = function () {
            closeNav();
            initializeRoleToggle();
            loadExistingSignature();
        };


    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>