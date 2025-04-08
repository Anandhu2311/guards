
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("signupForm");
    const emailInput = document.getElementById("email");
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirm-password");

    const emailError = document.getElementById("emailError");
    const passwordError = document.getElementById("passwordError");
    const confirmPasswordError = document.getElementById("confirmPasswordError");

    // Email Validation
    emailInput.addEventListener("input", function () {
        const email = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(email)) {
            emailError.textContent = "Please enter a valid email address.";
            return;
        } else {
            emailError.textContent = ""; // Clear error if format is correct
        }

        // Check if email exists in the database using AJAX
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "check_email.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                if (xhr.responseText === "exists") {
                    emailError.textContent = "This email is already registered.";
                } else {
                    emailError.textContent = "";
                }
            }
        };
        xhr.send("email=" + encodeURIComponent(email));
    });

    // Password Validation
    passwordInput.addEventListener("input", function () {
        const password = passwordInput.value;
        const minLength = 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasNumber = /\d/.test(password);

        if (password.length < minLength) {
            passwordError.textContent = "Password must be at least 8 characters long.";
        } else if (!hasUppercase) {
            passwordError.textContent = "Password must contain at least one uppercase letter.";
        } else if (!hasNumber) {
            passwordError.textContent = "Password must contain at least one number.";
        } else {
            passwordError.textContent = "";
        }
    });

    // Confirm Password Validation
    confirmPasswordInput.addEventListener("input", function () {
        if (confirmPasswordInput.value !== passwordInput.value) {
            confirmPasswordError.textContent = "Passwords do not match.";
        } else {
            confirmPasswordError.textContent = "";
        }
    });

    // Prevent form submission if there are errors
    form.addEventListener("submit", function (event) {
        if (emailError.textContent || passwordError.textContent || confirmPasswordError.textContent) {
            event.preventDefault(); // Stop form submission
            alert("Please fix the errors before submitting.");
        }
    });
});
