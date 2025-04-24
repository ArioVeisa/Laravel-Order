<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderController extends Controller
{
    public function index()
    {
        try {
            $orders = Order::with('products')->get();
            return response()->json(['success' => true, 'data' => $orders], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch orders'], 500);
        }
    }

    public function show($id)
    {
        try {
            $order = Order::with('products')->findOrFail($id);
            return response()->json(['success' => true, 'data' => $order], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch order'], 500);
        }
    }

    public function store(Request $request)
{
    $data = $request->validate([
        'products' => 'required|array',
        'products.*.id' => 'required|integer',
        'products.*.quantity' => 'required|integer|min:1'
    ]);

    try {
        $userData = $request->get('user_data');

        if (!isset($userData['id'])) {
            return response()->json(['success' => false, 'message' => 'User ID not found in token data'], 401);
        }

        $userId = $userData['id'];

        // Validasi produk yang dipesan
        $invalidProducts = [];
        foreach ($data['products'] as $item) {
            $product = Product::find($item['id']);
            if (!$product) {
                $invalidProducts[] = $item['id']; // Menyimpan ID produk yang tidak ditemukan
            }
        }

        if (count($invalidProducts) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Some products were not found',
                'invalid_products' => $invalidProducts
            ], 404);
        }

        // Jika semua produk valid, lanjutkan proses order
        $order = Order::create(['user_id' => $userId]);

        foreach ($data['products'] as $item) {
            $order->products()->attach($item['id'], ['quantity' => $item['quantity']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load('products')
        ], 201);

    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => 'Failed to create order', 'error' => $e->getMessage()], 500);
    }
}


    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Pending,Shipped,Delivered,Cancelled'
        ]);

        try {
            $order = Order::findOrFail($id);
            $order->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $order
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update order'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $order = Order::findOrFail($id);
            $order->delete();

            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully'
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete order'], 500);
        }
    }
}
