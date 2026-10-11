<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

$token = trim((string) ($_GET['token'] ?? ''));
$pdo   = getPDO();

if ($token !== '' && verify_email_token($pdo, $token)) {
    flash('success', 'Adres e-mail został potwierdzony. Możesz się teraz zalogować.');
} else {
    flash('error', 'Link weryfikacyjny jest nieprawidłowy albo został już wykorzystany.');
}

redirect('/index.php');
