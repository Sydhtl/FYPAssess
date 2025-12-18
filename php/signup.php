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
    // Account exists: ask if registering for a new semester
    $_SESSION['signup_fullName']  = $fullname;
    $_SESSION['signup_upmId']     = $upmId;
    $_SESSION['signup_password']  = $password;
    $_SESSION['existing_student'] = true;

    echo "<script>
        if (confirm('The account is already registered. Are you registering for a new semester?')) {
            window.location.href = '../html/login/information.php?existing=1';
        } else {
            window.location.href = '../html/login/Login.php';
        }
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

