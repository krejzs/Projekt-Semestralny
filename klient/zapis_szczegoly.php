<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['client']);

$pdo       = getPDO();
$id_ucznia = (int) current_user_id();
$id        = (int) ($_GET['id'] ?? 0);

// anulowanie z tej strony (te same zasady co na liscie)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['akcja'] ?? '') === 'anuluj') {
    if (anuluj_zapis_ucznia($pdo, $id, $id_ucznia)) {
        flash('success', 'Zapis został anulowany.');
    } else {
        flash('error', 'Nie można anulować tego zapisu.');
    }
    redirect('/klient/panel.php');
}

// KONTROLA WLASCICIELA: warunek r.client_id = :uczen sprawia, ze po zmianie id
// w adresie (np. ?id=153) uczen nie zobaczy cudzego zapisu.
$stmt = $pdo->prepare(
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.price_at_booking, r.created_at,
            s.name AS lekcja, s.duration_minutes, c.name AS jezyk,
            CONCAT(e.first_name, ' ', e.last_name) AS lektor,
            st.name AS status
     FROM reservations r
     JOIN services s ON s.id = r.service_id
     JOIN categories c ON c.id = s.category_id
     JOIN employees e ON e.id = r.employee_id
     JOIN reservation_statuses st ON st.id = r.status_id
     WHERE r.id = :id AND r.client_id = :uczen"
);
$stmt->execute(['id' => $id, 'uczen' => $id_ucznia]);
$zapis = $stmt->fetch();

if (!$zapis) {
    flash('error', 'Nie ma takiego zapisu.');
    redirect('/klient/panel.php');
}

$tytul = 'Szczegóły zapisu';
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<p><a href="<?= BASE_URL ?>/klient/panel.php">&larr; Wróć do moich zapisów</a></p>

<div class="karta">
    <table>
        <tbody>
            <tr><th>Lekcja</th><td><?= e($zapis['jezyk']) ?>: <?= e($zapis['lekcja']) ?> (<?= (int) $zapis['duration_minutes'] ?> min)</td></tr>
            <tr><th>Lektor</th><td><?= e($zapis['lektor']) ?></td></tr>
            <tr><th>Data</th><td><?= e($zapis['reservation_date']) ?></td></tr>
            <tr><th>Godzina</th><td><?= e(substr($zapis['start_time'], 0, 5)) ?>-<?= e(substr($zapis['end_time'], 0, 5)) ?></td></tr>
            <tr><th>Cena</th><td><?= e(number_format((float) $zapis['price_at_booking'], 2, ',', ' ')) ?> zł</td></tr>
            <tr><th>Zapis utworzony</th><td><?= e($zapis['created_at']) ?></td></tr>
            <tr>
                <th>Status</th>
                <td><span class="status status-<?= e($zapis['status']) ?>"><?= e(nazwa_statusu($zapis['status'])) ?></span></td>
            </tr>
        </tbody>
    </table>

    <?php if (mozna_anulowac($zapis)): ?>
        <p style="margin-top:16px;">
            <?= przycisk_post('anuluj', (int) $zapis['id'], 'Anuluj ten zapis', 'przycisk-czerwony', 'Czy na pewno anulować ten zapis?') ?>
        </p>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
