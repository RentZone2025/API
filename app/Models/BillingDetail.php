<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingDetail extends Model
{
    protected $table = "billing_details";
    public $timestamps = true;

        /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'city',
        'postal_code',
        'address',
        'country',
    ];
}
