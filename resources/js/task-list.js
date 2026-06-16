export default function taskList({ orderUrl, csrf }) {
  return {
    orderUrl, csrf,
    status: 'idle', savedAt: null, saving: false, _pending: false, _t: null, _hide: null,

    init() {
      if (!this.$refs?.tbody) return;
      new window.Sortable(this.$refs.tbody, {
        animation: 160,
        handle: '.drag-handle',
        draggable: 'tr[data-task-id]',
        onEnd: () => this.queueSave(),
      });
    },

    get ids() {
      return Array.from(this.$refs.tbody.querySelectorAll('tr[data-task-id]'))
        .map(tr => tr.dataset.taskId);
    },

    queueSave() {
      clearTimeout(this._t);
      this._t = setTimeout(() => this.save(), 150);
    },

    async save() {
      if (this.saving) { this._pending = true; return; }
      this.saving = true; this.status = 'saving'; clearTimeout(this._hide);
      try {
        const res = await fetch(this.orderUrl, {
          method: 'POST',
          headers: {'X-CSRF-TOKEN': this.csrf, 'Accept':'application/json', 'Content-Type':'application/json'},
          body: JSON.stringify({ order: this.ids })
        });
        if (!res.ok) throw new Error(await res.text() || 'Failed');
        this.status = 'saved'; this.savedAt = new Date().toLocaleTimeString();
        this._hide = setTimeout(() => { if (this.status === 'saved') this.status='idle' }, 2500);
      } catch(e) {
        this.status = 'error'; console.error(e);
      } finally {
        this.saving = false;
        if (this._pending) { this._pending = false; this.queueSave(); }
      }
    },
  };
}
