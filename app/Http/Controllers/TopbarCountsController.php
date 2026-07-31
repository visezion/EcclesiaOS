<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\UnreadCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TopbarCountsController extends Controller
{
    public function __invoke(Request $request, UnreadCounts $counts): JsonResponse
    {
        return response()->json($counts->for($request->user()));
    }
}
