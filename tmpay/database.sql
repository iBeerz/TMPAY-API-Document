-- สคีมาเติมเงิน TMPAY
-- นำเข้าผ่าน phpMyAdmin หรือ: mysql -u root < database.sql

CREATE DATABASE IF NOT EXISTS `tmpay_shop`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `tmpay_shop`;

-- ผู้ใช้ร้านค้า (เดโมมี user ชื่อ demo) — callback จะบวก credit เมื่อเติมสำเร็จ
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `credit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- คิวรายการบัตร: pending → awaiting_result (TMPAY รับแล้ว) → success/failed จาก callback
CREATE TABLE IF NOT EXISTS `tmpay_transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `password` CHAR(14) NOT NULL COMMENT 'รหัสบัตร 14 หลัก',
  `transaction_id` VARCHAR(10) DEFAULT NULL COMMENT 'รหัสรายการจาก TMPAY ตอนตอบ SUCCEED',
  `channel` ENUM('truemoney','razer_gold_pin') NOT NULL DEFAULT 'truemoney' COMMENT 'ช่องทางที่ส่งไป TMPAY',
  `real_amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'มูลค่าบัตรจาก callback',
  `status` ENUM('pending','awaiting_result','success','failed') NOT NULL DEFAULT 'pending',
  `tmpay_status` TINYINT UNSIGNED DEFAULT NULL COMMENT '1 สำเร็จ, 3 ใช้แล้ว, 4 รหัสผิด, 5 ทรูมูฟ',
  `message` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tmpay_password` (`password`),
  KEY `idx_tmpay_transaction_id` (`transaction_id`),
  KEY `idx_tmpay_user_id` (`user_id`),
  CONSTRAINT `fk_tmpay_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`username`, `credit`) VALUES
  ('demo', 0.00);
