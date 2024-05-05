<?php


function add_folder($conn, $name, $color, $userID)
{

    //prepare the stmt
    $stmt = $conn->prepare("INSERT INTO folders (folderName, color, userID) VALUES (?,?,?)");

    // bind the parameters
    $stmt->bind_param("ssi", $name, $color, $userID);

    // execute the query
    $stmt->execute();

}

function add_note($conn, $title, $folderID)
{

    //prepare the stmt
    $stmt = $conn->prepare("INSERT INTO notes (title, folderID) VALUES (?,?)");

    // bind the parameters
    $stmt->bind_param("si", $title, $folderID);

    // execute the query
    $stmt->execute();

}

function username_exists($conn, $username)
{

    // look for a record with the same username as the one inserted from the user
    $stmt = $conn->prepare("SELECT userID, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();


    if (isset($result["userID"], $result["userID"])) /* if there is already a record with this username */ {

        return $result;

    } else /* if there is no user with this username*/ {

        return false;

    }

}

function generate_tokens(): array
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));

    return [$selector, $validator, $selector . ':' . $validator];
}

function parse_token(string $token): ?array
{
    $parts = explode(':', $token);

    if ($parts && count($parts) === 2) {
        return [$parts[0], $parts[1]];
    }
    return null;
}

function insert_user_token($conn, int $userID, string $selector, string $hashedValidator, string $expiry): bool
{

    $stmt = $conn->prepare('INSERT INTO user_tokens(userID, selector, hashed_validator, expiry) VALUES(?, ?, ?, ?)');
    $stmt->bind_param("isss", $userID, $selector, $hashedValidator, $expiry);

    return $stmt->execute();
}

function find_user_token_by_selector($conn, string $selector)
{


    $stmt = $conn->prepare('SELECT *
                FROM user_tokens
                WHERE selector = ? AND
                    expiry >= now()
                LIMIT 1');
    $stmt->bind_param('s', $selector);

    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}
function delete_user_token($conn, int $userID): bool
{
    $stmt = $conn->prepare('DELETE FROM user_tokens WHERE userID = ?');
    $stmt->bind_param('i', $userID);

    return $stmt->execute();
}

function find_user_by_token($conn, string $token)
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

function token_is_valid($conn,string $token): bool { 
    // parse the token to get the selector and validator 
    [$selector, $validator] = parse_token($token);

$tokens = find_user_token_by_selector($conn, $selector);
if (!$tokens) {
    return false;
}

return password_verify($validator, $tokens['hashed_validator']);
}

function remember_me($conn, int $userID, int $day = 30)
{
    [$selector, $validator, $token] = generate_tokens();

    // remove all existing token associated with the user id
    delete_user_token($conn,$userID);

    // set expiration date
    $expired_seconds = time() + 60 * 60 * 24 * $day;

    // insert a token to the database
    $hash_validator = password_hash($validator, PASSWORD_DEFAULT);
    $expiry = date('Y-m-d H:i:s', $expired_seconds);

    if (insert_user_token($conn, $userID, $selector, $hash_validator, $expiry)) {
        setcookie('remember_me', $token, $expired_seconds);
    }
}
function signup($conn, $username, $password, $remember)
{

    // hash the passowrd
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // insert the user in the db
    $stmt = $conn->prepare("INSERT INTO users(username,password) VALUES(?,?)");
    $stmt->bind_param("ss", $username, $passwordHash);
    $stmt->execute();

    // get its userID
    $stmt = $conn->prepare("SELECT userID FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $userID = $stmt->get_result()->fetch_assoc()["userID"];

    // create a default "General" folder
    add_folder($conn, "General", "black", $userID);

    // save the userID in a session
    $_SESSION["userID"] = $userID;

}

function login($conn, $userID, $password, $passwordDB, $remember)
{

    if (password_verify($password, $passwordDB)) /* if the password is correct */ {

        // save the userID in a session
        $_SESSION["userID"] = $userID;
        if ($remember) {
            remember_me($conn, $userID);
        }
        echo json_encode(array("message" => "Access granted!", "code" => 200));

    } else {

        die(json_encode(array('message' => 'Wrong password', 'code' => 401)));


    }

}

function get_google_tokens($code)
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

function oauth_tokeninfo_call($id_token)
{

    // Google OAuth 2.0 tokeninfo endpoint URL
    $tokenInfoUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($id_token);

    // Make a GET request to the tokeninfo endpoint
    $response = file_get_contents($tokenInfoUrl);

    return $response;
}

function add_google_user($conn, $sub)
{

    // insert the user in the db
    $stmt = $conn->prepare("INSERT INTO users (sub) VALUES (?)");
    $stmt->bind_param("s", $sub);
    $stmt->execute();

}

function get_google_userID($conn, $sub)
{

    // get the userID of the current user from the db using the sub key
    $stmt = $conn->prepare("SELECT userID FROM users WHERE sub=?");
    $stmt->bind_param("s", $sub);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();


    return isset($result["userID"]) ? $result["userID"] : false;

}

//get the json data sent (we have to retrieve it this way since it's sent with ajax and not with a regular form, so $_POST[] doesn't work)
$json_data = file_get_contents("php://input");

// decode the json data into an associative array
$arr = json_decode($json_data, true);


if (isset($arr["color"], $arr["name"])) /* if a folder is being added */ {

    add_folder($conn, $arr["name"], $arr["color"], $_SESSION["userID"]);

    echo json_encode(array("message" => "Folder created!", "code" => 200));


} else if (isset($arr["title"], $arr["folderID"])) /* if a note is being added */ {

    add_note($conn, $arr["title"], $arr["folderID"]);

    echo json_encode(array("message" => "Note created!", "code" => 200));


} else if (isset($arr["action"]) && $arr["action"] === "signup") {

    if (username_exists($conn, $arr["username"])) /* if there is already a record with this username */ {

        die(json_encode(array('message' => 'This username is already in use', 'code' => 0)));

    } else /* if the username is valid */ {

        signup($conn, $arr["username"], $arr["password"], $arr["remember"]);

        echo json_encode(array("message" => "Signed up!", 'code' => 200));


    }

} else if (isset($arr["action"]) && $arr["action"] === "login") {

    $result = username_exists($conn, $arr["username"]);
    if (isset($result["password"], $result["userID"])) /* if a user with this username exists */ {

        login($conn, $result["userID"], $arr["password"], $result["password"], $arr["remember"]);

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
    $userID = get_google_userID($conn, $tokenInfoDecoded->sub);

    if (!$userID) /* if the user isn't already in the db */ {

        // add the user to the db
        add_google_user($conn, $tokenInfoDecoded->sub);

        // get its ID
        $userID = get_google_userID($conn, $tokenInfoDecoded->sub);


        // create a default "General" folder
        add_folder($conn, "General", "black", $userID);

    }

    // save the userID in a session variable
    $_SESSION["userID"] = $userID;

    echo json_encode(array("message" => "Access granted!", "code" => 200));

}

