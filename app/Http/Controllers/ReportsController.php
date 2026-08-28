<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request, SalesReportService $reports): View
    {
        $range = $reports->resolveRange($request);

        return view('reports.index', [
            'range' => $range,
            'summary' => $reports->summary($range['from'], $range['to']),
            'products' => $reports->products($range['from'], $range['to']),
            'categories' => $reports->categories($range['from'], $range['to']),
            'byType' => $reports->byOrderType($range['from'], $range['to']),
            'typeLabels' => SalesReportService::ORDER_TYPE_LABELS,
        ]);
    }
}
