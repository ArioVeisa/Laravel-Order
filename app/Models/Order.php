<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'status'];

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity');
    }
}

