# Deployment

## Development `/app` deployment

The development instance is served at:

- URL: `https://dev.archerchessacademy.com/app`
- Application directory: `/var/www/chess-lang`
- Database: separate from the ArcherKids ERP database
- PHP runtime: PHP 8.3 FPM

Required environment values for the subpath deployment:

```dotenv
APP_URL=https://dev.archerchessacademy.com/app
ASSET_URL=https://dev.archerchessacademy.com/app
SESSION_PATH=/app
CACHE_PREFIX=chess_lang_dev_cache_
VITE_APP_BASE_PATH=/app
```

The Nginx site routes `/app` requests to ChessLang's `public` directory while
the domain root remains mapped to ArcherKids. Reverb runs as a separate
service and its WebSocket endpoint is proxied through the same HTTPS host.

Deploy the `development` branch to the development server. Production must
use separate environment credentials, database, cache prefix, and process
services.

Cache configuration and views independently:

```bash
php artisan config:cache
php artisan view:cache
```

Do not run `php artisan optimize` for the `/app` deployment. Its route cache
changes the package route resolution at the subpath and causes `/app/` to
return `405 Method Not Allowed`. Keep the route cache cleared with
`php artisan route:clear`.
