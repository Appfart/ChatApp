<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Luck extends Model
{
    use HasFactory;

    protected $fillable = [
        'txid', 'owner', 'amount', 'status', 'claimed',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner');
    }
}
