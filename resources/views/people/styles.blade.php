<style>
    .people {max-width:1280px;margin:0 auto;padding:28px;color:var(--ink);font-family:var(--font-body)}
    .people *, .people *::before, .people *::after {box-sizing:border-box}
    .people h1 {font-size:clamp(24px,3vw,30px);font-weight:700;letter-spacing:-.8px;line-height:1.2;margin:0 0 8px}
    .people h2 {font-size:17px;font-weight:700;margin:0 0 10px;line-height:1.4}
    .people h3 {font-size:15px;font-weight:700;margin:0 0 10px}
    .people p {line-height:1.6;margin:8px 0}
    .people .muted, .people .field-hint {color:var(--ink-2);font-size:13px;font-weight:400}
    .people .page-header {margin-bottom:24px}
    .people .page-eyebrow {color:var(--ink-2);font-size:11px;font-weight:600;letter-spacing:.1em;text-transform:uppercase;margin:0 0 12px}
    .people .page-description {max-width:70ch;color:var(--ink-2);font-size:14px;margin:0}
    .people .card {background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:22px;margin-bottom:18px;box-shadow:var(--shadow)}
    .people .grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}
    .people .wide {grid-column:1/-1}
    .people .people-field {display:flex;flex-direction:column;gap:7px;font-size:13px;font-weight:600;min-width:0}
    .people input:not([type=checkbox]):not([type=radio]):not([type=hidden]), .people select, .people textarea {
        width:100%;min-height:42px;border:1px solid var(--border-strong);border-radius:var(--radius-sm);padding:10px 12px;background:var(--surface);color:var(--ink);font:inherit;font-size:14px;font-weight:400
    }
    .people input[type=checkbox], .people input[type=radio] {accent-color:var(--accent);width:18px;height:18px;flex:none}
    .people textarea {min-height:100px;resize:vertical}
    .people input:focus-visible, .people select:focus-visible, .people textarea:focus-visible, .people summary:focus-visible, .people a:focus-visible, .people button:focus-visible {outline:2px solid var(--accent);outline-offset:3px}
    .people [aria-invalid=true] {border-color:var(--bad)!important;background:var(--bad-soft)!important}
    .people .field-error {font-size:12px;font-weight:500;color:var(--bad);line-height:1.5}
    .people button, .people .button {display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;background:var(--brand);color:#fff;border:1px solid transparent;border-radius:var(--radius-sm);padding:9px 15px;font:600 13px/1.4 var(--font-body);cursor:pointer;text-decoration:none;transition:background .15s,border-color .15s}
    .people button:hover, .people .button:hover {background:var(--brand-hover)}
    .people button.secondary, .people .button.secondary {background:var(--surface);border-color:var(--border-strong);color:var(--ink)}
    .people button.secondary:hover, .people .button.secondary:hover {background:var(--surface-2);border-color:var(--ink-3)}
    .people button.danger, .people .button.danger {background:var(--bad-soft);color:var(--bad);border-color:var(--bad-border)}
    .people button:disabled, .people button[aria-disabled=true] {cursor:wait;opacity:.65}
    .people .row {display:flex;align-items:center;gap:12px;flex-wrap:wrap}
    .people .between {justify-content:space-between}
    .people .badge {display:inline-flex;align-items:center;gap:6px;padding:4px 9px;border:1px solid var(--border);border-radius:20px;background:var(--surface-2);color:var(--ink-2);font-size:12px;font-weight:500;line-height:1.4;text-transform:capitalize;white-space:nowrap}
    .people .people-status--success {background:var(--ok-soft);color:var(--ok);border-color:var(--ok-border)}
    .people .people-status--warning {background:var(--warn-soft);color:var(--warn);border-color:var(--warn-border)}
    .people .people-status--danger {background:var(--bad-soft);color:var(--bad);border-color:var(--bad-border)}
    .people .people-status--info {background:var(--accent-soft);color:var(--accent);border-color:#bfdbfe}
    .people .status-dot {width:6px;height:6px;border-radius:50%;background:currentColor;flex:none}
    .people .alert {padding:14px 16px;border:1px solid var(--ok-border);border-radius:var(--radius-sm);background:var(--ok-soft);color:var(--ok);margin:0 0 18px;font-size:14px}
    .people .warning {background:var(--warn-soft);color:var(--warn);border-color:var(--warn-border)}
    .people .error {background:var(--bad-soft);color:var(--bad);border-color:var(--bad-border)}
    .people .error ul {list-style:disc;padding-left:20px;margin:8px 0 0}
    .people .error a {color:inherit;text-decoration:underline;text-underline-offset:3px}
    .people .divider {margin:18px 0 0;border-top:1px solid var(--border);padding-top:18px}
    .people .scroll {overflow-x:auto}
    .people table {width:100%;border-collapse:collapse;font-size:13px}
    .people td, .people th {text-align:left;padding:13px 12px;border-bottom:1px solid var(--border)}
    .people th {font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--ink-2);background:var(--surface-2);font-weight:600}
    .people tbody tr:last-child td {border-bottom:0}
    .people tbody tr:hover {background:var(--surface-2)}
    .people details>summary {cursor:pointer;font-size:14px;font-weight:600;line-height:1.6}
    .people details>summary::marker {color:var(--ink-2)}
    .people .prose {white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px}
    .people progress {width:100%;height:8px;border:0;border-radius:99px;background:var(--border);appearance:none;-webkit-appearance:none;display:block;margin:10px 0}
    .people progress::-webkit-progress-bar {background:var(--border);border-radius:99px}
    .people progress::-webkit-progress-value {background:var(--ink);border-radius:99px}
    .people progress::-moz-progress-bar {background:var(--ink);border-radius:99px}
    .people .goal {padding:16px 0;border-top:1px solid var(--border)}
    .people .goal:first-of-type {border-top:0}
    .people .goal form {margin-top:10px;align-items:flex-end;gap:10px}
    .people .goal label {font-size:12px;font-weight:600;color:var(--ink-2)}
    .people .goal input[type=number] {width:92px}
    .people .request-list {display:grid;gap:12px;margin:16px 0}
    .people .request-item {background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin:0;padding:0}
    .people .request-summary {display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;list-style:none}
    .people .request-summary::-webkit-details-marker {display:none}
    .people .request-summary::after {content:'+';font-size:21px;font-weight:400;color:var(--ink-2);flex:none}
    .people .request-item[open]>.request-summary::after {content:'−'}
    .people .request-summary:hover {background:var(--surface-2)}
    .people .request-title {display:block;font-size:14px;font-weight:600;overflow-wrap:anywhere}
    .people .request-meta {display:block;font-size:12px;font-weight:400;color:var(--ink-2);line-height:1.6;margin-top:4px}
    .people .request-detail {padding:20px;border-top:1px solid var(--border)}
    .people .request-filters {display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px}
    .people .filter-search {flex:1 1 220px}
    .people .filter-status {flex:0 1 180px}
    .people .empty-state {text-align:center;padding:36px 24px}
    .people .empty-state h2 {margin:0 0 8px}
    .people .empty-state p {color:var(--ink-2);font-size:14px}
    .people .schedule-toolbar {display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}
    .people .schedule-toolbar form {display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap}
    .people .view-switch {display:flex;padding:4px;gap:4px;background:var(--surface-2);border:1px solid var(--border);border-radius:10px}
    .people .view-switch button {min-height:34px;padding:6px 12px;background:transparent;color:var(--ink-2);border-color:transparent}
    .people .view-switch button[aria-pressed=true] {background:var(--surface);color:var(--ink);box-shadow:var(--shadow);border-color:var(--border)}
    .people .calendar {display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;min-width:750px}
    .people .weekday {font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--ink-2);padding:0 8px 8px}
    .people .day {min-height:118px;border:1px solid var(--border);border-radius:8px;padding:9px;font-size:12px;background:var(--surface);min-width:0}
    .people .day.today {border-color:var(--accent)}
    .people .day-number {display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:26px;border-radius:50%;font-weight:600}
    .people .today .day-number {background:var(--accent);color:#fff}
    .people .agenda-date {display:none}
    .people .shift {background:var(--accent-soft);border-left:3px solid var(--accent);border-radius:5px;padding:7px;margin-top:7px;overflow-wrap:anywhere;line-height:1.6}
    .people .rest {background:var(--surface-2);border-color:var(--ink-3)}
    .people .holiday {background:var(--warn-soft);border-color:var(--warn)}
    .people .shift button {font-size:11px;min-height:30px;padding:4px 7px;margin-top:6px}
    .people .schedule[data-view=agenda] .calendar {display:flex;flex-direction:column;min-width:0;gap:10px}
    .people .schedule[data-view=agenda] .weekday, .people .schedule[data-view=agenda] .calendar-blank {display:none}
    .people .schedule[data-view=agenda] .day {display:grid;grid-template-columns:140px minmax(0,1fr);gap:12px;min-height:0;padding:14px}
    .people .schedule[data-view=agenda] .agenda-date {display:inline;font-size:13px;margin-left:6px}
    .people .schedule[data-view=agenda] .shift {margin-top:0;margin-bottom:6px}
    .people .schedule[data-view=agenda] .day-events {min-width:0}
    .people .table-cards td::before {display:none}
    @media(max-width:700px) {
        .people {padding:20px 16px}
        .people .grid {grid-template-columns:1fr;gap:16px}
        .people .card {padding:18px}
        .people .request-item {padding:0}
        .people .request-summary {padding:16px;gap:10px;flex-wrap:wrap}
        .people .request-summary>div:first-child {flex:1 1 100%}
        .people .request-summary::after {margin-left:auto}
        .people .request-detail {padding:16px}
        .people .schedule[data-view=agenda] .day {grid-template-columns:1fr;gap:8px}
        .people .table-cards thead {position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
        .people .table-cards, .people .table-cards tbody, .people .table-cards tr, .people .table-cards td {display:block;width:100%}
        .people .table-cards tr {border-bottom:1px solid var(--border);padding:10px 0}
        .people .table-cards td {display:flex;justify-content:space-between;gap:16px;padding:7px 0;border:0;text-align:right}
        .people .table-cards td[data-label]::before {display:block;content:attr(data-label);color:var(--ink-2);font-weight:500;text-align:left}
    }
    @media(prefers-reduced-motion:reduce) {.people * {scroll-behavior:auto!important;transition:none!important}}
</style>
