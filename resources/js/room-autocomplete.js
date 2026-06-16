// Alpine data factory for the Add Room form
export default function roomAutocomplete({ suggestUrl, csrf }) {
    return {
        suggestUrl,
        csrf,

        // state
        q: '',
        open: false,
        loading: false,
        focusedIndex: -1,
        items: [], // [{id, name, is_default}]

        // derived
        get hasResults() { return this.items.length > 0 },
        get highlighted() { return this.items[this.focusedIndex] || null },

        // lifecycle
        init() {
            // close on click outside
            document.addEventListener('click', (e) => {
                if (!this.$root.contains(e.target)) this.open = false;
            });
        },

        // actions
        onInput() {
            this.open = true;
            this.debouncedFetch();
        },

        onFocus() {
            if (this.q.trim() !== '') {
                this.open = true;
                this.debouncedFetch();
            }
        },

        choose(item) {
            this.q = item.name; // set the input text
            this.open = false;
            this.focusedIndex = -1;
        },

        createLabel() {
            return `Create "${this.q}"`;
        },

        keyDown(e) {
            if (!this.open) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.focusedIndex = (this.focusedIndex + 1) % Math.max(this.items.length + 1, 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.focusedIndex = (this.focusedIndex - 1 + (this.items.length + 1)) % (this.items.length + 1);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                // If index points to "create new"
                if (this.focusedIndex === this.items.length || this.items.length === 0) {
                    this.open = false;
                } else if (this.items[this.focusedIndex]) {
                    this.choose(this.items[this.focusedIndex]);
                }
            } else if (e.key === 'Escape') {
                e.stopPropagation();
                e.preventDefault();
                this.open = false;
            }
        },

        async fetchSuggestions() {
            const term = this.q.trim();
            if (!term) {
                this.items = [];
                return;
            }
            this.loading = true;
            try {
                const url = new URL(this.suggestUrl, window.location.origin);
                url.searchParams.set('q', term);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                this.items = res.ok ? await res.json() : [];
            } catch {
                this.items = [];
            } finally {
                this.loading = false;
            }
        },

        // small debounce
        _timer: null,
        debouncedFetch() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.fetchSuggestions(), 160);
        },
    };
}
