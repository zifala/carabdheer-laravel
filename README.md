# zifala/carabdheer-laravel

The Laravel package for [Carabdheer](https://carabdheer.com). It tells Carabdheer what your app is doing: exceptions, slow requests and queries, queued jobs, scheduled tasks, mail, logs, backups and database stats. It sends in small batches after the response, so your users never wait for it.

Laravel 12 or 13 · PHP 8.3+

## Install

1. In Carabdheer: **apps → connect an app**. Copy the app key (`crb_…`).
2. In your app:

```bash
composer require zifala/carabdheer-laravel
```

3. Add the key to `.env`:

```dotenv
CARABDHEER_KEY=crb_your_app_key
```

4. Run the installer. It publishes the config and sends one test event:

```bash
php artisan carabdheer:install
```

The connect page in Carabdheer turns green when the test event arrives.

Without a key (local, CI, tests) the package does nothing and sends nothing.

## What it sends

| Event          | When                                       | Notes                                                                                                                                                                                                                          |
| -------------- | ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| exception      | Every reported exception                   | Class, message, stack (app frames marked), request method, url and headers (authorization, cookies, api keys and csrf tokens replaced by `[filtered]`), user id, environment, release. Same bug is grouped once in Carabdheer. |
| request        | A sample of requests                       | 10 % by default (`requests.sample_rate`), counted back up in charts. Route, status, duration.                                                                                                                                  |
| slow_query     | Queries over 1 s                           | SQL with `?` placeholders, never the bound values; duration, connection.                                                                                                                                                       |
| job            | Processed, failed, retried                 | Class, queue, attempts, duration, wait.                                                                                                                                                                                        |
| scheduled_task | Finished or failed                         | Command or callback name, runtime.                                                                                                                                                                                             |
| mail           | Every sent mail                            | Subject, recipient, mailable class. Delivery, bounces and complaints come from Resend webhooks in Carabdheer.                                                                                                                  |
| log            | Log lines at `warning` and above           | Level, message, and up to 20 context values (objects sent as their type name only). The line Laravel writes for an exception already sent is skipped. Keep secrets out of log context.                                         |
| backup         | `spatie/laravel-backup` success or failure | Backup name and disk. Powers the "backup missed" alert.                                                                                                                                                                        |
| database_stats | Every 5 minutes from your scheduler        | Engine, name, size, connections, max connections (mysql, mariadb, pgsql, sqlite).                                                                                                                                              |

Request bodies, query bindings, cookies and session data are never sent.

## Settings

`config/carabdheer.php` (published by the installer):

| Key                    | Default                                                         | What it does                                                                      |
| ---------------------- | --------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `key`                  | `env('CARABDHEER_KEY')`                                         | The app key. Empty means off.                                                     |
| `url`                  | `env('CARABDHEER_URL', 'https://carabdheer.com/api/v1/ingest')` | Ingest address.                                                                   |
| `timeout`              | `2`                                                             | Seconds before a send gives up. A failed send never throws into your app.         |
| `release`              | `env('APP_RELEASE')`                                            | Git sha or version, shown on errors and as deploy markers. Set it in your deploy. |
| `requests.sample_rate` | `0.1`                                                           | Share of requests to send, `0` to `1`.                                            |
| `slow_query_ms`        | `1000`                                                          | Queries slower than this are sent.                                                |
| `log_level`            | `warning`                                                       | Lowest log level sent.                                                            |

## Linking containers to the app

If the app runs in Docker and the server runs the Carabdheer agent, label the container with the app's name so its restarts and logs show on the app page:

```yaml
labels:
    carabdheer.app: shop
```

## Database stats need the scheduler

Database stats are sent by a scheduled callback every 5 minutes, so the app's scheduler must run (`php artisan schedule:work`, or cron with `schedule:run`).

## Testing it

```bash
php artisan carabdheer:install
```

runs again safely and sends a new test event. You can also throw one on purpose:

```php
Route::get('/carabdheer-test', fn () => throw new RuntimeException('Carabdheer test'));
```

## Without Laravel

Anything that can send HTTP can report through the ingest API: see `/docs/api` on your Carabdheer.

## License

Proprietary, © Zifala.
