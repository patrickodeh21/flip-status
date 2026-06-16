/**
 * Modern Checklist AJAX Handler
 * Handles all checklist interactions without page reloads
 */

export default function checklist(config = {}) {
    return {
        // State
        loading: false,
        error: null,
        success: null,
        renderer: null,
        dataUrl: config.dataUrl || null,

        init() {
            // Store reference for external access
            window.checklistHandler = this;

            // Intercept all checklist form submissions
            this.setupFormHandlers();
        },

        setupFormHandlers() {
            // Handle toggle buttons - use event delegation for dynamically added elements
            document.addEventListener('click', (e) => {
                const button = e.target.closest('[data-checklist-toggle]');
                if (button && !button.disabled) {
                    // Let Alpine handle buttons that explicitly define @click logic
                    if (button.hasAttribute('@click')) return;
                    
                    e.preventDefault();
                    this.handleToggle(e, button);
                }
            });

            // Handle note saves - use event delegation for dynamically added elements
            document.removeEventListener('click', this.handleNoteSaveDelegate);
            this.handleNoteSaveDelegate = (e) => {
                // Check if clicked element or its parent has the save button attribute
                const saveButton = e.target.closest('[data-checklist-note-save]');
                if (saveButton) {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleNoteSave(e, saveButton);
                }
            };
            document.addEventListener('click', this.handleNoteSaveDelegate);

            // Handle photo uploads - use event delegation to prevent double binding
            // Only handle forms that don't have the photoUploader component (legacy support)
            // Remove any existing listeners first
            document.removeEventListener('submit', this.handlePhotoUploadDelegate);
            this.handlePhotoUploadDelegate = (e) => {
                // Skip if form is handled by photoUploader component (has @submit.prevent)
                const form = e.target;
                if (form && form.hasAttribute('x-data') && form.getAttribute('x-data').includes('photoUploader')) {
                    return; // Let photoUploader handle it
                }
                if (form.matches('[data-checklist-photo-form]')) {
                    this.handlePhotoUpload(e);
                }
            };
            document.addEventListener('submit', this.handlePhotoUploadDelegate);
        },

        async handleToggle(event, button, extraData = {}) {
            event.preventDefault();
            event.stopPropagation();

            if (!button) {
                button = event.currentTarget;
            }

            const url = button.dataset.toggleUrl;
            if (!url) {
                console.error('Toggle URL not found');
                return;
            }

            // Store the current state for potential rollback
            const currentChecked = button.dataset.checked === 'true';
            const newChecked = !currentChecked;

            // OPTIMISTIC UPDATE: Update UI immediately before API call
            button.dataset.checked = newChecked ? 'true' : 'false';
            this.updateToggleUI(button, newChecked);

            // Disable button during request to prevent double-clicks
            button.disabled = true;

            try {
                // Get CSRF token from meta tag (already set up in bootstrap.js)
                const data = await window.api.post(url, extraData);

                if (data.success) {
                    // Confirm the update with server response (in case of any discrepancy)
                    button.dataset.checked = data.checked ? 'true' : 'false';
                    this.updateToggleUI(button, data.checked);

                    // Update local session data and re-render without a network call
                    this.updateLocalAndRerender(button, data.checked);

                    // Show success feedback
                    this.showSuccess('Task updated');
                } else {
                    throw new Error(data.message || 'Failed to update task');
                }
            } catch (error) {
                console.error('Toggle error:', error);
                const errorMessage = error.response?.data?.message || error.message || 'An error occurred';
                this.showError(errorMessage);
                // ROLLBACK: Restore original state on error
                button.dataset.checked = currentChecked ? 'true' : 'false';
                this.updateToggleUI(button, currentChecked);
            } finally {
                button.disabled = false;
            }
        },

        async handleNoteSave(event, button = null) {
            event.preventDefault();
            event.stopPropagation();

            // Get button element - either passed as parameter or from event
            button = button || event.currentTarget || event.target.closest('[data-checklist-note-save]');

            if (!button) {
                console.error('Save button not found');
                return;
            }

            // Get URL from data attribute (use getAttribute as it's more reliable)
            const url = button.getAttribute('data-note-url') || (button.dataset && button.dataset.noteUrl);
            const noteInput = button.closest('[data-note-container]')?.querySelector('[data-note-input]');

            if (!url) {
                console.error('Note save URL not found on button. Button:', button, 'Attributes:', button.attributes);
                return;
            }

            if (!noteInput) {
                console.error('Note input not found');
                return;
            }

            const noteValue = noteInput.value || '';
            const originalText = button.textContent;
            const noteSaving = button.dataset.noteSaving === 'true';

            if (noteSaving) {
                return; // Prevent double submission
            }

            button.dataset.noteSaving = 'true';
            button.disabled = true;
            button.textContent = 'Saving...';

            try {
                const data = await window.api.post(url, {
                    note: noteValue
                });

                if (data.success) {
                    this.showSuccess('Note saved');
                    button.textContent = 'Saved!';
                    setTimeout(() => {
                        button.textContent = originalText;
                    }, 2000);
                } else {
                    throw new Error(data.message || 'Failed to save note');
                }
            } catch (error) {
                console.error('Note save error:', error);
                const errorMessage = error.response?.data?.message || error.message || 'Failed to save note';
                this.showError(errorMessage);
                button.textContent = originalText;
            } finally {
                button.dataset.noteSaving = 'false';
                button.disabled = false;
            }
        },

        async handlePhotoUpload(event) {
            event.preventDefault();
            event.stopPropagation(); // Prevent double submission

            const form = event.currentTarget;

            // Prevent double submission - check if dataset exists
            if (form && form.dataset && form.dataset.uploading === 'true') {
                return;
            }
            if (form && form.dataset) {
                form.dataset.uploading = 'true';
            }

            const url = form.action;
            const formData = new FormData(form);
            const fileInput = form.querySelector('input[type="file"]');
            const submitButton = form.querySelector('button[type="submit"]');
            const progressContainer = form.querySelector('[data-upload-progress]');

            if (!fileInput || !fileInput.files.length) {
                if (form && form.dataset) {
                    form.dataset.uploading = 'false';
                }
                this.showError('Please select photos to upload');
                return;
            }

            // Show progress indicator
            if (progressContainer) {
                progressContainer.classList.remove('hidden');
                progressContainer.innerHTML = '<div class="text-sm text-gray-600">Uploading...</div>';
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Uploading...';
            }

            try {
                const data = await window.api.post(url, formData, {
                    onUploadProgress: (progressEvent) => {
                        if (progressContainer && progressEvent.total) {
                            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                            progressContainer.innerHTML = `<div class="text-sm text-gray-600">Uploading... ${percentCompleted}%</div>`;
                        }
                    },
                });

                // Add new photos to gallery
                if (data.photos && Array.isArray(data.photos)) {
                    this.addPhotosToGallery(data.photos, form);
                }

                // Clear file input and preview
                fileInput.value = '';
                const previewContainer = form.querySelector('[data-photo-preview]');
                if (previewContainer) {
                    previewContainer.innerHTML = '';
                    previewContainer.classList.add('hidden');
                }

                // Update photo count
                this.updatePhotoCount(form);

                // Refresh session data to get updated photo counts
                const sessionContainer = document.querySelector('[data-session-id]');
                const sessionId = sessionContainer?.dataset.sessionId ||
                    window.location.pathname.match(/\/sessions\/([^\/]+)/)?.[1];
                if (sessionId) {
                    this.refreshAndRerender(sessionId);
                }

                this.showSuccess(data.message || 'Photos uploaded successfully');
            } catch (error) {
                console.error('Photo upload error:', error);
                const errorMessage = error.response?.data?.message || error.message || 'Failed to upload photos';
                this.showError(errorMessage);
            } finally {
                if (form && form.dataset) {
                    form.dataset.uploading = 'false';
                }
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Upload Photos';
                }
                if (progressContainer) {
                    progressContainer.classList.add('hidden');
                }
            }
        },

        updateLocalAndRerender(button, checked, data = null) {
            const rendererElement = document.querySelector('[x-data*="checklistRenderer"]');
            if (!rendererElement || !rendererElement._x_dataStack) return;

            const renderer = rendererElement._x_dataStack[0];
            if (!renderer || !renderer.sessionData) return;

            const sd = renderer.sessionData;
            const url = button.dataset.toggleUrl;
            if (!url) return;

            // Detect URL type: room/inventory task vs property-level task
            const roomMatch = url.match(/\/rooms\/(\d+)\/tasks\/(\d+)\/toggle/);
            const propMatch = !roomMatch && url.match(/\/tasks\/(\d+)\/toggle/);

            if (roomMatch) {
                const roomId = parseInt(roomMatch[1]);
                const taskId = parseInt(roomMatch[2]);

                if (sd.rooms) {
                    for (const room of sd.rooms) {
                        if (room.id === roomId && room.tasks) {
                            const tasksArr = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks);
                            for (const task of tasksArr) {
                                if (task.id === taskId) {
                                    if (!task.checklist_item) task.checklist_item = { checked: false };
                                    task.checklist_item.checked = checked;
                                    if (data && data.item && data.item.quantity !== undefined) {
                                        task.checklist_item.quantity = data.item.quantity;
                                    }
                                }
                            }
                        }
                    }
                }

                // Recalculate room_tasks count
                if (sd.counts && sd.rooms) {
                    let checkedRoom = 0;
                    for (const room of sd.rooms) {
                        const tasksArr = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                        for (const task of tasksArr) {
                            if (task.type !== 'instructions' && task.checklist_item?.checked) {
                                checkedRoom++;
                            }
                        }
                    }
                    if (sd.counts.room_tasks) sd.counts.room_tasks.checked = checkedRoom;
                }
            } else if (propMatch) {
                const taskId = parseInt(propMatch[1]);
                const phase = sd.stage;

                if (sd.property_tasks?.[phase]) {
                    for (const task of sd.property_tasks[phase]) {
                        if (task.id === taskId) {
                            if (!task.checklist_item) task.checklist_item = { checked: false };
                            task.checklist_item.checked = checked;
                            if (data && data.item && data.item.quantity !== undefined) {
                                task.checklist_item.quantity = data.item.quantity;
                            }
                        }
                    }
                }

                // Recalculate counts for this phase
                if (sd.counts?.[phase] && sd.property_tasks?.[phase]) {
                    const tasks = sd.property_tasks[phase];
                    sd.counts[phase].checked = tasks.filter(t => t.checklist_item?.checked).length;
                }
            }

            // REMOVED: Automatic stage advancement logic
            // We no longer compute and set new stage here
            // The stage will only change when user clicks submit

            // Re-render immediately from updated in-memory data (no network call)
            renderer.renderChecklist();
        },

        computeStage(sd) {
            if (sd.session?.status === 'completed') return 'summary';
            const c = sd.counts || {};
            const pre = c.pre_cleaning || { total: 0, checked: 0 };
            const room = c.room_tasks || { total: 0, checked: 0 };
            const dur = c.during_cleaning || { total: 0, checked: 0 };
            const post = c.post_cleaning || { total: 0, checked: 0 };
            if (pre.total > 0 && pre.checked < pre.total) return 'pre_cleaning';
            if (room.total > 0 && room.checked < room.total) return 'rooms_first_half';
            if (dur.total > 0 && dur.checked < dur.total) return 'during_cleaning';
            if (post.total > 0 && post.checked < post.total) return 'post_cleaning';
            return 'photos';
        },

        updateToggleUI(button, checked) {
            // Update button appearance
            if (checked) {
                button.classList.remove('bg-white', 'dark:bg-gray-700', 'border-gray-300', 'dark:border-gray-600');
                button.classList.add('bg-green-600', 'border-green-600', 'text-white');
                button.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>';
                button.setAttribute('aria-label', 'Mark as incomplete');
            } else {
                button.classList.remove('bg-green-600', 'border-green-600', 'text-white');
                button.classList.add('bg-white', 'dark:bg-gray-700', 'border-gray-300', 'dark:border-gray-600');
                button.innerHTML = '';
                button.setAttribute('aria-label', 'Mark as complete');
            }

            // Update task text styling
            const taskItem = button.closest('[data-task-item]');
            if (taskItem) {
                const taskName = taskItem.querySelector('[data-task-name]');
                if (taskName) {
                    if (checked) {
                        taskName.classList.add('text-gray-500', 'dark:text-gray-400');
                        taskName.classList.remove('text-gray-800', 'dark:text-gray-200');
                    } else {
                        taskName.classList.remove('text-gray-500', 'dark:text-gray-400');
                        taskName.classList.add('text-gray-800', 'dark:text-gray-200');
                    }
                }
            }
        },

        updateProgress() {
            // Update progress indicators if they exist
            const progressElements = document.querySelectorAll('[data-progress-update]');
            progressElements.forEach(el => {
                // Trigger a custom event that can be handled by Alpine components
                el.dispatchEvent(new CustomEvent('progress-update'));
            });
        },

        addPhotosToGallery(photos, form) {
            const gallery = form.closest('[data-room-photos]')?.querySelector('[data-photo-gallery]');
            if (!gallery) return;

            photos.forEach(photo => {
                const photoElement = document.createElement('div');
                photoElement.className = 'relative group';
                photoElement.innerHTML = `
                    <button type="button" class="w-full" data-photo-view="${photo.id}">
                        <img src="${photo.url}" alt="Photo"
                             class="aspect-square w-full object-cover rounded-xl border transition hover:opacity-90" />
                        <span class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">
                            ${photo.captured_at}
                        </span>
                    </button>
                `;
                gallery.appendChild(photoElement);
            });
        },

        updatePhotoCount(form) {
            const countElement = form.closest('[data-room-photos]')?.querySelector('[data-photo-count]');
            if (!countElement) return;

            const gallery = form.closest('[data-room-photos]')?.querySelector('[data-photo-gallery]');
            const currentCount = gallery?.children.length || 0;
            const roomId = form.dataset.roomId;

            // Update count display
            countElement.textContent = `${currentCount}/8 photos`;

            // Update progress bar if exists
            const progressBar = form.closest('[data-room-photos]')?.querySelector('[data-photo-progress]');
            if (progressBar) {
                const progress = Math.min((currentCount / 8) * 100, 100);
                progressBar.style.width = `${progress}%`;
            }
        },

        showSuccess(message) {
            this.success = message;
            this.error = null;
            setTimeout(() => {
                this.success = null;
            }, 3000);
        },

        showError(message) {
            this.error = message;
            this.success = null;
            setTimeout(() => {
                this.error = null;
            }, 5000);
        },


        /**
         * Refresh session data from API
         * Call this after completing tasks to update progress, stage, etc.
         */
        async refreshSessionData(sessionId) {
            try {
                const response = await window.api.get(`/api/sessions/${sessionId}/data`);

                if (response.success && response.data) {
                    // Update progress indicators
                    this.updateProgressFromData(response.data);

                    // Dispatch event for components to update
                    document.dispatchEvent(new CustomEvent('session-data-updated', {
                        detail: response.data
                    }));

                    return response.data;
                }
            } catch (error) {
                console.error('Failed to refresh session data:', error);
                this.showError('Failed to refresh session data');
            }
        },

        /**
         * Refresh session data and re-render the entire checklist
         */
        async refreshAndRerender(sessionId) {
            if (!sessionId || sessionId === '{session}') return;

            try {
                // Use provided route URL or fallback to hardcoded path
                let url = this.dataUrl || `/api/sessions/${sessionId}/data`;

                // If the URL still has placeholder, replace it
                if (url.includes('{session}')) {
                    url = url.replace('{session}', sessionId);
                }

                const response = await window.api.get(url);

                if (response.success && response.data) {
                    // Trigger re-render if renderer exists
                    const rendererElement = document.querySelector('[x-data*="checklistRenderer"]');
                    if (rendererElement && rendererElement._x_dataStack) {
                        const renderer = rendererElement._x_dataStack[0];
                        if (renderer && typeof renderer.refresh === 'function') {
                            // Update session data and re-render
                            renderer.sessionData = response.data;
                            renderer.renderChecklist();
                        }
                    }

                    // Also dispatch event for other components
                    document.dispatchEvent(new CustomEvent('session-data-updated', {
                        detail: response.data
                    }));
                }
            } catch (error) {
                console.error('Failed to refresh and re-render:', error);
                // Don't show error - silent fail
            }
        },

        /**
         * Update progress indicators from API data
         */
        updateProgressFromData(data) {
            // Update stage indicator if exists
            const stageElements = document.querySelectorAll('[data-stage-area], [data-current-stage]');
            const stageName = data.stage ? data.stage.replace(/_/g, ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ') : 'Unknown';
            const stageBadgeHtml = `<span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200">${stageName}</span>`;
            
            stageElements.forEach(el => {
                if (el.matches('[data-current-stage]')) {
                    el.textContent = stageName;
                } else if (el.matches('[data-stage-area]')) {
                    el.innerHTML = stageBadgeHtml;
                }
            });

            // Update progress bars
            const progressBars = document.querySelectorAll('[data-progress-update]');
            progressBars.forEach(bar => {
                const roomId = bar.dataset.roomId;
                if (roomId && data.photo_counts[roomId] !== undefined) {
                    const count = data.photo_counts[roomId];
                    const progress = Math.min((count / 8) * 100, 100);
                    bar.style.width = `${progress}%`;

                    const countElement = bar.closest('[data-room-photos]')?.querySelector('[data-photo-count]');
                    if (countElement) {
                        countElement.textContent = `${count}/8 photos`;
                    }
                }
            });

            // Update task counts if needed
            if (data.counts) {
                // Update property-level task counts
                ['pre_cleaning', 'during_cleaning', 'post_cleaning'].forEach(phase => {
                    const countEl = document.querySelector(`[data-${phase}-count]`);
                    if (countEl && data.counts[phase]) {
                        countEl.textContent = `${data.counts[phase].checked}/${data.counts[phase].total}`;
                    }
                });
            }
        },

        /**
         * Save progress and advance to the next stage
         */
        async saveProgress() {
            const rendererElement = document.querySelector('[x-data*="checklistRenderer"]');
            if (!rendererElement || !rendererElement._x_dataStack) {
                this.showError('Checklist renderer not found');
                return;
            }

            const renderer = rendererElement._x_dataStack[0];
            if (!renderer || !renderer.sessionData) {
                this.showError('Session data not loaded');
                return;
            }

            // Client-side validation: Check if there are any incomplete verify/inventory tasks in the current stage
            const sd = renderer.sessionData;
            const currentStage = sd.stage;
            let incompleteMandatory = [];

            if (currentStage === 'pre_cleaning' || currentStage === 'during_cleaning' || currentStage === 'post_cleaning') {
                const tasks = sd.property_tasks?.[currentStage] || [];
                incompleteMandatory = tasks.filter(t => t.type !== 'instructions' && (t.type === 'verify' || t.type === 'inventory') && !t.checklist_item?.checked);
            } else if (currentStage === 'rooms' || currentStage === 'rooms_first_half' || currentStage === 'rooms_second_half') {
                const rooms = sd.rooms || [];
                rooms.forEach(room => {
                    const tasksArr = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                    const mandatory = tasksArr.filter(t => t.type !== 'instructions' && (t.type === 'verify' || t.type === 'inventory') && !t.checklist_item?.checked);
                    incompleteMandatory.push(...mandatory);
                });
            }

            if (incompleteMandatory.length > 0) {
                this.showError('Please complete all mandatory verify/inventory tasks before advancing.');
                // Optionally scroll to the first incomplete task, or just scroll to bottom to see error
                window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
                return;
            }

            const sessionId = this.getSessionId();
            if (!sessionId) {
                this.showError('Session ID not found');
                return;
            }

            const submitButton = document.getElementById('stepBtn');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="inline-block animate-spin mr-2">↻</span> Advancing...';
            }

            try {
                const response = await window.api.post(`/sessions/${sessionId}/advance-stage`, {
                    current_stage: renderer.sessionData.stage
                });

                if (response.success) {
                    this.showSuccess(response.message || 'Progress saved! Moving to next stage...');

                    if (response.data) {
                        renderer.sessionData = response.data;
                    } else {
                        await this.refreshAndRerender(sessionId);
                    }

                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Submit';
                    }

                    renderer.renderChecklist();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    throw new Error(response.message || 'Failed to advance stage');
                }
            } catch (error) {
                console.error('Error advancing stage:', error);
                const errorMessage = error.response?.data?.message || error.message || 'Failed to advance stage';
                this.showError(errorMessage);

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = 'Submit';
                }
            }
        },

        /**
         * Helper method to get session ID from various sources
         */
        getSessionId() {
            // Try to get from data attribute
            const container = document.querySelector('[data-session-id]');
            if (container?.dataset.sessionId) {
                return container.dataset.sessionId;
            }

            // Try to extract from URL
            const match = window.location.pathname.match(/\/sessions\/([^\/]+)/);
            if (match) {
                return match[1];
            }

            return null;
        }
    };

}

/**
 * Note Reporter Component
 * Handles saving notes from the summary stage
 */
window.noteReporter = function (config = {}) {
    return {
        sessionId: config.sessionId || null,
        note: '',
        submitting: false,
        success: null,

        async submitNote() {
            if (!this.note.trim() || this.submitting) return;

            this.submitting = true;
            this.success = null;

            try {
                const response = await window.api.post(`/sessions/${this.sessionId}/save-note`, {
                    note: this.note
                });

                if (response.success) {
                    this.success = 'Note saved successfully!';

                    // Dispatch event to hide the notes card in the UI
                    window.dispatchEvent(new CustomEvent('note-submitted'));

                    // Clear success message after 3 seconds
                    setTimeout(() => {
                        this.success = null;
                    }, 3000);
                } else {
                    throw new Error(response.message || 'Failed to save note');
                }
            } catch (error) {
                console.error('Error saving note:', error);
                const errorMessage = error.response?.data?.message || error.message || 'Failed to save note';
                window.checklistHandler?.showError(errorMessage);
            } finally {
                this.submitting = false;
            }
        }
    };
};
