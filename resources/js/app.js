import './bootstrap';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-auto-submit]').forEach((element) => {
        element.addEventListener('change', () => {
            element.form?.submit();
        });
    });

    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('form.sync-form').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');

            if (!button || button.disabled) {
                return;
            }

            form.setAttribute('aria-busy', 'true');
            button.disabled = true;
            button.textContent = 'Syncing…';

            const spinner = document.createElement('span');
            spinner.setAttribute('aria-hidden', 'true');
            spinner.className = 'inline-block h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent align-middle';
            button.prepend(spinner);
        });
    });

    const DIRTY_SELECTOR = 'form.record-form-shell, form[data-dirty-guard]';

    document.addEventListener('input', (event) => {
        const form = event.target.closest(DIRTY_SELECTOR);

        if (form) {
            form.dataset.dirty = 'true';
        }
    });

    document.addEventListener('change', (event) => {
        const form = event.target.closest(DIRTY_SELECTOR);

        if (form) {
            form.dataset.dirty = 'true';
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest(DIRTY_SELECTOR);

        if (form) {
            delete form.dataset.dirty;
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (document.querySelector('form.record-form-shell[data-dirty], form[data-dirty-guard][data-dirty]')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
});
