<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderController extends Controller
{
    private $productServiceUrl;

    public function __construct()
    {
        $this->productServiceUrl = env('PRODUCT_SERVICE_URL', 'http://ProductService:9000');
    }

    public function index()
    {
        try {
            $orders = Order::with('orderProducts')->get();
            return response()->json(['success' => true, 'data' => $orders], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Failed to fetch orders', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $order = Order::with('orderProducts')->findOrFail($id);
            return response()->json(['success' => true, 'data' => $order], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            Log::error('Error fetching order: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
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
            
            // Debug log untuk melihat data user
            Log::info('Creating order with user data:', [
                'user_data' => $userData,
                'request_data' => $data,
                'headers' => $request->headers->all()
            ]);

            if (!isset($userData['id'])) {
                Log::error('User ID not found in token data', [
                    'user_data' => $userData,
                    'request_data' => $data
                ]);
                return response()->json(['success' => false, 'message' => 'User ID not found in token data'], 401);
            }

            $userId = $userData['id'];

            // Validasi produk yang dipesan dari product-service
            $productIds = collect($data['products'])->pluck('id')->toArray();
            
            // Ambil token dari request
            $token = $request->bearerToken();
            
            // Debug log untuk request ke product-service
            Log::info('Requesting products from product-service:', [
                'product_ids' => $productIds,
                'url' => $this->productServiceUrl . '/api/products',
                'params' => ['ids' => $productIds]
            ]);

            $productsResponse = Http::withToken($token)
                ->get($this->productServiceUrl . '/api/products', [
                    'ids' => $productIds
                ]);

            // Debug log untuk response dari product-service
            Log::info('Product service response:', [
                'status' => $productsResponse->status(),
                'body' => $productsResponse->json(),
                'headers' => $productsResponse->headers()
            ]);

            if (!$productsResponse->successful()) {
                Log::error('Failed to validate products from product-service', [
                    'product_ids' => $productIds,
                    'response' => $productsResponse->json(),
                    'status' => $productsResponse->status()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to validate products',
                    'error' => $productsResponse->json()
                ], 400);
            }

            $availableProducts = collect($productsResponse->json()['data'] ?? [])->pluck('id')->toArray();
            $invalidProducts = array_diff($productIds, $availableProducts);

            if (count($invalidProducts) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some products were not found',
                    'invalid_products' => $invalidProducts
                ], 404);
            }

            // Buat order
            $order = Order::create(['user_id' => $userId]);

            // Simpan order products
            foreach ($data['products'] as $item) {
                OrderProduct::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'quantity' => $item['quantity']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order->load('orderProducts')
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Error creating order: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
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
