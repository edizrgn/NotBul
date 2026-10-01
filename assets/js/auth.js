(() => {
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.getAttribute('aria-controls'));
        if (!input) return;
        const showLabel = button.getAttribute('aria-label') || 'Şifreyi göster';
        const hideLabel = showLabel.replace(/göster$/, 'gizle');

        button.hidden = false;
        button.classList.remove('d-none');
        button.addEventListener('click', () => {
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? hideLabel : showLabel);
            button.textContent = visible ? 'Gizle' : 'Göster';
        });
    });
})();
