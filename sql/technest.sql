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
-- orders : one row per placed order
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  order_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  order_number VARCHAR(20) NOT NULL UNIQUE,
  full_name    VARCHAR(100) NOT NULL,
  phone        VARCHAR(30)  NOT NULL,
  address      VARCHAR(255) NOT NULL,
  city         VARCHAR(80)  NOT NULL,
  postcode     VARCHAR(20)  NOT NULL,
  subtotal     DECIMAL(10,2) NOT NULL,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  total        DECIMAL(10,2) NOT NULL,
  status       ENUM('pending','processing','shipped','delivered','cancelled')
                 NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status)
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

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- password hashes below verified with PHP password_verify()
INSERT INTO users (full_name, email, password_hash, phone, role) VALUES
('TechNest Admin',   'admin@technest.com',    '$2y$12$UwK8NCx1zJkWEC/kHWwJv.HS7Frl/ZZMg.bObsCySrCH0w4HKm1i2', '0123456789', 'admin'),
('Demo Customer',    'customer@technest.com', '$2y$12$9RNnzA.Xm7HwiAZCLVIPI.Pi7p52Do3ysa9nlhC4w7tcCNikO.nHK',  '0198765432', 'customer');

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

INSERT INTO orders
  (user_id, order_number, full_name, phone, address, city, postcode, subtotal, shipping_fee, total, status)
VALUES
(2,'TN-1000','Demo Customer','0198765432','12 Jalan Teknologi','Cyberjaya','63000',3998.00,0.00,3998.00,'processing');

INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total) VALUES
(1, 1, 'Samsung Galaxy S24', 2999.00, 1, 2999.00),
(1, 10,'Apple AirPods Pro 2', 999.00,  1, 999.00);
