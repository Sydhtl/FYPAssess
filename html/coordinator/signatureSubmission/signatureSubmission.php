<?php include '../../../php/coordinator_bootstrap.php'; ?>
<?php
// Derive base course code (strip trailing section letter if present)
$baseCourseCode = '';
if (!empty($displayCourseCode)) {
    $baseCourseCode = preg_replace('/[-_ ]?[A-Za-z]$/', '', $displayCourseCode);
}
?>
<script>
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
<!DOCTYPE html>
<html>
<head>
    <title>FYPAssess - Stamp Submission</title>
    <link rel="stylesheet" href="../../../css/background.css">
    <link rel="stylesheet" href="../../../css/coordinator/dashboard.css">
    <link rel="stylesheet" href="../../../css/coordinator/signatureSubmission.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="coordinator-signature-page">

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
                <a href="#" id="Notification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="#" id="industryCollaboration"><i class="bi bi-file-earmark-text-fill icon-padding"></i> Industry Collaboration</a>
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
                <a href="../dashboard/dashboardCoordinator.php" id="coordinatorDashboard"><i class="bi bi-house-fill icon-padding"></i> Dashboard</a>
                <a href="../studentAssignation/studentAssignation.php" id="studentAssignation"><i class="bi bi-people-fill icon-padding"></i> Student Assignation</a>
                <a href="../learningObjective/learningObjective.php" id="learningObjective"><i class="bi bi-book-fill icon-padding"></i> Learning Objective</a>
                <a href="../markSubmission/markSubmission.php" id="markSubmission"><i class="bi bi-clipboard-check-fill icon-padding"></i> Progress Submission</a>
                <a href="../notification/notification.php" id="coordinatorNotification"><i class="bi bi-bell-fill icon-padding"></i> Notification</a>
                <a href="./signatureSubmission.php" id="signatureSubmission" class="active-menu-item"><i class="bi bi-pen-fill icon-padding"></i> Signature Submission</a>
                <a href="../dateTimeAllocation/dateTimeAllocation.php" id="dateTimeAllocation"><i class="bi bi-calendar-event-fill icon-padding"></i> Date and Time Allocation</a>
            </div>

            <a href="../../logout.php" id="logout">
                <i class="bi bi-box-arrow-left" style="padding-right: 10px;"></i> Logout
            </a>
        </div>
    </div>

    <div id="containerAtas" class="containerAtas">
        <a href="../dashboard/dashboardCoordinator.php">
            <img src="../../../assets/UPMLogo.png" alt="UPM logo" width="100px" id="upm-logo">
        </a>

        <div class="header-text-group">
            <div id="module-titles">
                <div id="containerModule">Coordinator Module</div>
                <div id="containerFYPAssess">FYPAssess</div>
            </div>
            <div id="course-session">
                <div id="courseCode"><?php echo htmlspecialchars($baseCourseCode ?: $displayCourseCode); ?></div>
                <div id="courseSession"><?php echo htmlspecialchars($selectedYear . ' - ' . $selectedSemester); ?></div>
            </div>
        </div>
    </div>

    <div id="main">
        <div class="signature-container">
            <h4 class="section-title">Stamp Submission</h4>

            <form id="signatureForm">
                <div class="upload-section">
                    <label class="upload-label" for="stampFileInput">Stamp</label>
                    <div class="file-upload-area" id="stampUploadArea">
                        <input type="file" id="stampFileInput" accept="image/*" style="display: none;">
                        <div class="upload-prompt">
                            <i class="bi bi-arrow-up-circle file-upload-icon"></i>
                            <p class="file-upload-text">Upload File Here</p>
                        </div>
                        <div class="file-preview" style="display: none;">
                            <img class="preview-image" alt="Stamp preview">
                            <div class="preview-overlay">
                                <p class="preview-file-name"></p>
                                <button type="button" class="btn-change-file">Change File</button>
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

    <div id="uploadSuccessModal" class="custom-modal">
        <div class="modal-dialog">
            <div class="modal-content-custom">
                <span class="close-btn" id="closeUploadModal">&times;</span>
                <div class="modal-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="modal-title-custom">Upload Successful!</div>
                <div class="modal-message">Your stamp has been uploaded successfully.</div>
                <div style="display:flex; justify-content:center;">
                    <button id="okUploadBtn" class="btn btn-success" type="button">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var collapsedWidth = "60px";
        const expandedWidth = "220px";

        function openNav() {
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main");
            var menuIcon = document.querySelector(".menu-icon");

            sidebar.style.width = expandedWidth;

            if (mainContent) mainContent.style.marginLeft = expandedWidth;
            if (header) header.style.marginLeft = expandedWidth;

            document.getElementById("nameSide").style.display = "block";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                if (links[i].classList.contains('role-header') || links[i].id === 'logout') {
                    links[i].style.display = 'flex';
                } else if (links[i].id === 'close') {
                    links[i].style.display = 'flex';
                }
            }

            document.querySelectorAll('.menu-items.expanded a').forEach(function(a) {
                a.style.display = 'block';
            });

            if (menuIcon) menuIcon.style.display = "none";
        }

        function closeNav() {
            var sidebar = document.getElementById("mySidebar");
            var header = document.getElementById("containerAtas");
            var mainContent = document.getElementById("main");
            var menuIcon = document.querySelector(".menu-icon");

            sidebar.style.width = collapsedWidth;

            if (mainContent) mainContent.style.marginLeft = collapsedWidth;
            if (header) header.style.marginLeft = collapsedWidth;

            document.getElementById("nameSide").style.display = "none";

            var links = document.getElementById("sidebarLinks").getElementsByTagName("a");
            for (var i = 0; i < links.length; i++) {
                links[i].style.display = "none";
            }

            if (menuIcon) menuIcon.style.display = "block";
        }

        var uploadSections = ['stamp'];
        var uploadState = {
            stamp: {
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

            area.addEventListener('click', function() {
                input.click();
            });

            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                area.style.backgroundColor = '#e8e8e8';
            });

            area.addEventListener('dragleave', function() {
                area.style.backgroundColor = '#ffffff';
            });

            area.addEventListener('drop', function(e) {
                e.preventDefault();
                area.style.backgroundColor = '#ffffff';
                var files = e.dataTransfer.files;
                if (files.length > 0) {
                    input.files = files;
                    handleNewFile(section, files[0]);
                }
            });

            input.addEventListener('change', function(e) {
                if (e.target.files.length > 0) {
                    handleNewFile(section, e.target.files[0]);
                }
            });

            changeBtn.addEventListener('click', function(e) {
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
            reader.onload = function(e) {
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

        document.getElementById('signatureForm').addEventListener('submit', function(e) {
            e.preventDefault();

            var stampState = uploadState.stamp;
            if (!stampState.uploadedFile && !stampState.fileSaved) {
                alert('Please upload the stamp file.');
                return;
            }

            // If there's a new file to upload, send it to the server
            if (stampState.uploadedFile) {
                var formData = new FormData();
                formData.append('stamp_file', stampState.uploadedFile);

                var saveBtn = document.getElementById('saveBtn');
                saveBtn.disabled = true;
                saveBtn.textContent = 'Uploading...';

                fetch('../../../php/phpCoordinator/save_stamp.php', {
                    method: 'POST',
                    body: formData
                })
                .then(async response => {
                    const raw = await response.text();
                    let data = {};
                    try {
                        data = raw ? JSON.parse(raw) : {};
                    } catch(e) {
                        throw new Error('Invalid JSON: ' + raw.substring(0, 300));
                    }
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || ('HTTP ' + response.status));
                    }
                    return data;
                })
                .then(() => {
                    openModal(uploadModal);
                    storeSavedState('stamp');
                })
                .catch(err => {
                    alert('Upload error: ' + err.message);
                })
                .finally(() => {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                });
            } else {
                // No new file, just show success modal
                openModal(uploadModal);
            }
        });

        document.getElementById('closeUploadModal').onclick = function() {
            closeModal(uploadModal);
            uploadSections.forEach(function(section) {
                storeSavedState(section);
            });
        };

        document.getElementById('okUploadBtn').onclick = function() {
            closeModal(uploadModal);
            uploadSections.forEach(function(section) {
                storeSavedState(section);
            });
        };

        document.getElementById('cancelBtn').addEventListener('click', function() {
            uploadSections.forEach(function(section) {
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

        // Initialize role toggle functionality
        function initializeRoleToggle() {
            const allRoleHeaders = document.querySelectorAll('.role-header');
            allRoleHeaders.forEach(header => {
                if (header.getAttribute('data-role') === 'coordinator') {
                    header.classList.add('active-role');
                    header.classList.add('menu-expanded');
                } else {
                    header.classList.remove('active-role');
                    header.classList.remove('menu-expanded');
                }
            });

            const roleHeaders = document.querySelectorAll('.role-header');
            roleHeaders.forEach(header => {
                header.addEventListener('click', function(e) {
                    e.preventDefault();
                    const role = this.getAttribute('data-role');
                    const menuId = `${role}Menu`;
                    const menu = document.getElementById(menuId);

                    if (!menu) return;

                    const isExpanded = menu.classList.contains('expanded');
                    const arrow = this.querySelector('.arrow-icon');

                    // Collapse all other menus and reset their arrows
                    document.querySelectorAll('.menu-items').forEach(otherMenu => {
                        if (otherMenu !== menu) {
                            otherMenu.classList.remove('expanded');
                            const otherHeader = document.querySelector(`.role-header[data-role="${otherMenu.id.replace('Menu', '')}"]`);
                            if (otherHeader) {
                                const otherArrow = otherHeader.querySelector('.arrow-icon');
                                if (otherArrow) {
                                    otherArrow.classList.remove('bi-chevron-down');
                                    otherArrow.classList.add('bi-chevron-right');
                                }
                            }
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
                        }
                    }

                    // Remove active-role from all non-coordinator roles (they shouldn't be highlighted on coordinator pages)
                    document.querySelectorAll('.role-header').forEach(h => {
                        const roleType = h.getAttribute('data-role');
                        // Only keep active-role for coordinator on coordinator pages
                        if (roleType !== 'coordinator') {
                            h.classList.remove('active-role');
                            h.classList.remove('menu-expanded');
                        }
                    });

                    // Toggle current menu
                    if (isExpanded) {
                        menu.classList.remove('expanded');
                        this.classList.remove('menu-expanded');
                        if (arrow) {
                            arrow.classList.remove('bi-chevron-down');
                            arrow.classList.add('bi-chevron-right');
                        }
                    } else {
                        menu.classList.add('expanded');
                        if (role === 'coordinator') {
                            this.classList.add('menu-expanded');
                        }
                        if (arrow) {
                            arrow.classList.remove('bi-chevron-right');
                            arrow.classList.add('bi-chevron-down');
                        }
                    }

                    // IMPORTANT: After toggling other roles, ensure coordinator header state is maintained
                    // This ensures coordinator stays white when its menu is collapsed, even when other roles are clicked
                    if (coordinatorHeader && coordinatorMenu && role !== 'coordinator') {
                        coordinatorHeader.classList.add('active-role');
                        if (!coordinatorMenu.classList.contains('expanded')) {
                            coordinatorHeader.classList.remove('menu-expanded');
                        } else {
                            coordinatorHeader.classList.add('menu-expanded');
                        }
                    }

                    // Show/hide child links for the current menu (only when sidebar is expanded)
                    const sidebar = document.getElementById("mySidebar");
                    const isSidebarExpanded = sidebar.style.width === expandedWidth;

                    menu.querySelectorAll('a').forEach(a => {
                        if (isSidebarExpanded) {
                            a.style.display = menu.classList.contains('expanded') ? 'block' : 'none';
                        } else {
                            a.style.display = 'none';
                        }
                    });
                });
            });
        }

        function loadExistingStamp() {
            fetch('../../../php/phpCoordinator/get_stamp.php')
                .then(response => {
                    if (response.ok) {
                        return response.blob();
                    } else if (response.status === 404) {
                        // No stamp exists yet, that's fine
                        return null;
                    } else {
                        throw new Error('Failed to load stamp');
                    }
                })
                .then(blob => {
                    if (blob) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            var state = uploadState.stamp;
                            state.savedPreview = e.target.result;
                            state.savedFileName = 'stamp';
                            state.fileSaved = true;
                            showSavedPreview('stamp');
                        };
                        reader.readAsDataURL(blob);
                    }
                })
                .catch(err => {
                    // Silently fail if no stamp exists
                    console.log('No existing stamp found');
                });
        }

        window.onload = function() {
            closeNav();
            initializeRoleToggle();
            loadExistingStamp();
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


