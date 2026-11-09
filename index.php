<?php

session_start();
require_once __DIR__ . '/includes/csrf.php';


if (isset($_SESSION['user_id'])) {

    header(
        "Location: dashboard.php"
    );

    exit;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>
  <link rel="shortcut icon" type="image/x-icon" href="assets/img/Logo/primovativelogo.png">

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Sign In — ProspectHunt
    </title>

    <link
        rel="stylesheet"
        href="css/auth.css">

</head>


<body>

<div class="auth-page">


    <!-- BRAND -->

    <section class="brand-panel">

        <div class="brand-inner">

            <div class="brand-logo">

                <div class="logo-mark">
                    P
                </div>

                <span>
                    ProspectHunt
                </span>

            </div>


            <div class="brand-content">

                <div class="eyebrow">
                    LEAD GENERATION PLATFORM
                </div>

                <h1>
                    Find your next
                    <span>customer.</span>
                </h1>

                <p>
                    Everything you need to discover prospects,
                    run campaigns, automate outreach and manage
                    conversations.
                </p>


                <div class="features">

                    <div class="feature">

                        <div class="feature-icon">
                            ✓
                        </div>

                        <div>

                            <strong>
                                Lead Discovery
                            </strong>

                            <small>
                                Discover new opportunities.
                            </small>

                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            ✓
                        </div>

                        <div>

                            <strong>
                                Smart Outreach
                            </strong>

                            <small>
                                Engage prospects automatically.
                            </small>

                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            ✓
                        </div>

                        <div>

                            <strong>
                                Conversations
                            </strong>

                            <small>
                                Keep every conversation organized.
                            </small>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <div class="brand-footer">

            <span>
                ProspectHunt &mdash; Powered by Primovative Co.
            </span>

        </div>

    </section>


    <!-- FORM -->

    <section class="form-panel">

        <div class="form-container">


            <div class="mobile-logo">

                <div class="logo-mark">
                    P
                </div>

                <span>
                    ProspectHunt
                </span>

            </div>


            <div class="form-header">

                <h2>
                    Welcome back
                </h2>

                <p>
                    Sign in to continue to your dashboard.
                </p>

            </div>


            <!-- GOOGLE -->

            <div
                id="google-button"
                class="google-container">
            </div>


            <div class="divider">

                <span>
                    OR
                </span>

            </div>


            <!-- EMAIL LOGIN -->

            <form
                method="POST"
                action="login-user.php">

                <?= csrf_field() ?>


                <div class="form-group">

                    <label>
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        placeholder="you@company.com"
                        required>

                </div>


                <div class="form-group">

                    <label>
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Your password"
                        required>

                </div>


                <button
                    type="submit"
                    class="register-btn">

                    Sign in

                </button>


            </form>


            <div class="login-link">

                Don't have an account?

                <a href="register.php">
                    Create account
                </a>

            </div>


        </div>

    </section>

</div>


<script
    src="https://accounts.google.com/gsi/client"
    async
    defer>
</script>


<script>

window.onload = function () {

    google.accounts.id.initialize({

        client_id:
            "187666137326-uuarlak2nv6nles24hje5qc2u82pta9f.apps.googleusercontent.com",

        callback:
            function(response) {

                fetch(
                    "google-login.php",
                    {

                        method: "POST",

                        headers: {
                            "Content-Type":
                                "application/x-www-form-urlencoded"
                        },

                        body:
                            "credential=" +
                            encodeURIComponent(
                                response.credential
                            )

                    }
                )

                .then(
                    response =>
                        response.json()
                )

                .then(
                    data => {

                        if (data.success) {

                            window.location.href =
                                "dashboard.php";

                        }

                        else {

                            alert(
                                data.message
                            );

                        }

                    }
                );

            }

    });


    google.accounts.id.renderButton(

        document.getElementById(
            "google-button"
        ),

        {

            theme: "outline",

            size: "large",

            width: 440,

            text: "signin_with"

        }

    );

};

</script>

</body>

</html>