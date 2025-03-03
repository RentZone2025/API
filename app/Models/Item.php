<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{

    use HasFactory;

    protected $table = "items";

    public $timestamps = true;

    protected $fillable = [
        'name',
        'description',
        'type',
        'cover_image',
        'price'
    ];

    public function rentItems()
    {
        return $this->hasMany(RentItem::class, 'item_id');
    }
}