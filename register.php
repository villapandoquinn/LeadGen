<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
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

    <title>Register — ProspectHunt</title>

    <link
        rel="stylesheet"
        href="css/auth.css">

</head>

<body>

<div class="auth-page">

    <!-- ======================================
         LEFT BRAND PANEL
    ======================================= -->

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
                    Discover qualified leads, automate your
                    outreach, and manage every conversation
                    from one powerful platform.
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
                                Find and qualify potential customers.
                            </small>
                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            ✓
                        </div>

                        <div>
                            <strong>
                                Campaign Management
                            </strong>

                            <small>
                                Organize and track your campaigns.
                            </small>
                        </div>

                    </div>


                    <div class="feature">

                        <div class="feature-icon">
                            ✓
                        </div>

                        <div>
                            <strong>
                                Automated Outreach
                            </strong>

                            <small>
                                Reach prospects with smarter follow-ups.
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
                                Manage prospect communication in one place.
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

            <span>
                Lead Generation Platform
            </span>

        </div>

    </section>


    <!-- ======================================
         REGISTER PANEL
    ======================================= -->

    <section class="form-panel">

        <div class="form-container">


            <!-- MOBILE LOGO -->

            <div class="mobile-logo">

                <div class="logo-mark">
                    P
                </div>

                <span>
                    ProspectHunt
                </span>

            </div>


            <!-- HEADER -->

            <div class="form-header">

                <h2>
                    Create your account
                </h2>

                <p>
                    Start generating and managing leads today.
                </p>

            </div>


            <!-- =================================
                 GOOGLE REGISTER
            ================================== -->

            <div
                id="google-button"
                class="google-container">
            </div>


            <!-- DIVIDER -->

            <div class="divider">

                <span>
                    OR
                </span>

            </div>


            <!-- =================================
                 NORMAL REGISTER
            ================================== -->

            <form id="registerForm">

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" placeholder="John Doe" required>
                </div>

                <div class="form-group">
                    <label for="email">Work Email</label>
                    <input type="email" id="email" name="email" placeholder="you@company.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="At least 8 characters" minlength="8" required>
                </div>

                <div class="terms" style="margin-bottom: 20px;">
                    <input type="checkbox" id="terms" name="terms" required style="margin-right: 8px;">
                    <label for="terms" style="font-size: 13px; color: #555;">
                        I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" id="registerBtn" class="register-btn">
                    <span>Create account</span>
                </button>

            </form>

            <div
                id="auth-message"
                class="auth-message">
            </div>


            <!-- LOGIN -->

            <div class="login-link">

                Already have an account?

                <a href="index.php">
                    Sign in
                </a>

            </div>


            <!-- SECURITY -->

            <div class="security-note">

                <span>🔒</span>

                Your information is securely protected.

            </div>


        </div>

    </section>

</div>


<!-- =========================================
     GOOGLE IDENTITY SERVICES
========================================== -->

<script src="https://accounts.google.com/gsi/client"></script>
<script src="js/auth.js"></script>


</body>
</html>