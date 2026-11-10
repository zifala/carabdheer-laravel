## Carabdheer

`zifala/carabdheer-laravel` reports exceptions, sampled requests, slow queries, jobs, scheduled tasks, mail, logs, `spatie/laravel-backup` results and database stats to Carabdheer. The provider is auto-discovered and listens to framework events, so reporting needs no application code. The package has no facade or public reporting API.

### Install

@verbatim
<code-snippet name="Install the package" lang="bash">
composer require zifala/carabdheer-laravel
php artisan carabdheer:install
</code-snippet>
@endverbatim

`carabdheer:install` publishes `config/carabdheer.php`, adds an empty `CARABDHEER_KEY=` to `.env` if missing, and sends a test event when a key is set. It is safe to run again. It fails when Carabdheer does not answer 202.

@verbatim
<code-snippet name="Set the app key" lang="dotenv">
CARABDHEER_KEY=crb_your_app_key
</code-snippet>
@endverbatim

### Key

- A missing key, or one that does not start with `crb_`, turns sending off. No event leaves the app.
- Leave `CARABDHEER_KEY` unset in local, CI and test environments.
- A failed send never throws into the app.

### Config

Only `key`, `url` and `release` read the environment. Change the others in `config/carabdheer.php`.

- `key`: `CARABDHEER_KEY`.
- `url`: `CARABDHEER_URL`, default `https://carabdheer.com/api/v1/ingest`.
- `timeout`: seconds before a send gives up, default `2`.
- `release`: `APP_RELEASE`, attached to exception events.
- `requests.sample_rate`: share of requests sent, `0` to `1`, default `0.1`. Responses with status 500 or above are always sent.
- `slow_query_ms`: queries at or above this are sent, default `1000`.
- `log_level`: lowest log level sent, default `warning`.

### Deploys

Set `APP_RELEASE` to the git sha or version in every deploy, before `php artisan config:cache`.

@verbatim
<code-snippet name="Set the release in a deploy" lang="bash">
APP_RELEASE="$(git rev-parse HEAD)" php artisan config:cache
</code-snippet>
@endverbatim

### Scheduler

Database stats are sent every 5 minutes by a scheduled callback named `carabdheer:database-stats`, for the default connection. The app's scheduler must run, through cron with `schedule:run` or through `schedule:work`.

### Reporting exceptions

Every exception that reaches Laravel's exception handler is sent at once, during the request. Use `report($exception)` to send one you caught. Exceptions Laravel does not report are not sent.

### Keep secrets out

- Never put secrets in log messages or log context. Messages and the first 20 context values are sent at `log_level` and above. Scalars go as they are, arrays and objects as their type name.
- Never put secrets in exception messages or URL query strings. Exception events include the message and the full URL.
- Always use query bindings. Slow queries send the SQL text, so values written into the SQL are sent.
- Only these request headers are replaced by `[filtered]`: `authorization`, `cookie`, `set-cookie`, `x-api-key`, `x-csrf-token`, `x-xsrf-token`, `php-auth-pw`. Any other header on an exception's request is sent, so do not carry secrets in custom headers.

### Never sent

Request bodies, query binding values, cookie values and session data.

Mail events do send the subject and recipient address, and exception and request events send the user id.
