<?php


function add_folder(mysqli $conn, string $name, string $color, int $userID): int
{

    $stmt = $conn->prepare("INSERT INTO folders (folderName, color, userID) VALUES (?,?,?)");

    $stmt->bind_param("ssi", $name, $color, $userID);

    $stmt->execute();

    return $conn->insert_id;

}

function add_note(mysqli $conn, string $title, int $folderID): int
{

    $stmt = $conn->prepare("INSERT INTO notes (title, folderID) VALUES (?,?)");

    $stmt->bind_param("si", $title, $folderID);

    $stmt->execute();

    return $conn->insert_id;
}

function username_exists(mysqli $conn, string $username): ?array
{

    // look for a record with the same username as the one inserted from the user
    $stmt = $conn->prepare("SELECT userID, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();


    if (isset($result["userID"], $result["userID"])) /* if there is already a record with this username */ {

        return $result;

    } else /* if there is no user with this username*/ {

        return null;

    }

}

function generate_tokens(): array
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));

    return [$selector, $validator, $selector . ':' . $validator];
}


function insert_user_token(mysqli $conn, int $userID, string $selector, string $hashedValidator, string $expiry): bool
{

    $stmt = $conn->prepare('INSERT INTO user_tokens(userID, selector, hashed_validator, expiry) VALUES(?, ?, ?, ?)');
    $stmt->bind_param("isss", $userID, $selector, $hashedValidator, $expiry);

    return $stmt->execute();
}





function remember_me(mysqli $conn, int $userID, int $day = 30): void
{
    [$selector, $validator, $token] = generate_tokens();


    // remove all existing token associated with the user id
    delete_user_token($conn, $userID);

    // set expiration date
    $expired_seconds = time() + 60 * 60 * 24 * $day;

    // insert a token to the database
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);

    if (insert_user_token($conn, $userID, $selector, $hash_validator, $expiry)) {
        setcookie('remember_me', $token, ['expires' => $expired_seconds, 'samesite' => 'None', 'domain' => ".coding-notes-backend.onrender.com", "httponly" => 1, "secure" => 1]);
    }
}

function delete_user_token(mysqli $conn, int $userID): bool
{
    $stmt = $conn->prepare('DELETE FROM user_tokens WHERE userID = ?');
    $stmt->bind_param('i', $userID);

    return $stmt->execute();
}
function signup(mysqli $conn, string $username, string $password, bool $remember): void
{

    // hash the passowrd
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // insert the user in the db
    $stmt = $conn->prepare("INSERT INTO users(username,password) VALUES(?,?)");
    $stmt->bind_param("ss", $username, $passwordHash);


    $userID = $conn->insert_id;

    echo json_encode(array("userID"=>$userID,"result"=>$stmt->execute()));

    // create a default "General" folder
    add_folder($conn, "General", "black", $userID);


    if (isset($_COOKIE['remember_me'])) {

        if (isset($_SESSION["userID"])) {
            // remove all existing token associated with the previous userID
            delete_user_token($conn, $_SESSION["userID"]);
        }

        // remove the remember_me cookie
        setcookie('remember_me', '', ['expires' => time() - 3600, 'samesite' => 'None', 'domain' => ".coding-notes-backend.onrender.com", "httponly" => 1, "secure" => 1]);

        unset($_COOKIE['remember_me']);

    }

    if ($remember) {

        remember_me($conn, $userID);

    }

    // save the userID in a session
    $_SESSION["userID"] = $userID;
    $_SESSION["username"] = $username;


}

function login(mysqli $conn, int $userID, string $username, string $password, string $passwordDB, bool $remember): void
{

    if (password_verify($password, $passwordDB)) /* if the password is correct */ {

        if (isset($_COOKIE['remember_me'])) {

            if (isset($_SESSION["userID"])) {
                // remove all existing token associated with the previous userID
                delete_user_token($conn, $_SESSION["userID"]);
            }

            // remove the remember_me cookie
            setcookie('remember_me', '', ['expires' => time() - 3600, 'samesite' => 'None', 'domain' => ".coding-notes-backend.onrender.com", "httponly" => 1, "secure" => 1]);

            unset($_COOKIE['remember_me']);

        }

        if ($remember) {
            remember_me($conn, $userID);
        }
        // save the userID in a session
        $_SESSION["userID"] = $userID;
        $_SESSION["username"] = $username;
        echo json_encode(array("message" => "Access granted!", "username" => $_SESSION["username"], "code" => 200));

    } else {

        die(json_encode(array('message' => 'Wrong password', 'code' => 401)));


    }

}

function get_google_tokens(string $code): string
{

    // set all the necessary data
    $client_id = $_ENV["GOOGLE_CLIENT_ID"];
    $client_secret = $_ENV["GOOGLE_CLIENT_SECRET"];
    $redirect_uri = $_SERVER['HTTP_ORIGIN'];
    $grant_type = 'authorization_code';

    // Build request body data
    $data = http_build_query(
        array(
            'code' => $code,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri' => $redirect_uri,
            'grant_type' => $grant_type
        )
    );

    // Set headers
    $headers = array(
        'Content-Type: application/x-www-form-urlencoded',
    );

    // Initialize cURL session
    $ch = curl_init();

    // Set cURL options
    // url
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');

    //POST request
    curl_setopt($ch, CURLOPT_POST, true);

    // the body of the request
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // the headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // make a string be returned
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        // Handle cURL errors
        $error_message = curl_error($ch);
        echo json_encode(['error' => 'cURL Error: ' . $error_message]);
    }

    // Close cURL session
    curl_close($ch);


    return $response;

}

function oauth_tokeninfo_call(string $id_token): string
{

    // Google OAuth 2.0 tokeninfo endpoint URL
    $tokenInfoUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);

    // Make a GET request to the tokeninfo endpoint
    $response = file_get_contents($tokenInfoUrl);

    return $response;
}

function add_google_user(mysqli $conn, string $sub, string $username): array
{

    // insert the user in the db
    $stmt = $conn->prepare("INSERT INTO users (username, sub) VALUES (?,?)");
    $stmt->bind_param("ss", $username, $sub);
    $stmt->execute();

    return array("userID" => $conn->insert_id, "username" => $username);

}

function get_google_user(mysqli $conn, string $sub): ?array
{

    // get the userID of the current user from the db using the sub key
    $stmt = $conn->prepare("SELECT userID, username FROM users WHERE sub=?");
    $stmt->bind_param("s", $sub);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();


    return isset($result["userID"]) ? $result : null;

}

//get the json data sent (we have to retrieve it this way since it's sent with ajax and not with a regular form, so $_POST[] doesn't work)
$json_data = file_get_contents("php://input");

// decode the json data into an associative array
$arr = json_decode($json_data, true);


if (isset($arr["color"], $arr["name"])) /* if a folder is being added */ {

    add_folder($conn, $arr["name"], $arr["color"], $_SESSION["userID"]);

    echo json_encode(array("message" => "Folder created!", "code" => 200));


} else if (isset($arr["title"], $arr["folderID"])) /* if a note is being added */ {

    $note_id = add_note($conn, $arr["title"], $arr["folderID"]);

    echo json_encode(array("message" => "Note created!", "noteID" => $note_id, "code" => 200));


} else if (isset($arr["action"]) && $arr["action"] === "signup") {

    if (username_exists($conn, $arr["username"])) /* if there is already a record with this username */ {

        die(json_encode(array('message' => 'This username is already in use', 'code' => 0)));

    } else /* if the username is valid */ {

        signup($conn, $arr["username"], $arr["password"], $arr["remember"]);

        // echo json_encode(array("message" => "Signed up!", "username" => $_SESSION["username"], "code" => 200));


    }

} else if (isset($arr["action"]) && $arr["action"] === "login") {

    $result = username_exists($conn, $arr["username"]);

    if (isset($result["password"], $result["userID"])) /* if a user with this username exists */ {

        login($conn, $result["userID"], $arr["username"], $arr["password"], $result["password"], $arr["remember"]);

    } else {

        die(json_encode(array('message' => 'Not existing username', 'code' => 401)));


    }

} else if (isset($arr["code"])) /* google login */ {

    // get the tokens (I actually only need the id_token)
    $tokens = get_google_tokens($arr["code"]);

    // decode tokens
    $tokensDecoded = json_decode($tokens);

    // verify the id_token and obtain the sub
    $tokenInfo = oauth_tokeninfo_call($tokensDecoded->id_token);

    // Decode the tokenInfo json
    $tokenInfoDecoded = json_decode($tokenInfo);

    // get the userID (if no user is found userID will be false, meaning the user is not in the db yet)
    $googleUser = get_google_user($conn, $tokenInfoDecoded->sub);

    if (!$googleUser) /* if the user isn't already in the db */ {

        $username = str_replace(" ", "_", strtolower($tokenInfoDecoded->name));
        $i = 0;

        while (username_exists($conn, $username)) {
            $i++;
            $username .= $i;

        }

        // add the user to the db
        $googleUser = add_google_user($conn, $tokenInfoDecoded->sub, $username);

        // create a default "General" folder
        add_folder($conn, "General", "black", $googleUser["userID"]);

    }

    if (isset($_COOKIE['remember_me'])) {

        if (isset($_SESSION["userID"])) {
            // remove all existing token associated with the previous userID
            delete_user_token($conn, $_SESSION["userID"]);
        }

        // remove the remember_me cookie
        setcookie('remember_me', '', ['expires' => time() - 3600, 'samesite' => 'None', 'domain' => ".coding-notes-backend.onrender.com", "httponly" => 1, "secure" => 1]);

        unset($_COOKIE['remember_me']);

    }

    if ($arr["remember"]) {
        remember_me($conn, $googleUser["userID"]);
    }

    // save the userID in a session variable
    $_SESSION["userID"] = $googleUser["userID"];
    $_SESSION["username"] = $googleUser["username"];


    echo json_encode(array("message" => "Access granted!", "username" => $_SESSION["username"], "code" => 200));

}

