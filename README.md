# Online Store API

REST API for an online store with flash sale support and race condition handling.

Built with **Laravel 13** + **MySQL**.

---

## Setup

```bash
# 1. Clone & install dependencies
git clone <repo-url> && cd online-store
composer install

# 2. Configure environment
cp .env.example .env
# Edit .env: set DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 3. Generate app key
php artisan key:generate

# 4. Run migrations and seed
php artisan migrate --seed

# 5. Start server
php artisan serve
```

---

## Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/products` | List all products |
| GET | `/api/v1/products/{id}` | Get product detail |
| POST | `/api/v1/orders` | Create an order |
| GET | `/api/v1/orders/{id}` | Get order detail |

### POST /api/v1/orders

```json
{
  "customer_name": "Budi Santoso",
  "customer_email": "budi@example.com",
  "items": [
    { "product_id": 2, "quantity": 1 }
  ]
}
```

**Responses:**
- `201 Created` — order placed successfully
- `409 Conflict` — insufficient stock (during flash sale burst)
- `422 Unprocessable` — validation error

---

## Race Condition Handling

The system uses **database-level row locking** (`SELECT ... FOR UPDATE`) inside a transaction to prevent overselling during flash sales.

```
BEGIN TRANSACTION
  SELECT * FROM products WHERE id = ? FOR UPDATE  ← acquires row lock
  -- check inventory
  UPDATE products SET inventory_count = inventory_count - ?
  INSERT INTO orders ...
COMMIT  ← releases lock
```

Concurrent requests for the same product are serialised at the database level. `inventory_count` can never go below zero.

---

## Running the Race Condition Test

```bash
# Make sure the server is running and the DB is freshly seeded first
php artisan migrate:fresh --seed

# Run 50 concurrent requests against the flash sale product (ID 2, stock: 10)
php tests/RaceConditionTest.php http://localhost:8000 2 50
```

Expected output:
```
=== Flash Sale Race Condition Test ===
Initial stock:    10
Expected success: 10
Expected fail:    40

Results:
  201 Created:   10
  409 Conflict:  40
  Final stock:   0

  [✓] Successful orders:   expected 10, got 10
  [✓] Failed orders (409): expected 40, got 40
  [✓] Final inventory:     expected 0, got 0

✓ ALL TESTS PASSED — race condition handled correctly.
```