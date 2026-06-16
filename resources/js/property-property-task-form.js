// resources/js/property-property-task-form.js

export default function propertyPropertyTaskForm({ suggestUrl, storeUrl, csrf, propertyId, taskId, mode, panelName, initialData }) {
    return {
        suggestUrl,
        storeUrl,
        csrf,
        propertyId,
        taskId,
        mode,
        panelName,
        submitting: false,
        error: null,
        success: null,
        formData: {
            name: initialData.name || '',
            type: initialData.type || 'room',
            phase: initialData.phase || 'pre_cleaning',
            instructions: initialData.instructions || '',
            is_sporadic: initialData.is_sporadic || false,
            is_default: initialData.is_default || false,
            visible_to_owner: initialData.visible_to_owner !== undefined ? initialData.visible_to_owner : true,
            visible_to_housekeeper: initialData.visible_to_housekeeper !== undefined ? initialData.visible_to_housekeeper : true,
        },

        init() {
            // Initialize form data from initialData
            this.formData = {
                name: initialData.name || '',
                type: initialData.type || 'room',
                phase: initialData.phase || 'pre_cleaning',
                instructions: initialData.instructions || '',
                is_sporadic: initialData.is_sporadic || false,
                is_default: initialData.is_default || false,
                visible_to_owner: initialData.visible_to_owner !== undefined ? initialData.visible_to_owner : true,
                visible_to_housekeeper: initialData.visible_to_housekeeper !== undefined ? initialData.visible_to_housekeeper : true,
            };
        },

        // Capitalize text to title case (e.g., "Open Windows For Airing")
        capitalizeText(text) {
            if (!text) return '';
            return text.toLowerCase()
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        },

        async submitForm(event) {
            this.error = null;
            this.success = null;
            this.submitting = true;

            try {
                const formData = new FormData();
                
                // Get name from the autocomplete input and capitalize it
                const nameInput = event.target.querySelector('#property-task-name');
                if (nameInput && nameInput.value) {
                    const capitalizedName = this.capitalizeText(nameInput.value.trim());
                    formData.append('name', capitalizedName);
                } else {
                    throw new Error('Task name is required');
                }
                
                // Add type and phase
                formData.append('type', this.formData.type);
                formData.append('phase', this.formData.phase);
                
                // Add sporadic
                formData.append('is_sporadic', this.formData.is_sporadic ? '1' : '0');

                // Add is_default
                formData.append('is_default', this.formData.is_default ? '1' : '0');
                
                // Add instructions
                formData.append('instructions', this.formData.instructions || '');
                
                // Add visibility
                formData.append('visible_to_owner', this.formData.visible_to_owner ? '1' : '0');
                formData.append('visible_to_housekeeper', this.formData.visible_to_housekeeper ? '1' : '0');

                // Add media files from the file input
                const mediaInput = event.target.querySelector('input[name="media[]"]');
                if (mediaInput && mediaInput.files.length > 0) {
                    for (let i = 0; i < mediaInput.files.length; i++) {
                        formData.append('media[]', mediaInput.files[i]);
                    }
                }

                // Add captions
                const captionInputs = event.target.querySelectorAll('input[name="captions[]"]');
                captionInputs.forEach(input => {
                    formData.append('captions[]', input.value || '');
                });
                
                // Add CSRF token
                formData.append('_token', this.csrf);

                // For edit mode, add method override
                if (this.mode === 'edit') {
                    formData.append('_method', 'PUT');
                }

                const response = await fetch(this.storeUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    // If response is not JSON, try to get text
                    const text = await response.text();
                    throw new Error(text || `Failed to ${this.mode === 'edit' ? 'update' : 'create'} property task`);
                }

                if (!response.ok) {
                    // Handle validation errors
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0][0] || 'Validation failed';
                        throw new Error(firstError);
                    }
                    throw new Error(data.message || `Failed to ${this.mode === 'edit' ? 'update' : 'create'} property task`);
                }

                // Success - show message and reload page after a short delay
                this.success = data.message || (this.mode === 'edit' ? 'Property task updated successfully!' : 'Property task added successfully!');
                
                // Reset form only for create mode
                if (this.mode === 'create') {
                    event.target.reset();
                    this.formData = {
                        name: '',
                        phase: 'pre_cleaning',
                        instructions: '',
                        is_sporadic: false,
                        is_default: false,
                        visible_to_owner: true,
                        visible_to_housekeeper: true,
                    };
                }

                // Close panel and reload after 1 second
                setTimeout(() => {
                    this.$dispatch('close-preview-panel', this.panelName);
                    window.location.reload();
                }, 1000);

            } catch (err) {
                this.error = err.message || `An error occurred while ${this.mode === 'edit' ? 'updating' : 'creating'} the property task`;
                console.error('Property task form error:', err);
            } finally {
                this.submitting = false;
            }
        },
    };
}

