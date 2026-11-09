<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

$pdo = getPDO();
$id  = (int) ($_GET['id'] ?? 0);

$lekcja = pobierz_lekcje($pdo, $id);

if (!$lekcja) {
    flash('error', 'Nie ma takiej lekcji w ofercie.');
    redirect('/publiczne/uslugi.php');
}

$lektorzy = lektorzy_lekcji($pdo, $id);

$stmt = $pdo->prepare(
    'SELECT day_of_week, start_time, end_time
     FROM employee_availability
     WHERE employee_id = :id
     ORDER BY day_of_week, start_time'
);
foreach ($lektorzy as $i => $lektor) {
    $stmt->execute(['id' => $lektor['id']]);
    $lektorzy[$i]['godziny'] = $stmt->fetchAll();
}

$moze_rezerwowac = !is_logged_in() || current_role() === 'client';

$tytul = $lekcja['name'];
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<p><a href="<?= BASE_URL ?>/publiczne/uslugi.php">&larr; Wróć do oferty</a></p>

<div class="karta">
    <p><span class="status"><?= e($lekcja['jezyk']) ?></span></p>

    <p><?= e((string) ($lekcja['description'] ?? 'Brak opisu.')) ?></p>

    <table>
        <tbody>
            <tr><th>Język</th><td><?= e($lekcja['jezyk']) ?></td></tr>
            <tr><th>Czas trwania</th><td><?= (int) $lekcja['duration_minutes'] ?> minut</td></tr>
            <tr><th>Cena</th><td><?= e(number_format((float) $lekcja['price'], 2, ',', ' ')) ?> zł</td></tr>
        </tbody>
    </table>

    <?php if ($moze_rezerwowac && !empty($lektorzy)): ?>
        <p style="margin-top:18px;">
            <a href="<?= BASE_URL ?>/klient/rezerwacja.php?usluga=<?= (int) $lekcja['id'] ?>" class="przycisk">Zarezerwuj tę lekcję</a>
        </p>
    <?php endif; ?>
</div>

<div class="karta">
    <h2>Dostępność lektorów</h2>

    <?php if (empty($lektorzy)): ?>
        <p>Obecnie żaden lektor nie ma ustawionych godzin pracy dla tej lekcji.</p>
    <?php else: ?>
        <?php foreach ($lektorzy as $lektor): ?>
            <p>
                <strong><?= e($lektor['first_name'] . ' ' . $lektor['last_name']) ?></strong><br>
                <?php foreach ($lektor['godziny'] as $g): ?>
                    <?= e(nazwa_dnia((int) $g['day_of_week'])) ?>:
                    <?= e(substr($g['start_time'], 0, 5)) ?>-<?= e(substr($g['end_time'], 0, 5)) ?><br>
                <?php endforeach; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
