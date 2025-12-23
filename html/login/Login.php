<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FYPAssess</title>
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
    <div class="container2">
        <p id="welcomeBack">Welcome Back </p>
        <p id="desc">Sign in to access your account</p>

        <form action="../../php/login.php" method="post">
     <p id="UsernamePassword"> Staff ID or Student ID </p>
        <input type="text" placeholder="Enter Staff or Student ID" name="upmId" required >
        <p id="UsernamePassword"> Password </p>
        <input type="password" name="password" placeholder="Enter Password" required>
        <button type ="submit" id="signInButton" class="disabled-link">Sign In To FYPAssess</button><br>
        <p id="noAccount">Don't have an account?</p>
        <a href="signup.php" id="signUpButton" style="text-decoration: none; display: block;">Sign Up</a>
    </div>
</form>
   
</div>
</div>
 <p class="faculty">
  FACULTY OF COMPUTER SCIENCE AND INFORMATION TECHNOLOGY
</p>

<!-- Error Modal -->
<div id="errorModal" class="custom-modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content-custom">
            <div class="modal-icon" style="color: #dc3545;">⚠️</div>
            <h3 class="modal-title-custom">Login Failed</h3>
            <p class="modal-message" id="errorMessage"></p>
            <button id="errorModalOkBtn" class="modal-button">OK</button>
        </div>
    </div>
</div>

<!-- Registration Success Modal -->
<div id="registrationSuccessModal" class="custom-modal" style="display: none;">
    <div class="modal-dialog">
        <div class="modal-content-custom">
            <div class="modal-icon">✅</div>
            <h3 class="modal-title-custom">Registration Successful!</h3>
            <p class="modal-message">Your account has been successfully created. You can now log in.</p>
            <button id="modalOkBtn" class="modal-button">OK</button>
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
}
.modal-dialog {
    position: relative;
    width: auto;
    max-width: 500px;
    margin: 10% auto;
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
</style>

</body>
<script>
    <?php
    session_start();
    $showSuccessModal = isset($_SESSION['registration_success']) && $_SESSION['registration_success'] === true;
    $loginError = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
    if ($showSuccessModal) {
        unset($_SESSION['registration_success']);
    }
    if ($loginError) {
        unset($_SESSION['login_error']);
    }
    ?>
    
    // Show error modal if login failed
    <?php if ($loginError): ?>
    window.addEventListener('DOMContentLoaded', function() {
        const errorModal = document.getElementById('errorModal');
        const errorMessage = document.getElementById('errorMessage');
        if (errorModal && errorMessage) {
            errorMessage.textContent = <?php echo json_encode($loginError); ?>;
            errorModal.style.display = 'block';
            document.getElementById('errorModalOkBtn').addEventListener('click', function() {
                errorModal.style.display = 'none';
            });
        }
    });
    <?php endif; ?>
    
    // Show success modal if registration was successful
    <?php if ($showSuccessModal): ?>
    window.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('registrationSuccessModal');
        if (modal) {
            modal.style.display = 'block';
            document.getElementById('modalOkBtn').addEventListener('click', function() {
                modal.style.display = 'none';
            });
        }
    });
    <?php endif; ?>
    
    const ID=document.querySelector('input[placeholder="Enter Staff or Student ID"]');
    const password=document.querySelector('input[placeholder="Enter Password"]');
    const signInButton=document.getElementById('signInButton');

    function checkForm(){
        const idFilled=ID && ID.value.trim().length>0;
        const passwordFilled=password && password.value.trim().length>0;
        if(idFilled && passwordFilled){
            signInButton.classList.remove('disabled-link');
        }else{
            signInButton.classList.add('disabled-link');
        }
    }

    if(ID) ID.addEventListener('input',checkForm);
    if(password) password.addEventListener('input',checkForm);

      
    checkForm();
</script>
</html>