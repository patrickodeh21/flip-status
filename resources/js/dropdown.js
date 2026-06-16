// resources/js/dropdown.js
export default function dropdown({ align = 'right', offset = 8 } = {}) {
  return {
    // state
    id: null,
    open: false,
    align,
    offset,
    panelStyle: '',
    justOpened: false,
    _bound: null,
    _onOtherOpen: null,

    init() {

      const cryptoObj =
        (typeof globalThis !== 'undefined' && globalThis.crypto)
          ? globalThis.crypto
          : (typeof window !== 'undefined' && window.crypto)
            ? window.crypto
            : null

      this.id = (cryptoObj && typeof cryptoObj.randomUUID === 'function')
        ? cryptoObj.randomUUID()
        : ('dd-' + Math.random().toString(36).slice(2))

      // listen for others opening -> close this one
      this._onOtherOpen = (e) => {
        if (e.detail && e.detail !== this.id) {
          this.close()
        }
      }
      window.addEventListener('dropdown:open', this._onOtherOpen)
    },

    toggle() {
      this.open ? this.close() : this.openAndPlace();
    },

    openAndPlace() {
      // tell everyone else to close
      window.dispatchEvent(new CustomEvent('dropdown:open', { detail: this.id }));

      this.open = true;
      // Prevent immediate close from the same click that opened it
      this.justOpened = true;
      queueMicrotask(() => { this.justOpened = false; });

      this.$nextTick(() => {
        // ensure teleported/panel DOM is painted before measuring
        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            this.placePanel();
            const handler = () => this.placePanel();
            window.addEventListener('scroll', handler, true);
            window.addEventListener('resize', handler, { passive: true });
            this._bound = handler;
          });
        });
      });
    },

    close() {
      if (!this.open) return;
      this.open = false;
      if (this._bound) {
        window.removeEventListener('scroll', this._bound, true);
        window.removeEventListener('resize', this._bound);
        this._bound = null;
      }
    },

    placePanel() {
      const btn = this.$refs.button;
      const panel = this.$refs.panel;
      if (!btn || !panel) return;

      const b = btn.getBoundingClientRect();

      // Temporarily show panel to measure without flashing
      const prevVis = panel.style.visibility;
      const prevDisp = panel.style.display;
      const prevMaxW = panel.style.maxWidth;
      panel.style.visibility = 'hidden';
      panel.style.display = 'block';
      panel.style.maxWidth = '100vw';

      const ph = panel.offsetHeight;
      const pw = panel.offsetWidth;

      // Vertical placement (flip up if not enough space below)
      const spaceBelow = window.innerHeight - b.bottom;
      const openUp = spaceBelow < (ph + this.offset);
      let top = openUp ? (b.top - ph - this.offset) : (b.bottom + this.offset);

      // Horizontal placement with clamping
      let left;
      if (this.align === 'left') {
        left = b.left;
        if (left + pw > window.innerWidth - 8) left = Math.max(8, window.innerWidth - pw - 8);
      } else {
        left = b.right - pw;
        if (left < 8) left = 8;
      }

      // Clamp top within viewport (with 8px padding)
      top = Math.max(8, Math.min(top, window.innerHeight - ph - 8));

      this.panelStyle = `top:${Math.round(top)}px; left:${Math.round(left)}px;`;

      // restore styles
      panel.style.visibility = prevVis;
      panel.style.display = prevDisp;
      panel.style.maxWidth = prevMaxW;
    },

    // keyboard nav
    focusNext(ev) {
      const items = Array.from(this.$refs.panel?.querySelectorAll('[data-menu-item]') ?? []);
      if (!items.length) return;
      let idx = items.indexOf(document.activeElement);
      idx = (idx + 1) % items.length;
      items[idx].focus();
      ev.preventDefault();
    },

    focusPrev(ev) {
      const items = Array.from(this.$refs.panel?.querySelectorAll('[data-menu-item]') ?? []);
      if (!items.length) return;
      let idx = items.indexOf(document.activeElement);
      idx = (idx - 1 + items.length) % items.length;
      items[idx].focus();
      ev.preventDefault();
    },
  };
}
