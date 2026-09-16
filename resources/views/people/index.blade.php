<x-dynamic-component :component="$hr ? 'layouts.humanresource' : 'layouts.employeeland'">
    @php
        $modules = \App\Http\Controllers\PeopleController::MODULES;
        $base = url(($hr ? 'hr' : 'employee').'/people/'.$module);
    @endphp
    <style>
        .people {max-width:1200px;margin:0 auto;padding:28px;color:#17233a}
        .people h1 {font-size:28px;font-weight:750;letter-spacing:-.6px;margin:0}
        .people h2 {font-size:18px;font-weight:700;margin:0 0 12px}
        .people p {line-height:1.6}.people .muted {color:#64748b;font-size:13px}
        .people nav {display:flex;gap:8px;flex-wrap:wrap;margin:24px 0}
        .people nav a {padding:8px 12px;border:1px solid #dce1e9;border-radius:8px;background:white;font-size:13px}
        .people nav a.selected {background:#17233a;color:white;border-color:#17233a}
        .people .card {background:white;border:1px solid #dce1e9;border-radius:12px;padding:22px;margin-bottom:18px;box-shadow:0 2px 5px #17233a05}
        .people .grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
        .people .wide {grid-column:1/-1}.people-field {display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:600}
        .people input:not([type=checkbox]),.people select,.people textarea {width:100%;border:1px solid #cbd5e1;border-radius:7px;padding:9px 11px;background:white;color:#17233a;font:inherit}
        .people textarea {min-height:95px;resize:vertical}.people input:focus,.people select:focus,.people textarea:focus {outline:2px solid #64748b;outline-offset:2px}
        .people button,.people .button {display:inline-block;background:#17233a;color:white;border:0;border-radius:7px;padding:9px 14px;font-size:13px;font-weight:600;cursor:pointer}
        .people button.secondary {background:#eef2f6;color:#17233a}.people button.danger {background:#fff1f2;color:#be123c}
        .people .row {display:flex;align-items:center;gap:12px;flex-wrap:wrap}.people .between {justify-content:space-between}
        .people .badge {display:inline-block;padding:4px 9px;border-radius:20px;background:#eef2f6;font-size:12px;text-transform:capitalize}
        .people .alert {padding:14px;border-radius:8px;background:#ecfdf5;color:#065f46;margin-bottom:18px}
        .people .warning {background:#fffbeb;color:#92400e}.people .error {background:#fff1f2;color:#9f1239}
        .people .divider {margin:16px 0;border-top:1px solid #e2e8f0;padding-top:16px}
        .people .calendar {display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px;min-width:750px}
        .people .day {min-height:110px;border:1px solid #e2e8f0;border-radius:8px;padding:9px;font-size:12px}
        .people .shift {background:#eff6ff;border-left:3px solid #3b82f6;border-radius:4px;padding:5px;margin-top:6px}
        .people .rest {background:#f1f5f9;border-color:#94a3b8}.people .scroll {overflow-x:auto}
        .people table {width:100%;border-collapse:collapse;font-size:13px}.people td,.people th {text-align:left;padding:10px;border-bottom:1px solid #e2e8f0}
        .people details summary {cursor:pointer;font-weight:600}.people .prose {white-space:pre-wrap;overflow-wrap:anywhere}
        /* A bare <progress> renders as the browser's own bright green bar,
           which looked like it belonged to a different program. Slim track,
           navy fill - progress is neither good news nor bad. */
        .people progress {width:100%;height:8px;border:0;border-radius:99px;background:#e2e8f0;appearance:none;-webkit-appearance:none;display:block;margin:8px 0}
        .people progress::-webkit-progress-bar {background:#e2e8f0;border-radius:99px}
        .people progress::-webkit-progress-value {background:#17233a;border-radius:99px}
        .people progress::-moz-progress-bar {background:#17233a;border-radius:99px}
        /* One goal per block, so the rows stop running together. */
        .people .goal {padding:14px 0;border-top:1px solid #eef1f6}
        .people .goal:first-of-type {border-top:0}
        .people .goal form {margin-top:8px;align-items:flex-end;gap:10px}
        .people .goal label {font-size:12px;font-weight:600;color:#64748b}
        .people .goal input[type=number] {width:92px}
        @media(max-width:640px){.people{padding:16px}.people .grid{grid-template-columns:1fr}.people h1{font-size:24px}}
    </style>
    <main class="people">
        <p class="muted">IMPRINT CUSTOMS · {{ $hr ? 'PEOPLE OPERATIONS' : 'EMPLOYEE SERVICES' }}</p>
        <h1>{{ $modules[$module] }}</h1>
        {{-- The module list was a row of tabs here and the same list again in
             the sidebar. The sidebar keeps it: it is in the same place on
             every screen, and it says where you are. --}}
        @if (session('success')) <div class="alert" role="status">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="alert error" role="alert"><strong>Please check your entries.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
        @include('people.'.$module)
        @if ($rows && $module !== 'shifts') <div>{{ $rows->links() }}</div> @endif
    </main>
</x-dynamic-component>
