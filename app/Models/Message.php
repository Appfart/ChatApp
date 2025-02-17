<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'message', 'image_url', 'audio_url', 'doc_url', 'reply_to_id', 'status', 'video_url'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // Parent message
    public function replyTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    // Replies to this message
    public function replies()
    {
        return $this->hasMany(Message::class, 'reply_to_id');
    }
    
    public function taggedUsers()
    {
        return $this->belongsToMany(User::class, 'message_user_tags', 'message_id', 'user_id')->withTimestamps();
    }
}
