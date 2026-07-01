<?php

namespace App\Http\Controllers;

use App\Models\AdvanceSalary;
use App\Models\User;
use App\Services\AdvanceSalaryImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;

class AdvanceSalaryController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month ?: now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $installmentActiveForMonth = function ($query) use ($monthStart, $monthEnd) {
            $query->whereIn('created_by', getCompanyAndUsersId())
                ->where('repayment_type', 'installment')
                ->whereNotNull('installment_months')
                ->whereNotNull('monthly_installment_amount')
                ->whereDate('request_date', '<', $monthStart->toDateString())
                ->whereDate('installment_start_date', '<=', $monthEnd->toDateString())
                ->whereRaw('DATE_ADD(COALESCE(installment_start_date, request_date), INTERVAL installment_months MONTH) > ?', [$monthStart->toDateString()]);
        };

        $query = User::emp()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereHas('advanceSalaries', function ($query) use ($monthStart, $monthEnd, $installmentActiveForMonth) {
                $query->where(function ($query) use ($monthStart, $monthEnd, $installmentActiveForMonth) {
                    $query->where(function ($query) use ($monthStart, $monthEnd) {
                        $query->whereIn('created_by', getCompanyAndUsersId())
                            ->whereBetween('request_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    })->orWhere(function ($query) use ($installmentActiveForMonth) {
                        $installmentActiveForMonth($query);
                    });
                });
            })
            ->with([
                'employee:id,user_id,employee_id,base_salary,department_id,whatsapp_number',
                'employee.department:id,name',
                'advanceSalaries' => function ($query) use ($monthStart, $monthEnd, $installmentActiveForMonth) {
                    $query->where(function ($query) use ($monthStart, $monthEnd, $installmentActiveForMonth) {
                        $query->where(function ($query) use ($monthStart, $monthEnd) {
                            $query->whereIn('created_by', getCompanyAndUsersId())
                                ->whereBetween('request_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                        })->orWhere(function ($query) use ($installmentActiveForMonth) {
                            $installmentActiveForMonth($query);
                        });
                    })
                        ->orderByDesc('request_date')
                        ->orderByDesc('id');
                },
            ]);

        if ($request->filled('employee_id') && $request->employee_id !== 'all') {
            $query->where('id', $request->employee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhereHas('employee', function ($query) use ($search) {
                        $query->where('employee_id', 'like', '%'.$search.'%');
                    });
            });
        }

        $advanceEmployees = $query
            ->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
            ->orderBy('name')
            ->paginate($request->per_page ?? 10)
            ->withQueryString()
            ->through(function (User $employee) use ($monthStart) {
                $advances = $this->monthlyAdvanceRows($employee->advanceSalaries, $monthStart);
                $advanceTotal = $advances->sum(fn ($advance) => (float) $advance['amount']);
                $salary = (float) ($employee->employee?->base_salary ?? 0);

                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee?->employee_id,
                    'name' => $employee->name,
                    'department_name' => $employee->employee?->department?->name,
                    'whatsapp_number' => $employee->employee?->whatsapp_number,
                    'salary' => $salary,
                    'advance_total' => $advanceTotal,
                    'remaining_salary' => $salary - $advanceTotal,
                    'latest_advance_date' => $advances->first()['request_date'] ?? null,
                    'advances' => $advances->values(),
                ];
            });

        $employees = User::emp()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->with([
                'employee:id,user_id,employee_id,base_salary,whatsapp_number',
                'advanceSalaries' => function ($query) use ($monthStart, $monthEnd) {
                    $query->whereIn('created_by', getCompanyAndUsersId())
                        ->whereBetween('request_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                },
            ])
            ->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_code' => $employee->employee?->employee_id,
                'whatsapp_number' => $employee->employee?->whatsapp_number,
                'salary' => (float) ($employee->employee?->base_salary ?? 0),
                'advance_total' => $employee->advanceSalaries->sum(fn ($advance) => (float) $advance->amount),
            ]);

        return Inertia::render('hr/advance-salaries/index', [
            'advanceEmployees' => $advanceEmployees,
            'employees' => $employees,
            'filters' => array_merge($request->all(['employee_id', 'per_page', 'search']), [
                'month' => $month,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'repayment_type' => 'nullable|in:full,installment',
            'repayment_source' => 'nullable|in:salary,bonus',
            'installment_months' => 'nullable|required_if:repayment_type,installment|integer|min:1|max:120',
            'monthly_installment_amount' => 'nullable|required_if:repayment_type,installment|numeric|min:0.01',
            'request_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $this->normalizeInstallmentFields($validated);
        $validated['created_by'] = creatorId();
        $validated['request_date'] = $validated['request_date'] ?? now()->toDateString();
        $validated['approval_date'] = $validated['request_date'];
        $validated['status'] = 'approved';

        AdvanceSalary::create($validated);

        return redirect()->back()->with('success', __('Salary advance added successfully.'));
    }

    public function update(Request $request, AdvanceSalary $advanceSalary)
    {
        if (! in_array($advanceSalary->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'repayment_type' => 'nullable|in:full,installment',
            'repayment_source' => 'nullable|in:salary,bonus',
            'installment_months' => 'nullable|required_if:repayment_type,installment|integer|min:1|max:120',
            'monthly_installment_amount' => 'nullable|required_if:repayment_type,installment|numeric|min:0.01',
            'request_date' => 'nullable|date',
            'approval_date' => 'nullable|date',
            'status' => 'nullable|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);
        $validated['request_date'] = $validated['request_date'] ?? $advanceSalary->request_date?->toDateString() ?? now()->toDateString();
        $validated['approval_date'] = $validated['approval_date'] ?? $advanceSalary->approval_date?->toDateString() ?? now()->toDateString();
        $validated['status'] = $validated['status'] ?? $advanceSalary->status ?? 'approved';
        $this->normalizeInstallmentFields($validated);

        $advanceSalary->update($validated);

        return redirect()->back()->with('success', __('Salary advance updated successfully.'));
    }

    public function updateStatus(Request $request, AdvanceSalary $advanceSalary)
    {
        if (! in_array($advanceSalary->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
        ]);

        $advanceSalary->update([
            'status' => $validated['status'],
            'approval_date' => $validated['status'] === 'approved' ? now()->toDateString() : null,
        ]);

        return redirect()->back()->with('success', __('Advance salary status updated successfully.'));
    }

    public function import(Request $request, AdvanceSalaryImportService $importService)
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt,pdf',
            'month' => 'required|date_format:Y-m',
        ]);

        try {
            $stats = $importService->import($validated['file'], $validated['month'], creatorId());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $message = __('Salary advances imported. Created: :created, Updated: :updated, Skipped: :skipped.', [
            'created' => $stats['created'],
            'updated' => $stats['updated'],
            'skipped' => $stats['skipped'],
        ]);

        if (! empty($stats['errors'])) {
            $message .= ' '.__('First issues:').' '.implode(' | ', $stats['errors']);
        }

        return redirect()->back()->with($stats['created'] || $stats['updated'] ? 'success' : 'error', $message);
    }

    public function destroy(AdvanceSalary $advanceSalary)
    {
        if (! in_array($advanceSalary->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $advanceSalary->delete();

        return redirect()->back()->with('success', __('Advance salary deleted successfully.'));
    }

    public function destroyEmployee(User $employee)
    {
        if (! in_array($employee->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $deleted = AdvanceSalary::where('employee_id', $employee->id)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->delete();

        return redirect()->back()->with('success', __('Deleted :count salary advance entries.', ['count' => $deleted]));
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:users,id',
        ]);

        $employeeIds = User::emp()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereIn('id', $validated['employee_ids'])
            ->pluck('id');

        if ($employeeIds->isEmpty()) {
            return redirect()->back()->with('error', __('No valid employees selected.'));
        }

        $deleted = AdvanceSalary::whereIn('employee_id', $employeeIds)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->delete();

        return redirect()->back()->with('success', __('Deleted :count salary advance entries.', ['count' => $deleted]));
    }

    public function forwardBalance(Request $request, User $employee)
    {
        if (! in_array($employee->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'deduct_amount' => 'required|numeric|min:0',
        ]);

        $monthStart = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonthNoOverflow()->startOfMonth();

        $advances = AdvanceSalary::where('employee_id', $employee->id)
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereBetween('request_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        if ($advances->isEmpty()) {
            return redirect()->back()->with('error', __('No salary advance found for this month.'));
        }

        $currentTotal = round($advances->sum(fn (AdvanceSalary $advance) => (float) $advance->amount), 2);
        $deductAmount = round((float) $validated['deduct_amount'], 2);

        if ($deductAmount > $currentTotal) {
            return redirect()->back()->with('error', __('Deduction amount cannot be greater than current month advance.'));
        }

        $forwardAmount = round($currentTotal - $deductAmount, 2);
        $forwardNotePrefix = 'Forwarded from '.$monthStart->format('F Y').' salary advance.';

        DB::transaction(function () use ($advances, $deductAmount, $employee, $forwardAmount, $forwardNotePrefix, $nextMonthStart) {
            $primaryAdvance = $advances->first();

            if ($deductAmount > 0) {
                $primaryAdvance->update([
                    'amount' => $deductAmount,
                    'repayment_type' => 'full',
                    'repayment_source' => 'salary',
                    'installment_months' => null,
                    'monthly_installment_amount' => null,
                    'installment_start_date' => null,
                    'notes' => trim(($primaryAdvance->notes ? $primaryAdvance->notes.' ' : '').'Current month deduction adjusted on '.now()->toDateString().'.'),
                ]);

                $advances->slice(1)->each->delete();
            } else {
                $advances->each->delete();
            }

            $existingForward = AdvanceSalary::where('employee_id', $employee->id)
                ->whereIn('created_by', getCompanyAndUsersId())
                ->whereBetween('request_date', [$nextMonthStart->toDateString(), $nextMonthStart->copy()->endOfMonth()->toDateString()])
                ->where('notes', 'like', $forwardNotePrefix.'%')
                ->first();

            if ($forwardAmount > 0) {
                $forwardPayload = [
                    'employee_id' => $employee->id,
                    'amount' => $forwardAmount,
                    'deducted_amount' => 0,
                    'repayment_type' => 'full',
                    'repayment_source' => 'salary',
                    'installment_months' => null,
                    'monthly_installment_amount' => null,
                    'installment_start_date' => null,
                    'request_date' => $nextMonthStart->toDateString(),
                    'approval_date' => $nextMonthStart->toDateString(),
                    'status' => 'approved',
                    'notes' => $forwardNotePrefix.' Current month deduction: '.number_format($deductAmount, 2, '.', '').'.',
                    'created_by' => creatorId(),
                ];

                if ($existingForward) {
                    $existingForward->update($forwardPayload);
                } else {
                    AdvanceSalary::create($forwardPayload);
                }
            } elseif ($existingForward) {
                $existingForward->delete();
            }
        });

        return redirect()->back()->with('success', __('Advance updated. :deduct will be deducted this month and :forward forwarded to next month.', [
            'deduct' => number_format($deductAmount, 2),
            'forward' => number_format($forwardAmount, 2),
        ]));
    }

    private function monthlyAdvanceRows($advances, Carbon $monthStart)
    {
        $monthEnd = $monthStart->copy()->endOfMonth();

        return $advances->flatMap(function (AdvanceSalary $advance) use ($monthStart, $monthEnd) {
            $isCurrentMonthAdvance = $advance->request_date
                && $advance->request_date->betweenIncluded($monthStart, $monthEnd);

            if ($isCurrentMonthAdvance) {
                return [[
                    'id' => $advance->id,
                    'employee_id' => $advance->employee_id,
                    'amount' => (float) $advance->amount,
                    'deducted_amount' => (float) $advance->deducted_amount,
                    'repayment_type' => $advance->repayment_type ?? 'full',
                    'repayment_source' => $advance->repayment_source ?? 'salary',
                    'installment_months' => $advance->installment_months,
                    'monthly_installment_amount' => $advance->monthly_installment_amount ? (float) $advance->monthly_installment_amount : null,
                    'installment_start_date' => $advance->installment_start_date?->toDateString(),
                    'outstanding_amount' => (float) $advance->outstanding_amount,
                    'request_date' => $advance->request_date?->toDateString(),
                    'notes' => $advance->notes,
                    'receipt_url' => URL::temporarySignedRoute('advance-salary-receipts.show', now()->addDays(7), $advance),
                    'status_label' => $advance->repayment_type === 'installment' ? 'Installment advance' : 'Advance taken',
                    'is_virtual_repayment' => false,
                ]];
            }

            if (($advance->repayment_type ?? 'full') !== 'installment') {
                return [];
            }

            $startDate = ($advance->installment_start_date ?: $advance->request_date)?->copy()->startOfMonth();

            if (! $startDate || ! $advance->installment_months || ! $advance->monthly_installment_amount) {
                return [];
            }

            $periodNumber = $startDate->diffInMonths($monthStart) + 1;

            if ($periodNumber < 1 || $periodNumber > (int) $advance->installment_months) {
                return [];
            }

            $monthlyAmount = (float) $advance->monthly_installment_amount;
            $scheduledBeforeThisMonth = max(0, $periodNumber - 1) * $monthlyAmount;
            $repaymentAmount = min($monthlyAmount, max(0, (float) $advance->amount - $scheduledBeforeThisMonth));

            if ($repaymentAmount <= 0) {
                return [];
            }

            return [[
                'id' => 'repayment-'.$advance->id.'-'.$monthStart->format('Y-m'),
                'source_advance_id' => $advance->id,
                'employee_id' => $advance->employee_id,
                'amount' => round($repaymentAmount, 2),
                'deducted_amount' => null,
                'repayment_type' => 'installment',
                'repayment_source' => $advance->repayment_source ?? 'salary',
                'installment_months' => $advance->installment_months,
                'monthly_installment_amount' => $monthlyAmount,
                'installment_start_date' => $advance->installment_start_date?->toDateString(),
                'outstanding_amount' => max(0, round((float) $advance->amount - ($scheduledBeforeThisMonth + $repaymentAmount), 2)),
                'request_date' => $monthStart->toDateString(),
                'notes' => trim('Loan repayment'.($advance->notes ? ' - '.$advance->notes : '')),
                'status_label' => 'Loan repayment',
                'is_virtual_repayment' => true,
            ]];
        })
            ->sortByDesc('request_date')
            ->values();
    }

    private function normalizeInstallmentFields(array &$validated): void
    {
        $validated['repayment_type'] = $validated['repayment_type'] ?? 'full';
        $validated['repayment_source'] = $validated['repayment_source'] ?? 'salary';

        if ($validated['repayment_type'] !== 'installment') {
            $validated['installment_months'] = null;
            $validated['monthly_installment_amount'] = null;
            $validated['installment_start_date'] = null;

            return;
        }

        $validated['installment_start_date'] = $validated['request_date'] ?? now()->toDateString();
    }
}
