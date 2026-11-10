-- ScholarMatch Student Accounts: additive migration (MySQL 8 / MariaDB)
-- BACK UP your existing database. Select the existing database in phpMyAdmin
-- before running this file. Do not drop existing tables or import a fresh schema.
-- This creates ONLY student_account, referencing the existing profile(id).
-- No old tables/columns/data are altered or removed.
CREATE TABLE IF NOT EXISTS student_account (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_student_account_profile (profile_id),
    UNIQUE KEY uq_student_account_email (email),
    CONSTRAINT fk_student_account_profile FOREIGN KEY (profile_id)
        REFERENCES profile(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
