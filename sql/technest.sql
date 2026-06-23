-- =====================================================================
-- TechNest E-Commerce Database
-- CIT6224 Web Application Development - Group 10
-- MySQL / MariaDB schema + seed data (XAMPP compatible)
--
-- Import:  phpMyAdmin > Import > choose this file
--   or CLI: mysql -u root < technest.sql
--
-- Demo accounts (passwords are hashed below with PHP password_hash):
--   admin@technest.com    / Admin@123
--   customer@technest.com / Customer@123
-- =====================================================================

CREATE DATABASE IF NOT EXISTS technest
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE technest;

DROP TABLE IF EXISTS wishlists;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS coupons;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ---------------------------------------------------------------------
-- users : customers and administrators (role-based access)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  user_id       INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  phone         VARCHAR(30)   DEFAULT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- categories : product groupings
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  category_id INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  slug        VARCHAR(80)  NOT NULL UNIQUE,
  description VARCHAR(255) DEFAULT NULL,
  icon        VARCHAR(40)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- products : catalogue items (single-vendor)
-- ---------------------------------------------------------------------
CREATE TABLE products (
  product_id     INT AUTO_INCREMENT PRIMARY KEY,
  category_id    INT NOT NULL,
  name           VARCHAR(150)  NOT NULL,
  brand          VARCHAR(80)   DEFAULT NULL,
  description    TEXT          DEFAULT NULL,
  price          DECIMAL(10,2) NOT NULL,
  discount_price DECIMAL(10,2) DEFAULT NULL,
  stock_quantity INT           NOT NULL DEFAULT 0,
  image_path     VARCHAR(255)  DEFAULT NULL,
  is_featured    TINYINT(1)    NOT NULL DEFAULT 0,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_products_category (category_id),
  INDEX idx_products_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- coupons : discount codes applied at cart/checkout
-- ---------------------------------------------------------------------
CREATE TABLE coupons (
  coupon_id    INT AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(30)   NOT NULL UNIQUE,
  type         ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value        DECIMAL(10,2) NOT NULL,
  min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  active       TINYINT(1)    NOT NULL DEFAULT 1,
  expires_at   DATE          DEFAULT NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- orders : one row per placed order
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  order_id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  order_number    VARCHAR(20) NOT NULL UNIQUE,
  full_name       VARCHAR(100) NOT NULL,
  phone           VARCHAR(30)  NOT NULL,
  address         VARCHAR(255) NOT NULL,
  city            VARCHAR(80)  NOT NULL,
  postcode        VARCHAR(20)  NOT NULL,
  subtotal        DECIMAL(10,2) NOT NULL,
  shipping_fee    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  coupon_code     VARCHAR(30)   DEFAULT NULL,
  total           DECIMAL(10,2) NOT NULL,
  payment_status  ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  payment_method  VARCHAR(20)  DEFAULT NULL,
  status          ENUM('pending','processing','shipped','delivered','cancelled')
                    NOT NULL DEFAULT 'pending',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- payments : transaction log (1-to-many, records refunds separately)
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  method     ENUM('card','ewallet','cod') NOT NULL,
  txn_ref    VARCHAR(40)   NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  status     ENUM('paid','refunded','failed') NOT NULL DEFAULT 'paid',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_payments_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- order_items : line items (price + name snapshotted at purchase time)
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
  order_item_id INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  product_id    INT DEFAULT NULL,
  product_name  VARCHAR(150)  NOT NULL,
  unit_price    DECIMAL(10,2) NOT NULL,
  quantity      INT NOT NULL,
  line_total    DECIMAL(10,2) NOT NULL,
  CONSTRAINT fk_items_order
    FOREIGN KEY (order_id) REFERENCES orders(order_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_items_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_items_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- reviews : customer product ratings (one per customer per product)
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
  review_id  INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id    INT NOT NULL,
  rating     TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment    TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review_product_user (product_id, user_id),
  CONSTRAINT fk_reviews_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_reviews_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_reviews_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- wishlists : saved products per customer
-- ---------------------------------------------------------------------
CREATE TABLE wishlists (
  wishlist_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  product_id  INT NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wishlist_user_product (user_id, product_id),
  CONSTRAINT fk_wishlists_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_wishlists_product
    FOREIGN KEY (product_id) REFERENCES products(product_id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- password hashes below verified with PHP password_verify()
INSERT INTO users (full_name, email, password_hash, phone, role) VALUES
('TechNest Admin',   'admin@technest.com',    '$2y$12$UwK8NCx1zJkWEC/kHWwJv.HS7Frl/ZZMg.bObsCySrCH0w4HKm1i2', '0123456789', 'admin'),
('Demo Customer',    'customer@technest.com', '$2y$12$9RNnzA.Xm7HwiAZCLVIPI.Pi7p52Do3ysa9nlhC4w7tcCNikO.nHK',  '0198765432', 'customer'),
('Ahmad Razif',      'ahmad@example.com',     '$2y$12$9RNnzA.Xm7HwiAZCLVIPI.Pi7p52Do3ysa9nlhC4w7tcCNikO.nHK',  '0111234567', 'customer'),
('Nurul Aina',       'nurul@example.com',     '$2y$12$9RNnzA.Xm7HwiAZCLVIPI.Pi7p52Do3ysa9nlhC4w7tcCNikO.nHK',  '0129876543', 'customer');

INSERT INTO categories (name, slug, description, icon) VALUES
('Smartphones & Tablets', 'smartphones', 'Phones, tablets and e-readers',       '📱'),
('Laptops & Computers',   'laptops',     'Laptops, desktops and monitors',      '💻'),
('Audio & Wearables',     'audio',       'Headphones, earbuds and smartwatches','🎧'),
('Smart Home Devices',    'smart-home',  'Speakers, cameras and smart bulbs',   '🏠'),
('Accessories',           'accessories', 'Cables, chargers and peripherals',    '🔌');

-- category_id: 1=Smartphones 2=Laptops 3=Audio 4=Smart Home 5=Accessories
INSERT INTO products
  (category_id, name, brand, description, price, discount_price, stock_quantity, image_path, is_featured, status) VALUES
(1,'Samsung Galaxy S24','Samsung','6.2-inch AMOLED, 256GB, Phantom Black. Flagship performance with a pro-grade triple camera.',3299.00,2999.00,18,'assets/images/products/smartphones.svg',1,'active'),
(1,'Apple iPhone 15','Apple','6.1-inch Super Retina XDR, 128GB, USB-C. A16 Bionic chip and Dynamic Island.',3999.00,NULL,12,'assets/images/products/smartphones.svg',1,'active'),
(1,'Xiaomi Pad 6','Xiaomi','11-inch 144Hz display, 128GB. Lightweight tablet for work and play.',1099.00,999.00,25,'assets/images/products/smartphones.svg',0,'active'),
(1,'Amazon Kindle Paperwhite','Amazon','6.8-inch glare-free e-reader, 16GB, waterproof. Weeks of battery life.',649.00,NULL,30,'assets/images/products/smartphones.svg',0,'active'),
(2,'ASUS VivoBook 15','ASUS','Intel i5, 16GB RAM, 512GB SSD. Slim everyday laptop with a 15.6-inch FHD screen.',2799.00,2599.00,10,'assets/images/products/laptops.svg',1,'active'),
(2,'Apple MacBook Air M3','Apple','13-inch Liquid Retina, 8GB, 256GB SSD. Silent, fanless and incredibly fast.',5499.00,NULL,6,'assets/images/products/laptops.svg',1,'active'),
(2,'Dell XPS 13','Dell','Intel i7, 16GB RAM, 1TB SSD. Premium ultrabook with InfinityEdge display.',6299.00,5999.00,4,'assets/images/products/laptops.svg',0,'active'),
(2,'LG UltraGear 27" Monitor','LG','27-inch QHD 165Hz gaming monitor with 1ms response and HDR10.',1499.00,1299.00,15,'assets/images/products/laptops.svg',0,'active'),
(3,'Sony WH-1000XM5','Sony','Industry-leading noise-cancelling over-ear headphones with 30h battery.',1499.00,NULL,22,'assets/images/products/audio.svg',1,'active'),
(3,'Apple AirPods Pro 2','Apple','Active noise cancellation, adaptive transparency, USB-C charging case.',1099.00,999.00,40,'assets/images/products/audio.svg',1,'active'),
(3,'Apple Watch SE 2','Apple','GPS, 44mm aluminium. Crash detection, sleep and fitness tracking.',1149.00,1049.00,16,'assets/images/products/audio.svg',0,'active'),
(3,'JBL Charge 5','JBL','Portable Bluetooth speaker, IP67 waterproof, 20h playtime and powerbank.',699.00,NULL,28,'assets/images/products/audio.svg',0,'active'),
(3,'Samsung Galaxy Watch 6','Samsung','40mm smartwatch with advanced sleep coaching and body composition.',1299.00,NULL,0,'assets/images/products/audio.svg',0,'active'),
(4,'Google Nest Audio','Google','Smart speaker with Google Assistant and room-filling sound.',399.00,349.00,20,'assets/images/products/smart-home.svg',1,'active'),
(4,'TP-Link Deco X50 (3-pack)','TP-Link','AX3000 Wi-Fi 6 whole-home mesh system covering up to 6,000 sq ft.',899.00,NULL,12,'assets/images/products/smart-home.svg',0,'active'),
(4,'Xiaomi Smart Camera C400','Xiaomi','2.5K IP camera with AI human detection and 360 view.',189.00,159.00,35,'assets/images/products/smart-home.svg',0,'active'),
(4,'Philips Hue Starter Kit','Philips','3 colour smart bulbs + bridge. 16 million colours, app and voice control.',549.00,499.00,14,'assets/images/products/smart-home.svg',0,'active'),
(5,'Anker 737 Power Bank','Anker','24,000mAh, 140W USB-C. Charges laptops and phones at full speed.',389.00,329.00,50,'assets/images/products/accessories.svg',1,'active'),
(5,'Logitech MX Master 3S','Logitech','Ergonomic wireless mouse with 8K DPI and quiet clicks.',379.00,NULL,32,'assets/images/products/accessories.svg',0,'active'),
(5,'Keychron K2 Keyboard','Keychron','Wireless hot-swappable mechanical keyboard, 75% layout, RGB.',459.00,419.00,18,'assets/images/products/accessories.svg',0,'active'),
(5,'USB-C Charging Cable 2m','UGREEN','100W fast-charging braided USB-C to USB-C cable.',49.00,NULL,120,'assets/images/products/accessories.svg',0,'active');

-- ---- Coupons ----
INSERT INTO coupons (code, type, value, min_subtotal, active, expires_at) VALUES
('TECH10',   'percent', 10.00,   0.00,   1, NULL),
('SAVE50',   'fixed',   50.00,  500.00,  1, NULL),
('STUDENT15','percent', 15.00,   0.00,   1, '2026-12-31'),
('WELCOME20','percent', 20.00,   0.00,   1, '2026-08-01');

-- ---- Orders (seed with realistic history for reports) ----
-- user 2 = Demo Customer, user 3 = Ahmad Razif, user 4 = Nurul Aina
INSERT INTO orders
  (user_id, order_number, full_name, phone, address, city, postcode,
   subtotal, shipping_fee, discount_amount, coupon_code, total,
   payment_status, payment_method, status, created_at)
VALUES
-- order 1 – already processing, paid by card
(2,'TN-1001','Demo Customer','0198765432','12 Jalan Teknologi','Cyberjaya','63000',
 3998.00,0.00,0.00,NULL,3998.00,'paid','card','delivered','2026-05-10 09:15:00'),
-- order 2 – Ahmad, paid by e-wallet
(3,'TN-1002','Ahmad Razif','0111234567','45 Jalan Imbi','Kuala Lumpur','50250',
 2599.00,0.00,0.00,NULL,2599.00,'paid','ewallet','delivered','2026-05-18 14:22:00'),
-- order 3 – Nurul, COD pending
(4,'TN-1003','Nurul Aina','0129876543','8 Persiaran Gurney','Georgetown','10250',
 1049.00,15.00,0.00,NULL,1064.00,'unpaid','cod','processing','2026-06-01 11:05:00'),
-- order 4 – Demo Customer, coupon applied, paid by card
(2,'TN-1004','Demo Customer','0198765432','12 Jalan Teknologi','Cyberjaya','63000',
 999.00,15.00,100.00,'TECH10',914.00,'paid','card','shipped','2026-06-05 16:40:00'),
-- order 5 – Ahmad, ewallet, processing
(3,'TN-1005','Ahmad Razif','0111234567','45 Jalan Imbi','Kuala Lumpur','50250',
 1299.00,15.00,0.00,NULL,1314.00,'paid','ewallet','processing','2026-06-10 10:30:00'),
-- order 6 – Nurul, card, pending
(4,'TN-1006','Nurul Aina','0129876543','8 Persiaran Gurney','Georgetown','10250',
 329.00,15.00,50.00,'SAVE50',294.00,'paid','card','pending','2026-06-15 08:55:00'),
-- order 7 – Demo Customer, card, delivered
(2,'TN-1007','Demo Customer','0198765432','12 Jalan Teknologi','Cyberjaya','63000',
 5999.00,0.00,0.00,NULL,5999.00,'paid','card','delivered','2026-06-18 13:20:00'),
-- order 8 – Ahmad, cancelled, unpaid
(3,'TN-1008','Ahmad Razif','0111234567','45 Jalan Imbi','Kuala Lumpur','50250',
 3999.00,0.00,0.00,NULL,3999.00,'unpaid',NULL,'cancelled','2026-06-20 09:00:00');

INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES
-- order 1
(1, 1,  'Samsung Galaxy S24',   2999.00, 1, 2999.00),
(1, 10, 'Apple AirPods Pro 2',   999.00, 1,  999.00),
-- order 2
(2, 5,  'ASUS VivoBook 15',     2599.00, 1, 2599.00),
-- order 3
(3, 11, 'Apple Watch SE 2',     1049.00, 1, 1049.00),
-- order 4
(4, 10, 'Apple AirPods Pro 2',   999.00, 1,  999.00),
-- order 5
(5, 13, 'Samsung Galaxy Watch 6',1299.00,1, 1299.00),
-- order 6
(6, 18, 'Anker 737 Power Bank',   329.00, 1,  329.00),
-- order 7
(7, 7,  'Dell XPS 13',          5999.00, 1, 5999.00),
-- order 8
(8, 2,  'Apple iPhone 15',      3999.00, 1, 3999.00);

-- ---- Payments (one row per paid order) ----
INSERT INTO payments (order_id, method, txn_ref, amount, status, created_at) VALUES
(1, 'card',    'TXN-A1B2C3D4', 3998.00, 'paid', '2026-05-10 09:16:00'),
(2, 'ewallet', 'TXN-E5F6G7H8', 2599.00, 'paid', '2026-05-18 14:23:00'),
(4, 'card',    'TXN-I9J0K1L2',  914.00, 'paid', '2026-06-05 16:41:00'),
(5, 'ewallet', 'TXN-M3N4O5P6', 1314.00, 'paid', '2026-06-10 10:31:00'),
(6, 'card',    'TXN-Q7R8S9T0',  294.00, 'paid', '2026-06-15 08:56:00'),
(7, 'card',    'TXN-U1V2W3X4', 5999.00, 'paid', '2026-06-18 13:21:00');

-- ---- Reviews (demo customer reviewed items from delivered orders) ----
INSERT INTO reviews (product_id, user_id, rating, comment, created_at) VALUES
(1,  2, 5, 'Absolutely love this phone! The camera quality is stunning and battery life is impressive. Highly recommended.', '2026-05-20 10:00:00'),
(10, 2, 4, 'Great noise cancellation, very comfortable to wear. Sound quality is top notch. Minor complaint: the case is a bit bulky.', '2026-05-21 11:30:00'),
(5,  3, 5, 'Best laptop I have ever owned. Fast boot, great display and the build quality feels premium. Perfect for university.', '2026-05-28 15:45:00'),
(11, 4, 4, 'Accurate fitness tracking and crash detection gives me peace of mind. Battery lasts about a day and a half.', '2026-06-08 09:20:00'),
(7,  2, 5, 'Absolutely brilliant machine. The InfinityEdge display is gorgeous and performance is exceptional.', '2026-06-22 14:10:00'),
(1,  3, 4, 'Very good phone for the price. Fast and reliable, though I wish the base storage was 512GB.', '2026-06-19 16:00:00'),
(18, 4, 5, 'Charges my MacBook at full speed. Compact and the braided cable feels durable. Great value.', '2026-06-20 08:30:00');

-- ---- Wishlists (sample saved products) ----
INSERT INTO wishlists (user_id, product_id, created_at) VALUES
(2, 6,  '2026-06-01 10:00:00'),
(2, 9,  '2026-06-02 11:00:00'),
(3, 2,  '2026-06-03 12:00:00'),
(3, 14, '2026-06-04 13:00:00'),
(4, 5,  '2026-06-05 14:00:00');

-- =====================================================================
-- Phase 2: Support Tickets + Delivery Role
-- Module: Admin & Database (Khalid)
-- Run these statements once via phpMyAdmin SQL tab on an existing DB.
-- They are also safe to include on fresh import (IF NOT EXISTS / MODIFY).
-- =====================================================================

-- Extend role enum to include delivery staff and sellers
ALTER TABLE users MODIFY role ENUM('customer','admin','delivery','seller') NOT NULL DEFAULT 'customer';

-- Link orders to assigned delivery staff
ALTER TABLE orders ADD COLUMN IF NOT EXISTS assigned_delivery_id INT DEFAULT NULL AFTER status;

-- Support ticket threads
CREATE TABLE IF NOT EXISTS support_tickets (
  ticket_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  order_id   INT UNSIGNED DEFAULT NULL,
  subject    VARCHAR(150) NOT NULL,
  status     ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id),
  CONSTRAINT fk_ticket_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)  ON DELETE CASCADE,
  CONSTRAINT fk_ticket_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE SET NULL,
  INDEX idx_ticket_user   (user_id),
  INDEX idx_ticket_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Return/refund requests submitted by customers for delivered orders
CREATE TABLE IF NOT EXISTS return_requests (
  request_id  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id    INT NOT NULL,
  user_id     INT NOT NULL,
  reason      TEXT NOT NULL,
  status      ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note  TEXT DEFAULT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (request_id),
  UNIQUE KEY uq_return_order (order_id),
  CONSTRAINT fk_return_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
  CONSTRAINT fk_return_user  FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
  INDEX idx_return_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual chat messages per ticket
CREATE TABLE IF NOT EXISTS ticket_messages (
  message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id  INT UNSIGNED NOT NULL,
  sender_id  INT UNSIGNED NOT NULL,
  message    TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (message_id),
  CONSTRAINT fk_msg_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_msg_ticket (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
