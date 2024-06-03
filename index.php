<?php
session_start();
    $allowed_origins = array("https://codingnotes-six.vercel.app", "http://localhost:3000");
    
    if (in_array($origin=$_SERVER["HTTP_ORIGIN"],$allowed_origins)) {
        header("Access-Control-Allow-Origin: $origin");
    }

    // header("Access-Control-Allow-Origin: " . $_SERVER["HTTP_ORIGIN"]);

    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day

// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PATCH, DELETE");
    
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}


require_once "db_connection.php";

switch ($_SERVER["REQUEST_METHOD"]) {
    case "GET":

        include_once "get_handler.php";
        break;

    case "POST":

        include_once "post_handler.php";
        break;

    case "PATCH":

        include_once "patch_handler.php";
        break;

    case "DELETE":

        include_once "delete_handler.php";
        break;
    
    default:
        # code...
        break;
}

