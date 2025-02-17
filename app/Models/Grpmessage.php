<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grpmessage extends Model
{
    use HasFactory;

    // Explicitly specify the table name if it doesn't follow Laravel's naming convention
    protected $table = 'grpmessage';

    protected $fillable = ['grpchat_id', 'user_id', 'message', 'image_url', 'audio_url', 'doc_url', 'status', 'reply_to_id', 'video_url'];

    public function grpchat()
    {
        return $this->belongsTo(Grpchat::class, 'grpchat_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    // Parent message
    public function replyTo()
    {
        return $this->belongsTo(Grpmessage::class, 'reply_to_id');
    }

    // Replies to this message
    public function replies()
    {
        return $this->hasMany(Grpmessage::class, 'reply_to_id');
    }
    
    public function taggedUsers()
    {
        return $this->belongsToMany(User::class, 'grpmessage_user_tags', 'grpmessage_id', 'user_id')->withTimestamps();
    }
}