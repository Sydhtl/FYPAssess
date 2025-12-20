<?php
include 'mysqlConnect.php';

session_start();

$stmt = $conn->prepare("SELECT * FROM user WHERE UPM_ID = ? AND Password = ?");
$stmt->bind_param("ss",$_POST['upmId'], $_POST['password']);
$stmt->execute();

$result=$stmt->get_result();

if($result->num_rows>0){
    $row=$result->fetch_assoc();
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
    echo "<script>
    alert('Invalid UPM ID or Password'); window.location.href='../html/login/login.php';</script>";
}
?>
