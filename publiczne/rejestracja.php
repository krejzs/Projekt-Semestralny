<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

if (is_logged_in()) {
    redirect('/publiczne/profil.php');
}

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $old['first_name'] = trim((string) ($_POST['first_name'] ?? ''));
        $old['last_name']  = trim((string) ($_POST['last_name'] ?? ''));
        $old['email']      = trim((string) ($_POST['email'] ?? ''));
        $password  = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        if ($old['first_name'] === '' || $old['last_name'] === '' || $old['email'] === '' || $password === '' || $password2 === '') {
            $errors[] = 'Wszystkie pola są wymagane.';
        }
        if ($old['email'] !== '' && !validate_email_format($old['email'])) {
            $errors[] = 'Podaj poprawny adres e-mail.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Hasło musi mieć co najmniej 8 znaków.';
        }
        if ($password !== '' && $password !== $password2) {
            $errors[] = 'Podane hasła nie są zgodne.';
        }

        if (empty($errors)) {
            try {
                $pdo  = getPDO();
                register_user($pdo, $old['first_name'], $old['last_name'], $old['email'], $password);
                $user = find_user_by_email($pdo, $old['email']);

                $verifyUrl = '/publiczne/potwierdzenie_email.php?token=' . urlencode((string) $user['email_verification_token']);
                flash('success', 'Konto zostało utworzone. Aby dokończyć rejestrację, potwierdź adres e-mail: '
                    . '<a href="' . e($verifyUrl) . '">kliknij tutaj, aby aktywować konto</a>.');
                redirect('/index.php');
            } catch (AuthException $e) {
                $errors[] = $e->getMessage();
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
    <title>Rejestracja - System rezerwacji usług</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="auth-box">
    <h1>Załóż konto klienta</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="/publiczne/rejestracja.php" novalidate>
        <?= csrf_field() ?>

        <label>Imię
            <input type="text" name="first_name" value="<?= e($old['first_name']) ?>" required maxlength="50" autofocus>
        </label>

        <label>Nazwisko
            <input type="text" name="last_name" value="<?= e($old['last_name']) ?>" required maxlength="50">
        </label>

        <label>Adres e-mail
            <input type="email" name="email" value="<?= e($old['email']) ?>" required maxlength="100">
        </label>

        <label>Hasło (min. 8 znaków)
            <input type="password" name="password" required minlength="8">
        </label>

        <label>Powtórz hasło
            <input type="password" name="password_confirm" required minlength="8">
        </label>

        <button type="submit">Zarejestruj się</button>
    </form>

    <p>Masz już konto? <a href="/index.php">Zaloguj się</a></p>
</div>
</body>
</html>
