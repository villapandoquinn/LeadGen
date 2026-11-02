<?php

session_start();

header(
    "Content-Type: application/json"
);

require_once __DIR__ .
    "/config/database.php";
require_once __DIR__ . "/services/RateLimiter.php";
require_once __DIR__ . "/services/WorkspaceService.php";

$rateCheck = RateLimiter::check('register', 5, 600);
if (!$rateCheck['allowed']) {
    echo json_encode(["success" => false, "message" => $rateCheck['message']]);
    exit;
}



/*
|--------------------------------------------------------------------------
| VALIDATE REQUEST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    echo json_encode([

        "success" => false,

        "message" =>
            "Invalid request."

    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET DATA
|--------------------------------------------------------------------------
*/

$name =
    trim($_POST['name'] ?? '');

$email =
    trim($_POST['email'] ?? '');

$password =
    $_POST['password'] ?? '';


/*
|--------------------------------------------------------------------------
| VALIDATION
|--------------------------------------------------------------------------
*/

if ($name === '') {

    echo json_encode([

        "success" => false,

        "message" =>
            "Full name is required."

    ]);

    exit;
}


if (!filter_var(
    $email,
    FILTER_VALIDATE_EMAIL
)) {

    echo json_encode([

        "success" => false,

        "message" =>
            "Please enter a valid email."

    ]);

    exit;
}


if (strlen($password) < 8) {

    echo json_encode([

        "success" => false,

        "message" =>
            "Password must contain at least 8 characters."

    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING EMAIL
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT id

    FROM users

    WHERE email = ?

    LIMIT 1

");

$stmt->execute([
    $email
]);


if ($stmt->fetch()) {

    echo json_encode([

        "success" => false,

        "message" =>
            "An account with this email already exists."

    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| HASH PASSWORD
|--------------------------------------------------------------------------
*/

$passwordHash =
    password_hash(
        $password,
        PASSWORD_DEFAULT
    );


/*
|--------------------------------------------------------------------------
| CREATE USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    INSERT INTO users (

        name,
        email,
        password_hash,
        auth_provider,
        status

    )

    VALUES (

        ?,
        ?,
        ?,
        'email',
        'active'

    )

");


$stmt->execute([

    $name,

    $email,

    $passwordHash

]);


/*
|--------------------------------------------------------------------------
| USER ID
|--------------------------------------------------------------------------
*/

$userId =
    $pdo->lastInsertId();


/*
|--------------------------------------------------------------------------
| CREATE SESSION
|--------------------------------------------------------------------------
*/

session_regenerate_id(true);


$_SESSION['user_id'] =
    $userId;

$_SESSION['user_name'] =
    $name;

$_SESSION['user_email'] =
    $email;

$_SESSION['profile_picture'] =
    null;

$_SESSION['auth_provider'] =
    "email";
try {
    $wsService = new WorkspaceService($pdo);
    $newWsId = $wsService->createWorkspace((int)$userId, $name . "'s Workspace", "Primary workspace");
    $_SESSION['current_workspace_id'] = $newWsId;
} catch (Throwable $e) {
    error_log("Failed to auto-create workspace for user {$userId}: " . $e->getMessage());
}



/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

echo json_encode([

    "success" => true,

    "message" =>
        "Account created successfully."

]);

exit;