document.addEventListener('alpine:init', () => {
    Alpine.data('conflictModal', () => ({
        isOpen: false,
        conflicts: [],
        resolutions: {},
        isResolving: false,
        fieldLabels: {},
        
        init() {
            // Read config from data attributes
            this.fieldLabels = JSON.parse(this.$el.dataset.fieldLabels || '{}');
            const conflicts = JSON.parse(this.$el.dataset.conflicts || '[]');
            this.conflicts = conflicts;
            this.resolutions = {};
            
            // Set default resolution to 'vault' (vault wins strategy)
            conflicts.forEach(c => {
                this.resolutions[c.id] = 'vault';
            });
            
            if (conflicts.length > 0) {
                this.open();
            }
        },
        
        open() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
        },
        
        get allResolved() {
            return this.conflicts.every(c => this.resolutions[c.id]);
        },
        
        formatType(type) {
            const labels = {
                'formation': 'Formation',
                'social_apostolate': 'Social Apostolate',
                'parish_involvement': 'Parish Involvement',
            };
            return labels[type] || type;
        },
        
        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString(undefined, { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        
        getFieldLabel(field) {
            return this.fieldLabels[field] || field;
        },
        
        async applyResolutions() {
            this.isResolving = true;
            
            try {
                const resolveUrl = this.$root.dataset.conflictsResolveUrl || '/conflicts/resolve';
                
                for (const conflict of this.conflicts) {
                    const resolution = this.resolutions[conflict.id];
                    if (!resolution) continue;
                    
                    const response = await fetch(resolveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            conflict_id: conflict.id,
                            resolution: resolution,
                        }),
                    });
                    
                    if (!response.ok) {
                        throw new Error(`Failed to resolve conflict ${conflict.id}`);
                    }
                }
                
                // Trigger sync after resolutions
                window.dispatchEvent(new CustomEvent('obsidian-sync-start'));
                
                this.close();
                this.$dispatch('conflicts-resolved');
                
            } catch (error) {
                console.error('Resolution error:', error);
                alert('Failed to apply resolutions. Please try again.');
            } finally {
                this.isResolving = false;
            }
        },
    }));
});