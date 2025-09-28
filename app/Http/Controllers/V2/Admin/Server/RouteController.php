<?php

namespace App\Http\Controllers\V2\Admin\Server;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ServerRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RouteController extends Controller
{
    public function fetch(Request $request)
    {
        $routes = ServerRoute::get();
        return [
            'data' => $routes
        ];
    }

    public function save(Request $request)
    {
        $params = $request->validate([
            'remarks' => 'required',
            'match' => 'required|array',
            'action' => 'required|in:block,dns',
            'action_value' => 'nullable'
        ], [
            'remarks.required' => 'Remarks cannot be empty',
            'match.required' => 'Match value cannot be empty',
            'action.required' => 'Action type cannot be empty',
            'action.in' => 'Action type parameter is invalid'
        ]);
        $params['match'] = array_filter($params['match']);
        // TODO: remove on 1.8.0
        if ($request->input('id')) {
            try {
                $route = ServerRoute::find($request->input('id'));
                $route->update($params);
                return $this->success(true);
            } catch (\Exception $e) {
                Log::error($e);
                return $this->fail([500,'Save failed']);
            }
        }
        try{
            ServerRoute::create($params);
            return $this->success(true);
        }catch(\Exception $e){
            Log::error($e);
            return $this->fail([500,'Create failed']);
        }
    }

    public function drop(Request $request)
    {
        $route = ServerRoute::find($request->input('id'));
        if (!$route) throw new ApiException('Route does not exist');
        if (!$route->delete()) throw new ApiException('Delete failed');
        return [
            'data' => true
        ];
    }
}
