document.addEventListener("DOMContentLoaded", function () {
    // Show / Hide password
    const toggleButtons = document.querySelectorAll(".toggle-password");

    toggleButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            const targetId = button.getAttribute("data-target");
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            if (input.type === "password") {
                input.type = "text";
                button.textContent = "Hide";
            } else {
                input.type = "password";
                button.textContent = "Show";
            }
        });
    });

    // Instructor extra fields show/hide
    const roleSelect = document.getElementById("role");
    const instructorFields = document.getElementById("instructorFields");

    if (roleSelect && instructorFields) {
        roleSelect.addEventListener("change", function () {
            if (roleSelect.value === "instructor") {
                instructorFields.style.display = "block";
            } else {
                instructorFields.style.display = "none";
            }
        });
    }

    // Phone number only digits
    const phoneInput = document.getElementById("phone");

    if (phoneInput) {
        phoneInput.addEventListener("input", function () {
            phoneInput.value = phoneInput.value.replace(/\D/g, "");
        });
    }

    // Full name only allowed characters
    const fullNameInput = document.getElementById("full_name");

    if (fullNameInput) {
        fullNameInput.addEventListener("input", function () {
            fullNameInput.value = fullNameInput.value.replace(/[^a-zA-Z\s.'-]/g, "");
        });
    }

    // Email live validation
    const emailInput = document.getElementById("email");
    const emailFeedback = document.getElementById("emailFeedback");

    if (emailInput && emailFeedback) {
        emailInput.addEventListener("input", function () {
            const value = emailInput.value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (value === "") {
                emailFeedback.textContent = "";
                emailFeedback.style.color = "";
            } else if (emailPattern.test(value)) {
                emailFeedback.textContent = "Valid email format";
                emailFeedback.style.color = "green";
            } else {
                emailFeedback.textContent = "Invalid email format";
                emailFeedback.style.color = "red";
            }
        });
    }

    // Register form password validation
    const registerForm = document.getElementById("registerForm");

    if (registerForm) {
        registerForm.addEventListener("submit", function (event) {
            const password = document.getElementById("password");
            const confirmPassword = document.getElementById("confirm_password");

            const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/;

            if (password && !passwordPattern.test(password.value)) {
                event.preventDefault();
                alert("Password must be at least 8 characters and include uppercase, lowercase, number, and special character.");
                return;
            }

            if (password && confirmPassword && password.value !== confirmPassword.value) {
                event.preventDefault();
                alert("Passwords do not match.");
                return;
            }

            if (roleSelect && roleSelect.value === "instructor") {
                const identityDocument = document.getElementById("identity_document");
                const profileImage = document.getElementById("profile_image");

                if (identityDocument && !identityDocument.files.length) {
                    event.preventDefault();
                    alert("Identity document image is required for instructor registration.");
                    return;
                }

                if (profileImage && !profileImage.files.length) {
                    event.preventDefault();
                    alert("Personal photo is required for instructor registration.");
                    return;
                }
            }
        });
    }
});