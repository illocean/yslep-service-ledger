document.addEventListener('alpine:init', () => {
    Alpine.data('quickAddBar', () => ({
        menuOpen: false,
        menuLabel: localStorage.getItem('yslep-quick-add-label') || 'Add entry',
        allowed: [],

        init() {
            this.allowed = JSON.parse(this.$el.dataset.allowedTypes || '[]');
            this.$watch('menuOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this.$el.querySelector('.quick-add-option')?.focus());
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
