# TechNest — Electronics E-Commerce Platform

**CIT6224 Web Application Development — Group 10**

TechNest is a full-featured PHP/MySQL e-commerce web application for selling electronics. Built without any frontend framework, it demonstrates core web development concepts: relational database design, server-side scripting, session-based authentication, role-based access control, CSRF protection, and client-side validation.

---

## Group Members

| Name | Module |
|------|--------|
| Sulaiman | Authentication, Cart & Checkout |
| Abdelaziz | Admin Panel, Database & Payment |
| Kashtu | Frontend, Products & Reviews |

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP 8.x |
| Database | MySQL / MariaDB (via XAMPP) |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Server | Apache (XAMPP) |
| DB Access | PDO with prepared statements |
| Charts | Chart.js (CDN, reports only) |
| Image upload | PHP `finfo` MIME validation + `move_uploaded_file` |

No UI framework (Bootstrap, Tailwind, etc.) is used. All CSS is hand-written in `assets/css/style.css` and `assets/css/admin.css`.

---

## Setup

### Requirements

- XAMPP with Apache + MySQL running
- PHP 8.0+
- MySQL port **3308** (default on this machine — change in `includes/config.php` if needed)

### Installation

1. Copy the project folder into `C:\xampp\htdocs\technest\`
2. Open **phpMyAdmin** → Import → select `sql/technest.sql` → Go
3. Visit `http://localhost/technest/`

### Database Config (`includes/config.php`)

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3308');   // change if your MySQL uses a different port
define('DB_NAME', 'technest');
define('DB_USER', 'root');
define('DB_PASS', '');
```

---

## Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@technest.com | Admin@123 |
| Customer | customer@technest.com | Customer@123 |
| Seller | create via Admin → Manage Users | — |
| Delivery | create via Admin → Manage Users | — |

---

## Actors & Roles

The system has four roles enforced at both route level and session level.

### Customer
- Browse products by category, search, filter by price/brand/rating
- Add to cart, apply coupon codes, proceed to checkout
- Pay via simulated Card, E-Wallet, or Cash on Delivery
- View order history and payment status
- Submit product reviews and star ratings (only for delivered orders)
- Manage wishlist (save/remove products)
- Open support tickets and chat with admin

### Admin
- Full product CRUD (add/edit/delete, image upload)
- Category management
- Order management: update status (Pending → Processing → Shipped → Cancelled), assign delivery staff, update payment status (Paid/Refunded)
- **Cannot set status to "Delivered"** — only delivery staff can do that
- Must assign a delivery person before setting status to Shipped
- User management: create staff accounts (seller, delivery, admin), delete accounts
- Coupon management: create/edit/toggle/delete discount codes
- Sales reports: revenue charts, top products, export CSV, print view
- Support ticket inbox: reply to customer tickets, update ticket status

### Seller
- Dedicated seller panel (separate layout and sidebar)
- Seller dashboard: total products, active products, low-stock alerts, recent orders
- Full product management: add, edit, delete products with image upload
- Cannot manage users, orders, coupons, or reports

### Delivery Staff
- Dedicated delivery panel
- View only orders assigned to them by admin
- Mark assigned orders as Delivered (from any active status)
- Cannot access admin, seller, or customer panels

---

## Functional Requirements

### Authentication & Sessions
- Register with full name, email, password (min 8 chars, must include letter + number), phone
- Login sets session: `user_id`, `role`, `full_name`
- Role-based redirect on login: admin → `/admin/dashboard.php`, seller → `/seller/dashboard.php`, delivery → `/delivery/dashboard.php`, customer → `/index.php`
- `require_login()`, `require_admin()`, `require_seller()`, `require_delivery()` guards on every protected page
- Password hashed with `password_hash()` / verified with `password_verify()`
- CSRF token on every state-changing POST form

### Product Catalogue
- Products belong to categories; each has name, brand, description, price, optional discount price, stock quantity, image, featured flag, and status
- Effective price = discount price when set, else regular price
- Product listing: search by keyword, filter by category/price range/brand, sort by price/name/rating/newest
- Featured products shown on homepage
- Low-stock warning (< 5 units) shown in admin/seller dashboards
- Product image: upload file (JPG/PNG/WEBP/GIF, max 3 MB) or enter path manually

### Shopping Cart
- Session-based cart (no login required to add items)
- Quantities capped at current stock
- Cart count badge in navigation

### Coupons
- Apply coupon code in cart; stored in session
- Validation: active flag, expiry date, minimum subtotal requirement
- Two types: `percent` (e.g. 10%) and `fixed` (e.g. RM 50 off)
- Discount shown as separate line in cart, checkout, and order confirmation
- Coupon cleared from session after order is placed

### Checkout & Payment (Simulated)
- Checkout collects shipping name, address, city, postcode, phone
- Redirects to payment page; customer selects payment method:
  - **Card** — card number (Luhn check), expiry (not past), CVV (3–4 digits), cardholder name; validated client-side and server-side
  - **E-Wallet** — phone number; client + server validated
  - **Cash on Delivery** — no extra fields; order stays `payment_status = unpaid`
- Card/E-Wallet: generates `txn_ref`, inserts `payments` row, sets `payment_status = paid`
- All writes in a DB transaction; rolled back on any error

### Order Lifecycle

```
Pending → Processing → Shipped ──→ Delivered  (delivery staff only)
                    ↘ Cancelled               (admin only)
```

- Admin moves orders through: Pending / Processing / Shipped / Cancelled
- Admin must assign a delivery person before setting status to Shipped
- Only delivery staff can set status to Delivered
- Admin can manually override payment status: Paid / Refunded

### Reviews & Ratings
- Customers can review a product only if they have a `delivered` order containing it
- One review per customer per product (DB UNIQUE constraint prevents duplicates)
- Star rating 1–5 + comment text
- Average rating shown on product cards and detail page
- Products can be sorted by rating on listing page

### Wishlist
- Logged-in customers can save/unsave products
- Heart toggle on product cards and detail page
- Dedicated wishlist page with remove and add-to-cart actions

### Support Tickets
- Customer opens ticket with subject, optional linked order, and initial message
- Full chat thread: customer messages right-aligned, admin messages left-aligned
- Customer can reply at any time; replying to a resolved ticket auto-reopens it
- Admin sees all tickets, filters by status, replies, and changes status (open / in_progress / resolved / closed)

### Admin Sales Reports
- Date-range filter (default: last 30 days)
- KPI cards: total revenue, order count, average order value, items sold
- Charts via Chart.js: daily revenue (line), top 10 products (bar), revenue by category (doughnut), orders by status, revenue by payment method
- CSV export of filtered orders
- Print-friendly layout

---

## Database Schema

### Tables

| Table | Purpose |
|-------|---------|
| `users` | All accounts; role ENUM: customer / admin / seller / delivery |
| `categories` | Product categories with slug and icon |
| `products` | Product catalogue; FK → categories |
| `orders` | Customer orders; FK → users; includes payment + coupon columns |
| `order_items` | Line items per order; FK → orders, products |
| `payments` | Payment transaction audit trail; FK → orders |
| `coupons` | Discount code definitions |
| `reviews` | Product reviews; UNIQUE(product_id, user_id); FK → products, users |
| `wishlists` | Saved products; UNIQUE(user_id, product_id); FK → users, products |
| `support_tickets` | Customer support tickets; FK → users, orders |
| `ticket_messages` | Individual chat messages; FK → support_tickets, users |

### Key Relationships

```
users ──< orders ──< order_items >── products >── categories
orders ──< payments
users ──< reviews >── products
users ──< wishlists >── products
users ──< support_tickets ──< ticket_messages >── users
orders >── users (assigned_delivery_id FK)
```

---

## Security

| Concern | Mitigation |
|---------|-----------|
| SQL injection | PDO prepared statements everywhere — no string interpolation in queries |
| XSS | All dynamic HTML output through `e()` = `htmlspecialchars(ENT_QUOTES)` |
| CSRF | `csrf_field()` + `csrf_verify()` on every POST form |
| Broken access control | Role guards at top of every protected file |
| Password storage | `password_hash()` with `PASSWORD_DEFAULT` (bcrypt) |
| File upload | MIME validated via `finfo` (not just extension); random filename stored |
| Privilege escalation | Delivery staff can only update their own assigned orders; customers can only view their own orders/tickets |
| Admin privilege | Delivery "Delivered" status blocked at both UI (dropdown removed) and server (POST rejected) |

---

## Project Structure

```
web-v1/
├── index.php                  Homepage — featured products + category grid
├── products.php               Product listing with search, filter, sort
├── product_detail.php         Product page + reviews form
├── cart.php                   Shopping cart + coupon input
├── checkout.php               Shipping details form
├── payment.php                Simulated payment gateway
├── order_confirm.php          Post-payment confirmation page
├── order_history.php          Customer order history
├── wishlist.php               Customer saved products
├── support.php                Customer ticket list + new ticket form
├── ticket.php                 Customer ticket chat thread
├── login.php / register.php / logout.php / profile.php / about.php
│
├── admin/
│   ├── dashboard.php          KPI overview cards
│   ├── products.php           Product list (search + delete)
│   ├── product_form.php       Add / edit product + image upload
│   ├── orders.php             Order management + delivery assignment
│   ├── users.php              User & staff account management
│   ├── coupons.php            Coupon list + toggle active
│   ├── coupon_form.php        Add / edit coupon
│   ├── reports.php            Sales reports + CSV export + print
│   ├── tickets.php            Support ticket inbox (filter by status)
│   └── ticket.php             Admin ticket reply + status change
│
├── seller/
│   ├── dashboard.php          Stats + low-stock alerts
│   ├── products.php           Seller product list
│   └── product_form.php       Add / edit product + image upload
│
├── delivery/
│   └── dashboard.php          Assigned orders + mark as delivered
│
├── includes/
│   ├── config.php             DB + app constants; auto BASE_URL detection
│   ├── db.php                 PDO singleton
│   ├── functions.php          All shared helpers (see table below)
│   ├── auth.php               Session helpers + role guard functions
│   ├── header.php             Customer layout header + nav
│   ├── footer.php             Customer layout footer + JS (cache-busted)
│   ├── admin_header.php       Admin sidebar layout
│   ├── admin_footer.php
│   ├── seller_header.php      Seller sidebar layout
│   ├── seller_footer.php
│   ├── delivery_header.php    Delivery sidebar layout
│   ├── delivery_footer.php
│   └── product_card.php       Reusable product card partial
│
├── assets/
│   ├── css/style.css          Main stylesheet (custom, no framework)
│   ├── css/admin.css          Admin / seller / delivery panel styles
│   ├── js/main.js             UI interactions (mobile nav, flash dismiss, burger menu)
│   ├── js/cart.js             Cart quantity live update
│   ├── js/payment.js          Payment method section show/hide
│   ├── js/products.js         Filter and sort panel UI
│   ├── js/validation.js       Client-side form validation (Luhn, pattern, match)
│   └── images/products/       SVG category icons + uploaded product images (uploads/)
│
└── sql/
    └── technest.sql           Full schema + seed data (import once)
```

---

## Key Helper Functions (`includes/functions.php`)

| Function | Purpose |
|----------|---------|
| `e($val)` | XSS-safe HTML output via `htmlspecialchars` |
| `url($path)` | Build absolute URL from app-relative path |
| `redirect($path)` | Header redirect + exit |
| `money($n)` | Format as `RM X.XX` |
| `db_one($sql, $params)` | Fetch single row via prepared statement |
| `db_all($sql, $params)` | Fetch all rows via prepared statement |
| `db_exec($sql, $params)` | INSERT / UPDATE / DELETE; returns affected row count |
| `effective_price($product)` | Returns discount price if set, else regular price |
| `cart_items()` | Session cart joined with live product data, qty capped at stock |
| `cart_totals($items)` | Returns subtotal, shipping, coupon discount, total |
| `coupon_for_session($subtotal)` | Validates session coupon; returns discount info or null |
| `upload_product_image($file)` | MIME-validates + saves uploaded image; returns relative path |
| `product_rating($id)` | Average rating + review count for a product |
| `stars_html($avg)` | Renders star rating as HTML spans |
| `csrf_token()` / `csrf_field()` / `csrf_verify()` | CSRF token generation and validation |
| `set_flash()` / `get_flashes()` | One-shot session flash messages across redirects |

---

## Client-Side Validation (`assets/js/validation.js`)

Validates on submit and on field blur. Rules are driven by HTML attributes:

| Attribute | Rule |
|-----------|------|
| `required` | Field must not be empty |
| `minlength` / `maxlength` | String length bounds |
| `pattern` | Regex match |
| `type="email"` | Email format |
| `data-match="#id"` | Must equal value of another field (confirm password) |
| `data-min-value` / `data-max-value` | Numeric range |
| `disabled` | Skipped entirely (used by payment.js to hide inactive sections) |

Card number is validated with the **Luhn algorithm**. Card expiry is checked against the current month.

---

## Coupon Codes (Seed Data)

| Code | Type | Value | Min Subtotal | Expiry |
|------|------|-------|-------------|-------|
| TECH10 | percent | 10% | RM 100 | 2026-12-31 |
| SAVE50 | fixed | RM 50 | RM 500 | 2026-12-31 |
| WELCOME20 | percent | 20% | RM 0 | 2026-12-31 |
