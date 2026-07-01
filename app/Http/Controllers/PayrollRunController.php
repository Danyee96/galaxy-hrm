<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\User;
use App\Services\WorkingScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PayrollRunController extends Controller
{
    public function index(Request $request)
    {
        if (Auth::user()->can('manage-payroll-runs')) {
            $query = PayrollRun::with(['creator'])->where(function ($q) {
                if (Auth::user()->can('manage-any-payroll-runs')) {
                    $q->whereIn('created_by', getCompanyAndUsersId());
                } elseif (Auth::user()->can('manage-own-payroll-runs')) {
                    $q->where('created_by', Auth::id());
                } else {
                    $q->whereRaw('1 = 0');
                }
            });

            // Handle search
            if ($request->has('search') && ! empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%'.$request->search.'%')
                        ->orWhere('notes', 'like', '%'.$request->search.'%');
                });
            }

            // Handle status filter
            if ($request->has('status') && ! empty($request->status) && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Handle date range filter
            if ($request->has('date_from') && ! empty($request->date_from)) {
                $query->where('pay_period_start', '>=', $request->date_from);
            }
            if ($request->has('date_to') && ! empty($request->date_to)) {
                $query->where('pay_period_end', '<=', $request->date_to);
            }

            // Handle sorting
            if ($request->has('sort_field') && ! empty($request->sort_field)) {
                $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
            } else {
                $query->orderBy('pay_period_start', 'desc');
            }

            $payrollRuns = $query->paginate($request->per_page ?? 10);
            $employees = User::where('type', 'employee')
                ->whereIn('created_by', getCompanyAndUsersId())
                ->whereHas('employee', function ($q) {
                    $q->whereIn('employee_status', ['active', 'probation']);
                })
                ->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
                ->orderBy('name')
                ->get(['id', 'name']);

            return Inertia::render('hr/payroll-runs/index', [
                'payrollRuns' => $payrollRuns,
                'employees' => $employees,
                'filters' => $request->all(['search', 'status', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'per_page']),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function show($payrollRunId)
    {
        if (Auth::user()->can('view-payroll-runs')) {
            $payrollRun = PayrollRun::where('id', $payrollRunId)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->with([
                    'payrollEntries' => function ($query) {
                        $query->select([
                            'id',
                            'payroll_run_id',
                            'employee_id',
                            'basic_salary',
                            'net_pay',
                            'unpaid_leave_days',
                            'absent_days',
                            'bonus_amount',
                            'advance_deduction',
                            'absence_deduction',
                            'late_penalty',
                            'other_deductions',
                            'deductions_breakdown',
                        ])->with([
                            'employee:id,name',
                            'employee.employee:id,user_id,employee_id,department_id',
                            'employee.employee.department:id,name',
                        ])
                            ->orderByRaw("CAST((select employee_id from employees where employees.user_id = payroll_entries.employee_id limit 1) AS UNSIGNED) asc")
                            ->orderBy('id');
                    },
                ])
                ->first();

            if (! $payrollRun) {
                return redirect()->back()->with('error', __('Payroll run not found.'));
            }

            return Inertia::render('hr/payroll-runs/show', [
                'payrollRun' => $payrollRun,
                'holidayExclusions' => collect(app(WorkingScheduleService::class)->holidayDetails())
                    ->filter(fn ($holiday) => $holiday['date'] >= $payrollRun->pay_period_start->toDateString()
                        && $holiday['date'] <= $payrollRun->pay_period_end->toDateString())
                    ->values()
                    ->all(),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function store(Request $request)
    {
        if (Auth::user()->can('create-payroll-runs')) {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'pay_period_start' => 'required|date',
                'pay_period_end' => 'required|date|after:pay_period_start',
            ]);

            $validated['created_by'] = creatorId();
            $validated['status'] = 'draft';
            $validated['pay_date'] = $validated['pay_period_end'];
            $validated['payroll_frequency'] = $request->input('payroll_frequency', 'monthly');

            // Check if payroll run already exists for this period
            $exists = PayrollRun::where('pay_period_start', $validated['pay_period_start'])
                ->where('pay_period_end', $validated['pay_period_end'])
                ->whereIn('created_by', getCompanyAndUsersId())
                ->exists();

            if ($exists) {
                return redirect()->back()->with('error', __('Payroll run already exists for this period.'));
            }

            PayrollRun::create($validated);

            return redirect()->back()->with('success', __('Payroll run created successfully.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function update(Request $request, $payrollRunId)
    {
        if (Auth::user()->can('edit-payroll-runs')) {
            $payrollRun = PayrollRun::where('id', $payrollRunId)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->first();

            if ($payrollRun) {
                try {
                    $validated = $request->validate([
                        'title' => 'required|string|max:255',
                        'pay_period_start' => 'required|date',
                        'pay_period_end' => 'required|date|after:pay_period_start',
                    ]);

                    $validated['pay_date'] = $validated['pay_period_end'];

                    // Only allow updates if status is draft
                    if ($payrollRun->status !== 'draft') {
                        return redirect()->back()->with('error', __('Cannot update processed payroll run.'));
                    }

                    $payrollRun->update($validated);

                    return redirect()->back()->with('success', __('Payroll run updated successfully'));
                } catch (\Exception $e) {
                    return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update payroll run'));
                }
            } else {
                return redirect()->back()->with('error', __('Payroll run Not Found.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function destroy($payrollRunId)
    {
        if (Auth::user()->can('delete-payroll-runs')) {
            $payrollRun = PayrollRun::where('id', $payrollRunId)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->first();

            if ($payrollRun) {
                try {
                    $payrollRun->delete();

                    return redirect()->back()->with('success', __('Payroll run deleted successfully'));
                } catch (\Exception $e) {
                    return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete payroll run'));
                }
            } else {
                return redirect()->back()->with('error', __('Payroll run Not Found.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function process($payrollRunId)
    {
        if (Auth::user()->can('process-payroll-runs')) {
            @set_time_limit(120);

            $validated = request()->validate([
                'select_all' => 'nullable|boolean',
                'employee_ids' => 'nullable|array',
                'employee_ids.*' => 'integer|exists:users,id',
            ]);

            $payrollRun = PayrollRun::where('id', $payrollRunId)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->first();

            if ($payrollRun) {
                try {
                    if ($payrollRun->status !== 'draft') {
                        return redirect()->back()->with('error', __('Payroll run is not in draft status.'));
                    }

                    $employeeIds = [];
                    if (! ($validated['select_all'] ?? false)) {
                        $employeeIds = array_map('intval', $validated['employee_ids'] ?? []);
                        if (empty($employeeIds)) {
                            return redirect()->back()->with('error', __('Please select at least one employee or choose select all.'));
                        }
                    }

                    $success = $payrollRun->processPayroll($employeeIds);

                    if ($success) {
                        return redirect()->back()->with('success', __('Payroll run processed successfully'));
                    } else {
                        return redirect()->back()->with('error', __('Failed to process payroll run'));
                    }
                } catch (\Exception $e) {
                    return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to process payroll run'));
                }
            } else {
                return redirect()->back()->with('error', __('Payroll run Not Found.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }
}
