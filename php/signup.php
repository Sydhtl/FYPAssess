<?php include 'mysqlConnect.php';

session_start();

$fullname = $_POST['fullName'];
$upmId = $_POST['upmId'];
$password = $_POST['password'];

$stmt=$conn-> prepare("Select * from user where UPM_ID=?");
$stmt-> bind_param("s",$upmId);
$stmt-> execute();
$result=$stmt-> get_result();

if($result->num_rows>0){
echo "<script>
         alert('This UPMID is already exist in the system. Please use another UPMID.');
          window.location.href='../html/login/signup.php';
          </script>";
}
else{
 $_SESSION['signup_fullName'] = $fullname;
    $_SESSION['signup_upmId']   = $upmId;
    $_SESSION['signup_password'] = $password;
    header("Location: ../html/login/information.php");
    exit();

}
?>

