/**
 * Forgot Password Page Logic
 */

document.addEventListener('DOMContentLoaded', function () {
    // Show Success Modal if triggered by PHP
    if (typeof showSuccessModal !== 'undefined' && showSuccessModal) {
        new bootstrap.Modal(document.getElementById('successModal')).show();
    }

    // Show Error Modal if triggered by PHP
    if (typeof showErrorModal !== 'undefined' && showErrorModal) {
        new bootstrap.Modal(document.getElementById('errorModal')).show();
    }
});
