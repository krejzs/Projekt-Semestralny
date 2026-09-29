

-- tworzenia tbeli do przydzielania rol
CREATE TABLE IF NOT EXISTS roles (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(20) NOT NULL,
    CONSTRAINT uq_roles_name UNIQUE (name)
) ENGINE=InnoDB;

INSERT INTO roles (name) VALUES ('client'), ('employee'), ('admin');

--dodanie kolumn potrzebnych do logowania do tabeli uzytkownikow

ALTER TABLE users
    ADD COLUMN role_id INT NOT NULL DEFAULT 1 AFTER password_hash,
    ADD COLUMN is_email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role_id,
    ADD COLUMN email_verification_token VARCHAR(64) NULL AFTER is_email_verified,
    ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0 AFTER email_verification_token,
    ADD COLUMN locked_until DATETIME NULL AFTER failed_login_attempts,
    ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;

-- powiazanie pracownika z kontem 

ALTER TABLE employees
    ADD COLUMN user_id INT NULL AFTER id,
    ADD CONSTRAINT uq_employees_user UNIQUE (user_id),
    ADD CONSTRAINT fk_employees_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE SET NULL;

-- Reset hasła
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token       VARCHAR(64) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_password_resets_token UNIQUE (token),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

