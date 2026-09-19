<?php

declare(strict_types=1);

namespace App\Modules\Access\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

final class AccessManagementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('access.users.view');

        return Inertia::render('Access/Index', [
            'users' => User::query()->with('roles:id,name')->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'active' => (bool) $user->active, 'last_login_at' => $user->last_login_at,
                'roles' => $user->roles->pluck('name')->values(),
            ]),
            'roles' => Role::query()->with('permissions:id,name')->orderBy('name')->get()->map(fn (Role $role): array => [
                'id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')->values(),
            ]),
            'permissions' => DB::table('permissions')->orderBy('name')->pluck('name'),
            'employees' => DB::table('employees')->leftJoin('users', 'users.id', '=', 'employees.user_id')
                ->whereNull('employees.deleted_at')->orderBy('employees.first_name')
                ->get(['employees.*', 'users.email as user_email']),
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        Gate::authorize('access.users.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'], 'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);
        $user = User::query()->create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => Hash::make($data['password']), 'active' => true]);
        $user->syncRoles($data['roles']);
        return back()->with('success', 'Usuario creado correctamente.');
    }

    public function updateUser(Request $request, int $userId): RedirectResponse
    {
        Gate::authorize('access.users.manage');
        $user = User::query()->findOrFail($userId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'], 'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::exists('roles', 'name')], 'active' => ['required', 'boolean'],
        ]);
        if ($user->id === (int) $request->user()->getAuthIdentifier() && ! $data['active']) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta.');
        }
        $user->fill(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'active' => $data['active']]);
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();
        $user->syncRoles($data['roles']);
        return back()->with('success', 'Usuario actualizado.');
    }

    public function storeRole(Request $request): RedirectResponse
    {
        Gate::authorize('access.roles.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:roles,name'], 'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions']);
        return back()->with('success', 'Rol creado correctamente.');
    }

    public function updateRole(Request $request, int $roleId): RedirectResponse
    {
        Gate::authorize('access.roles.manage');
        $role = Role::query()->findOrFail($roleId);
        $data = $request->validate(['permissions' => ['required', 'array'], 'permissions.*' => ['string', Rule::exists('permissions', 'name')]]);
        $role->syncPermissions($data['permissions']);
        return back()->with('success', 'Permisos del rol actualizados.');
    }

    public function storeEmployee(Request $request): RedirectResponse
    {
        Gate::authorize('access.employees.manage');
        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:30', 'unique:employees,employee_code'],
            'national_id' => ['nullable', 'string', 'max:30', 'unique:employees,national_id'],
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'], 'hire_date' => ['nullable', 'date'],
            'department' => ['nullable', 'string', 'max:80'], 'job_title' => ['nullable', 'string', 'max:100'],
            'monthly_salary' => ['required', 'numeric', 'min:0'], 'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:employees,user_id'],
        ]);
        DB::table('employees')->insert([...$data, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('success', 'Empleado creado correctamente.');
    }

    public function toggleEmployee(Request $request, int $employeeId): RedirectResponse
    {
        Gate::authorize('access.employees.manage');
        $data = $request->validate(['active' => ['required', 'boolean']]);
        DB::table('employees')->where('id', $employeeId)->update(['active' => $data['active'], 'updated_at' => now()]);
        return back()->with('success', 'Estado del empleado actualizado.');
    }
}
