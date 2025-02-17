<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrpChatSetting extends Model
{
    use HasFactory;

    protected $table = 'grpchatsettings'; // Explicitly define the table name

    protected $fillable = [
        'grpchat_id',
        'add_friend',
        'hide_members',
        'hide_allmembers',
        'allow_invite',
        'allow_qrinvite',
        'kyc',
        'block_quit',
        'mute_chat',
        'mute_members',
    ];

    protected $casts = [
        'add_friend' => 'boolean',
        'hide_members' => 'boolean',
        'hide_allmembers' => 'boolean',
        'allow_invite' => 'boolean',
        'allow_qrinvite' => 'boolean',
        'kyc' => 'boolean',
        'block_quit' => 'boolean',
        'mute_chat' => 'boolean',
        'mute_members' => 'array',
    ];
}
