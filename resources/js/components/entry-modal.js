document.addEventListener('alpine:init', () => {
    Alpine.data('entryModal', (initialData) => ({
        isOpen: false,
        type: initialData.type,
        isEdit: initialData.isEdit,
        entryId: initialData.entryId,
        formData: {},
        isSubmitting: false,
        formAction: '',
        formMethod: 'POST',
        typeLabels: {},
        typeIcons: {},
        academicYears: [],
        
        init() {
            // Read config from data attributes
            this.formAction = this.$el.dataset.formAction || '';
            this.formMethod = this.$el.dataset.formMethod || 'POST';
            this.typeLabels = JSON.parse(this.$el.dataset.typeLabels || '{}');
            this.typeIcons = JSON.parse(this.$el.dataset.typeIcons || '{}');
            this.academicYears = JSON.parse(this.$el.dataset.academicYears || '[]');
            this.formData = JSON.parse(this.$el.dataset.entryData || '{}');
            
            // Only listen for open events if this is a create modal (not edit)
            if (!this.isEdit) {
                window.addEventListener('quick-add-open', (e) => {
                    if (e.detail?.type === this.type) {
                        this._openExclusive();
                    }
                });
            } else {
                window.addEventListener('open-entry-modal', (e) => {
                    const eventEntryId = String(e.detail?.entryId);
                    const thisEntryId = String(this.entryId);
                    if (eventEntryId === thisEntryId && e.detail?.isEdit) {
                        this._openExclusive();
                    }
                });
            }
            
            // Reset form when opening
            this.$watch('isOpen', (open) => {
                if (open) {
                    if (!this.isEdit) {
                        this.formData = {
                            academic_year: this.academicYears[0] || '',
                        };
                    }
                    // Focus first input after transition
                    this.$nextTick(() => {
                        const firstInput = this.$refs.form?.querySelector('input, select, textarea');
                        firstInput?.focus();
                    });
                }
            });
        },
        
        _openExclusive() {
            // Close any other open modals first
            document.querySelectorAll('.entry-modal-overlay').forEach(m => {
                if (m._x_dataStack?.[0] && m !== this.$el) {
                    m._x_dataStack[0].isOpen = false;
                }
            });
            this.open();
        },
        
        open() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
            this.$dispatch('entry-modal-closed');
        },
        
        async submit() {
            this.isSubmitting = true;
            
            try {
                const formData = new FormData(this.$refs.form);
                const response = await fetch(this.formAction, {
                    method: this.formMethod,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                
                if (response.ok) {
                    const result = await response.json();
                    this.close();
                    this.$dispatch('entry-saved', { entry: result });
                    
                    // Trigger sync if needed
                    window.dispatchEvent(new CustomEvent('obsidian-sync-start'));
                } else {
                    const errors = await response.json();
                    this.showErrors(errors);
                }
            } catch (error) {
                console.error('Submit error:', error);
                this.showErrors({ message: 'An error occurred. Please try again.' });
            } finally {
                this.isSubmitting = false;
            }
        },
        
        showErrors(errors) {
            // Clear previous errors
            this.$refs.form?.querySelectorAll('.form-error').forEach(el => el.remove());
            this.$refs.form?.querySelectorAll('.form-input, .form-select, .form-textarea').forEach(el => {
                el.classList.remove('border-rose-500');
            });
            
            // Show new errors
            if (errors.errors) {
                Object.entries(errors.errors).forEach(([field, messages]) => {
                    const input = this.$refs.form?.querySelector(`[name="${field}"]`);
                    if (input) {
                        input.classList.add('border-rose-500');
                        const errorEl = document.createElement('p');
                        errorEl.className = 'form-error';
                        errorEl.textContent = Array.isArray(messages) ? messages[0] : messages;
                        input.parentNode.appendChild(errorEl);
                    }
                });
            }
        },
    }));
});