<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryToolController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('inventory.tools.view');

        $tools = DB::table('inventory_tools as tools')
            ->leftJoin('inventory_locations as locations', 'locations.id', '=', 'tools.location_id')
            ->leftJoin('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
            ->whereNull('tools.deleted_at')
            ->orderBy('tools.name')
            ->get([
                'tools.*', 'locations.name as location_name',
                'warehouses.name as warehouse_name',
            ])->map(function (object $tool): array {
                $assignment = DB::table('inventory_tool_assignments as assignments')
                    ->join('employees', 'employees.id', '=', 'assignments.employee_id')
                    ->where('assignments.tool_id', $tool->id)
                    ->whereNull('assignments.returned_at')
                    ->orderByDesc('assignments.id')
                    ->first([
                        'assignments.id', 'assignments.assigned_at',
                        'assignments.expected_return_at', 'assignments.condition_out',
                        'assignments.notes', 'employees.employee_code',
                        'employees.first_name', 'employees.last_name',
                    ]);

                return [
                    ...((array) $tool),
                    'id' => (int) $tool->id,
                    'active' => (bool) $tool->active,
                    'location' => $tool->warehouse_name === null
                        ? null
                        : $tool->warehouse_name.' / '.$tool->location_name,
                    'assignment' => $assignment === null ? null : [
                        'id' => (int) $assignment->id,
                        'employee' => trim($assignment->first_name.' '.$assignment->last_name),
                        'employee_code' => $assignment->employee_code,
                        'assigned_at' => (string) $assignment->assigned_at,
                        'expected_return_at' => $assignment->expected_return_at === null
                            ? null
                            : (string) $assignment->expected_return_at,
                        'condition_out' => $assignment->condition_out,
                        'notes' => $assignment->notes,
                    ],
                ];
            })->all();

        $history = DB::table('inventory_tool_assignments as assignments')
            ->join('inventory_tools as tools', 'tools.id', '=', 'assignments.tool_id')
            ->join('employees', 'employees.id', '=', 'assignments.employee_id')
            ->join('users as assigners', 'assigners.id', '=', 'assignments.assigned_by')
            ->leftJoin('users as returners', 'returners.id', '=', 'assignments.returned_by')
            ->whereNull('tools.deleted_at')
            ->orderByDesc('assignments.id')
            ->limit(100)
            ->get([
                'assignments.id', 'assignments.assigned_at',
                'assignments.expected_return_at', 'assignments.returned_at',
                'assignments.condition_out', 'assignments.condition_return',
                'assignments.notes', 'tools.code as tool_code',
                'tools.name as tool_name', 'employees.employee_code',
                'employees.first_name', 'employees.last_name',
                'assigners.name as assigned_by_name',
                'returners.name as returned_by_name',
            ])->map(function (object $assignment): array {
                $returnedCondition = $assignment->condition_return === null
                    ? null
                    : (string) $assignment->condition_return;

                return [
                    'id' => (int) $assignment->id,
                    'tool' => $assignment->tool_code.' — '.$assignment->tool_name,
                    'employee' => $assignment->employee_code.' — '.trim(
                        $assignment->first_name.' '.$assignment->last_name
                    ),
                    'assigned_at' => (string) $assignment->assigned_at,
                    'expected_return_at' => $assignment->expected_return_at === null
                        ? null
                        : (string) $assignment->expected_return_at,
                    'returned_at' => $assignment->returned_at === null
                        ? null
                        : (string) $assignment->returned_at,
                    'condition_out' => (string) $assignment->condition_out,
                    'condition_return' => $returnedCondition,
                    'deteriorated' => $returnedCondition !== null
                        && self::conditionRank($returnedCondition)
                            > self::conditionRank((string) $assignment->condition_out),
                    'notes' => $assignment->notes,
                    'assigned_by' => $assignment->assigned_by_name,
                    'returned_by' => $assignment->returned_by_name,
                ];
            })->all();

        return Inertia::render('Inventory/Tools/Index', [
            'tools' => $tools,
            'history' => $history,
            'employees' => DB::table('employees')
                ->where('active', true)->whereNull('deleted_at')
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'employee_code', 'first_name', 'last_name'])
                ->map(fn (object $employee): array => [
                    'id' => (int) $employee->id,
                    'name' => trim($employee->first_name.' '.$employee->last_name),
                    'code' => $employee->employee_code,
                ])->all(),
            'locations' => DB::table('inventory_locations as locations')
                ->join('inventory_warehouses as warehouses', 'warehouses.id', '=', 'locations.warehouse_id')
                ->where('locations.active', true)->whereNull('locations.deleted_at')
                ->where('warehouses.active', true)->whereNull('warehouses.deleted_at')
                ->orderBy('warehouses.name')->orderBy('locations.name')
                ->get(['locations.id', 'locations.name', 'warehouses.name as warehouse_name'])
                ->map(fn (object $location): array => [
                    'id' => (int) $location->id,
                    'name' => $location->warehouse_name.' / '.$location->name,
                ])->all(),
            'today' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('inventory.tools.manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:inventory_tools,code'],
            'name' => ['required', 'string', 'max:180'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'condition' => ['required', Rule::in(['good', 'fair', 'damaged', 'maintenance'])],
            'location_id' => ['nullable', 'integer', 'exists:inventory_locations,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::table('inventory_tools')->insert([
            ...$data,
            'status' => in_array($data['condition'], ['damaged', 'maintenance'], true)
                ? $data['condition']
                : 'available',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Herramienta registrada correctamente.');
    }

    public function assign(Request $request, int $toolId): RedirectResponse
    {
        Gate::authorize('inventory.tools.assign');
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'assigned_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'expected_return_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:assigned_at'],
            'condition_out' => ['required', Rule::in(['good', 'fair'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($data, $request, $toolId): void {
                $tool = DB::table('inventory_tools')
                    ->where('id', $toolId)->whereNull('deleted_at')
                    ->lockForUpdate()->first();

                if ($tool === null || ! $tool->active || $tool->status !== 'available') {
                    throw new DomainException('La herramienta no está disponible para asignación.');
                }

                $employeeIsActive = DB::table('employees')
                    ->where('id', $data['employee_id'])->where('active', true)
                    ->whereNull('deleted_at')->exists();

                if (! $employeeIsActive) {
                    throw new DomainException('El empleado seleccionado no está activo.');
                }

                $hasOpenAssignment = DB::table('inventory_tool_assignments')
                    ->where('tool_id', $toolId)->whereNull('returned_at')
                    ->lockForUpdate()->exists();

                if ($hasOpenAssignment) {
                    throw new DomainException('La herramienta ya tiene una asignación activa.');
                }

                $now = now();
                DB::table('inventory_tool_assignments')->insert([
                    ...$data,
                    'tool_id' => $toolId,
                    'assigned_by' => (int) $request->user()->getAuthIdentifier(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('inventory_tools')->where('id', $toolId)
                    ->update(['status' => 'assigned', 'updated_at' => $now]);
            }, 5);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['tool' => $exception->getMessage()]);
        }

        return back()->with('success', 'Herramienta asignada al empleado.');
    }

    public function returnTool(Request $request, int $toolId): RedirectResponse
    {
        Gate::authorize('inventory.tools.assign');
        $data = $request->validate([
            'returned_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'condition_return' => ['required', Rule::in(['good', 'fair', 'damaged', 'maintenance'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $deterioration = null;

        try {
            DB::transaction(function () use ($data, $request, $toolId, &$deterioration): void {
                $assignment = DB::table('inventory_tool_assignments')
                    ->where('tool_id', $toolId)->whereNull('returned_at')
                    ->lockForUpdate()->first();

                if ($assignment === null) {
                    throw new DomainException('La herramienta no tiene una asignación activa.');
                }

                if ($data['returned_at'] < (string) $assignment->assigned_at) {
                    throw new DomainException('La fecha de devolución no puede ser anterior a la asignación.');
                }

                if (
                    self::conditionRank($data['condition_return'])
                    > self::conditionRank((string) $assignment->condition_out)
                ) {
                    if (trim((string) ($data['notes'] ?? '')) === '') {
                        throw new DomainException(
                            'Describe la avería o diferencia encontrada al recibir la herramienta.'
                        );
                    }

                    $deterioration = sprintf(
                        'ALERTA: la herramienta salió en condición %s y regresó en condición %s.',
                        self::conditionLabel((string) $assignment->condition_out),
                        self::conditionLabel($data['condition_return']),
                    );
                }

                $previousNotes = trim((string) ($assignment->notes ?? ''));
                $returnNotes = trim((string) ($data['notes'] ?? ''));
                $notes = $returnNotes === ''
                    ? $previousNotes
                    : trim($previousNotes."\nObservación de devolución: ".$returnNotes);

                $now = now();
                DB::table('inventory_tool_assignments')->where('id', $assignment->id)->update([
                    'returned_at' => $data['returned_at'],
                    'condition_return' => $data['condition_return'],
                    'notes' => $notes === '' ? null : $notes,
                    'returned_by' => (int) $request->user()->getAuthIdentifier(),
                    'updated_at' => $now,
                ]);

                $status = in_array($data['condition_return'], ['damaged', 'maintenance'], true)
                    ? $data['condition_return']
                    : 'available';
                DB::table('inventory_tools')->where('id', $toolId)->update([
                    'condition' => $data['condition_return'],
                    'status' => $status,
                    'updated_at' => $now,
                ]);
            }, 5);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['tool' => $exception->getMessage()]);
        }

        if ($deterioration !== null) {
            return back()->with('warning', $deterioration.' La herramienta quedó bloqueada hasta revisión.');
        }

        return back()->with('success', 'Devolución registrada correctamente.');
    }

    public function updateStatus(Request $request, int $toolId): RedirectResponse
    {
        Gate::authorize('inventory.tools.manage');
        $data = $request->validate([
            'condition' => ['required', Rule::in(['good', 'fair', 'damaged', 'maintenance'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($data, $toolId): void {
                $tool = DB::table('inventory_tools')
                    ->where('id', $toolId)->whereNull('deleted_at')
                    ->lockForUpdate()->first();

                if ($tool === null || ! $tool->active) {
                    throw new DomainException('La herramienta no existe o está inactiva.');
                }

                $hasOpenAssignment = DB::table('inventory_tool_assignments')
                    ->where('tool_id', $toolId)->whereNull('returned_at')
                    ->lockForUpdate()->exists();

                if ($tool->status === 'assigned' || $hasOpenAssignment) {
                    throw new DomainException('No puedes cambiar el estado mientras la herramienta esté asignada.');
                }

                if (
                    in_array($tool->status, ['damaged', 'maintenance'], true)
                    && in_array($data['condition'], ['good', 'fair'], true)
                    && trim((string) ($data['notes'] ?? '')) === ''
                ) {
                    throw new DomainException(
                        'Indica qué reparación, revisión o prueba permitió volver a habilitar la herramienta.'
                    );
                }

                $status = in_array($data['condition'], ['damaged', 'maintenance'], true)
                    ? $data['condition']
                    : 'available';

                $previousNotes = trim((string) ($tool->notes ?? ''));
                $statusNotes = trim((string) ($data['notes'] ?? ''));
                $notes = $statusNotes === ''
                    ? $previousNotes
                    : trim($previousNotes."\n".now()->toDateString().': '.$statusNotes);

                DB::table('inventory_tools')->where('id', $toolId)->update([
                    'condition' => $data['condition'],
                    'status' => $status,
                    'notes' => $notes === '' ? null : $notes,
                    'updated_at' => now(),
                ]);
            }, 5);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['tool' => $exception->getMessage()]);
        }

        return back()->with('success', 'Estado de la herramienta actualizado correctamente.');
    }

    private static function conditionRank(string $condition): int
    {
        return match ($condition) {
            'good' => 0,
            'fair' => 1,
            'maintenance' => 2,
            'damaged' => 3,
            default => 0,
        };
    }

    private static function conditionLabel(string $condition): string
    {
        return match ($condition) {
            'good' => 'buena',
            'fair' => 'regular',
            'maintenance' => 'mantenimiento',
            'damaged' => 'dañada',
            default => $condition,
        };
    }
}
