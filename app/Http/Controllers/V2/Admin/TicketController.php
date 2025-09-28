<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    private function applyFiltersAndSorts(Request $request, $builder)
    {
        if ($request->has('filter')) {
            collect($request->input('filter'))->each(function ($filter) use ($builder) {
                $key = $filter['id'];
                $value = $filter['value'];
                $builder->where(function ($query) use ($key, $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, 'like', "%{$value}%");
                    }
                });
            });
        }

        if ($request->has('sort')) {
            collect($request->input('sort'))->each(function ($sort) use ($builder) {
                $key = $sort['id'];
                $value = $sort['desc'] ? 'DESC' : 'ASC';
                $builder->orderBy($key, $value);
            });
        }
    }
    public function fetch(Request $request)
    {
        if ($request->input('id')) {
            return $this->fetchTicketById($request);
        } else {
            return $this->fetchTickets($request);
        }
    }

    /**
     * Summary of fetchTicketById
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function fetchTicketById(Request $request)
    {
        $ticket = Ticket::with('messages', 'user')->find($request->input('id'));

        if (!$ticket) {
            return $this->fail([400202, 'Ticket does not exist']);
        }
        $result = $ticket->toArray();
        $result['user'] = UserController::transformUserData($ticket->user);

        return $this->success($result);
    }

    /**
     * Summary of fetchTickets
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    private function fetchTickets(Request $request)
    {
        $ticketModel = Ticket::with('user')
            ->when($request->has('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            })
            ->when($request->has('reply_status'), function ($query) use ($request) {
                $query->whereIn('reply_status', $request->input('reply_status'));
            })
            ->when($request->has('email'), function ($query) use ($request) {
                $query->whereHas('user', function ($q) use ($request) {
                    $q->where('email', $request->input('email'));
                });
            });

        $this->applyFiltersAndSorts($request, $ticketModel);
        $tickets = $ticketModel
            ->latest('updated_at')
            ->paginate(
                perPage: $request->integer('pageSize', 10),
                page: $request->integer('current', 1)
            );

        // Get items then map and transform
        $items = collect($tickets->items())->map(function ($ticket) {
            $ticketData = $ticket->toArray();
            $ticketData['user'] = UserController::transformUserData($ticket->user);
            return $ticketData;
        })->all();

        return response([
            'data' => $items,
            'total' => $tickets->total()
        ]);
    }

    public function reply(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric',
            'message' => 'required|string'
        ], [
            'id.required' => 'Ticket ID cannot be empty',
            'message.required' => 'Message cannot be empty'
        ]);
        $ticketService = new TicketService();
        $ticketService->replyByAdmin(
            $request->input('id'),
            $request->input('message'),
            $request->user()->id
        );
        return $this->success(true);
    }

    public function close(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => 'Ticket ID cannot be empty'
        ]);
        try {
            $ticket = Ticket::findOrFail($request->input('id'));
            $ticket->status = Ticket::STATUS_CLOSED;
            $ticket->save();
            return $this->success(true);
        } catch (ModelNotFoundException $e) {
            return $this->fail([400202, 'Ticket does not exist']);
        } catch (\Exception $e) {
            return $this->fail([500101, 'Close failed']);
        }
    }

    public function show($ticketId)
    {
        $ticket = Ticket::with([
            'user',
            'messages' => function ($query) {
                $query->with(['user']); // If user information is needed
            }
        ])->findOrFail($ticketId);

        // Automatically include is_me attribute
        return response()->json([
            'data' => $ticket
        ]);
    }
}
