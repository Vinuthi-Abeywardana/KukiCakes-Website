# KúkiCakes E-Commerce Platform — Project & Database Audit Report

**Audit Date:** 2026-10-26  
**Audited Location:** `C:\xampp\htdocs\KukiCakes-Website`  
**Database:** `kukicakes_db` (MariaDB/MySQL, Port 3307)  
**Environment:** XAMPP on Windows, PHP 8.x, Apache  
**Mode:** Complete Read-Only Audit (No schema modifications, no database record changes, no file mutations)

---

## 1. Executive Summary

This comprehensive, read-only audit covers the entire KúkiCakes e-commerce platform, including:
- **Architecture & Entry Points:** Single-Page Application (SPA) hybrid architecture driven by [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html) and REST API endpoints in [api/](file:///c:/xampp/htdocs/KukiCakes-Website/api).
- **Database Schema:** 15 active tables in `kukicakes_db` (including foreign key relations, constraints, indexes, and legacy backup tables).
- **Security Assessment:** Identification of critical account-takeover vectors, web-accessible database diagnostic scripts, unauthenticated migration endpoints, source code leakage via backup files, and user enumeration risks.
- **Data & Catalog Integrity:** Detection of pricing and description divergence between client-side fallback data ([js/data.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/data.js)) and authoritative database records ([cake](file:///c:/xampp/htdocs/KukiCakes-Website/database/migrations/002_update_product_prices_and_units.sql)).
- **Code Maintenance:** Identification of duplicated HTML component fragments, obsolete scripts, and misleading project documentation.

All findings are classified by severity (**Critical**, **High**, **Medium**, **Low**), complete with exact file paths, line references, observable evidence, proposed fixes, and verification test specifications. Sensitive credentials, payment secrets, password hashes, and reset tokens have been strictly redacted.

---

## 2. Project Architecture & Runtime Mapping

### 2.1 Application Entry Point & Runtime Loading Flow

The application operates as a client-side Single-Page Application (SPA) backed by server-side PHP API endpoints.

```
                              ┌───────────────────────────────────┐
                              │     Browser / User Request        │
                              └─────────────────┬─────────────────┘
                                                │
                                                ▼
                              ┌───────────────────────────────────┐
                              │            index.html             │
                              │ (Inlines Storefront: Home, Cakes, │
                              │ Cart, Checkout, Confirmation,     │
                              │ Modal, Toast, Footer)             │
                              └───────┬───────────────────┬───────┘
                                      │                   │
                     ┌────────────────┴──────┐     ┌──────┴────────────────┐
                     │ Stylesheets           │     │ Core Client Scripts   │
                     │ (via style.css)       │     │                       │
                     ├───────────────────────┤     ├───────────────────────┤
                     │ - css/base.css        │     │ 1. js/data.js         │
                     │ - css/header.css      │     │ 2. js/site.js         │
                     │ - css/home.css        │     │ 3. js/auth-loader.js  │
                     │ - css/pages.css       │     │ 4. js/admin.js        │
                     │ - css/responsive.css  │     │ 5. payhere.js (CDN)   │
                     │ - css/auth.css        │     └───────────┬───────────┘
                     │ - css/admin.css       │                 │
                     └───────────────────────┘                 ▼
                                                   ┌───────────────────────┐
                                                   │   js/auth-loader.js   │
                                                   │ (Asynchronously loads │
                                                   │  auth components):    │
                                                   ├───────────────────────┤
                                                   │ - login.html          │
                                                   │ - signup.html         │
                                                   │ - profile.html        │
                                                   │ - my-orders.html      │
                                                   │ - auth-guard.html     │
                                                   │ - reset-password.html │
                                                   │ - admin.html          │
                                                   └───────────┬───────────┘
                                                               │
                                                               ▼
                                                   ┌───────────────────────┐
                                                   │ Dynamic Script Load:  │
                                                   │   js/auth.js?v=...    │
                                                   └───────────────────────┘
```

### 2.2 Active vs. Dead / Orphaned Files

| File | Status | Role & Analysis |
|---|---|---|
| [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html) | **Active** | Primary application shell; inlines storefront markup (home, cakes, gallery, cart, checkout, confirmation, modal, toast, footer). |
| [style.css](file:///c:/xampp/htdocs/KukiCakes-Website/style.css) | **Active** | Primary CSS aggregator importing all 7 stylesheets in [css/](file:///c:/xampp/htdocs/KukiCakes-Website/css). |
| [js/data.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/data.js) | **Active (Flawed)** | Stores static product/gallery definitions; loaded first by [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html#L529). Stale prices and copy-paste descriptions. |
| [js/site.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/site.js) | **Active** | Storefront controller (navigation, catalog rendering, cart state, checkout form validation, 48h delivery rule). |
| [js/auth-loader.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/auth-loader.js) | **Active** | Asynchronously fetches 7 auth/admin templates from [components/](file:///c:/xampp/htdocs/KukiCakes-Website/components) and injects [js/auth.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/auth.js). |
| [js/auth.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/auth.js) | **Active** | Authentication controller (login, signup, session sync, profile, orders history, address book, policy modal). |
| [js/admin.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/admin.js) | **Active** | Administrative dashboard controller (orders, inventory, products, inquiries, status changes). |
| [js/app.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/app.js) | **Dead / Orphaned** | Legacy component-loader script. Not referenced anywhere in the repository. |
| [script.js](file:///c:/xampp/htdocs/KukiCakes-Website/script.js) | **Dead / Orphaned** | 2-line file containing `import './js/site.js';`. Not referenced anywhere. |
| [docs/temporary.html](file:///c:/xampp/htdocs/KukiCakes-Website/docs/temporary.html) | **Dead / Obsolete** | 40.5 KB standalone monolithic HTML prototype. Contains legacy styles and hardcoded products. |
| [components/cakes.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/cakes.html) | **Unused Duplicate** | Identical/divergent copy of cakes section already embedded in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). Not fetched by [auth-loader.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/auth-loader.js). |
| [components/cart.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/cart.html) | **Unused Duplicate** | Duplicate of cart section in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |
| [components/checkout.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/checkout.html) | **Unused Duplicate** | Duplicate of checkout section in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). Requires parallel maintenance. |
| [components/confirmation.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/confirmation.html) | **Unused Duplicate** | Duplicate of confirmation section in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |
| [components/home.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/home.html) | **Unused Duplicate** | Duplicate of homepage section in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |
| [components/footer.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/footer.html) | **Unused Duplicate** | Duplicate of footer in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |
| [components/header.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/header.html) | **Unused Duplicate** | Duplicate of navigation header in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |
| [components/modal.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/modal.html) | **Unused Duplicate** | Duplicate of product modal in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |
| [components/toast.html](file:///c:/xampp/htdocs/KukiCakes-Website/components/toast.html) | **Unused Duplicate** | Duplicate of toast notification in [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html). |

---

## 3. Database Architecture & Schema Review

### 3.1 Active Database Overview (`kukicakes_db`)

The database runs on `127.0.0.1:3307` and contains 15 tables:

| Table Name | Rows | Primary Key | Key Relationships / Foreign Keys | Indexes |
|---|---|---|---|---|
| `administrator` | 1 | `admin_id` | Standalone authentication table for administrative staff. | `uq_username`, `uq_email` |
| `cake` | 95 | `cake_id` | Referenced by `cake_category`, `cake_flavor`, `order_item`. | `PRIMARY` |
| `cake_backup_20261025` | 95 | None | **Legacy backup table** created during Week 8 price migration. | No indexes |
| `cake_category` | 110 | `(cake_id, category_id)` | Junction: `cake.cake_id`, `category.category_id`. | Composite PK |
| `cake_flavor` | 0 | `(cake_id, flavor_id)` | Junction: `cake.cake_id`, `flavor.flavor_id`. **Currently empty**. | Composite PK |
| `category` | 10 | `category_id` | Referenced by `cake_category`. | `uq_category_name` |
| `customer` | 24 | `customer_id` | Referenced by `orders`, `user_cart_items`, `user_addresses`, `password_resets`, `inquiry`. | `uq_email` |
| `flavor` | 6 | `flavor_id` | Referenced by `cake_flavor`, `inquiry`, `order_item`. | `uq_flavor_name` |
| `inquiry` | 3 | `inquiry_id` | `customer_id` (nullable), `status_id`, `flavor_id` (nullable). | `fk_inq_customer`, `fk_inq_status`, `fk_inq_flavor` |
| `inquiry_status` | 6 | `status_id` | Referenced by `inquiry`. | `uq_inquiry_status_name` |
| `order_item` | 16 | `order_item_id` | `order_id` (cascade), `cake_id` (restrict/set null), `flavor_id`. | `fk_oi_order`, `fk_oi_cake`, `fk_oi_flavor` |
| `orders` | 13 | `order_id` | `customer_id` (fk_ord_customer). | `uq_order_number`, `fk_ord_customer` |
| `password_resets` | 7 | `reset_id` | `customer_id` (fk_reset_customer, cascade). | `idx_token_hash`, `idx_customer_id` |
| `user_addresses` | 2 | `address_id` | `customer_id` (fk_addr_customer, cascade). | `fk_addr_customer` |
| `user_cart_items` | 15 | `cart_item_id` | `customer_id` (fk_cart_customer, cascade). **No FK on `product_id`**. | `idx_cart_customer` |

### 3.2 Schema & Architectural Inconsistencies

1. **Missing Foreign Key on `user_cart_items.product_id`:**  
   `user_cart_items` defines `product_id INT(11) NOT NULL`, but lacks a foreign key constraint referencing `cake(cake_id)`. If a product is deleted from `cake`, orphaned items will persist in customer carts.
2. **Unused Junction Table `cake_flavor` (0 Rows):**  
   The schema includes `cake_flavor` to associate specific flavors with specific cakes, but the table is empty. The storefront modal allows choosing any flavor from `flavor` for all cakes.
3. **Dual Admin Identity Architecture:**  
   - `customer` table contains `role ENUM('customer', 'admin')`.
   - `administrator` table contains dedicated admin credentials (`admin_id`, `username`, `password`, `email`).
   - In [api/bootstrap.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/bootstrap.php#L66-L82), `requireAdmin()` requires `!empty($_SESSION['admin_id'])`. If a customer record has `role = 'admin'`, [api/login.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/login.php#L88-L92) does not set `admin_id`, locking them out of admin functionality.
4. **Residual Backup Table in Production DB:**  
   `cake_backup_20261025` is an unindexed snapshot table with 95 rows left in `kukicakes_db`.

---

## 4. Detailed Audit Findings by Priority

```
┌────────────────────────────────────────────────────────────────────────┐
│                          FINDINGS DASHBOARD                            │
├────────────────────────────────┬───────────────────────────────────────┤
│ CRITICAL PRIORITY (4 Findings) │ High-risk security vulnerabilities     │
│ HIGH PRIORITY (4 Findings)     │ Data divergence & information leaks   │
│ MEDIUM PRIORITY (4 Findings)   │ Architectural drift & code duplication │
│ LOW PRIORITY (3 Findings)      │ Documentation & cosmetic glitches     │
└────────────────────────────────┴───────────────────────────────────────┘
```

---

### 4.1 Critical Priority Findings

#### [CRIT-01] Account Takeover & Reset Token Leakage via Development Fallback
- **File:** [api/forgot-password.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/forgot-password.php#L110-L117)
- **Evidence:**
  ```php
  // Lines 110-116 of api/forgot-password.php
  if (!$hasMailer) {
      $response['dev_note']    = 'Email delivery is not configured on this server. Use the reset token below to test password reset.';
      $response['reset_token'] = $plainToken;
      $response['reset_url']   = '#reset-password?token=' . $plainToken;
  }
  ```
- **Vulnerability:** When SMTP or `sendmail` is not configured (standard on XAMPP and many staging/production servers), the API responds to any forgot-password request with the unhashed, plaintext `reset_token` and `reset_url`. Anyone can submit an arbitrary user email to `/api/forgot-password` and immediately receive the token to reset the victim's password.
- **Proposed Fix:** Remove the `$response['reset_token']` exposure from public API responses. In local development or automated testing, write the token to a secure server-side log or an admin-only testing endpoint with strict IP/environment restrictions.
- **Verification Test:** Send `POST /api/forgot-password` with a valid customer email. Verify the response JSON contains `success: true` and a generic message, but **never** contains `reset_token`, `reset_url`, or `dev_note`.

#### [CRIT-02] Source Code Disclosure via Plaintext `.bak` Files in Web Root
- **Files:**
  - `api/bootstrap.php.task2.bak`
  - `api/check-session.php.task2.bak`
  - `api/login.php.task2.bak`
  - `api/profile.php.task2.bak`
  - `js/auth-loader.js.task2.bak`
  - `js/auth.js.task2.bak`
  - `js/site.js.task1_pre.bak`
  - `js/site.js.task2.bak`
  - `js/site.js.week8.bak`
- **Evidence:** These backup files are physically present in `api/` and `js/` in the Apache document root. While `.gitignore` hides them from Git, Apache is not configured to execute `.bak` files through the PHP engine. A direct HTTP GET to `/api/login.php.task2.bak` or `/api/bootstrap.php.task2.bak` returns raw PHP source code in plaintext, exposing internal business logic and queries.
- **Proposed Fix:** Permanently remove all `.bak` files from the web root and configure Apache in `.htaccess` to deny access to `*.bak` files:
  ```apache
  <FilesMatch "\.(bak|inc|old|save|tmp)$">
      Require all denied
  </FilesMatch>
  ```
- **Verification Test:** Execute HTTP GET to `http://localhost/KukiCakes-Website/api/bootstrap.php.task2.bak`. Verify that the server returns HTTP 404 or 403 Forbidden.

#### [CRIT-03] Unauthenticated Web-Accessible Database Migration Scripts
- **Files:**
  - [api/db_migrate.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/db_migrate.php)
  - [api/db_migrate_categories.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/db_migrate_categories.php)
  - [api/db_migrate_week8.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/db_migrate_week8.php)
- **Evidence:** These migration scripts reside directly inside `api/`. They execute `ALTER TABLE`, `CREATE TABLE`, and seed data using `require_once __DIR__ . '/../config/db.php'` without any session verification, authentication guard, or CLI restriction. Any user visiting `http://localhost/KukiCakes-Website/api/db_migrate.php` triggers database DDL operations.
- **Proposed Fix:** Move all migration scripts out of `api/` into `database/migrations/` and enforce a CLI-only execution guard at the top of every migration file:
  ```php
  if (php_sapi_name() !== 'cli') {
      http_response_code(403);
      exit('Forbidden: Migration scripts can only be executed via the command line.');
  }
  ```
- **Verification Test:** Send a web browser request to the migration URL. Verify that the server returns HTTP 403 Forbidden.

#### [CRIT-04] Live Test Script in API Web Directory Performing Database Mutations
- **File:** [api/regression_login.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/regression_login.php)
- **Evidence:** Lines 33-38 execute live SQL mutations on the customer table:
  ```php
  $pdo->prepare("DELETE FROM customer WHERE email='regrtest@kukicakes.lk'")->execute();
  ```
  The file is accessible over HTTP at `/api/regression_login.php` without authentication.
- **Proposed Fix:** Relocate `api/regression_login.php` to `tests/test_regression_login.php` alongside existing automated test suites.
- **Verification Test:** Verify that `tests/test_regression_login.php` executes properly via CLI, while no test files exist in `api/`.

---

### 4.2 High Priority Findings

#### [HIGH-01] User Account Enumeration via Forgot-Password Endpoint
- **File:** [api/forgot-password.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/forgot-password.php#L68-L72, #L107)
- **Evidence:**
  ```php
  // Inactive or non-existent account:
  respond([
      'success'        => true,
      'message'        => $genericMessage,
      'email_delivery' => false,
      'account_found'  => false, // <-- Distinguishes account status
  ]);

  // Existing active account:
  $response = [
      'success'        => true,
      'message'        => $genericMessage,
      'email_delivery' => $hasMailer,
      'account_found'  => true,  // <-- Leaks account existence
  ];
  ```
- **Vulnerability:** Despite displaying a generic message string, the JSON response explicitly differentiates registered accounts (`account_found: true`) from non-existent accounts (`account_found: false`), allowing attackers to harvest registered customer email addresses.
- **Proposed Fix:** Remove `account_found` from all response payloads. Always return uniform response keys:
  ```php
  respond([
      'success' => true,
      'message' => 'If an account exists with that email address, password reset instructions have been generated.',
  ]);
  ```
- **Verification Test:** Send `POST /api/forgot-password` with both an existing email and a non-existent email. Confirm both responses return identical JSON keys and HTTP status codes.

#### [HIGH-02] Stale Prices and Corrupted Descriptions in `js/data.js`
- **File:** [js/data.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/data.js#L1-L50)
- **Evidence:**
  - Product 1 price is hardcoded as `10500` (Approved database price: `10300`).
  - Product 2 price is hardcoded as `9000` (Approved database price: `10100`).
  - Cupcake products (IDs 75–84) have corrupted copy-paste descriptions referencing graduation cakes and number cakes (e.g. Line 38: *"Vanilla celebration cake with a custom graduation message"* for Blush Rose Swirl Cupcakes).
  - [js/site.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/site.js#L2) initializes `products = window.kukiData.products` before `loadProducts()` completes its asynchronous fetch from [api/products.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/products.php). If the network is throttled or the API fails, the user is presented with incorrect pricing and invalid descriptions.
- **Proposed Fix:** Synchronize `catalogProducts` in `js/data.js` with the authoritative prices from the approved worksheet / migration 002, and correct cupcake descriptions. Alternatively, initialize `products = []` and show a loading skeleton until [api/products.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/products.php) resolves.
- **Verification Test:** Compare all 95 product entries in `js/data.js` against `api/products.php` output and verify 100% price and description parity.

#### [HIGH-03] Database Connection Error Message Leakage
- **File:** [config/db.php](file:///c:/xampp/htdocs/KukiCakes-Website/config/db.php#L52-L57)
- **Evidence:**
  ```php
  try {
      $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
  } catch (PDOException $e) {
      http_response_code(500);
      exit('Database connection failed: ' . $e->getMessage());
  }
  ```
- **Vulnerability:** When the database service is restarting, misconfigured, or unreachable, `$e->getMessage()` outputs internal network details, usernames, ports, and socket paths directly to visitors.
- **Proposed Fix:** Log the exception message internally and output a generic user-safe error message:
  ```php
  catch (PDOException $e) {
      error_log('[KukiCakes DB Connection Error] ' . $e->getMessage());
      http_response_code(500);
      exit('A database error occurred. Please try again later.');
  }
  ```
- **Verification Test:** Temporarily set an invalid DB port in a local scratch script and confirm the browser output displays only the generic message while the stack trace is written to PHP's error log.

#### [HIGH-04] Direct Web Access to Database Backups Directory
- **Path:** `database/backups/`
- **Evidence:** Files `cake_table_backup_20261025.sql` and `cake_table_backup_20261025.json` reside in `database/backups/`. Unlike `config/` (which has `.htaccess` containing `Require all denied`), `database/` has no `.htaccess` file. In standard Apache installations, navigating to `/database/backups/cake_table_backup_20261025.sql` permits direct downloading of table schema and backup data.
- **Proposed Fix:** Add `.htaccess` to `database/` denying all web requests:
  ```apache
  <IfModule mod_authz_core.c>
      Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
      Deny from all
  </IfModule>
  ```
- **Verification Test:** Attempt an HTTP GET to `http://localhost/KukiCakes-Website/database/backups/cake_table_backup_20261025.sql`. Verify the server returns HTTP 403 Forbidden.

---

### 4.3 Medium Priority Findings

#### [MED-01] Architecture Drift: Monolithic `index.html` vs. Fragmented `components/`
- **Files:** [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html) vs. `components/*.html`
- **Evidence:** `components/checkout.html`, `components/home.html`, `components/cakes.html`, `components/cart.html`, `components/confirmation.html`, `components/footer.html`, `components/modal.html`, and `components/toast.html` are unreferenced duplicates of code already embedded in `index.html`. Only 7 auth/admin components are dynamically fetched by `js/auth-loader.js`. Edits made to `index.html` (such as removing "Bank transfer") must be manually synchronized to `components/checkout.html`.
- **Proposed Fix:** Document this architectural design explicitly in `README.md`. Decide either to fully modularize `index.html` (fetching all components dynamically) or maintain `index.html` as the authoritative source and archive non-auth components.
- **Verification Test:** Verify that any UI changes in `index.html` and `components/` are audited for parity.

#### [MED-02] Obsolete Scripts: `js/app.js` and `script.js`
- **Files:** [js/app.js](file:///c:/xampp/htdocs/KukiCakes-Website/js/app.js) and [script.js](file:///c:/xampp/htdocs/KukiCakes-Website/script.js)
- **Evidence:** Neither file is included in `index.html` or imported by any active module. `js/app.js` references obsolete component IDs (`#site-header`, `#site-footer`, `#site-modal`).
- **Proposed Fix:** Archive or remove `js/app.js` and `script.js` to avoid developer confusion.
- **Verification Test:** Verify application functions identically without these files.

#### [MED-03] Administrative Role Inconsistency Across `customer` and `administrator` Tables
- **Files:** [api/bootstrap.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/bootstrap.php#L66-L82), [api/login.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/login.php#L88-L92, #L144-L150)
- **Evidence:** `customer.role` includes `'admin'`, but `requireAdmin()` strictly inspects `$_SESSION['admin_id']`, which is only set when logging in through `administrator`. Setting `customer.role = 'admin'` does not grant administrative privileges.
- **Proposed Fix:** Either remove `'admin'` from `customer.role` ENUM or clarify in documentation that administrator accounts must reside exclusively in the `administrator` table.
- **Verification Test:** Verify admin login through `administrator` table works as intended and customer sessions cannot escalate privileges.

#### [MED-04] Missing Foreign Key on `user_cart_items.product_id`
- **Table:** `user_cart_items`
- **Evidence:** `user_cart_items.product_id` is an `INT(11)` without a foreign key constraint to `cake(cake_id)`.
- **Proposed Fix:** Add a foreign key constraint:
  ```sql
  ALTER TABLE `user_cart_items`
      ADD CONSTRAINT `fk_cart_cake` FOREIGN KEY (`product_id`)
      REFERENCES `cake` (`cake_id`) ON DELETE CASCADE ON UPDATE CASCADE;
  ```
- **Verification Test:** Verify cart items for deleted cakes are cleanly removed or handled.

---

### 4.4 Low Priority Findings

#### [LOW-01] Character Encoding Replacement Glitch in `index.html` Title
- **File:** [index.html](file:///c:/xampp/htdocs/KukiCakes-Website/index.html#L8)
- **Evidence:** Line 8 contains:
  ```html
  <title>KukiCakes  Cakes made for your moments</title>
  ```
  The byte `0xFD` or invalid UTF-8 sequence renders as a replacement diamond ``.
- **Proposed Fix:** Replace with an HTML entity or standard em-dash:
  ```html
  <title>KúkiCakes — Cakes made for your moments</title>
  ```
- **Verification Test:** Inspect document title in browser tab and confirm clean rendering.

#### [LOW-02] Severely Misleading `README.md`
- **File:** [README.md](file:///c:/xampp/htdocs/KukiCakes-Website/README.md)
- **Evidence:** Claims the project is a static site: *"No server, package, library, or framework is required. Open index.html directly in a browser"*. In reality, opening `index.html` directly via `file://` fails completely because all product catalog loading, user authentication, cart synchronization, orders, and payment webhooks require Apache, PHP, and MySQL.
- **Proposed Fix:** Rewrite `README.md` to document the actual XAMPP setup (Apache, PHP 8, MySQL port 3307, database `kukicakes_db`, PayHere configuration).
- **Verification Test:** Follow revised `README.md` instructions on a clean setup to verify accuracy.

#### [LOW-03] Residual Standalone Prototype in Documentation
- **File:** [docs/temporary.html](file:///c:/xampp/htdocs/KukiCakes-Website/docs/temporary.html)
- **Evidence:** 40.5 KB file containing obsolete styling, old navigation structure, and outdated product lists.
- **Proposed Fix:** Archive or document as legacy reference artifact.
- **Verification Test:** Confirm removal does not break any documentation links.

---

## 5. Prioritized Action Plan

### Phase 1: Immediate Security Hardening (Confirmed Vulnerabilities)
1. **Disable Reset Token Exposure:** Remove `$response['reset_token']` from [api/forgot-password.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/forgot-password.php).
2. **Prevent Account Enumeration:** Remove `'account_found'` flag from [api/forgot-password.php](file:///c:/xampp/htdocs/KukiCakes-Website/api/forgot-password.php).
3. **Purge Web Root `.bak` Files:** Delete all `.bak` backup files from `api/` and `js/`.
4. **Lock Down Protected Directories:** Add `.htaccess` (`Require all denied`) to `database/`.
5. **Secure Database Connection Errors:** Sanitize exception output in [config/db.php](file:///c:/xampp/htdocs/KukiCakes-Website/config/db.php).
6. **Relocate Migrations & Test Scripts:** Move `api/db_migrate*.php` to `database/migrations/` and `api/regression_login.php` to `tests/`.

### Phase 2: Data Parity & Catalog Integrity
1. **Update `js/data.js` Prices & Descriptions:** Sync Product 1 (Rs. 10,300), Product 2 (Rs. 10,100), and all other products with authoritative database pricing. Correct cupcake descriptions.
2. **Catalog Fallback Handling:** Ensure `site.js` gracefully handles catalog loading state without flashing stale hardcoded prices.

### Phase 3: Code Cleanup & Maintenance
1. **Remove Dead Scripts:** Remove or archive `js/app.js` and `script.js`.
2. **Fix Encoding Glitch:** Fix title replacement character in `index.html`.
3. **Update Documentation:** Rewrite `README.md` to accurately reflect the PHP/MySQL XAMPP architecture.
4. **Drop Legacy DB Table:** Once confirmed by project stakeholders, drop `cake_backup_20261025` from `kukicakes_db`.

---

## 6. Verification Status Matrix

| Finding ID | Classification | Current Status | Requires Manual Verification? |
|---|---|---|---|
| **CRIT-01** | Account Takeover / Token Leak | **Confirmed Problem** | No (Code inspection confirms plaintext token in JSON) |
| **CRIT-02** | Plaintext Source Disclosure (.bak) | **Confirmed Problem** | No (Files confirmed present in Apache web root) |
| **CRIT-03** | Unauthenticated Migration Endpoints | **Confirmed Problem** | No (Code inspection confirms no auth checks) |
| **CRIT-04** | Live Test Script in Web API Directory | **Confirmed Problem** | No (Confirmed present in api/ with live DELETE statements) |
| **HIGH-01** | User Account Enumeration | **Confirmed Problem** | No (Code inspection confirms explicit `account_found` boolean) |
| **HIGH-02** | Data Divergence in `js/data.js` | **Confirmed Problem** | No (Confirmed Rs. 10500 vs Rs. 10300 in DB) |
| **HIGH-03** | DB Connection Error Leak | **Confirmed Problem** | No (Code inspection confirms `$e->getMessage()` in response) |
| **HIGH-04** | Direct Access to DB Backups | **Confirmed Problem** | No (Directory lacks `.htaccess`) |
| **MED-01** | Architectural Template Duplication | **Confirmed Architecture Drift** | Yes (Team alignment on monolithic vs. componentized architecture) |
| **MED-02** | Obsolete Scripts (`app.js`, `script.js`) | **Confirmed Dead Code** | No (Grep confirms zero references) |
| **MED-03** | Dual Admin Role Architecture | **Confirmed Inconsistency** | Yes (Team alignment on whether customer.role='admin' should exist) |
| **MED-04** | Missing FK on `user_cart_items` | **Confirmed Schema Gap** | Yes (Verify whether orphaned cart items should cascade delete) |
| **LOW-01** | Replacement Character in Title | **Confirmed Glitch** | No (Byte sequence verified in `index.html` line 8) |
| **LOW-02** | Misleading `README.md` | **Confirmed Inaccuracy** | No (Text claims static HTML with no backend) |
| **LOW-03** | Legacy Prototype in `docs/` | **Confirmed Obsolete File** | Yes (Check if team keeps prototypes for course submissions) |
