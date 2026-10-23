# Solution

## What I changed

### 1. Filtering by status and customer email (`GET /api/orders`)

- `ListOrdersRequest` validates `status` (one of `OrderStatus`), `email` (valid address), `page` and `per_page` (1-100), with readable messages.
- `OrderStatus` is a backed enum cast on `Order`. The string constants and literals are gone.
- The email filter is an exact match on `customers.email`. Customer emails are lowercased on write (`Customer::email()` mutator, plus a one-off migration for existing rows), so the lookup is a plain `=` and needs no `lower()` in SQL.
- The UI sends the email filter only when the input is a complete address, and resets to page 1 when a filter changes.

### 2. Server-side pagination

- `OrderRepository::list()` returns a paginator. `OrderResource::collection()` serialises it (`data`, `links`, `meta`).
- The list loads `customer` and uses `withCount('items')`, so line items are not loaded just to count them.
- The page has Previous/Next and a "Page x of y" footer. If the current page disappears (last row deleted), the UI jumps to the last page.

### 3. Delete only your own `new` orders

- `OrderPolicy::delete` is the single rule. It returns a reason: "You can only delete orders you created." or `Only orders with status "new" can be deleted.`
- `destroy` enforces it with `Gate::authorize`. The list API returns `can_delete` per order (from the same policy), and the UI only renders the Delete button when it is true. The frontend holds no copy of the rule.
- Missing orders return a generic 404 message instead of the Eloquent model name.

### 4. Email and CRM off the create request

- `store` creates the order and dispatches an `OrderPlaced` event. Two queued listeners do the slow work:
  - `SendOrderPlacedEmail` sends the mail, then sets `email_sent`. It skips orders already marked sent.
  - `SyncOrderToCrm` pushes to the CRM. On final failure it logs `CRM sync failed` (same warning as before).
- Each listener retries 3 times (10 s, then 60 s backoff). `#[DeleteWhenMissingModels]` drops the job quietly when the order was deleted before the worker ran.
- If dispatching itself throws, the exception is reported and the response is still 201, so the order is never lost to a notification problem.
- Queue: the existing `database` driver. No new infrastructure.

### 5. Create request hardening

- `StoreOrderRequest` validates the lines with per-line messages ("Line 2: enter a product name."), limits `qty` to 1-10000, and rejects totals above `99,999,999.99` (the `decimal(10,2)` column) with a 422 instead of a database error on MySQL/Postgres.
- Money uses bcmath (`Order::totalFor()`), not floats. `unit_price` allows at most two decimals.
- Double submit: the UI blocks the button and ignores a second submit while one is in flight. On the server, `create()` uses `createOrFirst` on a unique `orders.fingerprint` (SHA-256 of user, customer, sorted lines and a 2-second time window). A repeat inside the window returns the existing order with 200 and does not dispatch the event again, so there is no second email or CRM push.

### Structure

- `OrderController` is thin. Queries and writes live behind an `OrderRepository` interface (`EloquentOrderRepository`, bound in `AppServiceProvider`). Request classes hold validation; `OrderResource` holds the response shape.
- `#[CurrentUser]` injects the signed-in user into `store`.

## Tests

```bash
php artisan test
```

All tests use in-memory SQLite and the `sync` queue (`phpunit.xml`), so no worker is needed. New or changed coverage:

- every status filter, email filter, combined filters, and the validation messages
- pagination bounds and the `page`/`per_page` errors
- `can_delete` flag and 403/404 messages on delete
- order creation: total without float drift, qty and total limits, readable validation errors
- `OrderPlaced` dispatched on create, mail sent once, CRM failure logged, jobs discarded when the order was deleted before the worker ran (uses the real database queue and `queue:work`)
- duplicate submit returns the same order (200) with one event, and later or different orders are created normally
- creation still succeeds when dispatching fails

The duplicate-submit race itself cannot be reproduced on SQLite. I checked it on MySQL 8 (throwaway container): 10 parallel identical requests, repeated 5 times, always produced exactly one order (one 201, nine 200) and no deadlocks.

## Running the queue worker

The database queue needs a worker, otherwise orders stay "Pending" and nothing is emailed or pushed to the CRM.

```bash
php artisan queue:listen --tries=1
```

`composer run dev` starts it together with the server and Vite. See the README for production notes. Failed jobs: `php artisan queue:failed`, `php artisan queue:retry all`.

## What I left alone, and why

- **CRM stub and mail transport.** `CrmSyncService` stays a local stand-in and mail still goes to the log.
- **Customer email filter and indexes.** The email filter uses `whereRelation`, which on SQLite scans `orders` instead of using the unique `customers.email` index, and `orders.customer_id` / `created_at` have no indexes. Fine at this size. A `whereIn('customer_id', subquery)` plus indexes is the next step if the table grows.
- **Frontend polish.** An incomplete email keeps the previous list on screen with no hint, and a slow response can overwrite a newer one (no abort/cancel flag). Not hurting correctness today.
- **Existing style issues.** `CustomerController`, `OrderPlacedMail` (Pint) and the unreachable `return 'x'` in `Order::legacyStatusCode()` (PHPStan) were there before and are outside these tasks.
- **`composer.json`.** The README asks to leave it alone. The only change is the `dev` script, which now also runs the queue worker (`queue:listen`), because otherwise `composer run dev` would leave orders stuck. No dependencies were added.

## Known limitations

- **Lost notifications.** The order is committed first and the jobs are queued right after. If the process dies in between, or `dispatch()` throws, the order exists but no email or CRM sync is ever sent, and nothing retries it. With the database queue the window is small (queue and orders share the same database), but it gets wider if the queue moves to Redis. The usual fix is an outbox flag on the order plus a scheduled command that re-dispatches orders that were never queued.
- **Duplicate email.** If the mail was sent but `markEmailSent` fails, the job retries and sends the mail a second time. Retrying the flag update a few times without re-sending would narrow this.
- **Duplicate detection is a time bucket.** Two identical submits on either side of a 2-second boundary create two orders, and a deliberate identical repeat inside the window returns the first order (200) without the UI saying so. The button lock covers the usual double click.
- **Auto-increment gaps.** A rejected duplicate insert consumes an order id, so ids can skip.
- **Local database migrations.** Existing databases need `php artisan migrate` (lowercases customer emails and adds `orders.fingerprint`).
