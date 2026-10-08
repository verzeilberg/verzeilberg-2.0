/* Show/hide a password: <button class="toggle-password" data-target="#field-id"><i class="fas fa-eye"></i></button> */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toggle-password').forEach(function (button) {
        button.addEventListener('click', function () {
            const target = document.querySelector(this.dataset.target);
            if (!target) {
                return;
            }
            const isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye', isPassword);
            icon.classList.toggle('fa-eye-slash', !isPassword);
        });
    });
});
