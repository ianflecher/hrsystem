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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

<style>
    :root {
        --bg:            #F4F6F9;
        --surface:       #ffffff;
        --surface-2:     #F7F9FB;
        --border:        #E5E9F0;
        --border-strong: #D3DAE4;
        --ink:           #17202E;
        --ink-2:         #566172;
        --ink-3:         #94A0AE;

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
        --font-head: "Space Grotesk", "Inter", system-ui, sans-serif;
    }

    body {
        font-family: var(--font-body);
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

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
        transform: translateY(-1px);
        border-color: var(--border-strong);
        box-shadow: var(--shadow-md);
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
        outline: 2px solid var(--brand);
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
</style>
