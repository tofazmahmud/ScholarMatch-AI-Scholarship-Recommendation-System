-- ScholarMatch V2 ADDITIVE migration for MySQL 8 / MariaDB (XAMPP)
-- Backup existing scholarmatch database first. Run once in phpMyAdmin SQL tab.
USE scholarmatch;
-- STOP if scripts/database_preflight.php reports a BROKEN table; SQL cannot fix orphaned tablespaces safely.
CREATE TABLE IF NOT EXISTS bookmark (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 profile_id BIGINT UNSIGNED NOT NULL,
 scholarship_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_bookmark (profile_id, scholarship_id),
 FOREIGN KEY (profile_id) REFERENCES profile(id) ON DELETE CASCADE,
 FOREIGN KEY (scholarship_id) REFERENCES scholarship(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS application_tracker (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 profile_id BIGINT UNSIGNED NOT NULL,
 scholarship_id BIGINT UNSIGNED NOT NULL,
 status ENUM('Interested','Preparing','Applied','Interview','Awarded','Rejected') NOT NULL DEFAULT 'Interested',
 notes TEXT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_tracker (profile_id, scholarship_id),
 FOREIGN KEY (profile_id) REFERENCES profile(id) ON DELETE CASCADE,
 FOREIGN KEY (scholarship_id) REFERENCES scholarship(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS recommendation_state (
 profile_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 source_hash CHAR(64) NOT NULL,
 generated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (profile_id) REFERENCES profile(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS scholarship_embedding (
 scholarship_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 model_name VARCHAR(100) NOT NULL,
 content_hash CHAR(64) NOT NULL,
 dimensions INT UNSIGNED NOT NULL,
 vector_json LONGTEXT NOT NULL,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (scholarship_id) REFERENCES scholarship(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS scholarship_document (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 scholarship_id BIGINT UNSIGNED NOT NULL,
 title VARCHAR(255) NOT NULL,
 source_url VARCHAR(2048) NULL,
 is_verified TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (scholarship_id) REFERENCES scholarship(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS scholarship_chunk (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 document_id BIGINT UNSIGNED NOT NULL,
 sequence_no INT UNSIGNED NOT NULL,
 chunk_text TEXT NOT NULL,
 UNIQUE KEY uq_doc_seq (document_id,sequence_no),
 FOREIGN KEY (document_id) REFERENCES scholarship_document(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Safe recovery: run only after earlier import finishes or stops.
-- Ensure source_verified_at column exists.
SET @sm_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'scholarship'
     AND COLUMN_NAME = 'source_verified_at') = 0,
  'ALTER TABLE scholarship ADD COLUMN source_verified_at DATE NULL',
  'SELECT ''source_verified_at already present'' AS migration_info'
);
PREPARE sm_stmt FROM @sm_sql;
EXECUTE sm_stmt;
DEALLOCATE PREPARE sm_stmt;

-- Ensure source_status column exists.
SET @sm_sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'scholarship'
     AND COLUMN_NAME = 'source_status') = 0,
  'ALTER TABLE scholarship ADD COLUMN source_status ENUM(''Unverified'',''Verified'',''Needs Review'') NOT NULL DEFAULT ''Unverified''',
  'SELECT ''source_status already present'' AS migration_info'
);
PREPARE sm_stmt FROM @sm_sql;
EXECUTE sm_stmt;
DEALLOCATE PREPARE sm_stmt;
