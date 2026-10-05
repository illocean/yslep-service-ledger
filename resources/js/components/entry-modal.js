document.addEventListener('alpine:init', () => {
    Alpine.data('entryModal', () => ({
        isSubmitting: false,

        init() {
            if (this.$el.dataset.hasErrors === 'true') {
                this.$nextTick(() => this.open());
            }
        },

        open() {
            document.querySelectorAll('dialog.entry-dialog[open]').forEach(dialog => dialog.close());
            this.isSubmitting = false;
            this.$el.showModal();
            document.body.style.overflow = 'hidden';
            (this.$el.querySelector('[aria-invalid="true"]')
                || this.$el.querySelector('input:not([type="hidden"])'))?.focus();
        },

        close() {
            this.$el.close();
        },
    }));
});
