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

    $stmt->execute();

}

function patch_folder(mysqli $conn, string $name, string $color, int $folderID): void
{

    $stmt = $conn->prepare("UPDATE folders SET folderName=?, color=? WHERE folderID=?");

    $stmt->bind_param("ssi", $name, $color, $folderID);

    $stmt->execute();

}


$json_data = file_get_contents("php://input");
$arr = json_decode($json_data, true);

if (isset($arr["content"])) /* if the content of the note has to be patched */ {

    patch_note_content($conn, $arr["content"], $arr["noteID"]);

    echo json_encode(array("message" => "Content updated!", "code" => 200));


} elseif (isset($arr["title"])) /* if the title of the note has to be patched */ {

    patch_note_title($conn, $arr["title"], $arr["noteID"]);

    echo json_encode(array("message" => "Title updated!", "code" => 200));


} else if (isset($arr["name"], $arr["color"], $arr["folderID"])) /* if a folder has to be patched */ {

    patch_folder($conn, $arr["name"], $arr["color"], $arr["folderID"]);

    echo json_encode(array("message" => "Folder updated!", "code" => 200));

}

