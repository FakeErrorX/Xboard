<?php

namespace App\Http\Controllers\V2\Admin\Server;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Server;
use App\Models\ServerGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    public function fetch(Request $request): JsonResponse
    {
        $serverGroups = ServerGroup::query()
            ->orderByDesc('id')
            ->withCount('users')
            ->get();

        // Only manually load server_count when needed
        $serverGroups->each(function ($group) {
            $group->setAttribute('server_count', $group->server_count);
        });

        return $this->success($serverGroups);
    }

    public function save(Request $request)
    {
        if (empty($request->input('name'))) {
            return $this->fail([422, 'Group name cannot be empty']);
        }

        if ($request->input('id')) {
            $serverGroup = ServerGroup::find($request->input('id'));
        } else {
            $serverGroup = new ServerGroup();
        }

        $serverGroup->name = $request->input('name');
        return $this->success($serverGroup->save());
    }

    public function drop(Request $request)
    {
        $groupId = $request->input('id');

        $serverGroup = ServerGroup::find($groupId);
        if (!$serverGroup) {
            return $this->fail([400202, 'Group does not exist']);
        }
        if (Server::whereJsonContains('group_ids', $groupId)->exists()) {
            return $this->fail([400, 'This group is being used by nodes and cannot be deleted']);
        }

        if (Plan::where('group_id', $groupId)->exists()) {
            return $this->fail([400, 'This group is being used by subscriptions and cannot be deleted']);
        }
        if (User::where('group_id', $groupId)->exists()) {
            return $this->fail([400, 'This group is being used by users and cannot be deleted']);
        }
        return $this->success($serverGroup->delete());
    }
}
