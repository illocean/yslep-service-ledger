document.addEventListener('alpine:init', () => {
    Alpine.data('ledgerSearch', () => ({
        q: '',

        match(el) {
            return this.q === '' || el.dataset.search.includes(this.q.trim().toLowerCase());
        },

        noMatchesFor(listEl) {
            return this.q !== ''
                && !Array.from(listEl.querySelectorAll('[data-search]')).some((el) => this.match(el));
        },
    }));
});
