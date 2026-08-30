document.addEventListener('alpine:init', () => {
    Alpine.data('quickAddBar', () => ({
        modalOpen: false,
        activeType: '',
        typeLabels: {},
        typeIcons: {},
        
        init() {
            // Read config from data attributes
            this.typeLabels = JSON.parse(this.$el.dataset.typeLabels || '{}');
            this.typeIcons = JSON.parse(this.$el.dataset.typeIcons || '{}');
            this.activeType = this.$el.dataset.defaultType || '';
            
            // Listen for global keyboard shortcut
            document.addEventListener('keydown', this.handleKeydown.bind(this));
            
            // Listen for modal events
            this.$watch('modalOpen', (open) => {
                if (open) {
                    window.dispatchEvent(new CustomEvent('quick-add-open', { detail: { type: this.activeType } }));
                } else {
                    window.dispatchEvent(new CustomEvent('quick-add-close'));
                }
            });
        },
        
        setType(type) {
            this.activeType = type;
        },
        
        openModal() {
            this.modalOpen = true;
        },
        
        closeModal() {
            this.modalOpen = false;
        },
        
        handleKeydown(e) {
            // Cmd/Ctrl + K to open quick add
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                this.openModal();
            }
            // Escape to close
            if (e.key === 'Escape' && this.modalOpen) {
                this.closeModal();
            }
        },
    }));
    
    Alpine.data('syncStatus', () => ({
        isSyncing: false,
        hasError: false,
        lastSync: null,
        statusText: 'Synced',
        
        init() {
            // Listen for sync events from other components
            window.addEventListener('obsidian-sync-start', () => {
                this.isSyncing = true;
                this.statusText = 'Syncing…';
            });
            
            window.addEventListener('obsidian-sync-complete', (e) => {
                this.isSyncing = false;
                this.hasError = false;
                this.lastSync = new Date();
                this.statusText = 'Synced just now';
                this.updateStatusText();
            });
            
            window.addEventListener('obsidian-sync-error', () => {
                this.isSyncing = false;
                this.hasError = true;
                this.statusText = 'Sync failed';
            });
            
            // Periodic status update
            setInterval(() => this.updateStatusText(), 30000);
        },
        
        updateStatusText() {
            if (this.lastSync) {
                const diff = Math.floor((Date.now() - this.lastSync) / 1000);
                if (diff < 60) {
                    this.statusText = 'Synced ' + diff + 's ago';
                } else if (diff < 3600) {
                    this.statusText = 'Synced ' + Math.floor(diff / 60) + 'm ago';
                } else {
                    this.statusText = 'Synced ' + Math.floor(diff / 3600) + 'h ago';
                }
            }
        },
    }));
});