<?php

namespace App\Exceptions;

use Exception;


//  Thrown when a product does not have enough inventory to fulfill an order.
//  Results in a 409 Conflict response.

class InsufficientStockException extends Exception
{
    public function __construct(string $productName, int $requested, int $available)
    {
        parent::__construct(
            "Insufficient stock for \"{$productName}\": requested {$requested}, available {$available}."
        );
    }
}