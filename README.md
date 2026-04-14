# PetMatch Backend (Phase 1)

Laravel backend for PetMatch with Dockerized development, PostgreSQL, and Sanctum authentication.

## Stack

- Laravel 13 (compatible with Laravel 11+ requirement)
- PHP 8.4 (8.2+ compatible requirement)
- PostgreSQL
- Laravel Sanctum
- Docker + Docker Compose

## Docker Services

- `app`: PHP-FPM container for Laravel
- `nginx`: web server (exposes `http://localhost:8000`)
- `postgres`: database service on internal Docker network (`postgres:5432`)
- `pgadmin` (optional): enabled via Compose profile `tools`

## Environment

Configured in `.env` / `.env.example`:

- `APP_ENV=local`
- `APP_URL=http://localhost`
- `DB_CONNECTION=pgsql`
- `DB_HOST=postgres`
- `DB_PORT=5432`
- `DB_DATABASE=petmatch`
- `DB_USERNAME=postgres`
- `DB_PASSWORD=postgres`

## Authentication Endpoints

- `POST /api/register`
- `POST /api/login`
- `POST /api/logout` (protected with `auth:sanctum`)
- `GET /api/me` (protected with `auth:sanctum`)

## Run Commands

```bash
docker compose up --build
docker compose exec app bash
docker compose exec app composer install
docker compose exec app php artisan migrate
```

### Optional pgAdmin

```bash
docker compose --profile tools up -d pgadmin
```

## Notes

- Users are stored with UUID primary keys.
- `personal_access_tokens` uses UUID morphs for Sanctum compatibility.
- Controller logic stays thin; business auth logic lives in `app/Services/AuthService.php`.
