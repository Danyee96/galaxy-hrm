<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeeDocument;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (Auth::user()->can('manage-employees')) {
            $authUser     = Auth::user();
            $query = User::with(['employee.branch', 'employee.department', 'employee.designation'])
                ->withSum(['bonuses as profile_bonus' => fn ($q) => $q->where('bonus_type', 'custom')->where('notes', 'Employee profile bonus')], 'amount')
                ->where(function ($q) {
                    if (Auth::user()->can('manage-any-employees')) {
                        $q->whereIn('created_by',  getCompanyAndUsersId());
                    } elseif (Auth::user()->can('manage-own-employees')) {
                        $q->where('created_by', Auth::id())->orWhere('id', Auth::id());
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                })
                ->where('type', 'employee');

            // Handle search
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhereHas('employee', function ($eq) use ($request) {
                            $eq->where('employee_id', 'like', '%' . $request->search . '%')
                                ->orWhereHas('department', function ($dq) use ($request) {
                                    $dq->where('name', 'like', '%' . $request->search . '%');
                                });
                        });
                });
            }

            // Handle department filter
            if ($request->has('department') && !empty($request->department) && $request->department !== 'all') {
                $query->whereHas('employee', function ($q) use ($request) {
                    $q->where('department_id', $request->department);
                });
            }

            // Handle sorting
            if ($request->has('sort_field') && !empty($request->sort_field)) {
                $sortDirection = strtolower($request->sort_direction ?? 'asc') === 'desc' ? 'desc' : 'asc';

                if ($request->sort_field === 'employee_id') {
                    $query->orderByRaw(
                        "CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) {$sortDirection}"
                    )->orderBy('users.name');
                } elseif ($request->sort_field === 'name') {
                    $query->orderBy('users.name', $sortDirection);
                } else {
                    $query->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
                        ->orderBy('users.name');
                }
            } else {
                $query->orderByRaw("CAST((select employee_id from employees where employees.user_id = users.id limit 1) AS UNSIGNED) asc")
                    ->orderBy('users.name');
            }

            $employees = $query->paginate($request->per_page ?? 10);

            $departments = Department::with('branch')
                ->whereIn('created_by', getCompanyAndUsersId())
                ->where('status', 'active')
                ->get(['id', 'name', 'branch_id']);

            // Get plan limits for company users and staff users (only in SaaS mode)
            $planLimits = null;
            if (isSaas()) {
                if ($authUser->type === 'company' && $authUser->plan) {
                    $currentUserCount = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->count();
                    $planLimits = [
                        'current_users' => $currentUserCount,
                        'max_users' => $authUser->plan->max_employees,
                        'can_create' => $currentUserCount < $authUser->plan->max_employees
                    ];
                }
                // Check for staff users (created by company users)
                elseif ($authUser->type !== 'superadmin' && $authUser->created_by) {
                    $companyUser = User::find($authUser->created_by);
                    if ($companyUser && $companyUser->type === 'company' && $companyUser->plan) {
                        $currentUserCount = User::where('type', 'employee')->whereIn('created_by', getCompanyAndUsersId())->count();
                        $planLimits = [
                            'current_users' => $currentUserCount,
                            'max_users' => $companyUser->plan->max_employees,
                            'can_create' => $currentUserCount < $companyUser->plan->max_employees
                        ];
                    }
                }
            }


            return Inertia::render('hr/employees/index', [
                'employees' => $employees,
                'planLimits' => $planLimits,
                'departments' => $departments,
                'filters' => $request->all(['search', 'department', 'sort_field', 'sort_direction', 'per_page']),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (Auth::user()->can('create-employees')) {
            $departments = Department::with('branch')
                ->whereIn('created_by', getCompanyAndUsersId())
                ->where('status', 'active')
                ->get(['id', 'name', 'branch_id']);

            return Inertia::render('hr/employees/create', [
                'departments' => $departments,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (Auth::user()->can('create-employees')) {
            try {
                $validator = Validator::make($request->all(), [
                    'employee_id' => [
                        'required',
                        'string',
                        'max:255',
                        Rule::unique('employees', 'employee_id')->ignore($employee->id),
                    ],
                    'name' => 'required|string|max:255',
                    'department_id' => 'required|exists:departments,id',
                    'whatsapp_number' => 'nullable|string|max:30',
                    'salary' => 'nullable|numeric|min:0',
                    'bonus' => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $department = Department::whereIn('created_by', getCompanyAndUsersId())->findOrFail($request->department_id);

                $user = new User();
                $user->name = $request->name;
                $user->email = $this->generateEmployeeEmail($request->name);
                $user->password = Hash::make(Str::random(24));
                $user->type = 'employee';
                $user->lang = 'en';
                $user->created_by = creatorId();
                $user->save();

                // Assign Employee role
                if (isSaaS()) {
                    $employeeRole = Role::where('created_by', createdBy())->where('name', 'employee')->first();
                    if ($employeeRole) {
                        $user->assignRole($employeeRole);
                    }
                } else {
                    $employeeRole = Role::where('name', 'employee')->first();
                    if ($employeeRole) {
                        $user->assignRole($employeeRole);
                    }
                }


                $employee = new Employee();
                $employee->user_id = $user->id;
                $employee->employee_id = Employee::generateEmployeeId();
                $employee->branch_id = $department->branch_id;
                $employee->department_id = $department->id;
                $employee->whatsapp_number = $this->normalizePakistanWhatsAppNumber($request->whatsapp_number);
                $employee->base_salary = $request->salary ?: null;
                $employee->employee_status = 'active';
                $employee->created_by = creatorId();
                $employee->save();

                // Ensure EmployeeSalary record exists or is synced with employee base salary
                if (! is_null($request->salary)) {
                    $existingSalary = \App\Models\EmployeeSalary::where('employee_id', $user->id)->first();
                    if ($existingSalary) {
                        $existingSalary->basic_salary = $request->salary;
                        $existingSalary->save();
                    } else {
                        \App\Models\EmployeeSalary::create([
                            'employee_id' => $user->id,
                            'basic_salary' => $request->salary,
                            'components' => null,
                            'is_active' => true,
                            'created_by' => creatorId(),
                        ]);
                    }
                }

                if (!$employee->save()) {
                    throw new \Exception('Failed to save employee data');
                }

                $this->syncEmployeeBonus($user->id, $request->bonus);

                // Check if this is a candidate conversion
                if ($request->has('candidate_id')) {
                    $candidate = Candidate::find($request->candidate_id);
                    if ($candidate) {
                        $candidate->update(['is_employee' => true]);
                    }
                    return redirect()->route('hr.recruitment.candidates.index')->with('success', __('Candidate converted to employee successfully'));
                }

                return redirect()->route('hr.employees.index')->with('success', __('Employee created successfully'));
            } catch (\Exception $e) {
                \Log::error('Employee creation failed: ' . $e->getMessage());
                \Log::error('Stack trace: ' . $e->getTraceAsString());
                return redirect()->back()->with('error', __('Failed to create employee: :message', ['message' => $e->getMessage()]))->withInput();
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        if (Auth::user()->can('view-employees')) {
            // Check if employee belongs to current company
            $companyUserIds = getCompanyAndUsersId();
            if (!in_array($employee->created_by, $companyUserIds)) {
                return redirect()->back()->with('error', __('You do not have permission to view this employee'));
            }

            // Load user with employee relationships
            $user = User::with(['employee.branch', 'employee.department', 'employee.designation', 'employee.shift', 'employee.attendancePolicy', 'employee.documents.documentType'])
                ->where('id', $employee->user_id)
                ->first();

            return Inertia::render('hr/employees/show', [
                'employee' => $user,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        if (Auth::user()->can('edit-employees')) {
            // Check if employee belongs to current company
            $companyUserIds = getCompanyAndUsersId();
            if (!in_array($employee->created_by, $companyUserIds)) {
                return redirect()->back()->with('error', __('You do not have permission to edit this employee'));
            }

            // Load user with employee relationships
            $user = User::with(['employee.department'])
                ->withSum(['bonuses as profile_bonus' => fn ($q) => $q->where('bonus_type', 'custom')->where('notes', 'Employee profile bonus')], 'amount')
                ->where('id', $employee->user_id)
                ->first();

            $departments = Department::with('branch')
                ->whereIn('created_by', getCompanyAndUsersId())
                ->where('status', 'active')
                ->get(['id', 'name', 'branch_id']);

            return Inertia::render('hr/employees/edit', [
                'employee' => $user,
                'departments' => $departments,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        if (Auth::user()->can('edit-employees')) {
            // Check if employee belongs to current company
            $companyUserIds = getCompanyAndUsersId();
            if (!in_array($employee->created_by, $companyUserIds)) {
                return redirect()->back()->with('error', __('You do not have permission to update this employee'));
            }

            try {
                $validator = Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    'department_id' => 'required|exists:departments,id',
                    'whatsapp_number' => 'nullable|string|max:30',
                    'salary' => 'nullable|numeric|min:0',
                    'bonus' => 'nullable|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    return redirect()->back()->withErrors($validator)->withInput();
                }

                $department = Department::whereIn('created_by', getCompanyAndUsersId())->findOrFail($request->department_id);
                $user = $employee->user;

                $user->name = $request->name;
                $user->save();

                $employee->employee_id = trim((string) $request->employee_id);
                $employee->branch_id = $department->branch_id;
                $employee->department_id = $department->id;
                $employee->whatsapp_number = $this->normalizePakistanWhatsAppNumber($request->whatsapp_number);
                $employee->base_salary = $request->salary ?: null;
                $employee->save();

                $this->syncEmployeeBonus($user->id, $request->bonus);

                // If salary changed via employee update, sync EmployeeSalary and trigger recalcs
                if ($request->has('salary')) {
                    $existingSalary = \App\Models\EmployeeSalary::where('employee_id', $user->id)->first();
                    if ($existingSalary) {
                        $existingSalary->basic_salary = $request->salary ?: 0;
                        $existingSalary->save();
                    } else {
                        \App\Models\EmployeeSalary::create([
                            'employee_id' => $user->id,
                            'basic_salary' => $request->salary ?: 0,
                            'components' => null,
                            'is_active' => true,
                            'created_by' => creatorId(),
                        ]);
                    }
                }

                return redirect()->route('hr.employees.index')->with('success', __('Employee updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update employee'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($userId)
    {
        if (Auth::user()->can('delete-employees')) {
            try {
                $user = User::with('employee')->where('id', $userId)->whereIn('created_by', getCompanyAndUsersId())->first();

                if (!$user || !$user->employee) {
                    return redirect()->back()->with('error', __('Employee not found'));
                }

                $employee = $user->employee;

                // Delete documents first
                EmployeeDocument::where('employee_id', $employee->id)->delete();

                // Delete employee record
                $employee->delete();

                // Delete user record and avatar
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $user->delete();

                return redirect()->route('hr.employees.index')->with('success', __('Employee deleted successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __('Failed to delete employee: :message', ['message' => $e->getMessage()]));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    private function generateEmployeeEmail(string $name): string
    {
        $base = Str::slug($name) ?: 'employee';
        $email = $base.'@example.local';
        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base.'-'.$counter.'@example.local';
            $counter++;
        }

        return $email;
    }

    private function normalizePakistanWhatsAppNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number ?? '');

        if (! $digits) {
            return null;
        }

        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '92')) {
            // Already normalized without plus sign.
        } elseif (str_starts_with($digits, '0')) {
            $digits = '92'.substr($digits, 1);
        } else {
            $digits = '92'.$digits;
        }

        return '+'.$digits;
    }

    private function syncEmployeeBonus(int $userId, mixed $bonus): void
    {
        $amount = is_numeric($bonus) ? (float) $bonus : 0.0;

        $existingBonus = EmployeeBonus::where('employee_id', $userId)
            ->where('bonus_type', 'custom')
            ->whereIn('created_by', getCompanyAndUsersId())
            ->orderByDesc('bonus_date')
            ->first();

        if ($amount <= 0) {
            $existingBonus?->delete();
            return;
        }

        if ($existingBonus) {
            $existingBonus->update([
                'employee_id' => $userId,
                'bonus_type' => 'custom',
                'amount' => $amount,
                'bonus_date' => now()->toDateString(),
                'notes' => 'Employee profile bonus',
                'created_by' => creatorId(),
            ]);
            return;
        }

        EmployeeBonus::create([
            'employee_id' => $userId,
            'bonus_type' => 'custom',
            'amount' => $amount,
            'bonus_date' => now()->toDateString(),
            'notes' => 'Employee profile bonus',
            'created_by' => creatorId(),
        ]);
    }

    /**
     * Update employee status.
     */
    public function toggleStatus(Employee $employee)
    {
        if (Auth::user()->can('edit-employees')) {
            // Check if employee belongs to current company
            $companyUserIds = getCompanyAndUsersId();
            if (!in_array($employee->created_by, $companyUserIds)) {
                return redirect()->back()->with('error', __('You do not have permission to update this employee'));
            }

            try {
                $user = $employee->user;
                $newStatus = $user->status === 'active' ? 'inactive' : 'active';
                $user->update(['status' => $newStatus]);

                return redirect()->back()->with('success', __('Employee status updated successfully'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update employee status'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Change employee password.
     */
    public function changePassword(Request $request, Employee $employee)
    {
        if (Auth::user()->can('edit-employees')) {
            // Check if employee belongs to current company
            $companyUserIds = getCompanyAndUsersId();
            if (!in_array($employee->created_by, $companyUserIds)) {
                return redirect()->back()->with('error', __('You do not have permission to change this employee password'));
            }

            try {
                $validated = $request->validate([
                    'password' => 'required|string|min:8|confirmed',
                ]);

                $user = $employee->user;
                $user->password = Hash::make($validated['password']);
                $user->save();

                return redirect()->back()->with('success', __('Employee password changed successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to change employee password'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Delete employee document.
     */
    public function deleteDocument($userId, $documentId)
    {
        $user = User::with('employee')->find($userId);

        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($user->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to access this employee'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();

        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        try {
            $document->delete();
            return redirect()->back()->with('success', __('Document deleted successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete document'));
        }
    }

    /**
     * Approve employee document.
     */
    public function approveDocument($userId, $documentId)
    {
        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();

        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        try {
            $document->update(['verification_status' => 'verified']);
            return redirect()->back()->with('success', __('Document approved successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to approve document'));
        }
    }

    /**
     * Reject employee document.
     */
    public function rejectDocument($userId, $documentId)
    {
        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();

        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        try {
            $document->update(['verification_status' => 'rejected']);
            return redirect()->back()->with('success', __('Document rejected successfully'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to reject document'));
        }
    }

    /**
     * Download employee document.
     */
    public function downloadDocument($userId, $documentId)
    {

        $user = User::with('employee')->find($userId);
        if (!$user || !$user->employee) {
            return redirect()->back()->with('error', __('Employee not found'));
        }

        $companyUserIds = getCompanyAndUsersId();
        if (!in_array($user->created_by, $companyUserIds)) {
            return redirect()->back()->with('error', __('You do not have permission to access this employee'));
        }

        $document = EmployeeDocument::where('id', $documentId)
            ->where('employee_id', $userId)
            ->first();


        if (!$document) {
            return redirect()->back()->with('error', __('Document not found'));
        }

        if (!$document->file_path) {
            return redirect()->back()->with('error', __('Document file not found'));
        }

        $filePath = getStorageFilePath($document->file_path);

        if (!file_exists($filePath)) {
            return redirect()->back()->with('error', __('Document file not found'));
        }

        return response()->download($filePath);
    }
}
