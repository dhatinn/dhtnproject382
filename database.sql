-- Database: harapan_bundo_db
-- Rumah Makan Padang Harapan Bundo
-- Cita Rasa Minang Otentik, Warisan Tradisi Bundo

CREATE DATABASE IF NOT EXISTS `harapan_bundo_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `harapan_bundo_db`;

-- 1. Table: users (Admin & Staff)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'kasir', 'dapur') DEFAULT 'admin',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Table: categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `icon` VARCHAR(50) DEFAULT 'utensils',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Table: menu_items
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `price` DECIMAL(12, 2) NOT NULL,
    `promo_price` DECIMAL(12, 2) DEFAULT NULL,
    `spicy_level` TINYINT DEFAULT 1 COMMENT '1: Sedang, 2: Pedas, 3: Sangat Pedas',
    `image_url` VARCHAR(500) NOT NULL,
    `is_available` TINYINT(1) DEFAULT 1,
    `is_featured` TINYINT(1) DEFAULT 0,
    `rating` DECIMAL(2, 1) DEFAULT 4.9,
    `total_sold` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Table: orders
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL UNIQUE,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_phone` VARCHAR(20) NOT NULL,
    `order_type` ENUM('dine_in', 'delivery', 'takeaway') NOT NULL DEFAULT 'dine_in',
    `table_number` VARCHAR(20) DEFAULT NULL,
    `delivery_address` TEXT DEFAULT NULL,
    `delivery_notes` TEXT DEFAULT NULL,
    `subtotal` DECIMAL(12, 2) NOT NULL,
    `tax_amount` DECIMAL(12, 2) DEFAULT 0.00,
    `delivery_fee` DECIMAL(12, 2) DEFAULT 0.00,
    `discount_amount` DECIMAL(12, 2) DEFAULT 0.00,
    `total_amount` DECIMAL(12, 2) NOT NULL,
    `payment_method` ENUM('qris', 'doku', 'cash') DEFAULT 'qris',
    `payment_status` ENUM('unpaid', 'paid', 'expired', 'failed') DEFAULT 'unpaid',
    `payment_ref` VARCHAR(100) DEFAULT NULL,
    `qris_raw_string` TEXT DEFAULT NULL,
    `paid_at` DATETIME DEFAULT NULL,
    `order_status` ENUM('pending', 'confirmed', 'cooking', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Table: order_items
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `menu_id` INT NOT NULL,
    `menu_name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(12, 2) NOT NULL,
    `quantity` INT NOT NULL,
    `subtotal` DECIMAL(12, 2) NOT NULL,
    `notes` VARCHAR(255) DEFAULT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Table: restaurant_settings
CREATE TABLE IF NOT EXISTS `restaurant_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Table: payment_logs
CREATE TABLE IF NOT EXISTS `payment_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_code` VARCHAR(50) NOT NULL,
    `provider` VARCHAR(50) NOT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `raw_payload` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Admin User: admin / admin123
-- Password hash generated with PASSWORD_BCRYPT for 'admin123'
INSERT INTO `users` (`id`, `username`, `password`, `name`, `role`) VALUES
(1, 'admin', '$2y$10$Tiol7lqe3jyV5H.5scsBzOQvk1JtipR4bkzJPAYsmWEM8UFmlR/4K', 'Uda Bundo (Admin)', 'admin')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `icon`, `sort_order`, `is_active`) VALUES
(1, 'Lauk Daging', 'lauk-daging', 'drumstick-bite', 1, 1),
(2, 'Ayam & Bebek', 'ayam-bebek', 'egg', 2, 1),
(3, 'Seafood & Gulai', 'seafood-gulai', 'fish', 3, 1),
(4, 'Sayur & Sambal', 'sayur-sambal', 'pepper-hot', 4, 1),
(5, 'Paket Nasi Lengkap', 'paket-nasi', 'box-open', 5, 1),
(6, 'Minuman Khas', 'minuman-khas', 'glass-martini-alt', 6, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Seed Menu Items (Authentic Padang Dishes with High-Resolution Photos)
INSERT INTO `menu_items` (`id`, `category_id`, `name`, `slug`, `description`, `price`, `promo_price`, `spicy_level`, `image_url`, `is_available`, `is_featured`, `rating`, `total_sold`) VALUES
(1, 1, 'Rendang Daging Sapi Suntiang', 'rendang-daging-sapi', 'Daging sapi pilihan dimasak perlahan 8 jam dengan santan kelapa tua & 16 rempah rahasia khas Bundo hingga meresap pekat berwarna cokelat kehitaman.', 28000.00, 25000.00, 2, 'https://images.unsplash.com/photo-1626074353765-517a681e40be?auto=format&fit=crop&w=800&q=80', 1, 1, 5.0, 1420),
(2, 2, 'Ayam Pop Bukittinggi Asli', 'ayam-pop-bukittinggi', 'Ayam kampung muda dimasak air kelapa dan bawang putih, digoreng kilat berkulit lembut disajikan dengan saus tomat khas Padang yang gurih pedas.', 24000.00, NULL, 1, 'https://images.unsplash.com/photo-1598515214211-89d3c73ae83b?auto=format&fit=crop&w=800&q=80', 1, 1, 4.9, 980),
(3, 1, 'Dendeng Batokok Balado Basah', 'dendeng-batokok-balado', 'Irisan daging sapi empuk yang ditokok (dipukul pipih) lalu dibakar lembut dan disiram sambal balado cabai merah uleg kasar minyak kelapa.', 27000.00, NULL, 3, 'https://images.unsplash.com/photo-1544025162-d76694265947?auto=format&fit=crop&w=800&q=80', 1, 1, 4.9, 750),
(4, 3, 'Gulai Kepala Ikan Kakap Merah', 'gulai-kepala-ikan-kakap', 'Kepala ikan kakap merah segar dengan kuah gulai kental kuning keemasan beraroma asam kandis, daun ruku-ruku, dan serai wangi.', 55000.00, 49000.00, 2, 'https://images.unsplash.com/photo-1534422298391-e4f8c172dddb?auto=format&fit=crop&w=800&q=80', 1, 1, 4.9, 430),
(5, 3, 'Gulai Tunjang Kikil Kenyal', 'gulai-tunjang-kikil', 'Tunjang sapi lembut kenyal dengan kuah santan bumbu kari Padang yang kaya rempah dan medok.', 26000.00, NULL, 2, 'https://images.unsplash.com/photo-1541832676-9b763b0239ab?auto=format&fit=crop&w=800&q=80', 1, 1, 4.8, 610),
(6, 2, 'Ayam Bakar Padang Bumbu Rujak', 'ayam-bakar-padang', 'Ayam kampung dibakar arang batok kelapa setelah diungkep bumbu santan pedas gurih manis alami.', 24000.00, NULL, 2, 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?auto=format&fit=crop&w=800&q=80', 1, 0, 4.8, 520),
(7, 5, 'Paket Nasi Padang Komplit Rendang', 'paket-nasi-komplit-rendang', 'Nasi putih pulen hangat, Rendang Daging Sapi, Gulai Nangka Kapau, Daun Singkong rebus, Sambalado Ijo, dan kuah rendang tumpah.', 36000.00, 32000.00, 2, 'https://images.unsplash.com/photo-1563379091339-03b21ab4a4f8?auto=format&fit=crop&w=800&q=80', 1, 1, 5.0, 2150),
(8, 5, 'Paket Nasi Ayam Pop Sambalado', 'paket-nasi-ayam-pop', 'Nasi putih pulen hangat, Ayam Pop Bukittinggi, Sayur Nangka, Daun Singkong, kuah gulai, dan Sambalado Ijo.', 32000.00, NULL, 1, 'https://images.unsplash.com/photo-1512058564366-18510be2db19?auto=format&fit=crop&w=800&q=80', 1, 0, 4.9, 1340),
(9, 4, 'Sambalado Ijo Padang (Porsi Mangkuk)', 'sambalado-ijo-padang', 'Cabai hijau keriting, tomat hijau, bawang merah ditumis wangi dengan minyak kelapa asli dan ikan teri medan goreng.', 8000.00, NULL, 2, 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80', 1, 0, 4.8, 890),
(10, 4, 'Sayur Kapau Nangka & Kol', 'sayur-kapau-nangka', 'Gulai nangka muda khas Bukittinggi dengan potongan kacang panjang, kol, dan kuah santan kuning bercita rasa gurih gurih asam segar.', 10000.00, NULL, 1, 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=800&q=80', 1, 0, 4.7, 720),
(11, 6, 'Teh Talua (Teh Telur Khas Minang)', 'teh-talua-khas-minang', 'Minuman stamina khas ranah Minang perpaduan kuning telur itik, teh hitam pekat seduh panas, kental manis dan perasan jeruk nipis berbusa lembut.', 15000.00, NULL, 1, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?auto=format&fit=crop&w=800&q=80', 1, 1, 4.9, 930),
(12, 6, 'Es Tebak Bundo Segar', 'es-tebak-bundo', 'Dessert es khas Sumatera Barat dengan tebak kenyal (olahan tepung beras ketan), cincau hitam, tape singkong, kolang-kaling, sirup merah bundo dan santan gurih.', 16000.00, 14000.00, 1, 'https://images.unsplash.com/photo-1551024709-8f23befc6f87?auto=format&fit=crop&w=800&q=80', 1, 1, 4.9, 810)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Default Settings
INSERT INTO `restaurant_settings` (`setting_key`, `setting_value`) VALUES
('restaurant_name', 'RM Padang Harapan Bundo'),
('tagline', 'Cita Rasa Minang Otentik, Warisan Tradisi Bundo'),
('address', 'Jl. Sudirman No. 88, Kawasan Kuliner Nusantara, Jakarta'),
('whatsapp_number', '6281234567890'),
('doku_mode', 'sandbox'),
('doku_client_id', 'MALL-ID-HARAPAN-BUNDO-DEMO'),
('doku_shared_key', 'SEC-KEY-HARAPAN-BUNDO-2026'),
('qris_merchant_name', 'RM PADANG HARAPAN BUNDO'),
('qris_nmid', 'ID1020039281720'),
('delivery_flat_fee', '10000'),
('tax_percentage', '0')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
