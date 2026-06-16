// resources/js/task-create-form.js

export default function taskCreateForm({ suggestUrl, storeUrl, csrf, roomId, panelName, initialData }) {
    const defaultFormData = {
        name: '',
        type: 'room',
        instructions: '',
        is_sporadic: false,
        visible_to_owner: true,
        visible_to_housekeeper: true,
    };
    
    return {
        suggestUrl,
        storeUrl,
        csrf,
        roomId,
        panelName: panelName || null,
        initialData: initialData || defaultFormData,
        submitting: false,
        error: null,
        success: null,
        formData: initialData ? { ...initialData } : { ...defaultFormData },

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

                // Explicitly set is_sporadic from model (checkboxes are unreliable in FormData)
                formData.set('is_sporadic', this.formData.is_sporadic ? '1' : '0');
                
                // Add CSRF token
                formData.append('_token', this.csrf);

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
                    throw new Error(text || 'Failed to create task');
                }

                if (!response.ok) {
                    // Handle validation errors
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0][0] || 'Validation failed';
                        throw new Error(firstError);
                    }
                    throw new Error(data.message || 'Failed to create task');
                }

                // Success - show message and reload page after a short delay
                this.success = data.message || 'Task added successfully!';
                
                // Reset form
                event.target.reset();
                this.formData = { ...this.initialData };

                // Close panel and reload after 1 second
                setTimeout(() => {
                    const panelToClose = this.panelName || `add-task-${this.roomId}`;
                    this.$dispatch('close-preview-panel', panelToClose);
                    window.location.reload();
                }, 1000);

            } catch (err) {
                this.error = err.message || 'An error occurred while creating the task';
                console.error('Task creation error:', err);
            } finally {
                this.submitting = false;
            }
        },
    };
}
