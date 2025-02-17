<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'security_pin',
        'referral',
        'referral_link',
        'birthday',
        'realname',
        'age',
        'robot',
        'role',
        'avatar',
        'realpass',
        'qr_link',
        'error'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    
    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'name', 'name');
    }
    
    public function getAvatarUrlAttribute()
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }
    
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }
    
    public function supportedRobots()
    {
        return $this->hasMany(RobotLink::class, 'support_id');
    }
    
    public function assignedSupport()
    {
        return $this->hasMany(RobotLink::class, 'robot_id');
    }
    
    public function userbanks()
    {
        return $this->hasMany(Userbank::class);
    }
    
    public function routeNotificationForFcm()
    {
        return $this->firebaseTokens->pluck('token')->toArray();
    }
    
    public function firebaseTokens()
    {
        return $this->hasMany(FirebaseToken::class);
    }
    
    protected static function booted()
    {
        static::saving(function ($user) {
            if ($user->isDirty('referral_link')) {
                // Check if referral_link is not null or empty
                if (!empty($user->referral_link)) {
                    try {
                        // Ensure the directory exists
                        $qrDirectory = storage_path('app/public/qr'); // Path to the QR code directory
                        if (!is_dir($qrDirectory)) {
                            mkdir($qrDirectory, 0755, true); // Create the directory if it doesn't exist
                        }
    
                        // Generate the QR code
                        $qrImagePath = 'qr/' . $user->id . '_qr.png'; // Path relative to the "public" disk
                        $qrFullPath = storage_path('app/public/' . $qrImagePath); // Absolute path
    
                        \QrCode::format('png')
                            ->size(300)
                            ->generate($user->referral_link, $qrFullPath);
    
                        // Update the user's QR link to match the expected format
                        $user->qr_link = 'storage/' . $qrImagePath;
                    } catch (\Exception $e) {
                        \Log::error('QR Code generation failed', ['error' => $e->getMessage()]);
                        $user->qr_link = null; // Reset the QR path if there's an error
                    }
                } else {
                    // If referral_link is null or empty, set qr_link to null
                    $user->qr_link = null;
                }
            }
        });
    }



}
