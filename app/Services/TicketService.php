<?php
namespace App\Services;


use App\Exceptions\ApiException;
use App\Jobs\SendEmailJob;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\Plugin\HookManager;

class TicketService
{
    public function reply($ticket, $message, $userId)
    {
        try {
            DB::beginTransaction();
            $ticketMessage = TicketMessage::create([
                'user_id' => $userId,
                'ticket_id' => $ticket->id,
                'message' => $message
            ]);
            if ($userId !== $ticket->user_id) {
                $ticket->reply_status = Ticket::STATUS_OPENING;
            } else {
                $ticket->reply_status = Ticket::STATUS_CLOSED;
            }
            if (!$ticketMessage || !$ticket->save()) {
                throw new \Exception();
            }
            DB::commit();
            return $ticketMessage;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    public function replyByAdmin($ticketId, $message, $userId): void
    {
        $ticket = Ticket::where('id', $ticketId)
            ->first();
        if (!$ticket) {
            throw new ApiException('Ticket does not exist');
        }
        $ticket->status = Ticket::STATUS_OPENING;
        try {
            DB::beginTransaction();
            $ticketMessage = TicketMessage::create([
                'user_id' => $userId,
                'ticket_id' => $ticket->id,
                'message' => $message
            ]);
            if ($userId !== $ticket->user_id) {
                $ticket->reply_status = Ticket::STATUS_OPENING;
            } else {
                $ticket->reply_status = Ticket::STATUS_CLOSED;
            }
            if (!$ticketMessage || !$ticket->save()) {
                throw new ApiException('Ticket reply failed');
            }
            DB::commit();
            HookManager::call('ticket.reply.admin.after', [$ticket, $ticketMessage]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        $this->sendEmailNotify($ticket, $ticketMessage);
    }

    public function createTicket($userId, $subject, $level, $message)
    {
        try {
            DB::beginTransaction();
            if (Ticket::where('status', 0)->where('user_id', $userId)->lockForUpdate()->first()) {
                DB::rollBack();
                throw new ApiException('Unclosed ticket exists');
            }
            $ticket = Ticket::create([
                'user_id' => $userId,
                'subject' => $subject,
                'level' => $level
            ]);
            if (!$ticket) {
                throw new ApiException('Ticket creation failed');
            }
            $ticketMessage = TicketMessage::create([
                'user_id' => $userId,
                'ticket_id' => $ticket->id,
                'message' => $message
            ]);
            if (!$ticketMessage) {
                DB::rollBack();
                throw new ApiException('Ticket message creation failed');
            }
            DB::commit();
            return $ticket;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // No repeat notification within half an hour
    private function sendEmailNotify(Ticket $ticket, TicketMessage $ticketMessage)
    {
        $user = User::find($ticket->user_id);
        $cacheKey = 'ticket_sendEmailNotify_' . $ticket->user_id;
        if (!Cache::get($cacheKey)) {
            Cache::put($cacheKey, 1, 1800);
            SendEmailJob::dispatch([
                'email' => $user->email,
                'subject' => 'Your ticket at ' . admin_setting('app_name', 'XBoard') . ' has been replied',
                'template_name' => 'notify',
                'template_value' => [
                    'name' => admin_setting('app_name', 'XBoard'),
                    'url' => admin_setting('app_url'),
                    'content' => "Subject: {$ticket->subject}\r\nReply: {$ticketMessage->message}"
                ]
            ]);
        }
    }
}
