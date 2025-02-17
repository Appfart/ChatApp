<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'txid', 'user_id', 'amount', 'currency', 'status', 
        'bankname', 'accname', 'accno', 'branch', 'iban', 
        'gateway', 'method'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
