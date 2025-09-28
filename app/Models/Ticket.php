<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * App\Models\Ticket
 *
 * @property int $id
 * @property int $user_id User ID
 * @property string $subject Ticket subject
 * @property string|null $level Ticket level
 * @property int $status Ticket status
 * @property int|null $reply_status Reply status
 * @property int|null $last_reply_user_id Last replier
 * @property int $created_at
 * @property int $updated_at
 * 
 * @property-read User $user Associated user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TicketMessage> $messages Associated ticket messages
 */
class Ticket extends Model
{
    protected $table = 'v2_ticket';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    const STATUS_OPENING = 0;
    const STATUS_CLOSED = 1;
    public static $statusMap = [
        self::STATUS_OPENING => 'Open',
        self::STATUS_CLOSED => 'Closed'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    
    /**
     * Associated ticket messages
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id', 'id');
    }
    
    // To be deleted
    public function message(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id', 'id');
    }
}
