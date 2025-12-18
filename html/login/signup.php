<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYPAssess - Sign Up</title>
    <link rel="stylesheet" href="../../css/background.css">
    <link rel="stylesheet" href="../../css/login.css">
</head>
<style>
    body{
    background-color:#780000;
    }
</style>
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
        <div class="container2" id="signup">
            <form action="../../php/signup.php" method="post">
            <p id="welcomeBack">Create Account</p>

            <p id="UsernamePassword">Full Name</p>
            <input type="text" name="fullName" placeholder="Enter your full name" required />
            
            <p id="UsernamePassword">Staff ID or Student ID</p>
            <input type="text" name="upmId" placeholder="Enter Staff or Student ID" required />
            
            
           <div class="input-group">
            <i class="fas fa-lock input-icon"></i>
            <p id="UsernamePassword">Password</p> 
            <input type="password" name="password" placeholder="Enter your password" id="password" required onkeyup="checkPasswordStrength(this.value)" > 
            <i class="fas fa-eye password-toggle"></i>
                        <label for="password" class="label-placeholder">Password Strength</label>
                        <!-- Info icon with tooltip showing password requirements -->
                        <span class="info-tooltip" aria-hidden="true">
                                ⓘ
                                <span class="tooltip-text">Password requirements: at least 8 characters, include uppercase and lowercase letters, a number and a special character.</span>
                        </span>
              </div>

           <div id="strength-indicator-container">
            <div id="strength-bar"></div>
            <span id="strength-text">Enter password</span>
             </div>
            
        <button type="submit" id="continue1" class="disabled-link" disabled style="display: block; width: 100%;">Continue</button>
            <p id="noAccount">Already have an account?</p>
            <a href="Login.php" id="signUpButton" style="text-decoration: none; display: block;">Sign In</a>
            </form>
        </div>
    </div>
</div>
<p class="faculty">
    FACULTY OF COMPUTER SCIENCE AND INFORMATION TECHNOLOGY
</p>
</body>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. SELECT ELEMENTS
    // We select by 'name' to be precise
    const fullName = document.querySelector('input[name="fullName"]');
    const studentId = document.querySelector('input[name="upmId"]');
    const password = document.getElementById('password'); 
    const continueBtn = document.getElementById('continue1'); 

    // 2. DEFINE THE PASSWORD CHECKER
    function checkPasswordStrength() {
        // We get the current value directly from the password input
        const val = password.value;
        const strengthBar = document.getElementById('strength-bar');
        const strengthText = document.getElementById('strength-text');
        
        let score = 0;
        
        // Reset class
        strengthBar.className = '';

        // Calculate Score
        if (val.length >= 8) score++;
        if (val.match(/[a-z]/) && val.match(/[A-Z]/)) score++;
        if (val.match(/\d/)) score++;
        if (val.match(/[^a-zA-Z\d]/)) score++;

        // Update UI
        if (val.length === 0) {
            strengthBar.style.width = '0%';
            strengthText.textContent = 'Enter password';
            strengthText.style.color = '#666';
        } else if (score < 2) {
            strengthBar.style.width = '33%';
            strengthBar.classList.add('weak');
            strengthText.textContent = 'Weak';
            strengthText.style.color = '#dc3544';
        } else if (score < 4) {
            strengthBar.style.width = '66%';
            strengthBar.classList.add('medium');
            strengthText.textContent = 'Medium';
            strengthText.style.color = '#ffc107';
        } else {
            strengthBar.style.width = '100%';
            strengthBar.classList.add('strong');
            strengthText.textContent = 'Strong';
            strengthText.style.color = '#28a745';
        }

        // AFTER checking strength, we immediately check if the whole form is valid
        checkForm();
    }

    // 3. DEFINE THE FORM VALIDATOR
    function checkForm() {
        const bar = document.getElementById('strength-bar');
        const isPasswordStrong = bar.classList.contains('strong'); // Only allow Strong password
        
        const isNameFilled = fullName.value.trim().length > 0;
        const isIdFilled = studentId.value.trim().length > 5;

        // If everything is good, unlock the button
        if (isNameFilled && isIdFilled && isPasswordStrong) {
            continueBtn.classList.remove('disabled-link');
            continueBtn.disabled = false; 
            continueBtn.style.cursor = "pointer";
        } else {
            continueBtn.classList.add('disabled-link');
            continueBtn.disabled = true;
            continueBtn.style.cursor = "not-allowed";
        }
    }

    // 4. ATTACH LISTENERS (This replaces the need for onkeyup in HTML)
    if (fullName) fullName.addEventListener('input', checkForm);
    if (studentId) studentId.addEventListener('input', checkForm);
    
    if (password) {
        // Run the strength checker whenever user types in password
        password.addEventListener('input', checkPasswordStrength);
    }

    // Run once on load to ensure button starts disabled
    checkForm();
});
</script>
</html>