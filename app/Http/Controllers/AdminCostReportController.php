<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCostReportRequest;
use App\Services\AdminCostReportService;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class AdminCostReportController extends Controller
{
    public function __invoke(AdminCostReportRequest $request, AdminCostReportService $service): Response
    {
        $today = CarbonImmutable::now('UTC')->toDateString();
        $start = $request->validated('start_date', CarbonImmutable::now('UTC')->subDays(6)->toDateString());
        $end = $request->validated('end_date', $today);

        return Inertia::render('Admin/CostReport', [
            'startDate' => $start,
            'endDate' => $end,
            'report' => $service->report($start, $end),
        ]);
    }
}
