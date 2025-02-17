<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description',
        'status',
        'ip_address',
        'user_agent',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime', // Cast logged_at to a Carbon instance
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
