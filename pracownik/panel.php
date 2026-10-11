<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['employee']);

$pdo = getPDO();

$stmt = $pdo->prepare('SELECT id, first_name, last_name FROM employees WHERE user_id = :user_id');
$stmt->execute(['user_id' => current_user_id()]);
$employee = $stmt->fetch();

$reservations = [];
if ($employee) {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.reservation_date, r.start_time, r.end_time,
                s.name AS service_name,
                CONCAT(u.first_name, \' \', u.last_name) AS client_name,
                st.name AS status_name
         FROM reservations r
         JOIN services s ON s.id = r.service_id
         JOIN users u ON u.id = r.client_id
         JOIN reservation_statuses st ON st.id = r.status_id
         WHERE r.employee_id = :employee_id
         ORDER BY r.reservation_date ASC, r.start_time ASC'
    );
    $stmt->execute(['employee_id' => $employee['id']]);
    $reservations = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel pracownika</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="panel panel-wide">
    <div class="topbar">
        <div><strong>Witaj, <?= e($_SESSION['first_name']) ?></strong> <span class="badge">pracownik</span></div>
        <div><a href="/publiczne/profil.php">Mój profil</a> &nbsp;|&nbsp; <a href="/publiczne/wylogowanie.php">Wyloguj się</a></div>
    </div>

    <h1>Moje najbliższe wizyty</h1>

    <?php if (!$employee): ?>
        <div class="alert alert-error">
            To konto ma rolę pracownika, ale nie jest jeszcze powiązane z żadnym profilem
            pracownika w tabeli <code>employees</code>. Skontaktuj się z administratorem.
        </div>
    <?php elseif (empty($reservations)): ?>
        <p>Brak zaplanowanych wizyt.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Data</th><th>Godzina</th><th>Usługa</th><th>Klient</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= e($r['reservation_date']) ?></td>
                        <td><?= e(substr($r['start_time'], 0, 5)) ?>-<?= e(substr($r['end_time'], 0, 5)) ?></td>
                        <td><?= e($r['service_name']) ?></td>
                        <td><?= e($r['client_name']) ?></td>
                        <td><?= e($r['status_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
