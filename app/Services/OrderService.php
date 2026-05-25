<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Creates an order for one or more products.
     *
     * Race condition safety:
     *   Each product row is locked with SELECT ... FOR UPDATE inside a database
     *   transaction. Concurrent requests for the same product are serialised at
     *   the database level — only one transaction may hold the lock at a time.
     *   This guarantees inventory_count never goes below zero.
     *
     * @param  array  $customerData  ['name' => ..., 'email' => ...]
     * @param  array  $items         [['product_id' => 1, 'quantity' => 2], ...]
     * @return Order
     *
     * @throws InsufficientStockException
     * @throws \Throwable
     */
    public function createOrder(array $customerData, array $items): Order
    {
        return DB::transaction(function () use ($customerData, $items) {

            $orderLines = [];
            $totalPrice = 0;

            foreach ($items as $item) {
                $productId = $item['product_id'];
                $quantity  = $item['quantity'];

                // Lock the product row for the duration of this transaction.
                // Any other transaction attempting to lock the same row will
                // block here until we COMMIT or ROLLBACK.
                $product = Product::where('id', $productId)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Validate stock AFTER acquiring the lock so we see the true
                // current value, not a stale snapshot from before another
                // concurrent order committed.
                if ($product->inventory_count < $quantity) {
                    throw new InsufficientStockException(
                        $product->name,
                        $quantity,
                        $product->inventory_count
                    );
                }

                // Atomically decrement inventory.
                $product->decrement('inventory_count', $quantity);

                $unitPrice    = $product->getEffectivePrice();
                $totalPrice  += $unitPrice * $quantity;

                $orderLines[] = [
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }

            // Persist the order header.
            $order = Order::create([
                'customer_name'  => $customerData['name'],
                'customer_email' => $customerData['email'],
                'total_price'    => $totalPrice,
                'status'         => 'confirmed',
            ]);

            // Persist all line items in one query.
            $order->orderItems()->createMany($orderLines);

            return $order->load('orderItems.product');
        });
    }
}