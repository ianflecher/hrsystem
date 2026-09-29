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
    .people .calendar {display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:10px;min-width:750px}
    .people .weekday {font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#475569;padding:0 10px 2px;font-weight:700}
    .people .day {min-height:118px;border:1px solid #dbe4ef;border-radius:10px;padding:10px;font-size:12px;background:linear-gradient(180deg,#fff 0%,#f8fafc 100%);min-width:0;box-shadow:0 1px 2px rgba(15,23,42,.04)}
    .people .day--work {border-color:#bfdbfe;background:linear-gradient(180deg,#ffffff 0%,#eff6ff 100%)}
    .people .day--rest {border-color:#cbd5e1;background:linear-gradient(180deg,#ffffff 0%,#f1f5f9 100%)}
    .people .day--holiday {border-color:#fde68a;background:linear-gradient(180deg,#fff 0%,#fffbeb 100%)}
    .people .day--assigned {border-color:#c4b5fd;background:linear-gradient(180deg,#fff 0%,#f5f3ff 100%)}
    .people .day.today {border-color:#dc2626;box-shadow:0 0 0 2px rgba(220,38,38,.12),0 8px 20px rgba(15,23,42,.08)}
    .people .day-number {display:inline-flex;align-items:center;justify-content:center;min-width:26px;height:26px;border-radius:999px;font-weight:700;color:#0f172a;background:rgba(255,255,255,.7)}
    .people .today .day-number {background:#dc2626;color:#fff}
    .people .agenda-date {display:none}
    .people .shift {background:#e8f1ff;border-left:4px solid #2563eb;border-radius:7px;padding:8px;margin-top:9px;overflow-wrap:anywhere;line-height:1.6;color:#0f172a}
    .people .shift--work {background:#dbeafe;border-color:#2563eb}
    .people .shift--assigned {background:#ede9fe;border-color:#7c3aed}
    .people .rest {background:#e2e8f0;border-color:#64748b;color:#334155}
    .people .holiday {background:#fef3c7;border-color:#d97706;color:#78350f}
    .people .shift button {font-size:11px;min-height:30px;padding:4px 7px;margin-top:6px}
    .people .shift-picker {max-width:420px;margin-top:14px}
    .people .current-rest-day {display:inline-flex;margin-top:12px;padding:8px 11px;border-radius:999px;background:#f1f5f9;color:#334155;font-size:13px;font-weight:600}
    .people .quick-rest-form {margin-top:8px}
    .people .quick-rest-form button {font-size:11px;min-height:30px;padding:4px 8px}
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
<style>
.people-dialog { border: 0; border-radius: 14px; padding: 24px; max-width: 380px; width: calc(100% - 32px); box-shadow: 0 20px 50px rgba(15, 23, 42, .25); }
.people-dialog::backdrop { background: rgba(15, 23, 42, .45); }
.people-dialog h3 { margin: 0 0 8px; font-size: 1.05rem; }
.people-dialog p { margin: 0 0 18px; color: #475569; line-height: 1.5; }
.people-dialog button { float: right; }
</style>
<style>
.rest-save-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 12px; }
.rest-save-bar [data-rest-pending] { flex: 1 1 220px; }
.people button.is-picked { background: #fee2e2; border-color: #dc2626; color: #991b1b; font-weight: 600; }
</style>
<style>
.people .shift-grid-wrap { overflow-x: auto; margin-top: 12px; border: 1px solid #cbd5e1; border-radius: 10px; }
.people .shift-grid { border-collapse: collapse; width: 100%; min-width: 900px; font-size: 13px; }
.people .shift-grid th, .people .shift-grid td { border: 1px solid #e2e8f0; padding: 7px 4px; text-align: center; white-space: nowrap; }
.people .shift-grid thead th { background: #f8fafc; font-weight: 700; color: #0f172a; }
.people .shift-grid thead th span { display: block; font-size: 10px; color: #64748b; letter-spacing: .04em; }
.people .shift-grid .sg-name { text-align: left; padding-left: 10px; font-weight: 600; position: sticky; left: 0; background: #fff; min-width: 190px; z-index: 1; }
.people .shift-grid thead .sg-name { background: #f8fafc; }
.people .shift-grid .sg-weekend { background: #fdf2f2; }
.people .shift-grid .sg-holiday { background: #fef3c7; }
.people .shift-grid .sg-today { box-shadow: inset 0 -3px 0 #dc2626; }
.people .shift-grid .sg-dept td { background: #eef2ff; text-align: left; font-weight: 700; color: #3730a3; padding-left: 10px; }
.people .sg-rest { background: #dc2626 !important; color: #fff; font-weight: 700; }
.people .sg-leave { background: #fde047 !important; color: #713f12; font-weight: 700; font-size: 11px; }
.people .sg-none { color: #94a3b8; }
.people .sg-legend span { display: inline-block; padding: 1px 6px; border-radius: 4px; margin: 0 4px 0 10px; }
.people .sg-legend span:first-child { margin-left: 0; }
</style>
<style>
.people .leave-agenda { display: none; list-style: none; margin: 12px 0 0; padding: 0; }
.people .leave-agenda li { display: flex; gap: 12px; padding: 12px 0; border-top: 1px solid #e2e8f0; }
.people .leave-agenda li:first-child { border-top: 0; }
.people .leave-agenda li.today .agenda-date strong { background: #dc2626; color: #fff; }
.people .leave-agenda .agenda-date { flex: 0 0 44px; text-align: center; }
.people .leave-agenda .agenda-date strong { display: grid; place-items: center; width: 36px; height: 36px; margin: 0 auto; border-radius: 999px; background: #f1f5f9; font-size: 15px; }
.people .leave-agenda .agenda-date span { display: block; margin-top: 3px; font-size: 11px; color: #64748b; text-transform: uppercase; }
.people .leave-agenda .agenda-items { flex: 1; min-width: 0; }
.people .leave-agenda .agenda-items .shift:first-child { margin-top: 0; }
.people .leave-agenda .agenda-empty { display: block; color: #64748b; text-align: center; padding: 20px 8px; }
@media (max-width: 767px) {
    .people .leave-agenda { display: block; }
    .people .leave-agenda + .calendar { display: none; }
}
</style>
<style>
/* Department labels stay in view while the dates scroll sideways. */
.people .shift-grid .sg-dept td { padding: 0; }
.people .shift-grid .sg-dept span { position: sticky; left: 0; display: inline-block; padding: 6px 10px; }
@media (max-width: 767px) {
    .people .shift-grid { min-width: 0; width: max-content; font-size: 12px; }
    .people .shift-grid .sg-name { min-width: 0; width: 112px; max-width: 112px; white-space: normal; line-height: 1.25; font-size: 12px; padding: 6px 8px; box-shadow: 1px 0 0 #e2e8f0; }
    .people .shift-grid th, .people .shift-grid td { padding: 6px 3px; }
    .people .shift-grid td:not(.sg-name) { min-width: 34px; }
    .people .sg-leave { font-size: 9px; }
}
</style>
<style>
/* Sticky cells in a border-collapse table are redrawn out of line with their
   rows on mobile browsers as the page scrolls. Separate borders keep the
   pinned name column attached to its row. */
.people .shift-grid { border-collapse: separate; border-spacing: 0; }
.people .shift-grid th, .people .shift-grid td { border-width: 0 1px 1px 0; }
.people .shift-grid .sg-name { background-clip: padding-box; }
</style>
<style>
/* Phones: nothing pinned - a pinned column drifts off its row on mobile
   browsers. Each name gets its own line above that person's shifts. */
.people .shift-grid .sg-name-row { display: none; }
@media (max-width: 767px) {
    .people .shift-grid .sg-name { display: none; }
    .people .shift-grid .sg-name-row { display: table-row; }
    .people .shift-grid .sg-name-row td { text-align: left; font-weight: 600; background: #f8fafc; padding: 6px 8px 4px; border-bottom: 0; }
    .people .shift-grid .sg-dept span { position: static; }
    .people .shift-grid { width: 100%; }
}
</style>
<style>
.people .schedule-upload { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end; margin-top: 12px; }
.people .schedule-upload-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
.people .schedule-upload-actions form { margin: 0; }
.people .secondary-link { font-size: 14px; color: #b91c1c; text-decoration: underline; }
.people .schedule-preview { margin-top: 18px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
.people .schedule-preview h3 { margin: 0 0 4px; font-size: 16px; }
.people .schedule-preview .alert { margin-top: 12px; }
.people .schedule-preview .alert ul { margin: 6px 0 0 18px; }
</style>
<style>
.people .sg-susp { background: #475569 !important; color: #fff; font-weight: 700; }
.people .sg-ob { background: #cffafe !important; color: #155e75; font-weight: 700; font-size: 11px; }
</style>
<style>
.people .leave-tag { display: inline-block; margin-right: 4px; padding: 0 6px; border-radius: 4px; background: #fde047; color: #713f12; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
</style>
