<?php
// app/Http/Controllers/Api/LogController.php

namespace App\Http\Controllers\Api;

use App\Models\Log;
use App\Http\Resources\LogResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log as LogFacade;

class LogController extends BaseController
{
    
    public static function addLog(
        $userId,
        $actionType,
        $actionName,
        $module,
        $entityId = null,
        $oldData = null,
        $newData = null,
        $ipAddress = null,
        $userAgent = null,
        $status = 'success',
        $message = null,
        $errorCode = null,
        $macAddress = null        
    ) {
        try {
            return Log::create([
                'user_id' => $userId,
                'action_type' => $actionType,
                'action_name' => $actionName,
                'module' => $module,
                'entity_type' => null,
                'entity_id' => $entityId,
                'old_data' => $oldData ? (is_array($oldData) ? json_encode($oldData) : $oldData) : null,
                'new_data' => $newData ? (is_array($newData) ? json_encode($newData) : $newData) : null,
                'ip_address' => $ipAddress,
                'mac_address' => $macAddress,  
                'user_agent' => $userAgent,
                'status' => $status,
                'message' => $message,
                'error_code' => $errorCode,
            ]);
        } catch (\Exception $e) {
         
            LogFacade::error('Failed to create log: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Display a listing of logs (Admin only)
     */
    public function index(Request $request)
    {
        $query = Log::with('user');

        
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

   
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('entity_type') && $request->filled('entity_id')) {
            $query->where('entity_type', $request->entity_type)
                  ->where('entity_id', $request->entity_id);
        }

        
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        
        if ($request->filled('search')) {
            $query->where('message', 'LIKE', "%{$request->search}%");
        }

  
        $sortField = in_array($request->get('sort_by', 'created_at'), ['created_at', 'log_id', 'action_type']) 
            ? $request->get('sort_by', 'created_at') 
            : 'created_at';
        $sortOrder = in_array(strtolower($request->get('sort_order', 'desc')), ['asc', 'desc']) 
            ? strtolower($request->get('sort_order', 'desc')) 
            : 'desc';
        
        $query->orderBy($sortField, $sortOrder);

        $logs = $query->paginate($request->get('per_page', 50));

        return $this->sendPaginated(
            $logs,
            LogResource::collection($logs),
            'Logs retrieved successfully'
        );
    }

    /**
     * Display the specified log (Admin only)
     */
    public function show($id)
    {
        $log = Log::with('user')->find($id);

        if (!$log) {
            return $this->sendError('Log not found', [], 404);
        }

        return $this->sendResponse(
            new LogResource($log),
            'Log retrieved successfully'
        );
    }

    /**
     * Get logs for a specific entity
     */
    public function entityLogs(Request $request, $entityType, $entityId)
    {
        $logs = Log::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('user')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return $this->sendPaginated(
            $logs,
            LogResource::collection($logs),
            'Entity logs retrieved successfully'
        );
    }

    /**
     * Get user activity logs
     */
    public function userLogs(Request $request, $userId)
    {
        $logs = Log::where('user_id', $userId)
            ->with('user')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return $this->sendPaginated(
            $logs,
            LogResource::collection($logs),
            'User activity logs retrieved successfully'
        );
    }

    /**
     * Get statistics about logs
     */
    public function stats(Request $request)
    {
        $stats = [
            'total' => Log::count(),
            'by_status' => Log::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'by_module' => Log::selectRaw('module, COUNT(*) as count')
                ->groupBy('module')
                ->pluck('count', 'module'),
            'by_action_type' => Log::selectRaw('action_type, COUNT(*) as count')
                ->groupBy('action_type')
                ->pluck('count', 'action_type'),
            'failed_count' => Log::where('status', 'failed')->count(),
            'today_count' => Log::whereDate('created_at', today())->count(),
        ];

        return $this->sendResponse($stats, 'Log statistics retrieved successfully');
    }
}