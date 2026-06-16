// resources/js/property-task-form.js

export default function propertyTaskForm({ suggestUrl, storeUrl, csrf, propertyId, roomId, taskId, mode, panelName, initialData }) {
    return {
        suggestUrl,
        storeUrl,
        csrf,
        propertyId,
        roomId,
        taskId,
        mode,
        panelName,
        submitting: false,
        error: null,
        success: null,
        formData: {
            name: initialData.name || '',
            type: initialData.type || 'room',
            instructions: initialData.instructions || '',
            is_sporadic: initialData.is_sporadic || false,
            is_default: initialData.is_default || false,
            visible_to_owner: true,
            visible_to_housekeeper: true,
        },

        init() {
            // Initialize form data from initialData
            this.formData = {
                name: initialData.name || '',
                type: initialData.type || 'room',
                instructions: initialData.instructions || '',
                is_sporadic: initialData.is_sporadic || false,
                is_default: initialData.is_default || false,
                visible_to_owner: true,
                visible_to_housekeeper: true,
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
                const formData = new FormData(event.target);
                
                // Ensure name is set from the input field and capitalize it
                const nameInput = event.target.querySelector('#task-name');
                if (nameInput && nameInput.value) {
                    const capitalizedName = this.capitalizeText(nameInput.value.trim());
                    formData.set('name', capitalizedName);
                }

                // Explicitly set checkbox values from model (checkboxes are unreliable in FormData)
                formData.set('is_sporadic', this.formData.is_sporadic ? '1' : '0');
                formData.set('is_default', this.formData.is_default ? '1' : '0');
                formData.set('visible_to_owner', '1');
                formData.set('visible_to_housekeeper', '1');
                
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
                    throw new Error(text || `Failed to ${this.mode === 'edit' ? 'update' : 'create'} task`);
                }

                if (!response.ok) {
                    // Handle validation errors
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0][0] || 'Validation failed';
                        throw new Error(firstError);
                    }
                    throw new Error(data.message || `Failed to ${this.mode === 'edit' ? 'update' : 'create'} task`);
                }

                // Success - show message and reload page after a short delay
                this.success = data.message || (this.mode === 'edit' ? 'Task updated successfully!' : 'Task added successfully!');
                
                // Reset form only for create mode
                if (this.mode === 'create') {
                    event.target.reset();
                    this.formData = {
                        name: '',
                        type: 'room',
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
                this.error = err.message || `An error occurred while ${this.mode === 'edit' ? 'updating' : 'creating'} the task`;
                console.error('Task form error:', err);
            } finally {
                this.submitting = false;
            }
        },
    };
}

