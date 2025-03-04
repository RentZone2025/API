<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\URL;

use Laravel\Cashier\Billable;

use App\Notifications\CustomResetPasswordNotification;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, Billable;

    protected $table = "users";

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'fullname',
        'email',
        'phone_number',
        'birthdate',
        'password',
        'two_factor_secret',
        'last_login',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function rents()
    {
        return $this->hasMany(Rent::class);
    }

    public function shipping()
    {
        return $this->hasOne(ShippingDetail::class);
    }

    public function billing()
    {
        return $this->hasOne(BillingDetail::class);
    }

    public function sendPasswordResetNotification($token)
    {
        $userId = $this->id; // vagy bármilyen egyedi azonosító
        $encodedUserId = base64_encode($userId);
        $url = "http://localhost:4200/reset-password?token={$token}&id={$encodedUserId}";
        $this->notify(new CustomResetPasswordNotification($url));
    } 
    
    public function sendEmailVerificationNotification()
    {
        $verificationUrl = URL::temporarySignedRoute(
            'verifyEmail',
            now()->addMinutes(60),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())]
        );
    
        $parsedUrl = parse_url($verificationUrl);
        $query = [];
        parse_str($parsedUrl['query'] ?? '', $query);
    
        $frontendUrl = config('app.frontend_url') . '/email-verification/verify?' . http_build_query([
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
            'expires' => $query['expires'],
            'signature' => $query['signature'],
        ]);
    
        $this->notify(new VerifyEmailNotification($frontendUrl));
    }
    
}
