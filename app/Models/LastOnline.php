<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LastOnline extends Model
{
    protected $table = 'last_online';

    protected $fillable = [
        'user_id',
        'ip_address',
        'updated_at',
    ];

    /**
     * Get the user that owns the last online record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
