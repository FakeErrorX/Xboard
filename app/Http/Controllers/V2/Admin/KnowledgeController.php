<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\KnowledgeSave;
use App\Http\Requests\Admin\KnowledgeSort;
use App\Models\Knowledge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KnowledgeController extends Controller
{
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            $knowledge = Knowledge::find($request->input('id'))->toArray();
            if (!$knowledge)
                return $this->fail([400202, 'Knowledge does not exist']);
            return $this->success($knowledge);
        }
        $data = Knowledge::select(['title', 'id', 'updated_at', 'category', 'show'])
            ->orderBy('sort', 'ASC')
            ->get();
        return $this->success($data);
    }

    public function getCategory(Request $request)
    {
        return $this->success(array_keys(Knowledge::get()->groupBy('category')->toArray()));
    }

    public function save(KnowledgeSave $request)
    {
        $params = $request->validated();

        if (!$request->input('id')) {
            if (!Knowledge::create($params)) {
                return $this->fail([500, 'Create failed']);
            }
        } else {
            try {
                Knowledge::find($request->input('id'))->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500, 'Create failed']);
            }
        }

        return $this->success(true);
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => 'Knowledge ID cannot be empty'
        ]);
        $knowledge = Knowledge::find($request->input('id'));
        if (!$knowledge) {
            throw new ApiException('Knowledge does not exist');
        }
        $knowledge->show = !$knowledge->show;
        if (!$knowledge->save()) {
            throw new ApiException('Save failed');
        }

        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ], [
            'ids.required' => 'Invalid parameters',
            'ids.array' => 'Invalid parameters'
        ]);
        try {
            DB::beginTransaction();
            foreach ($request->input('ids') as $k => $v) {
                $knowledge = Knowledge::find($v);
                $knowledge->timestamps = false;
                $knowledge->update(['sort' => $k + 1]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new ApiException('Save failed');
        }
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => 'Knowledge ID cannot be empty'
        ]);
        $knowledge = Knowledge::find($request->input('id'));
        if (!$knowledge) {
            return $this->fail([400202, 'Knowledge does not exist']);
        }
        if (!$knowledge->delete()) {
            return $this->fail([500, 'Delete failed']);
        }

        return $this->success(true);
    }
}
