-- ScholarMatch | MySQL 8.0+ schema
-- Version 1.0 | Student side: anonymous sessions; admin: authenticated
-- Run once on a MySQL server using phpMyAdmin, Workbench or MySQL CLI.
CREATE DATABASE IF NOT EXISTS scholarmatch
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE scholarmatch;

-- Admin accounts must be created by the application using a secure password hash.
CREATE TABLE IF NOT EXISTS admin (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS profile (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    cv_file_path VARCHAR(500) NULL,
    education VARCHAR(255) NULL,
    degree VARCHAR(150) NULL,
    field VARCHAR(150) NULL,
    gpa DECIMAL(4,2) NULL,
    skills TEXT NULL,
    interests TEXT NULL,
    experience TEXT NULL,
    publications TEXT NULL,
    projects TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_profile_gpa CHECK (gpa IS NULL OR gpa >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS preference (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    degree_level VARCHAR(150) NULL,
    field VARCHAR(150) NULL,
    country VARCHAR(100) NULL,
    funding_type VARCHAR(100) NULL,
    deadline_from DATE NULL,
    deadline_to DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_preference_profile (profile_id),
    CONSTRAINT fk_preference_profile FOREIGN KEY (profile_id)
        REFERENCES profile(id) ON DELETE CASCADE,
    CONSTRAINT chk_preference_deadline CHECK (
        deadline_from IS NULL OR deadline_to IS NULL OR deadline_from <= deadline_to
    )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scholarship (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by_admin_id BIGINT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    provider VARCHAR(255) NOT NULL,
    country VARCHAR(100) NOT NULL,
    degree_level VARCHAR(150) NULL,
    field VARCHAR(150) NULL,
    funding_type VARCHAR(100) NULL,
    deadline DATE NULL,
    official_url VARCHAR(2048) NOT NULL,
    description_text TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_scholarship_country (country),
    KEY idx_scholarship_degree (degree_level),
    KEY idx_scholarship_field (field),
    KEY idx_scholarship_deadline (deadline),
    KEY idx_scholarship_active (is_active),
    KEY idx_scholarship_admin (created_by_admin_id),
    CONSTRAINT fk_scholarship_admin FOREIGN KEY (created_by_admin_id)
        REFERENCES admin(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS requirement (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scholarship_id BIGINT UNSIGNED NOT NULL,
    requirement_type VARCHAR(100) NOT NULL,
    value TEXT NOT NULL,
    comparison_operator ENUM('=','!=','>','>=','<','<=','IN','NOT IN','CONTAINS') NOT NULL DEFAULT '=',
    certainty ENUM('explicit','ambiguous') NOT NULL DEFAULT 'explicit',
    KEY idx_requirement_scholarship (scholarship_id),
    CONSTRAINT fk_requirement_scholarship FOREIGN KEY (scholarship_id)
        REFERENCES scholarship(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recommendation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    profile_id BIGINT UNSIGNED NOT NULL,
    scholarship_id BIGINT UNSIGNED NOT NULL,
    eligibility_status ENUM(
        'Eligible', 'Potentially Eligible', 'Not Eligible', 'Unknown/Manual Check'
    ) NOT NULL,
    match_score DECIMAL(5,2) NULL,
    suitability_label VARCHAR(80) NULL,
    ai_blurb TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_recommendation_profile_scholarship (profile_id, scholarship_id),
    KEY idx_recommendation_profile (profile_id),
    KEY idx_recommendation_scholarship (scholarship_id),
    CONSTRAINT fk_recommendation_profile FOREIGN KEY (profile_id)
        REFERENCES profile(id) ON DELETE CASCADE,
    CONSTRAINT fk_recommendation_scholarship FOREIGN KEY (scholarship_id)
        REFERENCES scholarship(id) ON DELETE CASCADE,
    CONSTRAINT chk_match_score CHECK (
        match_score IS NULL OR (match_score >= 0 AND match_score <= 100)
    )
) ENGINE=InnoDB;
