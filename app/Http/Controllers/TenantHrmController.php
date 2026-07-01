<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\Shift;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TenantHrmController extends Controller
{
    public function index(Request $request): Response
    {
        $employees = Employee::with(['department', 'designation', 'team', 'officeLocation', 'shift', 'user.roles'])
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_no', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Tenant/Hrm/Index', [
            'employees' => $employees,
            'lookups' => $this->lookups(),
            'stats' => [
                'employees' => Employee::count(),
                'active' => Employee::where('status', 'active')->count(),
                'on_leave' => LeaveRequest::where('status', 'approved')
                    ->whereDate('starts_on', '<=', now())
                    ->whereDate('ends_on', '>=', now())
                    ->count(),
                'payroll_runs' => PayrollRun::count(),
            ],
            'attendance' => AttendanceRecord::with('employee:id,name,employee_no')->latest('attendance_date')->limit(10)->get(),
            'leaves' => LeaveRequest::with(['employee:id,name,employee_no', 'leaveType:id,name'])->latest()->limit(10)->get(),
            'payrollRuns' => PayrollRun::latest()->limit(10)->get(),
        ]);
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        $data = $this->employeeData($request);
        $role = $request->string('role')->toString();

        if ($data['login_enabled']) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($request->string('password')->toString() ?: str()->password(12)),
                    'email_verified_at' => now(),
                ]
            );

            if ($role) {
                $user->syncRoles([$role]);
            }

            $data['user_id'] = $user->id;
        }

        Employee::create($data);

        return back()->with('success', 'Employee created.');
    }

    public function updateEmployee(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->employeeData($request, $employee);
        $role = $request->string('role')->toString();

        if ($data['login_enabled']) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => $request->filled('password') ? Hash::make($request->string('password')->toString()) : ($employee->user?->password ?? Hash::make(str()->password(12))),
                    'email_verified_at' => now(),
                ]
            );

            if ($role) {
                $user->syncRoles([$role]);
            }

            $data['user_id'] = $user->id;
        } else {
            $data['user_id'] = null;
        }

        $employee->update($data);

        return back()->with('success', 'Employee updated.');
    }

    public function archiveEmployee(Employee $employee): RedirectResponse
    {
        $employee->update(['status' => 'archived', 'login_enabled' => false]);
        $employee->delete();

        return back()->with('success', 'Employee archived.');
    }

    public function storeDocument(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $path = $request->file('document')->store("hrm/employees/{$employee->id}", 'public');

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'type' => $data['type'] ?? null,
            'path' => $path,
        ]);

        return back()->with('success', 'Employee document uploaded.');
    }

    public function storeSetup(Request $request, string $type): RedirectResponse
    {
        match ($type) {
            'departments' => Department::create($request->validate(['name' => ['required', 'max:255'], 'code' => ['nullable', 'max:50'], 'description' => ['nullable', 'max:1000']])),
            'designations' => Designation::create($request->validate(['department_id' => ['nullable', 'exists:departments,id'], 'name' => ['required', 'max:255'], 'code' => ['nullable', 'max:50']])),
            'teams' => Team::create($request->validate(['department_id' => ['nullable', 'exists:departments,id'], 'name' => ['required', 'max:255'], 'description' => ['nullable', 'max:1000']])),
            'office-locations' => OfficeLocation::create($request->validate(['name' => ['required', 'max:255'], 'phone' => ['nullable', 'max:100'], 'address' => ['nullable', 'max:1000']])),
            'shifts' => Shift::create($request->validate(['name' => ['required', 'max:255'], 'starts_at' => ['required'], 'ends_at' => ['required'], 'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:180']])),
            'leave-types' => LeaveType::create($request->validate(['name' => ['required', 'max:255'], 'days_per_year' => ['required', 'integer', 'min:0', 'max:365'], 'is_paid' => ['boolean']])),
            default => abort(404),
        };

        return back()->with('success', 'HRM setup saved.');
    }

    private function employeeData(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_no' => ['required', 'max:100', Rule::unique('employees', 'employee_no')->ignore($employee?->id)],
            'name' => ['required', 'max:255'],
            'email' => [$request->boolean('login_enabled') ? 'required' : 'nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'max:100'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'office_location_id' => ['nullable', 'exists:office_locations,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'joining_date' => ['nullable', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,suspended,archived'],
            'login_enabled' => ['boolean'],
            'password' => [$request->boolean('login_enabled') && ! $employee ? 'required' : 'nullable', 'string', 'min:8'],
            'role' => ['nullable', 'exists:roles,name'],
        ]);
    }

    private function lookups(): array
    {
        return [
            'departments' => Department::orderBy('name')->get(),
            'designations' => Designation::orderBy('name')->get(),
            'teams' => Team::orderBy('name')->get(),
            'officeLocations' => OfficeLocation::orderBy('name')->get(),
            'shifts' => Shift::orderBy('name')->get(),
            'leaveTypes' => LeaveType::orderBy('name')->get(),
            'roles' => Role::whereIn('name', config('isp_os.tenant_default_roles', []))->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']),
        ];
    }
}
