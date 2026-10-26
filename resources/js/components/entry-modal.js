document.addEventListener('alpine:init', () => {
    Alpine.data('entryModal', () => ({
        isSubmitting: false,
        opener: null,

        init() {
            if (this.$el.dataset.hasErrors === 'true') {
                this.$nextTick(() => this.open());
            }
        },

        open() {
            document.querySelectorAll('dialog.entry-dialog[open]').forEach(dialog => dialog.close());
            this.isSubmitting = false;
            this.opener = this.triggerElement();
            this.$el.showModal();
            document.body.style.overflow = 'hidden';
            (this.$el.querySelector('[aria-invalid="true"]')
                || this.$el.querySelector('input:not([type="hidden"])'))?.focus();
        },

        close() {
            this.$el.close();
        },

        // Escape, the close button, and a backdrop click all end at the native
        // close event, so focus is restored from there instead of close().
        returnFocus() {
            const opener = this.opener;

            this.opener = null;

            // Another dialog took over: it owns focus now, and its own opener
            // is restored when it closes.
            if (!opener || !opener.isConnected || document.querySelector('dialog.entry-dialog[open]')) {
                return;
            }

            opener.focus({ preventScroll: true });
        },

        triggerElement() {
            const active = document.activeElement;

            if (!active || active === document.body || active === document.documentElement) {
                return null;
            }

            return active;
        },
    }));
});