# PromptEdit

A Laravel and Vue image-generation app using Inertia.js and Pixelforge. Users can submit image prompts, see an upfront cost estimate, follow generation status, and review completed images and charges.

## Features

- User registration, sign-in, and sign-out.
- Image generation with prompt, image size, quality, and optional reference image URLs.
- Upfront estimate and actual final price based on Pixelforge usage, including the 25% service fee.
- Generation history with status, image, estimated charge, and final charge.
- Signed Pixelforge webhooks with duplicate-event handling and recovery polling for stuck generations.
- Admin cost report for daily and per-user charges, provider costs, profit, and daily reconciliation against Pixelforge usage.

## Architecture

Laravel handles authentication, validation, persistence, and the Inertia pages. Vue renders the interface. Generation and report logic lives in services; `PixelforgeClient` wraps provider API calls, while `PriceCalculator` handles decimal-safe estimates and charges. `GenerationService` stores and submits requests, and `ApplyGenerationResult` applies webhook or recovery results. Webhook event IDs are recorded to prevent duplicate processing.

## Tech stack

- Laravel 13 and PHP 8.3+
- Vue 3, Inertia.js, and TypeScript
- MySQL
- Pest PHP
- Pixelforge API

## Requirements

- PHP 8.3 or later and Composer
- Node.js and npm
- MySQL
- Pixelforge API credentials

## Local setup

1. Install dependencies and create the local environment file:

   ```bash
   composer install
   cp .env.example .env
   npm install
   php artisan key:generate
   ```

2. Set the database and Pixelforge variables in `.env`:

   ```dotenv
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=promptedit
   DB_USERNAME=root
   DB_PASSWORD=

   PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
   PIXELFORGE_API_KEY=your-pixelforge-api-key
   PIXELFORGE_WEBHOOK_SECRET=your-pixelforge-webhook-signing-secret
   PIXELFORGE_TIMEOUT=10

   QUEUE_CONNECTION=database
   ```

   Use the database credentials for your own MySQL instance. Never commit real API keys, webhook secrets, or database passwords.

3. Run migrations and build the frontend:

   ```bash
   php artisan migrate
   npm run build
   ```

4. Start the app:

   ```bash
   composer run dev
   ```

   This dev command starts the Laravel server, Vite, and a queue listener. The app is available at `http://localhost:8000`. If you start the Laravel server manually instead, run a worker in another terminal:

   ```bash
   php artisan queue:work
   ```

   For Pixelforge webhooks during local development, expose the app with a public HTTPS tunnel and configure its webhook URL as:

   ```text
   https://your-tunnel-host/webhooks/pixelforge
   ```

## Admin access

Admin access uses the same sign-in page as user access. A simple `role` column defaults to `user`; only accounts with `role=admin` can open the cost report. There is no separate admin login or public admin registration.

The deployed assessment demo has this pre-created admin account:

| Email | Password |
| --- | --- |
| `admin@admin.com` | `password` |

This is a demo credential only. Change or remove it before using the app with real users. The repository's `DatabaseSeeder` does not create this account; the credential is present in the deployed assessment database.

To promote an existing account, after the role migration has run, use Laravel Tinker:

```bash
php artisan tinker
```

```php
DB::table('users')->where('email', 'person@example.com')->update(['role' => 'admin']);
```

## Assumptions and implementation decisions

- **Why the estimate is a range:** the exact token usage, especially reasoning tokens, is only known after Pixelforge finishes. A single upfront number could therefore look more certain than it is and can differ from the final charge. The UI shows the midpoint-based expected estimate as the headline and the low-to-high range to make that uncertainty clear before submission. After success, the final user charge is calculated from Pixelforge's reported actual usage and rates, with the 25% service fee applied. Full decimal precision is stored; the UI rounds for display.
- **Failed generations:** a failed generation has a zero final charge.
- **Webhook safety:** Pixelforge webhook signatures are verified, and provider event IDs are stored so repeated notifications are not applied twice. Recovery polling checks generations that remain queued or processing.
- **Reporting dates:** costs are grouped by the UTC date the generation completed.
- **Provider reconciliation:** Pixelforge usage is returned at account/day level, not per user. The report compares the app's provider-cost total with Pixelforge's account total for each UTC day; a mismatch can mean the provider account had usage outside this app. Per-user costs come from this app's generation records.
- **Admin roles:** a single role field keeps access control small for this assessment. New accounts are ordinary users; an operator is explicitly assigned the `admin` role.
- **Credits were left out:** there is no credit balance, fake purchase flow, or insufficient-balance generation block. Users can generate without a credit wallet. This was intentionally left outside the implemented core flow.

## Tests

Run the full Pest suite:

```bash
php artisan test
```

The tests cover pricing calculations, generation creation and history, idempotency, Pixelforge client behavior, webhook signature and duplicate-event handling, generation result processing, admin authorization, daily/per-user report totals, reconciliation, and report date filters.

Useful project checks:

```bash
npm run check
npm run types:check
vendor/bin/pint --test
```
