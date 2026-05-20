USE gestion_recettes;

ALTER TABLE users
    ADD UNIQUE KEY uniq_users_nom (nom);

CREATE TABLE IF NOT EXISTS login_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_login_links_token (token),
    KEY idx_login_links_user_id (user_id),
    KEY idx_login_links_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
