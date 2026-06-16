
export default function roomPicker({ fetchUrl, postUrl, csrf }) {
  return {
    fetchUrl,
    postUrl,
    csrf,

    query: '',
    open: false,
    suggestions: [],
    selected: [], // { key: 'uuid-ish', id?: number, name: string }

    async search() {
      const q = this.query.trim();
      if (!q) {
        this.suggestions = [];
        this.open = false;
        return;
      }
      try {
        const url = new URL(this.fetchUrl, window.location.origin);
        url.searchParams.set('q', q);
        const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        const json = await res.json();
        this.suggestions = json || [];
        this.open = this.suggestions.length > 0;
      } catch (e) {
        console.error(e);
      }
    },

    addItem(item) {
      // avoid duplicates (by id OR name)
      const exists = this.selected.some(r => (item.id && r.id === item.id) || r.name.toLowerCase() === item.name.toLowerCase());
      if (!exists) {
        this.selected.push({ key: randomUID(), id: item.id, name: item.name });
      }
      this.query = '';
      this.suggestions = [];
      this.open = false;
    },

    enterQuery() {
      const q = this.query.trim();
      if (!q) return;
      // If a suggestion exactly matches, pick it; else create a free-text chip
      const exact = this.suggestions.find(s => s.name.toLowerCase() === q.toLowerCase());
      if (exact) {
        this.addItem(exact);
      } else {
        const exists = this.selected.some(r => r.name.toLowerCase() === q.toLowerCase());
        if (!exists) {
          this.selected.push({ key: randomUUID(), name: q });
        }
      }
      this.query = '';
      this.suggestions = [];
      this.open = false;
    },

    remove(idx) {
      this.selected.splice(idx, 1);
    }
  }
}
