<?php
// Prevent caching to stop back button access
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

include '../../php/mysqlConnect.php';
session_start();

// 1. FETCH SAVED SESSION DATA (Use empty string if not set)
// We use the null coalescing operator (??) for cleaner code
$student_programme     = $_SESSION['temp_programme'] ?? '';
$student_course   = $_SESSION['temp_courseCode'] ?? ''; // Note: standardized to courseCode
$student_sem      = $_SESSION['temp_semester'] ?? '';
$student_session     = $_SESSION['temp_session'] ?? '';
$student_phone    = $_SESSION['temp_phone'] ?? '';
$student_race     = $_SESSION['temp_race'] ?? '';
$student_address  = $_SESSION['temp_address'] ?? '';
$student_minor    = $_SESSION['temp_minor'] ?? '';
$student_cgpa     = $_SESSION['temp_cgpa'] ?? '';
$student_supervisor  = $_SESSION['temp_supervisor'] ?? '';
$student_title1   = $_SESSION['temp_title1'] ?? '';
$student_title2   = $_SESSION['temp_title2'] ?? '';
$student_title3   = $_SESSION['temp_title3'] ?? '';
$existingStudent  = $_SESSION['existing_student'] ?? false;
$previousSupervisorId = null;
$previousAssessor1 = null;
$previousAssessor2 = null;
$lockedProjectTitle = '';
$lockedTitleStatus = '';
$titleLabel = 'Suggestion FYP Title';
$previousSessionEnrollments = [];

// If existing student, fetch latest enrollment and project title
if ($existingStudent && isset($_SESSION['signup_upmId'])) {
    $currentUpmId = $_SESSION['signup_upmId'];

    // Latest enrollment (by highest FYP_Session_ID)
    $enrollSql = "
        SELECT se.Supervisor_ID, se.Assessor_ID_1, se.Assessor_ID_2
        FROM student_enrollment se
        JOIN fyp_session fs ON se.Fyp_Session_ID = fs.FYP_Session_ID
        WHERE se.Student_ID = ?
        ORDER BY fs.FYP_Session_ID DESC
        LIMIT 1
    ";
    if ($stmtEnroll = $conn->prepare($enrollSql)) {
        $stmtEnroll->bind_param('s', $currentUpmId);
        if ($stmtEnroll->execute()) {
            $resEnroll = $stmtEnroll->get_result();
            if ($rowEnroll = $resEnroll->fetch_assoc()) {
                $previousSupervisorId = $rowEnroll['Supervisor_ID'];
                $previousAssessor1 = $rowEnroll['Assessor_ID_1'];
                $previousAssessor2 = $rowEnroll['Assessor_ID_2'];
                $_SESSION['existing_assessor1'] = $previousAssessor1;
                $_SESSION['existing_assessor2'] = $previousAssessor2;
            }
        }
        $stmtEnroll->close();
    }

    // Latest project title
    $projectSql = "
        SELECT Project_Title, Proposed_Title, Title_Status
        FROM fyp_project
        WHERE Student_ID = ?
        ORDER BY Project_ID DESC
        LIMIT 1
    ";
    if ($stmtProj = $conn->prepare($projectSql)) {
        $stmtProj->bind_param('s', $currentUpmId);
        if ($stmtProj->execute()) {
            $resProj = $stmtProj->get_result();
            if ($rowProj = $resProj->fetch_assoc()) {
                // Prefer the approved/official Project_Title for existing students
                $lockedProjectTitle = $rowProj['Project_Title'] ?: $rowProj['Proposed_Title'];
                $lockedTitleStatus = $rowProj['Title_Status'];
                $student_title1 = $lockedProjectTitle; // prefill
            }
        }
        $stmtProj->close();
    }

    // Adjust label for existing students
    $titleLabel = 'FYP Title';

    // All prior course/semester/session combinations for duplicate check
    $sessionSql = "
        SELECT fs.Course_ID, fs.Semester, fs.FYP_Session
        FROM student_enrollment se
        JOIN fyp_session fs ON se.Fyp_Session_ID = fs.FYP_Session_ID
        WHERE se.Student_ID = ?
    ";
    if ($stmtSession = $conn->prepare($sessionSql)) {
        $stmtSession->bind_param('s', $currentUpmId);
        if ($stmtSession->execute()) {
            $resSession = $stmtSession->get_result();
            while ($rowSession = $resSession->fetch_assoc()) {
                $previousSessionEnrollments[] = $rowSession;
            }
        }
        $stmtSession->close();
    }
}
$isOther = !empty($student_race) && $student_race != 'Malay' && $student_race != 'Chinese' && $student_race != 'Indian';
// 2. FETCH DEPARTMENTS (For Programme Dropdown)
$deptQuery = "SELECT * FROM department";
$deptResult = $conn->query($deptQuery);

// 3. FETCH COURSES (To pass to JavaScript)
$courseData = [];
$courseQuery = "SELECT * FROM course";
$result = $conn->query($courseQuery);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()){
        $courseData[] = $row;
    }
}

// 4. FETCH LECTURERS (Grouped by Department_ID)
// 4. FETCH LECTURERS (Grouped by Department_ID)
$lecturerData = [];
$lecturerQuery = "SELECT l.Lecturer_ID, l.Department_ID, l.Lecturer_Name, s.Supervisor_ID 
                  FROM lecturer l
                  JOIN supervisor s ON l.Lecturer_ID = s.Lecturer_ID
                  ORDER BY l.Department_ID, l.Lecturer_Name";
$lecturerResult = $conn->query($lecturerQuery);
if ($lecturerResult->num_rows > 0) {
    while($row = $lecturerResult->fetch_assoc()){
        $lecturerData[] = $row;
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYPAssess - Additional Information</title>
    <link rel="stylesheet" href="../../css/background.css">
    <link rel="stylesheet" href="../../css/login.css">
    <style>
        body{ background-color:#780000; }
        .disabled-link { pointer-events: none; opacity: 0.6; cursor: not-allowed; }
        
        /* Prevent layout shifts when switching between containers */
        #courseInfo, #Studinfo {
            transition: none !important;
        }
    </style>
</head>
<body>

<div class="page">
    <div class="left">
        <div class="container1">
            <img src="../../assets/UPM Logoo.jpg" alt="UPM" width="300px" height="200px"> <br>
            <b class="title">FYPAssess</b>
            <P class="fulltitle">Final Year Project Assessment System</P>
            <div class="description">Student and Lecturers FYP Portal</div>
        </div>
    </div>
    
        <div class="right"> 
  
            <form action="../../php/phpStudent/save_information.php" method="post">
            <input type="hidden" name="upmId" value="<?php echo $_SESSION['signup_upmId'] ?? ($_SESSION['upmId'] ?? ''); ?>">
            <input type="hidden" name="existingStudent" value="<?php echo $existingStudent ? '1' : '0'; ?>">

            <?php if (isset($_SESSION['error'])): ?>
                <div style="background-color: #ffebee; color: #c62828; padding: 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid #ef5350;">
                    <strong>Error:</strong> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="container2" id="courseInfo">
                <p id="welcomeBack">FYP Session Information</p>
                <p id="desc">Please complete your profile</p>

                <p id="UsernamePassword">Programme Name <span class="info-tooltip">ⓘ<span class="tooltip-text">Must select a programme.</span></span></p>
                <select class="input-field" id="programme" name="programme" required>
                    <option value="" disabled selected>Select your programme</option>
                    <?php
                    if ($deptResult->num_rows > 0) {
                        while($row = $deptResult->fetch_assoc()){
                            // Check if this option was saved in session
                            $sel = ($student_programme == $row['Department_ID']) ? 'selected' : '';
                            echo "<option value='".$row['Department_ID']."' $sel>".$row['Department_Name']."</option>";
                        }
                    }
                    ?>
                </select>
                
                <p id="UsernamePassword">Course Code <span class="info-tooltip">ⓘ<span class="tooltip-text">Must contain letters and numbers.</span></span></p>
                <select class="input-field" id="coursecode" name="courseCode" required>
                    <option value="" disabled selected>Select current course code</option>
                  <?php
                    $courseQuery = "SELECT * FROM course";
                    $result = $conn->query($courseQuery);

                    while($row = $result->fetch_assoc()){
                        $isSelected = "";

                        // CORRECT PLACE: Check inside the loop for every single row
                        if (isset($_SESSION['temp_coursecode']) && $_SESSION['temp_coursecode'] == $row['Course_ID']) {
                            $isSelected = "selected";
                        }

                        echo "<option value='".$row['Course_ID']."' $isSelected>".$row['Course_Code']."</option>";
                    }
                    ?>
                    </select>

                <p id="UsernamePassword">Semester <span class="info-tooltip">ⓘ<span class="tooltip-text">Choose correct semester.</span></span></p>
                <select class="input-field" id="semester" name="semester" required>
                    <option value="" disabled selected>Select your semester</option>
                    <option value="1" <?php if($student_sem == '1') echo "selected"; ?>>1</option> 
                    <option value="2" <?php if($student_sem == '2') echo "selected"; ?>>2</option>
                </select>

                <p id="UsernamePassword">Session <span class="info-tooltip">ⓘ<span class="tooltip-text">Format: YYYY/YYYY.</span></span></p>
                <input type="text" id="session" name="session" placeholder="Session" value="<?php echo $student_session; ?>" required>

                <p id="duplicateWarning" style="display:none;color:#c62828;font-size:12px;margin-top:8px;">You are already registered for this course, semester, and session. Please choose a different combination.</p>

                <a href="signup.php" id="previous" style="text-decoration: none; display: inline-block; margin-right: 20px;">Previous</a>
                <a href="#Studinfo" id="continue1" class="disabled-link" style="text-decoration:none; display: inline-block;">Continue</a>    
            </div>

            <div class="container2" id="Studinfo" style="display: none;">
                <p id="welcomeBack">Student Information</p>
                <p id="desc">Enter the details</p>

                <p id="UsernamePassword">Phone Number <span class="info-tooltip">ⓘ<span class="tooltip-text">10-15 digits only.</span></span></p>
                <input type="text" id="phoneNumber" name="phone" placeholder="Enter phone number" value="<?php echo $student_phone; ?>" required>
             
                <p id="UsernamePassword">Race <span class="info-tooltip">ⓘ<span class="tooltip-text">Choose correct race.</span></span></p>
                <select class="input-field" id="race" name="race" required>
                    <option value="" disabled selected>Select your race</option>
                    <option value="Malay" <?php if($student_race == 'Malay') echo 'selected'; ?>>Malay</option>
                    <option value="Chinese" <?php if($student_race == 'Chinese') echo 'selected'; ?>>Chinese</option>
                    <option value="Indian" <?php if($student_race == 'Indian') echo 'selected'; ?>>Indian</option>
                    <option value="Others" >Others</option>
                </select>
              <input type="text" id="otherRace"  name="otherRace"  placeholder="Please specify your race"  style="display: <?php echo $isOther ? 'block' : 'none'; ?>; margin-top:5px;"  value="<?php if($isOther) echo $student_race; ?>"
>
                <p id="UsernamePassword">Current Address <span class="info-tooltip">ⓘ<span class="tooltip-text">Must not be empty.</span></span></p>
                <textarea id="currentAddress" name="address" placeholder="Enter full address" class="input-field" style="height: 100px; resize: none;" required><?php echo $student_address; ?></textarea>
                
                <p id="UsernamePassword">Minor <span class="info-tooltip">ⓘ<span class="tooltip-text">(Optional).</span></span></p>
                <input type="text" id="minor" name="minor" placeholder="Enter your minor" value="<?php echo $student_minor; ?>">
                  
                <p id="UsernamePassword">CGPA <span class="info-tooltip">ⓘ<span class="tooltip-text">0.000 to 4.000.</span></span></p>
                <input type="text" id="cgpa" name="cgpa" placeholder="CGPA" value="<?php echo $student_cgpa; ?>" required>
            
                <a href="#courseInfo" id="previous" style="text-decoration: none; display: inline-block; margin-right: 20px;">Previous</a>
                <button type="submit" id="submitButton" style="display: inline-block;">Submit</button>
            </div>
        </form>
    </div>
</div>

<p class="faculty">FACULTY OF COMPUTER SCIENCE AND INFORMATION TECHNOLOGY</p>

<div id="submissionSuccessModal" class="custom-modal">
    <div class="modal-dialog">
        <div class="modal-content-custom">
            <span class="close-btn">&times;</span>
            <div class="modal-icon">✅</div>
            <h3 class="modal-title-custom">Submission Successful!</h3>
            <p class="modal-message">Your suggested FYP titles are now waiting for supervisor approval.</p>
            <button id="modalLoginRedirect" class="modal-button">Go to Login Page</button>
        </div>
    </div>
</div>

<style>
.custom-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
}
.modal-dialog {
    position: relative;
    width: 500px;
    max-width: 90%;
    margin: 0 auto;
}
.modal-content-custom {
    background-color: #fff;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}
.modal-icon {
    font-size: 48px;
    margin-bottom: 20px;
}
.modal-title-custom {
    color: #333;
    margin-bottom: 15px;
    font-size: 24px;
}
.modal-message {
    color: #666;
    margin-bottom: 25px;
    font-size: 16px;
}
.modal-button {
    background-color: #780000;
    color: white;
    border: none;
    padding: 10px 30px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
}
.modal-button:hover {
    background-color: #5a0000;
}
.close-btn {
    position: absolute;
    right: 15px;
    top: 10px;
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
}
.close-btn:hover {
    color: #000;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // --- 0. DATA INJECTION FROM PHP TO JS ---
    const allCourses = <?php echo json_encode($courseData); ?>;
    const allLecturers = <?php echo json_encode($lecturerData); ?>;
    const savedCourseID = "<?php echo $student_course; ?>";
    const savedSupervisorID = "<?php echo ($previousSupervisorId ?? $student_supervisor); ?>";
    const existingStudent = <?php echo $existingStudent ? 'true' : 'false'; ?>;
    const previousEnrollments = <?php echo json_encode($previousSessionEnrollments); ?>;

    // --- 1. CONFIGURATION ---
    const courseInfo = {
        fields: {
            programme: document.querySelector('#programme'),
            coursecode: document.querySelector('#coursecode'),
            semester: document.querySelector('#semester'),
            session: document.querySelector('#session')
        },
        continue: document.querySelector('#courseInfo #continue1')
    };

    const studentInfo = {
        fields: {
            phoneNumber: document.querySelector('#phoneNumber'),
            race: document.querySelector('#race'),
            otherRace: document.querySelector('#otherRace'),
            currentAddress: document.querySelector('#currentAddress'),
            cgpa: document.querySelector('#cgpa'),
            minor: document.querySelector('#minor')
        },
        submit: document.querySelector('#submitButton')
    };



    // --- 2. DYNAMIC LOGIC (Course & Supervisor) ---

    // A. Course Filter Logic
    function updateCourseOptions(deptID) {
        const select = courseInfo.fields.coursecode;
        select.innerHTML = '<option value="" disabled selected>Select current course code</option>';
        
        // Ensure allCourses is valid before filtering
        if (!allCourses) return;

        const filtered = allCourses.filter(c => c.Department_ID == deptID);
        
        filtered.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.Course_ID; 
            opt.text = c.Course_Code ;
            if (savedCourseID && c.Course_ID == savedCourseID) opt.selected = true;
            select.add(opt);
        });
        
        // Re-validate
        const isValid = validateFields(courseInfo);
        updateButton(courseInfo.continue, isValid);
    }



    // C. Listeners for Programme Change
    if (courseInfo.fields.programme) {
        courseInfo.fields.programme.addEventListener('change', function() {
            updateCourseOptions(this.value);
        });
        
        // Run on Load
        if (courseInfo.fields.programme.value) {
            updateCourseOptions(courseInfo.fields.programme.value);
        }
    }

    // --- 3. VALIDATION HELPERS ---
    function isStringValid(str) { return str && str.trim().length > 0; }
    function isPhoneNumberValid(phone) { return /^\d{10,15}$/.test(phone); }
    function isSessionValid(sess) { return /^(\d{4}\/\d{4}|\d{2}\/\d{2})$/.test(sess); }
    function isCGPA(str) { return /^([0-3](\.\d{1,3})?|4(\.0{1,3})?)$/.test(str); }

    function isDuplicateSelection() {
        if (!existingStudent || !previousEnrollments || previousEnrollments.length === 0) return false;
        const courseVal = courseInfo.fields.coursecode.value;
        const semesterVal = courseInfo.fields.semester.value;
        const sessionVal = courseInfo.fields.session.value.trim();
        if (!courseVal || !semesterVal || !sessionVal) return false;
        return previousEnrollments.some(enrollment =>
            String(enrollment.Course_ID) === String(courseVal) &&
            String(enrollment.Semester) === String(semesterVal) &&
            String(enrollment.FYP_Session).trim() === sessionVal
        );
    }

    function renderDuplicateWarning(isDup) {
        const warning = document.getElementById('duplicateWarning');
        if (!warning) return;
        warning.style.display = isDup ? 'block' : 'none';
    }

    function validateFields(sectionObject) {
        const fields = sectionObject.fields;

        if (sectionObject === courseInfo) {
            const baseValid = isStringValid(fields.programme.value) &&
                             isStringValid(fields.coursecode.value) &&
                             isStringValid(fields.semester.value) &&
                             isSessionValid(fields.session.value);
            const duplicate = isDuplicateSelection();
            renderDuplicateWarning(duplicate);
            return baseValid && !duplicate;
        }
        if (sectionObject === studentInfo) {
            const isRaceValid = isStringValid(fields.race.value) && 
                               (fields.race.value !== 'Others' || isStringValid(fields.otherRace.value));
            return isPhoneNumberValid(fields.phoneNumber.value) &&
                   isRaceValid &&
                   isStringValid(fields.currentAddress.value) &&
                   isCGPA(fields.cgpa.value);
        }
        return false;
    }

    function updateButton(button, isValid) {
        isValid ? button.classList.remove('disabled-link') : button.classList.add('disabled-link');
    }
    function updateSubmitButton(button, isValid) {
        button.disabled = !isValid;
    }

    // --- 4. GENERIC EVENT LISTENERS ---
    [courseInfo, studentInfo].forEach(section => {
        Object.values(section.fields).forEach(field => {
            if (!field) return;
            const eventType = field.tagName === 'SELECT' ? 'change' : 'input';
            field.addEventListener(eventType, () => {
                const isValid = validateFields(section);
                if (section.submit) updateSubmitButton(section.submit, isValid);
                else updateButton(section.continue, isValid);
            });
        });
    });

    // Race "Others" Toggle
    if (studentInfo.fields.race) {
        studentInfo.fields.race.addEventListener('change', function() {
            const otherInput = studentInfo.fields.otherRace;
            if (this.value === 'Others') {
                otherInput.style.display = 'block';
                otherInput.required = true;
            } else {
                otherInput.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
            updateSubmitButton(studentInfo.submit, validateFields(studentInfo));
        });
    }

    // --- 5. NAVIGATION ---
    const links = document.querySelectorAll('#continue1, #previous');
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.classList.contains('disabled-link')) { e.preventDefault(); return; }

            if (this.closest('#courseInfo') && isDuplicateSelection()) {
                e.preventDefault();
                renderDuplicateWarning(true);
                return;
            }
            
            const href = this.getAttribute('href');
            if (href && href.startsWith('#')) {
                e.preventDefault();
                const targetId = href.substring(1);
                const current = document.querySelector('.container2:not([style*="display: none"])');
                const target = document.getElementById(targetId);
                
                if (target && current) {
                    current.style.display = 'none';
                    target.style.display = 'block';
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    });

    // --- 6. SUBMIT BUTTON ---
    const submitBtn = document.querySelector('#submitButton');
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            if (this.disabled) { e.preventDefault(); return; }
            // Valid form: Let PHP handle the submission.
        });
    }
    
    // Initial Validation
    updateButton(courseInfo.continue, validateFields(courseInfo));
    updateSubmitButton(submitBtn, validateFields(studentInfo));
});
</script>
</body>
</html>