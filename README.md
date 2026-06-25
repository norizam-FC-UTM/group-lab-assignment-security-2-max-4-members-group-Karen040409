[![Review Assignment Due Date](https://classroom.github.com/assets/deadline-readme-button-22041afd0340ce965d47ae6ef1cefeee28c7c493a6346c4f15d667ab976d596c.svg)](https://classroom.github.com/a/GIZXzuf0)

# SECJ3483 — Person BMI Security Lab

This repository contains an **intentionally insecure** Person BMI web application used in the SECJ3483 Web Technology Security lab.

The application allows users to register, log in, and manage BMI records. It contains deliberate security weaknesses that students are expected to investigate, document, and fix in later commits.

---

## Project Structure

```
group-lab-assignment-security-2-max-4-members-group-Karen040409/
├── slimbackend/                  # PHP Slim 4 REST API (insecure)
│   ├── public/
│   │   └── index.php             # Entry point / front controller
│   ├── src/
│   │   ├── config.php            # Database configuration (hardcoded)
│   │   └── db.php                # PDO database connection
│   ├── sql/
│   │   ├── schema.sql            # Database and table definitions
│   │   └── seed.sql              # Sample users and BMI records
│   ├── vendor/                   # Composer dependencies (not committed)
│   ├── composer.json
│   └── composer.lock
├── vuefrontend/                  # Vue 3 CLI frontend (insecure)
│   ├── public/
│   │   └── index.html
│   ├── src/
│   │   ├── components/           # Reusable Vue components
│   │   ├── views/                # Page-level Vue views
│   │   ├── router/               # Vue Router configuration
│   │   ├── services/
│   │   │   └── api.js            # Axios API calls to backend
│   │   ├── utils/
│   │   │   └── auth.js           # Auth helper (insecure token handling)
│   │   ├── App.vue
│   │   ├── main.js
│   │   └── style.css
│   ├── node_modules/             # npm dependencies (not committed)
│   ├── .env                      # API base URL (not committed)
│   ├── .env.example
│   ├── package.json
│   ├── package-lock.json
│   └── vue.config.js
├── report/                       # Lab report folder
├── person-bmi-slim-insecure-backend.rar
├── person-bmi-vue-cli-insecure-starter.rar
├── .gitignore
└── README.md
```

---

## Prerequisites

| Tool | Version | Notes |
|------|---------|-------|
| PHP | 8.0+ | via XAMPP recommended |
| Composer | any | `C:\xampp\php\composer.phar` or global |
| Node.js | 16+ | v24 confirmed working |
| npm | 8+ | comes with Node.js |
| MySQL | 5.7+ / 8.0 | via XAMPP |
| phpMyAdmin | any | for easy DB import |

---

## Database Setup

### 1. Start MySQL

Start XAMPP and ensure the **MySQL** service is running.

### 2. Create the database and tables

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or use the MySQL CLI:

```sql
source path/to/slimbackend/sql/schema.sql
```

Or in the MySQL CLI:

```bash
mysql -u root -p < slimbackend/sql/schema.sql
```

### 3. Import seed data

```bash
mysql -u root -p < slimbackend/sql/seed.sql
```

- **Database name:** `security_bmi_lab`
- **Schema file:** `slimbackend/sql/schema.sql`
- **Seed file:** `slimbackend/sql/seed.sql`

### 4. Verify database configuration

Open `slimbackend/src/config.php` and confirm the settings match your local MySQL:

```php
'db_host' => '127.0.0.1',
'db_name' => 'security_bmi_lab',
'db_user' => 'root',
'db_pass' => ''          // change if your MySQL root has a password
```

---

## Backend Setup (Slim 4 PHP API)

### Install dependencies

```bash
cd slimbackend
# If composer is global:
composer install

# If using XAMPP composer:
C:\xampp\php\php.exe C:\xampp\php\composer.phar install
```

### Start the backend server

```bash
cd slimbackend
php -S localhost:8080 -t public
```

Or using the composer script:

```bash
composer start
```

**Backend API URL:** `http://localhost:8080`

**Health check:** `http://localhost:8080/api/health`

Expected response:
```json
{ "status": "ok", "api": "person-bmi-insecure-backend" }
```

---

## Frontend Setup (Vue 3 CLI)

### Install dependencies

```bash
cd vuefrontend
npm install
```

### Start the development server

```bash
cd vuefrontend
npm run serve
```

**Frontend URL:** `http://localhost:8081`

The frontend is pre-configured to call the backend at `http://localhost:8080/api` via `vuefrontend/.env`.

---

## Default Test Accounts

| Role  | Email                      | Password    |
|-------|----------------------------|-------------|
| user  | `aiman@student.utm.my`     | `password123` |
| user  | `aisyah@student.utm.my`    | `password123` |
| staff | `siti.hajar@utm.my`        | `password123` |
| admin | `amran.hamid@utm.my`       | `password123` |

---

## Running Order

1. Start XAMPP → start **Apache** and **MySQL**
2. Import database: run `schema.sql` then `seed.sql`
3. Start backend: `php -S localhost:8080 -t public` (inside `slimbackend/`)
4. Start frontend: `npm run serve` (inside `vuefrontend/`)
5. Open browser: `http://localhost:8081`

---

## Notes

- This application is **intentionally insecure** for lab investigation purposes.
- Security fixes will be applied in later commits.
- Do not use real credentials or deploy this publicly.
