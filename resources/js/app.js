// resources/js/app.js

import './bootstrap'
import './property-panels'

import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'
import PerfectScrollbar from 'perfect-scrollbar'
import Sortable from 'sortablejs'

window.Sortable = Sortable
window.PerfectScrollbar = PerfectScrollbar

// Simple UID helper
window.randomUID = function randomUID(prefix = 'id') {
    const rand = Math.random().toString(36).substring(2, 10)
    const time = Date.now().toString(36)
    return `${prefix}-${time}-${rand}`
}

// Existing local components
import roomsList from './room-list'
import roomAutocomplete from './room-autocomplete'
import taskList from './task-list'
import taskAutocomplete from './task-autocomplete'
import mediaDropzone from './media-dropzone'
import roomPicker from './room-picker'
import taskPicker from './task-picker'
import dropdown from './dropdown'

// New components
import roomsIndex from './rooms-index'
import roomTasksEditor from './room-tasks-editor'
import taskCreateForm from './task-create-form'
import roomCreateForm from './room-create-form'
import propertyTaskForm from './property-task-form'
import propertyRoomForm from './property-room-form'
import propertyPropertyTaskForm from './property-property-task-form'
import checklist from './checklist'
import checklistRenderer from './checklist-renderer'
import photoUploader from './photo-uploader'
import photoDeleteHandler from './photo-delete-handler'

import propertyAssignmentsPanel from './pages/properties/property-assignments-panel'

// ⛔️ DO NOT MODIFY — main app interaction (kept exactly as you sent)
document.addEventListener('alpine:init', () => {
    Alpine.data('mainState', () => {
        let lastScrollTop = 0
        const init = function () {
            window.addEventListener('scroll', () => {
                let st =
                    window.pageYOffset || document.documentElement.scrollTop
                if (st > lastScrollTop) {
                    // downscroll
                    this.scrollingDown = true
                    this.scrollingUp = false
                } else {
                    // upscroll
                    this.scrollingDown = false
                    this.scrollingUp = true
                    if (st == 0) {
                        //  reset
                        this.scrollingDown = false
                        this.scrollingUp = false
                    }
                }
                lastScrollTop = st <= 0 ? 0 : st // For Mobile or negative scrolling
            })
        }

        const getTheme = () => {
            if (window.localStorage.getItem('dark')) {
                return JSON.parse(window.localStorage.getItem('dark'))
            }
            return (
                !!window.matchMedia &&
                window.matchMedia('(prefers-color-scheme: dark)').matches
            )
        }
        const setTheme = (value) => {
            window.localStorage.setItem('dark', value)
        }
        return {
            init,
            isDarkMode: getTheme(),
            toggleTheme() {
                this.isDarkMode = !this.isDarkMode
                setTheme(this.isDarkMode)
            },
            isSidebarOpen: window.innerWidth > 1024,
            _sidebarManuallyOpened: false,
            _lastWindowHeight: window.innerHeight,
            toggleSidebar() {
                this.isSidebarOpen = !this.isSidebarOpen;
                // Track if user manually opened on mobile
                if (this.isSidebarOpen && window.innerWidth <= 1024) {
                    this._sidebarManuallyOpened = true;
                } else if (!this.isSidebarOpen) {
                    this._sidebarManuallyOpened = false;
                }
            },
            isSidebarHovered: false,
            handleSidebarHover(value) {
                if (window.innerWidth < 1024) {
                    return
                }
                this.isSidebarHovered = value
            },
            handleWindowResize() {
                // On mobile, the browser address bar showing/hiding fires resize
                // events with only height changes. Ignore those to prevent
                // the sidebar from closing unexpectedly.
                const heightDiff = Math.abs(window.innerHeight - this._lastWindowHeight);
                const widthChanged = false; // resize.window doesn't tell us, but we can infer
                this._lastWindowHeight = window.innerHeight;

                if (window.innerWidth <= 1024) {
                    // If user manually opened sidebar on mobile, don't auto-close
                    // unless this is a genuine width-based resize (rotation, etc.)
                    if (this._sidebarManuallyOpened && heightDiff < 200) {
                        return; // Ignore small height-only resizes (address bar)
                    }
                    this.isSidebarOpen = false;
                    this._sidebarManuallyOpened = false;
                } else {
                    this.isSidebarOpen = true;
                }
            },
            scrollingDown: false,
            scrollingUp: false,
        }
    })
})

// Second alpine:init for all other components
document.addEventListener('alpine:init', () => {
    // Existing components
    Alpine.data('mediaDropzone', mediaDropzone)
    Alpine.data('taskList', taskList)
    Alpine.data('taskAutocomplete', taskAutocomplete)

    Alpine.data('roomsList', roomsList)
    Alpine.data('roomAutocomplete', roomAutocomplete)
    Alpine.data('roomPicker', roomPicker)
    Alpine.data('taskPicker', taskPicker)
    Alpine.data('dropdown', dropdown)

    // New: rooms index (bulk assign tasks)
    Alpine.data('roomsIndex', roomsIndex)

    // New: edit room + tasks on same page
    Alpine.data('roomTasksEditor', roomTasksEditor)

    // New: task create form
    Alpine.data('taskCreateForm', taskCreateForm)

    // New: room create form
    Alpine.data('roomCreateForm', roomCreateForm)

    // New: property task form (create/edit)
    Alpine.data('propertyTaskForm', propertyTaskForm)

    // New: property room form (create/edit)
    Alpine.data('propertyRoomForm', propertyRoomForm)

    // New: property property task form (create/edit)
    Alpine.data('propertyPropertyTaskForm', propertyPropertyTaskForm)

    // Checklist AJAX handler
    Alpine.data('checklist', checklist)

    // Checklist renderer (dynamic rendering)
    Alpine.data('checklistRenderer', checklistRenderer)

    // Photo uploader component
    Alpine.data('photoUploader', photoUploader)

    // Photo delete handler component
    Alpine.data('photoDeleteHandler', photoDeleteHandler)

    Alpine.data('propertyAssignmentsPanel', propertyAssignmentsPanel)

    // Global modal portal — renders modals at body level to avoid fixed-position containing-block bugs
    Alpine.data('globalModal', () => ({
        show: false,
        type: null, // 'note', 'photo', 'gallery', 'inventory'

        // Shared Existing Data display
        existingPhotos: [],
        existingNote: '',

        // Note modal
        noteUrl: '',
        currentTaskId: null,
        noteValue: '',
        noteSaving: false,

        // Photo modal
        photoUrl: '',
        previewUrl: null,
        selectedFile: null,
        photoNoteValue: '',
        photoUploading: false,
        taskType: null, // track which type of task opened the modal

        // Gallery
        gallerySrc: null,

        // Inventory modal
        inventoryStep: 1, // 1 = quantity entry, 2 = photo upload
        inventoryQuantity: '',
        inventoryTaskName: '',
        inventoryToggleUrl: '',
        inventoryError: '',

        init() {
            window.addEventListener('open-note-modal', (e) => {
                this.type = 'note';
                this.noteUrl = e.detail.noteUrl;
                this.currentTaskId = e.detail.taskId;
                this.noteValue = e.detail.noteValue || '';
                this.noteSaving = false;
                this.existingPhotos = e.detail.itemPhotos || [];
                this.existingNote = e.detail.noteValue || '';
                this.show = true;
            });
            window.addEventListener('open-photo-modal', (e) => {
                this.type = 'photo';
                this.photoUrl = e.detail.photoUrl;
                this.photoToggleUrl = e.detail.toggleUrl || null;
                this.currentTaskId = e.detail.taskId;
                this.taskType = e.detail.taskType || null;
                this.inventoryToggleUrl = '';  // Clear so inventory doesn't get cross-ticked
                this.previewUrl = null;
                this.selectedFile = null;
                this.photoNoteValue = '';
                this.photoUploading = false;
                this.existingPhotos = e.detail.itemPhotos || [];
                this.existingNote = e.detail.noteValue || '';
                this.show = true;
            });
            window.addEventListener('open-inventory-modal', (e) => {
                this.type = 'inventory';
                this.currentTaskId = e.detail.taskId;
                this.inventoryTaskName = e.detail.taskName;
                this.photoUrl = e.detail.photoUrl;
                this.inventoryToggleUrl = e.detail.toggleUrl;
                this.inventoryStep = 1;
                this.inventoryQuantity = '';
                this.inventoryError = '';
                this.previewUrl = null;
                this.selectedFile = null;
                this.photoNoteValue = '';
                this.photoUploading = false;
                this.existingPhotos = e.detail.itemPhotos || [];
                this.existingNote = e.detail.noteValue || '';
                this.show = true;
            });
            window.addEventListener('open-gallery', (e) => {
                this.type = 'gallery';
                this.gallerySrc = e.detail.src;
                this.show = true;
            });
            // Update task item itemPhotos when a photo is uploaded via the global modal
            window.addEventListener('photo-uploaded', (e) => {
                const { taskId, photo } = e.detail;
                const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
                if (taskEl && taskEl._x_dataStack) {
                    const data = taskEl._x_dataStack[0];
                    if (data && Array.isArray(data.itemPhotos)) {
                        data.itemPhotos.push(photo);
                    }
                }
            });
            // Update task item noteValue when a note is saved
            window.addEventListener('note-saved', (e) => {
                const { taskId, note } = e.detail;
                const taskEl = document.querySelector(`[data-task-id="${taskId}"]`);
                if (taskEl && taskEl._x_dataStack) {
                    const data = taskEl._x_dataStack[0];
                    if (data !== undefined) {
                        data.noteValue = note;
                    }
                }
            });
        },

        close() {
            this.show = false;
            this.type = null;
        },

        handleFileChange(event) {
            const file = event.target.files[0];
            if (file) {
                this.selectedFile = file;
                this.previewUrl = URL.createObjectURL(file);
            }
        },

        clearPreview() {
            this.previewUrl = null;
            this.selectedFile = null;
        },

        // Inventory: advance from quantity step to photo step
        inventoryNextStep() {
            const qty = parseInt(this.inventoryQuantity);
            if (isNaN(qty) || qty < 0) {
                this.inventoryError = 'Please enter a valid number.';
                return;
            }
            this.inventoryError = '';
            this.inventoryStep = 2;
        },

        // Inventory: upload photo then toggle the task with quantity
        async inventoryUploadAndComplete() {
            if (!this.selectedFile) return;
            this.photoUploading = true;
            const formData = new FormData();
            formData.append('photo', this.selectedFile);
            formData.append('note', 'Inventory qty: ' + this.inventoryQuantity);
            try {
                const res = await window.api.post(this.photoUrl, formData);
                if (res.success) {
                    if (res.photo) {
                        window.dispatchEvent(new CustomEvent('photo-uploaded', {
                            detail: { taskId: this.currentTaskId, photo: res.photo }
                        }));
                    }
                    // Now toggle the task with quantity
                    const qty = parseInt(this.inventoryQuantity) || 0;
                    try {
                        const toggleRes = await window.api.post(this.inventoryToggleUrl, { quantity: qty, force_checked: true });
                        if (toggleRes.success) {
                            // Update the toggle button UI
                            const taskEl = document.querySelector(`[data-task-id="${this.currentTaskId}"]`);
                            if (taskEl) {
                                const toggleBtn = taskEl.querySelector('[data-checklist-toggle]');
                                if (toggleBtn) {
                                    toggleBtn.dataset.checked = 'true';
                                    window.checklistHandler?.updateToggleUI(toggleBtn, true);
                                    window.checklistHandler?.updateLocalAndRerender(toggleBtn, true, toggleRes);
                                }
                            }
                        }
                    } catch (toggleErr) {
                        console.error('Toggle error after inventory upload:', toggleErr);
                    }
                    // Refresh session data so re-render includes the new photo
                    const sessionContainer = document.querySelector('[data-session-id]');
                    const sessionId = sessionContainer?.dataset.sessionId || window.location.pathname.match(/\/sessions\/([^\/]+)/)?.[1];
                    if (sessionId) {
                        await window.checklistHandler?.refreshAndRerender(sessionId);
                    }
                    window.checklistHandler?.showSuccess('Inventory recorded successfully');
                    this.close();
                } else {
                    window.checklistHandler?.showError(res.message || 'Failed to upload photo');
                }
            } catch (e) {
                window.checklistHandler?.showError(e.message || 'Failed to upload photo');
            } finally {
                this.photoUploading = false;
            }
        },

        async saveNote() {
            this.noteSaving = true;
            try {
                const res = await window.api.post(this.noteUrl, { note: this.noteValue });
                if (res.success) {
                    window.dispatchEvent(new CustomEvent('note-saved', {
                        detail: { taskId: this.currentTaskId, note: this.noteValue }
                    }));
                    window.checklistHandler?.showSuccess('Note saved');
                    this.close();
                } else {
                    window.checklistHandler?.showError(res.message || 'Failed to save note');
                }
            } catch (e) {
                window.checklistHandler?.showError('Failed to save note');
            } finally {
                this.noteSaving = false;
            }
        },

        // Check if photo is mandatory for this task type
        get isPhotoRequired() {
            return this.taskType === 'verify' || this.taskType === 'inventory';
        },
        // Check if the Upload/Save button should be enabled
        get canSubmitPhoto() {
            // For verify/inventory, photo is mandatory
            if (this.isPhotoRequired) return !!this.previewUrl;
            // For standard tasks, either a photo or a note (or both) is fine
            return !!(this.previewUrl || this.photoNoteValue.trim());
        },

        async uploadPhoto() {
            // For non-verify/non-inventory: if photo is attached, note is mandatory
            if (!this.isPhotoRequired && this.previewUrl && !this.photoNoteValue.trim()) {
                window.checklistHandler?.showError('Please add a note to describe this photo.');
                return;
            }
            // For verify/inventory: photo is required
            if (this.isPhotoRequired && !this.selectedFile) return;
            // For standard: at least a note
            if (!this.isPhotoRequired && !this.selectedFile && !this.photoNoteValue.trim()) return;
            this.photoUploading = true;
            const formData = new FormData();
            if (this.selectedFile) {
                formData.append('photo', this.selectedFile);
            }
            formData.append('note', this.photoNoteValue);
            try {
                const res = await window.api.post(this.photoUrl, formData);
                if (res.success) {
                    if (res.photo) {
                        window.dispatchEvent(new CustomEvent('photo-uploaded', {
                            detail: { taskId: this.currentTaskId, photo: res.photo }
                        }));
                    }
                    // Auto-tick THIS task only (verify or inventory via photo modal)
                    if (this.photoToggleUrl) {
                        try {
                            await window.api.post(this.photoToggleUrl, { force_checked: true });
                            // Immediately update the toggle button UI
                            const taskEl = document.querySelector(`[data-task-id="${this.currentTaskId}"]`);
                            if (taskEl) {
                                const toggleBtn = taskEl.querySelector('[data-checklist-toggle]');
                                if (toggleBtn) {
                                    toggleBtn.dataset.checked = 'true';
                                    window.checklistHandler?.updateToggleUI(toggleBtn, true);
                                    window.checklistHandler?.updateLocalAndRerender(toggleBtn, true);
                                }
                            }
                        } catch (e) {
                            console.error('Failed to auto-tick task after photo upload', e);
                        }
                    }
                    // Refresh session data so re-render includes the new photo
                    const sessionContainer = document.querySelector('[data-session-id]');
                    const sessionId = sessionContainer?.dataset.sessionId || window.location.pathname.match(/\/sessions\/([^\/]+)/)?.[1];
                    if (sessionId) {
                        await window.checklistHandler?.refreshAndRerender(sessionId);
                    }
                    window.checklistHandler?.showSuccess('Photo uploaded');
                    this.close();
                } else {
                    window.checklistHandler?.showError(res.message || 'Failed to upload');
                }
            } catch (e) {
                window.checklistHandler?.showError(e.message || 'Failed to upload');
            } finally {
                this.photoUploading = false;
            }
        },
    }))

    // Bulk task form (defined inline in blade, but register here for consistency)
    Alpine.data('bulkTaskForm', function (config) {
        return {
            tasks: [],
            defaultType: 'room',
            defaultIsSporadic: config.defaultIsSporadic || false,
            status: null,
            message: '',
            taskInput: null,

            init() {
                this.$refs.taskInput?.focus();
            },

            // Capitalize text to title case (e.g., "Open Windows For Airing")
            capitalizeText(text) {
                if (!text) return '';
                return text.toLowerCase()
                    .split(' ')
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                    .join(' ');
            },

            addTaskFromInput() {
                const input = this.$refs.taskInput;
                if (!input) return;

                const value = input.value.trim();
                if (!value) return;

                // Capitalize the task name
                const capitalizedValue = this.capitalizeText(value);

                // Check for duplicates (case-insensitive)
                const exists = this.tasks.some(t => t.name.toLowerCase() === capitalizedValue.toLowerCase());
                if (exists) {
                    this.showMessage('error', `"${capitalizedValue}" is already in the list`);
                    return;
                }

                this.tasks.push({ name: capitalizedValue });
                input.value = '';
                this.status = null;
            },

            addSuggestedTask(taskName) {
                if (!taskName || !taskName.trim()) return;

                const value = taskName.trim();
                // Capitalize the task name
                const capitalizedValue = this.capitalizeText(value);

                // Check for duplicates (case-insensitive)
                const exists = this.tasks.some(t => t.name.toLowerCase() === capitalizedValue.toLowerCase());
                if (exists) {
                    this.showMessage('error', `"${capitalizedValue}" is already in the list`);
                    return;
                }

                this.tasks.push({ name: capitalizedValue });
                this.status = null;
            },

            handlePaste(event) {
                event.preventDefault();
                const pastedText = (event.clipboardData || window.clipboardData).getData('text');
                const lines = pastedText.split('\n').map(line => line.trim()).filter(line => line.length > 0);

                if (lines.length > 0) {
                    lines.forEach(line => {
                        // Capitalize each line
                        const capitalizedLine = this.capitalizeText(line);
                        const exists = this.tasks.some(t => t.name.toLowerCase() === capitalizedLine.toLowerCase());
                        if (!exists) {
                            this.tasks.push({ name: capitalizedLine });
                        }
                    });
                    this.$refs.taskInput.value = '';
                }
            },

            removeTask(index) {
                this.tasks.splice(index, 1);
            },

            clearAll() {
                if (confirm(`Remove all ${this.tasks.length} tasks from the list?`)) {
                    this.tasks = [];
                    this.status = null;
                }
            },

            showMessage(status, message) {
                this.status = status;
                this.message = message;
                if (status === 'saved') {
                    setTimeout(() => {
                        this.status = null;
                    }, 3000);
                }
            },

            async saveAll() {
                if (this.tasks.length === 0) return;

                this.status = 'saving';
                this.message = `Creating ${this.tasks.length} task(s)...`;

                const formData = new FormData();
                formData.append('_token', config.csrf);
                formData.append('tasks', JSON.stringify(this.tasks.map(t => t.name)));
                formData.append('default_type', this.defaultType);
                formData.append('default_is_sporadic', this.defaultIsSporadic ? '1' : '0');

                try {
                    const response = await fetch(config.storeUrl, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                        }
                    });

                    const data = await response.json();

                    if (response.ok) {
                        this.showMessage('saved', `Successfully created ${data.created || this.tasks.length} task(s)!`);
                        this.tasks = [];

                        // Reload page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        this.showMessage('error', data.message || 'Failed to create tasks. Please try again.');
                    }
                } catch (error) {
                    console.error('Error saving bulk tasks:', error);
                    this.showMessage('error', 'An error occurred while saving tasks. Please try again.');
                }
            }
        };
    });

    // Sporadic Tasks form for session scheduling
    Alpine.data('sporadicTasksForm', (config) => ({
        propertyId: config.initialProperty,
        tasks: [],
        selectedTasks: (config.initialTasks || []).map(String),
        loading: false,

        init() {
            if (this.propertyId) {
                this.fetchTasks();
            }
        },

        async fetchTasks() {
            if (!this.propertyId) {
                this.tasks = [];
                return;
            }
            this.loading = true;
            try {
                const res = await fetch(`/api/properties/${this.propertyId}/sporadic-tasks`);
                if (res.ok) {
                    this.tasks = await res.json();
                } else {
                    this.tasks = [];
                }
            } catch (e) {
                console.error('Failed to load sporadic tasks', e);
                this.tasks = [];
            } finally {
                this.loading = false;
            }
        }
    }));
})

Alpine.plugin(collapse)
Alpine.start()
