<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo = getPDO();

$stmt = $pdo->query(
    'SELECT u.id, u.first_name, u.last_name, u.email, u.is_email_verified, u.locked_until, r.name AS role_name
     FROM users u
     JOIN roles r ON r.id = u.role_id
     ORDER BY u.id ASC'
);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Zarządzanie użytkownikami</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="panel panel-wide">
    <div class="topbar">
        <div><strong>Witaj, <?= e($_SESSION['first_name']) ?></strong> <span class="badge">administrator</span></div>
        <div><a href="/administrator/panel.php">&larr; Panel</a> &nbsp;|&nbsp; <a href="/publiczne/wylogowanie.php">Wyloguj się</a></div>
    </div>

    <h1>Użytkownicy systemu</h1>

    <table>
        <thead>
            <tr><th>ID</th><th>Imię i nazwisko</th><th>E-mail</th><th>Rola</th><th>Zweryfikowany</th><th>Zablokowany</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int) $u['id'] ?></td>
                    <td><?= e($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['role_name']) ?></td>
                    <td><?= $u['is_email_verified'] ? 'tak' : 'nie' ?></td>
                    <td><?= ($u['locked_until'] && strtotime($u['locked_until']) > time()) ? 'tak' : 'nie' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
