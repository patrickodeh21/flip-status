/**
 * Checklist Renderer - Renders checklist dynamically from API data
 * Reduces Blade code by handling all rendering in JavaScript
 */

export default function checklistRenderer(config = {}) {
    return {
        sessionId: null,
        reportUrl: null,
        sessionData: null,
        loading: false,
        error: null,
        renderedContent: '',
        dataUrl: config.dataUrl || null,
        fallbackDataUrl: null,
        photoDeleteUrl: config.photoDeleteUrl || null,
        _showPhotoUpload: false,
        _showPhotoExtrasPrompt: false,

        init() {
            // Get session ID from data attribute or URL
            const container = document.querySelector('[data-session-id]');
            this.sessionId = container?.dataset.sessionId ||
                window.location.pathname.match(/\/sessions\/([^\/]+)/)?.[1];
            this.reportUrl = container?.dataset.reportUrl || null;

            if (!this.sessionId) {
                console.error('Session ID not found');
                return;
            }

            // Get CSRF token
            this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            // Build data URL if not provided (fallback for backward compatibility)
            // Prefer the non-API route, but keep an API fallback to avoid breaking older/newer backends
            if (!this.dataUrl) {
                this.dataUrl = `/sessions/${this.sessionId}/data`;
            }
            this.fallbackDataUrl = `/api/sessions/${this.sessionId}/data`;

            // Load initial data
            this.loadSessionData();
        },

        async loadSessionData() {
            this.loading = true;
            this.error = null;

            try {
                let response;
                try {
                    response = await window.api.get(this.dataUrl);
                } catch (e) {
                    // If the non-API route isn't available, fall back to the API route
                    const status = e?.response?.status;
                    if (status === 404 && this.fallbackDataUrl) {
                        response = await window.api.get(this.fallbackDataUrl);
                    } else {
                        throw e;
                    }
                }

                if (response.success && response.data) {
                    this.sessionData = response.data;
                    this.renderChecklist();
                } else {
                    throw new Error('Failed to load session data');
                }
            } catch (error) {
                console.error('Error loading session data:', error);

                // Provide user-friendly error messages
                const status = error.response?.status;
                let errorMessage = 'Failed to load checklist. Please try again.';

                if (status === 401 || status === 403) {
                    errorMessage = 'You do not have permission to access this checklist. Please ensure you are logged in with the correct account.';
                } else if (status === 404) {
                    errorMessage = 'This session could not be found. It may have been deleted or you may not have access.';
                } else if (status === 500) {
                    errorMessage = 'A server error occurred. Please try again or contact support if the problem persists.';
                } else if (!navigator.onLine) {
                    errorMessage = 'You appear to be offline. Please check your internet connection and try again.';
                } else if (error.response?.data?.message) {
                    errorMessage = error.response.data.message;
                } else if (error.message) {
                    errorMessage = error.message;
                }

                this.error = errorMessage;
            } finally {
                this.loading = false;
            }
        },

        async skipRoom(roomId) {
            if (!confirm('Are you sure you want to skip this room?')) return;
            try {
                const response = await window.api.post(`/sessions/${this.sessionId}/rooms/${roomId}/skip`);
                if (response.success) {
                    // Update local data
                    if (!this.sessionData.session.skipped_rooms) this.sessionData.session.skipped_rooms = [];
                    this.sessionData.session.skipped_rooms.push(roomId);
                    this.renderChecklist();
                }
            } catch (error) {
                console.error('Error skipping room:', error);
                alert('Failed to skip room.');
            }
        },

        async unskipRoom(roomId) {
            try {
                const response = await window.api.post(`/sessions/${this.sessionId}/rooms/${roomId}/unskip`);
                if (response.success) {
                    // Update local data
                    this.sessionData.session.skipped_rooms = this.sessionData.session.skipped_rooms.filter(id => id !== roomId);
                    this.renderChecklist();
                }
            } catch (error) {
                console.error('Error unskipping room:', error);
                alert('Failed to unskip room.');
            }
        },

        renderChecklist() {
            if (!this.sessionData) return;

            const stage = this.sessionData.stage;

            // Update stage indicator
            const stageElements = document.querySelectorAll('[data-stage-area]');
            const stageCurrentElements = document.querySelectorAll('[data-current-stage]');
            const reportUrlContainer = document.querySelector('[data-report-url]');
            const reportUrl = reportUrlContainer ? reportUrlContainer.dataset.reportUrl : null;
            
            const stageName = stage ? stage.replace(/_/g, ' ').split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ') : 'Unknown';
            const stageBadgeHtml = `<span class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200">${stageName}</span>`;
            const reportLinkHtml = reportUrl ? `<a href="${reportUrl}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-3 py-2 text-sm font-medium text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20">View Report</a>` : stageBadgeHtml;

            stageElements.forEach(el => {
                if (stage === 'summary' && reportUrl) {
                    el.innerHTML = reportLinkHtml;
                } else {
                    el.innerHTML = stageBadgeHtml;
                }
            });

            stageCurrentElements.forEach(el => {
                el.textContent = stageName;
            });

            // Store rendered HTML in Alpine reactive property
            let renderedHtml = '';

            // Reset photo prompt when re-rendering (unless we're on photos stage and user already clicked Yes)
            if (stage !== 'photos') {
                this._showPhotoUpload = false;
                this._showPhotoExtrasPrompt = false;
            }

            // Dynamically update status badges on the page
            if (this.sessionData.session?.status === 'completed' || stage === 'summary') {
                document.querySelectorAll('[data-status-badge]').forEach(el => {
                    el.innerHTML = `<span class="inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                        <svg class="-ml-1 mr-1.5 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Completed
                    </span>`;
                });
            }

            // Render based on stage
            switch (stage) {
                case 'pre_cleaning':
                    renderedHtml = this.renderPropertyTasks('pre_cleaning', 'Pre-Cleaning Tasks');
                    break;
                case 'rooms':
                case 'rooms_first_half':  // Legacy fallback
                case 'rooms_second_half': // Legacy fallback
                    renderedHtml = this.renderRooms();
                    break;
                case 'during_cleaning':
                    renderedHtml = this.renderPropertyTasks('during_cleaning', 'Mid-Cleaning Tasks');
                    break;
                case 'photos':
                    renderedHtml = this.renderPhotosRoomByRoom();
                    break;
                case 'post_cleaning':
                    renderedHtml = this.renderPropertyTasks('post_cleaning', 'End-of-Cleaning Tasks');
                    break;
                case 'inventory':
                    renderedHtml = this.renderInventory();
                    break;
                case 'summary':
                    renderedHtml = this.renderSummary();
                    break;
                default:
                    renderedHtml = '<p class="text-gray-500">Unknown stage</p>';
            }

            // Store in Alpine reactive property
            this.renderedContent = renderedHtml;

            // Use setTimeout to ensure Alpine processes the update and DOM is ready
            setTimeout(() => {
                // Re-initialize event handlers after rendering
                this.setupEventHandlers();
            }, 100);
        },

        renderPropertyTasks(phase, title) {
            // Ensure tasks is an array
            let tasks = this.sessionData.property_tasks[phase] || [];
            if (!Array.isArray(tasks)) {
                tasks = Object.values(tasks);
            }
            const counts = this.sessionData.counts[phase] || { total: 0, checked: 0 };

            if (tasks.length === 0) {
                return `
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">No ${title.toLowerCase()} defined.</p>
                    </div>
                `;
            }

            return `
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <!-- Back Button -->
                    <div class="mb-4">
                        <button type="button"
                                @click="
                                    $el.disabled = true;
                                    $el.textContent = 'Going back...';
                                    window.api.post('/sessions/${this.sessionId}/go-back-stage', { current_stage: '${phase}' })
                                    .then(res => {
                                        if (res.success && res.data) {
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0].sessionData = res.data;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        } else {
                                            window.location.reload();
                                        }
                                    })
                                    .catch(() => { window.location.reload(); });
                                "
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Go Back
                        </button>
                    </div>
                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">${title}</h2>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">${counts.checked}/${counts.total}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">completed</div>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="h-full rounded-full bg-blue-600 transition-all duration-500"
                                 style="width: ${counts.total > 0 ? (counts.checked / counts.total * 100) : 0}%"></div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        ${tasks.map(task => this.renderTaskItem(task, null)).join('')}
                    </div>
                    ${(() => {
                        return `
                        <div class="mt-6">
                            <button type="button"
                                    @click="window.checklistHandler.saveProgress()"
                                    class="w-full px-6 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors font-semibold text-lg shadow-sm cursor-pointer flex items-center justify-center gap-2">
                                Next Stage <span class="text-xl">→</span>
                            </button>
                        </div>
                    `;
                    })()}
                </div>
            `;
        },

        renderRooms() {
            const stepBtn = document.querySelector('button#stepBtn');
            if (stepBtn) stepBtn.classList.remove('hidden');
            // Ensure rooms is an array
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                rooms = Object.values(rooms);
            }

            // Calculate which rooms are complete and which should be enabled
            let firstIncompleteIndex = null;
            rooms.forEach((room, index) => {
                // Ensure room.tasks is an array
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                // Exclude instruction-only tasks from counts
                const countableTasks = roomTasks.filter(t => t.type !== 'instructions');
                const checkedCount = countableTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = countableTasks.length;

                // If this room is incomplete and we haven't found the first incomplete yet
                if (firstIncompleteIndex === null && checkedCount < totalCount && totalCount > 0) {
                    firstIncompleteIndex = index;
                }
            });

            return `
                <div class="space-y-6">
                    ${rooms.map((room, index) => {
                // Ensure room.tasks is an array
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                // Exclude instruction-only tasks from counts
                const countableTasks = roomTasks.filter(t => t.type !== 'instructions');
                const checkedCount = countableTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = countableTasks.length;
                const isComplete = checkedCount === totalCount && totalCount > 0;
                // Check if room is skipped
                const skippedRooms = this.sessionData.session.skipped_rooms || [];
                const isSkipped = skippedRooms.includes(room.id);
                // Room is disabled if skipped (visual style)
                const isDisabled = isSkipped;

                return `
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5 ${isDisabled ? 'opacity-60' : ''}"
                                 data-room-id="${room.id}" data-room-index="${index}">
                                <div class="mb-6">
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                        ${isDisabled ? `
                                            <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400">
                                                Complete previous rooms first
                                            </span>
                                        ` : ''}
                                        ${isComplete ? `
                                            <span class="text-xs px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">
                                                ✓ Complete
                                            </span>
                                        ` : ''}
                                        ${isSkipped ? `
                                            <span class="text-xs px-2 py-1 rounded bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200">
                                                Skipped
                                            </span>
                                        ` : ''}
                                        <div class="ml-2">
                                            ${isSkipped ? `
                                                <button type="button" @click.stop="unskipRoom(${room.id})" class="text-xs text-blue-600 hover:text-blue-800 underline">Unskip</button>
                                            ` : `
                                                <button type="button" @click.stop="skipRoom(${room.id})" class="text-xs text-gray-500 hover:text-gray-700 underline">Skip</button>
                                            `}
                                        </div>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="h-full rounded-full bg-blue-600 transition-all duration-500"
                                             style="width: ${totalCount > 0 ? (checkedCount / totalCount * 100) : 0}%"></div>
                                    </div>
                                    <div class="flex items-center justify-between mt-1.5">
                                        <span class="text-[10px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Progress</span>
                                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400">${checkedCount}/${totalCount}</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    ${roomTasks.map(task => this.renderTaskItem(task, room, isDisabled)).join('')}
                                </div>
                            </div>
                        `;
            }).join('')}
                    <div class="mt-6">
                        <button type="button"
                                @click="window.checklistHandler.saveProgress()"
                                class="w-full px-6 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors font-semibold text-lg shadow-sm cursor-pointer flex items-center justify-center gap-2">
                            Next Stage <span class="text-xl">→</span>
                        </button>
                    </div>
                </div>
            `;
        },

        renderRoomsSubset(stageKey, title) {
            const stepBtn = document.querySelector('button#stepBtn');
            if (stepBtn) stepBtn.classList.remove('hidden');

            let rooms = this.sessionData[stageKey] || [];
            if (!Array.isArray(rooms)) rooms = Object.values(rooms);

            // Back button HTML
            const backBtn = `
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <button type="button"
                            @click="
                                $el.disabled = true;
                                $el.textContent = 'Going back...';
                                window.api.post('/sessions/${this.sessionId}/go-back-stage', { current_stage: '${stageKey}' })
                                .then(res => {
                                    if (res.success && res.data) {
                                        const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                        if (rendererEl && rendererEl._x_dataStack) {
                                            rendererEl._x_dataStack[0].sessionData = res.data;
                                            rendererEl._x_dataStack[0].renderChecklist();
                                        }
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    } else {
                                        window.location.reload();
                                    }
                                })
                                .catch(() => { window.location.reload(); });
                            "
                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Go Back
                    </button>
                </div>
            `;

            // Title header
            const header = `
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">${title}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${rooms.length} room${rooms.length !== 1 ? 's' : ''} in this section</p>
                </div>
            `;

            return `
                <div class="space-y-6">
                    ${backBtn}
                    ${header}
                    ${rooms.map((room, index) => {
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                const countableTasks = roomTasks.filter(t => t.type !== 'instructions');
                const checkedCount = countableTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = countableTasks.length;
                const isComplete = checkedCount === totalCount && totalCount > 0;
                const skippedRooms = this.sessionData.session.skipped_rooms || [];
                const isSkipped = skippedRooms.includes(room.id);
                const isDisabled = isSkipped;

                return `
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5 ${isDisabled ? 'opacity-60' : ''}"
                         data-room-id="${room.id}" data-room-index="${index}">
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                ${isComplete ? '<span class="text-xs px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200">✓ Complete</span>' : ''}
                                ${isSkipped ? '<span class="text-xs px-2 py-1 rounded bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200">Skipped</span>' : ''}
                                <div class="ml-2">
                                    ${isSkipped ? `<button type="button" @click.stop="unskipRoom(${room.id})" class="text-xs text-blue-600 hover:text-blue-800 underline">Unskip</button>` : `<button type="button" @click.stop="skipRoom(${room.id})" class="text-xs text-gray-500 hover:text-gray-700 underline">Skip</button>`}
                                </div>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="h-full rounded-full bg-blue-600 transition-all duration-500"
                                     style="width: ${totalCount > 0 ? (checkedCount / totalCount * 100) : 0}%"></div>
                            </div>
                            <div class="flex items-center justify-between mt-1.5">
                                <span class="text-[10px] sm:text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Progress</span>
                                <span class="text-xs font-bold text-blue-600 dark:text-blue-400">${checkedCount}/${totalCount}</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            ${roomTasks.map(task => this.renderTaskItem(task, room, isDisabled)).join('')}
                        </div>
                    </div>
                `;
            }).join('')}
                    ${(() => {
                        let incompleteRoomTasks = [];
                        rooms.forEach(r => {
                            const rTasksArr = Array.isArray(r.tasks) ? r.tasks : Object.values(r.tasks || {});
                            const rTasks = rTasksArr.filter(t => r.room_tasks.includes(t.id));
                            rTasks.forEach(t => {
                                if (t.type !== 'instructions' && (t.type === 'verify' || t.type === 'inventory') && !t.checklist_item?.checked) {
                                    incompleteRoomTasks.push({ roomName: r.name, taskName: t.name, taskType: t.type });
                                }
                            });
                        });
                        const hasBlocking = incompleteRoomTasks.length > 0;
                        return `
                        <div x-data="{ showWarning: false }">
                            ${hasBlocking ? `
                                <div x-show="showWarning" x-cloak class="mt-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        <div>
                                            <p class="font-semibold text-amber-800 dark:text-amber-200 text-sm">Cannot advance yet</p>
                                            <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">Complete these verify/inventory tasks before advancing:</p>
                                            <ul class="mt-2 space-y-1">
                                                ${incompleteRoomTasks.map(t => `<li class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span><span class="font-medium">${t.roomName}</span> — ${t.taskName} <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200">${t.taskType}</span></li>`).join('')}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            ` : ''}
                            <div class="mt-6">
                                <button type="button"
                                        @click="${hasBlocking ? 'showWarning = true' : 'window.checklistHandler.saveProgress()'}"
                                        class="w-full px-6 py-4 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-colors font-semibold text-lg shadow-sm cursor-pointer flex items-center justify-center gap-2">
                                    Next Stage <span class="text-xl">→</span>
                                </button>
                            </div>
                        </div>
                    `;
                    })()}
                </div>
            `;
        },

        renderPhotosRoomByRoom() {
            const stepBtn = document.querySelector('button#stepBtn');
            if (stepBtn) stepBtn.classList.add('hidden');

            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) rooms = Object.values(rooms);

            const photoCounts = this.sessionData.photo_counts || {};
            const photosByRoom = this.sessionData.photos_by_room || {};

            // Calculate total photos uploaded
            let totalPhotos = 0;
            rooms.forEach(r => { totalPhotos += (photoCounts[r.id] || 0); });

            // Phase 1: Show mandatory room photos first (skip prompt)
            // Phase 2: After clicking "Finish Photos", show the extras prompt
            if (this._showPhotoExtrasPrompt) {
                // Show the "any extras?" prompt after mandatory photos are done
                return `
                    <div class="space-y-6">
                        <!-- Go Back to Photos -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                            <button type="button"
                                    @click="
                                        const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                        if (rendererEl && rendererEl._x_dataStack) {
                                            rendererEl._x_dataStack[0]._showPhotoExtrasPrompt = false;
                                            rendererEl._x_dataStack[0]._showPhotoUpload = true;
                                            rendererEl._x_dataStack[0].renderChecklist();
                                        }
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    "
                                    class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Go Back to Photos
                            </button>
                        </div>

                        <!-- Photo Extras Prompt Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                            <div class="flex justify-center mb-4">
                                <div class="w-16 h-16 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Do you have any photos to add before the report is closed?</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">${totalPhotos > 0 ? totalPhotos + ' photo' + (totalPhotos !== 1 ? 's' : '') + ' uploaded so far.' : 'No photos uploaded yet.'}</p>
                            <div class="flex items-center justify-center gap-3">
                                <button type="button"
                                        @click="
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0]._showPhotoExtrasPrompt = false;
                                                rendererEl._x_dataStack[0]._showPhotoUpload = true;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                        "
                                        class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold cursor-pointer">
                                    Yes, Add Photos
                                </button>
                                <button type="button"
                                        @click="window.checklistHandler.saveProgress()"
                                        class="px-6 py-2.5 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors font-semibold cursor-pointer">
                                    No, Continue
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Default: always show mandatory room photo uploads first
            if (!this._showPhotoUpload) {
                this._showPhotoUpload = true;
            }

            // --- Full photo upload UI below (shown after clicking "Yes, Add Photos") ---

            // Find first room without enough photos (minimum 2)
            let currentRoomIndex = 0;
            if (this._photoRoomIndex === undefined || this._photoRoomIndex === null) {
                this._photoRoomIndex = parseInt(localStorage.getItem(`session_${this.sessionId}_photo_room`) || '0');
            }
            currentRoomIndex = this._photoRoomIndex;
            if (currentRoomIndex >= rooms.length) currentRoomIndex = rooms.length - 1;

            const room = rooms[currentRoomIndex];
            if (!room) {
                return `
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <p class="text-gray-500 mb-6">No rooms to photograph.</p>
                        <button type="button" @click="window.checklistHandler.saveProgress()" class="px-6 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
                            Continue to Finish
                        </button>
                    </div>
                `;
            }

            const photoCount = photoCounts[room.id] || 0;
            const minPhotos = Number(room.min_photos ?? 2);
            const photos = photosByRoom[room.id] || [];

            // Check for incomplete mandatory tasks across all rooms
            let incompleteTasks = [];
            rooms.forEach(r => {
                const rTasksArray = Array.isArray(r.tasks) ? r.tasks : Object.values(r.tasks || {});
                const rTasks = rTasksArray.filter(t => r.room_tasks.includes(t.id));
                rTasks.forEach(task => {
                    if (task.type === 'instructions') return;
                    const checked = task.checklist_item?.checked || false;
                    if (!checked && (task.type === 'inventory' || task.type === 'verify')) {
                        incompleteTasks.push({ roomName: r.name, taskName: task.name, taskType: task.type });
                    }
                });
            });
            ['pre_cleaning', 'during_cleaning', 'post_cleaning'].forEach(phase => {
                const tasks = this.sessionData.property_tasks?.[phase] || [];
                (Array.isArray(tasks) ? tasks : Object.values(tasks)).forEach(task => {
                    if (task.type === 'instructions') return;
                    const checked = task.checklist_item?.checked || false;
                    if (!checked && (task.type === 'inventory' || task.type === 'verify')) {
                        incompleteTasks.push({ roomName: phase.replace(/_/g, ' '), taskName: task.name, taskType: task.type });
                    }
                });
            });
            const hasIncomplete = incompleteTasks.length > 0;

            // Progress indicator for all rooms
            const progressDots = rooms.map((r, i) => {
                const pc = photoCounts[r.id] || 0;
                const rMin = Number(r.min_photos ?? 2);
                const isActive = i === currentRoomIndex;
                const isDone = pc >= rMin;
                return `<div class="flex flex-col items-center gap-1 cursor-pointer"
                        @click="
                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                            if (rendererEl && rendererEl._x_dataStack) {
                                rendererEl._x_dataStack[0]._photoRoomIndex = ${i};
                                localStorage.setItem('session_' + rendererEl._x_dataStack[0].sessionId + '_photo_room', ${i});
                                rendererEl._x_dataStack[0].refresh();
                            }
                            window.scrollTo({ top: 0, behavior: 'smooth' });
                        ">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-all
                        ${isActive ? 'border-blue-600 bg-blue-600 text-white' : isDone ? 'border-green-500 bg-green-500 text-white' : 'border-gray-300 dark:border-gray-600 text-gray-400'}"
                    >${i + 1}</div>
                    <span class="text-[10px] text-gray-500 dark:text-gray-400 truncate max-w-[60px] text-center">${r.name}</span>
                </div>`;
            }).join('');

            const deleteUrlConfig = this.photoDeleteUrl ? `, { deleteUrl: '${this.photoDeleteUrl}' }` : '';

            return `
                <div class="space-y-6">
                    <!-- Back Button -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <button type="button"
                                @click="
                                    $el.disabled = true;
                                    $el.textContent = 'Going back...';
                                    window.api.post('/sessions/${this.sessionId}/go-back-stage', { current_stage: 'photos' })
                                    .then(res => {
                                        if (res.success && res.data) {
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0].sessionData = res.data;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        } else {
                                            window.location.reload();
                                        }
                                    })
                                    .catch(() => { window.location.reload(); });
                                "
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Go Back
                        </button>
                    </div>

                    <!-- Room Progress Dots -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-3">Room Photos</h2>
                        <div class="flex items-center justify-center gap-3 flex-wrap">
                            ${progressDots}
                        </div>
                    </div>

                    <!-- Current Room Photo Upload -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5" data-room-photos data-room-id="${room.id}">
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                <span class="text-xs font-bold ${photoCount < minPhotos ? 'text-amber-500' : 'text-gray-500 dark:text-gray-400'}" data-photo-count>${photoCount} / ${minPhotos} Photos Req.</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Room ${currentRoomIndex + 1} of ${rooms.length} — Take photos of this room</p>
                        </div>

                        <div x-data="photoUploader(${room.id})" class="mb-6">
                            <form method="post" enctype="multipart/form-data"
                                  action="/sessions/${this.sessionId}/rooms/${room.id}/photos"
                                  data-checklist-photo-form
                                  data-room-id="${room.id}"
                                  @submit.prevent.stop="handleSubmit($event)">

                                <div class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 sm:p-6 mb-4
                                           border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50
                                           hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors cursor-pointer"
                                     @dragover.prevent="hover = true"
                                     @dragleave.prevent="hover = false"
                                     @drop.prevent="handleDrop($event)"
                                     @click="$refs.fileInput.click()"
                                     :class="hover ? 'border-blue-400 bg-blue-50/50 dark:bg-blue-900/10' : ''">
                                    <svg class="h-8 w-8 sm:h-10 sm:w-10 text-gray-400 dark:text-gray-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 text-center px-2">
                                        <span class="text-blue-600 dark:text-blue-400 font-medium">Tap to take a photo</span>
                                    </p>
                                    <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-500 mt-1">Take photos — click Upload when ready</p>

                                    <input x-ref="fileInput"
                                           type="file"
                                           name="photos[]"
                                           accept="image/*"
                                           capture="environment"
                                           class="hidden"
                                           @change="handleFiles($event)" />
                                </div>

                                <!-- Upload Button Moved Above Previews -->
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 mb-4" x-show="previews.length > 0" x-cloak>
                                    <button type="submit"
                                            :disabled="previews.length === 0 || uploading"
                                            class="w-full sm:flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium text-sm shadow-sm">
                                        <span x-show="!uploading">Upload <span x-text="previews.length"></span> Photo<span x-show="previews.length !== 1">s</span></span>
                                        <span x-show="uploading" class="flex items-center justify-center gap-2">
                                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Uploading...
                                        </span>
                                    </button>
                                </div>
                                
                                <div x-show="uploading && uploadProgress > 0" x-cloak class="mb-4">
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" :style="'width: ' + uploadProgress + '%'"></div>
                                    </div>
                                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 text-center" x-text="uploadProgress + '%'"></p>
                                </div>

                                <div x-show="previews.length > 0" x-cloak class="mb-4">
                                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                        <template x-for="(preview, index) in previews" :key="index">
                                            <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                                                <img :src="preview.url" class="w-full aspect-square object-cover" :alt="'Preview ' + (index + 1)" loading="lazy" />
                                                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <button type="button" @click.stop="removePreview(index)" class="px-2 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 text-xs font-medium">Remove</button>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </form>
                        </div>

                        ${photos.length > 0 ? `
                            <div data-photo-gallery class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                ${photos.map(photo => {
                    const photoUrl = photo.url || '';
                    const timeStr = photo.captured_at ? new Date(photo.captured_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
                    return `
                        <div class="relative group"
                             x-data="photoDeleteHandler(${photo.id}, '${this.sessionId}'${deleteUrlConfig})"
                             data-photo-id="${photo.id}">
                            <button type="button" @click="fullscreen = true" class="w-full">
                                <img src="${photoUrl}" alt="Photo" width="300" height="300" class="aspect-square w-full object-cover rounded-xl border transition hover:opacity-90" loading="lazy" />
                                ${timeStr ? `<span class="absolute bottom-1 right-1 text-[10px] px-1.5 py-0.5 rounded bg-black/60 text-white">${timeStr}</span>` : ''}
                            </button>
                            <button type="button" @click.stop="handleDeletePhoto()" :disabled="deleting" class="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700" title="Delete photo">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                            <div x-show="fullscreen" x-cloak @click.self="fullscreen = false" @keydown.escape.window="fullscreen = false" class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-2">
                                <img src="${photoUrl}" class="max-h-full max-w-full object-contain rounded-lg" alt="Fullscreen photo" />
                                <button type="button" @click="fullscreen = false" class="absolute top-2 right-2 text-white text-2xl hover:text-gray-300 bg-black/50 rounded-full w-8 h-8 flex items-center justify-center">×</button>
                            </div>
                        </div>
                    `;
                }).join('')}
                            </div>
                        ` : `
                            <p class="text-center text-gray-500 dark:text-gray-400 py-4">No photos uploaded yet for this room.</p>
                        `}
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <button type="button"
                                    ${currentRoomIndex === 0 ? 'disabled' : ''}
                                    @click="
                                        const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                        if (rendererEl && rendererEl._x_dataStack) {
                                            const r = rendererEl._x_dataStack[0];
                                            r._photoRoomIndex = ${currentRoomIndex - 1};
                                            localStorage.setItem('session_' + r.sessionId + '_photo_room', r._photoRoomIndex);
                                            r.refresh();
                                        }
                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                    "
                                    class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                ← Previous Room
                            </button>

                            <span class="text-sm text-gray-500 dark:text-gray-400 font-medium">${currentRoomIndex + 1} / ${rooms.length}</span>

                            ${currentRoomIndex < rooms.length - 1 ? `
                                <button type="button"
                                        ${photoCount < minPhotos ? 'disabled' : ''}
                                        @click="
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                const r = rendererEl._x_dataStack[0];
                                                r._photoRoomIndex = ${currentRoomIndex + 1};
                                                localStorage.setItem('session_' + r.sessionId + '_photo_room', r._photoRoomIndex);
                                                r.refresh();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        "
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors ${photoCount < minPhotos ? 'opacity-50 cursor-not-allowed' : ''}">
                                    Next Room →
                                </button>
                            ` : `
                                <button type="button"
                                        ${hasIncomplete || photoCount < minPhotos ? 'disabled' : ''}
                                        @click="
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0]._showPhotoUpload = false;
                                                rendererEl._x_dataStack[0]._showPhotoExtrasPrompt = true;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        "
                                        class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 transition-colors ${hasIncomplete || photoCount < minPhotos ? 'opacity-50 cursor-not-allowed' : ''}">
                                    Finish Photos →
                                </button>
                            `}
                        </div>
                        ${photoCount < minPhotos ? `
                            <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <p class="text-xs font-semibold text-amber-700 dark:text-amber-300">⚠️ Please upload at least ${minPhotos} photos of this room before continuing.</p>
                            </div>
                        ` : ''}
                        ${hasIncomplete ? `
                            <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <p class="text-xs text-amber-700 dark:text-amber-300">⚠️ Some verify/inventory tasks are incomplete. Complete them before finishing.</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        },



        renderPhotos() {
            const stepBtn = document.querySelector('button#stepBtn');
            if (stepBtn) stepBtn.classList.add('hidden');
            // Ensure rooms is an array
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                rooms = Object.values(rooms);
            }
            const photoCounts = this.sessionData.photo_counts || {};
            const photosByRoom = this.sessionData.photos_by_room || {};

            // Check for incomplete mandatory tasks across all rooms
            let incompleteTasks = [];
            rooms.forEach(room => {
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                roomTasks.forEach(task => {
                    if (task.type === 'instructions') return; // skip instruction-only
                    const checked = task.checklist_item?.checked || false;
                    if (!checked && (task.type === 'inventory' || task.type === 'verify')) {
                        incompleteTasks.push({ roomName: room.name, taskName: task.name, taskType: task.type });
                    }
                });
            });
            // Also check property-level tasks
            ['pre_cleaning', 'during_cleaning', 'post_cleaning'].forEach(phase => {
                const tasks = this.sessionData.property_tasks?.[phase] || [];
                (Array.isArray(tasks) ? tasks : Object.values(tasks)).forEach(task => {
                    if (task.type === 'instructions') return;
                    const checked = task.checklist_item?.checked || false;
                    if (!checked && (task.type === 'inventory' || task.type === 'verify')) {
                        incompleteTasks.push({ roomName: phase.replace(/_/g, ' '), taskName: task.name, taskType: task.type });
                    }
                });
            });
            const hasIncomplete = incompleteTasks.length > 0;

            let pendingPhotos = [];
            rooms.forEach(room => {
                const count = photoCounts[room.id] || 0;
                const min = room.min_photos ?? 2;
                if (count < min) {
                    pendingPhotos.push({ roomName: room.name, remaining: min - count, min: min });
                }
            });
            const hasPendingPhotos = pendingPhotos.length > 0;
            const canSubmit = !hasIncomplete && !hasPendingPhotos;

            return `
                <div class="space-y-6">
                    <!-- Go Back Button -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                        <button type="button"
                                @click="
                                    $el.disabled = true;
                                    $el.textContent = 'Going back...';
                                    window.api.post('/sessions/${this.sessionId}/go-back-stage', { current_stage: 'photos' })
                                    .then(res => {
                                        if (res.success && res.data) {
                                            const rendererEl = document.querySelector('[x-data*=\\'checklistRenderer\\']');
                                            if (rendererEl && rendererEl._x_dataStack) {
                                                rendererEl._x_dataStack[0].sessionData = res.data;
                                                rendererEl._x_dataStack[0].renderChecklist();
                                            }
                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                        } else {
                                            window.location.reload();
                                        }
                                    })
                                    .catch(() => { window.location.reload(); });
                                "
                                class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Go Back
                        </button>
                    </div>

                    ${rooms.map(room => {
                const photoCount = photoCounts[room.id] || 0;
                const photos = photosByRoom[room.id] || [];

                return `
                            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-5" data-room-photos data-room-id="${room.id}">
                                <div class="mb-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="text-base sm:text-lg font-bold text-gray-900 dark:text-gray-100">${room.name}</h3>
                                        <div class="text-right">
                                            <span class="text-xs font-bold ${photoCount < (room.min_photos ?? 2) ? 'text-amber-500' : 'text-gray-500 dark:text-gray-400'}" data-photo-count>${photoCount} / ${room.min_photos ?? 2} Photos Req.</span>
                                        </div>
                                    </div>
                                </div>

                                <div x-data="photoUploader(${room.id})" class="mb-6">
                                    <form method="post" enctype="multipart/form-data"
                                          action="/sessions/${this.sessionId}/rooms/${room.id}/photos"
                                          data-checklist-photo-form
                                          data-room-id="${room.id}"
                                          @submit.prevent.stop="handleSubmit($event)">

                                        <div class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 sm:p-6 mb-4
                                                   border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/50
                                                   hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-colors cursor-pointer"
                                             @dragover.prevent="hover = true"
                                             @dragleave.prevent="hover = false"
                                             @drop.prevent="handleDrop($event)"
                                             @click="$refs.fileInput.click()"
                                             :class="hover ? 'border-blue-400 bg-blue-50/50 dark:bg-blue-900/10' : ''">
                                            <svg class="h-8 w-8 sm:h-10 sm:w-10 text-gray-400 dark:text-gray-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 text-center px-2">
                                                <span class="text-blue-600 dark:text-blue-400 font-medium">Tap to take a photo</span>
                                            </p>
                                            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-500 mt-1">PNG, JPG, JPEG up to 5MB</p>

                                            <input x-ref="fileInput"
                                                   type="file"
                                                   name="photos[]"
                                                   accept="image/*"
                                                   capture="environment"
                                                   class="hidden"
                                                   @change="handleFiles($event)" />
                                        </div>

                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 mb-4" x-show="previews.length > 0" x-cloak>
                                            <button type="submit"
                                                    :disabled="previews.length === 0 || uploading"
                                                    class="w-full sm:flex-1 px-4 py-2.5 sm:py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium text-sm sm:text-base shadow-sm">
                                                <span x-show="!uploading">Upload <span x-text="previews.length"></span> Photo<span x-show="previews.length !== 1">s</span></span>
                                                <span x-show="uploading" class="flex items-center justify-center gap-2">
                                                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Uploading...
                                                </span>
                                            </button>
                                        </div>

                                        <div x-show="uploading && uploadProgress > 0" x-cloak class="mb-4">
                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-300"
                                                     :style="'width: ' + uploadProgress + '%'"></div>
                                            </div>
                                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1 text-center" x-text="uploadProgress + '%'"></p>
                                        </div>

                                        <div x-show="previews.length > 0" x-cloak class="mb-4">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                                <template x-for="(preview, index) in previews" :key="index">
                                                    <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800"
                                                         x-data="{ fullscreen: false }">
                                                        <button type="button" @click="fullscreen = true" class="w-full">
                                                            <img :src="preview.url"
                                                                 class="w-full aspect-square object-cover"
                                                                 :alt="'Preview ' + (index + 1)"
                                                                 :width="preview.width || 200"
                                                                 :height="preview.height || 200"
                                                                 loading="lazy" />
                                                        </button>
                                                        <div x-show="fullscreen"
                                                             x-cloak
                                                             @click.self="fullscreen = false"
                                                             @keydown.escape.window="fullscreen = false"
                                                             class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-2 sm:p-4">
                                                            <img :src="preview.url"
                                                                 class="max-h-full max-w-full object-contain rounded-lg"
                                                                 :alt="'Preview ' + (index + 1)"
                                                                 :width="preview.width || 1920"
                                                                 :height="preview.height || 1080" />
                                                            <button type="button"
                                                                    @click="fullscreen = false"
                                                                    class="absolute top-2 right-2 sm:top-4 sm:right-4 text-white text-2xl sm:text-3xl hover:text-gray-300 transition-colors bg-black/50 rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                                                                ×
                                                            </button>
                                                        </div>
                                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                            <button type="button"
                                                                    @click.stop="removePreview(index)"
                                                                    class="px-2 sm:px-3 py-1 sm:py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-xs sm:text-sm font-medium">
                                                                Remove
                                                            </button>
                                                        </div>
                                                        <div class="absolute top-1 right-1 sm:top-2 sm:right-2">
                                                            <span class="text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded bg-black/60 text-white" x-text="formatFileSize(preview.size)"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                ${photos.length > 0 ? `
                                    <div data-photo-gallery class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2 sm:gap-3">
                                        ${photos.map(photo => {
                    const photoUrl = photo.url || '';
                    const timeStr = photo.captured_at ? new Date(photo.captured_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) : '';
                    const deleteUrlConfig = this.photoDeleteUrl ? `, { deleteUrl: '${this.photoDeleteUrl}' }` : '';
                    return `
                                                <div class="relative group"
                                                     x-data="photoDeleteHandler(${photo.id}, '${this.sessionId}'${deleteUrlConfig})"
                                                     data-photo-id="${photo.id}">
                                                    <button type="button" @click="fullscreen = true" class="w-full">
                                                        <img src="${photoUrl}"
                                                             alt="Photo"
                                                             width="300"
                                                             height="300"
                                                             class="aspect-square w-full object-cover rounded-xl border transition hover:opacity-90"
                                                             loading="lazy" />
                                                        ${timeStr ? `
                                                            <span class="absolute bottom-1 right-1 text-[10px] sm:text-xs px-1.5 sm:px-2 py-0.5 sm:py-1 rounded bg-black/60 text-white">
                                                                ${timeStr}
                                                            </span>
                                                        ` : ''}
                                                    </button>
                                                    <button type="button"
                                                            @click.stop="handleDeletePhoto()"
                                                            :disabled="deleting"
                                                            class="absolute top-1 right-1 sm:top-2 sm:right-2 p-1 sm:p-1.5 bg-red-600 text-white rounded-full opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700 disabled:opacity-50"
                                                            title="Delete photo">
                                                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                    </button>
                                                    <div x-show="fullscreen"
                                                         x-cloak
                                                         @click.self="fullscreen = false"
                                                         @keydown.escape.window="fullscreen = false"
                                                         class="fixed inset-0 z-50 bg-black/95 flex items-center justify-center p-2 sm:p-4">
                                                        <img src="${photoUrl}"
                                                             class="max-h-full max-w-full object-contain rounded-lg"
                                                             alt="Fullscreen photo"
                                                             width="1920"
                                                             height="1080" />
                                                        <button type="button"
                                                                @click="fullscreen = false"
                                                                class="absolute top-2 right-2 sm:top-4 sm:right-4 text-white text-2xl sm:text-3xl hover:text-gray-300 transition-colors bg-black/50 rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center">
                                                            ×
                                                        </button>
                                                    </div>
                                                </div>
                                            `;
                }).join('')}
                                    </div>
                                ` : `
                                    <p class="text-center text-gray-500 dark:text-gray-400 py-8">No photos uploaded yet.</p>
                                `}
                            </div>
                        `;
            }).join('')}

                    <!-- Submit Section with Validation -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 text-center">
                        ${hasIncomplete ? `
                            <div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-left">
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <div>
                                        <p class="font-semibold text-amber-800 dark:text-amber-200 text-sm">Cannot submit yet</p>
                                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">The following tasks must be completed before submission:</p>
                                        <ul class="mt-2 space-y-1">
                                            ${incompleteTasks.map(t => `
                                                <li class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                                                    <span class="font-medium">${t.roomName}</span> — ${t.taskName}
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-200 dark:bg-amber-800 text-amber-800 dark:text-amber-200">${t.taskType}</span>
                                                </li>
                                            `).join('')}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            </div>
                        ` : ''}
                        ${hasPendingPhotos ? `
                            <div class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg text-left">
                                <div class="flex items-start gap-2">
                                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                    <div>
                                        <p class="font-semibold text-amber-800 dark:text-amber-200 text-sm">More photos required</p>
                                        <ul class="mt-2 space-y-1">
                                            ${pendingPhotos.map(p => `
                                                <li class="text-xs text-amber-700 dark:text-amber-300 flex items-center gap-1.5">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                                                    <span class="font-medium">${p.roomName}</span> requires ${p.remaining} more photo(s) (minimum ${p.min}).
                                                </li>
                                            `).join('')}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        ` : ''}
                        <button type="button"
                                ${!canSubmit ? 'disabled' : ''}
                                @click="
                                    $el.disabled = true;
                                    $el.textContent = 'Submitting...';
                                    window.api.post('/sessions/${this.sessionId}/complete', {})
                                    .then(res => {
                                        if (res.success && res.redirect) { window.location.href = res.redirect; }
                                        else if (res.success) { window.location.reload(); }
                                        else { window.checklistHandler?.showError(res.message || 'Submission failed'); $el.disabled = false; $el.textContent = 'Submit Checklist'; }
                                    })
                                    .catch(err => {
                                        const msg = err.response?.data?.message || err.message || 'Submission failed';
                                        window.checklistHandler?.showError(msg);
                                        $el.disabled = false;
                                        $el.textContent = 'Submit Checklist';
                                    });
                                "
                                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium ${!canSubmit ? 'opacity-50 cursor-not-allowed' : ''}">
                            Submit Checklist
                        </button>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                            ${!canSubmit ? 'Please complete all mandatory tasks and photo requirements.' : 'Please ensure all photo requirements are met. Timestamp overlay is automatic.'}
                        </p>
                    </div>
                </div>
            `;
        },

        renderSummary(showButton = false) {
            const isCompleted = this.sessionData.session?.status === 'completed';
            let html = `
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8"
                     x-data="{ sessionSubmitted: false }">

                    <!-- Header -->
                    <div class="text-center py-8" x-show="!sessionSubmitted">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">All Stages Complete!</h2>
                        <p class="text-gray-600 dark:text-gray-400 max-w-md mx-auto mb-8">
                            You have completed all stages of this cleaning session. Please add any notes and submit when ready.
                        </p>
                    </div>

                    <!-- Notes Section -->
                    <div class="max-w-2xl mx-auto mb-8" x-show="!sessionSubmitted">
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-6 bg-gray-50 dark:bg-gray-800/50">
                            <div class="flex items-start gap-3 mb-4">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Session Notes</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Add any notes, feedback, or issues you want to report.
                                    </p>
                                </div>
                            </div>

                            <div x-data="noteReporter({ sessionId: '${this.sessionId}' })" class="space-y-4">
                                <textarea 
                                    x-model="note"
                                    rows="4"
                                    placeholder="Enter your notes here..."
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                ></textarea>

                                <div class="flex items-center justify-end gap-3 mt-4">
                                    <button 
                                        type="button"
                                        @click="
                                            if (sessionSubmitted) return;
                                            $el.disabled = true;
                                            $el.textContent = note.trim() ? 'Saving & Submitting...' : 'Submitting...';
                                            
                                            // 1. If no note, just complete
                                            if (!note.trim()) {
                                                window.api.post('/sessions/${this.sessionId}/complete', {})
                                                .then(res => {
                                                    if (res.success && res.redirect) { window.location.replace(res.redirect); }
                                                    else if (res.success) { 
                                                        sessionSubmitted = true; 
                                                        document.querySelectorAll('[data-status-badge]').forEach(el => {
                                                            el.innerHTML = '<span class=\\'inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400\\'><svg class=\\'-ml-1 mr-1.5 h-4 w-4\\' fill=\\'currentColor\\' viewBox=\\'0 0 20 20\\'><path fill-rule=\\'evenodd\\' d=\\'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z\\' clip-rule=\\'evenodd\\' /></svg>Completed</span>';
                                                        });
                                                    }
                                                    else { window.checklistHandler?.showError(res.message || 'Submission failed'); $el.disabled = false; $el.textContent = 'Submit Checklist'; }
                                                })
                                                .catch(err => {
                                                    window.checklistHandler?.showError(err.response?.data?.message || err.message || 'Submission failed');
                                                    $el.disabled = false;
                                                    $el.textContent = 'Submit Checklist';
                                                });
                                                return;
                                            }

                                            // 2. If there is a note, save it first
                                            window.api.post('/sessions/${this.sessionId}/notes', { note: note })
                                            .then(res => {
                                                if (res.success) {
                                                    return window.api.post('/sessions/${this.sessionId}/complete', {});
                                                }
                                                throw new Error(res.message || 'Failed to save note');
                                            })
                                            .then(res => {
                                                if (res.success && res.redirect) { window.location.replace(res.redirect); }
                                                else if (res.success) { 
                                                    sessionSubmitted = true;
                                                    document.querySelectorAll('[data-status-badge]').forEach(el => {
                                                        el.innerHTML = '<span class=\\'inline-flex items-center justify-center rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400\\'><svg class=\\'-ml-1 mr-1.5 h-4 w-4\\' fill=\\'currentColor\\' viewBox=\\'0 0 20 20\\'><path fill-rule=\\'evenodd\\' d=\\'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z\\' clip-rule=\\'evenodd\\' /></svg>Completed</span>';
                                                    });
                                                }
                                                else { throw new Error(res.message || 'Submission failed'); }
                                            })
                                            .catch(err => {
                                                window.checklistHandler?.showError(err.message || 'Submission failed');
                                                $el.disabled = false;
                                                $el.textContent = 'Submit Checklist';
                                            });
                                        "
                                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-semibold cursor-pointer w-full sm:w-auto"
                                    >
                                        Submit Checklist
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Session Completed Confirmation -->
                    <div class="text-center py-8" x-show="sessionSubmitted" x-cloak>
                        <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                            <svg class="w-10 h-10 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">Session Completed!</h2>
                        <p class="text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                            Thank you! Your report has been generated. You can now close this page.
                        </p>
                    </div>
                </div>
            `;

            html += `</div>`;

            return html;
        },

        renderTaskItem(task, room, disabled = false) {
            const isPropertyTask = room === null;
            const toggleUrl = isPropertyTask
                ? `/sessions/${this.sessionId}/tasks/${task.id}/toggle`
                : `/sessions/${this.sessionId}/rooms/${room.id}/tasks/${task.id}/toggle`;
            const noteUrl = isPropertyTask
                ? `/sessions/${this.sessionId}/tasks/${task.id}/note`
                : `/sessions/${this.sessionId}/rooms/${room.id}/tasks/${task.id}/note`;
            const photoUrl = isPropertyTask
                ? `/sessions/${this.sessionId}/tasks/${task.id}/photo`
                : `/sessions/${this.sessionId}/rooms/${room.id}/tasks/${task.id}/photo`;

            const checked = task.checklist_item?.checked || false;
            const hasInstructions = task.instructions && task.instructions.trim().length > 0;
            const hasMedia = task.media && task.media.length > 0;
            const showDetails = hasInstructions || hasMedia;
            const isViewOnly = this.sessionData.is_view_only || false;
            const taskDisabled = disabled || isViewOnly;

            // Check if user has added a note
            const userNote = task.checklist_item?.note || '';
            const hasUserNote = userNote.trim().length > 0;

            // Clean up property wide labels for occasional tasks
            const displayName = task.name.replace(/\[Property Wide\]\s*-?\s*|\(Property Wide\)\s*-?\s*|Property\sWide\s*-?\s*/gi, '').trim() || task.name;

            return `
                <div data-task-item data-task-id="${task.id}"
                     class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-200 ${checked ? 'opacity-90' : ''}"
                     x-data="{
                         detailsOpen: false,
                         taskName: ${JSON.stringify(displayName).replace(/"/g, '&quot;')},
                         noteValue: ${JSON.stringify(userNote).replace(/"/g, '&quot;')},
                         itemPhotos: ${JSON.stringify(task.checklist_item?.photos || []).replace(/"/g, '&quot;')},
                         taskType: '${task.type}',
                         get photoRequirement() {
                             if (this.taskType === 'verify') return 1;
                             if (this.taskType === 'inventory') return 1;
                             const name = this.taskName.toLowerCase();
                             if (name.includes('photo') || name.includes('picture') || name.includes('image')) return 1;
                             return 0;
                         },
                         get isMandatoryPhoto() {
                             return this.photoRequirement > 0;
                         },
                         get canToggle() {
                             if (this.isMandatoryPhoto && this.itemPhotos.length < this.photoRequirement) return false;
                             return true;
                         },
                         handleClick(event, el) {
                             if (!this.canToggle && this.itemPhotos.length < this.photoRequirement) {
                                  // For inventory tasks, open inventory modal; for others, open photo modal
                                  if (this.taskType === 'inventory') {
                                      window.dispatchEvent(new CustomEvent('open-inventory-modal', {
                                          detail: {
                                              taskId: ${task.id},
                                              taskName: this.taskName,
                                              photoUrl: '${photoUrl}',
                                              toggleUrl: '${toggleUrl}'
                                          }
                                      }));
                                  } else {
                                      window.dispatchEvent(new CustomEvent('open-photo-modal', { detail: { photoUrl: '${photoUrl}', taskId: ${task.id}, taskType: this.taskType } }));
                                  }
                                  return;
                             }
                             if (this.taskType === 'inventory' && !this.checked) {
                                 // Open inventory modal instead of prompt()
                                 window.dispatchEvent(new CustomEvent('open-inventory-modal', {
                                     detail: {
                                         taskId: ${task.id},
                                         taskName: this.taskName,
                                         photoUrl: '${photoUrl}',
                                         toggleUrl: '${toggleUrl}'
                                     }
                                 }));
                                 return;
                             }
                             window.checklistHandler.handleToggle(event, el);
                         },
                         openUploadModal() {
                             if (this.taskType === 'inventory' && !this.checked) {
                                 window.dispatchEvent(new CustomEvent('open-inventory-modal', {
                                     detail: { taskId: ${task.id}, taskName: this.taskName, photoUrl: '${photoUrl}', toggleUrl: '${toggleUrl}', itemPhotos: this.itemPhotos, noteValue: this.noteValue }
                                 }));
                             } else {
                                 window.dispatchEvent(new CustomEvent('open-photo-modal', { detail: { photoUrl: '${photoUrl}', toggleUrl: '${toggleUrl}', taskId: ${task.id}, taskType: this.taskType, itemPhotos: this.itemPhotos, noteValue: this.noteValue } }));
                             }
                         }
                     }">
                    <div class="p-4 flex flex-col h-full">
                        <div class="flex items-start gap-4 flex-1">
                            <div class="flex-shrink-0 pt-1">
                                <template x-if="taskType !== 'instructions'">
                                    <button type="button"
                                            data-checklist-toggle
                                            @click="handleClick($event, $el)"
                                            data-toggle-url="${toggleUrl}"
                                            data-checked="${checked}"
                                            :disabled="${taskDisabled} || noteSaving || photoUploading"
                                            class="relative w-7 h-7 rounded-lg border-2 flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 ${taskDisabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer hover:scale-110 shadow-sm'} ${checked ? 'bg-green-600 border-green-600 text-white' : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600'}"
                                            :class="{ 'opacity-50 grayscale cursor-not-allowed': isMandatoryPhoto && itemPhotos.length === 0 && !${checked} }"
                                            aria-label="${checked ? 'Mark as incomplete' : 'Mark as complete'}">
                                        ${checked ? `
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                    ` : `
                                        <template x-if="isMandatoryPhoto && itemPhotos.length === 0">
                                            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                            </svg>
                                        </template>
                                    `}
                                    </button>
                                </template>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col gap-1">
                                    <h3 data-task-name x-text="taskName" class="text-lg font-bold text-gray-900 dark:text-gray-100 transition-all ${checked ? 'text-gray-400 dark:text-gray-500 font-medium' : ''}">
                                        ${displayName}
                                    </h3>

                                    <template x-if="isMandatoryPhoto && itemPhotos.length < photoRequirement && !${checked}">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-orange-600 uppercase tracking-tight">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                            </svg>
                                            <span>Photo Required</span>
                                        </span>
                                    </template>

                                    <template x-if="taskType === 'inventory' && ${task.checklist_item?.quantity ? 'true' : 'false'}">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 px-2 py-0.5 rounded-full w-fit">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                            </svg>
                                            <span>Qty: ${task.checklist_item?.quantity}</span>
                                        </span>
                                    </template>
                                    
                                    <template x-if="taskType === 'instructions'">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-gray-500 uppercase tracking-tight">
                                            Instruction Guide
                                        </span>
                                    </template>
                                    
                                    <template x-if="taskType === 'inventory' && !${checked}">
                                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-blue-500 uppercase tracking-tight">
                                            Inventory Task
                                        </span>
                                    </template>

                                        <div class="mt-3 w-full" x-show="${hasInstructions} || ${hasMedia} || itemPhotos.length > 0 || noteValue.length > 0" x-cloak>
                                            <button type="button" @click="detailsOpen = !detailsOpen"
                                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200 border-2 border-amber-300 dark:border-amber-700 hover:bg-amber-200 dark:hover:bg-amber-900/60 transition-all shadow-sm active:scale-95">
                                                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span x-text="detailsOpen ? 'Hide Notes' : 'Read Notes'">Read Notes</span>
                                                <svg class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': detailsOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </button>
                                        </div>
                                </div>

                                <!-- Footer Actions -->
                                <div class="mt-4 flex items-center justify-between border-t border-gray-100 dark:border-gray-800/50 pt-3" data-note-container>
                                    <div class="flex items-center gap-1.5">
                                        <!-- Note / Upload Button -->
                                        <button type="button"
                                                @click="openUploadModal()"
                                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:bg-green-50 dark:hover:bg-green-900/20 hover:text-green-600 transition-all active:scale-95"
                                                :class="itemPhotos.length > 0 ? 'border-green-200 bg-green-50 text-green-600' : ''"
                                                title="${task.type === 'verify' || task.type === 'inventory' ? 'Upload Photo' : 'Add Note'}">
                                            <template x-if="taskType === 'verify' || taskType === 'inventory'">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                            </template>
                                            <template x-if="taskType !== 'verify' && taskType !== 'inventory'">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                                </svg>
                                            </template>
                                            <span class="text-xs font-bold uppercase tracking-tight" x-text="(taskType === 'verify' || taskType === 'inventory') ? 'Upload' : 'Note'"></span>
                                            <template x-if="itemPhotos.length > 0">
                                                <span class="bg-green-600 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center" x-text="itemPhotos.length"></span>
                                            </template>
                                        </button>

                                        <!-- View Photos Button (only for verify/inventory with uploaded photos) -->
                                        <template x-if="(taskType === 'verify' || taskType === 'inventory') && itemPhotos.length > 0">
                                            <button type="button"
                                                    @click="detailsOpen = !detailsOpen"
                                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-blue-200 dark:border-blue-700 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all active:scale-95"
                                                    :class="detailsOpen ? 'bg-blue-50 dark:bg-blue-900/30' : ''">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                                <span class="text-xs font-bold uppercase tracking-tight" x-text="detailsOpen ? 'Hide' : 'View'"></span>
                                                <span class="bg-blue-600 text-white text-[10px] w-4 h-4 rounded-full flex items-center justify-center" x-text="itemPhotos.length"></span>
                                            </button>
                                        </template>
                                    </div>

                                    <input type="hidden" data-note-input x-model="noteValue" />
                                </div>

                                    <div x-show="detailsOpen" x-collapse x-cloak class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                        ${hasInstructions ? `
                                            <div class="mb-3">
                                                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Instructions</div>
                                                <div class="prose dark:prose-invert prose-sm max-w-none text-gray-600 dark:text-gray-400 leading-relaxed">
                                                    ${this.formatInstructions(task.instructions)}
                                                </div>
                                            </div>
                                        ` : ''}

                                         ${hasMedia ? `
                                            <div class="mb-3">
                                                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Examples</div>
                                                <div class="grid grid-cols-3 gap-2">
                                                    ${task.media.map(media => `
                                                        <div class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 group bg-gray-100 dark:bg-gray-800">
                                                            ${media.type === 'image' ? `
                                                                <button type="button" @click="window.dispatchEvent(new CustomEvent('open-gallery', { detail: { src: '${media.url}' } }))" class="block w-full h-full">
                                                                    <div class="aspect-video w-full relative bg-gray-200 dark:bg-gray-700 rounded overflow-hidden">
                                                                        <img src="${media.thumbnail || media.url}" alt="${media.caption || 'Task media'}"
                                                                             class="w-full h-full object-cover transition-transform group-hover:scale-105"
                                                                             onerror="this.onerror=null; this.src='https://placehold.co/400x300/e2e8f0/64748b?text=Image+Not+Found'; this.parentElement.classList.add('bg-gray-200');"
                                                                             loading="lazy" />
                                                                    </div>
                                                                </button>
                                                            ` : `
                                                                <video src="${media.url}" class="w-full h-24 object-cover" controls muted></video>
                                                            `}
                                                            ${media.caption ? `
                                                                <div class="absolute bottom-0 left-0 right-0 bg-black/50 p-1">
                                                                    <p class="text-[10px] text-white truncate text-center">${this.escapeHtml(media.caption)}</p>
                                                                </div>
                                                            ` : ''}
                                                        </div>
                                                    `).join('')}
                                                </div>
                                            </div>
                                        ` : ''}

                                        <template x-if="itemPhotos.length > 0">
                                            <div class="mb-1">
                                                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-1.5">Your Photos</div>
                                                <div class="grid grid-cols-3 gap-2">
                                                    <template x-for="photo in itemPhotos" :key="photo.id">
                                                        <div class="relative rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 group bg-gray-100 dark:bg-gray-800">
                                                            <button type="button" @click="window.dispatchEvent(new CustomEvent('open-gallery', { detail: { src: photo.url } }))" class="block w-full">
                                                                <img :src="photo.url" alt="Attached photo"
                                                                     class="w-full h-24 object-cover transition-transform group-hover:scale-105"
                                                                     loading="lazy" />
                                                            </button>
                                                            <button type="button" 
                                                                    @click.stop="
                                                                        if(confirm('Are you sure you want to delete this photo?')) {
                                                                            // Optimistic: remove photo and uncheck immediately
                                                                            itemPhotos = itemPhotos.filter(p => p.id !== photo.id);
                                                                            if (itemPhotos.length === 0) {
                                                                                const toggleBtn = $el.closest('[data-task-item]')?.querySelector('[data-checklist-toggle]');
                                                                                if (toggleBtn) {
                                                                                    toggleBtn.dataset.checked = 'false';
                                                                                    window.checklistHandler?.updateToggleUI(toggleBtn, false);
                                                                                }
                                                                            }
                                                                            const deleteUrl = '${photoUrl}/' + photo.id;
                                                                            window.api.delete(deleteUrl).then(res => {
                                                                                if(res.success) {
                                                                                    // Silently succeed. We already updated UI optimistically.
                                                                                } else {
                                                                                    alert(res.message || 'Failed to delete photo');
                                                                                }
                                                                            }).catch(err => {
                                                                                console.error(err);
                                                                                alert('Error deleting photo');
                                                                            });
                                                                        }
                                                                    "
                                                                    class="absolute top-1 right-1 bg-red-600 text-white rounded-full p-1 hover:bg-red-700 shadow-md transform scale-90 opacity-80 hover:opacity-100 hover:scale-100 transition-all z-10"
                                                                    title="Delete Photo">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                            <template x-if="photo.note">
                                                                <div class="absolute bottom-0 left-0 right-0 bg-black/50 p-1">
                                                                    <p class="text-[10px] text-white truncate text-center" x-text="photo.note"></p>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                            </div>
                        </div>

                    </div>
                </div>
            `;
        },

        formatInstructions(instructions) {
            if (!instructions) return '';
            // Convert newlines to <br> and escape HTML
            return this.escapeHtml(instructions).replace(/\n/g, '<br>');
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        setupEventHandlers() {
            // Event handlers are set up by the checklist.js module
            // This is called after rendering to ensure new elements are handled
            if (window.checklistHandler) {
                window.checklistHandler.setupFormHandlers();
            }
        },

        // Public method to refresh data and re-render
        async refresh() {
            await this.loadSessionData();
        },

        // Check if a room is complete and unlock next room
        checkRoomCompletion() {
            if (!this.sessionData) return;

            // Re-calculate completion status
            let rooms = this.sessionData.rooms || [];
            if (!Array.isArray(rooms)) {
                rooms = Object.values(rooms);
            }
            rooms.forEach((room, index) => {
                // Ensure room.tasks is an array
                const roomTasksArray = Array.isArray(room.tasks) ? room.tasks : Object.values(room.tasks || {});
                const roomTasks = roomTasksArray.filter(t => room.room_tasks.includes(t.id));
                const checkedCount = roomTasks.filter(t => t.checklist_item?.checked).length;
                const totalCount = roomTasks.length;
                const isComplete = checkedCount === totalCount && totalCount > 0;

                // If room just completed, refresh to unlock next room
                if (isComplete) {
                    // Small delay to ensure database is updated
                    setTimeout(() => {
                        this.refresh();
                    }, 500);
                }
            });
        },
    };
}