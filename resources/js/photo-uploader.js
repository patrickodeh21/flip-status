/**
 * Photo Uploader Component
 * Handles drag & drop, preview, and upload for room photos
 */

export default function photoUploader(roomId) {
    return {
        roomId: roomId,
        previews: [],
        hover: false,
        uploading: false,
        uploadProgress: 0,

        init() {
            // Initialize empty state
        },

        handleDrop(event) {
            event.preventDefault();
            this.hover = false;

            const files = Array.from(event.dataTransfer.files);
            this.addFiles(files);
        },

        handleFiles(event) {
            const files = Array.from(event.target.files || []);
            this.addFiles(files);
            // Reset file input so the user can select more photos by tapping again
            event.target.value = '';
        },

        addFiles(files) {
            files.forEach(file => {
                if (!file.type.startsWith('image/')) {
                    return;
                }

                // Check file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                    return;
                }

                const reader = new FileReader();
                reader.onload = (e) => {
                    // Get image dimensions
                    const img = new Image();
                    img.onload = () => {
                        this.previews.push({
                            file: file,
                            url: e.target.result,
                            size: file.size,
                            name: file.name,
                            width: img.width,
                            height: img.height
                        });
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        },

        removePreview(index) {
            // Revoke object URL to free memory
            if (this.previews[index].url.startsWith('blob:')) {
                URL.revokeObjectURL(this.previews[index].url);
            }
            this.previews.splice(index, 1);
        },

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        },

        async handleSubmit(event) {
            event.preventDefault();
            event.stopPropagation(); // Prevent event from bubbling to other handlers

            if (this.previews.length === 0) {
                return;
            }

            // Prevent double submission
            if (this.uploading) {
                return;
            }

            const form = event.currentTarget;

            this.uploading = true;
            this.uploadProgress = 0;

            const totalPhotos = this.previews.length;
            let uploadedCount = 0;
            let failedCount = 0;

            try {
                // Upload photos sequentially (one at a time) to avoid
                // exceeding PHP post_max_size which silently drops $_POST
                // (including CSRF tokens), causing TokenMismatchException
                // and catastrophic 302 redirects that wipe Alpine state.
                for (const preview of this.previews) {
                    const formData = new FormData();
                    formData.append('photos[]', preview.file);

                    try {
                        const response = await window.api.post(form.action, formData);
                        if (response.success || response.photos) {
                            uploadedCount++;
                        } else {
                            failedCount++;
                            console.warn('Photo upload returned non-success:', response);
                        }
                    } catch (singleError) {
                        failedCount++;
                        console.error('Single photo upload error:', singleError);
                    }

                    // Update progress based on completed uploads
                    this.uploadProgress = Math.round((uploadedCount + failedCount) / totalPhotos * 100);
                }

                if (uploadedCount > 0) {
                    // Clear previews
                    this.previews.forEach(preview => {
                        if (preview.url.startsWith('blob:')) {
                            URL.revokeObjectURL(preview.url);
                        }
                    });
                    this.previews = [];

                    // Clear file input
                    if (this.$refs.fileInput) {
                        this.$refs.fileInput.value = '';
                    }

                    // Update photo count display locally instead of full re-render
                    const roomContainer = this.$el.closest('[data-room-photos]');
                    if (roomContainer) {
                        const countEl = roomContainer.querySelector('[data-photo-count]');
                        if (countEl) {
                            const currentText = countEl.textContent;
                            const match = currentText.match(/(\d+)/);
                            if (match) {
                                const newCount = parseInt(match[1]) + uploadedCount;
                                countEl.textContent = currentText.replace(/\d+/, newCount);
                            }
                        }
                    }

                    // Show success message
                    const checklistHandler = window.checklistHandler;
                    const message = failedCount > 0
                        ? `${uploadedCount} photo(s) uploaded, ${failedCount} failed.`
                        : `${uploadedCount} photo(s) uploaded successfully.`;
                    if (checklistHandler) {
                        checklistHandler.showSuccess(message);
                    }

                    // Refresh the checklist payload to get verified DB photo counts, then maybe auto-advance
                    const renderer = document.querySelector('[x-data*="checklistRenderer"]')?._x_dataStack?.[0];
                    if (renderer && renderer.refresh) {
                        renderer.refresh().then(() => {
                            const rooms = renderer.sessionData?.rooms || [];
                            const roomIdx = renderer._photoRoomIndex || 0;
                            const currentRoom = rooms[roomIdx];
                            if (currentRoom) {
                                const currentCount = (renderer.sessionData?.photo_counts || {})[currentRoom.id] || 0;
                                const minReq = currentRoom.min_photos ?? 2;
                                if (currentCount >= minReq && roomIdx < rooms.length - 1) {
                                    renderer._photoRoomIndex = roomIdx + 1;
                                    renderer.renderChecklist();
                                    window.scrollTo({ top: 0, behavior: 'smooth' });
                                }
                            }
                        });
                    }
                } else {
                    throw new Error('All photo uploads failed.');
                }
            } catch (error) {
                console.error('Upload error:', error);
                const errorMessage = error.response?.data?.message || error.message || 'Failed to upload photos';
                const checklistHandler = window.checklistHandler;
                if (checklistHandler) {
                    checklistHandler.showError(errorMessage);
                } else {
                    alert(errorMessage);
                }
            } finally {
                this.uploading = false;
                this.uploadProgress = 0;
            }
        },
    };
}
