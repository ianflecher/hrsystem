<?php

namespace App\Http\Controllers;

use App\Support\PeopleAccess;
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
        'checklists' => 'Onboarding & offboarding',
        'reviews' => 'Performance reviews', 'loans' => 'Loans & cash advances',
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

    public function index(Request $request, string $module)
    {
        [$hr, $employeeId] = $this->context($request);
        abort_unless(isset(self::MODULES[$module]), 404);
        $request->validate(['month' => 'nullable|date_format:Y-m', 'search' => 'nullable|string|max:100']);
        $month = Carbon::parse(($request->input('month') ?: now()->format('Y-m')).'-01');
        $employees = $hr ? DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
            ->select('e.employee_id', 'u.full_name')->orderBy('u.full_name')->get() : collect();
        $departments = $hr ? DB::table('departments')->orderBy('department_name')->get() : collect();
        $query = null;
        $extra = [];
        $tables = ['documents' => 'employee_documents', 'overtime' => 'overtime_requests',
            'checklists' => 'employee_checklists',
            'reviews' => 'performance_reviews', 'loans' => 'employee_loans'];
        if (isset($tables[$module])) {
            $query = DB::table($tables[$module].' as r')
                ->join('employees as e', 'e.employee_id', '=', 'r.employee_id')
                ->join('users as u', 'u.user_id', '=', 'e.user_id')->select('r.*', 'u.full_name');
            if (! $hr) {
                $query->where(function ($q) use ($module, $employeeId) {
                    $q->where('r.employee_id', $employeeId);
                    if ($module === 'overtime' && auth()->user()->role === 'supervisor') {
                        $q->orWhereIn('e.department_id', DB::table('departments')->where('supervisor_id', auth()->id())->select('department_id'));
                    }
                });
            }
            if ($request->filled('search')) {
                $query->where('u.full_name', 'like', '%'.$request->input('search').'%');
            }
        }
        if ($module === 'documents') {
            $extra['expiring'] = (clone $query)->whereNotNull('r.expires_on')->where('r.expires_on', '<=', today()->addDays(30)->toDateString())->count();
            if ($request->boolean('expiring')) {
                $query->whereNotNull('r.expires_on')->where('r.expires_on', '<=', today()->addDays(30)->toDateString());
            }
        }
        if ($module === 'shifts') {
            // Nothing is assigned per date any more: the calendar is drawn from
            // each person's own shift and rest days, and the holidays on top.
            $extra['holidays'] = DB::table('holidays')
                ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->orderBy('date')->get()->keyBy('date');
            $extra['staff'] = DB::table('employees as e')->join('users as u', 'u.user_id', '=', 'e.user_id')
                ->when($hr, fn ($q) => $q->where('e.status', 'active'), fn ($q) => $q->where('e.employee_id', $employeeId))
                ->select('e.employee_id', 'e.shift_start', 'e.shift_end', 'e.rest_days', 'u.full_name')
                ->orderBy('u.full_name')->get();
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
            $extra['goals'] = DB::table('performance_goals')->whereIn('review_id', $rows->pluck('id'))->get()->groupBy('review_id');
        }
        if ($module === 'loans') {
            $extra['installments'] = DB::table('loan_installments as i')->join('hr_payroll as p', 'p.payroll_id', '=', 'i.payroll_id')
                ->whereIn('i.loan_id', $rows->pluck('id'))->select('i.*', 'p.period_start', 'p.status')->get()->groupBy('loan_id');
        }
        return view('people.index', compact('hr', 'employeeId', 'module', 'employees', 'departments', 'month', 'rows', 'extra'));
    }

    public function store(Request $request, string $module)
    {
        [$hr, $employeeId] = $this->context($request);
        abort_unless(isset(self::MODULES[$module]), 404);
        if (! in_array($module, ['overtime', 'loans'], true)) PeopleAccess::hr();
        // Loans are requested from the employee portal only - see the view.
        abort_if($hr && in_array($module, ['loans', 'overtime'], true), 403, 'This is requested by the employee.');
        if (in_array($module, ['documents', 'checklists', 'reviews'], true) && $hr) {
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
                if ($minutes > 960) throw ValidationException::withMessages(['ends_at' => 'Submit at most 16 hours per request.']);
                DB::transaction(function () use ($employeeId, $start, $end, $base, $data, $minutes) {
                    DB::table('employees')->where('employee_id', $employeeId)->lockForUpdate()->first();
                    if (DB::table('overtime_requests')->where('employee_id', $employeeId)->whereIn('status', ['pending', 'approved'])
                        ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                        throw ValidationException::withMessages(['starts_at' => 'This request overlaps existing overtime.']);
                    }
                    DB::table('overtime_requests')->insert($base + ['starts_at' => $start, 'ends_at' => $end, 'minutes' => $minutes, 'reason' => $data['reason']]);
                });
                break;
            case 'shifts':
                // A holiday is the company's, not any one employee's, so this is
                // the whole of the input for the calendar.
                $data = $request->validate([
                    'date' => 'required|date_format:Y-m-d',
                    'name' => 'required|string|max:120',
                    'type' => ['required', Rule::in(['regular', 'special'])],
                ]);
                DB::table('holidays')->updateOrInsert(['date' => $data['date']],
                    ['name' => $data['name'], 'type' => $data['type'], 'created_at' => now(), 'updated_at' => now()]);
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
                $data = $request->validate(['type' => ['required', Rule::in(['loan', 'cash_advance'])], 'amount' => 'required|numeric|min:1|max:1000000',
                    'installment' => 'required|numeric|min:1|lte:amount', 'starts_on' => 'required|date_format:Y-m-d', 'reason' => 'required|string|min:5|max:3000']);
                DB::table('employee_loans')->insert($base + $data);
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
        abort_unless(isset($tables[$module]), 404);
        DB::transaction(function () use ($request, $module, $id, $action, $tables) {
            $row = DB::table($tables[$module])->where('id', $id)->lockForUpdate()->first();
            abort_unless($row, 404);
            if ($module === 'shifts') {
                // A holiday belongs to no employee, so it is handled before any
                // of the ownership checks below - there is no owner to check.
                PeopleAccess::hr();
                abort_unless($action === 'delete', 422);
                DB::table('holidays')->where('id', $id)->delete();

                return;
            }
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
                if ($module === 'loans' && $action === 'disburse') {
                    abort_unless($row->status === 'approved', 422);
                    DB::table('employee_loans')->where('id', $id)->update(['status' => 'active', 'disbursed_at' => now(), 'updated_at' => now()]);
                    return;
                }
                abort_unless(in_array($action, ['approve', 'reject'], true) && $row->status === 'pending', 422);
                $data = $request->validate(['decision_note' => ($action === 'reject' ? 'required' : 'nullable').'|string|max:2000']);
                $update = ['status' => $action === 'approve' ? 'approved' : 'rejected', 'decision_note' => $data['decision_note'] ?? null, 'reviewed_by' => auth()->id(), 'updated_at' => now()];
                if ($module === 'overtime') {
                    $update['reviewed_at'] = now();
                    if ($action === 'approve') {
                        $request->validate(['approved_amount' => 'required|numeric|min:0.01|max:1000000']);
                        $update['approved_amount'] = round((float) $request->input('approved_amount'), 2);
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
                if ($action === 'goal') {
                    PeopleAccess::hr();
                    $data = $request->validate(['title' => 'required|string|max:200']);
                    DB::table('performance_goals')->insert($data + ['review_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
                } elseif ($action === 'progress') {
                    $request->validate(['progress' => 'required|integer|between:0,100']);
                    $goal = DB::table('performance_goals')->where('review_id', $id)->where('id', $request->input('goal_id'))->first();
                    abort_unless($goal, 404);
                    DB::table('performance_goals')->where('id', $goal->id)->update(['progress' => $request->input('progress'), 'updated_at' => now()]);
                } elseif ($action === 'self-assessment') {
                    abort_unless(PeopleAccess::employeeId() === (int) $row->employee_id, 403);
                    $data = $request->validate(['self_assessment' => 'required|string|min:10|max:10000']);
                    DB::table('performance_reviews')->where('id', $id)->update($data + ['status' => 'submitted', 'updated_at' => now()]);
                } else {
                    PeopleAccess::hr();
                    abort_unless($action === 'finalize', 422);
                    $data = $request->validate(['rating' => 'required|integer|between:1,5', 'feedback' => 'required|string|min:10|max:10000']);
                    DB::table('performance_reviews')->where('id', $id)->update($data + ['status' => 'finalized', 'reviewed_by' => auth()->id(), 'finalized_at' => now(), 'updated_at' => now()]);
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

    public function download(int $id)
    {
        $row = DB::table('employee_documents')->find($id);
        abort_unless($row, 404);
        PeopleAccess::ownOrHr((int) $row->employee_id);
        abort_unless(Storage::disk('local')->exists($row->path), 404);
        return Storage::disk('local')->download($row->path, $row->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }
}
