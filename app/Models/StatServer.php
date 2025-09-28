<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\StatServer
 *
 * @property int $id
 * @property int $server_id Server ID
 * @property int $u Upload traffic
 * @property int $d Download traffic
 * @property int $record_at Record time
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read int $value Total traffic value calculated by SUM(u + d), only available when query specifies
 */
class StatServer extends Model
{
    protected $table = 'v2_stat_server';
    protected $dateFormat = 'U';
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    public function server()
    {
        return $this->belongsTo(Server::class, 'server_id');
    }
}
