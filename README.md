# 🍽️ Restaurant API

A REST API for running a restaurant: the menu, tables, and orders that move from the dining room to the kitchen and back to the cashier. Access is controlled by **role**: admin, waiter (`mesero`), kitchen (`cocina`) and customer (`cliente`).

Built with **Laravel 13 (MVC)**, **Sanctum**, **PostgreSQL** and **Eloquent**. It is documented with **OpenAPI / Swagger**, covered by **PHPUnit** tests, and deployed with **Docker** on Render, using Neon Postgres.

**▶ Live demo (Swagger UI):** https://restaurant-api-6rqh.onrender.com/api/documentation

> The demo runs on a free plan, so the first request after 15 minutes of inactivity can take about 1 minute while the server wakes up.

---

## Try it in 30 seconds

1. Open the [Swagger UI](https://restaurant-api-6rqh.onrender.com/api/documentation).
2. Run `POST /api/auth/login` with one of the demo users below and copy the `token`.
3. Click **Authorize**, paste the token, and call any endpoint.

| Role    | Email                     | Password   | What it can do                                  |
| ------- | ------------------------- | ---------- | ----------------------------------------------- |
| admin   | `admin@restaurante.test`  | `password` | Everything: menu, tables, orders                |
| mesero  | `mesero@restaurante.test` | `password` | Open orders, add dishes, deliver, charge, cancel |
| cocina  | `cocina@restaurante.test` | `password` | See orders, mark them in preparation / ready    |
| cliente | `cliente@restaurante.test`| `password` | Browse the menu                                 |

---

## Features

- **Token auth with Sanctum.** Register, login, logout (revokes only the current token) and `me`. Login and register are rate-limited to 6 requests per minute.
- **Role-based authorization** with Policies, so every rule lives in one place.
- **Menu.** CRUD for categories and dishes. The dish list supports filters (`category_id`, `available`, `search`) and pagination (`per_page`, between 1 and 100).
- **Tables.** Each table is `libre`, `ocupada` or `reservada`. A table cannot be changed while it has an active order.
- **Orders** with a status workflow, totals computed on the server, and table occupancy handled automatically.
- **One error format** across the whole API (see [Error contract](#error-contract)).
- **Interactive OpenAPI 3 documentation** generated from PHP attributes in the code.

## Business rules

Orders follow a state machine defined in the `OrderStatus` enum:

```mermaid
stateDiagram-v2
    [*] --> pendiente: mesero/admin opens order
    pendiente --> en_preparacion: cocina
    pendiente --> cancelado: mesero/admin
    en_preparacion --> listo: cocina
    en_preparacion --> cancelado: mesero/admin
    listo --> entregado: mesero/admin
    entregado --> pagado: mesero/admin
    pagado --> [*]
    cancelado --> [*]
```

- **The price is never sent by the client.** Each order line copies the dish's `unit_price` when it is added, so later menu price changes do not rewrite past orders. The total is recalculated in the database (`SUM(quantity * unit_price)`).
- **Only available dishes** can be ordered.
- Opening an order marks its table as **occupied**. Moving the order to `pagado` or `cancelado` **frees the table**.
- A table with an active order cannot receive a second one, and dishes can only be added while the order is not yet `listo`.
- Every write to an order runs in a **DB transaction with row locks** (`lockForUpdate`), so two waiters cannot take the same table at the same time.
- Categories or dishes that are still in use cannot be deleted (**409**). The foreign keys use `RESTRICT`.

## Architecture

Classic Laravel **MVC**, where each layer has one responsibility:

```
HTTP request
   │
   ▼
routes/api.php ──► auth:sanctum middleware
   │
   ▼
Form Request ──── validates input + authorizes (calls the Policy)
   │
   ▼
Controller ────── thin: coordinates, no business rules
   │
   ├──► Service (OrderService) ── business rules, transactions
   │
   ▼
Model (Eloquent) ── relations, casts, scopes, enums
   │
   ▼
API Resource ──── the "View": shapes the JSON response
```

| Folder                    | Contents                                                        |
| ------------------------- | --------------------------------------------------------------- |
| `app/Enums`               | `Role`, `TableStatus`, `OrderStatus` (with the transition rules) |
| `app/Models`              | `User`, `Category`, `Dish`, `Table`, `Order`, `OrderItem`        |
| `app/Http/Requests`       | Validation per endpoint                                          |
| `app/Policies`            | Who can do what                                                  |
| `app/Http/Resources`      | JSON output                                                      |
| `app/Services`            | `OrderService` (open order, add items, change status)            |
| `app/OpenApi`             | Global OpenAPI definitions (info, security, shared responses)    |
| `database/seeders`        | Idempotent demo data (safe to run on every deploy)               |

### Data model

```mermaid
erDiagram
    CATEGORY ||--o{ DISH : has
    TABLE ||--o{ ORDER : hosts
    USER ||--o{ ORDER : "opens (waiter)"
    ORDER ||--|{ ORDER_ITEM : contains
    DISH ||--o{ ORDER_ITEM : "appears in"
    ORDER_ITEM {
        int quantity
        decimal unit_price "price copied at order time"
        string notes
    }
```

## Error contract

Every error response is JSON and always has a `message`. A `422` response also includes `errors` for each field.

| Status | When                                                  |
| ------ | ----------------------------------------------------- |
| `401`  | Missing or invalid token, or wrong credentials        |
| `403`  | The role is not allowed to do this                    |
| `404`  | `"Dish not found."`, `"Endpoint not found."`          |
| `409`  | A business rule was broken (table busy, invalid transition…) |
| `422`  | Validation failed                                     |
| `429`  | Too many login/register attempts                      |

```json
{
  "message": "The price field is required.",
  "errors": { "price": ["The price field is required."] }
}
```

## Tech stack

| Area       | Tool                                                         |
| ---------- | ------------------------------------------------------------ |
| Language   | PHP 8.6                                                      |
| Framework  | Laravel 13                                                   |
| Auth       | Laravel Sanctum (personal access tokens)                     |
| Database   | PostgreSQL 17 (Neon in production)                           |
| Docs       | OpenAPI 3 · l5-swagger + swagger-php (spec attributes)       |
| Tests      | PHPUnit 12: feature + unit tests                             |
| Deploy     | Docker (php-apache) · Render Blueprint (`render.yaml`) · autodeploy on push |

## Run it locally

Requirements: PHP 8.3+ (with `pdo_pgsql` and `mbstring`), Composer and PostgreSQL.

```bash
git clone https://github.com/JuanMiguelRojas96/restaurant-api.git
cd restaurant-api
composer install
cp .env.example .env
php artisan key:generate

# set DB_USERNAME / DB_PASSWORD in .env for your Postgres user, then:
createdb restaurant_api
php artisan migrate --seed
php artisan l5-swagger:generate
php artisan serve
```

Open http://localhost:8000/api/documentation.

### With Docker

```bash
docker build -t restaurant-api .
docker run --rm -p 8080:8080 \
  -e APP_KEY="base64:$(openssl rand -base64 32)" \
  -e APP_URL=http://localhost:8080 \
  -e DB_URL="postgresql://user:pass@host:5432/dbname" \
  restaurant-api
```

When the container starts, it caches the config, runs the migrations and seeders, and generates the OpenAPI spec (see `docker/entrypoint.sh`).

## Tests

```bash
createdb restaurant_api_test
php artisan test
```

There are **73 tests and 174 assertions**. They cover auth, permissions for every role, validation, filters, the order state machine, totals, and table occupancy.

## Deployment

`git push` to `main` triggers this flow:

```
GitHub ──► Render (docker build) ──► entrypoint: migrate + seed + swagger ──► health check /up ──► Live
                                                    │
                                                    └──► Neon PostgreSQL
```

Secrets (`APP_KEY`, `APP_URL`, `DB_URL`) are kept in Render's dashboard and never committed. The service itself is defined as code in [`render.yaml`](render.yaml).

---

Made by **Juan Miguel Rojas** · [GitHub](https://github.com/JuanMiguelRojas96)
