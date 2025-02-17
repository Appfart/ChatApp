<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'target',
        'read_timestamps',
        'remove',
    ];

    protected $casts = [
        'read_timestamps' => 'array',
        'remove' => 'array',
    ];

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'name');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target');
    }
    
    public function getOtherUserId($userId)
    {
        if ($this->name == $userId) {
            return $this->target;
        } elseif ($this->target == $userId) {
            return $this->name;
        }
        return null; // Handle as per your application's logic
    }
    
    public function removeForUser(int $userId): void
    {
        $remove = $this->remove ?? [];
    
        $remove[(string)$userId] = now()->toDateTimeString();
    
        $this->remove = $remove;
        $this->save();
    }
}