<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (($_POST['akcja'] ?? '') === 'zmien_status') {
        $nowy_status = (int) ($_POST['status'] ?? 0);

        $stmt = $pdo->prepare('SELECT id FROM reservation_statuses WHERE id = :id');
        $stmt->execute(['id' => $nowy_status]);

        if (!$stmt->fetch()) {
            $bledy[] = 'Wybierz status z listy.';
        } else {
            $stmt = $pdo->prepare('UPDATE reservations SET status_id = :status WHERE id = :id');
            $stmt->execute(['status' => $nowy_status, 'id' => $id]);
            flash('success', 'Zmieniono status zapisu.');
            redirect('/administrator/rezerwacja_szczegoly.php?id=' . $id);
        }
    }
}

$stmt = $pdo->prepare(
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.price_at_booking, r.created_at, r.status_id,
            s.name AS lekcja, s.duration_minutes, c.name AS jezyk,
            CONCAT(u.first_name, ' ', u.last_name) AS uczen, u.email AS uczen_email, u.phone AS uczen_telefon,
            CONCAT(e.first_name, ' ', e.last_name) AS lektor,
            st.name AS status
     FROM reservations r
     JOIN users u ON u.id = r.client_id
     JOIN services s ON s.id = r.service_id
     JOIN categories c ON c.id = s.category_id
     JOIN employees e ON e.id = r.employee_id
     JOIN reservation_statuses st ON st.id = r.status_id
     WHERE r.id = :id"
);
$stmt->execute(['id' => $id]);
$zapis = $stmt->fetch();

if (!$zapis) {
    flash('error', 'Nie ma takiego zapisu.');
    redirect('/administrator/rezerwacje.php');
}

$statusy = $pdo->query('SELECT id, name FROM reservation_statuses ORDER BY id')->fetchAll();

$aktywne_menu = 'rezerwacje.php';
$tytul        = 'Zapis na lekcję nr ' . $zapis['id'];
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<p><a href="<?= BASE_URL ?>/administrator/rezerwacje.php">&larr; Wróć do listy zapisów</a></p>

<div class="karta">
    <h2>Szczegóły</h2>

    <table>
        <tbody>
            <tr><th>Lekcja</th><td><?= e($zapis['jezyk']) ?>: <?= e($zapis['lekcja']) ?> (<?= (int) $zapis['duration_minutes'] ?> min)</td></tr>
            <tr><th>Data</th><td><?= e($zapis['reservation_date']) ?></td></tr>
            <tr><th>Godzina</th><td><?= e(substr($zapis['start_time'], 0, 5)) ?>-<?= e(substr($zapis['end_time'], 0, 5)) ?></td></tr>
            <tr><th>Lektor</th><td><?= e($zapis['lektor']) ?></td></tr>
            <tr><th>Uczeń</th><td><?= e($zapis['uczen']) ?></td></tr>
            <tr><th>E-mail ucznia</th><td><?= e($zapis['uczen_email']) ?></td></tr>
            <tr><th>Telefon ucznia</th><td><?= e($zapis['uczen_telefon'] ?? '-') ?></td></tr>
            <tr><th>Cena</th><td><?= e(number_format((float) $zapis['price_at_booking'], 2, ',', ' ')) ?> zł</td></tr>
            <tr><th>Zapis utworzony</th><td><?= e($zapis['created_at']) ?></td></tr>
            <tr>
                <th>Status</th>
                <td><span class="status status-<?= e($zapis['status']) ?>"><?= e(nazwa_statusu($zapis['status'])) ?></span></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="karta">
    <h2>Zmień status</h2>

    <form method="post" action="<?= BASE_URL ?>/administrator/rezerwacja_szczegoly.php?id=<?= (int) $zapis['id'] ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="zmien_status">

        <label>Nowy status
            <select name="status">
                <?php foreach ($statusy as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === (int) $zapis['status_id'] ? 'selected' : '' ?>>
                        <?= e(nazwa_statusu($s['name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="przycisk">Zapisz status</button>
    </form>
</div>

<?php require __DIR__ . '/../czesci_wspolne/admin_dol.php'; ?>
