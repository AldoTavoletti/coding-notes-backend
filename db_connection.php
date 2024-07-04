<?php

$fields = parse_url($_ENV["uri"]);

// Extracting components from the URI
$host = $fields["host"];
$port = $fields["port"];
$user = urldecode($fields["user"]);
$pass = urldecode($fields["pass"]);
$dbname = trim($fields["path"], '/'); // extracting dbname from path

// Establishing mysqli connection
$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Setting SSL options
mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    __DIR__ . "/ca.pem", // path to your ca.pem file
    NULL,
    NULL
);


