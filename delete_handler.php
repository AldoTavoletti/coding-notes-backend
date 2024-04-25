<?php

//get the json data sent (we have to retrieve it this way since it's sent with ajax and not with a regular form)
$json_data = file_get_contents("php://input");

// decode the json data into an associative array
$arr = json_decode($json_data, true);


if ($arr["elementType"] === "note") /* if a note is being deleted */ {

    //prepare the statement
    $stmt = $conn->prepare("DELETE FROM notes WHERE noteID =?");

} else /* if a folder is being deleted */ {

    //prepare the statement
    $stmt = $conn->prepare("DELETE FROM folders WHERE folderID =?");
    
}

    // bind the parameter
    $stmt->bind_param("i", $arr["elementID"]);

    // execute the query
    $stmt->execute();

echo json_encode(array("message" => "Element deleted!", "code" => 200));

