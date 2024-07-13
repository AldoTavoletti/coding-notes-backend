<?php

function patch_note_content(mysqli $conn, string $content, int $noteID): void
{

    $stmt = $conn->prepare("UPDATE notes SET content=? WHERE noteID=?");

    $stmt->bind_param("si", $content, $noteID);

    $stmt->execute();

}

function patch_note_title(mysqli $conn, string $title, int $noteID): void
{

    $stmt = $conn->prepare("UPDATE notes SET title=? WHERE noteID=?");

    $stmt->bind_param("si", $title, $noteID);

    

    echo json_encode(array("result"=>$stmt->execute(), "message" => "Title updated!", "code" => 200, "title" => $title));
}

function patch_folder(mysqli $conn, string $name, string $color, int $folderID): void
{

    $stmt = $conn->prepare("UPDATE folders SET folderName=?, color=? WHERE folderID=?");

    $stmt->bind_param("ssi", $name, $color, $folderID);

    $stmt->execute();

}

function reorder_folders(mysqli $conn, int $oldIndex, int $newIndex, int $folderID): void
{

    $oldIndex++;
    $newIndex++;

    if ($oldIndex < $newIndex) {
       
        $conn->query("UPDATE folders SET folderIndex = folderIndex-1 WHERE userID = {$_SESSION['userID']} AND folderIndex BETWEEN $oldIndex+1 AND $newIndex");

    } else {

        $conn->query("UPDATE folders SET folderIndex = folderIndex+1 WHERE userID = {$_SESSION['userID']} AND folderIndex BETWEEN $newIndex AND $oldIndex-1");

    }

    $conn->query("UPDATE folders SET folderIndex = $newIndex WHERE folderID = $folderID");


}

function reorder_notes(mysqli $conn, int $oldIndex, int $newIndex, int $noteID, int $folderID): void
{

    $oldIndex++;
    $newIndex++;

    if ($oldIndex < $newIndex) {

        $conn->query("UPDATE notes SET noteIndex = noteIndex-1 WHERE folderID = $folderID AND noteIndex BETWEEN $oldIndex+1 AND $newIndex");

    } else {

        $conn->query("UPDATE notes SET noteIndex = noteIndex+1 WHERE folderID = $folderID AND noteIndex BETWEEN $newIndex AND $oldIndex-1");

    }

    $conn->query("UPDATE notes SET noteIndex = $newIndex WHERE noteID = $noteID");


}

function move_note(mysqli $conn, int $folderID, int $noteID):void
{

$conn->query("UPDATE notes SET folderID = $folderID WHERE noteID = $noteID");

}

$json_data = file_get_contents("php://input");
$arr = json_decode($json_data, true);

if (isset($arr["content"])) /* if the content of the note has to be patched */ {

    patch_note_content($conn, $arr["content"], $arr["noteID"]);

    echo json_encode(array("message" => "Content updated!", "code" => 200));


} elseif (isset($arr["title"])) /* if the title of the note has to be patched */ {

    patch_note_title($conn, $arr["title"], $arr["noteID"]);

    // echo json_encode(array("message" => "Title updated!", "code" => 200, "title"=>$arr["title"]));


} else if (isset($arr["name"], $arr["color"], $arr["folderID"])) /* if a folder has to be patched */ {

    patch_folder($conn, $arr["name"], $arr["color"], $arr["folderID"]);

    echo json_encode(array("message" => "Folder updated!", "code" => 200));


}else if(isset($arr["oldIndex"], $arr["noteID"])){

    reorder_notes($conn, $arr["oldIndex"], $arr["newIndex"], $arr["noteID"], $arr["folderID"]);

    echo json_encode(array("message" => "Notes reordered!", "code" => 200));

} else if (isset($arr["oldIndex"], $arr["folderID"])) {

    reorder_folders($conn, $arr["oldIndex"], $arr["newIndex"], $arr["folderID"]);

    echo json_encode(array("message" => "Folders reordered!", "code" => 200));

}else if(isset($arr["action"]) && $arr["action"]==="move-note"){

    move_note($conn, $arr["folderID"], $arr["noteID"]);

    echo json_encode(array("message" => "Note moved!", "code" => 200));

}

