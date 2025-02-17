<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grpchat extends Model
{
    use HasFactory;

    protected $fillable = ['chatname', 'members', 'quitmembers', 'admins', 'owner', 'status', 'announcement', 'read_timestamps', 'remove'];

    protected $casts = [
        'members' => 'array',
        'quitmembers' => 'array',
        'admins' => 'array', // Admins stored as an array
        'announcement' => 'string',
        'read_timestamps' => 'array',
        'remove' => 'array',
    ];

    public function messages()
    {
        return $this->hasMany(Grpmessage::class, 'grpchat_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(Grpmessage::class, 'grpchat_id')->latestOfMany();
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner');
    }
    
    public function settings()
    {
        return $this->hasOne(GrpChatSetting::class, 'grpchat_id');
    }

    // Retrieve admin users
    public function getAdmins()
    {
        return User::whereIn('id', $this->admins ?? [])->get();
    }

    // Check if a user is an admin
    public function isAdmin($userId)
    {
        return in_array($userId, $this->admins ?? []);
    }

    // Add an admin
    public function addAdmin($userId)
    {
        $admins = $this->admins ?? [];
        if (!in_array($userId, $admins)) {
            $admins[] = $userId;
            $this->admins = $admins;
            $this->save();
        }
    }

    // Remove an admin
    public function removeAdmin($userId)
    {
        $admins = $this->admins ?? [];
        if (($key = array_search($userId, $admins)) !== false) {
            unset($admins[$key]);
            $this->admins = array_values($admins); // Reindex array
            $this->save();
        }
    }
    
    public function removeForUser(int $userId): void
    {
        $remove = $this->remove ?? [];
    
        $remove[(string)$userId] = now()->toDateTimeString();
    
        $this->remove = $remove;
        $this->save();
    }
}
