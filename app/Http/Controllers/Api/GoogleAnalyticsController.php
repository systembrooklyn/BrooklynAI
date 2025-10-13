<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleAnalyticsService;
use Illuminate\Http\Request;

class GoogleAnalyticsController extends Controller
{
    public function properties(Request $request)
    {
        $service = new GoogleAnalyticsService($request->user());

        return response()->json([
            'message' => 'Properties retrieved successfully.',
            'data' => $service->getProperties()
        ]);
    }

    public function report(Request $request, string $propertyId)
    {
        $validated = $request->validate([
            "startDate" => 'nullable|date',
            "endDate" => 'nullable|date|after_or_equal:startDate'
        ]);
        $dimensions = array_filter(explode(',', $request->input('dimensions', 'date,country,deviceCategory')));
        $metrics = array_filter(explode(',', $request->input('metrics', 'sessions,totalUsers,activeUsers,bounceRate')));
        $country = $request->input('country');
        $start = $validated['startDate'] ?? '30daysAgo';
        $end = $validated['endDate'] ?? 'today';


        $service = new GoogleAnalyticsService($request->user());
        $data = $service->getReport($propertyId, $dimensions, $metrics, $start, $end, $country);

        return response()->json([
            'message' => 'Report retrieved successfully.',
            'data' => $data
        ]);
    }
    public function realtime(Request $request, string $propertyId)
    {
        $service = new GoogleAnalyticsService($request->user());
        $data = $service->getRealtimeOverview($propertyId);
        return response()->json([
            'message' => 'Realtime overview retrieved successfully.',
            'data' => $data,
        ]);
    }


    public function homeScreenMetrics(Request $request, string $propertyId)
    {
        $validated = $request->validate([
            "startDate" => 'nullable|date',
            "endDate" => 'nullable|date|after_or_equal:startDate'
        ]);
        $start = $validated['startDate'] ?? 'today';
        $end = $validated['endDate'] ?? 'today';
        $service = new GoogleAnalyticsService($request->user());
        $data = $service->getHomeScreenMetrics($propertyId, $start, $end);
        return response()->json([
            'message' => 'Home screen metrics retrieved successfully.',
            'data' => $data,
        ]);
    }

    public function getTopPagesByViews(Request $request, string $propertyId)
    {
        $service = new GoogleAnalyticsService($request->user());
        $data = $service->getTopPagesByViews($propertyId, 10); // Top 10 pages
        return response()->json([
            'message' => 'Views by page title retrieved successfully.',
            'data' => $data,
        ]);
    }
}
