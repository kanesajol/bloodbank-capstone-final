document.addEventListener('click', (event) => {
    const target = event.target;

    if (!(target instanceof Element)) {
        return;
    }

    const button = target.closest('[data-password-toggle]');

    if (!button) {
        return;
    }

    const input = document.getElementById(button.getAttribute('aria-controls'));

    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const isVisible = input.type === 'password';

    input.type = isVisible ? 'text' : 'password';
    button.textContent = isVisible ? 'Hide' : 'Show';
    button.setAttribute('aria-label', `${isVisible ? 'Hide' : 'Show'} password`);
    button.setAttribute('aria-pressed', String(isVisible));
});
