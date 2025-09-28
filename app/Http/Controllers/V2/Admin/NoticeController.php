<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NoticeSave;
use App\Models\Notice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticeController extends Controller
{
    public function fetch(Request $request)
    {
        return $this->success(
            Notice::orderBy('sort', 'ASC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }

    public function save(NoticeSave $request)
    {
        $data = $request->only([
            'title',
            'content',
            'img_url',
            'tags',
            'show',
            'popup'
        ]);
        if (!$request->input('id')) {
            if (!Notice::create($data)) {
                return $this->fail([500, 'Save failed']);
            }
        } else {
            try {
                Notice::find($request->input('id'))->update($data);
            } catch (\Exception $e) {
                return $this->fail([500, 'Save failed']);
            }
        }
        return $this->success(true);
    }



    public function show(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([500, 'Notice ID cannot be empty']);
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            return $this->fail([400202, 'Notice does not exist']);
        }
        $notice->show = $notice->show ? 0 : 1;
        if (!$notice->save()) {
            return $this->fail([500, 'Save failed']);
        }

        return $this->success(true);
    }

    public function drop(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([422, 'Notice ID cannot be empty']);
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            return $this->fail([400202, 'Notice does not exist']);
        }
        if (!$notice->delete()) {
            return $this->fail([500, 'Delete failed']);
        }
        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $params = $request->validate([
            'ids' => 'required|array'
        ]);

        try {
            DB::beginTransaction();
            foreach ($params['ids'] as $k => $v) {
                $notice = Notice::findOrFail($v);
                $notice->update(['sort' => $k + 1]);
            }
            DB::commit();
            return $this->success(true);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e);
            return $this->fail([500, 'Sort save failed']);
        }
    }
}
