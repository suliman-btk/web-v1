# TechNest — E-Commerce Web Application

**CIT6224 Web Application Development — Group 10**

TechNest is a single-vendor B2C e-commerce store for consumer electronics, built with
**HTML5, custom CSS3, vanilla JavaScript, PHP and MySQL** (no UI frameworks). It runs on
XAMPP and supports two roles — **customer** and **admin**.

---

## 1. Requirements

- XAMPP (Apache + PHP 8+ + MySQL/MariaDB) on Windows, **or** PHP 8 + MySQL/MariaDB on
  Linux/Mac.
- A modern web browser.

## 2. Installation & Setup (XAMPP / Windows)

1. Copy the whole project folder into XAMPP's web root and name it `technest`:
   ```
   C:\xampp\htdocs\technest\
   ```
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

## 3. Database Configuration

1. Open phpMyAdmin: <http://localhost/phpmyadmin>
2. Click **Import** → **Choose File** → select `sql/technest.sql` → **Go**.
   This creates the `technest` database with all tables and sample data.
3. The app's DB settings live in `includes/config.php`. The defaults match XAMPP
   (host `127.0.0.1`, user `root`, empty password). Change them only if your MySQL
   credentials differ.

## 4. Running the App

- Open: <http://localhost/technest/>

### Running on Linux/Mac (PHP built-in server)

```bash
# 1. Import the database (MySQL/MariaDB must be running)
mysql -u root < sql/technest.sql
# 2. From the project folder, start the dev server
php -S localhost:8000
# 3. Visit http://localhost:8000/
```

## 5. Login Credentials

| Role     | Email                  | Password      |
|----------|------------------------|---------------|
| Admin    | `admin@technest.com`   | `Admin@123`   |
| Customer | `customer@technest.com`| `Customer@123`|

You can also register a brand-new customer account from the **Create Account** page.

## 6. Key Features

**Customer**
- Browse home page, product listing with search / category / price / availability filters & sorting
- Product detail pages with specifications and stock status
- Session-based shopping cart with live JavaScript quantity & total updates
- Secure registration & login with real-time + server-side validation
- Checkout with delivery details, order confirmation and order history
- Profile management (update details / change password)

**Admin** (`/admin/`)
- Dashboard with order, customer, product and revenue summaries + low-stock alerts
- Product management — full CRUD (create, read, update, delete) incl. stock & images
- Order management — view all orders and update their status
- User management — view all registered customers

## 7. Security Measures

- **SQL Injection** — all queries use PDO prepared statements (no string concatenation).
- **XSS** — every dynamic value is escaped on output via `htmlspecialchars()` (`e()` helper).
- **Passwords** — hashed with PHP `password_hash()` / verified with `password_verify()`.
- **Sessions** — session ID regenerated on login; role-based guards protect admin pages.
- **CSRF** — state-changing forms include and verify a CSRF token.

## 8. Project Structure

```
technest/
├── index.php  products.php  product_detail.php   (browsing)
├── register.php  login.php  logout.php  profile.php (auth & user)
├── cart.php  checkout.php  order_confirm.php  order_history.php (cart & orders)
├── about.php  members.php                         (info / group details)
├── admin/      dashboard, products, product_form, orders, users
├── includes/   config, db, functions, auth, header/footer, admin chrome
├── assets/     css/  js/  images/
└── sql/technest.sql                               (database export)
```

## 9. Group 10

| Student ID  | Name                          | Module                       |
|-------------|-------------------------------|------------------------------|
| 1221301196  | Kashtu, Nasr Abraheem Barkah  | Frontend & Product           |
| 1211305566  | Alkatheri, Sulaiman Ali Mahdi | Authentication & User        |
| 241UC24008  | Abdelaziz, Khalid Moussa      | Admin, Checkout & Database  |

See the **Our Team** page in the app for full roles and contributions.
