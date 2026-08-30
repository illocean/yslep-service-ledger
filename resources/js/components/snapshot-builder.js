document.addEventListener('alpine:init', () => {
    Alpine.data('snapshotBuilder', (config = {}) => ({
        selectedIds: Array.isArray(config.selectedIds) ? config.selectedIds.map(String) : [],
        filterType: '',

        init() {
            // Coerce to string for checkbox value comparison
            this.selectedIds = this.selectedIds.map(String);
        },

        selectAll() {
            const cards = this.$root.querySelectorAll('[data-available-archive-report]');
            const visibleIds = [];

            cards.forEach((card) => {
                if (this.filterType === '' || this.hasType(parseInt(card.dataset.availableArchiveReport, 10), this.filterType)) {
                    visibleIds.push(String(card.dataset.availableArchiveReport));
                }
            });

            const merged = new Set([...this.selectedIds, ...visibleIds]);
            this.selectedIds = Array.from(merged);
        },

        clearAll() {
            if (this.filterType === '') {
                this.selectedIds = [];
                return;
            }

            const cards = this.$root.querySelectorAll('[data-available-archive-report]');

            this.selectedIds = this.selectedIds.filter((id) => {
                const card = this.$root.querySelector(`[data-available-archive-report="${id}"]`);
                if (!card) {
                    return true;
                }
                return !this.hasType(parseInt(id, 10), this.filterType);
            });
        },

        hasType(reportId, typeValue) {
            const card = this.$root.querySelector(`[data-available-archive-report="${reportId}"]`);
            if (!card) {
                return false;
            }

            try {
                const types = JSON.parse(card.dataset.reportTypes || '[]');
                return types.includes(typeValue);
            } catch (e) {
                return false;
            }
        },

        get selectedRecordCount() {
            let total = 0;
            this.selectedIds.forEach((id) => {
                const card = this.$root.querySelector(`[data-available-archive-report="${id}"]`);
                if (card) {
                    total += parseInt(card.dataset.reportRecords || '0', 10);
                }
            });
            return total;
        },

        get selectedHoursLabel() {
            let totalMinutes = 0;
            this.selectedIds.forEach((id) => {
                const card = this.$root.querySelector(`[data-available-archive-report="${id}"]`);
                if (card) {
                    totalMinutes += parseInt(card.dataset.reportMinutes || '0', 10);
                }
            });

            const hours = Math.floor(totalMinutes / 60);
            const minutes = totalMinutes % 60;

            if (totalMinutes === 0) {
                return '0 hr';
            }
            if (minutes === 0) {
                return hours + ' hr';
            }
            return hours + ' hr ' + String(minutes).padStart(2, '0') + ' min';
        },
    }));
});
