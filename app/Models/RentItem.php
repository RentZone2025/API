<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentItem extends Model
{

    use HasFactory;

    protected $table = "rent_items";

    public $timestamps = true;

    protected $fillable = [
        'rent_id',
        'item_id',
        'price',
        'amount',
        'type'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function rent()
    {
        return $this->belongsTo(Rent::class, 'rent_id');
    }
}
