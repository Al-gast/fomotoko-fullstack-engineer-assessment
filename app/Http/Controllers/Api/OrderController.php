<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        /*
         * Jika produk yang sama dikirim lebih dari sekali,
         * quantity-nya digabung agar pengecekan stok tetap rapi.
         */
        $items = collect($validated['items'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->sum('quantity'))
            ->sortKeys();

        $order = DB::transaction(function () use ($items) {
            $order = Order::create([
                'total_price' => 0,
                'status' => 'created',
            ]);

            $totalPrice = 0;

            foreach ($items as $productId => $quantity) {
                /*
                 * Lock baris produk saat dicek.
                 * Ini mencegah request flash sale lain membaca stok yang sama.
                 */
                $product = Product::where('id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Product not found',
                    ], 404));
                }

                if ($product->stock < $quantity) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Insufficient stock',
                        'errors' => [
                            'product_id' => $product->id,
                            'requested_quantity' => $quantity,
                            'available_stock' => $product->stock,
                        ],
                    ], 409));
                }

                $unitPrice = $product->effective_price;
                $subtotal = $unitPrice * $quantity;

                /*
                 * Stok dikurangi setelah row lock aktif dan stok terbukti cukup.
                 * Constraint database juga tetap menjaga stok tidak bisa minus.
                 */
                $product->decrement('stock', $quantity);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $totalPrice += $subtotal;
            }

            $order->update([
                'total_price' => $totalPrice,
            ]);

            return $order->load('items.product');
        });

        return response()->json([
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'message' => 'Order retrieved successfully',
            'data' => $order->load('items.product'),
        ]);
    }
}