<?php

declare(strict_types=1);

namespace App\Modules\Inventory\UI\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Application\Ports\RegisterInventoryEntryInterface;
use App\Modules\Inventory\UI\Http\Requests\StoreInventoryEntryRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Modules\Inventory\Application\Ports\InventoryEntryOptionsInterface;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryEntryController extends Controller
{
    public function store(
        StoreInventoryEntryRequest $request,
        RegisterInventoryEntryInterface $entries,
    ): JsonResponse {
        try {
            $result = $entries->register(
                $request->validated(),
                (int) $request->user()->getAuthIdentifier(),
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages([
                'entry' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $result['repeated']
                ? 'Esta entrada ya había sido registrada.'
                : 'Entrada confirmada correctamente.',
            'data' => $result,
        ], $result['repeated'] ? 200 : 201);
    }

    public function create(InventoryEntryOptionsInterface $options): Response
    {
        Gate::authorize('inventory.entries.create');
        Gate::authorize('inventory.entries.post');

        return Inertia::render('Inventory/Entries/Create', $options->get());
    }
}
