<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::query()
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => 'Products retrieved successfully',
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Validasi dibuat ketat supaya data produk tetap aman dari awal.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lte:price'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'message' => 'Product retrieved successfully',
            'data' => $product,
        ]);
    }
}