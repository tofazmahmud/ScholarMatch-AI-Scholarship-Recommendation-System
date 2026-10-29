USE scholarmatch;
-- ScholarMatch Admin V3 ONLY. Backup your database first.
-- Does not touch the existing bookmark or student scholarship tables.
-- Compatible with MySQL/MariaDB versions commonly bundled with XAMPP.
CREATE TABLE IF NOT EXISTS admin_access (
 admin_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 role ENUM('superadmin','editor','viewer') NOT NULL DEFAULT 'viewer',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 display_name VARCHAR(120) NULL,
 email VARCHAR(254) NULL,
 last_login_at DATETIME NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_admin_access_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- The ORIGINAL trusted admins receive superadmin access; newly added staff
-- must be created through the V3 staff-management page.
INSERT INTO admin_access (admin_id,role,is_active)
 SELECT a.id,'superadmin',1 FROM admin a LEFT JOIN admin_access aa ON aa.admin_id=a.id
 WHERE aa.admin_id IS NULL;
CREATE TABLE IF NOT EXISTS admin_audit_log (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 admin_id BIGINT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL,
 subject_type VARCHAR(60) NOT NULL,
 subject_id BIGINT UNSIGNED NULL,
 detail VARCHAR(500) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 KEY idx_admin_audit_date(created_at),
 KEY idx_admin_audit_actor(admin_id),
 CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS admin_site_setting (
 setting_key VARCHAR(90) NOT NULL PRIMARY KEY,
 setting_value TEXT NOT NULL,
 updated_by BIGINT UNSIGNED NULL,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_setting_admin FOREIGN KEY (updated_by) REFERENCES admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO admin_site_setting(setting_key, setting_value)
VALUES ('public_announcement', ''), ('support_email', ''), ('catalogue_notice', '');
