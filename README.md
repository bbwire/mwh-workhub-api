# MWH WorkHub — Laravel API

REST API for the MWH WorkHub Nuxt frontend (Sanctum SPA authentication).

## Requirements

- PHP 8.2+
- Composer
- SQLite (default local) or PostgreSQL

## Quick start (SQLite)

```bash
cd backend
composer install
cp .env.example .env   # if needed
php artisan key:generate
type nul > database\database.sqlite   # Windows
php artisan migrate --seed
php artisan serve
```

API base URL: `http://localhost:8000/api/v1`

## Docker services (PostgreSQL, Redis, Mailpit)

From repository root:

```bash
docker compose -f docker/docker-compose.yml up -d
```

Then set in `backend/.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=mwh_workhub
DB_USERNAME=mwh
DB_PASSWORD=mwh
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

## Sanctum (Nuxt SPA)

1. `GET http://localhost:8000/sanctum/csrf-cookie` (with credentials)
2. `POST http://localhost:8000/api/v1/auth/login` with `{ "email", "password" }`
3. Authenticated requests use session cookies (`credentials: 'include'`)

## Seed users (password: `password`)

| Email | Role |
|-------|------|
| admin@mswnh.test | system_admin |
| management@mswnh.test | hospital_management |
| facilities.supervisor@mswnh.test | department_supervisor |
| facilities.officer@mswnh.test | staff (Facilities workspace) |
| ict.officer@mswnh.test | staff (ICT workspace) |
| staff@mswnh.test | staff (requester) |

## Key endpoints

| Method | Path |
|--------|------|
| GET | `/api/v1/health` |
| POST | `/api/v1/auth/login` |
| POST | `/api/v1/auth/forgot-password` |
| POST | `/api/v1/auth/reset-password` |
| POST | `/api/v1/auth/logout` |
| GET | `/api/v1/auth/user` |
| GET | `/api/v1/work-types` |
| GET | `/api/v1/my-work` |
| GET/POST | `/api/v1/work-items` |
| GET/PATCH | `/api/v1/work-items/{id}` |
| POST | `/api/v1/work-items/{id}/transitions` |
| POST | `/api/v1/work-items/{id}/assign` |
| GET/POST | `/api/v1/work-items/{id}/comments` |
| GET | `/api/v1/work-items/{id}/activity` |
| GET/POST | `/api/v1/work-items/{id}/attachments` |
| GET | `/api/v1/work-items/{id}/attachments/{id}/download` |
| GET | `/api/v1/notifications` |
| POST | `/api/v1/notifications/{id}/read` |
| GET | `/api/v1/dashboards/management` |
| GET | `/api/v1/dashboards/department/{id}` |
| GET | `/api/v1/reports/department-activity` |
| GET/POST/PATCH/DELETE | `/api/v1/meetings` |
| POST | `/api/v1/meetings/{id}/actions/bulk` |
| GET/POST/PATCH/DELETE | `/api/v1/projects` |
| GET | `/api/v1/projects/{id}/board` |
| POST | `/api/v1/projects/{id}/tasks` |
| GET/POST/PATCH | `/api/v1/admin/users` |
| GET/POST/PATCH | `/api/v1/admin/departments` |
| GET/POST/PATCH/DELETE | `/api/v1/admin/workflows` |
| PUT | `/api/v1/admin/workflows/{id}/definition` |
| GET | `/api/v1/workspaces` |
| GET | `/api/v1/work-items/{id}/assignees` |
| GET | `/api/v1/workspaces/{slug}/board` |

## Scheduler & queues

Notifications and scheduled jobs expect a queue worker and the task scheduler:

```bash
php artisan queue:work
php artisan schedule:work
```

Artisan shortcuts: `workhub:notify-due-soon`, `workhub:notify-overdue`, `workhub:escalate-overdue`.

See [docs/backend-implementation-plan.md](../docs/backend-implementation-plan.md).
