CREATE TABLE IF NOT EXISTS timesheets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id   INT UNSIGNED NOT NULL,
    user_id       INT UNSIGNED NOT NULL,
    work_date     DATE NOT NULL,
    hours         DECIMAL(5,2) NOT NULL DEFAULT 0,
    description   VARCHAR(500) NULL,
    project       VARCHAR(200) NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by   INT UNSIGNED NULL,
    approved_at   DATETIME NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ts_biz (business_id),
    INDEX idx_ts_user (business_id, user_id),
    INDEX idx_ts_date (business_id, work_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
