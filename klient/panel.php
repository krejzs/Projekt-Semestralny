<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['client']);

$pdo = getPDO();

$stmt = $pdo->prepare(
    'SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.price_at_booking,
            s.name AS service_name,
            CONCAT(e.first_name, \' \', e.last_name) AS employee_name,
            st.name AS status_name
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN employees e ON e.id = r.employee_id
     JOIN reservation_statuses st ON st.id = r.status_id
     WHERE r.client_id = :client_id
     ORDER BY r.reservation_date DESC, r.start_time DESC'
);
$stmt->execute(['client_id' => current_user_id()]);
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel klienta</title>
    <link rel="stylesheet" href="/zasoby/styl.css">
</head>
<body>
<div class="panel panel-wide">
    <div class="topbar">
        <div><strong>Witaj, <?= e($_SESSION['first_name']) ?></strong> <span class="badge">klient</span></div>
        <div><a href="/publiczne/profil.php">Mój profil</a> &nbsp;|&nbsp; <a href="/publiczne/wylogowanie.php">Wyloguj się</a></div>
    </div>

    <h1>Moje rezerwacje</h1>

    <?php if (empty($reservations)): ?>
        <p>Nie masz jeszcze żadnych rezerwacji.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Data</th><th>Godzina</th><th>Usługa</th><th>Pracownik</th><th>Cena</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservations as $r): ?>
                    <tr>
                        <td><?= e($r['reservation_date']) ?></td>
                        <td><?= e(substr($r['start_time'], 0, 5)) ?>-<?= e(substr($r['end_time'], 0, 5)) ?></td>
                        <td><?= e($r['service_name']) ?></td>
                        <td><?= e($r['employee_name']) ?></td>
                        <td><?= e(number_format((float) $r['price_at_booking'], 2)) ?> zł</td>
                        <td><?= e($r['status_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
