<?php

$servername = $_ENV["DB_HOSTNAME"];
$userdb = $_ENV["DB_USERNAME"];
$passworddb = $_ENV["DB_PASSWORD"];
$dbname = $_ENV["DB_NAME"];

// Create connection 
$conn = mysqli_connect($servername, $userdb, $passworddb, $dbname, 27516);

// Check connection 
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}


