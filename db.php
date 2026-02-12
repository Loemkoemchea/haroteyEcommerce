<?php
// file: db.php
$servername = "localhost";
$username   = "root";     // your MySQL username
$password   = "";         // your MySQL password
$dbname     = "harotey";  // changed from shop1 to harotey

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to UTF-8
$conn->set_charset("utf8");
?>