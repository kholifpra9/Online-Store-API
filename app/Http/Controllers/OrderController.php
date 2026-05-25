<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    /**
     * POST /api/orders
     *
     * Request body:
     * {
     *   "customer_name": "Budi Santoso",
     *   "customer_email": "budi@example.com",
     *   "items": [
     *     { "product_id": 2, "quantity": 1 }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        // Validate incoming payload.
        $validated = $request->validate([
            'customer_name'          => ['required', 'string', 'max:255'],
            'customer_email'         => ['required', 'email'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1'],
        ]);

        try {
            $order = $this->orderService->createOrder(
                customerData: [
                    'name'  => $validated['customer_name'],
                    'email' => $validated['customer_email'],
                ],
                items: $validated['items'],
            );

            return response()->json([
                'message' => 'Order created successfully.',
                'data'    => $this->formatOrder($order),
            ], 201);

        } catch (InsufficientStockException $e) {
            // A product ran out of stock — likely during a flash sale burst.
            return response()->json([
                'message' => $e->getMessage(),
                'error'   => 'INSUFFICIENT_STOCK',
            ], 409);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
                'error'   => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * GET /api/orders/{id}
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => $this->formatOrder($order->load('orderItems.product')),
        ]);
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id'             => $order->id,
            'customer_name'  => $order->customer_name,
            'customer_email' => $order->customer_email,
            'total_price'    => $order->total_price,
            'status'         => $order->status,
            'created_at'     => $order->created_at->toIso8601String(),
            'items'          => $order->orderItems->map(fn ($item) => [
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'quantity'     => $item->quantity,
                'unit_price'   => $item->unit_price,
                'subtotal'     => $item->unit_price * $item->quantity,
            ]),
        ];
    }
}