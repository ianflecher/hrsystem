<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
use App\Support\PayPeriod;
use App\Support\WorkWeek;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PeopleController extends Controller
{
    public const MODULES = [
        'documents' => 'Document vault', 'overtime' => 'Overtime', 'shifts' => 'Shift calendar',
        'announcements' => 'Announcements', 'checklists' => 'Onboarding & offboarding',
        'reviews' => 'Performance reviews', 'loans' => 'Government loans', 'reports' => 'Reports',
    ];

    /** Employment statuses that mean the person is on their way out. */
    public const LEAVING = ['terminated', 'inactive'];

    /** How long after a hire date somebody still counts as a new starter. */
    public const ONBOARDING_WINDOW_DAYS = 30;

    private function context(Request $request): array
    {
        $hr = $request->is('hr/*');
        if ($hr) {
            PeopleAccess::hr();
        } else {
            PeopleAccess::employeeId();
        }
        return [$hr, $hr ? null : PeopleAccess::employeeId()];
    }

    private function isTeamPortal(string $module): bool
    {
        return ! PeopleAccess::isHr()
            && in_array(auth()->user()->role, ['supervisor', 'leader'], true)
            && in_array($module, ['overtime', 'shifts'], true);
    }

    /**
     * A cutoff carries at most three rest days. Marking a fourth
     * moves an existing rest day back to work rather
     * than stacking more days off onto the payroll.
     */
    private function makeRoomForRestDay(int $employeeId, Carbon $day, string $status, bool $hr): void
    {
        $start = $day->day <= 15 ? $day->copy()->startOfMonth() : $day->copy()->day(16);
        $end = $day->day <= 15 ? $day->copy()->day(15) : $day->copy()->endOfMonth();
        $limit = 3;

        $employee = DB::table('employees')->where('employee_id', $employeeId)->first();
        $assigned = DB::table('shift_assignments')->where('employee_id', $employeeId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->get()->keyBy(fn ($row) => substr((string) $row->work_date, 0, 10));

        $rest = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            if ($date === $day->toDateString()) continue;
            $shift = $assigned->get($date);
            if ($shift ? $shift->rest_day : WorkWeek::restsOn($employee->rest_days, $d)) {
                $rest[$date] = ! $shift;
            }
        }

        if (count($rest) < $limit) return;

        // A default rest day moves first (same week, then nearest); if every
        // rest day was marked by hand, the nearest of those moves instead.
        $movable = collect($rest)->keys()
            ->sortBy(fn ($date) => [$rest[$date] ? 0 : 1, Carbon::parse($date)->isoWeek() === $day->isoWeek() ? 0 : 1, abs(Carbon::parse($date)->diffInDays($day))])
            ->values();

        DB::table('shift_assignments')->updateOrInsert(
            ['employee_id' => $employeeId, 'work_date' => $movable->first()],
            ['starts_at' => $employee->shift_start ? substr($employee->shift_start, 0, 5) : null, 'ends_at' => $employee->shift_end ? substr($employee->shift_end, 0, 5) : null,
                'rest_day' => false, 'label' => 'Rest day moved to '.$day->format('M j'), 'status' => $status,
                'created_by' => auth()->id(), 'approved_by' => $hr ? auth()->id() : null, 'approved_at' => $hr ? now() : null,
                'created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * A blank schedule for a cutoff, as a spreadsheet: every active person with
     * their employee number, one column per day. Filled in and uploaded back.
     */
    private function scheduleTemplate(Request $request)
    {
        $period = PayPeriod::fromStart($request->query('cutoff') ?: now()->toDateString());
        $days = [];
        for ($d = Carbon::parse($period->start); $d->lte(Carbon::parse($period->end)); $d->addDay()) {
            $days[] = $d->copy();
        }
        $people = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->leftJoin('departments as dp', 'dp.department_id', '=', 'e.department_id')
            ->where('e.status', 'active')
            ->when($request->query('company'), fn ($q, $c) => $q->where('e.company', $c))
            ->orderBy('dp.department_name')->orderBy('u.full_name')
            ->get(['e.employee_no', 'u.full_name', 'dp.department_name']);

        $name = 'schedule-'.$period->start.'.csv';

        return response()->streamDownload(function () use ($days, $people) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['Employee No', 'Name', 'Department'], array_map(fn ($d) => $d->format('D'), $days)));
            fputcsv($out, array_merge(['', '', ''], array_map(fn ($d) => $d->toDateString(), $days)));
            foreach ($people as $p) {
                fputcsv($out, array_merge([$p->employee_no, $p->full_name, $p->department_name ?? ''], array_fill(0, count($days), '')));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function index(Request $request, string $module)
    {
        [$hr, $employeeId] = $this->context($request);
        abort_unless(isset(self::MODULES[$module]), 404);
        if ($module === 'shifts' && $hr && $request->query('download') === 'schedule-template') {
            return $this->scheduleTemplate($request);
        }
        $requestStatuses = match ($module) {
            'overtime' => ['pending', 'approved', 'rejected', 'cancelled'],
            'loans' => ['pending', 'approved', 'active', 'repaid', 'stopped', 'rejected', 'cancelled'],
            default => [],
        };
        $request->validate([
            'month' => 'nullable|date_format:Y-m',
            'search' => 'nullable|string|max:100',
            'status' => $requestStatuses ? ['nullable', 'string', Rule::in($requestStatuses)] : 'nullable|string|max:30',
        ]);
        $month = Carbon::parse(($request->input('month') ?: now()->format('Y-m')).'-01');
        $teamPortal = $this->isTeamPortal($module);
        $managedDepartmentIds = PeopleAccess::managedDepartmentIds();
        $employees = ($hr || $teamPortal) ? DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->when(! $hr, fn ($q) => $q->whereIn('e.department_id', $managedDepartmentIds))
            ->select('e.employee_id', 'u.full_name')->orderBy('u.full_name')->get() : collect();
        $departments = $hr ? DB::table('departments')->orderBy('department_name')->get() : collect();
        $query = null;
        $extra = ['requestStatuses' => $requestStatuses];
        $tables = ['documents' => 'employee_documents', 'overtime' => 'overtime_requests',
            'checklists' => 'employee_checklists',
            'reviews' => 'performance_reviews', 'loans' => 'employee_loans'];
        if (isset($tables[$module])) {
            $query = DB::table($tables[$module].' as r')
                ->join('employees as e', 'e.employee_id', '=', 'r.employee_id')
                ->join('users as u', 'u.user_id', '=', 'e.user_id')->select('r.*', 'u.full_name', 'u.user_id as request_user_id');
            if (! $hr) {
                $query->where(function ($q) use ($module, $employeeId) {
                    $q->where('r.employee_id', $employeeId);
                    if ($module === 'overtime' && in_array(auth()->user()->role, ['supervisor', 'leader'], true)) {
                        $q->orWhereIn('e.department_id', PeopleAccess::managedDepartmentIds());
                    }
                });
            }
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request, $requestStatuses) {
                    $term = '%'.$request->input('search').'%';
                    $q->where('u.full_name', 'like', $term);
                    if ($requestStatuses) {
                        $q->orWhere('r.reason', 'like', $term);
                    }
                });
            }
            if ($requestStatuses && $request->filled('status')) {
                $query->where('r.status', $request->input('status'));
            }
        }
        // A draft belongs to HR until it is graded: the employee gets the
        // evaluation, not the work in progress.
        if ($module === 'reviews' && ! $hr) {
            $query->where('r.status', 'finalized');
        }

        if ($module === 'documents') {
            $extra['expiring'] = (clone $query)->whereNotNull('r.expires_on')->where('r.expires_on', '<=', today()->addDays(30)->toDateString())->count();
            if ($request->boolean('expiring')) {
                $query->whereNotNull('r.expires_on')->where('r.expires_on', '<=', today()->addDays(30)->toDateString());
            }
        }
        if ($module === 'shifts') {
            $period = PayPeriod::recent(1)[0];
            $extra['period'] = $period;
            $extra['holidays'] = DB::table('holidays')
                ->whereBetween('date', [$period->start, $period->end])
                ->orderBy('date')->get()->keyBy('date');
            $extra['staff'] = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->leftJoin('departments as dp', 'dp.department_id', '=', 'e.department_id')
                ->when($hr, fn ($q) => $q->where('e.status', 'active'))
                ->when(! $hr && $teamPortal, fn ($q) => $q->where('e.status', 'active')->whereIn('e.department_id', $managedDepartmentIds))
                ->when(! $hr && ! $teamPortal, fn ($q) => $q->where('e.employee_id', $employeeId))
                ->select('e.employee_id', 'e.department_id', 'dp.department_name', 'e.shift_start', 'e.shift_end', 'e.rest_days', 'u.full_name')
                ->orderBy('u.full_name')->get();
            $extra['assignments'] = DB::table('shift_assignments as s')
                ->join('employees as e', 'e.employee_id', '=', 's.employee_id')
                ->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->whereBetween('s.work_date', [$period->start, $period->end])
                ->when(! $hr && $teamPortal, fn ($q) => $q->whereIn('e.department_id', $managedDepartmentIds))
                ->when(! $hr && ! $teamPortal, fn ($q) => $q->where('s.employee_id', $employeeId))
                ->select('s.*', 'u.full_name')
                ->orderBy('s.work_date')->orderBy('u.full_name')->get()
                ->groupBy(fn ($row) => substr((string) $row->work_date, 0, 10));
            // The calendar shows who is away: every leave touching the
            // cutoff, spread onto each of its days.
            $staffIds = $extra['staff']->pluck('employee_id');
            // Days the schedule grid marks from attendance: suspensions and
            // official business, keyed employee|date.
            $extra['dayMarks'] = DB::table('hr_attendance')
                ->whereIn('employee_id', $extra['staff']->pluck('employee_id'))
                ->whereBetween('date', [$period->start, $period->end])
                ->where(fn ($q) => $q->where('status', 'official_business')->orWhere('notes', 'Suspension'))
                ->get(['employee_id', 'date', 'status', 'notes'])
                ->keyBy(fn ($r) => $r->employee_id.'|'.substr((string) $r->date, 0, 10));
            $extra['leaves'] = collect();
            foreach (DB::table('leaves as l')->join('employees as e', 'e.employee_id', '=', 'l.employee_id')
                ->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->whereIn('l.employee_id', $staffIds)
                ->whereIn('l.status', ['approved', 'pending', 'pending_hr', 'pending_manager'])
                ->where('l.start_date', '<=', $period->end)->where('l.end_date', '>=', $period->start)
                ->select('l.*', 'u.full_name')->orderBy('u.full_name')->get() as $leave) {
                $from = Carbon::parse($leave->start_date)->max(Carbon::parse($period->start));
                $to = Carbon::parse($leave->end_date)->min(Carbon::parse($period->end));
                for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                    $extra['leaves']->put($d->toDateString(), $extra['leaves']->get($d->toDateString(), collect())->push($leave));
                }
            }
        }
        // Onboarding and offboarding is a roster, not a queue: HR is looking at
        // people, so everybody is listed - including whoever has no checklist
        // yet, who are precisely the ones worth noticing.
        $order = ['r.id', 'desc'];
        if ($module === 'checklists' && $hr) {
            $query = DB::table('employees as r')->join('users as u', 'u.user_id', '=', 'r.user_id')
                ->select('r.employee_id', 'r.job_title', 'r.status', 'r.hire_date', 'u.full_name');
            $order = ['u.full_name', 'asc'];

            if ($request->filled('search')) {
                $query->where('u.full_name', 'like', '%'.$request->input('search').'%');
            } elseif (! $request->boolean('all')) {
                // Only the people this screen is actually about: someone still
                // being onboarded or cleared, a recent hire nobody has started,
                // and anyone who has left without being cleared. Settled staff
                // are not a to-do list, so they are behind "Show everyone".
                $query->where(function ($q) {
                    $q->whereExists(fn ($c) => $c->from('employee_checklists as c')
                        ->whereColumn('c.employee_id', 'r.employee_id')->whereNull('c.completed_at'))
                      ->orWhere(fn ($q2) => $q2->whereIn('r.status', self::LEAVING)
                          ->whereNotExists(fn ($c) => $c->from('employee_checklists as c')
                              ->whereColumn('c.employee_id', 'r.employee_id')->where('c.type', 'offboarding')))
                      ->orWhere(fn ($q2) => $q2->whereNotIn('r.status', self::LEAVING)
                          ->where('r.hire_date', '>=', today()->subDays(self::ONBOARDING_WINDOW_DAYS)->toDateString())
                          ->whereNotExists(fn ($c) => $c->from('employee_checklists as c')
                              ->whereColumn('c.employee_id', 'r.employee_id')->where('c.type', 'onboarding')));
                });
            }
        }
        $rows = $query ? $query->orderBy($order[0], $order[1])->paginate(20)->withQueryString() : null;
        if ($module === 'checklists') {
            $lists = $hr
                ? DB::table('employee_checklists')->whereIn('employee_id', $rows->pluck('employee_id'))->orderByDesc('id')->get()
                : collect($rows->items());
            $extra['checklists'] = $lists->groupBy('employee_id');
            $extra['items'] = DB::table('checklist_items')->whereIn('checklist_id', $lists->pluck('id'))->orderBy('id')->get()->groupBy('checklist_id');
        }
        if ($module === 'reviews') {
            $summary = new \App\Services\ReviewSummary;
            $extra['attendance'] = [];
            $extra['notices'] = [];

            // Notices an employee still owes an answer on are shown whatever
            // period they belong to: a deadline is not a filing question.
            $extra['awaiting'] = $hr
                ? collect()
                : $summary->awaiting((int) $employeeId);

            // A Notice of Termination is not something to answer, so it is
            // handed to the view on its own rather than sitting in a list.
            $extra['termination'] = $hr ? null : DB::table('employee_notices as n')
                ->leftJoin('users as u', 'u.user_id', '=', 'n.issued_by')
                ->where('n.employee_id', $employeeId)->where('n.kind', 'not')
                ->orderByDesc('n.id')->select('n.*', 'u.full_name as issued_by_name')->first();

            $extra['terminations'] = $summary->terminationsFor(
                collect($extra['notices'])->flatten(1));

            foreach ($rows as $review) {
                $subject = DB::table('employees')->where('employee_id', $review->employee_id)->first();

                if ($subject) {
                    $extra['attendance'][$review->id] = $summary->attendance($subject, $review->period_start, $review->period_end);
                }

                $extra['notices'][$review->id] = $summary->notices(
                    (int) $review->employee_id, $review->period_start, $review->period_end);
            }
        }
        if ($module === 'loans') {
            $extra['installments'] = DB::table('loan_installments as i')->join('hr_payroll as p', 'p.payroll_id', '=', 'i.payroll_id')
                ->whereIn('i.loan_id', $rows->pluck('id'))->select('i.*', 'p.period_start', 'p.status')->get()->groupBy('loan_id');
        }
        if ($module === 'announcements') {
            $query = DB::table('announcements as a')
                ->leftJoin('departments as d', 'd.department_id', '=', 'a.department_id')
                ->leftJoin('announcement_reads as ar', fn ($join) => $join->on('ar.announcement_id', '=', 'a.id')->where('ar.user_id', auth()->id()))
                ->select('a.*', 'd.department_name', 'ar.acknowledged_at');
            if (! $hr) {
                $departmentId = DB::table('employees')->where('employee_id', $employeeId)->value('department_id');
                $query->where('a.archived', false)
                    ->where(fn ($q) => $q->whereNull('a.published_at')->orWhere('a.published_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('a.expires_at')->orWhere('a.expires_at', '>=', now()))
                    ->where(fn ($q) => $q->whereNull('a.department_id')->orWhere('a.department_id', $departmentId));
            }
            $rows = $query->orderByDesc('a.published_at')->orderByDesc('a.id')->paginate(20)->withQueryString();
            $extra['readCounts'] = $hr
                ? DB::table('announcement_reads')->whereIn('announcement_id', $rows->pluck('id'))->select('announcement_id', DB::raw('count(*) as total'))->groupBy('announcement_id')->pluck('total', 'announcement_id')
                : collect();
        }
        if ($module === 'reports') {
            $rows = null;
        }
        return view('people.index', compact('hr', 'employeeId', 'module', 'employees', 'departments', 'month', 'rows', 'extra'));
    }

    public function store(Request $request, string $module)
    {
        [$hr, $employeeId] = $this->context($request);
        abort_unless(isset(self::MODULES[$module]), 404);
        // Documents, overtime and loans come from the person they are about.
        if (! in_array($module, ['overtime', 'loans', 'documents'], true) && ! ($module === 'shifts' && $this->isTeamPortal($module))) PeopleAccess::hr();
        // Overtime is requested by the employee. Government loans are recorded
        // by HR from SSS/Pag-IBIG notices and only displayed to employees.
        abort_if($hr && $module === 'overtime', 403, 'This is requested by the employee.');
        abort_if(! $hr && $module === 'loans', 403, 'Government loans are recorded by HR.');
        if (in_array($module, ['documents', 'checklists', 'reviews', 'loans'], true) && $hr) {
            $request->validate(['employee_id' => 'required|integer|exists:employees,employee_id']);
            $employeeId = (int) $request->input('employee_id');
        }
        $base = ['employee_id' => $employeeId, 'created_at' => now(), 'updated_at' => now()];
        switch ($module) {
            case 'documents':
                $data = $request->validate(['title' => 'required|string|max:150', 'category' => ['required', Rule::in(['contract', 'id', 'certificate', 'other'])],
                    'expires_on' => 'nullable|date_format:Y-m-d', 'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240']);
                $path = $request->file('document')->store('employee-documents', 'local');
                abort_unless($path, 500, 'The document could not be stored.');
                try {
                    DB::table('employee_documents')->insert($base + [
                        'title' => $data['title'], 'category' => $data['category'], 'expires_on' => $data['expires_on'] ?? null,
                        'path' => $path, 'original_name' => basename($request->file('document')->getClientOriginalName()), 'uploaded_by' => auth()->id(),
                    ]);
                } catch (\Throwable $e) {
                    Storage::disk('local')->delete($path);
                    throw $e;
                }
                break;
            case 'overtime':
                $data = $request->validate(['starts_at' => 'required|date_format:Y-m-d\TH:i', 'ends_at' => 'required|date_format:Y-m-d\TH:i|after:starts_at', 'reason' => 'required|string|min:5|max:3000']);
                $start = Carbon::parse($data['starts_at']);
                $end = Carbon::parse($data['ends_at']);
                $minutes = (int) $start->diffInMinutes($end);
                if ($minutes < 60 || $minutes % 60 !== 0) {
                    throw ValidationException::withMessages(['ends_at' => 'Overtime must be requested in whole-hour blocks.']);
                }
                if ($minutes > 960) throw ValidationException::withMessages(['ends_at' => 'Submit at most 16 hours per request.']);
                DB::transaction(function () use ($employeeId, $start, $end, $base, $data, $minutes) {
                    DB::table('employees')->where('employee_id', $employeeId)->lockForUpdate()->first();
                    if (DB::table('overtime_requests')->where('employee_id', $employeeId)->whereIn('status', ['pending', 'pending_hr', 'approved'])
                        ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                        throw ValidationException::withMessages(['starts_at' => 'This request overlaps existing overtime.']);
                    }
                    DB::table('overtime_requests')->insert($base + ['starts_at' => $start, 'ends_at' => $end, 'minutes' => $minutes, 'reason' => $data['reason']]);
                });
                break;
            case 'shifts':
                $teamPortal = $this->isTeamPortal($module);

                // A cutoff's schedule from HR's spreadsheet: read and shown
                // first, written only once confirmed.
                if (in_array($request->input('kind'), ['schedule-upload', 'schedule-confirm', 'schedule-cancel'], true)) {
                    // The team's supervisor or leader uploads it, for their own team only.
                    abort_unless($teamPortal, 403);
                    if ($request->input('kind') === 'schedule-cancel') {
                        session()->forget('schedule_upload');

                        return back();
                    }
                    if ($request->input('kind') === 'schedule-confirm') {
                        $plan = session('schedule_upload');
                        abort_unless(is_array($plan), 422, 'Upload the schedule again - the preview has expired.');
                        $done = (new \App\Services\ScheduleUpload)->apply($plan, auth()->id());
                        session()->forget('schedule_upload');

                        return back()->with('success', "Schedule for {$plan['period']['label']} saved: {$done['shifts']} shift day(s), {$done['rest']} rest day(s), "
                            ."{$done['suspensions']} suspension day(s), {$done['leave']} leave request(s), {$done['ob']} official business day(s).");
                    }
                    $request->validate([
                        'schedule_file' => 'required|file|max:10240|mimes:xlsx,csv,txt',
                        'cutoff' => 'required|date_format:Y-m-d',
                    ], ['schedule_file.mimes' => 'Upload the schedule as .xlsx or .csv.']);
                    $file = $request->file('schedule_file');
                    try {
                        $rows = \App\Support\SpreadsheetReader::rows($file->getRealPath(), $file->getClientOriginalName());
                        $plan = (new \App\Services\ScheduleUpload)->read($rows, PayPeriod::fromStart($request->input('cutoff')));
                    } catch (\RuntimeException $e) {
                        throw ValidationException::withMessages(['schedule_file' => $e->getMessage()]);
                    }

                    // A supervisor schedules their own team: hours and rest days.
                    // Leave, suspensions and official business change pay, and
                    // stay HR's to record.
                    if (! $hr) {
                        $plan['hr_only'] = [];
                        $team = [];
                        foreach ($plan['people'] as $person) {
                            if (! PeopleAccess::managesEmployee((int) $person['employee_id'])) {
                                $plan['unmatched'][] = $person['name'].' (not on your team)';
                                continue;
                            }
                            foreach ($person['days'] as $date => $day) {
                                if (! in_array($day['type'], ['shift', 'rest', 'school'], true)) {
                                    $plan['hr_only'][] = $person['name'].', '.Carbon::parse($date)->format('M j').': '
                                        .['leave_paid' => 'leave with pay', 'leave_unpaid' => 'leave', 'suspension' => 'suspension', 'ob' => 'official business'][$day['type']];
                                    unset($person['days'][$date]);
                                }
                            }
                            $team[] = $person;
                        }
                        $plan['people'] = $team;
                    }
                    session(['schedule_upload' => $plan]);

                    return back();
                }
                if ($request->input('kind') === 'holiday' || $request->filled(['date', 'name', 'type'])) {
                    PeopleAccess::hr();
                    $data = $request->validate([
                        'date' => 'required|date_format:Y-m-d',
                        'name' => 'required|string|max:120',
                        'type' => ['required', Rule::in(['regular', 'special_non_working', 'special_working', 'special'])],
                    ]);
                    DB::table('holidays')->updateOrInsert(['date' => $data['date']],
                        ['name' => $data['name'], 'type' => in_array($data['type'], ['special_non_working', 'special_working', 'special'], true) ? 'special' : 'regular', 'classification' => $data['type'] === 'special' ? 'special_non_working' : $data['type'], 'created_at' => now(), 'updated_at' => now()]);
                    break;
                }

                // Rest days picked on the calendar arrive together, saved in
                // one go when HR presses Save - never one per click.
                if ($request->has('rest_dates')) {
                    $data = $request->validate([
                        'employee_id' => 'required|integer|exists:employees,employee_id',
                        'rest_dates' => 'required|array|min:1|max:31',
                        'rest_dates.*' => 'date_format:Y-m-d',
                    ], ['rest_dates.required' => 'Click Mark rest on at least one day first.']);
                    if ($teamPortal) {
                        PeopleAccess::managerForEmployee((int) $data['employee_id']);
                    }
                    $status = $hr ? 'approved' : 'pending_hr';
                    foreach (collect($data['rest_dates'])->unique()->sort() as $date) {
                        $day = Carbon::parse($date);
                        $this->makeRoomForRestDay((int) $data['employee_id'], $day->copy(), $status, $hr);
                        DB::table('shift_assignments')->updateOrInsert(
                            ['employee_id' => (int) $data['employee_id'], 'work_date' => $date],
                            ['starts_at' => null, 'ends_at' => null, 'rest_day' => true, 'label' => 'Rest day',
                                'status' => $status, 'created_by' => auth()->id(),
                                'approved_by' => $hr ? auth()->id() : null, 'approved_at' => $hr ? now() : null,
                                'created_at' => now(), 'updated_at' => now()]
                        );
                    }

                    return back()->with('success', 'Rest days saved.')->with('shift_employee', (int) $data['employee_id']);
                }

                $data = $request->validate([
                    'employee_id' => 'required|integer|exists:employees,employee_id',
                    'from' => 'required|date_format:Y-m-d',
                    'to' => 'required|date_format:Y-m-d|after_or_equal:from',
                    'starts_at' => 'nullable|required_unless:rest_day,1|date_format:H:i',
                    'ends_at' => 'nullable|required_unless:rest_day,1|date_format:H:i',
                    'rest_day' => 'nullable|boolean',
                    'label' => 'nullable|string|max:80',
                ]);
                if ($teamPortal) {
                    PeopleAccess::managerForEmployee((int) $data['employee_id']);
                }
                $from = Carbon::parse($data['from']);
                $to = Carbon::parse($data['to']);
                if ($from->diffInDays($to) > 93) throw ValidationException::withMessages(['to' => 'Schedule at most 93 days at a time.']);
                $status = $hr ? 'approved' : 'pending_hr';
                for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                    if ($request->boolean('rest_day')) {
                        $this->makeRoomForRestDay((int) $data['employee_id'], $day->copy(), $status, $hr);
                    }
                    DB::table('shift_assignments')->updateOrInsert(
                        ['employee_id' => (int) $data['employee_id'], 'work_date' => $day->toDateString()],
                        ['starts_at' => $request->boolean('rest_day') ? null : $data['starts_at'],
                            'ends_at' => $request->boolean('rest_day') ? null : $data['ends_at'],
                            'rest_day' => $request->boolean('rest_day'),
                            'label' => $data['label'] ?: ($request->boolean('rest_day') ? 'Rest day' : 'Assigned shift'),
                            'status' => $status,
                            'created_by' => auth()->id(),
                            'approved_by' => $hr ? auth()->id() : null,
                            'approved_at' => $hr ? now() : null,
                            'created_at' => now(), 'updated_at' => now()]
                    );
                }
                break;
            case 'checklists':
                $data = $request->validate(['type' => ['required', Rule::in(['onboarding', 'offboarding'])], 'due_on' => 'required|date_format:Y-m-d']);
                DB::transaction(function () use ($data, $base) {
                    $id = DB::table('employee_checklists')->insertGetId($base + $data);
                    $tasks = $data['type'] === 'onboarding'
                        ? ['Submit identification and signed contract' => 'employee', 'Complete orientation' => 'employee', 'Issue equipment' => 'hr', 'Set up work access' => 'hr']
                        : ['Return company equipment' => 'employee', 'Complete work handover' => 'employee', 'Revoke work access' => 'hr', 'Confirm final pay and clearance' => 'hr'];
                    foreach ($tasks as $title => $owner) DB::table('checklist_items')->insert(['checklist_id' => $id, 'title' => $title, 'owner' => $owner, 'created_at' => now(), 'updated_at' => now()]);
                });
                break;
            case 'reviews':
                $data = $request->validate(['period_start' => 'required|date_format:Y-m-d', 'period_end' => 'required|date_format:Y-m-d|after_or_equal:period_start', 'due_on' => 'required|date_format:Y-m-d']);
                if (DB::table('performance_reviews')->where('employee_id', $employeeId)->where('period_start', $data['period_start'])->where('period_end', $data['period_end'])->exists()) {
                    throw ValidationException::withMessages(['period_start' => 'A review already exists for this period.']);
                }
                DB::table('performance_reviews')->insert($base + $data);
                break;
            case 'loans':
                PeopleAccess::hr();
                // One figure, as the SSS / Pag-IBIG notice gives it: the monthly
                // amortization. No balance - it is deducted every cutoff, half
                // each, until HR stops it. A stored amount of 0 marks that.
                $data = $request->validate([
                    'type' => ['required', Rule::in(['sss', 'pagibig', 'government'])],
                    'monthly' => 'required|numeric|min:1|max:1000000',
                    'starts_on' => 'required|date_format:Y-m-d',
                    'reason' => 'required|string|min:5|max:3000',
                ], [], ['monthly' => 'monthly amortization']);
                $data['installment'] = round((float) $data['monthly'], 2);
                $data['amount'] = 0;
                unset($data['monthly']);
                DB::table('employee_loans')->insert($base + $data + [
                    'status' => 'active',
                    'reviewed_by' => auth()->id(),
                    'disbursed_at' => now(),
                ]);
                break;
            case 'announcements':
                $data = $request->validate([
                    'title' => 'required|string|max:160',
                    'body' => 'required|string|min:5|max:10000',
                    'department_id' => 'nullable|integer|exists:departments,department_id',
                    'published_at' => 'nullable|date_format:Y-m-d\TH:i',
                    'expires_at' => 'nullable|date_format:Y-m-d\TH:i|after:published_at',
                ]);
                DB::table('announcements')->insert([
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'department_id' => $data['department_id'] ?? null,
                    'published_at' => isset($data['published_at']) ? Carbon::parse($data['published_at']) : now(),
                    'expires_at' => isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                break;
        }
        return back()->with('success', 'Saved successfully.');
    }

    public function action(Request $request, string $module, int $id)
    {
        $this->context($request);
        $action = $request->input('action');
        $tables = ['overtime' => 'overtime_requests', 'loans' => 'employee_loans',
            'checklists' => 'employee_checklists', 'reviews' => 'performance_reviews', 'shifts' => 'holidays', 'documents' => 'employee_documents'];
        if ($module === 'announcements') {
            $announcement = DB::table('announcements')->where('id', $id)->first();
            abort_unless($announcement, 404);
            if ($action === 'acknowledge') {
                [$hr, $employeeId] = $this->context($request);
                abort_if($hr, 403);
                $departmentId = DB::table('employees')->where('employee_id', $employeeId)->value('department_id');
                abort_if($announcement->archived || ($announcement->department_id && (int) $announcement->department_id !== (int) $departmentId), 403);
                DB::table('announcement_reads')->updateOrInsert(['announcement_id' => $id, 'user_id' => auth()->id()],
                    ['acknowledged_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                return back()->with('success', 'Updated successfully.');
            }
            PeopleAccess::hr();
            abort_unless(in_array($action, ['archive', 'restore'], true), 422);
            DB::table('announcements')->where('id', $id)->update(['archived' => $action === 'archive', 'updated_at' => now()]);
            return back()->with('success', 'Updated successfully.');
        }
        abort_unless(isset($tables[$module]), 404);
        if ($module === 'shifts') {
            abort_unless(PeopleAccess::isHr() || in_array(auth()->user()->role, ['supervisor', 'leader'], true), 403);
            abort_unless(in_array($action, ['delete', 'delete-holiday', 'approve'], true), 422);
            if ($action === 'delete') {
                $shift = DB::table('shift_assignments')->where('id', $id)->first();
                if ($shift && ! PeopleAccess::isHr()) {
                    PeopleAccess::managerForEmployee((int) $shift->employee_id);
                    abort_unless($shift->status === 'pending_hr', 403);
                }
                $deleted = DB::table('shift_assignments')->where('id', $id)->delete();
                if ($deleted && $shift && $shift->rest_day) {
                    // The default rest day this one displaced comes back.
                    DB::table('shift_assignments')->where('employee_id', $shift->employee_id)
                        ->where('label', 'Rest day moved to '.Carbon::parse($shift->work_date)->format('M j'))->delete();
                }
                if (! $deleted) {
                    PeopleAccess::hr();
                    DB::table('holidays')->where('id', $id)->delete();
                } elseif ($shift) {
                    // Stay on the same person after the reload.
                    session()->flash('shift_employee', (int) $shift->employee_id);
                }
            } elseif ($action === 'approve') {
                PeopleAccess::hr();
                DB::table('shift_assignments')->where('id', $id)->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                PeopleAccess::hr();
                DB::table('holidays')->where('id', $id)->delete();
            }
            return back()->with('success', 'Updated successfully.');
        }
        DB::transaction(function () use ($request, $module, $id, $action, $tables) {
            $row = DB::table($tables[$module])->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($module === 'overtime' || $module === 'loans') {
                if ($action === 'cancel') {
                    PeopleAccess::ownOrHr((int) $row->employee_id);
                    abort_unless($row->status === 'pending', 422, 'Only pending requests can be cancelled.');
                    DB::table($tables[$module])->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
                    return;
                }
                if ($module === 'overtime') PeopleAccess::reviewOvertime((int) $row->employee_id);
                else {
                    PeopleAccess::hr();
                    abort_if(DB::table('employees')->where('employee_id', $row->employee_id)->value('user_id') == auth()->id(), 403, 'You cannot approve your own loan.');
                }
                // A monthly amortization runs until HR ends it - when the agency
                // says the loan is paid, or the employee leaves.
                if ($module === 'loans' && $action === 'stop') {
                    abort_unless($row->status === 'active', 422);
                    DB::table('employee_loans')->where('id', $id)->update(['status' => 'stopped', 'decision_note' => 'Deductions stopped by '.auth()->user()->full_name.' on '.now()->format('M j, Y'), 'updated_at' => now()]);
                    return;
                }
                if ($module === 'loans' && $action === 'disburse') {
                    abort_unless($row->status === 'approved', 422);
                    DB::table('employee_loans')->where('id', $id)->update(['status' => 'active', 'disbursed_at' => now(), 'updated_at' => now()]);
                    return;
                }
                $firstReview = $module === 'overtime' && ! PeopleAccess::isHr();
                $expectedStatus = $module === 'overtime' && PeopleAccess::isHr() ? 'pending_hr' : 'pending';
                abort_unless(in_array($action, ['approve', 'reject'], true) && $row->status === $expectedStatus, 422);
                $data = $request->validate(['decision_note' => ($action === 'reject' ? 'required' : 'nullable').'|string|max:2000']);
                $update = [
                    'status' => $action === 'approve'
                        ? ($firstReview ? 'pending_hr' : 'approved')
                        : 'rejected',
                    'decision_note' => $firstReview ? null : ($data['decision_note'] ?? null),
                    'reviewed_by' => $firstReview ? null : auth()->id(),
                    'updated_at' => now(),
                ];
                if ($module === 'overtime') {
                    if ($firstReview) {
                        $update['manager_reviewed_by'] = auth()->id();
                        $update['manager_reviewed_at'] = now();
                        $update['manager_decision_note'] = $data['decision_note'] ?? null;
                    } else {
                        $update['reviewed_at'] = now();
                    }
                    if ($action === 'approve') {
                        $request->validate(['approved_amount' => 'nullable|numeric|min:0.01|max:1000000']);
                        $employee = DB::table('employees')->where('employee_id', $row->employee_id)->first();
                        abort_unless($employee, 404);
                        $suggestion = app(\App\Services\PhilippineOvertime::class)->suggest($employee, $row->starts_at, $row->ends_at);
                        $approved = $request->filled('approved_amount')
                            ? round((float) $request->input('approved_amount'), 2)
                            : $suggestion['suggested_amount'];
                        if ($firstReview) {
                            $update['manager_decision_note'] = trim(($update['manager_decision_note'] ?? '').' Supervisor checked suggested amount: PHP '.number_format($suggestion['suggested_amount'], 2).' at '.$suggestion['multiplier'].'x.');
                        } else {
                            $update['approved_amount'] = $approved;
                            $update['decision_note'] = trim(($update['decision_note'] ?? '').' Auto-calculated suggestion: PHP '.number_format($suggestion['suggested_amount'], 2).' at '.$suggestion['multiplier'].'x; HR-approved amount: PHP '.number_format($approved, 2).'.');
                        }
                    }
                }
                DB::table($tables[$module])->where('id', $id)->update($update);
                return;
            }
            PeopleAccess::ownOrHr((int) $row->employee_id);
            if ($module === 'checklists') {
                if ($action === 'add') {
                    PeopleAccess::hr();
                    abort_if($row->completed_at, 422);
                    $data = $request->validate(['title' => 'required|string|max:200', 'owner' => ['required', Rule::in(['hr', 'employee'])]]);
                    DB::table('checklist_items')->insert($data + ['checklist_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                } elseif ($action === 'complete') {
                    PeopleAccess::hr();
                    abort_if(DB::table('checklist_items')->where('checklist_id', $id)->whereNull('completed_at')->exists(), 422, 'Complete every item before closing the checklist.');
                    DB::table('employee_checklists')->where('id', $id)->update(['completed_at' => now(), 'updated_at' => now()]);
                } else {
                    abort_unless($action === 'toggle' && ! $row->completed_at, 422);
                    $item = DB::table('checklist_items')->where('checklist_id', $id)->where('id', $request->input('item_id'))->first();
                    abort_unless($item, 404);
                    if ($item->owner === 'hr') PeopleAccess::hr();
                    DB::table('checklist_items')->where('id', $item->id)->update(['completed_at' => $item->completed_at ? null : now(), 'completed_by' => $item->completed_at ? null : auth()->id(), 'updated_at' => now()]);
                }
                return;
            }
            if ($module === 'reviews') {
                abort_if($row->status === 'finalized', 422, 'Finalized reviews are locked.');

                // The review itself is HR's to write. The employee's part is
                // answering the notices and reading the result.
                PeopleAccess::hr();

                if ($action === 'notice') {
                    $data = $request->validate([
                        'occurred_on' => 'required|date_format:Y-m-d',
                        'type'        => ['required', Rule::in(array_keys(\App\Services\ReviewSummary::TYPES))],
                        'allegation'  => 'required|string|min:10|max:5000',
                        'respond_by'  => 'required|date_format:Y-m-d|after_or_equal:today',
                    ]);

                    DB::table('employee_notices')->insert($data + [
                        'employee_id' => $row->employee_id,
                        'issued_by'   => auth()->id(),
                        'issued_at'   => now(),
                        'status'      => 'issued',
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                } else {
                    abort_unless($action === 'finalize', 422);

                    $data = $request->validate([
                        'rating'   => ['required', 'integer', Rule::in(array_keys(\App\Services\ReviewSummary::GRADES))],
                        'feedback' => 'required|string|min:10|max:10000',
                    ]);

                    DB::table('performance_reviews')->where('id', $id)->update($data + [
                        'status' => 'finalized', 'reviewed_by' => auth()->id(),
                        'finalized_at' => now(), 'updated_at' => now(),
                    ]);
                }
                return;
            }
            PeopleAccess::hr();
            abort_unless($action === 'delete', 422);
            DB::table($tables[$module])->where('id', $id)->delete();
            if ($module === 'documents') Storage::disk('local')->delete($row->path);
        });
        return back()->with('success', 'Updated successfully.');
    }

    /**
     * The two halves of a notice that are not HR writing it: the employee
     * answering, and HR deciding once they have.
     *
     * Its own route rather than an action on a review, because a notice exists
     * whether or not a review does - somebody served in March should not have
     * to wait for the June appraisal to answer it.
     */
    public function notice(Request $request, int $id)
    {
        $notice = DB::table('employee_notices')->where('id', $id)->first();
        abort_unless($notice, 404);

        $action = $request->input('action');

        if ($action === 'explain') {
            // Only the person it is about, and only once.
            abort_unless(PeopleAccess::employeeId() === (int) $notice->employee_id, 403);
            abort_unless($notice->status === 'issued', 422, 'That notice has already been answered.');

            $data = $request->validate(['explanation' => 'required|string|min:10|max:10000']);

            DB::table('employee_notices')->where('id', $id)->update($data + [
                'status' => 'explained', 'explained_at' => now(), 'updated_at' => now(),
            ]);

            \App\Services\Auditor::record('update', 'employee_notices', $id,
                ['status' => 'issued'], ['status' => 'explained']);

            return back()->with('success', 'Your explanation has been sent to HR.');
        }

        PeopleAccess::hr();

        if ($action === 'terminate') {
            // Only from a heard case, and only once: this is the second notice,
            // and it has to have a first one behind it.
            $already = DB::table('employee_notices')->where('parent_id', $id)->where('kind', 'not')->exists();

            abort_unless(\App\Services\ReviewSummary::canTerminate($notice, $already), 422,
                'A Notice of Termination can only follow a Notice to Explain that was answered and decided as termination.');

            $data = $request->validate([
                'effective_on' => 'required|date_format:Y-m-d|after_or_equal:today',
                'allegation'   => 'required|string|min:10|max:5000',
            ]);

            DB::transaction(function () use ($data, $notice, $id) {
                DB::table('employee_notices')->insert([
                    'employee_id'  => $notice->employee_id,
                    'kind'         => 'not',
                    'parent_id'    => $id,
                    'effective_on' => $data['effective_on'],
                    'occurred_on'  => $notice->occurred_on,
                    'type'         => $notice->type,
                    'allegation'   => $data['allegation'],
                    'issued_by'    => auth()->id(),
                    'issued_at'    => now(),
                    'status'       => 'closed',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);

                // The employment ends on the date the notice gives, so the
                // record says so rather than somebody remembering to change it.
                DB::table('employees')->where('employee_id', $notice->employee_id)
                    ->update(['status' => 'terminated', 'updated_at' => now()]);
            });

            \App\Services\Auditor::record('create', 'employee_notices', $id, null,
                ['kind' => 'not', 'effective_on' => $data['effective_on']]);

            return back()->with('success', 'The Notice of Termination has been issued and they can see it.');
        }

        abort_unless($action === 'decide', 422);
        abort_if($notice->status === 'closed', 422, 'That notice is already closed.');

        // A decision before the explanation is the thing the twin-notice rule
        // exists to prevent, so it is refused unless the time to answer has
        // run out.
        abort_if($notice->status === 'issued' && \Carbon\Carbon::parse($notice->respond_by)->gte(today()),
            422, 'They still have until '.$notice->respond_by.' to explain.');

        $data = $request->validate([
            'decision'       => ['required', Rule::in(array_keys(\App\Services\ReviewSummary::DECISIONS))],
            'decision_notes' => 'required|string|min:10|max:10000',
        ]);

        DB::table('employee_notices')->where('id', $id)->update($data + [
            'status' => 'closed', 'decided_by' => auth()->id(), 'decided_at' => now(), 'updated_at' => now(),
        ]);

        \App\Services\Auditor::record('update', 'employee_notices', $id,
            ['status' => $notice->status], ['status' => 'closed', 'decision' => $data['decision']]);

        return back()->with('success', 'The decision has been recorded and the employee can see it.');
    }

    public function download(int $id)
    {
        $row = DB::table('employee_documents')->find($id);
        abort_unless($row, 404);
        PeopleAccess::ownOrHr((int) $row->employee_id);
        abort_unless(Storage::disk('local')->exists($row->path), 404);
        return Storage::disk('local')->download($row->path, $row->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
