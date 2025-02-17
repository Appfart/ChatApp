<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RobotLink extends Model
{
    use HasFactory;

    // Specify the table name
    protected $table = 'robotlinks';

    protected $fillable = [
        'support_id',
        'robot_id',
        'status',
    ];

    public function support()
    {
        return $this->belongsTo(User::class, 'support_id');
    }

    public function robot()
    {
        return $this->belongsTo(User::class, 'robot_id');
    }
}
