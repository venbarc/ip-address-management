<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 100), 1), 250);

        $query = AuditLog::query()->latest('occurred_at');

        if ($request->filled('actor_user_id')) {
            $query->where('actor_user_id', $request->integer('actor_user_id'));
        }

        if ($request->filled('session_uuid')) {
            $query->where('session_uuid', 'like', '%'.$request->string('session_uuid')->trim()->toString().'%');
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', 'like', '%'.$request->string('subject_type')->trim()->toString().'%');
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', 'like', '%'.$request->string('subject_id')->trim()->toString().'%');
        }

        if ($request->filled('event')) {
            $query->where('event', 'like', '%'.$request->string('event')->trim()->toString().'%');
        }

        return response()->json([
            'data' => $query->limit($limit)->get(),
        ]);
    }
}
