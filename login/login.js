document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorMessage = document.querySelector('.error-message');
            
            // Simple validation
            if (username.trim() === '' || password.trim() === '') {
                e.preventDefault();
                if (!errorMessage) {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.textContent = 'Please enter both username and password';
                    loginForm.insertBefore(errorDiv, document.querySelector('.btn-login'));
                } else {
                    errorMessage.textContent = 'Please enter both username and password';
                }
                return;
            }
        });
    }
});