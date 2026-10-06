// Page motion: intro loader (refresh and outside arrivals), navigation progress bar, list skeletons,
// image fade-in and scroll reveal. Everything is transform/opacity only and is
// skipped entirely when the visitor asks for reduced motion.

const root = document.documentElement;
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2); // easeInOutCubic

/* ---------- 1. Intro loader (on refresh and outside arrivals, decided in <head>) ---------- */

const INTRO_MS = 2200; // counter runs 0 to 100 in this time
const INTRO_MAX_MS = 3000; // never hold the page longer than this, even on a slow network
const STATUS = [
    [0, 'Menyiapkan peta proyek Sulawesi Tengah'],
    [35, 'Memuat talenta Teknik Sipil UNTAD'],
    [75, 'Mencocokkan SKK dan jenjang'],
    [100, 'Siap.'],
];

function runIntro(onDone) {
    const el = document.getElementById('intro');
    if (!el || !root.classList.contains('kb-intro')) return onDone();

    const num = el.querySelector('[data-intro-num]');
    const bar = el.querySelector('[data-intro-bar]');
    const status = el.querySelector('[data-intro-status]');
    const start = performance.now();
    let loaded = document.readyState === 'complete';
    let finished = false;
    addEventListener('load', () => { loaded = true; }, { once: true });

    const finish = () => {
        if (finished) return;
        finished = true;
        removeEventListener('keydown', skip);
        el.classList.add('is-done');
        root.classList.add('kb-intro-out');
        // Let the curtain clear the hero before the hero animates in.
        setTimeout(() => {
            root.classList.add('kb-hero-in');
            onDone();
        }, 380);
        setTimeout(() => {
            el.remove();
            root.classList.remove('kb-intro', 'kb-intro-out');
        }, 900);
    };

    // Click or any key jumps straight to the end, without waiting for the next animation frame.
    const skip = () => {
        num.textContent = 100;
        bar.style.transform = 'scaleX(1)';
        status.textContent = STATUS[STATUS.length - 1][1];
        finish();
    };
    el.addEventListener('click', skip);
    addEventListener('keydown', skip, { once: true });
    // Safety net: rAF pauses in background tabs, so a timer guarantees the page is never stuck.
    setTimeout(finish, INTRO_MAX_MS + 300);

    const frame = (now) => {
        const elapsed = now - start;
        let t = Math.min(elapsed / INTRO_MS, 1);
        // Hold at 92% while the page is still loading, up to the hard limit.
        if (!loaded && elapsed < INTRO_MAX_MS) t = Math.min(t, 0.92);
        const pct = Math.round(ease(t) * 100);

        num.textContent = pct;
        bar.style.transform = `scaleX(${pct / 100})`;
        status.textContent = STATUS.filter(([at]) => pct >= at).pop()[1];

        if (finished) return;
        if (pct >= 100) setTimeout(finish, 180);
        else requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}

/* ---------- 2. Navigation progress bar + skeletons for same-page result updates ---------- */

const progress = document.querySelector('.nav-progress');

const SKELETON = {
    talent: () => `<div class="skel-card">
        <div class="skel-row"><span class="skel skel-av"></span><span class="skel-lines"><span class="skel skel-line" style="width:72%"></span><span class="skel skel-line" style="width:48%"></span></span></div>
        <span class="skel skel-block"></span>
        <span class="skel skel-line" style="width:36%;margin:12px"></span></div>`,
    job: () => `<div class="skel-card">
        <span class="skel-strip"></span>
        <div style="padding:18px;display:grid;gap:10px"><span class="skel skel-line" style="width:80%;height:16px"></span><span class="skel skel-line" style="width:45%"></span><span class="skel skel-line" style="width:60%"></span><span class="skel skel-line" style="width:30%;height:30px;margin-top:6px"></span></div></div>`,
    row: () => `<div class="skel-tr"><span class="skel skel-av sm"></span><span class="skel skel-line" style="width:28%"></span><span class="skel skel-line" style="width:14%"></span><span class="skel skel-line" style="width:18%"></span><span class="skel skel-line" style="width:12%"></span></div>`,
};

function showSkeletons() {
    document.querySelectorAll('[data-skeleton]').forEach((list) => {
        if (list.nextElementSibling?.classList.contains('skel-wrap')) return;
        const [kind, count] = list.dataset.skeleton.split(':');
        const wrap = document.createElement('div');
        wrap.className = `skel-wrap ${kind === 'row' ? 'skel-rows' : list.className}`;
        wrap.setAttribute('aria-hidden', 'true');
        wrap.innerHTML = Array.from({ length: Number(count) || 6 }, SKELETON[kind] || SKELETON.talent).join('');
        list.hidden = true;
        list.after(wrap);
        list.closest('[aria-live], .results')?.setAttribute('aria-busy', 'true');
    });
}

function startNavigation(url) {
    progress?.classList.remove('is-on');
    void progress?.offsetWidth; // restart the animation
    progress?.classList.add('is-on');
    // Same page with new filters, sorting or page number: show skeletons where the results were.
    if (url.pathname === location.pathname && url.search !== location.search) showSkeletons();
}

document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (a.target && a.target !== '_self') return;
    if (a.hasAttribute('download') || a.getAttribute('href').startsWith('#')) return;
    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search)) return;
    if (/^(mailto|tel):/.test(a.href)) return;
    startNavigation(url);
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (e.defaultPrevented || (form.target && form.target !== '_self')) return;
    const url = new URL(form.action || location.href, location.href);
    if (url.origin !== location.origin) return;
    if ((form.method || 'get').toLowerCase() === 'get') url.search = new URLSearchParams(new FormData(form)).toString();
    startNavigation(url);
});

// Coming back with the browser's back button restores the old page from cache: clean up.
addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    progress?.classList.remove('is-on');
    document.querySelectorAll('.skel-wrap').forEach((w) => w.remove());
    document.querySelectorAll('[data-skeleton]').forEach((l) => { l.hidden = false; });
    document.querySelectorAll('[aria-busy]').forEach((el) => el.removeAttribute('aria-busy'));
});

/* ---------- 3. Images fade in once decoded (the frame shows a shimmer until then) ---------- */

function fadeImages() {
    document.querySelectorAll('.av img, .co-logo img').forEach((img) => {
        const done = () => img.classList.add('is-loaded');
        if (img.complete) done();
        else {
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
        }
    });
}

/* ---------- 4. Scroll reveal for content below the fold ---------- */

const REVEAL = ['main > section:not(.hero)', '.page-h', '.tgrid > *', '.jobs > *', '.inbox-card', '.formcard', '.profile > *', '.co-hero', '.empty'];

function reveal() {
    if (reduce || !('IntersectionObserver' in window)) return;
    const fold = innerHeight * 0.9;
    const targets = [];

    document.querySelectorAll(REVEAL.join(',')).forEach((el) => {
        // One animation per area: skip anything inside an element that already animates.
        if (el.closest('.rv') || el.getBoundingClientRect().top < fold) return;
        const siblings = el.parentElement ? [...el.parentElement.children] : [];
        el.style.setProperty('--i', Math.min(siblings.indexOf(el) % 6, 5));
        el.classList.add('rv');
        targets.push(el);
    });

    const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('rv-in');
            io.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px' });
    targets.forEach((el) => io.observe(el));
}

/* ---------- boot ---------- */

fadeImages();
runIntro(reveal);
