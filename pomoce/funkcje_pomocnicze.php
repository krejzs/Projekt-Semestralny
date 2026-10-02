<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Musisz się zalogować, aby zobaczyć tę stronę.');
        redirect('/index.php');
    }
}

/**
 * @param string[] $allowedRoles
 */
function require_role(array $allowedRoles): void
{
    require_login();

    if (!in_array(current_role(), $allowedRoles, true)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8">'
           . '<title>Brak dostępu</title><link rel="stylesheet" href="/zasoby/styl.css"></head>'
           . '<body><div class="auth-box"><h1>403 - Brak dostępu</h1>'
           . '<p>Nie masz uprawnień do wyświetlenia tej strony.</p>'
           . '<p><a href="/index.php">Wróć do logowania</a></p></div></body></html>';
        exit;
    }
}

function validate_email_format(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}
