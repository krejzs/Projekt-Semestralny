<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['employee']);

$pdo   = getPDO();
$bledy = [];

$stmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = :konto');
$stmt->execute(['konto' => current_user_id()]);
$lektor = $stmt->fetch();

if (!$lektor) {
    flash('error', 'Twoje konto nie ma profilu lektora.');
    redirect('/pracownik/panel.php');
}

$id_lektora = (int) $lektor['id'];
$id         = (int) ($_GET['id'] ?? 0);

// --- zmiana statusu (przyciski: potwierdz / zrealizuj / anuluj) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        // zmien_status_wizyty sprawdza, czy wizyta nalezy do tego lektora
        $blad = zmien_status_wizyty($pdo, $id, $id_lektora, (string) ($_POST['akcja'] ?? ''));

        if ($blad === null) {
            flash('success', 'Zmieniono status wizyty.');
        } else {
            flash('error', $blad);
        }
        redirect('/pracownik/wizyta.php?id=' . $id);
    }
}

// KONTROLA WLASCICIELA: r.employee_id = :lektor - lektor nie zobaczy wizyty innego lektora
$stmt = $pdo->prepare(
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.price_at_booking,
            s.name AS lekcja, s.duration_minutes, c.name AS jezyk,
            CONCAT(u.first_name, ' ', u.last_name) AS uczen, u.email AS uczen_email, u.phone AS uczen_telefon,
            st.name AS status
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN categories c ON c.id = s.category_id
     JOIN users u ON u.id = r.client_id
     JOIN reservation_statuses st ON st.id = r.status_id
     WHERE r.id = :id AND r.employee_id = :lektor"
);
$stmt->execute(['id' => $id, 'lektor' => $id_lektora]);
$wizyta = $stmt->fetch();

if (!$wizyta) {
    flash('error', 'Nie ma takiej wizyty.');
    redirect('/pracownik/panel.php');
}

$otwarta     = $wizyta['status'] === 'pending' || $wizyta['status'] === 'confirmed';
$juz_sie_zaczela = !zapis_w_przyszlosci($wizyta['reservation_date'], $wizyta['start_time']);

$tytul = 'Szczegóły wizyty';
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<p><a href="<?= BASE_URL ?>/pracownik/panel.php">&larr; Wróć do moich wizyt</a></p>

<div class="karta">
    <table>
        <tbody>
            <tr><th>Lekcja</th><td><?= e($wizyta['jezyk']) ?>: <?= e($wizyta['lekcja']) ?> (<?= (int) $wizyta['duration_minutes'] ?> min)</td></tr>
            <tr><th>Data</th><td><?= e($wizyta['reservation_date']) ?></td></tr>
            <tr><th>Godzina</th><td><?= e(substr($wizyta['start_time'], 0, 5)) ?>-<?= e(substr($wizyta['end_time'], 0, 5)) ?></td></tr>
            <tr><th>Uczeń</th><td><?= e($wizyta['uczen']) ?></td></tr>
            <tr><th>E-mail ucznia</th><td><?= e($wizyta['uczen_email']) ?></td></tr>
            <tr><th>Telefon ucznia</th><td><?= e($wizyta['uczen_telefon'] ?? '-') ?></td></tr>
            <tr><th>Cena</th><td><?= e(number_format((float) $wizyta['price_at_booking'], 2, ',', ' ')) ?> zł</td></tr>
            <tr>
                <th>Status</th>
                <td><span class="status status-<?= e($wizyta['status']) ?>"><?= e(nazwa_statusu($wizyta['status'])) ?></span></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="karta">
    <h2>Zmiana statusu</h2>

    <?php if (!$otwarta): ?>
        <p>Status tej wizyty jest już zamknięty, nie można go zmienić.</p>
    <?php else: ?>
        <div class="akcje">
            <?php if ($wizyta['status'] === 'pending'): ?>
                <?= przycisk_post('potwierdz', (int) $wizyta['id'], 'Potwierdź wizytę', '') ?>
            <?php endif; ?>

            <?php if ($juz_sie_zaczela): ?>
                <?= przycisk_post('zrealizuj', (int) $wizyta['id'], 'Oznacz jako zrealizowaną', '', 'Oznaczyć lekcję jako zrealizowaną?') ?>
            <?php endif; ?>

            <?= przycisk_post('anuluj', (int) $wizyta['id'], 'Anuluj wizytę', 'przycisk-czerwony', 'Czy na pewno anulować tę wizytę?') ?>
        </div>

        <?php if (!$juz_sie_zaczela): ?>
            <p><small>Przycisk "zrealizowana" pojawi się, gdy lekcja się rozpocznie.</small></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
