<?php

namespace ApiModelRelations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ApiDebugController extends Controller
{
    /**
     * Display the API debug dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Check if debugging is enabled
        if (!Config::get('api-model-relations.debug', false)) {
            abort(403, 'API debugging is disabled. Enable it in the api-model-relations config file.');
        }

        // Get recent API requests from cache
        $requests = Cache::get('api_model_relations_debug_requests', []);
        
        return view('api-model-relations::debug.index', [
            'requests' => $requests,
            'stats' => $this->getStats($requests),
        ]);
    }

    /**
     * Get API request details.
     *
     * @param string $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        // Check if debugging is enabled
        if (!Config::get('api-model-relations.debug', false)) {
            abort(403, 'API debugging is disabled. Enable it in the api-model-relations config file.');
        }

        // Get recent API requests from cache
        $requests = Cache::get('api_model_relations_debug_requests', []);
        
        // Find the specific request
        $request = collect($requests)->firstWhere('id', $id);
        
        if (!$request) {
            abort(404, 'API request not found');
        }
        
        return view('api-model-relations::debug.show', [
            'request' => $request,
        ]);
    }

    /**
     * Clear all debug data.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function clear()
    {
        // Check if debugging is enabled
        if (!Config::get('api-model-relations.debug', false)) {
            abort(403, 'API debugging is disabled. Enable it in the api-model-relations config file.');
        }

        Cache::forget('api_model_relations_debug_requests');
        
        return redirect()->route('api-model-relations.debug.index')
            ->with('success', 'Debug data cleared successfully');
    }

    /**
     * Calculate statistics from the requests.
     *
     * @param array $requests
     * @return array
     */
    protected function getStats($requests)
    {
        $stats = [
            'total_requests' => count($requests),
            'avg_response_time' => 0,
            'success_rate' => 0,
            'endpoints' => [],
            'status_codes' => [],
        ];
        
        if (empty($requests)) {
            return $stats;
        }
        
        $totalTime = 0;
        $successCount = 0;
        
        foreach ($requests as $request) {
            // Calculate average response time
            $totalTime += $request['duration'] ?? 0;
            
            // Calculate success rate
            $statusCode = $request['status_code'] ?? 0;
            if ($statusCode >= 200 && $statusCode < 300) {
                $successCount++;
            }
            
            // Count endpoints
            $endpoint = $request['endpoint'] ?? 'unknown';
            if (!isset($stats['endpoints'][$endpoint])) {
                $stats['endpoints'][$endpoint] = 0;
            }
            $stats['endpoints'][$endpoint]++;
            
            // Count status codes
            $statusCodeKey = (string) $statusCode;
            if (!isset($stats['status_codes'][$statusCodeKey])) {
                $stats['status_codes'][$statusCodeKey] = 0;
            }
            $stats['status_codes'][$statusCodeKey]++;
        }
        
        $stats['avg_response_time'] = $totalTime / count($requests);
        $stats['success_rate'] = ($successCount / count($requests)) * 100;
        
        return $stats;
    }
}
