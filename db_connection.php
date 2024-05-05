<?php 

$servername = $_ENV["DB_HOSTNAME"]; 
$username = $_ENV["DB_USERNAME"]; 
$password = $_ENV["DB_PASSWORD"];
$dbname = $_ENV["DB_NAME"]; 
// $servername = "localhost";
// $username = "root";
// $password = '';
// $dbname = "codingnotesdb";

// Create connection 
$conn = mysqli_connect($servername, $username, $password, $dbname); 
 
// Check connection 
if (!$conn) { 
    die("Connection failed: " . mysqli_connect_error()); 
} 


