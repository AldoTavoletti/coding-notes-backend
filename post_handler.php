<?php


function add_folder($conn, $name, $color, $userID)
{

    //prepare the statement
    $stmt = $conn->prepare("INSERT INTO folders (folderName, color, userID) VALUES (?,?,?)");

    // bind the parameters
    $stmt->bind_param("ssi", $name, $color, $userID);

    // execute the query
    $stmt->execute();

}

function add_note($conn, $title, $folderID)
{

    //prepare the statement
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

function signup($conn, $username, $password)
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

function login($userID, $password, $passwordDB)
{

    if (password_verify($password, $passwordDB)) /* if the password is correct */ {

        // save the userID in a session
        $_SESSION["userID"] = $userID;

        echo json_encode(array("message" => "Access granted!", "code" => 200));

    } else {

        die(json_encode(array('message' => 'Wrong password', 'code' => 401)));


    }

}

function get_google_tokens($code)
{

    // set all the necessary data
    $client_id = '225902902685-nfk9t53m1894vf4rmi4jj3fpp3o913cp.apps.googleusercontent.com';
    $client_secret = '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';
    $redirect_uri = 'http://localhost:3000';
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


} else if (isset($arr["username"], $arr["password"]) && $arr["action"] === "signup") {

    if (username_exists($conn, $arr["username"])) /* if there is already a record with this username */ {

        die(json_encode(array('message' => 'This username is already in use', 'code' => 0)));

    } else /* if the username is valid */ {

        signup($conn, $arr["username"], $arr["password"]);

        echo json_encode(array("message" => "Signed up!", 'code' => 200));


    }

} else if (isset($arr["username"], $arr["password"]) && $arr["action"] === "login") {

    $result = username_exists($conn, $arr["username"]);
    if (isset($result["password"], $result["userID"])) /* if a user with this username exists */ {

        login($result["userID"], $arr["password"], $result["password"]);

    } else {

        die(json_encode(array('message' => 'Not existing username', 'code' => 401)));


    }

} else if (isset($arr["code"])) /* google login */ {

    // get the tokens (I actually only need the id_token)
    $tokens = get_google_tokens($arr["code"]);

    // decode tokens
    $tokensDecoded = json_decode($tokens);
            echo var_dump($tokensDecoded);


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
