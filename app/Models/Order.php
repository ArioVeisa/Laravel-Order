<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Http;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'status'];

    public function orderProducts()
    {
        return $this->hasMany(OrderProduct::class);
    }

    public function getProductsAttribute()
    {
        $productIds = $this->orderProducts()->pluck('product_id')->toArray();
        
        if (empty($productIds)) {
            return [];
        }

        $response = Http::get(env('PRODUCT_SERVICE_URL', 'http://ProductService:9000') . '/api/products', [
            'ids' => $productIds
        ]);

        if ($response->successful()) {
            return $response->json()['data'] ?? [];
        }

        return [];
    }
}

