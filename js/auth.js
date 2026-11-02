document.addEventListener("DOMContentLoaded", function () {

    initializeGoogle();
    initializeRegistration();

});


/* ==================================================
   GOOGLE SIGN IN
================================================== */

function initializeGoogle() {

    console.log("Initializing Google Sign-In...");

    const googleButton =
        document.getElementById("google-button");

    if (!googleButton) {

        console.error(
            "google-button element was not found."
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | GOOGLE CLIENT ID
    |--------------------------------------------------------------------------
    */

    const GOOGLE_CLIENT_ID =
        "187666137326-uuarlak2nv6nles24hje5qc2u82pta9f.apps.googleusercontent.com";


    /*
    |--------------------------------------------------------------------------
    | CHECK GOOGLE LIBRARY
    |--------------------------------------------------------------------------
    */

    if (
        typeof google === "undefined" ||
        !google.accounts ||
        !google.accounts.id
    ) {

        console.error(
            "Google Identity Services is not loaded."
        );

        googleButton.innerHTML = `
            <div class="google-error">
                Google Sign-In could not be loaded.
                Please refresh the page.
            </div>
        `;

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | INITIALIZE GOOGLE
    |--------------------------------------------------------------------------
    */

    google.accounts.id.initialize({

        client_id:
            GOOGLE_CLIENT_ID,

        callback:
            handleGoogleResponse,

        auto_select:
            false,

        cancel_on_tap_outside:
            true

    });


    /*
    |--------------------------------------------------------------------------
    | RENDER GOOGLE BUTTON
    |--------------------------------------------------------------------------
    */

    google.accounts.id.renderButton(

        googleButton,

        {

            theme:
                "outline",

            size:
                "large",

            width:
                400,

            text:
                "signup_with",

            shape:
                "rectangular",

            logo_alignment:
                "left"

        }

    );


    console.log(
        "Google Sign-In initialized successfully."
    );

}


/* ==================================================
   GOOGLE RESPONSE
================================================== */

function handleGoogleResponse(response) {

    console.log(
        "Google response received."
    );


    if (
        !response ||
        !response.credential
    ) {

        showMessage(
            "Google authentication failed.",
            "error"
        );

        return;
    }


    showMessage(
        "Connecting to Google...",
        "success"
    );


    const formData =
        new URLSearchParams();


    formData.append(
        "credential",
        response.credential
    );


    fetch(
        "google-login.php",
        {

            method:
                "POST",

            headers: {

                "Content-Type":
                    "application/x-www-form-urlencoded"

            },

            body:
                formData.toString()

        }
    )


    .then(function (response) {

        if (!response.ok) {

            throw new Error(
                "HTTP error: " +
                response.status
            );

        }

        return response.json();

    })


    .then(function (data) {

        console.log(
            "Server response:",
            data
        );


        if (data.success) {

            showMessage(
                "Registration successful. Redirecting...",
                "success"
            );


            setTimeout(
                function () {

                    window.location.href =
                        "dashboard.php";

                },
                500
            );

        }

        else {

            showMessage(
                data.message ||
                "Google authentication failed.",
                "error"
            );

        }

    })


    .catch(function (error) {

        console.error(
            "Google authentication error:",
            error
        );


        showMessage(
            "Unable to connect to the server.",
            "error"
        );

    });

}


/* ==================================================
   NORMAL REGISTRATION
================================================== */

function initializeRegistration() {

    const form =
        document.getElementById(
            "registerForm"
        );


    if (!form) {
        return;
    }


    form.addEventListener(
        "submit",
        function (event) {

            event.preventDefault();


            const name =
                document.getElementById(
                    "name"
                ).value.trim();


            const email =
                document.getElementById(
                    "email"
                ).value.trim();


            const password =
                document.getElementById(
                    "password"
                ).value;


            const terms =
                document.getElementById(
                    "terms"
                ).checked;


            if (!name) {

                showMessage(
                    "Please enter your full name.",
                    "error"
                );

                return;
            }


            if (!validateEmail(email)) {

                showMessage(
                    "Please enter a valid email address.",
                    "error"
                );

                return;
            }


            if (password.length < 8) {

                showMessage(
                    "Password must contain at least 8 characters.",
                    "error"
                );

                return;
            }


            if (!terms) {

                showMessage(
                    "Please accept the Terms of Service.",
                    "error"
                );

                return;
            }


            registerUser(
                name,
                email,
                password
            );

        }
    );

}


/* ==================================================
   NORMAL REGISTER
================================================== */

function registerUser(
    name,
    email,
    password
) {

    const button =
        document.getElementById(
            "registerBtn"
        );


    if (!button) {
        return;
    }


    button.disabled = true;


    const buttonText =
        button.querySelector(
            "span"
        );


    if (buttonText) {

        buttonText.textContent =
            "Creating account...";

    }


    const formData =
        new FormData();


    formData.append(
        "name",
        name
    );


    formData.append(
        "email",
        email
    );


    formData.append(
        "password",
        password
    );


    fetch(
        "register-user.php",
        {

            method:
                "POST",

            body:
                formData

        }
    )


    .then(function (response) {

        if (!response.ok) {

            throw new Error(
                "HTTP error: " +
                response.status
            );

        }

        return response.json();

    })


    .then(function (data) {

        if (data.success) {

            showMessage(
                "Account created successfully.",
                "success"
            );


            setTimeout(
                function () {

                    window.location.href =
                        "dashboard.php";

                },
                500
            );

        }

        else {

            showMessage(
                data.message ||
                "Unable to create account.",
                "error"
            );


            resetRegisterButton();

        }

    })


    .catch(function (error) {

        console.error(
            "Registration error:",
            error
        );


        showMessage(
            "Something went wrong. Please try again.",
            "error"
        );


        resetRegisterButton();

    });

}


/* ==================================================
   PASSWORD TOGGLE
================================================== */

function togglePassword() {

    const password =
        document.getElementById(
            "password"
        );


    const button =
        document.querySelector(
            ".password-toggle"
        );


    if (!password || !button) {
        return;
    }


    if (
        password.type ===
        "password"
    ) {

        password.type =
            "text";

        button.textContent =
            "Hide";

    }

    else {

        password.type =
            "password";

        button.textContent =
            "Show";

    }

}


/* ==================================================
   RESET BUTTON
================================================== */

function resetRegisterButton() {

    const button =
        document.getElementById(
            "registerBtn"
        );


    if (!button) {
        return;
    }


    button.disabled =
        false;


    const buttonText =
        button.querySelector(
            "span"
        );


    if (buttonText) {

        buttonText.textContent =
            "Create account";

    }

}


/* ==================================================
   EMAIL VALIDATION
================================================== */

function validateEmail(email) {

    const regex =
        /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    return regex.test(email);

}


/* ==================================================
   MESSAGE
================================================== */

function showMessage(
    message,
    type
) {

    const element =
        document.getElementById(
            "auth-message"
        );


    if (!element) {
        return;
    }


    element.textContent =
        message;


    element.className =
        "auth-message " +
        type;

}