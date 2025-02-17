<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdrawal extends Model
{
    use HasFactory;

    protected $fillable = [
        'txid', 'user_id', 'amount', 'currency', 
        'status', 'gateway', 'method', 'bankname', 'accname', 'accno', 'branch'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
