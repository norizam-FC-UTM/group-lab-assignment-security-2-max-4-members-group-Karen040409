[![Review Assignment Due Date](https://classroom.github.com/assets/deadline-readme-button-22041afd0340ce965d47ae6ef1cefeee28c7c493a6346c4f15d667ab976d596c.svg)](https://classroom.github.com/a/GIZXzuf0)

# SECJ3483 — Person BMI Security Lab

Person BMI web application for the SECJ3483 Web Technology security lab. The project started as an intentionally insecure starter and has been hardened across five commits (validation, JWT, RBAC, safe responses, XSS protection).

> **Warning:** For local lab use only. Do not deploy to a public server.

---

## Quick Start

Run these steps in order:

1. Start **MySQL** (XAMPP Control Panel → Start MySQL)
2. Import `slimbackend/sql/schema.sql` then `slimbackend/sql/seed.sql`
3. Configure database credentials in `slimbackend/src/config.php` if needed
4. Copy JWT config: `slimbackend/.env.example` → `slimbackend/.env`
5. Install and start the backend (terminal 1)
6. Install and start the frontend (terminal 2)
7. Open the frontend URL in your browser and log in

---

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.0+ | XAMPP recommended on Windows |
| Composer | any | For backend dependencies |
| Node.js | 16+ | For Vue frontend |
| npm | 8+ | Bundled with Node.js |
| MySQL | 5.7+ / 8.0 | Via XAMPP |
| phpMyAdmin | optional | Easiest way to import SQL files |

---

## Project Structure

```
├── slimbackend/          # PHP Slim 4 REST API
│   ├── public/index.php  # API entry point
│   ├── src/              # config, db, helpers, jwt
│   ├── sql/              # schema.sql, seed.sql
│   └── .env.example      # JWT secret template
├── vuefrontend/          # Vue 3 CLI frontend
│   └── .env.example      # API base URL template
└── report/               # Lab report and testing checklist
```

---

## 1. Database Setup

### Start MySQL

Open XAMPP and start **MySQL**. Apache is not required for this lab (the PHP built-in server runs the backend).

### Import SQL files

**Option A — phpMyAdmin (recommended)**

1. Open `http://localhost/phpmyadmin`
2. Click **Import**
3. Import `slimbackend/sql/schema.sql`
4. Import `slimbackend/sql/seed.sql`

**Option B — MySQL CLI**

```bash
mysql -u root -p < slimbackend/sql/schema.sql
mysql -u root -p < slimbackend/sql/seed.sql
```

If your MySQL root user has no password, omit `-p` or press Enter when prompted.

### Database configuration

Edit `slimbackend/src/config.php` if your local MySQL settings differ:

```php
'db_host' => '127.0.0.1',
'db_name' => 'security_bmi_lab',
'db_user' => 'root',
'db_pass' => '',   // set your MySQL password here if required
```

**Database name:** `security_bmi_lab`

---

## 2. Backend Setup (Slim 4 API)

### Install dependencies

```bash
cd slimbackend
composer install
```

On Windows with XAMPP Composer:

```powershell
cd slimbackend
C:\xampp\php\php.exe C:\xampp\php\composer.phar install
```

### Configure JWT secret

Login requires a JWT secret. Copy the example file:

```bash
cd slimbackend
copy .env.example .env
```

On Linux/macOS:

```bash
cp .env.example .env
```

Edit `slimbackend/.env` and set a strong random value:

```
JWT_SECRET=your-long-random-secret-here
```

> Do not commit `.env` — it is listed in `.gitignore`.

### Start the backend

```bash
cd slimbackend
php -S localhost:8080 -t public
```

Or:

```bash
composer start
```

**Backend base URL:** `http://localhost:8080`  
**Health check:** `http://localhost:8080/api/health`

Expected response:

```json
{
  "status": "ok",
  "api": "person-bmi-api"
}
```

### Port 8080 already in use?

If another program is using port 8080, pick a free port:

```bash
php -S localhost:8082 -t public
```

Then update the frontend API URL (see step 3 below) to match, e.g. `http://localhost:8082/api`.

---

## 3. Frontend Setup (Vue 3)

### Install dependencies

```bash
cd vuefrontend
npm install
```

If `npm install` fails or `npm run serve` reports missing packages, try:

```powershell
Remove-Item -Recurse -Force node_modules
npm cache clean --force
npm install
```

### Configure API URL

Copy the example env file if `.env` does not exist:

```bash
cd vuefrontend
copy .env.example .env
```

Default contents:

```
VUE_APP_API_BASE_URL=http://localhost:8080/api
```

Change the port if your backend runs on a different port (e.g. `8082`).

### Start the frontend

```bash
cd vuefrontend
npm run serve
```

Vue CLI prints the URL when ready — usually:

**Frontend URL:** `http://localhost:8081`

If 8081 is busy, Vue picks the next free port (e.g. 8082). Use the URL shown in the terminal.

---

## 4. Log In and Explore

Open the frontend URL in your browser.

### Default test accounts

All seed accounts use password **`password123`**.

| Role | Email | Password |
|------|-------|----------|
| user | `aiman@student.utm.my` | `password123` |
| user | `aisyah@student.utm.my` | `password123` |
| staff | `siti.hajar@utm.my` | `password123` |
| admin | `amran.hamid@utm.my` | `password123` |

### What to try in the app

| Page | Who can use it |
|------|----------------|
| Login / Register | Everyone |
| My BMI | Logged-in users (own records) |
| Staff BMI Monitor | staff, admin |
| Admin Users | admin only |

---

## API Overview

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| GET | `/api/health` | No | Health check |
| POST | `/api/register` | No | Creates user role only |
| POST | `/api/login` | No | Returns JWT token |
| GET | `/api/profile` | JWT | Current user profile |
| GET/POST | `/api/persons` | JWT | List/create own BMI records |
| GET/PUT/DELETE | `/api/persons/{id}` | JWT | Owner or admin |
| GET | `/api/staff/persons` | JWT | staff, admin |
| GET | `/api/staff/persons/{id}` | JWT | staff, admin |
| GET | `/api/admin/users` | JWT | admin |
| PUT | `/api/admin/users/{id}/role` | JWT | admin |
| DELETE | `/api/admin/persons/{id}` | JWT | admin |

Send the JWT as a Bearer token:

```
Authorization: Bearer <your-token-here>
```

---

## Lab Documentation

| File | Purpose |
|------|---------|
| `report/SECURITY_LAB_REPORT.md` | Full investigation report template |
| `report/MANUAL_TESTING_CHECKLIST.md` | Step-by-step security tests |

Fill in group member names, test results, and screenshots before submission.

---

## Troubleshooting

### `Access denied for user 'root'@'localhost'`

MySQL rejected the login. Set the correct password in `slimbackend/src/config.php` (`db_pass`).

### MySQL will not start in XAMPP

- Run XAMPP as Administrator
- Check if port 3306 is already in use
- If the data directory is corrupted, restore from `C:\xampp\mysql\backup` (Windows/XAMPP default)

### Login returns 500 / "JWT secret is not configured"

Create `slimbackend/.env` from `.env.example` and set `JWT_SECRET`.

### Frontend cannot reach backend (network error)

1. Confirm the backend is running (`/api/health` works in the browser)
2. Confirm `vuefrontend/.env` has the correct `VUE_APP_API_BASE_URL` port
3. Restart the frontend after changing `.env` (`npm run serve`)

### `npm run serve` — missing webpack or caniuse-lite

```powershell
cd vuefrontend
Remove-Item -Recurse -Force node_modules
npm cache clean --force
npm install
npm run serve
```

### Port conflicts summary

| Service | Default port | If busy |
|---------|--------------|---------|
| Backend | 8080 | Use 8082: `php -S localhost:8082 -t public` |
| Frontend | 8081 | Vue auto-picks next port — check terminal output |
| MySQL | 3306 | Stop conflicting service or change XAMPP port |

When you change the backend port, always update `vuefrontend/.env` to match.

---

## Running Order (Checklist)

- [ ] MySQL running
- [ ] `schema.sql` and `seed.sql` imported
- [ ] `slimbackend/src/config.php` credentials correct
- [ ] `slimbackend/.env` created with `JWT_SECRET`
- [ ] `composer install` done in `slimbackend/`
- [ ] Backend running: `php -S localhost:8080 -t public`
- [ ] `vuefrontend/.env` points to backend URL
- [ ] `npm install` done in `vuefrontend/`
- [ ] Frontend running: `npm run serve`
- [ ] Browser open at frontend URL; login works

---

## Security Notes

This lab demonstrates securing a web application incrementally:

1. Input validation, password hashing, prepared statments
2. JWT authentication
3. RBAC and record ownership
4. Safe API responses, XSS prevention, secure error handling

For production systems you would also need HTTPS, restricted CORS, rate limiting, and secrets management — those are out of scope for this lab starter.
