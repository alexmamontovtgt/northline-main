# Order desk

Take-home for a middle+ fullstack role. Budget **4–6 hours**. At 6 (or earlier, if you wish) hours, stop, leave the app working, and write down what you would do next

This is a small internal tool for reviewing and creating customer orders. Improve it in place. Do not replace it

AI tools are allowed. Disclosure in `AI_USAGE.md` is part of the submission. The review focuses on which decisions are yours

## Setup

PHP 8.3+, Composer, Node 20+

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
```

Then either:

```bash
php artisan serve
npm run dev
```

or:

```bash
composer run dev
```

Open http://localhost:8000. Tests: `php artisan test`.

Pint, PHPStan, Rector, and Pest are installed. Nothing runs them for you, and they are not part of the task list. `php artisan test` is the suite this app ships with. Use the others only if you decide they help

Mail is appended to `storage/logs/laravel.log`. Nothing is sent to a real inbox

Leave `composer.json` as it is. It keeps this Laravel 11 app installable on current PHP

## Sign in

Both passwords are `password`

- Alex Morgan — `alex.morgan@northline.test`. Owns most orders.
- Sam Rivera — `sam.rivera@northline.test`. Owns some orders, including some with status `new`

## What works today

- Sign in, then a list of orders with customer, item count, total, and whether the customer email was marked sent
- Filter the list by status
- Create an order for an existing customer with one or more lines. The server stores the total
- Delete an order
- On create, the app emails the customer and pushes the order to CRM before the response returns

`app/Services/CrmSyncService.php` is a local stand-in. It is slow on purpose. Orders for Westbridge Hydraulics are rejected every time. The order is still saved, and the rejection is written to the log

The database queue driver and `jobs` table are already configured. Nothing is dispatched yet, so you do not need a queue worker to use the app as shipped

## What to do

There is more roughness here than you can responsibly fix in the time. Fix what will hurt this tool in real use. Leave the rest, and say why

1. Finish filtering by status and by customer email. Statuses are `new`, `processing`, `completed`, and `cancelled`
2. Add pagination on the server and in the list
3. Only the user who created an order may delete it, and only when its status is `new`
4. Move the customer email and the CRM sync off the create request. Creating the order must still succeed when either one fails. The database queue already in this project is sufficient. Do not add Redis, Docker, or another broker
5. Add tests for the behavior you changed. Keep the existing tests passing, or update them when that behavior is supposed to change
6. Add `SOLUTION.md`, `DECISIONS.md`, and `AI_USAGE.md` at the repo root

## What good looks like

- Alex can filter by status and customer email, page the list, create an order without that request waiting on the customer email or the CRM call, and delete only their own `new` orders. Sam's orders stay protected. A completed order cannot be deleted
- `php artisan test` passes
- The three files describe the code you committed, not a design you did not ship
- The app still runs with the setup above

## What to write

`SOLUTION.md` — what you changed, what you left alone and why, how to run the tests, and how to run a queue worker if your design needs one

`DECISIONS.md` — **3** decisions that are visible in the diff. For each one: options you considered, the one you chose, and why you rejected the others. A general principle with no code behind it does not count

`AI_USAGE.md` — if you used no AI, say so. If you did, for each tool include:

- the tasks you used it for
- the prompt, or a faithful description of what you asked
- what you accepted unchanged
- what you edited by hand
- one suggestion that was wrong or incomplete, and how you noticed

If you did not reject or correct anything, say so, and name one place you narrowed a suggestion instead of taking it whole

## Submission

- Git history with meaningful commits. One commit at the end that contains the whole solution does not meet the bar
- Passing tests
- The three files above
- The app still runnable with this README

## Do not

- Rewrite the project or replace authentication
- Add a new architectural layer for every class
- Optimize or restyle things that are outside the problems you chose
- Add Docker, Redis, Kubernetes, or a deployment pipeline
