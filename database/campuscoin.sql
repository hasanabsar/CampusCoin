  -- =====================================================================
  -- CampusCoin — database schema and seed data
  -- Import this file into MySQL/MariaDB (phpMyAdmin: "Import" tab, or
  -- `mysql -u root campuscoin < campuscoin.sql` from the command line).
  -- Safe to re-run: it drops and recreates the `campuscoin` database.
  -- =====================================================================

  SET NAMES utf8mb4;
  SET FOREIGN_KEY_CHECKS = 0;

  DROP DATABASE IF EXISTS `campuscoin`;
  CREATE DATABASE `campuscoin` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  USE `campuscoin`;

  -- ---------------------------------------------------------------------
  -- users
  -- ---------------------------------------------------------------------
  CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `academic_year` VARCHAR(30) NULL,
    `monthly_savings_goal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
    `avatar` VARCHAR(255) NULL,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- admins (administrators are stored separately from student accounts)
  -- ---------------------------------------------------------------------
  CREATE TABLE `admins` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`),
    KEY `idx_admins_status` (`status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- categories  (defaults have user_id = NULL; custom categories belong
  -- to one student; a category is never shared between two students)
  -- ---------------------------------------------------------------------
  CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(40) NOT NULL,
    `type` ENUM('income','expense') NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_categories_user` (`user_id`),
    KEY `idx_categories_type` (`type`, `is_default`, `is_active`),
    CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- transactions
  -- ---------------------------------------------------------------------
  CREATE TABLE `transactions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `amount` DECIMAL(12,2) NOT NULL,
    `type` ENUM('income','expense') NOT NULL,
    `description` VARCHAR(255) NULL,
    `date` DATE NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tx_user_date` (`user_id`, `date`),
    KEY `idx_tx_user_type_date` (`user_id`, `type`, `date`),
    KEY `idx_tx_category` (`category_id`),
    CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_tx_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- budgets  (one limit per student + category + month)
  -- ---------------------------------------------------------------------
  CREATE TABLE `budgets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `category_id` INT UNSIGNED NOT NULL,
    `month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `limit_amount` DECIMAL(12,2) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_budget_user_cat_month` (`user_id`, `category_id`, `month`),
    KEY `idx_budgets_user_month` (`user_id`, `month`),
    CONSTRAINT `fk_budgets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_budgets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- insights  (one AI / rule-based monthly summary per student + month)
  -- ---------------------------------------------------------------------
  CREATE TABLE `insights` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `month` CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `summary` TEXT NOT NULL,
    `tip` TEXT NOT NULL,
    `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_insights_user_month` (`user_id`, `month`),
    CONSTRAINT `fk_insights_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- password_resets
  -- ---------------------------------------------------------------------
  CREATE TABLE `password_resets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_resets_token` (`token_hash`),
    KEY `idx_resets_user` (`user_id`, `used_at`),
    CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- login_attempts  (brute-force throttling)
  -- ---------------------------------------------------------------------
  CREATE TABLE `login_attempts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(190) NOT NULL,
    `ip` VARCHAR(45) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attempts_email_time` (`email`, `attempted_at`),
    KEY `idx_attempts_ip_time` (`ip`, `attempted_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- contact_messages  (public "Contact us" form on the landing page)
  -- ---------------------------------------------------------------------
  CREATE TABLE `contact_messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(190) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `ip` VARCHAR(45) NULL,
    `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_status` (`status`),
    KEY `idx_contact_ip_time` (`ip`, `created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- announcements  (shown at the top of the student dashboard)
  -- ---------------------------------------------------------------------
  CREATE TABLE `announcements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(150) NOT NULL,
    `description` VARCHAR(500) NOT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `admin_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_announcements_status` (`status`),
    KEY `idx_announcements_admin` (`admin_id`),
    CONSTRAINT `fk_announcements_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- ---------------------------------------------------------------------
  -- saving_tips  (admin-authored tips shown on the student Saving Tips page)
  -- ---------------------------------------------------------------------
  CREATE TABLE `saving_tips` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(150) NOT NULL,
    `content` VARCHAR(600) NOT NULL,
    `topic` ENUM('general','food','entertainment','transport','shopping','academics') NOT NULL DEFAULT 'general',
    `is_published` TINYINT(1) NOT NULL DEFAULT 0,
    `admin_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tips_published` (`is_published`),
    KEY `idx_tips_admin` (`admin_id`),
    CONSTRAINT `fk_tips_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  SET FOREIGN_KEY_CHECKS = 1;

  -- =====================================================================
  -- SEED DATA
  -- =====================================================================

  -- ---------------------------------------------------------------------
  -- Users
  --   admin@campuscoin.com    / Admin@123
  --   student@campuscoin.com  / Student@123
  -- (Passwords are bcrypt-hashed below — never stored in plain text.)
  -- ---------------------------------------------------------------------
  INSERT INTO `admins` (`id`, `name`, `email`, `password`, `status`, `created_at`) VALUES
  (1, 'CampusCoin Admin', 'admin@campuscoin.com', '$2y$10$eNtUXeWhUzqbYsU.M2xpCuMVGeQ5FO10mEMFQwJArNiY/tkS7lzEm', 'active', NOW());

  INSERT INTO `users` (`id`, `name`, `email`, `password`, `academic_year`, `monthly_savings_goal`, `status`, `created_at`) VALUES
  (2, 'Ayesha Khan', 'student@campuscoin.com', '$2y$10$eKinvT7h098TdbOJ8gsche7TtaMgla8/f/5kNMbWXCKcUPmCDPsky', '3rd Year', 8000.00, 'active', NOW() - INTERVAL 5 MONTH);

  -- ---------------------------------------------------------------------
  -- Default categories (shared by every student; user_id = NULL)
  -- ---------------------------------------------------------------------
  INSERT INTO `categories` (`id`, `name`, `type`, `user_id`, `is_default`, `is_active`) VALUES
  (1, 'Allowance',      'income',  NULL, 1, 1),
  (2, 'Scholarship',    'income',  NULL, 1, 1),
  (3, 'Part-time Job',  'income',  NULL, 1, 1),
  (4, 'Gift',           'income',  NULL, 1, 1),
  (5, 'Other Income',   'income',  NULL, 1, 1),
  (6, 'Food',           'expense', NULL, 1, 1),
  (7, 'Transport',      'expense', NULL, 1, 1),
  (8, 'Academics',      'expense', NULL, 1, 1),
  (9, 'Entertainment',  'expense', NULL, 1, 1),
  (10,'Shopping',       'expense', NULL, 1, 1),
  (11,'Health',         'expense', NULL, 1, 1),
  (12,'Subscriptions',  'expense', NULL, 1, 1),
  (13,'Rent & Utilities','expense',NULL, 1, 1),
  (14,'Other',          'expense', NULL, 1, 1);

  -- A custom category for the sample student
  INSERT INTO `categories` (`id`, `name`, `type`, `user_id`, `is_default`, `is_active`) VALUES
  (15, 'Hostel Mess Fund', 'expense', 2, 0, 1);

  -- ---------------------------------------------------------------------
  -- Sample transactions for the demo student (id 2) — this month and last month
  -- ---------------------------------------------------------------------
  INSERT INTO `transactions` (`user_id`, `category_id`, `amount`, `type`, `description`, `date`) VALUES
  -- This month
  (2, 1,  20000.00, 'income',  'Monthly allowance',            DATE_FORMAT(CURDATE(), '%Y-%m-03')),
  (2, 3,   6000.00, 'income',  'Tutoring — 2 sessions',        DATE_FORMAT(CURDATE(), '%Y-%m-10')),
  (2, 6,    650.00, 'expense', 'Lunch at Campus Cafe',         DATE_FORMAT(CURDATE(), '%Y-%m-02')),
  (2, 6,    420.00, 'expense', 'Foodpanda delivery',           DATE_FORMAT(CURDATE(), '%Y-%m-04')),
  (2, 6,    980.00, 'expense', 'Grocery run',                  DATE_FORMAT(CURDATE(), '%Y-%m-06')),
  (2, 6,    510.00, 'expense', 'Foodpanda delivery',           DATE_FORMAT(CURDATE(), '%Y-%m-09')),
  (2, 7,    350.00, 'expense', 'Careem to campus',             DATE_FORMAT(CURDATE(), '%Y-%m-05')),
  (2, 7,    900.00, 'expense', 'Monthly bus pass',             DATE_FORMAT(CURDATE(), '%Y-%m-01')),
  (2, 8,   1200.00, 'expense', 'Semester textbooks',           DATE_FORMAT(CURDATE(), '%Y-%m-07')),
  (2, 9,    800.00, 'expense', 'Movie night with friends',     DATE_FORMAT(CURDATE(), '%Y-%m-12')),
  (2, 10,  1500.00, 'expense', 'New sneakers',                 DATE_FORMAT(CURDATE(), '%Y-%m-11')),
  (2, 12,   250.00, 'expense', 'Spotify subscription',         DATE_FORMAT(CURDATE(), '%Y-%m-01')),
  -- Last month
  (2, 1,  20000.00, 'income',  'Monthly allowance',            DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-03')),
  (2, 2,  15000.00, 'income',  'Merit scholarship',            DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-15')),
  (2, 6,   3200.00, 'expense', 'Groceries and cafeteria',      DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-08')),
  (2, 7,    750.00, 'expense', 'Transport for the month',      DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-04')),
  (2, 8,   2200.00, 'expense', 'Lab fee',                      DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-10')),
  (2, 9,    600.00, 'expense', 'Bowling with friends',         DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-18')),
  (2, 15,  1000.00, 'expense', 'Hostel mess contribution',     DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01'));

  -- ---------------------------------------------------------------------
  -- Budgets for the current month
  -- ---------------------------------------------------------------------
  INSERT INTO `budgets` (`user_id`, `category_id`, `month`, `limit_amount`) VALUES
  (2, 6, DATE_FORMAT(CURDATE(), '%Y-%m'), 3000.00),
  (2, 7, DATE_FORMAT(CURDATE(), '%Y-%m'), 1200.00),
  (2, 9, DATE_FORMAT(CURDATE(), '%Y-%m'), 1000.00),
  (2, 10, DATE_FORMAT(CURDATE(), '%Y-%m'), 2000.00);

  -- ---------------------------------------------------------------------
  -- Published saving tips (admin-authored)
  -- ---------------------------------------------------------------------
  INSERT INTO `saving_tips` (`title`, `content`, `topic`, `is_published`, `admin_id`) VALUES
  ('Try the 50/30/20 rule', 'Split your monthly money into 50% needs, 30% wants and 20% savings. It is a simple starting point most students can adapt.', 'general', 1, 1),
  ('Cook one extra portion', 'When you cook, make double and save the rest for tomorrow. It cuts food delivery spending fast without changing your routine.', 'food', 1, 1),
  ('Use student discounts everywhere', 'Many cinemas, transport apps and software subscriptions offer a student rate — always ask or check before paying full price.', 'shopping', 1, 1),
  ('Buy used textbooks first', 'Check your class seniors, campus groups or second-hand bookshops before buying textbooks new — it can save more than half the cost.', 'academics', 1, 1),
  ('Batch your errands', 'Combine trips (bank, grocery, printing) into one outing to cut down on repeated transport costs during the week.', 'transport', 1, 1),
  ('Set a weekly entertainment cap', 'Decide a fixed weekly amount for outings and entertainment before the week starts — it keeps spontaneous plans from adding up unnoticed.', 'entertainment', 1, 1);

  -- Draft tip (not published, only visible in the admin panel)
  INSERT INTO `saving_tips` (`title`, `content`, `topic`, `is_published`, `admin_id`) VALUES
  ('Compare mobile data plans every semester', 'Providers change student bundles often — a five-minute comparison each semester can lower a recurring monthly cost.', 'general', 0, 1);

  -- ---------------------------------------------------------------------
  -- Announcements
  -- ---------------------------------------------------------------------
  INSERT INTO `announcements` (`title`, `description`, `status`, `admin_id`) VALUES
  ('Welcome to CampusCoin', 'Track your income and expenses, set budgets and get personalised saving tips — all in one place, built for student life.', 'active', 1),
  ('New: AI-powered insights', 'Enable AI insights from the .env file to get plain-language monthly summaries and smarter category suggestions.', 'inactive', 1);

  -- ---------------------------------------------------------------------
  -- A sample resolved contact message (for the admin panel demo)
  -- ---------------------------------------------------------------------
  INSERT INTO `contact_messages` (`name`, `email`, `subject`, `message`, `ip`, `status`, `created_at`) VALUES
  ('Hassan Raza', 'hassan.raza@example.com', 'Question about budgets', 'Hi, can budgets roll over unused amounts to next month? Just curious how that works.', '127.0.0.1', 'closed', NOW() - INTERVAL 12 DAY);
