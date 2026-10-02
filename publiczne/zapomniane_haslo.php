<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '' || !validate_email_format($email)) {
            $errors[] = 'Podaj poprawny adres e-mail.';
        } else {
            $pdo   = getPDO();
            $token = create_password_reset_token($pdo, $email);

            $success = 'Jeśli podany adres e-mail istnieje w naszej bazie, wysłaliśmy na niego link do zresetowania hasła.';

            if ($token !== null) {
                $resetUrl = '/publiczne/reset_hasla.php?token=' . urlencode($token);
                $success .= ' <a href="' . e($resetUrl) . '">(link testowy - kliknij tutaj)</a>';
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
    <title>Przypomnienie hasła</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="auth-box">
    <h1>Nie pamiętasz hasła?</h1>
    <p>Podaj adres e-mail powiązany z kontem, a wyślemy Ci link do ustawienia nowego hasła.</p>

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

    <form method="post" action="/publiczne/zapomniane_haslo.php" novalidate>
        <?= csrf_field() ?>
        <label>Adres e-mail
            <input type="email" name="email" required autofocus>
        </label>
        <button type="submit">Wyślij link resetujący</button>
    </form>

    <p><a href="/index.php">Wróć do logowania</a></p>
</div>
</body>
</html>
