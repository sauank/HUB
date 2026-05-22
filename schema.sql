-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS `fmhy_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fmhy_db`;

-- Drop tables if they exist to start fresh during bootstrap/import
DROP TABLE IF EXISTS `links`;
DROP TABLE IF EXISTS `sections`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `pages`;

-- Table for main categories (sidebar and top nav items)
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `type` VARCHAR(50) NOT NULL DEFAULT 'wiki', -- 'wiki', 'tools', 'other'
    `icon` VARCHAR(255) DEFAULT NULL, -- Emoji or icon class name
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for sub-sections within a category page
CREATE TABLE `sections` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for individual links/resources
CREATE TABLE `links` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `section_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `url` TEXT NOT NULL,
    `description` TEXT DEFAULT NULL,
    `is_starred` TINYINT(1) DEFAULT 0,
    `is_unsafe` TINYINT(1) DEFAULT 0,
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`section_id`) REFERENCES `sections`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for standalone text pages (e.g. Beginners Guide, FAQ)
CREATE TABLE `pages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) UNIQUE NOT NULL,
    `content` LONGTEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FULLTEXT index for rich search on links, descriptions, and page contents
ALTER TABLE `links` ADD FULLTEXT INDEX `search_idx` (`name`, `description`);
ALTER TABLE `pages` ADD FULLTEXT INDEX `content_idx` (`title`, `content`);
