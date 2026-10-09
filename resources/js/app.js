import './bootstrap';

const SCROLL_KEY = 'yslep-scroll';

document.addEventListener('DOMContentLoaded', () => {
    const savedScroll = sessionStorage.getItem(SCROLL_KEY);

    if (savedScroll !== null) {
        sessionStorage.removeItem(SCROLL_KEY);

        let y = NaN;
        let path = null;

        try {
            const parsed = JSON.parse(savedScroll);

            if (parsed !== null && typeof parsed === 'object') {
                ({ y, path } = parsed);
            } else {
                y = parsed;
            }
        } catch {
            // Unparseable value: drop it rather than scroll somewhere random.
        }

        // Only the page that saved the offset may restore it; a cross-page
        // submit would otherwise land mid-page on an unrelated destination.
        if (path !== null && path !== location.pathname) {
            y = NaN;
        }

        if (Number.isFinite(y)) {
            requestAnimationFrame(() => {
                window.scrollTo({ top: y, behavior: 'instant' });
            });
        }
    }

    const saveScroll = () => {
        sessionStorage.setItem(SCROLL_KEY, JSON.stringify({ y: window.scrollY, path: location.pathname }));
    };

    document.querySelectorAll('[data-auto-submit]').forEach((element) => {
        element.addEventListener('change', () => {
            saveScroll();
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
        const form = event.target;
        const dirtyForm = form.closest?.(DIRTY_SELECTOR);

        if (dirtyForm) {
            delete dirtyForm.dataset.dirty;
        }

        // A cancelled data-confirm sets defaultPrevented and must not leave a
        // stale key. Cross-page posts are safe without opting out: restore
        // skips any key whose pathname differs. data-no-keep-scroll is for
        // same-page landings that should start at the top (the builder).
        if (!event.defaultPrevented && !form.hasAttribute?.('data-no-keep-scroll')) {
            saveScroll();
        }
    });

    window.addEventListener('beforeunload', (event) => {
        if (document.querySelector('form.record-form-shell[data-dirty], form[data-dirty-guard][data-dirty]')) {
            event.preventDefault();
            event.returnValue = '';
        }
    });

    const menubar = document.querySelector('[role="menubar"]');

    if (menubar) {
        const items = Array.from(menubar.querySelectorAll('[role="menuitem"]'));

        menubar.addEventListener('keydown', (event) => {
            const currentIndex = items.indexOf(document.activeElement);

            switch (event.key) {
                case 'ArrowRight':
                case 'ArrowDown':
                    event.preventDefault();
                    items[(currentIndex + 1) % items.length]?.focus();
                    break;
                case 'ArrowLeft':
                case 'ArrowUp':
                    event.preventDefault();
                    items[(currentIndex - 1 + items.length) % items.length]?.focus();
                    break;
                case 'Home':
                    event.preventDefault();
                    items[0]?.focus();
                    break;
                case 'End':
                    event.preventDefault();
                    items[items.length - 1]?.focus();
                    break;
            }
        });
    }

    document.querySelectorAll('details.group').forEach((details) => {
        const summary = details.querySelector('summary');

        if (!summary) return;

        details.addEventListener('toggle', () => {
            if (details.open) {
                const firstInput = details.querySelector('input:not([type="hidden"]):not([disabled]), select, textarea');

                if (firstInput) {
                    requestAnimationFrame(() => firstInput.focus({ preventScroll: true }));
                }
            }
        });
    });

    const errorSummary = document.querySelector('[role="alert"]');

    if (errorSummary) {
        const firstErrorField = document.querySelector('.form-input[aria-invalid="true"], .form-input.is-invalid');

        if (firstErrorField) {
            requestAnimationFrame(() => firstErrorField.focus({ preventScroll: true }));
        }
    }
});
