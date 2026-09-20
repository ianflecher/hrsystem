{{-- The HRIS design system, in one place.

     Each portal layout grew its own several-hundred-line <style> block, so the
     same card, button and table ended up drawn six slightly different ways.
     Rather than rewrite all six, this partial is included last in every <head>:
     it restates the shared component classes those layouts already use
     (.dashboard-card, .card-icon, .form-input, .btn-primary, .data-table,
     .status-badge and friends) and wins on source order.

     The tokens are Imprint Production's, taken from its public/css/app.css, so
     the two systems read as one product. --}}

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    :root {
        --bg:            #F4F6F9;
        --surface:       #ffffff;
        --surface-2:     #F7F9FB;
        --border:        #E5E9F0;
        --border-strong: #D3DAE4;
        --ink:           #17202E;
        --ink-2:         #566172;
        --ink-3:         #687588;

        --brand:        #E31B23;
        --brand-hover:  #B5141A;
        --brand-soft:   #FDECEC;
        --accent:       #2563eb;
        --accent-soft:  #eff6ff;

        /* Outcome colours. These stay green/amber/red by meaning, never by
           branding - a red "Approved" badge would misinform. */
        --ok:          #15803d;
        --ok-soft:     #f0fdf4;
        --ok-border:   #bbf7d0;
        --warn:        #b45309;
        --warn-soft:   #fffbeb;
        --warn-border: #fde68a;
        --bad:         #b91c1c;
        --bad-soft:    #fef2f2;
        --bad-border:  #fecaca;

        --radius:    12px;
        --radius-sm: 8px;
        --shadow:    0 1px 2px rgba(19, 30, 51, .04), 0 2px 8px rgba(19, 30, 51, .05);
        --shadow-md: 0 4px 12px rgba(19, 30, 51, .06), 0 12px 28px rgba(19, 30, 51, .08);

        --font-body: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
        --font-head: var(--font-body);
    }

    body {
        font-family: var(--font-body);
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    .hr-shell :is(a, button, input, select, textarea, summary):focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 3px;
    }

    .hr-shell :is(button, input, select, textarea) { font-family: inherit; }
    .hr-shell button:disabled { cursor: not-allowed; opacity: .6; }

    h1, h2, h3, .card-stat {
        font-family: var(--font-head);
        letter-spacing: -0.015em;
    }

    /* ---------- Cards -------------------------------------------------- */
    /* The inherited card carried a 4px brand stripe, a 20px-blur shadow and a
       5px hover jump. One brand stripe is a detail; fifteen on a screen is
       noise, and the deep shadow muddied every edge it touched. */
    .dashboard-card,
    .stat-card,
    .content-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-top: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 1.25rem 1.375rem;
        box-shadow: var(--shadow);
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .dashboard-card:hover,
    .stat-card:hover,
    .content-card:hover {
        transform: none;
        border-color: var(--border-strong);
        box-shadow: var(--shadow);
    }

    .card-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        margin-bottom: .875rem;
    }

    /* One icon treatment, rather than a different hue per tile. */
    .card-icon {
        width: 40px;
        height: 40px;
        flex: none;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: var(--brand-soft);
        color: var(--brand);
    }

    .card-stat {
        font-size: 1.75rem;
        font-weight: 700;
        line-height: 1.1;
        color: var(--ink);
    }

    .card-title {
        font-size: .875rem;
        font-weight: 600;
        color: var(--ink);
        margin-bottom: .125rem;
    }

    .card-subtitle {
        font-size: .8125rem;
        color: var(--ink-2);
        line-height: 1.5;
    }

    /* ---------- Buttons ------------------------------------------------ */
    /* Flat fills. The gradient plus coloured glow read as a 2015 dashboard and
       fought the calm surfaces around them. */
    .btn-primary,
    .btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        padding: .625rem 1.125rem;
        border-radius: var(--radius-sm);
        font-family: var(--font-body);
        font-size: .875rem;
        font-weight: 600;
        line-height: 1.2;
        /* A button label breaking across two lines in a narrow grid cell reads
           as two buttons. */
        white-space: nowrap;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        box-shadow: none;
    }

    .btn-primary {
        background: var(--brand);
        border: 1px solid var(--brand);
        color: #fff;
    }

    .btn-primary:hover {
        background: var(--brand-hover);
        border-color: var(--brand-hover);
        transform: none;
        box-shadow: none;
    }

    .btn-secondary {
        background: var(--surface);
        border: 1px solid var(--border-strong);
        color: var(--ink);
    }

    .btn-secondary:hover {
        background: var(--surface-2);
        border-color: var(--ink-3);
    }

    .btn-primary:focus-visible,
    .btn-secondary:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    /* ---------- Forms -------------------------------------------------- */
    .form-label {
        display: block;
        margin-bottom: .375rem;
        font-size: .75rem;
        font-weight: 600;
        letter-spacing: .02em;
        text-transform: uppercase;
        color: var(--ink-2);
    }

    .form-input,
    select.form-input {
        width: 100%;
        padding: .5625rem .75rem;
        background: var(--surface);
        border: 1px solid var(--border-strong);
        border-radius: var(--radius-sm);
        font-family: var(--font-body);
        font-size: .875rem;
        color: var(--ink);
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    /* Focus takes the calm accent, not the brand: a red ring on a field the
       user has merely tabbed into reads as a validation error. Imprint
       Production makes the same distinction. */
    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
    }

    .form-input::placeholder { color: var(--ink-3); }

    /* ---------- Tables ------------------------------------------------- */
    .data-table {
        width: 100%;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
        box-shadow: var(--shadow);
    }

    /* A neutral header lets the rows carry the colour that means something. */
    .data-table th {
        background: var(--surface-2);
        padding: .75rem 1rem;
        text-align: left;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--ink-2);
        border-bottom: 1px solid var(--border);
    }

    .data-table td {
        padding: .875rem 1rem;
        font-size: .875rem;
        color: var(--ink);
        border-bottom: 1px solid var(--border);
    }

    .data-table tr:last-child td { border-bottom: 0; }
    .data-table tbody tr:hover { background: var(--surface-2); }

    /* ---------- Status badges ------------------------------------------ */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: .375rem;
        padding: .1875rem .5rem;
        border-radius: 999px;
        font-size: .75rem;
        font-weight: 600;
        line-height: 1.4;
        border: 1px solid transparent;
    }

    .status-active     { background: var(--ok-soft);     color: var(--ok);     border-color: var(--ok-border); }
    .status-pending    { background: var(--warn-soft);   color: var(--warn);   border-color: var(--warn-border); }
    .status-onleave    { background: var(--accent-soft); color: var(--accent); border-color: #bfdbfe; }
    .status-inactive   { background: var(--surface-2);   color: var(--ink-2);  border-color: var(--border-strong); }
    .status-terminated { background: var(--bad-soft);    color: var(--bad);    border-color: var(--bad-border); }

    .portal-feedback {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 14px 16px;
        margin-bottom: 20px;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: .875rem;
        line-height: 1.5;
    }
    .portal-feedback--success { background: var(--ok-soft); border-color: var(--ok-border); color: var(--ok); }
    .portal-feedback--error { background: var(--bad-soft); border-color: var(--bad-border); color: var(--bad); }
    .portal-feedback--warning { background: var(--warn-soft); border-color: var(--warn-border); color: var(--warn); }
    .portal-feedback--info { background: var(--accent-soft); border-color: #bfdbfe; color: #1d4ed8; }
    .portal-feedback > i { flex: none; margin-top: 3px; }
    .portal-feedback__message { flex: 1; min-width: 0; }
    .portal-feedback [data-feedback-dismiss] {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: none;
        width: 32px;
        height: 32px;
        padding: 0;
        margin: -5px -6px -5px auto;
        border: 0;
        border-radius: 6px;
        background: transparent;
        color: inherit;
        cursor: pointer;
    }
    .portal-feedback [data-feedback-dismiss]:hover { background: rgba(0, 0, 0, .06); }
    .portal-feedback[hidden] { display: none; }

    /* ------------------------------------------------------------------
       The application shell: a fixed navy sidebar and the page beside it.

       It lived in the HR layout, while the employee portal had its own
       hover-to-expand icon rail - a 65px strip that showed nothing until you
       moused over it. One shell now, worn by both, so the two halves of the
       product are recognisably the same thing. The .hr- names are kept
       because every HR screen already uses them.
       ------------------------------------------------------------------ */
    .hr-shell {
        display: flex;
        min-height: 100vh;
        background: var(--bg, #F4F6F9);
    }

    .hr-sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        z-index: 60;
        width: 244px;
        display: flex;
        flex-direction: column;
        background: var(--sidebar-bg, #0C1626);
        border-right: 1px solid rgba(255, 255, 255, .06);
        transition: transform .2s ease;
    }

    .hr-sidebar__brand {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 18px 18px 16px;
        text-decoration: none;
        flex: 1;
        min-width: 0;
    }

    .hr-sidebar__brand img {
        height: 36px;
        width: 36px;
        object-fit: contain;
        background: #fff;
        border-radius: 50%;
        padding: 2px;
        flex: none;
    }

    .hr-sidebar__brand-name {
        color: #fff;
        font-family: var(--font-head, "Space Grotesk", system-ui, sans-serif);
        font-size: .9375rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .hr-sidebar__brand-sub {
        color: #A3B0C2;
        font-size: .6875rem;
        letter-spacing: .02em;
    }

    .hr-sidebar__label {
        padding: 19px 12px 7px;
        color: #A3B0C2;
        font-size: .6875rem;
        font-weight: 600;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .hr-sidebar__header { display: flex; align-items: center; flex: none; border-bottom: 1px solid rgba(255, 255, 255, .08); }
    .hr-sidebar nav { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 10px 16px; scrollbar-width: thin; scrollbar-color: #40516a transparent; }

    .hr-sidebar .nav-link {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 9px 12px;
        margin-bottom: 2px;
        border-radius: 8px;
        color: #B7C2D2;
        font-size: .875rem;
        font-weight: 500;
        text-decoration: none;
        transition: background-color .12s ease, color .12s ease;
    }

    .hr-sidebar .nav-link i { width: 18px; text-align: center; font-size: .9375rem; }
    .hr-sidebar .nav-link:hover { background: rgba(255, 255, 255, .06); color: #E6EBF2; transform: none; }
    .hr-sidebar :is(a, button):focus-visible { outline-color: #93c5fd; }

    /* The current page, marked by a fill and a rule down its edge rather than
       colour alone. */
    .hr-sidebar .nav-link.active {
        background: var(--sidebar-active, #17233A);
        color: #fff;
        box-shadow: inset 3px 0 0 var(--brand, #E31B23);
    }

    .hr-sidebar .nav-link.active i { color: var(--brand, #E31B23); }

    .hr-sidebar__foot {
        padding: 12px;
        border-top: 1px solid rgba(255, 255, 255, .08);
        flex: none;
    }

    .hr-sidebar__user {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 8px 10px 10px;
        color: #C6CFDC;
        font-size: .8125rem;
    }

    .hr-sidebar__user i { color: var(--brand, #E31B23); }

    .hr-sidebar__user {
        text-decoration: none;
        border-radius: 8px;
        transition: background-color .12s ease;
    }

    .hr-sidebar__user:hover { background: rgba(255, 255, 255, .06); }

    .hr-sidebar__avatar {
        width: 22px;
        height: 22px;
        border-radius: 50%;
        object-fit: cover;
        flex: none;
    }

    .hr-sidebar__logout {
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .5rem;
        padding: .5rem .75rem;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, .18);
        background: transparent;
        color: #C6CFDC;
        font-size: .8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: background-color .12s ease, border-color .12s ease, color .12s ease;
    }

    .hr-sidebar__logout:hover {
        background: var(--brand, #E31B23);
        border-color: var(--brand, #E31B23);
        color: #fff;
    }

    .hr-main { flex: 1; min-width: 0; margin-left: 244px; }

    .hr-topbar {
        display: none;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        background: var(--sidebar-bg, #0C1626);
        color: #fff;
        position: sticky;
        top: 0;
        z-index: 50;
    }

    .hr-topbar__toggle {
        background: none;
        border: 0;
        color: #fff;
        font-size: 1.25rem;
        cursor: pointer;
        line-height: 1;
        width: 44px;
        height: 44px;
        border-radius: 8px;
    }

    .hr-sidebar__close { display: none; flex: none; margin-right: 8px; width: 40px; height: 40px; border: 0; border-radius: 8px; background: transparent; color: #fff; cursor: pointer; }
    .hr-sidebar__close:hover, .hr-topbar__toggle:hover { background: rgba(255, 255, 255, .08); }
    .hr-skip-link { position: fixed; top: 10px; left: 10px; z-index: 80; padding: 12px 16px; border-radius: 8px; background: white; color: var(--ink); transform: translateY(-150%); }
    .hr-skip-link:focus { transform: translateY(0); }

    .hr-content { padding: 0; }

    .hr-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 55;
        background: rgba(12, 22, 38, .55);
    }

    .hr-backdrop.show { display: block; }

    @media (max-width: 1024px) {
        .hr-sidebar { width: min(288px, calc(100vw - 48px)); transform: translateX(-100%); visibility: hidden; }
        .hr-sidebar.open { transform: translateX(0); visibility: visible; }
        .hr-sidebar .nav-link { min-height: 44px; }
        .hr-sidebar__close { display: inline-flex; align-items: center; justify-content: center; }
        .hr-main { margin-left: 0; }
        .hr-topbar { display: flex; }
    }

    @media (prefers-reduced-motion: reduce) {
        .hr-shell *, .hr-shell *::before, .hr-shell *::after { scroll-behavior: auto !important; transition: none !important; animation: none !important; }
    }

</style>

<script>
    (() => {
        if (window.hrPortalShellReady) return;
        window.hrPortalShellReady = true;
        let cleanUp = () => {};

        function initializePortalShell() {
            cleanUp();
            const shell = document.querySelector('[data-portal-shell]');
            if (!shell) return;

            const sidebar = shell.querySelector('.hr-sidebar');
            const main = shell.querySelector('.hr-main');
            const backdrop = shell.querySelector('.hr-backdrop');
            const toggle = shell.querySelector('[data-sidebar-toggle]');
            const close = shell.querySelector('[data-sidebar-close]');
            const viewport = window.matchMedia('(max-width: 1024px)');
            const controller = new AbortController();
            const options = { signal: controller.signal };
            let previousFocus = null;
            let previousOverflow = null;

            function setOpen(open, returnFocus = true) {
                open = open && viewport.matches;
                const wasOpen = sidebar.classList.contains('open');
                if (open && !wasOpen) {
                    previousFocus = document.activeElement;
                    previousOverflow = document.body.style.overflow;
                    document.body.style.overflow = 'hidden';
                }

                sidebar.classList.toggle('open', open);
                backdrop.classList.toggle('show', open);
                toggle.setAttribute('aria-expanded', String(open));
                toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
                sidebar.inert = viewport.matches && !open;
                main.inert = open;

                if (open) {
                    sidebar.setAttribute('role', 'dialog');
                    sidebar.setAttribute('aria-modal', 'true');
                    close.focus();
                } else {
                    sidebar.removeAttribute('role');
                    sidebar.removeAttribute('aria-modal');
                    if (previousOverflow !== null) {
                        document.body.style.overflow = previousOverflow;
                        previousOverflow = null;
                    }
                    if (wasOpen && returnFocus && viewport.matches) {
                        (previousFocus?.isConnected ? previousFocus : toggle).focus();
                    }
                }
            }

            toggle.addEventListener('click', () => setOpen(!sidebar.classList.contains('open')), options);
            close.addEventListener('click', () => setOpen(false), options);
            backdrop.addEventListener('click', () => setOpen(false), options);
            sidebar.addEventListener('click', event => {
                if (event.target.closest('a[href]') && viewport.matches) setOpen(false, false);
            }, options);
            document.addEventListener('keydown', event => {
                if (!sidebar.classList.contains('open')) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    setOpen(false);
                }
                if (event.key === 'Tab') {
                    const focusable = [...sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex="0"]')]
                        .filter(element => element.getClientRects().length);
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                }
            }, options);
            viewport.addEventListener('change', () => {
                const focusWasInSidebar = sidebar.contains(document.activeElement);
                setOpen(false, false);
                if (viewport.matches && focusWasInSidebar) toggle.focus();
                if (!viewport.matches && document.activeElement === close) sidebar.querySelector('a').focus();
            }, options);
            setOpen(false, false);
            cleanUp = () => {
                setOpen(false, false);
                controller.abort();
            };
        }

        document.addEventListener('click', event => {
            const button = event.target.closest('[data-feedback-dismiss]');
            if (!button) return;
            const message = button.closest('.portal-feedback');
            if (!message) return;
            message.hidden = true;
        });
        document.addEventListener('livewire:navigating', () => cleanUp());
        document.addEventListener('livewire:navigated', initializePortalShell);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializePortalShell);
        else initializePortalShell();
    })();
</script>
