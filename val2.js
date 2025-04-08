document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("loginForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");

    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");

    // Email Validation
    emailInput.addEventListener("input", function () {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailInput.value)) {
            emailError.textContent = "Please enter a valid email address.";
        } else {
            emailError.textContent = "";
        }
    });

    // Password Validation
    passwordInput.addEventListener("input", function () {
        if (passwordInput.value.trim().length === 0) {
            passwordError.textContent = "Password cannot be empty.";
        } else {
            passwordError.textContent = "";
        }
    });

    // Form Validation before submission
    form.addEventListener("submit", function (event) {
        let valid = true;

        if (!emailInput.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            emailError.textContent = "Please enter a valid email address.";
            valid = false;
        }

        if (passwordInput.value.trim().length === 0) {
            passwordError.textContent = "Password cannot be empty.";
            valid = false;
        }

        if (!valid) {
            event.preventDefault(); // Stop form submission
        }
    });
});