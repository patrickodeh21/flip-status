// resources/js/room-create-form.js

export default function roomCreateForm({ storeUrl, csrf }) {
    return {
        storeUrl,
        csrf,
        submitting: false,
        error: null,
        success: null,
        formData: {
            name: '',
            is_default: false,
        },

        async submitForm(event) {
            this.error = null;
            this.success = null;
            this.submitting = true;

            try {
                const formData = new FormData();
                formData.append('name', this.formData.name);
                formData.append('is_default', this.formData.is_default ? '1' : '0');
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
                    const text = await response.text();
                    throw new Error(text || 'Failed to create room');
                }

                if (!response.ok) {
                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0][0] || 'Validation failed';
                        throw new Error(firstError);
                    }
                    throw new Error(data.message || 'Failed to create room');
                }

                // Success - show message and reload page after a short delay
                this.success = data.message || 'Room created successfully!';
                
                // Reset form
                this.formData = {
                    name: '',
                    is_default: false,
                };

                // Close panel and reload after 1 second
                setTimeout(() => {
                    this.$dispatch('close-preview-panel', 'add-room');
                    window.location.reload();
                }, 1000);

            } catch (err) {
                this.error = err.message || 'An error occurred while creating the room';
                console.error('Room creation error:', err);
            } finally {
                this.submitting = false;
            }
        },
    };
}

