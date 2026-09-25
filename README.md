# BB Builders ERP

Construction company ERP — CodeIgniter 4 (MVC) backend + MySQL, single-page frontend.

## Stack

- **Backend:** CodeIgniter 4 — `app/Controllers` (API), `app/Models`, `app/Filters`
- **Database:** MySQL — `schema.sql` (import via phpMyAdmin)
- **Frontend:** static SPA in `public/` — `app.html`, `assets/css`, `assets/js`

## Structure

```
app/
  Config/Routes.php          API routes (all under /api/*)
  Filters/AuthFilter.php     session guard ('auth', 'auth:admin')
  Controllers/Api/*.php      Auth, State, Heads, Employees, Vouchers,
                             Invoices, Salary, Settings, Backup
  Models/*.php               one model per table + API field mapping
                             (users table = auth: type = admin | staff)
public/
  index.php                  CI4 front controller
  app.html                   SPA shell
  assets/css|js              extracted styles + app logic (fetch → /api)
schema.sql                   full database schema + default settings row
```

## Local run

```bash
mysql -uroot -proot -e "CREATE DATABASE bb_erp;"
mysql -uroot -proot bb_erp < schema.sql
php spark serve          # http://localhost:8080
```

Upgrading an existing database? Run `migrate_users.sql` once — it moves
admin credentials and employee logins into the `users` table.

Default login: **BBAccounts / admin2026** (hash is applied automatically on
first login; change it in Settings → Admin Login).

**Users:** the `users` table holds all logins — `type` is `admin` or
`staff`. Staff accounts are linked to an employee record (`employee_id`)
and are created/updated automatically when you enable "System Login" on an
employee. Admins are standalone `users` rows.

## Deploy to shared hosting

1. Create a MySQL database + user in cPanel, then import `schema.sql` in
   phpMyAdmin.
2. Upload the whole `bb-erp` folder. Point the domain/docroot at `public/`
   if possible; otherwise browse to `…/bb-erp/public/`.
3. Edit `app/Config/Database.php` (hostname, username, password, database)
   — or create a `.env` file with `database.default.*` keys.
4. If you use a `.env` or your host requires it, also set
   `app.baseURL = 'https://yourdomain.com/'`.
5. Make `writable/` writable by PHP (755 or 775).
6. If pretty URLs 404 on your host (no mod_rewrite), the app automatically
   falls back to `index.php/api/…` — no action needed.

## Security notes

- All credentials live in `users` (`type`: admin/staff), hashed with
  `password_hash()` (bcrypt). Plaintext seeds/imported passwords are
  upgraded to hashes automatically.
- All `/api/*` routes except `login`/`logout`/`me` require a session;
  mutations are admin-only unless noted.
- Staff logins can only create vouchers for heads assigned to their
  employee record (enforced server-side).
