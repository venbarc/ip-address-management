<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\IpAddress\StoreIpAddressRequest;
use App\Http\Requests\IpAddress\UpdateIpAddressRequest;
use App\Models\IpAddressRecord;
use App\Services\IpManagement\IpAddressService;
use App\Support\ActorContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IpAddressController extends Controller
{
    public function __construct(
        private readonly IpAddressService $ipAddressService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->ipAddressService->list(),
        ]);
    }

    public function store(StoreIpAddressRequest $request): JsonResponse
    {
        $record = $this->ipAddressService->create(
            ActorContext::fromRequest($request),
            $request->validated(),
        );

        return response()->json([
            'data' => $record,
        ], 201);
    }

    public function update(UpdateIpAddressRequest $request, IpAddressRecord $record): JsonResponse
    {
        $record = $this->ipAddressService->update(
            ActorContext::fromRequest($request),
            $record,
            $request->validated(),
        );

        return response()->json([
            'data' => $record,
        ]);
    }

    public function destroy(Request $request, IpAddressRecord $record): JsonResponse
    {
        $this->ipAddressService->delete(ActorContext::fromRequest($request), $record);

        return response()->json([], 204);
    }
}
