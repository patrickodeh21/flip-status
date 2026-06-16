// resources/js/modules/room-list.js
// Uses window.Sortable (already assigned in app.js)

export default function roomsList({ orderUrl, csrf }) {
  return {
    orderUrl,
    csrf,

    // UI state
    saving: false,
    savedAt: null,
    status: 'idle', // 'idle' | 'saving' | 'saved' | 'error'
    errorMsg: null,

    // internal
    _saveTimer: null,     // debounce timer
    _hideTimer: null,     // hide "Saved" after a moment
    _pending: false,      // if a drop happened while saving

    init() {
      const desktopList = this.$refs?.tbody;
      const mobileList = this.$refs?.mobileList;

      const sortableConfig = {
        animation: 160,
        handle: '.drag-handle',
        draggable: '[data-room-id]',
        onStart: (evt) => evt.from.classList.add('dragging'),
        onEnd: (evt) => {
          evt.from.classList.remove('dragging');
          this.queueSave();
        },
      };

      if (desktopList) {
        new window.Sortable(desktopList, sortableConfig);
      }
      if (mobileList) {
        new window.Sortable(mobileList, sortableConfig);
      }
    },

    get orderIds() {
      const isMobile = window.innerWidth < 768; // Simple MD breakpoint check
      const list = (isMobile && this.$refs.mobileList) ? this.$refs.mobileList : this.$refs.tbody;

      if (!list) return [];

      return Array.from(list.querySelectorAll('[data-room-id]'))
        .map(el => el.dataset.roomId);
    },

    queueSave() {
      // Coalesce multiple quick drops into one request
      clearTimeout(this._saveTimer);
      this._saveTimer = setTimeout(() => this.saveOrder(), 150);
    },

    async saveOrder() {
      if (this.saving) {
        // A save is in-flight; mark that we need another run after it finishes
        this._pending = true;
        return;
      }

      this.saving = true;
      this.status = 'saving';
      this.errorMsg = null;
      clearTimeout(this._hideTimer);

      try {
        const res = await fetch(this.orderUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': this.csrf,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ order: this.orderIds }),
        });

        if (!res.ok) {
          const msg = await res.text().catch(() => null);
          throw new Error(msg || 'Failed to save order');
        }

        this.savedAt = new Date().toLocaleTimeString();
        this.status = 'saved';

        // Auto-hide "Saved" after 2.5s
        this._hideTimer = setTimeout(() => {
          if (this.status === 'saved') this.status = 'idle';
        }, 2500);
      } catch (e) {
        this.status = 'error';
        this.errorMsg = e?.message ?? 'Failed to save';
      } finally {
        this.saving = false;

        // If another drop happened while saving, run again
        if (this._pending) {
          this._pending = false;
          this.queueSave();
        }
      }
    },
  };
}
