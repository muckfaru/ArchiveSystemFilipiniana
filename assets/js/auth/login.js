/**
 * Login Page Logic
 */

const loginView = document.getElementById('loginView');
const forgotView = document.getElementById('forgotView');
const showForgotPassword = document.getElementById('showForgotPassword');
const backToLogin = document.getElementById('backToLogin');

if (showForgotPassword && loginView && forgotView) {
    showForgotPassword.addEventListener('click', function () {
        window.location.href = `${APP_URL}/?admin=forgot`;
    });
}

if (backToLogin && loginView && forgotView) {
    backToLogin.addEventListener('click', function () {
        forgotView.style.display = 'none';
        loginView.style.display = 'block';
    });
}

// Toggle password visibility
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

if (togglePassword) {
    togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        
        const icon = this.querySelector('i');
        if (icon) {
            if (type === 'text') {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash-fill');
            } else {
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye');
            }
        }
    });
}

// Handle Login Submission
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const btn = this.querySelector('.login-btn');
        const spinner = btn.querySelector('.spinner-border');
        const btnText = btn.querySelector('.btn-text');
        const alertContainer = document.getElementById('alert-container');

        // UI Loading State
        btn.disabled = true;
        spinner.classList.remove('d-none');
        btnText.textContent = 'LOGGING IN...';
        alertContainer.innerHTML = '';

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch(`${APP_URL}/backend/api/auth/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (response.ok && result.status === 'success') {
                // Success
                window.location.href = result.redirect;
            } else {
                // Error
                throw new Error(result.message || 'Login failed');
            }
        } catch (error) {
            // Show Error
            alertContainer.innerHTML = `
                <div class="error-alert">
                    <i class="bi bi-exclamation-circle-fill"></i> ${error.message}
                </div>
            `;

            // Reset UI
            btn.disabled = false;
            spinner.classList.add('d-none');
            btnText.textContent = 'LOGIN';
        }
    });
}

const forgotPasswordForm = document.getElementById('forgotPasswordForm');
if (forgotPasswordForm) {
    forgotPasswordForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        const submitBtn = document.getElementById('forgotSubmitBtn');
        const spinner = document.getElementById('forgotSpinner');
        const btnText = document.getElementById('forgotBtnText');
        const alertContainer = document.getElementById('forgot-alert-container');

        submitBtn.disabled = true;
        spinner.classList.remove('d-none');
        btnText.textContent = 'Sending...';
        alertContainer.innerHTML = '';

        try {
            const formData = new FormData(this);
            const response = await fetch(`${APP_URL}/forgot-password`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to send reset email.');
            }

            alertContainer.innerHTML = `
                <div class="success-alert">
                    <i class="bi bi-check-circle-fill"></i> ${result.message}
                </div>
            `;
            this.reset();
        } catch (error) {
            alertContainer.innerHTML = `
                <div class="error-alert">
                    <i class="bi bi-exclamation-circle-fill"></i> ${error.message}
                </div>
            `;
        } finally {
            submitBtn.disabled = false;
            spinner.classList.add('d-none');
            btnText.textContent = 'Send Reset Link';
        }
    });
}
