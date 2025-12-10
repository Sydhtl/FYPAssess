<?php
$servername = "localhost";
$username = "root";       // Default XAMPP username
$password = "123456";           // Default XAMPP password is empty
$dbname = "fypassess";    // <--- MUST MATCH WORKBENCH EXACTLY
$port = 3307;

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, port: $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} 
// If the code reaches here, it means success, but this file is usually silent.
?>