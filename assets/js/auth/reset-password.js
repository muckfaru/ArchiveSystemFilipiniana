/**
 * Reset Password Page Logic
 */

function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Show Success Modal if triggered by PHP
    if (typeof showSuccessModal !== 'undefined' && showSuccessModal) {
        new bootstrap.Modal(document.getElementById('successModal')).show();
    }

    // Show Error Modal if triggered by PHP
    if (typeof showErrorModal !== 'undefined' && showErrorModal) {
        new bootstrap.Modal(document.getElementById('errorModal')).show();
    }

    // Real-time Validation
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const submitBtn = document.querySelector('.submit-btn');
    const passwordFeedback = document.getElementById('password-feedback');
    const confirmFeedback = document.getElementById('confirm-password-feedback');

    function validatePasswords() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        let isValid = true;

        // Reset states
        passwordInput.classList.remove('is-invalid', 'is-valid');
        confirmInput.classList.remove('is-invalid', 'is-valid');
        passwordFeedback.textContent = '';
        passwordFeedback.className = 'validation-message';
        confirmFeedback.textContent = '';
        confirmFeedback.className = 'validation-message';

        // Validate Password Length/Strength (Basic)
        if (password.length > 0) {
            if (password.length < 6) {
                passwordInput.classList.add('is-invalid');
                passwordFeedback.textContent = 'Password must be at least 6 characters.';
                passwordFeedback.classList.add('error');
                isValid = false;
            } else {
                // passwordInput.classList.add('is-valid'); // Optional: show green for valid length?
            }
        } else {
            isValid = false; // Empty is not valid for submission
        }

        // Validate Match
        if (confirm.length > 0) {
            if (password !== confirm) {
                confirmInput.classList.add('is-invalid');
                confirmFeedback.textContent = 'Passwords do not match.';
                confirmFeedback.classList.add('error');
                isValid = false;
            } else if (password.length >= 6) {
                confirmInput.classList.add('is-valid');
                confirmFeedback.textContent = 'Passwords match.';
                confirmFeedback.classList.add('success');
            }
        } else {
            if (password.length >= 6) isValid = false; // Confirm is empty
        }

        // Check if both are empty (initially) or invalid
        if (password.length < 6 || confirm.length < 6 || password !== confirm) {
            isValid = false;
        }

        submitBtn.disabled = !isValid;
    }

    if (passwordInput && confirmInput) {
        // Initial check
        submitBtn.disabled = true;

        passwordInput.addEventListener('input', validatePasswords);
        confirmInput.addEventListener('input', validatePasswords);
    }
});
