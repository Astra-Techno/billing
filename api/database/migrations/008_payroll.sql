-- Staff Members
CREATE TABLE IF NOT EXISTS `staff_members` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `name`          VARCHAR(255)    NOT NULL,
    `role`          VARCHAR(100)    DEFAULT NULL,
    `mobile`        VARCHAR(20)     DEFAULT NULL,
    `upi_id`        VARCHAR(100)    DEFAULT NULL,
    `bank_name`     VARCHAR(100)    DEFAULT NULL,
    `bank_account`  VARCHAR(50)     DEFAULT NULL,
    `bank_ifsc`     VARCHAR(20)     DEFAULT NULL,
    `monthly_salary` DECIMAL(15,2)  NOT NULL DEFAULT 0,
    `join_date`     DATE            DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `sm_business` (`business_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payroll Runs (one row per staff per month)
CREATE TABLE IF NOT EXISTS `payroll_runs` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `business_id`   BIGINT UNSIGNED NOT NULL,
    `staff_id`      BIGINT UNSIGNED NOT NULL,
    `month`         TINYINT         NOT NULL COMMENT '1-12',
    `year`          SMALLINT        NOT NULL,
    `working_days`  SMALLINT        NOT NULL DEFAULT 30,
    `days_worked`   SMALLINT        NOT NULL DEFAULT 30,
    `basic_salary`  DECIMAL(15,2)   NOT NULL DEFAULT 0,
    `bonus`         DECIMAL(15,2)   NOT NULL DEFAULT 0,
    `deductions`    DECIMAL(15,2)   NOT NULL DEFAULT 0,
    `net_pay`       DECIMAL(15,2)   NOT NULL DEFAULT 0,
    `note`          VARCHAR(500)    DEFAULT NULL,
    `status`        ENUM('draft','paid') NOT NULL DEFAULT 'draft',
    `paid_date`     DATE            DEFAULT NULL,
    `method`        ENUM('cash','upi','neft','cheque','other') DEFAULT 'cash',
    `expense_id`    BIGINT UNSIGNED DEFAULT NULL COMMENT 'linked expense record',
    `created_at`    TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`staff_id`)    REFERENCES `staff_members`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_payroll` (`business_id`, `staff_id`, `month`, `year`),
    INDEX `pr_month` (`business_id`, `year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
