<?php
$servername="localhost";
$username="root";
$dbname="fypassess";
$password="";

$conn=mysqli_connect($servername,$username,$password,$dbname);

if($conn->connect_error){
    die("Connection failed");
}

?>