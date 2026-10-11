const formulario = document.getElementById('form-registro');

if (formulario) {
    const password = document.getElementById('password');
    const repetirPassword = document.getElementById('repetir_password');
    const mensaje = document.getElementById('error-password');

    function comprobarPasswords() {
        if (repetirPassword.value === '') {
            repetirPassword.setCustomValidity('');
            mensaje.textContent = '';
            return;
        }

        if (password.value !== repetirPassword.value) {
            repetirPassword.setCustomValidity(
                'Las contraseñas no coinciden.'
            );
            mensaje.textContent = 'Las contraseñas no coinciden.';
        } else {
            repetirPassword.setCustomValidity('');
            mensaje.textContent = '';
        }
    }

    password.addEventListener('input', comprobarPasswords);
    repetirPassword.addEventListener('input', comprobarPasswords);

    formulario.addEventListener('submit', function (event) {
        comprobarPasswords();

        if (!formulario.checkValidity()) {
            event.preventDefault();
            formulario.reportValidity();
        }
    });
}