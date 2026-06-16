export default function taskAutocomplete({ suggestUrl }) {
    return {
        suggestUrl, q: '', open: false, loading: false, focusedIndex: -1, items: [],
        get hasResults() { return this.items.length > 0 }, get highlighted() { return this.items[this.focusedIndex] || null },

        init() { document.addEventListener('click', e => { if (!this.$root.contains(e.target)) this.open = false }) },

        // Capitalize text to title case (e.g., "Open Windows For Airing")
        capitalizeText(text) {
            if (!text) return '';
            return text.toLowerCase()
                .split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        },

        onInput() { 
            this.open = true; 
            this.debounce();
            // Debounce capitalization to avoid interrupting typing
            this.debounceCapitalize();
        },
        onFocus() { if (this.q.trim()) { this.open = true; this.debounce() } },
        choose(item) { this.q = item.name; this.open = false; this.focusedIndex = -1; },
        createLabel() { return `Create "${this.q}"` },

        keyDown(e) {
            if (!this.open) return;
            const max = this.items.length;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.focusedIndex = (this.focusedIndex + 1) % (max + 1) }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.focusedIndex = (this.focusedIndex - 1 + (max + 1)) % (max + 1) }
            else if (e.key === 'Enter') { e.preventDefault(); if (this.focusedIndex < max && this.items[this.focusedIndex]) this.choose(this.items[this.focusedIndex]); else this.open = false }
            else if (e.key === 'Escape') { e.stopPropagation(); this.open = false }
        },

        async fetch() {
            const term = this.q.trim(); if (!term) { this.items = []; return; }
            this.loading = true;
            try {
                const url = new URL(this.suggestUrl, window.location.origin); url.searchParams.set('q', term);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                this.items = res.ok ? await res.json() : [];
            } catch { this.items = [] }
            finally { this.loading = false }
        },

        _timer: null, debounce() { clearTimeout(this._timer); this._timer = setTimeout(() => this.fetch(), 160) },
        
        // Debounced capitalization
        _capitalizeTimer: null,
        debounceCapitalize() {
            clearTimeout(this._capitalizeTimer);
            this._capitalizeTimer = setTimeout(() => {
                if (this.q && this.q.trim()) {
                    const capitalized = this.capitalizeText(this.q);
                    // Only update if different to avoid cursor jumping
                    if (capitalized !== this.q) {
                        this.q = capitalized;
                    }
                }
            }, 500); // 500ms debounce for capitalization
        },
    };
}
