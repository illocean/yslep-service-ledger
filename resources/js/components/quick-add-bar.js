document.addEventListener('alpine:init', () => {
    Alpine.data('quickAddBar', () => ({
        menuOpen: false,

        init() {
            this.$watch('menuOpen', (open) => {
                if (open) {
                    this.$nextTick(() => this.$el.querySelector('.quick-add-option')?.focus());
                }
            });
        },

        openModal(type) {
            this.menuOpen = false;
            this.$dispatch('quick-add-open', { type });
        },
    }));
});
