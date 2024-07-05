<?php
function delete_user(mysqli $conn) : void
{
    $stmt = $conn->prepare("DELETE FROM users WHERE userID = ?");

    $stmt->bind_param("i", $_SESSION["userID"]);

    $stmt->execute();
}

function delete_element(mysqli $conn, string $elementType, int $elementID) : void
{

    if ($elementType === "note") /* if a note is being deleted */ {
        
        $conn->query("UPDATE notes SET noteIndex=noteIndex-1 WHERE noteIndex > (SELECT noteIndex FROM notes WHERE noteID = $elementID)");
        //prepare the statement
        $stmt = $conn->prepare("DELETE FROM notes WHERE noteID =?");

    } else /* if a folder is being deleted */ {

        $folderIndex=$conn->query("SELECT folderIndex FROM folders WHERE folderID = $elementID")->fetch_assoc()["folderIndex"];
        $conn->query("UPDATE folders SET folderIndex = folderIndex-1 WHERE folderIndex > $folderIndex");

        //prepare the statement
        $stmt = $conn->prepare("DELETE FROM folders WHERE folderID =?");

    }

    // bind the parameter
    $stmt->bind_param("i", $elementID);

    // execute the query
    $stmt->execute();

}

$json_data = file_get_contents("php://input");
$arr = json_decode($json_data, true);

if (isset($arr["elementType"])) /* if a note or a folder has to be deleted */{

    delete_element($conn, $arr["elementType"], $arr["elementID"]);
    
    echo json_encode(array("message" => "Element deleted!", "code" => 200));

} else if (isset($arr["deleteUser"])) /* if a user has to be deleted */ {

    delete_user($conn);

    echo json_encode(array("message" => "User deleted!", "code" => 200));

}


