document.addEventListener('alpine:init', () => {
    Alpine.data('quickAddBar', () => ({
        menuOpen: false,
        menuLabel: localStorage.getItem('yslep-quick-add-label') || 'Add entry',
        allowed: [],

        init() {
            // Guard against a stored type that no longer exists (e.g. an enum
            // value renamed since it was written): fall back to the menu
            // instead of dispatching an event no modal listens for.
            this.allowed = JSON.parse(this.$el.dataset.allowedTypes || '[]');

            if (!this.allowed.includes(localStorage.getItem('yslep-quick-add-type'))) {
                this.menuLabel = 'Add entry';
            }

            this.$watch('menuOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this.$el.querySelector('.quick-add-option')?.focus());
                    return;
                }

                // Closing while a menu item still owns focus (Escape, Ctrl+K)
                // would otherwise leave focus on a hidden button.
                const options = this.$el.querySelector('#quick-add-options');

                if (options?.contains(document.activeElement)) {
                    this.$nextTick(() => {
                        if (!document.querySelector('dialog.entry-dialog[open]')) {
                            this.$el.querySelector('.quick-add-toggle')?.focus();
                        }
                    });
                }
            });
        },

        quickAdd() {
            const type = localStorage.getItem('yslep-quick-add-type');

            if (type && this.allowed.includes(type)) {
                this.$dispatch('quick-add-open', { type });
                return;
            }

            this.menuOpen = !this.menuOpen;
        },

        openModal(type, label) {
            this.menuOpen = false;
            localStorage.setItem('yslep-quick-add-type', type);
            localStorage.setItem('yslep-quick-add-label', 'Add ' + label);
            this.menuLabel = 'Add ' + label;
            this.$dispatch('quick-add-open', { type });
        },
    }));
});
