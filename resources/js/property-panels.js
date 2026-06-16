// resources/js/property-panels.js
//
// Central place for Alpine "panel" helper factories previously embedded in Blade.
// These functions are used via x-data="..." in Blade templates, so we attach them to window.

/**
 * Assign rooms preview-panel helper
 * Used by: resources/views/properties/__assign_rooms_panel.blade.php
 */
window.assignRoomsPanel = function assignRoomsPanel(
    propertyId,
    allRooms,
    initialAttachedIds = [],
    propertyRoomSoreUrl,
    propertyRoomsAttachUrl
) {
    return {
        propertyId,
        rooms: allRooms,
        search: '',
        selectedIds: Array.from(new Set(initialAttachedIds)), // preselect attached rooms
        newRoomName: '',
        isSaving: false,
        isCreating: false,

        get filtered() {
            if (!this.search) return this.rooms
            const q = this.search.toLowerCase()
            return this.rooms.filter((r) =>
                (r?.name || '').toLowerCase().includes(q)
            )
        },

        isSelected(id) {
            return this.selectedIds.includes(id)
        },

        toggle(id) {
            if (this.isSelected(id)) {
                this.selectedIds = this.selectedIds.filter((x) => x !== id)
            } else {
                this.selectedIds.push(id)
            }
        },

        selectAll() {
            this.selectedIds = this.filtered.map((r) => r.id)
        },

        clearSelection() {
            this.selectedIds = []
        },

        async createRoom() {
            if (!this.newRoomName.trim()) return

            this.isCreating = true
            try {
                // Keep behavior consistent with prior inline implementation
                window.api?.post?.(propertyRoomSoreUrl, {
                    name: this.newRoomName.trim(),
                })

                const newId = Date.now()
                this.rooms.push({
                    id: newId,
                    name: this.newRoomName.trim(),
                    is_default: false,
                })
                this.selectedIds.push(newId)
                this.newRoomName = ''
            } finally {
                this.isCreating = false
            }
        },

        async save() {
            this.isSaving = true
            try {
                const response = await window.api.post(propertyRoomsAttachUrl, {
                    room_ids: this.selectedIds,
                })

                this.$dispatch('toast', {
                    type: 'success',
                    message: response.message || 'Rooms attached successfully!',
                })

                this.$dispatch('close-preview-panel', `assign-rooms-${this.propertyId}`)
                window.location.reload()
            } catch (e) {
                let errorMessage = 'Failed to attach rooms. Please try again.'

                if (e?.response?.data) {
                    const data = e.response.data
                    errorMessage = data.message || data.error || errorMessage

                    if (data.errors) {
                        const firstError = Object.values(data.errors)[0]
                        errorMessage = Array.isArray(firstError)
                            ? firstError[0]
                            : firstError
                    }
                } else if (e?.message) {
                    errorMessage = e.message
                }

                this.$dispatch('toast', {
                    type: 'error',
                    message: errorMessage,
                })
            } finally {
                this.isSaving = false
            }
        },
    }
}

/**
 * Default rooms preview panel helper
 * Used by: resources/views/properties/__preview_panel.blade.php
 */
window.roomsPreview = function roomsPreview(rooms = []) {
    return {
        rooms,
        search: '',
        selectedIds: [],

        get filtered() {
            if (!this.search) return this.rooms
            const q = this.search.toLowerCase()
            return this.rooms.filter((r) =>
                (r?.name || '').toLowerCase().includes(q)
            )
        },

        isSelected(id) {
            return this.selectedIds.includes(id)
        },

        toggle(id) {
            if (this.isSelected(id)) {
                this.selectedIds = this.selectedIds.filter((x) => x !== id)
            } else {
                this.selectedIds.push(id)
            }
        },

        selectAll() {
            this.selectedIds = this.filtered.map((r) => r.id)
        },

        clearSelection() {
            this.selectedIds = []
        },
    }
}

/**
 * Duplicate property panel helper
 * Used by: resources/views/properties/__duplicate_property_panel.blade.php
 */
window.duplicatePropertyPanel = function duplicatePropertyPanel({
    initialName = '',
    rooms = [],
    tasks = [],
    selectedRoomIds = [],
    selectedTaskIds = [],
} = {}) {
    return {
        newName: initialName,
        rooms,
        tasks,
        roomSearch: '',
        taskSearch: '',
        selectedRoomIds: Array.from(new Set(selectedRoomIds)),
        selectedTaskIds: Array.from(new Set(selectedTaskIds)),

        get filteredRooms() {
            if (!this.roomSearch) return this.rooms
            const q = this.roomSearch.toLowerCase()
            return this.rooms.filter((r) =>
                (r?.name || '').toLowerCase().includes(q)
            )
        },
        get filteredTasks() {
            if (!this.taskSearch) return this.tasks
            const q = this.taskSearch.toLowerCase()
            return this.tasks.filter((t) =>
                (t?.name || '').toLowerCase().includes(q)
            )
        },

        isRoomSelected(id) {
            return this.selectedRoomIds.includes(id)
        },
        isTaskSelected(id) {
            return this.selectedTaskIds.includes(id)
        },

        toggleRoom(id) {
            if (this.isRoomSelected(id)) {
                this.selectedRoomIds = this.selectedRoomIds.filter((x) => x !== id)
            } else {
                this.selectedRoomIds.push(id)
            }
        },
        toggleTask(id) {
            if (this.isTaskSelected(id)) {
                this.selectedTaskIds = this.selectedTaskIds.filter((x) => x !== id)
            } else {
                this.selectedTaskIds.push(id)
            }
        },

        selectAllRooms() {
            this.selectedRoomIds = this.filteredRooms.map((r) => r.id)
        },
        clearRooms() {
            this.selectedRoomIds = []
        },

        selectAllTasks() {
            this.selectedTaskIds = this.filteredTasks.map((t) => t.id)
        },
        clearTasks() {
            this.selectedTaskIds = []
        },
    }
}

