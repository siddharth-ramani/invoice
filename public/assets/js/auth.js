(() => {
    const t = window.bizT || (key => key);
    document.querySelectorAll('[data-password-toggle]').forEach(toggle => {
        const input = document.getElementById(toggle.dataset.passwordTarget || 'password');
        if (!input) return;
        toggle.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(reveal));
            toggle.setAttribute('aria-label', t(reveal ? 'Hide password' : 'Show password'));
        });
    });
    document.querySelectorAll('.auth-form').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            const submit = form.querySelector('button[type="submit"]');
            if (submit) submit.disabled = true;
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('.auth-form button[type="submit"]').forEach(button => { button.disabled = false; });
    });
})();
