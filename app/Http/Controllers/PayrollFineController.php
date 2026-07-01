<?php

namespace App\Http\Controllers;

use App\Models\PayrollFine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollFineController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month ?: now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $query = User::emp()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->whereHas('payrollFines', function ($query) use ($monthStart, $monthEnd) {
                $query->whereIn('created_by', getCompanyAndUsersId())
                    ->whereBetween('fine_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
            })
            ->with([
                'employee:id,user_id,employee_id,base_salary',
                'payrollFines' => function ($query) use ($monthStart, $monthEnd) {
                    $query->whereIn('created_by', getCompanyAndUsersId())
                        ->whereBetween('fine_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->orderByDesc('fine_date')
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

        $fineEmployees = $query
            ->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
            ->orderBy('name')
            ->paginate($request->per_page ?? 10)
            ->withQueryString()
            ->through(function (User $employee) {
                $fineTotal = $employee->payrollFines->sum(fn ($fine) => (float) $fine->amount);

                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee?->employee_id,
                    'name' => $employee->name,
                    'salary' => (float) ($employee->employee?->base_salary ?? 0),
                    'fine_total' => $fineTotal,
                    'latest_fine_date' => $employee->payrollFines->first()?->fine_date?->toDateString(),
                    'fines' => $employee->payrollFines->map(fn ($fine) => [
                        'id' => $fine->id,
                        'employee_id' => $fine->employee_id,
                        'amount' => (float) $fine->amount,
                        'fine_date' => $fine->fine_date?->toDateString(),
                        'notes' => $fine->notes,
                    ])->values(),
                ];
            });

        $employees = User::emp()
            ->whereIn('created_by', getCompanyAndUsersId())
            ->where('status', 'active')
            ->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('hr/payroll-fines/index', [
            'fineEmployees' => $fineEmployees,
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
            'fine_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $validated['created_by'] = creatorId();
        $validated['fine_date'] = $validated['fine_date'] ?? now()->toDateString();

        PayrollFine::create($validated);

        return redirect()->back()->with('success', __('Fine added successfully.'));
    }

    public function update(Request $request, PayrollFine $payrollFine)
    {
        if (! in_array($payrollFine->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validated = $request->validate([
            'employee_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'fine_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $validated['fine_date'] = $validated['fine_date'] ?? $payrollFine->fine_date?->toDateString() ?? now()->toDateString();
        $payrollFine->update($validated);

        return redirect()->back()->with('success', __('Fine updated successfully.'));
    }

    public function destroy(PayrollFine $payrollFine)
    {
        if (! in_array($payrollFine->created_by, getCompanyAndUsersId())) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $payrollFine->delete();

        return redirect()->back()->with('success', __('Fine deleted successfully.'));
    }
}
