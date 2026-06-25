# Manual Testing Checklist — Person BMI Security Lab

Use this checklist after starting the backend (`php -S localhost:8080 -t public`) and frontend (`npm run serve`).

**Tools:** Browser, Postman (or similar), seed accounts from `slimbackend/sql/seed.sql`  
**Default password:** `password123`

Mark each item: **Pass** / **Fail** / **Not tested**  
Attach screenshots to the evidence table in `SECURITY_LAB_REPORT.md`.

---

## Setup Verification

- [ ] MySQL running; database `security_bmi_lab` imported (`schema.sql` + `seed.sql`)
- [ ] Backend health check: `GET http://localhost:8080/api/health` returns `{"status":"ok"}`
- [ ] Frontend loads at `http://localhost:8081` (or your port)
- [ ] JWT config present (`slimbackend/jwt.config.local.php` or `.env` with `JWT_SECRET`)

---

## 1. Negative Weight

**Goal:** Backend rejects invalid BMI input.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 1.1 | Login as `aiman@student.utm.my` | JWT received | `[ ]` | `[ ]` |
| 1.2 | `POST /api/persons` with `"weight": -70` | HTTP 400, weight validation error | `[ ]` | `[Screenshot: negative-weight.png]` |

**Sample body:**
```json
{
  "name": "Test User",
  "age": 21,
  "height": 1.70,
  "weight": -70,
  "notes": "negative weight test"
}
```

---

## 2. Empty Name

**Goal:** Backend rejects empty name.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 2.1 | Login as any user | JWT received | `[ ]` | `[ ]` |
| 2.2 | `POST /api/persons` with `"name": ""` | HTTP 400, name required error | `[ ]` | `[Screenshot: empty-name.png]` |

---

## 3. SQL Injection Login Input

**Goal:** Prepared statments prevent injection; no SQL errors exposed.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 3.1 | `POST /api/login` with email `' OR '1'='1` | HTTP 401 Invalid login | `[ ]` | `[Screenshot: sql-injection-login.png]` |
| 3.2 | Inspect response body | No SQLSTATE, table names, or stack trace | `[ ]` | `[ ]` |

**Sample body:**
```json
{
  "email": "' OR '1'='1",
  "password": "anything"
}
```

---

## 4. Access Another User's Record

**Goal:** IDOR blocked — only owner (or admin) can access.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 4.1 | Login as `aiman@student.utm.my` (user_id 1) | JWT for user | `[ ]` | `[ ]` |
| 4.2 | `GET /api/persons/2` (Aisyah's record) | HTTP 403 Forbidden | `[ ]` | `[Screenshot: idor-forbidden.png]` |
| 4.3 | `GET /api/persons/1` (own record) | HTTP 200 | `[ ]` | `[ ]` |

---

## 5. User Accesses Staff Route

**Goal:** RBAC blocks non-staff users.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 5.1 | Login as `aiman@student.utm.my` | User JWT | `[ ]` | `[ ]` |
| 5.2 | `GET /api/staff/persons` | HTTP 403 Forbidden | `[ ]` | `[Screenshot: user-staff-route.png]` |

---

## 6. User Accesses Admin Route

**Goal:** RBAC blocks non-admin users.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 6.1 | Login as `aiman@student.utm.my` | User JWT | `[ ]` | `[ ]` |
| 6.2 | `GET /api/admin/users` | HTTP 403 Forbidden | `[ ]` | `[Screenshot: user-admin-route.png]` |

---

## 7. Protected-Field Update

**Goal:** Client cannot change `user_id`, `bmi`, or `category` via PUT.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 7.1 | Login as `aiman@student.utm.my` | JWT received | `[ ]` | `[ ]` |
| 7.2 | `PUT /api/persons/1` with `"user_id": 99, "bmi": 10, "category": "Normal"` | HTTP 200 but fields unchanged | `[ ]` | `[Screenshot: protected-field.png]` |
| 7.3 | `GET /api/persons/1` | `user_id` still 1; BMI recalculated from height/weight only | `[ ]` | `[ ]` |

---

## 8. Sensitive Data in API Response

**Goal:** No passwords, hashes, debug SQL, or internal fields in responses.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 8.1 | `POST /api/login` as any seed user | Response has `id`, `name`, `email`, `role` only in `user` | `[ ]` | `[Screenshot: login-response.png]` |
| 8.2 | Login as admin; `GET /api/admin/users` | No `password`, `password_hash`, `debug_sql` | `[ ]` | `[Screenshot: admin-users-response.png]` |
| 8.3 | Search raw JSON for `"password"` | Not present | `[ ]` | `[ ]` |

---

## 9. XSS Payload in Notes

**Goal:** Payload displays as text; no alert or script execution.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 9.1 | Login and create/update a BMI record | Record saved | `[ ]` | `[ ]` |
| 9.2 | Set notes to `<img src=x onerror="alert('XSS')">` | Saved successfully | `[ ]` | `[ ]` |
| 9.3 | View record on My BMI page | Payload visible as text; **no alert popup** | `[ ]` | `[Screenshot: xss-safe-render.png]` |

---

## 10. Secure Error Handling (Optional)

**Goal:** Internal errors return generic message.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 10.1 | Stop MySQL or use wrong DB credentials temporarily | `[ ]` | `[ ]` | `[ ]` |
| 10.2 | Call any protected API route | HTTP 500, `{"error":"Unable to process request"}` | `[ ]` | `[Screenshot: generic-error.png]` |
| 10.3 | Confirm response has no file path, SQLSTATE, or credentials | Clean generic error | `[ ]` | `[ ]` |
| 10.4 | Check PHP error log | Detailed error logged server-side | `[ ]` | `[ ]` |

---

## 11. Register Role Escalation (Commit 5)

**Goal:** Registration cannot create admin/staff accounts.

| Step | Action | Expected | Pass? | Screenshot |
|------|--------|----------|-------|------------|
| 11.1 | `POST /api/register` with `"role": "admin"` | HTTP 201, user created with `"role": "user"` | `[ ]` | `[Screenshot: register-role.png]` |

---

## 12. Staff and Admin Positive Tests

Confirm authorized roles still work:

| Step | Action | Expected | Pass? |
|------|--------|----------|-------|
| 12.1 | Login as `siti.hajar@utm.my` (staff) | JWT received | `[ ]` |
| 12.2 | `GET /api/staff/persons` | HTTP 200, all records | `[ ]` |
| 12.3 | Login as `amran.hamid@utm.my` (admin) | JWT received | `[ ]` |
| 12.4 | `GET /api/admin/users` | HTTP 200 | `[ ]` |
| 12.5 | `DELETE /api/admin/persons/1` | HTTP 200 | `[ ]` |

---

## Summary

| Test Area | Pass | Fail | Not Tested |
|-----------|------|------|------------|
| Negative weight | | | |
| Empty name | | | |
| SQL injection login | | | |
| IDOR / ownership | | | |
| Staff RBAC | | | |
| Admin RBAC | | | |
| Protected fields | | | |
| Sensitive data | | | |
| XSS | | | |
| Error handling | | | |
| Register role | | | |

**Tester name:** `[Your name]`  
**Date tested:** `[Date]`  
**Backend URL:** `[e.g. http://localhost:8080]`  
**Frontend URL:** `[e.g. http://localhost:8081]`
