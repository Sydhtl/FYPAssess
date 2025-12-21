<?php
include 'mysqlConnect.php';

session_start();

$upmId = $_POST['upmId'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT * FROM user WHERE UPM_ID = ?");
$stmt->bind_param("s", $upmId);
$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows>0){
    $row=$result->fetch_assoc();
    
    // Check both hashed password and plain text password for backward compatibility
    $passwordMatch = false;
    
    // First try password_verify for hashed passwords (new system)
    if(password_verify($password, $row['Password'])){
        $passwordMatch = true;
    }
    // Fall back to plain text comparison for old passwords (legacy system)
    elseif($password === $row['Password']){
        $passwordMatch = true;
    }
    
    if(!$passwordMatch){
        $_SESSION['login_error'] = 'Invalid UPM ID or Password';
        header("Location: ../html/login/login.php");
        exit();
    }
    $_SESSION['upmId']=$row['UPM_ID'];
    $_SESSION['name']=$row['Name'];
    $_SESSION['role']=$row['Role'];

    if($_SESSION['role']=='Coordinator'){
        header("Location: ../html/coordinator/dashboard/dashboardCoordinator.php");
        exit();
    }
    
    elseif($_SESSION['role']=='Supervisor'){
        header("Location: ../php/phpSupervisor/dashboard.php");
        exit();
    }
    
    elseif($_SESSION['role']=='Student'){
        header("Location: ../html/student/dashboard/dashboard.php");
        exit();
    }

}
else{
    $_SESSION['login_error'] = 'Invalid UPM ID or Password';
    header("Location: ../html/login/login.php");
    exit();
}
?>
