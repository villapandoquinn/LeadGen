<?php

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/services/RateLimiter.php";

$rateCheck = RateLimiter::check('login', 5, 300);
if (!$rateCheck['allowed']) {
    die($rateCheck['message']);
}

require_csrf();


$email =
    trim($_POST['email'] ?? '');

$password =
    $_POST['password'] ?? '';


if (
    !$email ||
    !$password
) {

    die(
        "Email and password are required."
    );

}


/*
|--------------------------------------------------------------------------
| FIND USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT *

    FROM users

    WHERE email = ?

    LIMIT 1

");


$stmt->execute([
    $email
]);


$user =
    $stmt->fetch();


/*
|--------------------------------------------------------------------------
| VERIFY
|--------------------------------------------------------------------------
*/

if (
    !$user ||
    !$user['password_hash'] ||
    !password_verify(
        $password,
        $user['password_hash']
    )
) {

    die(
        "Invalid email or password."
    );

}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

if (
    $user['status'] !== 'active'
) {

    die(
        "Your account is inactive."
    );

}


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/
RateLimiter::reset('login');


session_regenerate_id(true);


$_SESSION['user_id'] =
    $user['id'];

$_SESSION['user_name'] =
    $user['name'];

$_SESSION['user_email'] =
    $user['email'];

$_SESSION['profile_picture'] =
    $user['profile_picture'];

$_SESSION['auth_provider'] =
    $user['auth_provider'];


/*
|--------------------------------------------------------------------------
| REDIRECT
|--------------------------------------------------------------------------
*/

header(
    "Location: dashboard.php"
);

exit;