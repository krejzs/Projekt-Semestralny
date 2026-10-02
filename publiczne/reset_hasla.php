<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

$errors = [];
$token  = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$pdo    = getPDO();

$resetRow = $token !== '' ? validate_reset_token($pdo, $token) : null;

if ($token === '' || $resetRow === null) {
    flash('error', 'Link do resetu hasła jest nieprawidłowy albo wygasł. Poproś o nowy.');
    redirect('/publiczne/zapomniane_haslo.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $password  = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
        }
        if ($password !== $password2) {
            $errors[] = 'Podane hasła nie są zgodne.';
        }

        if (empty($errors)) {
            try {
                apply_password_reset($pdo, $resetRow, $password);
                flash('success', 'Hasło zostało zmienione. Możesz się teraz zalogować.');
                redirect('/index.php');
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $errors[] = 'Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ustaw nowe hasło</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="auth-box">
    <h1>Ustaw nowe hasło</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/publiczne/reset_hasla.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label>Nowe hasło (min. 8 znaków)
            <input type="password" name="password" required minlength="8" autofocus>
        </label>

        <label>Powtórz nowe hasło
            <input type="password" name="password_confirm" required minlength="8">
        </label>

        <button type="submit">Zapisz nowe hasło</button>
    </form>
</div>
</body>
</html>
