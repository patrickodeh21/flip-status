// Your code + a tiny optional replace() helper
export default function mediaDropzone({ formId, accept = 'image/*,video/*', multiple = true }) {
  return {
    formId, accept, multiple,
    hover: false,
    previews: [], // { file, url, kind, caption }

    pick() { this.$refs.input.click() },

    handleChoose(e) { this.addFiles(e.target.files) },

    handleDrop(e) {
      this.hover = false;
      const files = e.dataTransfer?.files;
      if (!files?.length) return;
      this.addFiles(files);
    },

    addFiles(fileList) {
      const allowed = (this.accept || '').split(',').map(s => s.trim());
      const dt = new DataTransfer(); // rebuild input.files

      // keep existing files already chosen
      for (const f of this.$refs.input.files) dt.items.add(f);

      for (const file of fileList) {
        if (!this.isAllowed(file, allowed)) continue;
        dt.items.add(file);
        this.previews.push({
          file,
          url: URL.createObjectURL(file),
          kind: file.type.startsWith('video') ? 'video' : 'image',
          caption: ''
        });
      }
      this.$refs.input.files = dt.files;
    },

    // Optional: allow full-card click "replace this file"
    replace(idx, file) {
      if (!file) return;
      const allowed = (this.accept || '').split(',').map(s => s.trim());
      if (!this.isAllowed(file, allowed)) return;

      const dt = new DataTransfer();
      this.previews.forEach((p, i) => dt.items.add(i === idx ? file : p.file));
      this.$refs.input.files = dt.files;

      try { URL.revokeObjectURL(this.previews[idx]?.url); } catch {}
      const oldCaption = this.previews[idx]?.caption || '';
      this.previews[idx] = {
        file,
        url: URL.createObjectURL(file),
        kind: file.type.startsWith('video') ? 'video' : 'image',
        caption: oldCaption
      };
    },

    remove(idx) {
      const dt = new DataTransfer();
      this.previews.forEach((p, i) => { if (i !== idx) dt.items.add(p.file) });
      this.$refs.input.files = dt.files;

      URL.revokeObjectURL(this.previews[idx]?.url);
      this.previews.splice(idx, 1);
    },

    isAllowed(file, allowed) {
      if (!allowed.length) return true;
      return allowed.some(a => {
        if (a.endsWith('/*')) return file.type.startsWith(a.slice(0, -1));
        return file.type === a;
      });
    }
  };
}
