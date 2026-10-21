<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';


require_role(['admin']);

$pdo = getPDO();


$liczby = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.name = 'client') AS uczniowie,
        (SELECT COUNT(*) FROM employees) AS lektorzy,
        (SELECT COUNT(*) FROM services) AS lekcje,
        (SELECT COUNT(*) FROM reservations) AS zapisy,
        (SELECT COUNT(*) FROM reservations WHERE reservation_date = CURDATE()) AS dzisiaj"
)->fetch();


$dzisiejsze = $pdo->query(
    "SELECT r.id, r.start_time, r.end_time,
            s.name AS lekcja,
            CONCAT(u.first_name, ' ', u.last_name) AS uczen,
            CONCAT(e.first_name, ' ', e.last_name) AS lektor,
            st.name AS status
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN users u ON u.id = r.client_id
     JOIN employees e ON e.id = r.employee_id
     JOIN reservation_statuses st ON st.id = r.status_id
     WHERE r.reservation_date = CURDATE()
     ORDER BY r.start_time"
)->fetchAll();

$tytul = 'Pulpit';
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<div class="kafelki">
    <div class="kafelek"><span class="liczba"><?= (int) $liczby['uczniowie'] ?></span>Uczniowie</div>
    <div class="kafelek"><span class="liczba"><?= (int) $liczby['lektorzy'] ?></span>Lektorzy</div>
    <div class="kafelek"><span class="liczba"><?= (int) $liczby['lekcje'] ?></span>Lekcje w ofercie</div>
    <div class="kafelek"><span class="liczba"><?= (int) $liczby['zapisy'] ?></span>Wszystkie zapisy</div>
    <div class="kafelek"><span class="liczba"><?= (int) $liczby['dzisiaj'] ?></span>Zapisy na dziś</div>
</div>

<div class="karta">
    <h2>Dzisiejsze lekcje</h2>

    <?php if (empty($dzisiejsze)): ?>
        <p>Na dziś nie ma żadnych zapisów.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>Godzina</th>
                        <th>Lekcja</th>
                        <th>Uczeń</th>
                        <th>Lektor</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dzisiejsze as $d): ?>
                        <tr>
                            <td><?= e(substr($d['start_time'], 0, 5)) ?>-<?= e(substr($d['end_time'], 0, 5)) ?></td>
                            <td><?= e($d['lekcja']) ?></td>
                            <td><?= e($d['uczen']) ?></td>
                            <td><?= e($d['lektor']) ?></td>
                            <td><span class="status status-<?= e($d['status']) ?>"><?= e(nazwa_statusu($d['status'])) ?></span></td>
                            <td><a href="<?= BASE_URL ?>/administrator/rezerwacja_szczegoly.php?id=<?= (int) $d['id'] ?>">Szczegóły</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/admin_dol.php'; ?>
