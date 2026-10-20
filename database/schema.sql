-- =====================================================================
-- GoDrive - Cấu trúc database MySQL / MariaDB
-- Chạy file này 1 lần (phpMyAdmin > Import, hoặc dòng lệnh mysql).
-- CHẠY LẠI SẼ XÓA SẠCH DỮ LIỆU CŨ (DROP TABLE).
-- =====================================================================

-- Báo cho MySQL biết file này viết bằng UTF-8 (nếu thiếu, chữ tiếng Việt trong ENUM sẽ bị lỗi)
SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS godrive
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE godrive;

-- Xóa theo thứ tự ngược với khóa ngoại: bảng "con" trước, bảng "cha" sau
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS maintenance;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS admins;

-- ---------------------------------------------------------------------
-- Tài khoản quản trị
-- ---------------------------------------------------------------------
CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NULL,              -- NULL = không có email
  password_hash VARCHAR(255) NOT NULL,          -- bcrypt, không bao giờ lưu mật khẩu gốc
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admins_username (username),
  UNIQUE KEY uq_admins_email (email)            -- UNIQUE cho phép nhiều dòng NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Xe
-- ENUM: database tự từ chối giá trị ngoài danh sách (trước đây chỉ PHP kiểm tra)
-- ---------------------------------------------------------------------
CREATE TABLE cars (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  brand      VARCHAR(50)  NOT NULL DEFAULT '',
  type       ENUM('Sedan','SUV','Hatchback') NOT NULL DEFAULT 'Sedan',
  seats      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  price      INT UNSIGNED NOT NULL,                       -- giá thuê / ngày (VND)
  location   VARCHAR(100) NOT NULL,
  status     ENUM('available','rented','maintenance') NOT NULL DEFAULT 'available',
  rating     DECIMAL(2,1) NOT NULL DEFAULT 4.8,
  featured   TINYINT(1)   NOT NULL DEFAULT 0,             -- 0/1 thay cho true/false
  image      VARCHAR(2000) NOT NULL DEFAULT '',
  color      ENUM('','purple','blue','red','white','black','silver','teal','orange') NOT NULL DEFAULT '',
  CONSTRAINT chk_cars_price  CHECK (price > 0),
  CONSTRAINT chk_cars_rating CHECK (rating BETWEEN 0 AND 5),
  KEY idx_cars_status (status),
  KEY idx_cars_location (location)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Khách hàng
-- total_spent / total_bookings vẫn lưu sẵn (dữ liệu mẫu không suy ra được từ bảng bookings).
-- Hạng (tier) KHÔNG lưu: luôn tính từ total_spent khi truy vấn, nên không bao giờ bị lệch.
-- ---------------------------------------------------------------------
CREATE TABLE customers (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name           VARCHAR(100) NOT NULL,
  email          VARCHAR(150) NOT NULL,
  phone          VARCHAR(20)  NOT NULL,
  joined_at      DATE         NOT NULL,
  total_bookings INT UNSIGNED    NOT NULL DEFAULT 0,
  total_spent    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_customers_email (email),
  KEY idx_customers_phone (phone)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Đơn đặt xe
-- car_name: "ảnh chụp" tên xe lúc đặt. Xe bị đổi tên/xóa thì đơn cũ vẫn giữ đúng tên lúc đó.
-- car_id: khóa ngoại tới cars. Xóa xe -> car_id thành NULL, đơn vẫn còn (ON DELETE SET NULL).
-- ---------------------------------------------------------------------
CREATE TABLE bookings (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_name  VARCHAR(100) NOT NULL,
  customer_email VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(20)  NOT NULL,
  car_id         INT UNSIGNED NULL,
  car_name       VARCHAR(100) NOT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  location       VARCHAR(100) NOT NULL DEFAULT '',
  total          BIGINT UNSIGNED NOT NULL,
  status         ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(30) NULL,                -- chọn khi duyệt đơn
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  approved_at    DATETIME NULL,
  CONSTRAINT fk_bookings_car FOREIGN KEY (car_id) REFERENCES cars(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_bookings_dates CHECK (end_date > start_date),
  KEY idx_bookings_email (customer_email),
  KEY idx_bookings_car_dates (car_id, start_date, end_date)   -- tăng tốc kiểm tra trùng lịch
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Giao dịch thanh toán (tạo khi admin duyệt đơn)
-- Không lưu tên khách / tên xe: lấy bằng JOIN với bookings, nên luôn khớp với đơn.
-- Xóa đơn -> xóa luôn giao dịch của đơn (ON DELETE CASCADE).
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_code      VARCHAR(30) NOT NULL,
  booking_id    BIGINT UNSIGNED NOT NULL,
  amount        BIGINT UNSIGNED NOT NULL,
  amount_edited TINYINT(1) NOT NULL DEFAULT 0,     -- 1 = admin đã tự sửa số tiền
  method        ENUM('Tiền mặt','Chuyển khoản','Thẻ tín dụng','Ví MoMo','ZaloPay','VNPay','COD') NULL,
  status        ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at       DATETIME NULL,
  refunded_at   DATETIME NULL,
  note          VARCHAR(120) NOT NULL DEFAULT '',
  approved_by   VARCHAR(50)  NOT NULL DEFAULT '',
  updated_at    DATETIME NULL,
  updated_by    VARCHAR(50)  NULL,
  UNIQUE KEY uq_payments_txn (txn_code),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  KEY idx_payments_status (status)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Lịch bảo dưỡng
-- ---------------------------------------------------------------------
CREATE TABLE maintenance (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  car_id     INT UNSIGNED NULL,
  car_name   VARCHAR(100) NOT NULL,                -- ảnh chụp tên xe, giống bookings
  type       VARCHAR(100) NOT NULL DEFAULT '',
  cost       INT UNSIGNED NOT NULL DEFAULT 0,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  status     ENUM('scheduled','in_progress','completed') NOT NULL DEFAULT 'scheduled',
  note       VARCHAR(500) NOT NULL DEFAULT '',
  CONSTRAINT fk_maintenance_car FOREIGN KEY (car_id) REFERENCES cars(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_maintenance_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB;
