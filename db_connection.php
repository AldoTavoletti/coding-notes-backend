<?php 

$servername = "codingnotes.c9isugac825d.eu-north-1.rds.amazonaws.com"; 
$username = "aldotavoletti"; 
$password = 'KSd{{eY40DyMv$RvK7}s0i_Pf0NB';
$dbname = "codingnotesdb"; 
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
