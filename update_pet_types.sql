-- ============================================================
-- SQL Script: เพิ่มตาราง pet_types และเพิ่มฟิลด์ target_pet_type_id ในตาราง products
-- สำหรับ Hello Pet Shop
-- ============================================================

USE hello_pet_shop;

-- 1. สร้างตาราง pet_types (ประเภทสัตว์เลี้ยง)
CREATE TABLE IF NOT EXISTS pet_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. เพิ่มข้อมูลเริ่มต้น (Seed Data)
INSERT INTO pet_types (id, name, code, description) VALUES
(1, 'สัตว์ทุกประเภท', 'all', 'สำหรับสัตว์เลี้ยงทุกชนิด/ทั่วไป'),
(2, 'สุนัข', 'dog', 'สำหรับสุนัขทุกสายพันธุ์'),
(3, 'แมว', 'cat', 'สำหรับแมวทุกสายพันธุ์'),
(4, 'นก', 'bird', 'สำหรับนกทุกสายพันธุ์'),
(5, 'แฮมสเตอร์', 'hamster', 'สำหรับแฮมสเตอร์และสัตว์ขนาดเล็ก'),
(6, 'กระต่าย', 'rabbit', 'สำหรับกระต่าย')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);

-- 3. เพิ่มคอลัมน์ target_pet_type_id ในตาราง products (หากยังไม่มี)
SET @dbname = DATABASE();
SET @tablename = "products";
SET @columnname = "target_pet_type_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE products ADD COLUMN target_pet_type_id INT NULL DEFAULT 1 AFTER category_id;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. เพิ่ม Foreign Key เชื่อมโยงไปยังตาราง pet_types(id) (หากยังไม่มี)
SET @fkname = "fk_products_pet_type";
SET @preparedStatementFK = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE
      CONSTRAINT_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND CONSTRAINT_NAME = @fkname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE products ADD CONSTRAINT fk_products_pet_type FOREIGN KEY (target_pet_type_id) REFERENCES pet_types(id) ON DELETE SET NULL;"
));
PREPARE addFkIfNotExists FROM @preparedStatementFK;
EXECUTE addFkIfNotExists;
DEALLOCATE PREPARE addFkIfNotExists;
