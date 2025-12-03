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
</body>
<script>
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