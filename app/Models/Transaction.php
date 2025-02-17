<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'txid', 'user_id', 'amount', 'currency', 
        'status', 'type', 'method'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
