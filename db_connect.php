<?php
$servername = "localhost";
$username = "root";
$password = "password";
$database = "campus_lost_found";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
// Use UTF-8 and don't echo from included connection file.
$conn->set_charset('utf8mb4');
?>