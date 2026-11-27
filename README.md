# Homemon

A password-protected, single-page dashboard for Shelly plugs, built with PHP 8.4+, Symfony 8.1, Doctrine ORM, Twig, Symfony UX Live Components and Chart.js.

## Setup

```bash
composer install
```

Configure your environment in `.env.local` (ignored by Git). `.env` defines the defaults, including `APP_PASSWORD`:

```dotenv
APP_SECRET=replace-with-a-random-secret
APP_PASSWORD=replace-with-your-shared-password
DATABASE_URL="postgresql://db:db@db:5432/db?serverVersion=17&charset=utf8"
```

The default database URL targets the included DDEV environment. For a database running elsewhere, change the URL accordingly. An empty `APP_PASSWORD` disables sign-in until a password is configured. No username or registration is needed.

```bash
symfony console doctrine:migrations:migrate --no-interaction
symfony serve -d
```

With DDEV, use `ddev composer install` and `ddev exec php bin/console doctrine:migrations:migrate --no-interaction`, then open `https://homemon.ddev.site`.

## Background collection

Run the collector as a **separate, continuously running process**:

```bash
symfony console app:plugs:collect --no-debug
# DDEV:
ddev exec php bin/console app:plugs:collect --no-debug
```

It reloads the plug list and requests `PLUG_URL/rpc/Switch.GetStatus?id=0` every second, even without an open browser. HTTP and HTTPS URLs, local hostnames, IP addresses, ports and base paths are supported. Requests run concurrently, have an 800 ms timeout and do not follow redirects. An unavailable device or invalid response creates a gap in its graph and does not interrupt the other devices. A Symfony lock prevents duplicate collectors on the same host; use a shared `LOCK_DSN` for multiple application hosts.

For deployment, run `APP_ENV=prod APP_DEBUG=0 php bin/console app:plugs:collect` under a process manager such as systemd or Supervisor, with automatic restart enabled. Restart the process after deploying new code or changing environment variables. Ctrl+C and SIGTERM stop it gracefully.

To collect one batch (for diagnostics):

```bash
symfony console app:plugs:collect --once -v --no-debug
```

## Dashboard

Sign in, click **Add Plug**, and enter a name and a full base URL, for example `http://192.168.1.42`. Add, edit and delete all happen on the dashboard without navigation. Each card refreshes once per second.

- The line graph shows **power in watts (`apower`) only** over the last five minutes.
- Voltage, current, total energy, temperature and switch state appear as current readings.
- Total energy from the device is converted from Wh to kWh for display.
- Times are UTC. A reading older than five seconds is marked stale.
- Current readings and up to 300 power samples per plug are persisted; this is a bounded live window, not a long-term measurement archive.

## Checks

```bash
php bin/phpunit
php bin/console lint:container
php bin/console lint:twig templates/
php bin/console lint:yaml config/
vendor/bin/pint --test
```

Tests use a separate SQLite database configured in `.env.test` and mock device HTTP responses; physical plugs are not required.
