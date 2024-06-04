<?php

function get_all_folders_and_notes(mysqli $conn): ?array
{

    $folderID = null;

    // get the user's folders
    $stmt = $conn->prepare("SELECT * FROM folders WHERE userID=?");
    $stmt->bind_param("i", $_SESSION["userID"]);
    $stmt->execute();

    // get the result
    $result = $stmt->get_result();

    // fetch the whole result into an associative array
    $folders = $result->fetch_all(MYSQLI_ASSOC);

    // get all the notes of the user's folders. The statement is prepared only once.
    $stmt = $conn->prepare("SELECT * FROM notes WHERE folderID=?");

    // $folderID doesn't have a value now, but it gets assigned a value in the for loop. When the statement gets executed, the compiler looks for the binded parameters and uses the current value it is assigned to.
    $stmt->bind_param("i", $folderID);

    for ($i = 0; $i < count($folders); $i++) {

        // assign the current folder's ID to $folderID
        $folderID = $folders[$i]["folderID"];

        $stmt->execute();
        $result = $stmt->get_result();

        // fetch the whole result into an associative array
        $notes = $result->fetch_all(MYSQLI_ASSOC);

        // insert the notes associative array in a field related to the parent folder
        $folders[$i]["notes"] = $notes;

    }

    return $folders;

}

function get_single_note(mysqli $conn): ?array
{

    // get the requested note
    $stmt = $conn->prepare("SELECT * FROM notes WHERE noteID =?");

    $stmt->bind_param("i", $_GET["note"]);

    $stmt->execute();
    $result = $stmt->get_result();

    // fetch the single row as an associative array
    $note = $result->fetch_assoc();

    return $note;

}
function find_user_by_token(mysqli $conn, string $token) : ?array
{
    $tokens = parse_token($token);

    if (!$tokens) {
        return null;
    }


    $stmt = $conn->prepare('SELECT users.userID, username
            FROM users
            INNER JOIN user_tokens ON userID = users.id
            WHERE selector = ? AND
                expiry > now()
            LIMIT 1');
    $stmt->bind_param('s', $tokens[0]);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function token_is_valid(mysqli $conn, string $token) : array | bool
{
    // parse the token to get the selector and validator 
    [$selector, $validator] = parse_token($token);

    $tokens = find_user_token_by_selector($conn, $selector);
    if (!$tokens) {
        return false;
    } else if (password_verify($validator, $tokens['hashed_validator'])) {
        return $tokens;

    }
    return false;

}
function find_user_token_by_selector(mysqli $conn, string $selector) : ?array
{


    $stmt = $conn->prepare('SELECT ut.userID, u.username, ut.hashed_validator
                FROM user_tokens ut INNER JOIN users u ON u.userID=ut.userID
                WHERE ut.selector = ? AND
                    ut.expiry >= now()
                LIMIT 1');
    $stmt->bind_param('s', $selector);

    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function parse_token(string $token): ?array
{
    $parts = explode(':', $token);

    if ($parts && count($parts) === 2) {
        return [$parts[0], $parts[1]];
    }
    return null;
}


function check_logged_in(mysqli $conn) : bool
{

    if (isset($_SESSION["userID"])) /* if the session variable is set, it means the user is still logged in */ {

        echo json_encode(array("message" => "The user is logged in!", "username" => $_SESSION["username"], "code" => 200));
        return true;
    }

    // check the remember_me in cookie
    $token = filter_input(INPUT_COOKIE, 'remember_me', FILTER_SANITIZE_STRING);
    if ($token) {

        $result = token_is_valid($conn, $token);
        if ($result) {
            $_SESSION["userID"] = $result["userID"];
            $_SESSION["username"] = $result["username"];


            return true;
        }
    }
    return false;

}

function logout() : void
{

    // destroy the userID session variable
    unset($_SESSION["userID"]);
    unset($_SESSION["username"]);

    // remove the remember_me cookie
    if (isset($_COOKIE['remember_me'])) {
        setcookie('remember_me', '', ['expires' => time() - 3600, 'samesite' => 'None', 'domain' => ".coding-notes-backend.onrender.com", "httponly" => 1, "secure" => 1]);

        unset($_COOKIE['remember_me']);
    }

    echo json_encode(array("message" => "The user has logged out!", "code" => 200));

}





if (isset($_GET["retrieve"]) && $_GET["retrieve"] === "all") {

    $folders_and_notes = get_all_folders_and_notes($conn);

    echo json_encode($folders_and_notes);


} elseif (isset($_GET["retrieve"]) && $_GET["retrieve"] === "single") {

    $note = get_single_note($conn);

    echo json_encode($note);


} else if (isset($_GET["check"]) && $_GET["check"] === "login") {

    echo check_logged_in($conn) ? 
    json_encode(array("message" => "The user is logged in!", "username" => $_SESSION["username"], "code" => 200)):
    json_encode(array("message" => "The user is not logged in!", "code" => 403));
    
    ;

} else if (isset($_GET["logout"]) && $_GET["logout"] === "true") {

    logout();

}

