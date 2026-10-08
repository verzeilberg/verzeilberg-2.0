/*
 * Password strength meter: #password-input, #password-strength-bar (gets .strength-0 … .strength-4)
 * and #password-requirements .requirement[data-req="length|uppercase|lowercase|number|special"].
 */
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password-input');
    const strengthBar = document.getElementById('password-strength-bar');
    const requirements = document.querySelectorAll('#password-requirements .requirement');
    if (!passwordInput || !strengthBar) {
        return;
    }

    passwordInput.addEventListener('input', function () {
        const password = this.value;
        const checks = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[^A-Za-z0-9]/.test(password)
        };

        requirements.forEach(function (requirement) {
            if (checks[requirement.dataset.req]) {
                requirement.classList.remove('unmet');
                requirement.classList.add('met');
                requirement.innerHTML = '<i class="fas fa-check me-1"></i> ' + requirement.textContent.replace(/^./, '').trim();
            } else {
                requirement.classList.remove('met');
                requirement.classList.add('unmet');
            }
        });

        strengthBar.className = 'password-strength-bar strength-' + Object.values(checks).filter(Boolean).length;
    });
});
