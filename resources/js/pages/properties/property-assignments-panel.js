// resources/js/pages/properties/property-assignments-panel.js

function phaseLabel(phase) {
    if (!phase) return ''
    return phase
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase())
}

export default function propertyAssignmentsPanel({
    roomsUrl,
    tasksUrl,
    manageRoomsUrl,
    manageTasksUrl,
}) {
    return {
        roomsUrl,
        tasksUrl,
        manageRoomsUrl,
        manageTasksUrl,

        rooms: [],
        tasks: [],

        loadingRooms: false,
        loadingTasks: false,
        roomsError: '',
        tasksError: '',

        phaseLabel,

        init() {
            this.refreshAll()
        },

        async refreshAll() {
            await Promise.all([this.fetchRooms(), this.fetchTasks()])
        },

        async fetchRooms() {
            this.loadingRooms = true
            this.roomsError = ''
            try {
                const res = await fetch(this.roomsUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                const data = await res.json()
                if (!res.ok) {
                    throw new Error(data?.message || 'Failed to load rooms')
                }
                this.rooms = Array.isArray(data.rooms) ? data.rooms : []
            } catch (e) {
                console.error(e)
                this.roomsError =
                    e?.message || 'Unable to load assigned rooms right now.'
                this.rooms = []
            } finally {
                this.loadingRooms = false
            }
        },

        async fetchTasks() {
            this.loadingTasks = true
            this.tasksError = ''
            try {
                const res = await fetch(this.tasksUrl, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                const data = await res.json()
                if (!res.ok) {
                    throw new Error(data?.message || 'Failed to load tasks')
                }
                this.tasks = Array.isArray(data.tasks) ? data.tasks : []
            } catch (e) {
                console.error(e)
                this.tasksError =
                    e?.message ||
                    'Unable to load assigned property tasks right now.'
                this.tasks = []
            } finally {
                this.loadingTasks = false
            }
        },
    }
}

