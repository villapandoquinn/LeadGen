<?php

/*
|--------------------------------------------------------------------------
| GOOGLE LOGIN / REGISTRATION
|--------------------------------------------------------------------------
| Native PHP
| Google Identity Services
| MySQL / PDO
|--------------------------------------------------------------------------
*/

session_start();

header("Content-Type: application/json; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| ERROR HANDLING
|--------------------------------------------------------------------------
*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);

error_reporting(E_ALL);


/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    array $data = []
): void {

    echo json_encode(
        array_merge(
            [
                "success" => $success,
                "message" => $message
            ],
            $data
        ),
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD COMPOSER AUTOLOAD
|--------------------------------------------------------------------------
|
| Composer must have installed:
|
| composer require google/apiclient
|
|--------------------------------------------------------------------------
*/

$autoload = __DIR__ . "/vendor/autoload.php";

if (!file_exists($autoload)) {

    jsonResponse(
        false,
        "Google API library is not installed. Please run: composer require google/apiclient"
    );
}

require_once $autoload;

$aliasesFile = __DIR__ . '/vendor/google/apiclient/src/aliases.php';
if (file_exists($aliasesFile)) {
    require_once $aliasesFile;
}


/*
|--------------------------------------------------------------------------
| CHECK GOOGLE CLIENT
|--------------------------------------------------------------------------
*/

if (!class_exists("Google_Client")) {

    jsonResponse(
        false,
        "Google API Client could not be loaded. Please run: composer dump-autoload"
    );
}


/*
|--------------------------------------------------------------------------
| LOAD DATABASE
|--------------------------------------------------------------------------
*/

$database = __DIR__ . "/config/database.php";

if (!file_exists($database)) {

    jsonResponse(
        false,
        "Database configuration file was not found."
    );
}

require_once $database;


/*
|--------------------------------------------------------------------------
| GOOGLE CLIENT ID
|--------------------------------------------------------------------------
*/

$GOOGLE_CLIENT_ID =
    "187666137326-uuarlak2nv6nles24hje5qc2u82pta9f.apps.googleusercontent.com";


/*
|--------------------------------------------------------------------------
| GET GOOGLE CREDENTIAL
|--------------------------------------------------------------------------
|
| Google Identity Services sends:
|
| POST credential
|
|--------------------------------------------------------------------------
*/

$credential = $_POST["credential"] ?? "";


/*
|--------------------------------------------------------------------------
| VALIDATE CREDENTIAL
|--------------------------------------------------------------------------
*/

if (empty($credential)) {

    jsonResponse(
        false,
        "Google credential is missing."
    );
}


/*
|--------------------------------------------------------------------------
| CREATE GOOGLE CLIENT
|--------------------------------------------------------------------------
*/

try {

    $client = new Google_Client();

    $client->setClientId(
        $GOOGLE_CLIENT_ID
    );

} catch (Throwable $e) {

    error_log(
        "Google Client initialization error: " .
        $e->getMessage()
    );

    jsonResponse(
        false,
        "Unable to initialize Google authentication."
    );
}


/*
|--------------------------------------------------------------------------
| VERIFY GOOGLE ID TOKEN
|--------------------------------------------------------------------------
*/

try {

    $payload = $client->verifyIdToken(
        $credential
    );

} catch (Throwable $e) {

    error_log(
        "Google token verification error: " .
        $e->getMessage()
    );

    jsonResponse(
        false,
        "Google authentication failed."
    );
}


/*
|--------------------------------------------------------------------------
| CHECK PAYLOAD
|--------------------------------------------------------------------------
*/

if (!$payload) {

    jsonResponse(
        false,
        "Invalid or expired Google account token."
    );
}


/*
|--------------------------------------------------------------------------
| GET GOOGLE ACCOUNT DATA
|--------------------------------------------------------------------------
*/

$googleId =
    $payload["sub"] ?? null;

$name =
    $payload["name"] ?? "";

$email =
    $payload["email"] ?? "";

$picture =
    $payload["picture"] ?? null;

$emailVerified =
    $payload["email_verified"] ?? false;


/*
|--------------------------------------------------------------------------
| VALIDATE GOOGLE ACCOUNT
|--------------------------------------------------------------------------
*/

if (empty($googleId)) {

    jsonResponse(
        false,
        "Google account ID is missing."
    );
}


if (empty($email)) {

    jsonResponse(
        false,
        "Google account email is missing."
    );
}


if (!$emailVerified) {

    jsonResponse(
        false,
        "Your Google email address has not been verified."
    );
}


/*
|--------------------------------------------------------------------------
| NORMALIZE EMAIL
|--------------------------------------------------------------------------
*/

$email = strtolower(
    trim($email)
);


/*
|--------------------------------------------------------------------------
| CHECK DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

if (!isset($pdo)) {

    jsonResponse(
        false,
        "Database connection is not available."
    );
}


/*
|--------------------------------------------------------------------------
| FIND USER BY GOOGLE ID
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare(
        "
        SELECT *
        FROM users
        WHERE google_id = ?
        LIMIT 1
        "
    );

    $stmt->execute([
        $googleId
    ]);

    $user = $stmt->fetch(
        PDO::FETCH_ASSOC
    );

} catch (Throwable $e) {

    error_log(
        "Google user lookup error: " .
        $e->getMessage()
    );

    jsonResponse(
        false,
        "Unable to access the user database."
    );
}


/*
|--------------------------------------------------------------------------
| EXISTING GOOGLE USER
|--------------------------------------------------------------------------
*/

if ($user) {

    /*
    |--------------------------------------------------------------------------
    | CHECK ACCOUNT STATUS
    |--------------------------------------------------------------------------
    */

    if (
        isset($user["status"]) &&
        $user["status"] !== "active"
    ) {

        jsonResponse(
            false,
            "Your account is inactive. Please contact the administrator."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PROFILE INFORMATION
    |--------------------------------------------------------------------------
    |
    | Keep Google profile information updated.
    |
    */

    try {

        $stmt = $pdo->prepare(
            "
            UPDATE users
            SET
                name = ?,
                profile_picture = ?,
                auth_provider = 'google'
            WHERE id = ?
            "
        );

        $stmt->execute([
            $name,
            $picture,
            $user["id"]
        ]);

        /*
        |--------------------------------------------------------------------------
        | Update local user data
        |--------------------------------------------------------------------------
        */

        $user["name"] =
            $name;

        $user["profile_picture"] =
            $picture;

        $user["auth_provider"] =
            "google";

    } catch (Throwable $e) {

        error_log(
            "Google profile update error: " .
            $e->getMessage()
        );

        /*
        |--------------------------------------------------------------------------
        | Do not stop login if profile update fails.
        |--------------------------------------------------------------------------
        */
    }
}


/*
|--------------------------------------------------------------------------
| GOOGLE ID NOT FOUND
|--------------------------------------------------------------------------
|
| Search using email.
|
|--------------------------------------------------------------------------
*/

else {

    try {

        $stmt = $pdo->prepare(
            "
            SELECT *
            FROM users
            WHERE email = ?
            LIMIT 1
            "
        );

        $stmt->execute([
            $email
        ]);

        $existingUser =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

    } catch (Throwable $e) {

        error_log(
            "Email user lookup error: " .
            $e->getMessage()
        );

        jsonResponse(
            false,
            "Unable to check your existing account."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXISTING EMAIL ACCOUNT
    |--------------------------------------------------------------------------
    */

    if ($existingUser) {

        /*
        |--------------------------------------------------------------------------
        | CHECK STATUS
        |--------------------------------------------------------------------------
        */

        if (
            isset($existingUser["status"]) &&
            $existingUser["status"] !== "active"
        ) {

            jsonResponse(
                false,
                "Your account is inactive. Please contact the administrator."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LINK GOOGLE ACCOUNT
        |--------------------------------------------------------------------------
        */

        try {

            $stmt = $pdo->prepare(
                "
                UPDATE users
                SET
                    google_id = ?,
                    profile_picture = ?,
                    auth_provider = 'google'
                WHERE id = ?
                "
            );

            $stmt->execute([
                $googleId,
                $picture,
                $existingUser["id"]
            ]);

        } catch (Throwable $e) {

            error_log(
                "Google account linking error: " .
                $e->getMessage()
            );

            jsonResponse(
                false,
                "Unable to connect your Google account."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | USE EXISTING USER
        |--------------------------------------------------------------------------
        */

        $user =
            $existingUser;

        $user["google_id"] =
            $googleId;

        $user["profile_picture"] =
            $picture;

        $user["auth_provider"] =
            "google";
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE NEW GOOGLE USER
    |--------------------------------------------------------------------------
    */

    else {

        try {

            $stmt = $pdo->prepare(
                "
                INSERT INTO users
                (
                    google_id,
                    name,
                    email,
                    profile_picture,
                    auth_provider,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'google',
                    'active'
                )
                "
            );

            $stmt->execute([
                $googleId,
                $name,
                $email,
                $picture
            ]);

            /*
            |--------------------------------------------------------------------------
            | Get newly created ID
            |--------------------------------------------------------------------------
            */

            $userId =
                $pdo->lastInsertId();

        } catch (Throwable $e) {

            error_log(
                "Google user creation error: " .
                $e->getMessage()
            );

            jsonResponse(
                false,
                "Unable to create your account."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | GET NEW USER
        |--------------------------------------------------------------------------
        */

        try {

            $stmt = $pdo->prepare(
                "
                SELECT *
                FROM users
                WHERE id = ?
                LIMIT 1
                "
            );

            $stmt->execute([
                $userId
            ]);

            $user =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );

        } catch (Throwable $e) {

            error_log(
                "New Google user retrieval error: " .
                $e->getMessage()
            );

            jsonResponse(
                false,
                "Account was created but could not be loaded."
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| FINAL USER VALIDATION
|--------------------------------------------------------------------------
*/

if (!$user) {

    jsonResponse(
        false,
        "Unable to load your account."
    );
}


/*
|--------------------------------------------------------------------------
| CHECK USER STATUS
|--------------------------------------------------------------------------
*/

if (
    isset($user["status"]) &&
    $user["status"] !== "active"
) {

    jsonResponse(
        false,
        "Your account is inactive."
    );
}


/*
|--------------------------------------------------------------------------
| REGENERATE SESSION ID
|--------------------------------------------------------------------------
|
| Prevent session fixation.
|
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);


/*
|--------------------------------------------------------------------------
| CREATE SESSION
|--------------------------------------------------------------------------
*/

$_SESSION["user_id"] =
    $user["id"];

$_SESSION["user_name"] =
    $user["name"];

$_SESSION["user_email"] =
    $user["email"];

$_SESSION["profile_picture"] =
    $user["profile_picture"] ?? null;

$_SESSION["auth_provider"] =
    "google";


/*
|--------------------------------------------------------------------------
| OPTIONAL SESSION DATA
|--------------------------------------------------------------------------
*/

$_SESSION["logged_in"] =
    true;

try {
    require_once __DIR__ . '/services/WorkspaceService.php';
    $wsService = new WorkspaceService($pdo);
    $ws = $wsService->getCurrentWorkspace((int)$user["id"]);
    $_SESSION["current_workspace_id"] = (int)$ws["id"];
} catch (Throwable $e) {
    error_log("Google login workspace init error: " . $e->getMessage());
}


/*
|--------------------------------------------------------------------------
| SUCCESS RESPONSE
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    "Google authentication successful.",
    [
        "user" => [
            "id" =>
                $user["id"],

            "name" =>
                $user["name"],

            "email" =>
                $user["email"],

            "profile_picture" =>
                $user["profile_picture"] ?? null,

            "auth_provider" =>
                "google"
        ],

        "redirect" =>
            "dashboard.php"
    ]
);