<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_login();

$pdo  = getPDO();
$user = find_user_by_id($pdo, (int) current_user_id());

if (!$user) {
    logout_session();
    redirect('/index.php');
}

$profileErrors  = [];
$passwordErrors = [];
$profileSuccess = null;
$passwordSuccess = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
    $profileErrors[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'profile') {
    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $lastName  = trim((string) ($_POST['last_name'] ?? ''));
    $phone     = trim((string) ($_POST['phone'] ?? ''));

    if ($firstName === '' || $lastName === '') {
        $profileErrors[] = 'Imię i nazwisko są wymagane.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+ ]{6,20}$/', $phone)) {
        $profileErrors[] = 'Podany numer telefonu ma nieprawidłowy format.';
    }

    if (empty($profileErrors)) {
        update_profile($pdo, (int) $user['id'], $firstName, $lastName, $phone);
        $user = find_user_by_id($pdo, (int) $user['id']);
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];
        $profileSuccess = 'Dane profilu zostały zaktualizowane.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'password') {
    $oldPassword  = (string) ($_POST['old_password'] ?? '');
    $newPassword  = (string) ($_POST['new_password'] ?? '');
    $newPassword2 = (string) ($_POST['new_password_confirm'] ?? '');

    if ($oldPassword === '' || $newPassword === '' || $newPassword2 === '') {
        $passwordErrors[] = 'Wszystkie pola dotyczące hasła są wymagane.';
    }
    if (strlen($newPassword) < 8) {
        $passwordErrors[] = 'Nowe hasło musi mieć co najmniej 8 znaków.';
    }
    if ($newPassword !== $newPassword2) {
        $passwordErrors[] = 'Nowe hasła nie są zgodne.';
    }

    if (empty($passwordErrors)) {
        try {
            change_password($pdo, (int) $user['id'], $oldPassword, $newPassword);
            $passwordSuccess = 'Hasło zostało zmienione.';
        } catch (AuthException $e) {
            $passwordErrors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mój profil</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="panel">
    <div class="topbar">
        <div>
            <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
            <span class="badge"><?= e($user['role_name']) ?></span>
        </div>
        <a href="/publiczne/wylogowanie.php">Wyloguj się</a>
    </div>

    <h1>Mój profil</h1>

    <?php if ($profileSuccess): ?><div class="alert alert-success"><?= e($profileSuccess) ?></div><?php endif; ?>
    <?php if (!empty($profileErrors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($profileErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/publiczne/profil.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="profile">

        <label>Imię
            <input type="text" name="first_name" value="<?= e($user['first_name']) ?>" required maxlength="50">
        </label>
        <label>Nazwisko
            <input type="text" name="last_name" value="<?= e($user['last_name']) ?>" required maxlength="50">
        </label>
        <label>Telefon
            <input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" maxlength="20">
        </label>
        <label>E-mail (nie można zmienić samodzielnie)
            <input type="email" value="<?= e($user['email']) ?>" disabled>
        </label>

        <button type="submit">Zapisz zmiany</button>
    </form>

    <hr>

    <h1>Zmiana hasła</h1>

    <?php if ($passwordSuccess): ?><div class="alert alert-success"><?= e($passwordSuccess) ?></div><?php endif; ?>
    <?php if (!empty($passwordErrors)): ?>
        <div class="alert alert-error">
            <ul><?php foreach ($passwordErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/publiczne/profil.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="password">

        <label>Obecne hasło
            <input type="password" name="old_password" required>
        </label>
        <label>Nowe hasło (min. 8 znaków)
            <input type="password" name="new_password" required minlength="8">
        </label>
        <label>Powtórz nowe hasło
            <input type="password" name="new_password_confirm" required minlength="8">
        </label>

        <button type="submit">Zmień hasło</button>
    </form>

    <p>
        <?php if ($user['role_name'] === 'client'): ?>
            <a href="/klient/panel.php">&larr; Wróć do panelu klienta</a>
        <?php elseif ($user['role_name'] === 'employee'): ?>
            <a href="/pracownik/panel.php">&larr; Wróć do panelu pracownika</a>
        <?php else: ?>
            <a href="/administrator/panel.php">&larr; Wróć do panelu administratora</a>
        <?php endif; ?>
    </p>
</div>
</body>
</html>
