export default function taskPicker({ fetchUrl, postUrl, csrf }) {
  return {
    fetchUrl,
    postUrl,
    csrf,

    query: '',
    open: false,
    suggestions: [],       // [{id, name}]
    selected: [],          // [{key, id?, name}]
    highlighted: -1,

    async search() {
      const q = this.query.trim();
      if (!q) {
        this.suggestions = [];
        this.open = false;
        this.highlighted = -1;
        return;
      }
      try {
        const url = new URL(this.fetchUrl, window.location.origin);
        url.searchParams.set('q', q);
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        const json = await res.json();
        const arr = Array.isArray(json) ? json : (Array.isArray(json) ? json : []);
        this.suggestions = arr;
        this.open = this.suggestions.length > 0;
        this.highlighted = this.open ? 0 : -1;
      } catch (e) {
        console.error(e);
      }
    },

    addItem(item) {
      const exists = this.selected.some(
        r => (item.id && r.id === item.id) || r.name.toLowerCase() === item.name.toLowerCase()
      );
      if (!exists) {
        this.selected.push({ key: randomUID(), id: item.id, name: item.name });
      }
      this.resetList();
      this.query = '';
    },

    enterQuery() {
      const q = this.query.trim();
      if (!q) return;
      const exact = this.suggestions.find(s => s.name.toLowerCase() === q.toLowerCase());
      if (exact) {
        this.addItem(exact);
      } else {
        const exists = this.selected.some(r => r.name.toLowerCase() === q.toLowerCase());
        if (!exists) this.selected.push({ key: randomUID(), name: q });
        this.resetList();
        this.query = '';
      }
    },

    confirm() {
      if (this.open && this.highlighted >= 0 && this.highlighted < this.suggestions.length) {
        this.addItem(this.suggestions[this.highlighted]);
      } else {
        this.enterQuery();
      }
    },

    move(delta) {
      if (!this.open || this.suggestions.length === 0) return;
      const len = this.suggestions.length;
      let next = this.highlighted + delta;
      if (next < 0) next = len - 1;
      if (next >= len) next = 0;
      this.highlighted = next;

      this.$nextTick(() => {
        const list = this.$refs.list;
        if (!list) return;
        const opt = list.querySelector(`[data-idx="${this.highlighted}"]`);
        opt?.scrollIntoView?.({ block: 'nearest' });
      });
    },

    hoverIndex(i) { this.highlighted = i; },

    remove(idx) { this.selected.splice(idx, 1); },

    resetList() {
      this.suggestions = [];
      this.open = false;
      this.highlighted = -1;
    }
  };
}
