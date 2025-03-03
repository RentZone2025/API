<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rent extends Model
{

    use SoftDeletes;

    protected $table = "rents";

    public $timestamps = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'device_id',
        'rent_period',
        'sum_price',
        'sale',
        'is_free_shipping',
        'is_free_deposit'
    ];

    public function rentItems()
    {
        return $this->hasMany(RentItem::class, 'rent_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($rent) {
            // Calculate back_date based on delivery_date and rent_period
            if ($rent->isDirty('delivery_date')) {
                $rent->back_date = $rent->delivery_date ? 
                    \Carbon\Carbon::parse($rent->delivery_date)->addDays($rent->rent_period) : null;
            }
        });
    }
}
