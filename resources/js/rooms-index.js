// resources/js/rooms-index.js

export default () => ({
    allRoomIds: [],
    selectedRoomIds: [],
    selectAll: false,

    tasks: [],
    taskSearch: '',
    taskTypeFilter: '', // '', 'room', 'inventory', etc.
    selectedTaskIds: [],

    isSubmittingBulk: false,
    bulkUrl: '',
    csrfToken: '',

    init() {
        // Load data from data-* attributes on the root element
        this.allRoomIds = JSON.parse(this.$el.dataset.rooms || '[]')
        this.tasks = JSON.parse(this.$el.dataset.tasks || '[]')
        this.bulkUrl = this.$el.dataset.bulkUrl || ''
        this.csrfToken = this.$el.dataset.csrf || ''
    },

    toggleSelectAll() {
        if (this.selectAll) {
            this.selectedRoomIds = [...this.allRoomIds]
        } else {
            this.selectedRoomIds = []
        }
    },

    filteredTasks() {
        const q = this.taskSearch.toLowerCase().trim()
        const type = this.taskTypeFilter

        return this.tasks.filter((task) => {
            const matchesSearch =
                !q || task.name.toLowerCase().includes(q)

            const matchesType =
                !type || task.type === type

            return matchesSearch && matchesType
        })
    },

    async submitBulkAssign() {
        if (!this.selectedRoomIds.length || !this.selectedTaskIds.length) {
            return
        }

        this.isSubmittingBulk = true

        try {
            const res = await fetch(this.bulkUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    room_ids: this.selectedRoomIds,
                    task_ids: this.selectedTaskIds,
                }),
            })

            if (!res.ok) {
                throw new Error('Request failed')
            }

            window.location.reload()
        } catch (error) {
            console.error('Bulk assign failed', error)
            alert('Something went wrong while assigning tasks. Please try again.')
        } finally {
            this.isSubmittingBulk = false
        }
    },
})
