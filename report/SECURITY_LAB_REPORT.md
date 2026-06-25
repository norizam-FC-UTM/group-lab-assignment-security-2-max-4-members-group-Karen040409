# SECJ3483 Web Technology — Security Lab Report

## Project Title

**Person BMI Web Application — Security Investigation and Remediation**

---

## Group Members

| # | Name | Matric No. | Role in Project |
|---|------|------------|-----------------|
| 1 | `[Member 1 Name]` | `[Matric No.]` | `[e.g. Backend / Documentation]` |
| 2 | `[Member 2 Name]` | `[Matric No.]` | `[e.g. Frontend / Testing]` |
| 3 | `[Member 3 Name]` | `[Matric No.]` | `[e.g. Database / Report]` |
| 4 | `[Member 4 Name]` | `[Matric No.]` | `[e.g. Integration / Presentation]` |

**Group ID:** Karen040409  
**Course:** SECJ3483 Web Technology  
**Lab:** Security Assignment 2

---

## Security Investigation Summary

This lab used an intentionally insecure Person BMI application (PHP Slim 4 backend + Vue 3 frontend) to investigate common web security weaknesses. The team followed the approach **Break it → Explain it → Fix it → Proof it**.

The insecure starter exposed problems across input validation, authentication, authorization, sensitive data handling, XSS, and error disclosure. Fixes were applied incrementally across five Git commits so each security layer could be tested independently.

At the end of Commit 5, the application enforces:

- Server-side BMI validation and backend BMI calculation
- Bcrypt password hashing with `password_verify()`
- PDO prepared statments for database queries
- JWT authentication on protected routes
- Record ownership checks on user BMI routes
- Role-based access control (user / staff / admin)
- Protected-field filtering on create/update
- Safe API responses without passwords or debug data
- Vue text interpolation instead of `v-html` for user notes
- Generic client error messages with server-side logging

---

## Investigation Table

| # | Test / Attack Scenario | Expected Secure Behavior | Actual Result (Before Fix) | Weakness? | Fix Applied |
|---|------------------------|--------------------------|----------------------------|-----------|-------------|
| 1 | Submit BMI with negative weight | HTTP 400 with validation errors | `[Capture result]` | `[Yes/No]` | Backend validation (Commit 2) |
| 2 | Submit BMI with empty name | HTTP 400, name required | `[Capture result]` | `[Yes/No]` | Backend validation (Commit 2) |
| 3 | SQL injection in login email field | Login fails safely, no SQL error exposed | `[Capture result]` | `[Yes/No]` | Prepared statments (Commit 2) |
| 4 | Access another user's BMI by ID | HTTP 403 Forbidden | `[Capture result]` | `[Yes/No]` | Ownership check (Commit 4) |
| 5 | Normal user calls staff API | HTTP 403 Forbidden | `[Capture result]` | `[Yes/No]` | RBAC middleware (Commit 4) |
| 6 | Normal user calls admin API | HTTP 403 Forbidden | `[Capture result]` | `[Yes/No]` | RBAC middleware (Commit 4) |
| 7 | Send `user_id` / `bmi` in PUT body | Fields ignored; only allowed fields updated | `[Capture result]` | `[Yes/No]` | Protected-field filtering (Commit 4) |
| 8 | Register with `"role": "admin"` | User always created as `user` | `[Capture result]` | `[Yes/No]` | Force role on register (Commit 5) |
| 9 | Inspect login / admin user API response | No `password`, `password_hash`, or secrets | `[Capture result]` | `[Yes/No]` | Safe response formatters (Commit 5) |
| 10 | XSS payload in notes field | Rendered as plain text, no script execution | `[Capture result]` | `[Yes/No]` | Replace `v-html` with `{{ }}` (Commit 5) |
| 11 | Trigger backend exception (e.g. stop MySQL) | Generic JSON error, no stack trace | `[Capture result]` | `[Yes/No]` | Secure error handling (Commit 5) |
| 12 | Access protected route without JWT | HTTP 401 Unauthorized | `[Capture result]` | `[Yes/No]` | JWT middleware (Commit 3) |
| 13 | Modify localStorage role to admin | Frontend may show admin UI; backend still blocks | `[Capture result]` | `[Yes/No]` | Backend RBAC (Commit 4) |

> **Note:** Fill the "Actual Result (Before Fix)" column from your own investigation notes or re-test against an earlier commit. Do not copy fabricated results.

---

## Weakness Classification Table

| Weakness | OWASP Category (approx.) | Severity | Location | Description |
|----------|--------------------------|----------|----------|-------------|
| Missing input validation | A03 Injection / Input Validation | High | Backend BMI routes | Invalid age, height, weight accepted |
| Plain-text / weak passwords | A02 Cryptographic Failures | Critical | Original seed / register | Passwords stored or exposed insecurely |
| SQL injection risk | A03 Injection | Critical | Original string-built queries | User input concatenated into SQL |
| Missing authentication | A07 Identification & Auth Failures | High | Protected API routes | Routes accessible without login |
| Missing authorization / IDOR | A01 Broken Access Control | Critical | `/api/persons/{id}` | Any user could read/update/delete others' records |
| Missing RBAC | A01 Broken Access Control | High | Staff / admin routes | Role not enforced server-side |
| Mass assignment | A01 Broken Access Control | High | POST/PUT `/api/persons` | Client could send `user_id`, `bmi`, `role` |
| Privilege escalation on register | A01 Broken Access Control | Critical | `/api/register` | Client could choose admin role |
| Sensitive data exposure | A02 Cryptographic Failures | High | User API responses | `password_hash`, debug SQL returned |
| XSS via `v-html` | A03 Injection | High | `BmiCard.vue` | User notes rendered as HTML |
| Verbose error messages | A05 Security Misconfiguration | Medium | Backend exception handler | File path, SQL errors exposed |
| Frontend-only access control | A01 Broken Access Control | Medium | Vue router guards | localStorage role easily modified |

---

## Problem–Solution Mapping

| Problem | Root Cause | Solution | Commit |
|---------|------------|----------|--------|
| Invalid BMI data saved | No server validation | `validatePersonData()`, backend BMI calc | 2 |
| SQL injection possible | String concatenation in queries | PDO prepared statments | 2 |
| Weak password storage | Plain text / exposed hashes | `password_hash()` / `password_verify()` | 2 |
| Unauthenticated API access | No token check | JWT middleware on protected routes | 3 |
| User reads others' BMI records | No ownership check | `canAccessPersonOnUserRoute()` | 4 |
| Staff/admin routes open to all | No role middleware | `requireRolesMiddleware()` | 4 |
| Client controls `user_id`, BMI | No input filtering | `filterAllowedPersonInput()` | 4 |
| API returns password fields | `SELECT *` + debug fields | Explicit columns + `formatPublicUser()` | 5 |
| XSS in notes | `v-html` on user input | Vue interpolation `{{ person.notes }}` | 5 |
| Stack traces in API | `exposeException()` | `handleApiException()` + `error_log()` | 5 |
| Register as admin | Role taken from request body | Force `$role = 'user'` on register | 5 |

---

## Fixes Implemented

### Commit 1 — Project setup
- Flattened backend and frontend folders
- Installed Composer and npm dependencies
- Updated README and `.gitignore`

### Commit 2 — Validation, hashing, prepared statments
- Backend validation for name, age, height, weight
- BMI and category calculated server-side
- Bcrypt password hashing in seed and register/login
- All queries converted to prepared statments

### Commit 3 — JWT authentication
- JWT issued on login (`user_id`, `role`, `exp`)
- Bearer token required on protected routes
- Frontend stores token and redirects on 401

### Commit 4 — Authorization (RBAC + ownership)
- Users access only their own BMI records
- Staff: all records read-only via staff routes; delete own only
- Admin: full user list, role updates, delete any record
- Protected fields stripped from create/update payloads

### Commit 5 — Sensitive data, XSS, secure errors, documentation
- Removed debug fields from all API responses
- Explicit column selection for users and person records
- `formatPublicUser()` / `formatPersonRecord()` response helpers
- Replaced `v-html` with safe Vue interpolation in `BmiCard.vue`
- Generic error response: `{"error":"Unable to process request"}`
- Internal exceptions logged with `error_log()`
- Registration forced to `user` role
- Lab report and manual testing checklist

---

## Security Review Checklist (Final State)

| Control | Status | Evidence Location |
|---------|--------|-------------------|
| Backend validation | Implemented | `slimbackend/src/helpers.php` |
| Backend BMI calculation | Implemented | `calculateBmiAndCategory()` |
| Password hashing | Implemented | `password_hash()` / `password_verify()` |
| Prepared statments | Implemented | `slimbackend/public/index.php` |
| JWT protection | Implemented | `slimbackend/src/jwt.php` |
| Ownership checks | Implemented | `canAccessPersonOnUserRoute()` |
| RBAC | Implemented | `requireRolesMiddleware()` |
| Protected-field filtering | Implemented | `filterAllowedPersonInput()` |
| Safe API responses | Implemented | `formatPublicUser()`, `formatPersonRecord()` |
| XSS protection | Implemented | `vuefrontend/src/components/BmiCard.vue` |
| Secure error handling | Implemented | `handleApiException()` |

---

## before-and-after Evidence Table

| Test | before (Insecure) | After (Secured) | Screenshot / Evidence |
|------|-------------------|-----------------|----------------------|
| Negative weight submission | `[Describe / paste response]` | HTTP 400 validation error | `[Screenshot: before-negative-weight.png]` |
| Empty name submission | `[Describe]` | HTTP 400 validation error | `[Screenshot: after-empty-name.png]` |
| SQL injection login | `[Describe]` | Safe failure, no SQL details | `[Screenshot: sql-injection-login.png]` |
| Access another user's record | HTTP 200, record returned | HTTP 403 Forbidden | `[Screenshot: idor-before-after.png]` |
| User → staff route | HTTP 200, all records | HTTP 403 Forbidden | `[Screenshot: staff-rbac.png]` |
| User → admin route | HTTP 200, all users | HTTP 403 Forbidden | `[Screenshot: admin-rbac.png]` |
| Protected-field PUT (`user_id`) | Owner changed / field accepted | Field ignored, owner unchanged | `[Screenshot: protected-field.png]` |
| Login API response | `password_hash` / debug fields visible | Only id, name, email, role | `[Screenshot: sensitive-data.png]` |
| XSS in notes | Alert / script executes | Payload shown as text | `[Screenshot: xss-before-after.png]` |
| Backend exception | File path / SQLSTATE exposed | Generic error message | `[Screenshot: error-handling.png]` |
| Register as admin | User created with admin role | User always created as user | `[Screenshot: register-role.png]` |

> **Action required:** Replace every `[Screenshot: ...]` placeholder with your own captured evidence before submission.

---

## API Endpoints Tested

| Method | Endpoint | Auth | Role | Tested? | Result |
|--------|----------|------|------|---------|--------|
| GET | `/api/health` | No | — | `[ ]` | `[ ]` |
| POST | `/api/register` | No | — | `[ ]` | `[ ]` |
| POST | `/api/login` | No | — | `[ ]` | `[ ]` |
| GET | `/api/profile` | JWT | any | `[ ]` | `[ ]` |
| GET | `/api/persons` | JWT | user+ | `[ ]` | `[ ]` |
| POST | `/api/persons` | JWT | user+ | `[ ]` | `[ ]` |
| GET | `/api/persons/{id}` | JWT | owner/admin | `[ ]` | `[ ]` |
| PUT | `/api/persons/{id}` | JWT | owner/admin | `[ ]` | `[ ]` |
| DELETE | `/api/persons/{id}` | JWT | owner/admin | `[ ]` | `[ ]` |
| GET | `/api/staff/persons` | JWT | staff/admin | `[ ]` | `[ ]` |
| GET | `/api/staff/persons/{id}` | JWT | staff/admin | `[ ]` | `[ ]` |
| GET | `/api/admin/users` | JWT | admin | `[ ]` | `[ ]` |
| PUT | `/api/admin/users/{id}/role` | JWT | admin | `[ ]` | `[ ]` |
| DELETE | `/api/admin/persons/{id}` | JWT | admin | `[ ]` | `[ ]` |

**Sample test accounts (seed data, password `password123`):**

| Role | Email |
|------|-------|
| user | `aiman@student.utm.my` |
| user | `aisyah@student.utm.my` |
| staff | `siti.hajar@utm.my` |
| admin | `amran.hamid@utm.my` |

---

## Five GitHub Commit Summary

| Commit | Message (suggested) | Main changes |
|--------|---------------------|--------------|
| 1 | `chore: set up slim backend, vue frontend, and lab documentation` | Project structure, dependencies, README, `.gitignore` |
| 2 | `fix: validate BMI input, hash passwords, and use PDO prepared statments` | Validation, bcrypt, prepared statments, backend BMI calc |
| 3 | `feat: implement JWT authentication for protected API routes` | JWT issue/verify, middleware, frontend token handling |
| 4 | `feat: enforce RBAC and record ownership on protected routes` | Ownership checks, staff/admin RBAC, field filtering |
| 5 | `fix: sanitize API responses, prevent XSS, and secure error handling` | Safe responses, XSS fix, generic errors, report docs |

---

## Reflection Answers

### 1. Why must security be enforced on the backend even if the frontend validates input?

`[Write your answer here. Consider: frontend can be bypassed with Postman, modified JavaScript, or direct API calls.]`

### 2. What is the difference between authentication and authorization in this lab?

`[Write your answer here. Authentication = proving identity via JWT login. Authorization = checking role/ownership before allowing an action.]`

### 3. Why is frontend route guarding alone insufficient?

`[Write your answer here. Reference Debug Panel localStorage role modification.]`

### 4. What risk does returning `password_hash` in API responses create?

`[Write your answer here. Offline cracking, credential reuse, information disclosure.]`

### 5. How did fixing XSS with Vue interpolation differ from using `v-html`?

`[Write your answer here. Interpolation escapes HTML; v-html renders raw HTML.]`

### 6. What did you learn from incremental commits instead of one large fix?

`[Write your answer here.]`

### 7. Which weakness was most critical in this application and why?

`[Write your answer here.]`

### 8. What would you still improve before deploying to production?

`[Write your answer here. e.g. HTTPS, rate limiting, CORS restriction, refresh token, audit logging.]`

---

## Appendix — Key File References

| File | Purpose |
|------|---------|
| `slimbackend/public/index.php` | API routes and middleware |
| `slimbackend/src/helpers.php` | Validation, formatting, authorization helpers |
| `slimbackend/src/jwt.php` | JWT create/verify and role middleware |
| `vuefrontend/src/components/BmiCard.vue` | BMI record display (XSS fix) |
| `vuefrontend/src/services/api.js` | Axios + Bearer token |
| `report/MANUAL_TESTING_CHECKLIST.md` | Step-by-step test checklist |

---

*Export this document to PDF for submission: `[report/pdf/SECURITY_LAB_REPORT.pdf]` — generate after filling placeholders.*
