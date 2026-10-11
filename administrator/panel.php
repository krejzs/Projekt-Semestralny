<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo = getPDO();

$counts = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM users WHERE role_id = (SELECT id FROM roles WHERE name = \'client\')) AS clients,
        (SELECT COUNT(*) FROM employees) AS employees,
        (SELECT COUNT(*) FROM services) AS services,
        (SELECT COUNT(*) FROM reservations) AS reservations'
)->fetch();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel administratora</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="panel panel-wide">
    <div class="topbar">
        <div><strong>Witaj, <?= e($_SESSION['first_name']) ?></strong> <span class="badge">administrator</span></div>
        <div><a href="/publiczne/profil.php">Mój profil</a> &nbsp;|&nbsp; <a href="/publiczne/wylogowanie.php">Wyloguj się</a></div>
    </div>

    <h1>Panel administratora</h1>

    <table>
        <tbody>
            <tr><th>Liczba klientów</th><td><?= (int) $counts['clients'] ?></td></tr>
            <tr><th>Liczba pracowników</th><td><?= (int) $counts['employees'] ?></td></tr>
            <tr><th>Liczba usług</th><td><?= (int) $counts['services'] ?></td></tr>
            <tr><th>Liczba rezerwacji</th><td><?= (int) $counts['reservations'] ?></td></tr>
        </tbody>
    </table>

    <p><a href="/administrator/uzytkownicy.php">&rarr; Zarządzanie użytkownikami</a></p>
</div>
</body>
</html>
