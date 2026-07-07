# Fomotoko Fullstack Engineer Assessment

Laravel solution for the Fomotoko Fullstack Engineer Assessment.

This repository contains two parts:

1. Online Store API
2. Hidden Item CLI Program

## Tech Stack

- PHP
- Laravel
- PostgreSQL
- Supabase PostgreSQL
- Guzzle HTTP Client for the race condition test

## Public API

Public API URL:

```txt
TBD after deployment
```

After deployment, replace the value above with the live API URL.

## Task 1: Online Store API

The Online Store API covers product listing, product creation, order creation, and order listing.

Main business rules:

- An order must contain at least one order item.
- Product stock must never become negative.
- The order creation flow must be safe during concurrent flash sale requests.

## Race Condition Handling

The order creation process uses a database transaction and row-level locking.

When an order is created, the related product row is locked before stock is checked and reduced:

```php
Product::where('id', $productId)
    ->lockForUpdate()
    ->first();
```

This prevents multiple concurrent requests from reading and reducing the same stock at the same time.

The database also has `CHECK` constraints to prevent negative stock at the database level.

## API Endpoints

### Products

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/products` | Get all products |
| POST | `/api/products` | Create a product |
| GET | `/api/products/{product}` | Get product detail |

### Orders

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/orders` | Get paginated order list |
| POST | `/api/orders` | Create an order |
| GET | `/api/orders/{order}` | Get order detail |

## Example: Create Product

```bash
curl -X POST http://127.0.0.1:8000/api/products \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Flash Sale Product",
    "price": 100000,
    "sale_price": 25000,
    "stock": 10
  }'
```

Expected status code:

```txt
201 Created
```

## Example: Create Order

```bash
curl -X POST http://127.0.0.1:8000/api/orders \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "items": [
      {
        "product_id": 1,
        "quantity": 1
      }
    ]
  }'
```

Expected status code:

```txt
201 Created
```

Example response:

```json
{
  "message": "Order created successfully",
  "data": {
    "id": 1,
    "total_price": 25000,
    "status": "created",
    "items": [
      {
        "product_id": 1,
        "quantity": 1,
        "unit_price": 25000,
        "subtotal": 25000
      }
    ]
  }
}
```

## Example: Insufficient Stock

If the requested quantity is higher than the available stock, the API returns:

```txt
409 Conflict
```

Example response:

```json
{
  "message": "Insufficient stock",
  "errors": {
    "product_id": 1,
    "requested_quantity": 1,
    "available_stock": 0
  }
}
```

## Race Condition Test

This project includes a command-line functional test that sends many order requests at the same time.

Start the local API server first:

```bash
php artisan serve
```

Then run the test:

```bash
php artisan test:race-condition --requests=50 --stock=10 --quantity=1 --concurrency=5
```

Expected result:

```txt
201 Created        : 10
409 Stock Conflict : 40
Failed requests    : 0
Created order item : 10
Final stock        : 0

PASSED: API berhasil mencegah stok menjadi negatif saat request bersamaan.
```

This means only 10 orders are created from 50 concurrent requests because the initial stock is 10.

## Task 2: Hidden Item CLI Program

The Hidden Item program solves a simple grid-based movement problem.

Grid symbols:

| Symbol | Meaning |
|---|---|
| `#` | Obstacle |
| `.` | Clear path |
| `X` | Player starting position |
| `$` | Probable hidden item location |

Movement order:

```txt
North A step(s)
East B step(s)
South C step(s)
```

### Run with All Possible Steps

The assessment does not provide exact values for A, B, and C, so the default mode tries all valid combinations.

```bash
php artisan hidden-item:solve
```

Example output:

```txt
Probable item locations:

+-----+--------+
| Row | Column |
+-----+--------+
| 3   | 6      |
| 3   | 7      |
| 4   | 6      |
| 5   | 4      |
| 5   | 6      |
+-----+--------+
```

The command also displays the grid with probable item locations marked using `$`.

### Run with Exact Steps

```bash
php artisan hidden-item:solve --up=1 --right=2 --down=1
```

Example output:

```txt
Probable item locations:

+-----+--------+
| Row | Column |
+-----+--------+
| 5   | 4      |
+-----+--------+
```

## Installation

Clone the repository:

```bash
git clone https://github.com/Al-gast/fomotoko-fullstack-engineer-assessment.git
cd fomotoko-fullstack-engineer-assessment
```

Install dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure the PostgreSQL database connection in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=
DB_PASSWORD=
DB_SSLMODE=require
```

Run migrations:

```bash
php artisan migrate
```

Seed sample products:

```bash
php artisan db:seed
```

Start the local server:

```bash
php artisan serve
```

Local API base URL:

```txt
http://127.0.0.1:8000
```

## Useful Commands

List registered routes:

```bash
php artisan route:list
```

Run the race condition test:

```bash
php artisan test:race-condition --requests=50 --stock=10 --quantity=1 --concurrency=5
```

Run the Hidden Item solver:

```bash
php artisan hidden-item:solve
```

Run the Hidden Item solver with exact steps:

```bash
php artisan hidden-item:solve --up=1 --right=2 --down=1
```

## Notes

- Supabase is used only as a PostgreSQL database provider.
- API logic, validation, transaction handling, and race condition protection are implemented in Laravel.
- `.env` is ignored and should not be committed.
