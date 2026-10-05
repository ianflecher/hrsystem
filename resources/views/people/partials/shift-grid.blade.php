{{-- The team's schedule as a grid: people down the side, the cutoff's days
     across. Pick a shift above and click or drag across cells to paint it, or
     type in a cell. Changes save themselves a second after the last one. --}}
@php
    $gridThis = \App\Support\PayPeriod::fromStart(now()->toDateString());
    $gridNext = \App\Support\PayPeriod::fromStart(\Carbon\Carbon::parse($gridThis->end)->addDay()->toDateString());
    $gridPrev = \App\Support\PayPeriod::fromStart(\Carbon\Carbon::parse($gridThis->start)->subDay()->toDateString());
    $gridOptions = [$gridPrev, $gridThis, $gridNext];
    $gridPeriod = collect($gridOptions)->firstWhere('start', request('grid')) ?? $gridNext;
    $gridDays = [];
    for ($d = \Carbon\Carbon::parse($gridPeriod->start); $d->lte(\Carbon\Carbon::parse($gridPeriod->end)); $d->addDay()) {
        $gridDays[] = $d->copy();
    }
    $gridStaff = \Illuminate\Support\Facades\DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
        ->where('e.status', 'active')->where(fn ($q) => \App\Support\PeopleAccess::scopeTeam($q))
        ->where('e.user_id', '!=', auth()->id())
        ->orderByRaw("COALESCE(NULLIF(u.last_name, ''), u.full_name)")->get(['e.*', 'u.full_name']);
    $gridHolidays = \Illuminate\Support\Facades\DB::table('holidays')->whereBetween('date', [$gridPeriod->start, $gridPeriod->end])
        ->pluck('name', 'date')->mapWithKeys(fn ($n, $d) => [substr((string) $d, 0, 10) => $n]);
    $hourLabel = function (?string $t) {
        if (! $t) return '';
        [$h, $m] = array_map('intval', explode(':', substr($t, 0, 5)));
        $suffix = $h < 12 ? 'AM' : 'PM';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;
        return $h12.($m ? ':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '').$suffix;
    };
    // "8-5" when that reads back as the same shift, else "7PM-7AM".
    $cellLabel = function (array $s) use ($hourLabel) {
        if ($s['rest']) return 'RD';
        if (! $s['start'] || ! $s['end']) return '';
        $short = preg_replace('/(AM|PM)/', '', $hourLabel($s['start'])).'-'.preg_replace('/(AM|PM)/', '', $hourLabel($s['end']));
        $back = \App\Services\ScheduleUpload::times($short);
        return $back && $back['start'] === substr($s['start'], 0, 5) && $back['end'] === substr($s['end'], 0, 5)
            ? $short : $hourLabel($s['start']).'-'.$hourLabel($s['end']);
    };
    // Leave on the grid: each day marked paid or unpaid, the way payroll decides it.
    $gridLeave = [];
    foreach (\Illuminate\Support\Facades\DB::table('leaves')->whereIn('employee_id', $gridStaff->pluck('employee_id'))
                 ->whereIn('status', ['approved', 'pending', 'pending_hr'])
                 ->where('start_date', '<=', $gridPeriod->end)->where('end_date', '>=', $gridPeriod->start)->get() as $leave) {
        $paid = $leave->leave_type !== 'unpaid' && ($leave->pay_status === null || $leave->pay_status === 'paid');
        for ($d = \Carbon\Carbon::parse(max(substr((string) $leave->start_date, 0, 10), $gridPeriod->start));
             $d->lte(\Carbon\Carbon::parse(min(substr((string) $leave->end_date, 0, 10), $gridPeriod->end))); $d->addDay()) {
            $gridLeave[$leave->employee_id][$d->toDateString()] = ['paid' => $paid, 'pending' => $leave->status !== 'approved',
                'type' => ucwords(str_replace('_', ' ', (string) $leave->leave_type))];
        }
    }
    // Suspensions (S) set earlier, shown as S on their days.
    $gridSuspended = \Illuminate\Support\Facades\DB::table('hr_attendance')->whereIn('employee_id', $gridStaff->pluck('employee_id'))
        ->whereBetween('date', [$gridPeriod->start, $gridPeriod->end])->where('notes', 'Suspension')->whereNull('time_in')->get()
        ->mapWithKeys(fn ($a) => [$a->employee_id.'|'.substr((string) $a->date, 0, 10) => true]);
    $presets = ['8-5', '10-7', '6-3', '7-4', '9-6', '12-9', '7-7', '7PM-7AM', 'RD', 'S', 'SCHOOL'];
@endphp
<details class="card shift-grid-card" @if(request('grid')) open @endif>
    <summary class="tg-open"><i class="fas fa-pen-to-square"></i> Edit schedule</summary>
    <div class="tg-head">
        <div>
            <h2>Team schedule</h2>
            <p class="muted">Pick a shift, then click or drag across days to set it. You can also type in a cell (<strong>8-5</strong>, <strong>10-7</strong>, <strong>RD</strong>, <strong>S</strong> for suspension - unpaid). Changes save by themselves a second after you stop.</p>
        </div>
        <form method="GET">
            <label class="people-field"><span>Cutoff</span>
                <select name="grid" onchange="this.form.submit()">
                    @foreach($gridOptions as $option)
                        <option value="{{ $option->start }}" @selected($option->start === $gridPeriod->start)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    <div class="tg-toolbar" role="toolbar" aria-label="Shift to paint">
        <span class="tg-label">Paint:</span>
        @foreach($presets as $preset)
            <button type="button" class="tg-chip palette-btn" data-value="{{ $preset }}">{{ $preset }}</button>
        @endforeach
        <input type="text" class="palette-custom" placeholder="Other: 1-10, 11PM-7AM" aria-label="Other shift">
        <button type="button" class="tg-chip tg-stop" hidden>Stop painting</button>
    </div>
    <div class="tg-legend">
        <span class="tg-sw k-morning">Morning</span><span class="tg-sw k-mid">Mid</span><span class="tg-sw k-late">Afternoon</span><span class="tg-sw k-night">Night</span><span class="tg-sw k-rest">Rest day</span><span class="tg-sw k-susp">S - suspension</span><span class="tg-sw k-school">School</span>
        <span class="tg-sep"></span>
        <span class="tg-sw lv-paid">Paid leave</span><span class="tg-sw lv-unpaid">Unpaid leave</span><span class="tg-sw lv-paid lv-pending">Not yet approved</span>
    </div>

    @if($gridStaff->isEmpty())
        <p class="muted">No one on your team yet.</p>
    @else
    <form method="POST" action="{{ $base }}" class="shift-grid-form">@csrf
        <input type="hidden" name="kind" value="schedule-grid">
        <input type="hidden" name="cutoff" value="{{ $gridPeriod->start }}">
        <div class="tg-scroll">
            <table class="team-grid">
                <thead>
                    <tr>
                        <th class="tg-name">Employee</th>
                        <th class="tg-leave">Leave</th>
                        @foreach($gridDays as $day)
                            @php($hol = $gridHolidays[$day->toDateString()] ?? null)
                            <th class="tg-day {{ $day->isSunday() ? 'sun' : '' }} {{ $hol ? 'hol' : '' }} {{ $day->isToday() ? 'today' : '' }}" title="{{ $hol }}">
                                <span>{{ strtoupper($day->format('D')) }}</span>{{ $day->format('j') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($gridStaff as $person)
                        @php($mine = collect($gridLeave[$person->employee_id] ?? []))
                        <tr>
                            <th class="tg-name">
                                <span class="tg-person">{{ $person->full_name }}</span>
                                <button type="button" class="fill-row" title="Set every day in this row to the shift you are painting">Fill row</button>
                            </th>
                            <td class="tg-leave">
                                @if($mine->isEmpty())<span class="muted">—</span>@else
                                    @if($n = $mine->where('paid', true)->count())<span class="tg-sw lv-paid">{{ $n }} paid</span>@endif
                                    @if($n = $mine->where('paid', false)->count())<span class="tg-sw lv-unpaid">{{ $n }} unpaid</span>@endif
                                @endif
                            </td>
                            @foreach($gridDays as $day)
                                @php($lv = $gridLeave[$person->employee_id][$day->toDateString()] ?? null)
                                @if($lv)
                                    <td class="tg-cell"><span class="tg-box {{ $lv['paid'] ? 'lv-paid' : 'lv-unpaid' }} {{ $lv['pending'] ? 'lv-pending' : '' }}"
                                        title="{{ $lv['type'] }} - {{ $lv['paid'] ? 'paid' : 'unpaid' }}{{ $lv['pending'] ? ' (not yet approved)' : '' }}">{{ $lv['paid'] ? 'PAID' : 'UNPAID' }}</span></td>
                                @else
                                    @php($value = isset($gridSuspended[$person->employee_id.'|'.$day->toDateString()]) ? 'S' : $cellLabel(\App\Support\ShiftSchedule::forEmployeeDate($person, $day->toDateString())))
                                    <td class="tg-cell"><input type="text" class="tg-box" name="cells[{{ $person->employee_id }}][{{ $day->toDateString() }}]"
                                        value="{{ $value }}" data-original="{{ $value }}"
                                        aria-label="{{ $person->full_name }}, {{ $day->format('M j') }}" autocomplete="off" spellcheck="false"></td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="tg-actions">
            <button class="grid-save" disabled>Save now</button>
            <button type="button" class="secondary grid-reset">Undo changes</button>
            <span class="muted grid-count"></span>
        </div>
    </form>
    @endif
</details>

<style>
    .shift-grid-card .tg-open { cursor:pointer; font-weight:600; color:#0f172a; list-style:none; display:inline-flex; gap:8px; align-items:center; padding:8px 16px; border:1px solid #cbd5e1; border-radius:9999px; }
    .shift-grid-card .tg-open::-webkit-details-marker { display:none; }
    .shift-grid-card[open] .tg-open { margin-bottom:12px; background:#0f172a; color:#fff; }
    .shift-grid-card .tg-head { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; }
    .shift-grid-card .tg-head p { margin:4px 0 0; max-width:720px; }
    .shift-grid-card .tg-toolbar { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:14px 0 8px; }
    .shift-grid-card .tg-label { font-size:.8rem; font-weight:600; color:#475569; margin-right:2px; }
    .shift-grid-card .tg-chip { padding:5px 12px; font-size:.8rem; font-weight:600; border-radius:9999px; border:1px solid #cbd5e1; background:#fff; color:#0f172a; cursor:pointer; line-height:1.2; }
    .shift-grid-card .tg-chip:hover { border-color:#0f172a; }
    .shift-grid-card .tg-chip.active { background:#0f172a; border-color:#0f172a; color:#fff; }
    .shift-grid-card .tg-stop { border-style:dashed; color:#b91c1c; }
    .shift-grid-card .palette-custom { width:170px; padding:5px 10px; font-size:.8rem; border-radius:9999px; border:1px solid #cbd5e1; }
    .shift-grid-card .tg-legend { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-bottom:12px; }
    .shift-grid-card .tg-sep { width:1px; height:16px; background:#e2e8f0; margin:0 4px; }
    .shift-grid-card .tg-sw { display:inline-block; border-radius:6px; padding:2px 8px; font-size:.72rem; font-weight:600; white-space:nowrap; }
    .shift-grid-card .tg-scroll { overflow:auto; max-height:70vh; border:1px solid #e2e8f0; border-radius:12px; }
    .shift-grid-card table.team-grid { border-collapse:separate; border-spacing:0; font-size:.78rem; width:max-content; min-width:100%; }
    .shift-grid-card table.team-grid th, .shift-grid-card table.team-grid td { border:0; border-bottom:1px solid #f1f5f9; padding:3px; text-align:center; white-space:nowrap; background:#fff; }
    .shift-grid-card table.team-grid thead th { position:sticky; top:0; z-index:2; background:#f8fafc; font-size:.7rem; color:#475569; padding:6px 3px; border-bottom:1px solid #e2e8f0; text-transform:none; }
    .shift-grid-card table.team-grid thead th span { display:block; font-size:.62rem; font-weight:600; letter-spacing:.04em; color:#94a3b8; }
    .shift-grid-card table.team-grid thead th.sun { background:#fef2f2; }
    .shift-grid-card table.team-grid thead th.hol { background:#fef9c3; }
    .shift-grid-card table.team-grid thead th.today { box-shadow:inset 0 -3px 0 #2563eb; }
    .shift-grid-card table.team-grid .tg-name { position:sticky; left:0; z-index:1; text-align:left; padding:4px 10px; min-width:190px; max-width:220px; border-right:1px solid #e2e8f0; }
    .shift-grid-card table.team-grid thead .tg-name { z-index:3; }
    .shift-grid-card .tg-person { display:block; font-size:.78rem; font-weight:600; color:#0f172a; white-space:normal; line-height:1.2; text-transform:none; }
    .shift-grid-card .fill-row { font-size:.68rem; color:#2563eb; background:none; border:0; padding:0; cursor:pointer; visibility:hidden; }
    .shift-grid-card tr:hover .fill-row { visibility:visible; }
    .shift-grid-card table.team-grid tbody tr:hover th, .shift-grid-card table.team-grid tbody tr:hover td { background:#f8fafc; }
    .shift-grid-card table.team-grid .tg-leave { min-width:74px; border-right:1px solid #e2e8f0; }
    .shift-grid-card table.team-grid .tg-leave .tg-sw { display:block; margin:1px 0; }
    .shift-grid-card .tg-box { display:block; box-sizing:border-box; width:56px !important; min-width:0 !important; height:30px !important; margin:0 auto; border:0 !important; border-radius:6px !important;
        text-align:center; font-size:.74rem !important; font-weight:600; padding:0 2px !important; line-height:30px; cursor:pointer; background:#f1f5f9 !important; color:#334155 !important; box-shadow:none !important; }
    .shift-grid-card input.tg-box:focus { outline:2px solid #2563eb; outline-offset:1px; cursor:text; }
    .shift-grid-card span.tg-box { font-size:.62rem !important; cursor:default; }
    .shift-grid-card .tg-box.changed { box-shadow:0 0 0 2px #facc15 !important; }
    .shift-grid-card .tg-box.k-unknown { background:#fee2e2 !important; color:#b91c1c !important; text-decoration:underline wavy; }
    .shift-grid-card .k-morning { background:#dbeafe !important; color:#1e40af !important; }
    .shift-grid-card .k-mid { background:#e0e7ff !important; color:#3730a3 !important; }
    .shift-grid-card .k-late { background:#fef3c7 !important; color:#92400e !important; }
    .shift-grid-card .k-night { background:#334155 !important; color:#f8fafc !important; }
    .shift-grid-card .k-rest { background:#fee2e2 !important; color:#b91c1c !important; }
    .shift-grid-card .k-school { background:#ccfbf1 !important; color:#115e59 !important; }
    .shift-grid-card .k-susp { background:#d1d5db !important; color:#111827 !important; }
    .shift-grid-card .lv-paid { background:#dcfce7 !important; color:#166534 !important; }
    .shift-grid-card .lv-unpaid { background:#ffedd5 !important; color:#9a3412 !important; }
    .shift-grid-card .lv-pending { outline:2px dashed currentColor; outline-offset:-3px; }
    .shift-grid-card.painting input.tg-box { cursor:crosshair; }
    .shift-grid-card .tg-actions { display:flex; gap:8px; align-items:center; margin-top:12px; position:sticky; bottom:0; background:#fff; padding:8px 0; }
</style>
<script>
(function () {
    const card = document.currentScript.previousElementSibling.previousElementSibling;
    if (!card) return;
    const form = card.querySelector('.shift-grid-form');
    if (!form) return;
    let paint = null;
    const save = form.querySelector('.grid-save');
    const count = form.querySelector('.grid-count');
    const stop = card.querySelector('.tg-stop');
    const custom = card.querySelector('.palette-custom');
    const inputs = [...form.querySelectorAll('input.tg-box')];
    const KINDS = ['k-morning', 'k-mid', 'k-late', 'k-night', 'k-rest', 'k-school', 'k-susp', 'k-unknown'];

    // Read like the server: without AM/PM, 7-11 is morning, 12 noon, 1-6 afternoon (but "6-3" is 6AM-3PM).
    function kind(v) {
        v = v.trim().toUpperCase();
        if (!v) return null;
        if (['RD', 'REST', 'REST DAY', 'OFF', 'DAY OFF'].includes(v)) return 'k-rest';
        if (v === 'SCHOOL') return 'k-school';
        if (['S', 'SUSP', 'SUSPENSION', 'SUSPENDED'].includes(v) || v.startsWith('SUSPENDED')) return 'k-susp';
        const m = v.match(/^(\d{1,2})(?::\d{2})?\s*(AM|PM|NN|N|MN)?\s*(?:-|–|TO)\s*(\d{1,2})(?::\d{2})?\s*(AM|PM|NN|N|MN)?$/);
        if (!m) return 'k-unknown';
        let h = +m[1];
        if (m[2] === 'PM' && h < 12) h += 12;
        else if (m[2] === 'AM' && h === 12) h = 0;
        else if (!m[2] && h <= 6 && !(+m[3] < h)) h += 12;
        if (h >= 18 || h < 4) return 'k-night';
        if (h >= 12) return 'k-late';
        if (h >= 10) return 'k-mid';
        return 'k-morning';
    }

    // Saved as they type: a second after the last change, the changed cells go
    // to the server, so a refresh never loses them. A cell that is not a shift
    // yet (half-typed, or a typo) waits until it reads as one.
    let timer = null, saving = false, again = false, quiet = false;
    function refresh() {
        let n = 0;
        inputs.forEach(i => {
            const changed = i.value.trim().toUpperCase() !== i.dataset.original.toUpperCase();
            i.classList.toggle('changed', changed);
            i.classList.remove(...KINDS);
            const k = kind(i.value);
            if (k) i.classList.add(k);
            if (changed) n++;
        });
        save.disabled = n === 0;
        if (n) count.textContent = n + ' day(s) changed - saving…';
        if (n && !quiet) queueSave();
    }
    function queueSave() { clearTimeout(timer); timer = setTimeout(autosave, 1000); }
    async function autosave() {
        if (saving) { again = true; return; }
        const ready = inputs.filter(i => i.classList.contains('changed') && i.value.trim() !== '' && !i.classList.contains('k-unknown'));
        if (!ready.length) { if (inputs.some(i => i.classList.contains('k-unknown'))) count.textContent = 'Not saved: a cell is not a shift (8-5, 10-7, RD, S).'; return; }
        saving = true;
        const body = new FormData();
        body.append('_token', form.querySelector('input[name=_token]').value);
        body.append('kind', 'schedule-grid');
        body.append('cutoff', form.querySelector('input[name=cutoff]').value);
        ready.forEach(i => body.append(i.name, i.value.trim()));
        const sent = ready.map(i => [i, i.value]);
        try {
            const res = await fetch(form.action, { method: 'POST', body, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Could not save.'));
            sent.forEach(([i, v]) => { if (i.value === v) i.dataset.original = v; });
            quiet = true; refresh(); quiet = false;
            count.textContent = 'Saved ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })
                + (data.skipped && data.skipped.length ? ' - not saved: ' + data.skipped.join('; ') : '');
        } catch (e) {
            count.textContent = 'Not saved: ' + e.message + ' Your changes are still here - try again.';
        } finally {
            saving = false;
            if (again) { again = false; queueSave(); }
        }
    }
    function setPaint(value) {
        paint = value || null;
        card.classList.toggle('painting', !!paint);
        stop.hidden = !paint;
        card.querySelectorAll('.palette-btn').forEach(b => b.classList.toggle('active', b.dataset.value === paint));
    }
    card.querySelectorAll('.palette-btn').forEach(b => b.addEventListener('click', () => { custom.value = ''; setPaint(paint === b.dataset.value ? null : b.dataset.value); }));
    custom.addEventListener('input', e => setPaint(e.target.value.trim().toUpperCase()));
    stop.addEventListener('click', () => { custom.value = ''; setPaint(null); });

    let dragging = false;
    document.addEventListener('mouseup', () => { dragging = false; });
    inputs.forEach(i => {
        i.addEventListener('mousedown', e => { if (paint) { e.preventDefault(); dragging = true; i.value = paint; refresh(); } });
        i.addEventListener('mouseenter', () => { if (paint && dragging) { i.value = paint; refresh(); } });
        i.addEventListener('input', refresh);
        i.addEventListener('focus', () => i.select());
    });
    form.querySelectorAll('.fill-row').forEach(b => b.addEventListener('click', () => {
        if (!paint) { alert('Pick a shift to paint first.'); return; }
        b.closest('tr').querySelectorAll('input.tg-box').forEach(i => { i.value = paint; });
        refresh();
    }));
    form.querySelector('.grid-reset').addEventListener('click', () => { inputs.forEach(i => { i.value = i.dataset.original; }); quiet = true; refresh(); quiet = false; count.textContent = ''; });
    // "Save now" saves at once instead of waiting.
    form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); autosave(); });
    window.addEventListener('beforeunload', e => { if (!save.disabled) { autosave(); e.preventDefault(); } });
    quiet = true; refresh(); quiet = false;
})();
</script>
