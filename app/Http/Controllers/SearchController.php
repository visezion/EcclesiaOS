<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $searchService): View
    {
        return view('modules.search', $searchService->search($request->string('q')->toString()));
    }
}
