<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = ['user_id', 'subject', 'status',];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }
    
    public function latestMessage()
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany();
    }
}
