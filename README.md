# CampusCoin

**Smart Money Management for Students.** Track income and expenses, set monthly
budgets, and get saving tips built from your own spending — all in a clean,
fintech-style interface.

CampusCoin is a plain PHP + MySQL web application. No frameworks, no build
step, no Composer — just PHP, vanilla JavaScript, and Chart.js. It is built to
run on XAMPP out of the box.

---

## Features

**For students**
- Dashboard with balance, income/expense totals, budget progress and charts
- Add / edit / delete income and expense transactions
- Custom categories alongside a shared set of defaults
- Monthly budgets per category with color-coded progress (on track → exceeded)
- Reports: daily, weekly and monthly charts, category breakdown, 6-month trend, printable / savable as PDF
- Saving tips generated from your own transactions (budget alerts, food-delivery detection, category-share rules, month-over-month trend, savings-goal tracking) — plus tips published by the CampusCoin team
- AI Insights page: a plain-language monthly summary and a category-suggestion tool (works with or without AI configured)
- Profile with avatar upload and password change

**For admins**
- Dashboard with platform-wide stats and charts
- User management: search, disable/enable accounts, reset a student's password
- Manage the default categories every student starts with
- Publish saving tips and dashboard announcements
- Platform statistics: 12-month growth, income/expense trend, top categories

**Security**
- Passwords hashed with bcrypt, session fixation protection, CSRF tokens on every form, brute-force login throttling, ownership checks on every student action, safe file uploads (validated MIME type, random filenames, no PHP execution in the uploads folder), parameterized SQL everywhere.

---

## Tech stack

| Layer      | Technology |
|------------|------------|
| Backend    | PHP 8+ (no framework), PDO for MySQL |
| Database   | MySQL / MariaDB |
| Frontend   | Vanilla HTML/CSS/JavaScript, [Chart.js](https://www.chartjs.org/) (loaded from CDN) |
| Server     | Apache (via XAMPP) |
| AI (optional) | Anthropic or any OpenAI-compatible API, called directly via cURL — no SDK |

---

## Requirements

- [XAMPP](https://www.apachefriends.org/) (or any Apache + PHP 8+ + MySQL stack)
- PHP 8.0 or newer with the `pdo_mysql`, `curl`, `fileinfo` and `gd` extensions (all enabled by default in XAMPP)
- A modern browser

---

## Setup (XAMPP)

### 1. Copy the project

Extract this folder into your XAMPP `htdocs` directory so the path is:

```
C:\xampp\htdocs\CampusCoin      (Windows)
/Applications/XAMPP/htdocs/CampusCoin   (macOS)
/opt/lampp/htdocs/CampusCoin    (Linux)
```

### 2. Start Apache and MySQL

Open the XAMPP Control Panel and start **Apache** and **MySQL**.

### 3. Import the database

1. Open **phpMyAdmin** — `http://localhost/phpmyadmin`
2. Click **Import**
3. Choose the file `database/campuscoin.sql` from this project
4. Click **Go**

This creates a `campuscoin` database with all tables and demo data. (The
script drops and recreates the database, so it is safe to re-run if you want
to reset everything.)

> Prefer the command line? `mysql -u root -p < database/campuscoin.sql`
> (leave the password empty and just press Enter on a default XAMPP install).

### 4. Configure the app

Copy `.env.example` to `.env` in the project root:

```
cp .env.example .env
```

The defaults already match a fresh XAMPP install (`root` user, empty
password, database name `campuscoin`), so for local development **you often
don't need to change anything**. If your MySQL setup differs, edit the
`DB_*` values in `.env`.

### 5. Open the app

Visit:

```
http://localhost/CampusCoin/
```

### 6. Log in

| Role    | Email                     | Password     |
|---------|---------------------------|--------------|
| Admin   | admin@campuscoin.com      | Admin@123    |
| Student | student@campuscoin.com    | Student@123  |

The demo student account already has a few months of sample transactions and
budgets so the dashboard and reports aren't empty on first login. You can also
register a brand-new student account from the landing page at any time.

---

## Password reset in local development

XAMPP does not include a mail server, so "Forgot password" emails can't
actually be delivered locally. When `APP_ENV=development` (the default), the
**Forgot password** page shows the reset link directly on screen after you
submit the form, so you can test the full flow without configuring email. In
a real deployment, wire up `mail()` (or replace it with a proper mailer) and
set `APP_ENV=production` to hide the on-screen link.

---

## Optional: AI-powered insights

CampusCoin works completely on its own using rule-based logic — enabling AI
is optional and only adds a bit of polish (a more natural monthly summary,
slightly smarter category suggestions). Only **aggregated numbers** (monthly
totals per category) are ever sent to the AI provider — never your name,
email, or individual transaction descriptions beyond the single line you're
categorizing.

To enable it, edit `.env`:

```
AI_ENABLED=true
AI_PROVIDER=anthropic          # or "openai" (any OpenAI-compatible API)
AI_API_KEY=your-api-key-here
AI_MODEL=claude-sonnet-5       # or e.g. gpt-4o-mini
```

If AI is disabled, misconfigured, or a request fails for any reason,
CampusCoin automatically falls back to its built-in rule-based logic — the
app never breaks because of AI.

---

## Folder structure

```
CampusCoin/
├── index.php                 Landing page
├── login.php / register.php / forgot-password.php / reset-password.php / logout.php
├── 404.php / 403.php         Error pages
├── privacy.php / terms.php   Legal pages
├── .env.example               Copy to .env and configure
├── .htaccess                  Blocks .env/.sql/.log, custom 404
│
├── config/
│   ├── config.php             App constants, env() loader, security headers
│   └── database.php           PDO connection + query helpers
│
├── includes/                  Shared PHP: auth, layout, helpers, AI service, tips engine
│
├── assets/
│   ├── css/                   style.css (design system), responsive.css
│   ├── js/                    app.js, validation.js, charts.js, dashboard.js
│   ├── images/                Logo, favicon, illustrations
│   └── uploads/avatars/       Profile photo uploads (PHP execution disabled here)
│
├── api/
│   └── suggest-category.php   JSON endpoint used by the "Add expense" form
│
├── student/                   Dashboard, transactions, budgets, categories,
│                               reports, saving tips, AI insights, profile
│
├── admin/                      Dashboard, users, categories, tips,
│                               announcements, statistics
│
└── database/
    └── campuscoin.sql          Full schema + seed data
```

---

## Notes on the design

- Colors: navy `#0B1B3A` (primary), emerald `#10B981` (positive/accent), soft
  blue `#3B82F6` (secondary accent), with coral/amber for warnings and alerts.
- Typeface: [Plus Jakarta Sans](https://fonts.google.com/specimen/Plus+Jakarta+Sans).
- Currency is displayed as `Rs.` throughout (see `CURRENCY` in `config/config.php`
  if you need to change it).
- Charts are drawn with Chart.js, loaded from a CDN with a graceful fallback
  message if it can't load (e.g. no internet access).

## Troubleshooting

- **"We can't reach the database"** — make sure MySQL is running in XAMPP and
  that you've imported `database/campuscoin.sql`.
- **Blank page / PHP errors** — set `APP_DEBUG=true` in `.env` to see the
  underlying error, and check `php_error.log` in your XAMPP install.
- **Styles look broken** — make sure you're accessing the app through
  `http://localhost/CampusCoin/` (not by opening the HTML files directly),
  so relative asset paths resolve correctly.
- **Avatar upload fails** — check that `assets/uploads/avatars/` is
  writable by the web server.
