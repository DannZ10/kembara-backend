<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function dashboard(): JsonResponse
    {
        $data = $this->reportService->getDashboardSummary();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function popularGear(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 5);
        $data = $this->reportService->getPopularGears($limit);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $period = $request->input('groupBy', 'daily');
        $data = $this->reportService->getRevenueReport(
            $period,
            $request->input('date_from'),
            $request->input('date_to'),
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $threshold = (int) $request->input('threshold', 3);
        $data = $this->reportService->getLowStockGears($threshold);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function busiestPeriods(Request $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 7);

        return response()->json([
            'success' => true,
            'data' => $this->reportService->getBusiestPeriods($limit),
        ]);
    }

    public function statusBreakdown(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->getStatusBreakdown(),
        ]);
    }

    public function categoryPerformance(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->reportService->getCategoryPerformance(),
        ]);
    }
}
