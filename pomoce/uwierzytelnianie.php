<?php

declare(strict_types=1);

class AuthException extends Exception
{
}

function get_role_id(PDO $pdo, string $roleName): int
{
    $stmt = $pdo->prepare('SELECT id FROM roles WHERE name = :name');
    $stmt->execute(['name' => $roleName]);
    $role = $stmt->fetch();

    if (!$role) {
        throw new AuthException('Nieznana rola użytkownika.');
    }

    return (int) $role['id'];
}

function email_exists(PDO $pdo, string $email): bool
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute(['email' => $email]);

    return (bool) $stmt->fetch();
}

function register_user(PDO $pdo, string $firstName, string $lastName, string $email, string $password): int
{
    if (email_exists($pdo, $email)) {
        throw new AuthException('Ten adres e-mail jest już zarejestrowany.');
    }

    $hash              = password_hash($password, PASSWORD_DEFAULT);
    $roleId            = get_role_id($pdo, 'client');
    $verificationToken = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare(
        'INSERT INTO users
            (first_name, last_name, email, password_hash, role_id, is_email_verified, email_verification_token)
         VALUES
            (:first_name, :last_name, :email, :password_hash, :role_id, 0, :token)'
    );
    $stmt->execute([
        'first_name'    => $firstName,
        'last_name'     => $lastName,
        'email'         => $email,
        'password_hash' => $hash,
        'role_id'       => $roleId,
        'token'         => $verificationToken,
    ]);

    return (int) $pdo->lastInsertId();
}

function find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function find_user_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.*, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function is_account_locked(array $user): bool
{
    return !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time();
}

function register_failed_attempt(PDO $pdo, array $user): void
{
    $attempts    = (int) $user['failed_login_attempts'] + 1;
    $lockedUntil = null;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        $attempts    = 0; // licznik jest zerowany po nałożeniu blokady
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET failed_login_attempts = :attempts, locked_until = :locked WHERE id = :id'
    );
    $stmt->execute([
        'attempts' => $attempts,
        'locked'   => $lockedUntil,
        'id'       => $user['id'],
    ]);
}

function reset_failed_attempts(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}

function attempt_login(PDO $pdo, string $email, string $password): array
{
    $user = find_user_by_email($pdo, $email);

    $genericError = 'Nieprawidłowy e-mail lub hasło.';

    if (!$user) {
        throw new AuthException($genericError);
    }

    if (is_account_locked($user)) {
        throw new AuthException(
            'Konto zostało tymczasowo zablokowane z powodu zbyt wielu nieudanych prób logowania. '
            . 'Spróbuj ponownie za kilkanaście minut.'
        );
    }

    if (!password_verify($password, $user['password_hash'])) {
        register_failed_attempt($pdo, $user);
        throw new AuthException($genericError);
    }

    if ((int) $user['is_email_verified'] !== 1) {
        throw new AuthException('Potwierdź adres e-mail przed zalogowaniem - sprawdź swoją skrzynkę pocztową.');
    }

    reset_failed_attempts($pdo, (int) $user['id']);

    return $user;
}

function login_session(array $user): void
{
    session_regenerate_id(true);

    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['role']          = $user['role_name'];
    $_SESSION['first_name']    = $user['first_name'];
    $_SESSION['last_name']     = $user['last_name'];
    $_SESSION['last_activity'] = time();
}

function logout_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function verify_email_token(PDO $pdo, string $token): bool
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email_verification_token = :token');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if (!$user) {
        return false;
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET is_email_verified = 1, email_verification_token = NULL WHERE id = :id'
    );
    $stmt->execute(['id' => $user['id']]);

    return true;
}

function create_password_reset_token(PDO $pdo, string $email): ?string
{
    $user = find_user_by_email($pdo, $email);

    if (!$user) {
        return null;
    }

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (user_id, token, expires_at, used) VALUES (:user_id, :token, :expires, 0)'
    );
    $stmt->execute(['user_id' => $user['id'], 'token' => $token, 'expires' => $expires]);

    return $token;
}

function validate_reset_token(PDO $pdo, string $token): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM password_resets WHERE token = :token AND used = 0 AND expires_at > NOW()'
    );
    $stmt->execute(['token' => $token]);
    $reset = $stmt->fetch();

    return $reset ?: null;
}

function apply_password_reset(PDO $pdo, array $resetRow, string $newPassword): void
{
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, failed_login_attempts = 0, locked_until = NULL WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $resetRow['user_id']]);

        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE id = :id');
        $stmt->execute(['id' => $resetRow['id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function change_password(PDO $pdo, int $userId, string $oldPassword, string $newPassword): void
{
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($oldPassword, $row['password_hash'])) {
        throw new AuthException('Podane obecne hasło jest nieprawidłowe.');
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute(['hash' => $hash, 'id' => $userId]);
}

function update_profile(PDO $pdo, int $userId, string $firstName, string $lastName, string $phone): void
{
    $stmt = $pdo->prepare(
        'UPDATE users SET first_name = :first_name, last_name = :last_name, phone = :phone WHERE id = :id'
    );
    $stmt->execute([
        'first_name' => $firstName,
        'last_name'  => $lastName,
        'phone'      => $phone,
        'id'         => $userId,
    ]);
}
