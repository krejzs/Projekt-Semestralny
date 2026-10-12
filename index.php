<?php

declare(strict_types=1);
require_once __DIR__ . '/konfiguracja/ustawienia.php';

if (is_logged_in()) {
    redirect('/publiczne/profil.php');
}

$errors     = [];
$emailOld   = '';
$success    = flash('success');
$errorFlash = flash('error');
if ($errorFlash) {
    $errors[] = $errorFlash;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $emailOld = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($emailOld === '' || $password === '') {
            $errors[] = 'Podaj adres e-mail i hasło.';
        } else {
            try {
                $pdo  = getPDO();
                $user = attempt_login($pdo, $emailOld, $password);
                login_session($user);

                // Po zalogowaniu kierujemy dalej w zaleznosci od roli.
                $redirectMap = [
                    'admin'    => '/administrator/panel.php',
                    'employee' => '/pracownik/panel.php',
                    'client'   => '/klient/panel.php',
                ];
                redirect($redirectMap[$user['role_name']] ?? '/publiczne/profil.php');
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
    <title>System rezerwacji usług</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/zasoby/styl.css">
</head>
<body>

<!--
    Pasek nawigacji strony glownej.
    Na razie prowadzi tylko do logowania/rejestracji, ale sciezki ponizej
    sa juz gotowe pod przyszle podstrony glownej czesci serwisu
    (katalog uslug, strona "o nas" itd.) - wystarczy w tamtym momencie
    utworzyc plik pod wskazana sciezka, link juz tu czeka.
-->
<nav class="topbar" style="max-width:480px;margin:24px auto 0;">
    <div><a href="<?= BASE_URL ?>/index.php"><strong>Szkoła Językowa</strong></a></div>
    <div>
        <a href="<?= BASE_URL ?>/publiczne/uslugi.php">Usługi</a>
        &nbsp;|&nbsp;
        <a href="<?= BASE_URL ?>/publiczne/rejestracja.php">Rejestracja</a>
    </div>
</nav>

<div class="auth-box">
    <h1>Zaloguj się</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/index.php" novalidate>
        <?= csrf_field() ?>

        <label>Adres e-mail
            <input type="email" name="email" value="<?= e($emailOld) ?>" required autofocus>
        </label>

        <label>Hasło
            <input type="password" name="password" required>
        </label>

        <button type="submit">Zaloguj się</button>
    </form>

    <p>
        <a href="<?= BASE_URL ?>/publiczne/zapomniane_haslo.php">Nie pamiętasz hasła?</a>
    </p>
    <p>Nie masz jeszcze konta? <a href="<?= BASE_URL ?>/publiczne/rejestracja.php">Zarejestruj się</a></p>
</div>
</body>
</html>
