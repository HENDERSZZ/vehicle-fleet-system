-- 🔹 1. Log license usage history
CREATE TABLE license_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);

-- 🔹 2. Multi-device tracking
CREATE TABLE license_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    device_hash VARCHAR(255) NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    UNIQUE (license_id, device_hash),
    FOREIGN KEY (license_id) REFERENCES licenses(id) ON DELETE CASCADE
);

-- 🔹 3. License revocation support
ALTER TABLE licenses ADD COLUMN is_revoked TINYINT(1) DEFAULT 0 AFTER is_used;
