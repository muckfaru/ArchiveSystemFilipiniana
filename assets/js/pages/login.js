/**
 * Login Page Logic
 */

// Toggle password visibility
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#password');

if (togglePassword) {
    togglePassword.addEventListener('click', function (e) {
        // toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        // toggle the eye slash icon
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash-fill');
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
