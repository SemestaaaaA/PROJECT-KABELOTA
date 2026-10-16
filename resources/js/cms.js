// Mode CMS editor. Loaded only for an admin with Mode edit on (layout + components/cms.blade.php).
// Click a text with the dashed orange outline to edit it in place. Saving asks for confirmation with the
// old and new text side by side; history and "back to the original text" are confirmed too.
// Typed markup is never rendered through innerHTML: previews are rebuilt node by node from the same
// allowlist the server uses (b, strong, i, em, br, a), and the server sanitizes again on save.

const root = document.documentElement;
const token = document.querySelector('[data-cms-toggle] input[name="_token"]')?.value ?? '';
const SINGLE_LINE = new Set(['H1', 'H2', 'H3', 'B']);
const INLINE = new Set(['B', 'STRONG', 'I', 'EM']);
const DROP = new Set(['SCRIPT', 'STYLE', 'TEMPLATE', 'NOSCRIPT', 'IFRAME', 'OBJECT', 'EMBED', 'SVG', 'MATH', 'TEXTAREA', 'SELECT', 'BUTTON']);
const BLOCK = new Set(['DIV', 'P', 'LI', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'TR']);
const CONFLICT = 'Teks sudah diubah admin lain, muat ulang.';

let current = null; // { el, original: Node[], originalHtml }
let modal = null;

/* ---------- Safe copies (no innerHTML of typed or stored markup) ---------- */

/** Same rules as App\Support\SiteText::safeHref: http(s), mailto, or a same-site relative path. */
function safeHref(raw) {
    const href = (raw ?? '').replace(/[\u0000-\u001F\u007F]/g, '').trim();
    const compact = href.replace(/\s+/g, '');
    if (!href || compact.startsWith('//') || compact.startsWith('\\')) return null;
    if (/^(https?:\/\/|mailto:)/i.test(compact)) return href;
    const beforePath = compact.split(/[/?#]/)[0];
    return beforePath.includes(':') ? null : href;
}

/** A fragment with only allowed tags; other elements are unwrapped, script-like ones dropped. */
function cleanCopy(source, inLink = false) {
    const frag = document.createDocumentFragment();
    for (const node of source.childNodes) {
        if (node.nodeType === Node.TEXT_NODE) {
            frag.append(document.createTextNode(node.nodeValue.replace(/ /g, ' ')));
            continue;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) continue;
        const tag = node.tagName.toUpperCase();
        if (DROP.has(tag)) continue;
        if (tag === 'BR') {
            frag.append(document.createElement('br'));
        } else if (INLINE.has(tag)) {
            const el = document.createElement(tag.toLowerCase());
            el.append(cleanCopy(node, inLink));
            frag.append(el);
        } else if (tag === 'A' && !inLink && safeHref(node.getAttribute('href'))) {
            const a = document.createElement('a');
            a.setAttribute('href', safeHref(node.getAttribute('href')));
            a.setAttribute('rel', 'noopener noreferrer');
            a.append(cleanCopy(node, true));
            frag.append(a);
        } else {
            const inner = cleanCopy(node, inLink);
            if (BLOCK.has(tag) && frag.childNodes.length && frag.lastChild.nodeName !== 'BR') frag.append(document.createElement('br'));
            frag.append(inner);
        }
    }
    return frag;
}

/** Serialize a clean fragment (reading markup we built ourselves, never rendering it). */
function serialize(frag) {
    const box = document.createElement('div');
    box.append(frag);
    return box.innerHTML.replace(/^(\s|<br>)+|(\s|<br>)+$/g, '');
}

/** Parse markup in an inert document (scripts never run there) and return a clean fragment. */
function fromHtml(html) {
    return cleanCopy(new DOMParser().parseFromString(`<body>${html ?? ''}</body>`, 'text/html').body);
}

function nodesFragment(nodes) {
    const holder = document.createElement('div');
    nodes.forEach((n) => holder.append(n.cloneNode(true)));
    return cleanCopy(holder);
}

/* ---------- Small UI helpers ---------- */

function h(tag, attrs = {}, ...children) {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (k === 'class') el.className = v;
        else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
        else if (v !== false && v != null) el.setAttribute(k, v === true ? '' : v);
    }
    el.append(...children.filter((c) => c != null));
    return el;
}

function toast(text) {
    const el = h('div', { class: 'toast', role: 'status', 'aria-live': 'polite' }, h('i', { class: 'ph ph-pencil-simple', 'aria-hidden': 'true' }), h('span', {}, text));
    document.body.append(el);
    requestAnimationFrame(() => el.classList.add('is-on'));
    setTimeout(() => el.remove(), 3100);
}

async function request(method, url, body) {
    const res = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
        body: body ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch { /* empty body */ }
    if (!res.ok) {
        const message = res.status === 409 ? (data.message || CONFLICT)
            : res.status === 419 ? 'Sesi berakhir. Muat ulang halaman lalu coba lagi.'
            : res.status === 429 ? 'Terlalu banyak perubahan dalam satu menit. Tunggu sebentar lalu coba lagi.'
            : (Object.values(data.errors ?? {})[0]?.[0] ?? data.message ?? 'Gagal menyimpan. Coba lagi.');
        throw Object.assign(new Error(message), { status: res.status });
    }
    return data;
}

const url = (key, suffix = '') => `/cms/${encodeURIComponent(key)}${suffix}`;

/* ---------- Accessible modal (focus trap, Esc, aria-modal) ---------- */

function openModal(title, onEscape) {
    closeModal();
    const titleId = 'cms-modal-h';
    const body = h('div', { class: 'content' });
    const footer = h('footer');
    const heading = h('h2', { id: titleId }, title);
    const sheet = h('div', { class: 'sheet', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': titleId, tabindex: '-1' },
        h('header', {}, heading), body, footer);
    const bg = h('div', { class: 'modal-bg cms-modal' }, sheet);
    const returnTo = document.activeElement;

    const focusables = () => [...sheet.querySelectorAll('button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])')];
    const onKey = (e) => {
        if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); (modal?.onEscape ?? closeModal)(); return; }
        if (e.key !== 'Tab') return;
        const items = focusables();
        if (!items.length) { e.preventDefault(); return; }
        const first = items[0];
        const last = items[items.length - 1];
        if (e.shiftKey && (document.activeElement === first || document.activeElement === sheet)) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
    bg.addEventListener('keydown', onKey);
    bg.addEventListener('mousedown', (e) => { if (e.target === bg) (modal?.onEscape ?? closeModal)(); });

    document.body.append(bg);
    root.classList.add('no-scroll');
    modal = { bg, sheet, body, footer, heading, returnTo, onEscape };
    requestAnimationFrame(() => (focusables().find((b) => b.dataset.autofocus !== undefined) ?? focusables()[0] ?? sheet).focus());
    return modal;
}

function closeModal() {
    if (!modal) return;
    const { bg, returnTo } = modal;
    modal = null;
    bg.remove();
    root.classList.remove('no-scroll');
    if (returnTo?.isConnected) returnTo.focus({ preventScroll: true });
}

function setModal(build) {
    modal.body.replaceChildren();
    modal.footer.replaceChildren();
    build(modal.body, modal.footer);
    requestAnimationFrame(() => (modal?.footer.querySelector('[data-autofocus]') ?? modal?.footer.querySelector('button'))?.focus());
}

const errorLine = () => h('p', { class: 'cms-err', role: 'alert', hidden: true });

/* ---------- Toolbar ---------- */

const bar = h('div', { class: 'cms-bar', role: 'toolbar', 'aria-label': 'Alat edit teks', hidden: true });
const tool = (cmd, label, icon, extra = {}) => h('button', { type: 'button', class: 'cms-tool', 'data-cmd': cmd, 'aria-label': label, title: label, ...extra }, icon);
bar.append(
    tool('bold', 'Tebal', h('b', {}, 'B')),
    tool('italic', 'Miring', h('i', {}, 'I')),
    tool('link', 'Tautan', h('i', { class: 'ph ph-link', 'aria-hidden': 'true' })),
    h('span', { class: 'cms-sep', 'aria-hidden': 'true' }),
    h('button', { type: 'button', class: 'btn btn-line btn-sm', 'data-cmd': 'history' }, h('i', { class: 'ph ph-clock-counter-clockwise', 'aria-hidden': 'true' }), 'Riwayat teks ini'),
    h('button', { type: 'button', class: 'btn btn-line btn-sm', 'data-cmd': 'cancel' }, 'Batal'),
    h('button', { type: 'button', class: 'btn btn-accent btn-sm', 'data-cmd': 'save' }, 'Simpan'),
);
document.body.append(bar);

// Keep the text selection inside the edited element while pressing toolbar buttons.
bar.addEventListener('mousedown', (e) => { if (e.target.closest('.cms-tool')) e.preventDefault(); });
bar.addEventListener('click', (e) => {
    const cmd = e.target.closest('[data-cmd]')?.dataset.cmd;
    if (!cmd || !current) return;
    if (cmd === 'bold' || cmd === 'italic') { current.el.focus(); document.execCommand(cmd); }
    if (cmd === 'link') addLink();
    if (cmd === 'cancel') cancelEdit();
    if (cmd === 'save') confirmSave();
    if (cmd === 'history') openHistory(current.el);
});

function addLink() {
    const sel = getSelection();
    const range = sel.rangeCount ? sel.getRangeAt(0) : null;
    if (!range || range.collapsed || !current.el.contains(range.commonAncestorContainer)) {
        toast('Pilih kata yang mau dijadikan tautan dulu.');
        return;
    }
    const value = prompt('Alamat tautan (https://..., mailto:... atau halaman seperti /lowongan). Kosongkan untuk menghapus tautan.', '');
    if (value === null) return;
    current.el.focus();
    sel.removeAllRanges();
    sel.addRange(range);
    if (value.trim() === '') { document.execCommand('unlink'); return; }
    const href = safeHref(value);
    if (!href) { toast('Alamat tidak didukung. Pakai https://, mailto: atau halaman seperti /lowongan.'); return; }
    document.execCommand('createLink', false, href);
}

/* ---------- Editing ---------- */

const editables = [...document.querySelectorAll('.cms-editable')];
editables.forEach((el) => {
    el.tabIndex = 0;
    el.title = 'Klik untuk mengedit teks ini';
});

function isDirty() {
    return current && serialize(cleanCopy(current.el)) !== current.originalHtml;
}

function startEdit(el) {
    if (current?.el === el) return;
    if (current) {
        if (isDirty()) { toast('Simpan atau batalkan teks yang sedang diedit dulu.'); current.el.focus(); return; }
        endEdit(false);
    }
    current = { el, original: [...el.childNodes].map((n) => n.cloneNode(true)), originalHtml: serialize(cleanCopy(el)) };
    el.contentEditable = 'true';
    el.setAttribute('role', 'textbox');
    el.setAttribute('aria-label', 'Edit teks');
    if (!SINGLE_LINE.has(el.tagName)) el.setAttribute('aria-multiline', 'true');
    el.removeAttribute('title');
    el.classList.add('is-editing');
    root.classList.add('cms-editing');
    bar.hidden = false;
    el.focus();
    const sel = getSelection();
    if (!sel.rangeCount || !el.contains(sel.anchorNode)) {
        sel.selectAllChildren(el);
        sel.collapseToEnd();
    }
}

function endEdit(restore) {
    if (!current) return;
    const { el, original } = current;
    if (restore) el.replaceChildren(...original.map((n) => n.cloneNode(true)));
    el.removeAttribute('contenteditable');
    el.removeAttribute('role');
    el.removeAttribute('aria-label');
    el.removeAttribute('aria-multiline');
    el.title = 'Klik untuk mengedit teks ini';
    el.classList.remove('is-editing');
    root.classList.remove('cms-editing');
    bar.hidden = true;
    current = null;
    el.focus({ preventScroll: true });
}

function cancelEdit() {
    endEdit(true);
}

// Links inside editable text must not navigate while Mode edit is on (capture: before motion.js sees it).
document.addEventListener('click', (e) => {
    const el = e.target.closest('.cms-editable');
    if (!el) return;
    if (e.target.closest('a')) e.preventDefault();
    startEdit(el);
}, true);

document.addEventListener('keydown', (e) => {
    const el = e.target.closest?.('.cms-editable');
    if (!el || modal) return;
    if (current?.el !== el) {
        if (e.key === 'Enter' || e.key === 'F2') { e.preventDefault(); startEdit(el); }
        return;
    }
    if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); }
    else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') { e.preventDefault(); confirmSave(); }
    else if (e.key === 'Enter') {
        e.preventDefault();
        if (!SINGLE_LINE.has(el.tagName)) document.execCommand('insertLineBreak');
    }
});

// Pasted text arrives as plain text; dropped content is refused.
document.addEventListener('paste', (e) => {
    if (!current || !current.el.contains(e.target)) return;
    e.preventDefault();
    const text = (e.clipboardData?.getData('text/plain') ?? '').replace(/\s*\n\s*/g, SINGLE_LINE.has(current.el.tagName) ? ' ' : '\n');
    document.execCommand('insertText', false, text);
});
document.addEventListener('drop', (e) => { if (current?.el.contains(e.target)) e.preventDefault(); });

addEventListener('beforeunload', (e) => { if (isDirty()) e.preventDefault(); });

/* ---------- Save with confirmation ---------- */

function compareBox(label, fragment) {
    return h('div', { class: 'cms-side' }, h('span', { class: 'lbl' }, label), h('div', { class: 'cms-text' }, fragment));
}

function confirmSave() {
    if (!current) return;
    const { el, original, originalHtml } = current;
    const html = serialize(cleanCopy(el));
    if (!fromHtml(html).textContent.trim()) { toast('Teks tidak boleh kosong.'); el.focus(); return; }
    if (html === originalHtml) { toast('Belum ada perubahan.'); el.focus(); return; }

    let failed = false;
    openModal('Simpan perubahan?', () => { closeModal(); el.focus(); });
    setModal((body, footer) => {
        const err = errorLine();
        body.append(
            h('p', { class: 'demo-note' }, 'Pengunjung langsung melihat teks baru setelah disimpan.'),
            h('div', { class: 'cms-diff' }, compareBox('Teks lama', nodesFragment(original)), compareBox('Teks baru', fromHtml(html))),
            err,
        );
        const cancel = h('button', { type: 'button', class: 'btn btn-line', onclick: () => {
            closeModal();
            if (failed) el.focus(); // after an error, keep what was typed
            else cancelEdit();
        } }, 'Batal');
        const save = h('button', { type: 'button', class: 'btn btn-accent', 'data-autofocus': true, onclick: async () => {
            save.disabled = true;
            save.textContent = 'Menyimpan...';
            err.hidden = true;
            try {
                const data = await request('PUT', url(el.dataset.cmsKey), { html, expected: el.dataset.cmsUpdated ?? '' });
                el.replaceChildren(fromHtml(data.html));
                el.dataset.cmsUpdated = data.updated ?? '';
                current.original = [...el.childNodes].map((n) => n.cloneNode(true));
                closeModal();
                endEdit(false);
                toast('Teks tersimpan.');
            } catch (error) {
                failed = true;
                err.textContent = error.status === 409 ? CONFLICT : error.message;
                err.hidden = false;
                cancel.textContent = 'Tutup';
                save.textContent = 'Simpan';
                save.disabled = error.status === 409;
                (error.status === 409 ? cancel : save).focus();
            }
        } }, 'Simpan');
        footer.append(cancel, save);
    });
}

/* ---------- History and restore ---------- */

function defaultFragment(el) {
    return fromHtml(el.dataset.cmsDefault ?? '');
}

function openHistory(el) {
    openModal('Riwayat teks ini');
    showHistoryList(el);
}

async function showHistoryList(el) {
    if (!modal) return;
    modal.heading.textContent = 'Riwayat teks ini';
    modal.onEscape = closeModal;
    const list = h('ol', { class: 'cms-hist', 'aria-busy': 'true' }, h('li', { class: 'demo-note' }, 'Memuat riwayat...'));
    const err = errorLine();
    setModal((body, footer) => {
        body.append(h('p', { class: 'demo-note' }, 'Setiap baris adalah teks sebelum diganti, dengan nama admin yang menggantinya.'), list, err);
        footer.append(
            h('button', { type: 'button', class: 'btn btn-line', onclick: () => confirmRestore(el, null) }, 'Kembalikan ke teks asli'),
            h('button', { type: 'button', class: 'btn btn-ink', 'data-autofocus': true, onclick: closeModal }, 'Tutup'),
        );
    });

    try {
        const { revisions } = await request('GET', url(el.dataset.cmsKey, '/riwayat'));
        list.replaceChildren(...(revisions.length ? revisions.map((rev) => h('li', {},
            h('div', { class: 'cms-hist-text' }, rev.text ?? h('em', {}, 'Teks asli')),
            h('div', { class: 'cms-hist-meta' },
                h('span', {}, `Diganti oleh ${rev.user}, `, h('time', { datetime: rev.created_at }, rev.when)),
                h('button', { type: 'button', class: 'btn btn-line btn-sm', onclick: () => confirmRestore(el, rev) }, 'Pulihkan versi ini')),
        )) : [h('li', { class: 'demo-note' }, 'Belum ada perubahan untuk teks ini.')]));
    } catch (error) {
        list.replaceChildren();
        err.textContent = error.message;
        err.hidden = false;
    }
    list.removeAttribute('aria-busy');
}

function confirmRestore(el, rev) {
    modal.heading.textContent = rev ? 'Pulihkan versi ini?' : 'Kembalikan ke teks asli?';
    modal.onEscape = () => showHistoryList(el);
    setModal((body, footer) => {
        const err = errorLine();
        const preview = rev?.text != null ? document.createTextNode(rev.text) : defaultFragment(el);
        body.append(
            compareBox(rev ? 'Teks yang dipulihkan' : 'Teks asli', preview),
            isDirty() ? h('p', { class: 'demo-note' }, 'Perubahan yang belum disimpan akan hilang.') : null,
            err,
        );
        const back = h('button', { type: 'button', class: 'btn btn-line', onclick: () => showHistoryList(el) }, 'Batal');
        const ok = h('button', { type: 'button', class: 'btn btn-accent', 'data-autofocus': true, onclick: async () => {
            ok.disabled = true;
            err.hidden = true;
            try {
                await request('POST', url(el.dataset.cmsKey, '/pulihkan'), rev ? { revision: rev.id } : {});
                current = null; // the page reloads with the restored text
                location.reload();
            } catch (error) {
                err.textContent = error.message;
                err.hidden = false;
                ok.disabled = false;
            }
        } }, rev ? 'Ya, pulihkan' : 'Ya, kembalikan');
        footer.append(back, ok);
    });
}
