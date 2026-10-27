# KúkiCakes E-Commerce Platform

A bespoke, responsive artisanal bakery and cake shop e-commerce platform built with PHP, MySQL/MariaDB, vanilla HTML5, CSS3, and modern JavaScript.

---

## 1. Project Overview & Features

KúkiCakes is a full-featured e-commerce platform tailored for custom cake ordering, inquiry management, and online payments:

- **Dynamic Catalog & Filtering:** 95 handcrafted cakes across 10 celebration categories (Birthday, Wedding, Anniversary, Cupcakes, Engagement, Graduation, Number, Party, Christmas, Others) with real-time text search, category filtering, and accurate sale-unit indicators.
- **Product Details & Customization:** Interactive modal with size selection, flavor options (Vanilla bean, Chocolate, Red velvet, Coffee, Butter cake), and custom message notes.
- **Customer Authentication:** Secure customer registration, bcrypt password hashing, session-based login/logout, profile management, and a hardened SHA-256 password reset flow with uniform neutral responses preventing account enumeration.
- **Persistent Shopping Cart:** Guest cart stored client-side in `localStorage`, automatically synchronized and merged with the customer's server-side MySQL cart upon login, and persisted across page reloads.
- **Checkout & 48-Hour Advance Notice Validation:** Strict server-side and client-side validation enforcing a mandatory 48-hour advance notice requirement for all orders (calculated in `Asia/Colombo` timezone).
- **Server-Authoritative Pricing:** Flat Rs. 500 delivery fee and line-item prices recalculated from the database, preventing client-side price tampering.
- **Order Tracking ("My Orders"):** Customer order history displaying order numbers (`KC-YYYYMMDD-XXXXXX`), line items, flavor choices, delivery dates, and order statuses.
- **PayHere Payment Gateway Integration:** Server-side PayHere Sandbox checkout parameter generation with MD5 hash security and an IPN (Instant Payment Notification) webhook listener.
- **Custom Cake Inquiry Workflow:** Multi-step custom inquiry submission with image attachment handling, rate limiting (HTTP 429), and status tracking.
- **Administrator Dashboard:** Role-based access control protecting administrative endpoints for product catalog management, order review, and custom cake inquiries.
- **Hardened Architecture:** Apache `.htaccess` directives denying direct HTTP access to database dumps, configuration files, environment variables, and migration scripts.

---

## 2. System Requirements

- **Operating System:** Windows 10 or Windows 11
- **Web Server:** Apache 2.4 (via XAMPP)
- **PHP:** PHP 8.1 or PHP 8.2 (with `pdo_mysql`, `curl`, `json`, `mbstring`, and `openssl` extensions enabled by default in XAMPP)
- **Database:** MySQL 5.7+ or MariaDB 10.4+ (InnoDB engine)
- **Web Browser:** Modern web browser (Google Chrome, Microsoft Edge, Mozilla Firefox)

---

## 3. Installation & Project Directory Setup

1. Copy or extract the project folder into your XAMPP web root directory:
   ```text
   C:\xampp\htdocs\KukiCakes-Website
   ```

2. Verify that the entry point `index.html` is located directly at:
   ```text
   C:\xampp\htdocs\KukiCakes-Website\index.html
   ```

> **Important:** Do NOT open `index.html` directly via `file:///` or via VS Code Live Server. The platform relies on backend PHP API endpoints in `api/` which require an active Apache web server.

---

## 4. Starting Apache and MySQL

1. Launch the **XAMPP Control Panel**.
2. Start the **Apache** service by clicking **Start**.
3. Start the **MySQL** service by clicking **Start**.
4. Ensure both services show green status indicators in the control panel.

---

## 5. Database Setup & Import

The master database dump is located at `database/kukicakes_db.sql`.

### Importing via phpMyAdmin:
1. Open your browser and navigate to:
   ```text
   http://localhost/phpmyadmin/
   ```
2. Click **New** in the left sidebar to create a database.
3. In the **Database name** field, enter:
   ```text
   kukicakes_db
   ```
4. Choose `utf8mb4_unicode_ci` as the collation and click **Create**.
5. Click on `kukicakes_db` in the left sidebar, then click the **Import** tab in the top navigation bar.
6. Under **File to import**, click **Choose File** (or **Browse**) and select:
   ```text
   C:\xampp\htdocs\KukiCakes-Website\database\kukicakes_db.sql
   ```
7. Click the **Import** (or **Go**) button at the bottom of the page.
8. Confirm that all 15 tables are imported successfully (e.g., `cake`, `category`, `customer`, `flavor`, `orders`, `order_item`, `administrator`, etc.).

---

## 6. Database Connection Configuration (`config/db.php`)

Database settings are defined in `config/db.php`:

```php
define('DB_HOST',    '127.0.0.1');
define('DB_PORT',    '3307');
define('DB_NAME',    'kukicakes_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');
```

### Adapting the MySQL Port:
- **Port 3307 (Current Project Default):** Used if your XAMPP MySQL/MariaDB service is configured on port `3307`.
- **Port 3306 (Standard XAMPP Default):** If your local XAMPP MySQL service runs on the default port `3306`, open `config/db.php` in a text editor and update line 18:
  ```php
  define('DB_PORT',    '3306');
  ```
- If your local MySQL `root` user has a password, set `DB_PASS` accordingly.

---

## 7. Running the Website

Once Apache and MySQL are running and the database is imported, open your web browser and navigate to:

```text
http://localhost/KukiCakes-Website/
```

### Navigation & Key Pages:
- **Storefront & Catalog:** `http://localhost/KukiCakes-Website/#cakes`
- **Customer Sign Up:** `http://localhost/KukiCakes-Website/#signup`
- **Customer Login:** `http://localhost/KukiCakes-Website/#login`
- **Password Reset:** `http://localhost/KukiCakes-Website/#forgot-password`
- **Shopping Cart:** `http://localhost/KukiCakes-Website/#cart`
- **Checkout:** `http://localhost/KukiCakes-Website/#checkout`
- **Customer Profile:** `http://localhost/KukiCakes-Website/#profile`
- **My Orders:** `http://localhost/KukiCakes-Website/#my-orders`
- **Custom Cake Inquiries:** `http://localhost/KukiCakes-Website/#inquiry`
- **Admin Panel:** `http://localhost/KukiCakes-Website/#admin`

---

## 8. User Accounts & Testing

### Customer Account:
To test customer features (cart synchronization, advance-notice checkout, and My Orders history):
1. Navigate to `#signup` (`http://localhost/KukiCakes-Website/#signup`).
2. Fill in your name, email, phone number, and password.
3. Submit the form to create an active test customer account.
4. You will be logged in automatically, and your session will persist across the storefront.

### Administrator Account:
Administrator privileges are required to access the admin portal (`#admin`), manage the cake catalog, update inquiry statuses, and review orders:
- The database schema includes an `administrator` table (`admin_id`, `username`, `email`, `password`, `created_at`).
- To configure an administrator credential for local evaluation, open phpMyAdmin, view the `administrator` table, and either use an existing admin record or update the `password` field with a fresh `password_hash()` value generated in PHP.

---

## 9. PayHere Payment Gateway (Sandbox Mode)

The website is configured with PayHere Sandbox integration for cashless card payments:

- **Configuration File:** `config/payhere.php`
- **Sandbox Mode:** Pre-configured with `define('PAYHERE_SANDBOX_MODE', true);`.
- **Sandbox Testing:** When checking out with the "Card Payment (PayHere)" option, the system generates secure transaction parameters and redirects to the PayHere Sandbox checkout modal.
- **Test Cards:** Evaluators can use the official PayHere Sandbox test cards (available on the PayHere official documentation) to test successful payments, cancellations, and declines.
- **IPN Webhook Notifications:** In a production or live environment, PayHere sends asynchronous server-to-server notifications (`notify_url`) to confirm payments. In a local XAMPP development environment, asynchronous webhook calls from PayHere require a public HTTPS tunnel (e.g., ngrok or Cloudflare Tunnel) pointing to `api/payhere-notify.php`. Storefront redirect callbacks (`return_url` and `cancel_url`) function directly on `localhost`.

---

## 10. Running Automated Tests

The project includes CLI-executable test scripts in the `tests/` directory to verify critical features and security constraints:

Open PowerShell or Command Prompt, navigate to the project directory, and execute the tests using the PHP CLI:

```powershell
cd C:\xampp\htdocs\KukiCakes-Website

# Verify Customer Orders API, access control, and IDOR immunity:
php tests/test_my_orders.php

# Verify Apache HTTP access controls and directory protection:
php scratch/test_apache_access_hardening.php

# Verify database connection failure handling & error sanitization:
php scratch/test_db_failure_simulation.php
```

---

## 11. Troubleshooting

| Issue | Likely Cause | Solution |
|---|---|---|
| **HTTP 500 / "A database error occurred"** | MySQL service stopped or incorrect port configured in `config/db.php`. | 1. Ensure MySQL is running in XAMPP.<br>2. Check MySQL port in XAMPP Control Panel.<br>3. If port is `3306`, update line 18 in `config/db.php` from `3307` to `3306`. |
| **HTTP 403 Forbidden on database or config files** | Access control rules in `.htaccess`. | This is intended security behavior. Direct web browser access to `.sql`, `.bak`, `.env`, and `config/` files is blocked to prevent data exposure. |
| **Page styling missing or API calls returning 404** | Project placed in a subdirectory different from `KukiCakes-Website`. | Ensure the folder name in `C:\xampp\htdocs\` is exactly `KukiCakes-Website`, or update the path accordingly. |
| **"Delivery date must be at least 48 hours in advance"** | Advance notice requirement for custom cakes. | In the checkout date selector, select a delivery date at least 2 calendar days ahead of the current date. |
| **Catalog displays "Catalog unavailable"** | Database table `cake` is empty or unimported. | Re-import `database/kukicakes_db.sql` into the `kukicakes_db` database via phpMyAdmin. |
