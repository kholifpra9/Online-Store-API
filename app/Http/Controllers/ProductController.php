<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * Returns all products with their effective (flash sale) price.
     */
    public function index(): JsonResponse
    {
        $products = Product::all()->map(fn (Product $p) => $this->formatProduct($p));

        return response()->json(['data' => $products]);
    }

    /**
     * GET /api/products/{id}
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $this->formatProduct($product)]);
    }

    private function formatProduct(Product $product): array
    {
        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'description'      => $product->description,
            'price'            => $product->price,
            'inventory_count'  => $product->inventory_count,
            'is_flash_sale'    => $product->isFlashSaleActive(),
            'effective_price'  => $product->getEffectivePrice(),
            'flash_sale_ends_at' => $product->flash_sale_ends_at?->toIso8601String(),
        ];
    }
}