<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\TicketMessage
 *
 * @property int $id
 * @property int $ticket_id
 * @property int $user_id
 * @property string $message
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Ticket $ticket Associated ticket
 * @property-read bool $is_from_user Whether message was sent by ticket initiator
 * @property-read bool $is_from_admin Whether message was sent by admin
 */
class TicketMessage extends Model
{
    protected $table = 'v2_ticket_message';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    protected $appends = ['is_from_user', 'is_from_admin'];

    /**
     * Associated ticket
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'id');
    }

    /**
     * Check if message was sent by ticket initiator
     */
    public function getIsFromUserAttribute(): bool
    {
        return $this->ticket->user_id === $this->user_id;
    }

    /**
     * Check if message was sent by admin
     */
    public function getIsFromAdminAttribute(): bool
    {
        return $this->ticket->user_id !== $this->user_id;
    }
}
