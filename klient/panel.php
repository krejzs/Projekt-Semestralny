<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

// tylko uczen (klient)
require_role(['client']);

$pdo       = getPDO();
$id_ucznia = (int) current_user_id();
$bledy     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (($_POST['akcja'] ?? '') === 'anuluj') {
        // id ucznia bierzemy z sesji, a nie z formularza - nie da sie anulowac cudzego zapisu
        if (anuluj_zapis_ucznia($pdo, (int) ($_POST['id'] ?? 0), $id_ucznia)) {
            flash('success', 'Zapis został anulowany.');
        } else {
            flash('error', 'Nie można anulować tego zapisu (nie jest Twój, już się odbył albo jest zamknięty).');
        }
        redirect('/klient/panel.php');
    }
}

$uczen = find_user_by_id($pdo, $id_ucznia);

$zapytanie = "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.price_at_booking,
                     s.name AS lekcja,
                     CONCAT(e.first_name, ' ', e.last_name) AS lektor,
                     st.name AS status
              FROM reservations r
              JOIN services s ON s.id = r.service_id
              JOIN employees e ON e.id = r.employee_id
              JOIN reservation_statuses st ON st.id = r.status_id
              WHERE r.client_id = :uczen AND ";

$nadchodzace_warunek = "st.name IN ('pending', 'confirmed')
                        AND (r.reservation_date > :dzis1 OR (r.reservation_date = :dzis2 AND r.start_time > :teraz))";

$parametry = [
    'uczen' => $id_ucznia,
    'dzis1' => date('Y-m-d'),
    'dzis2' => date('Y-m-d'),
    'teraz' => date('H:i:s'),
];

$stmt = $pdo->prepare($zapytanie . $nadchodzace_warunek . ' ORDER BY r.reservation_date ASC, r.start_time ASC');
$stmt->execute($parametry);
$nadchodzace = $stmt->fetchAll();

$stmt = $pdo->prepare($zapytanie . 'NOT (' . $nadchodzace_warunek . ') ORDER BY r.reservation_date DESC, r.start_time DESC LIMIT 50');
$stmt->execute($parametry);
$historia = $stmt->fetchAll();

function tabela_zapisow(array $zapisy, bool $z_anulowaniem): void
{
    ?>
    <div class="tabela-przewijana">
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Godzina</th>
                    <th>Lekcja</th>
                    <th>Lektor</th>
                    <th>Cena</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($zapisy as $z): ?>
                    <tr>
                        <td><?= e($z['reservation_date']) ?></td>
                        <td><?= e(substr($z['start_time'], 0, 5)) ?>-<?= e(substr($z['end_time'], 0, 5)) ?></td>
                        <td><?= e($z['lekcja']) ?></td>
                        <td><?= e($z['lektor']) ?></td>
                        <td><?= e(number_format((float) $z['price_at_booking'], 2, ',', ' ')) ?> zł</td>
                        <td><span class="status status-<?= e($z['status']) ?>"><?= e(nazwa_statusu($z['status'])) ?></span></td>
                        <td>
                            <div class="akcje">
                                <a href="<?= BASE_URL ?>/klient/zapis_szczegoly.php?id=<?= (int) $z['id'] ?>" class="przycisk przycisk-maly przycisk-szary">Szczegóły</a>
                                <?php if ($z_anulowaniem): ?>
                                    <?= przycisk_post('anuluj', (int) $z['id'], 'Anuluj', 'przycisk-czerwony', 'Czy na pewno anulować ten zapis?') ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

$tytul = 'Moje konto';
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<div class="karta">
    <h2>Moje dane</h2>
    <table>
        <tbody>
            <tr><th>Imię i nazwisko</th><td><?= e($uczen['first_name'] . ' ' . $uczen['last_name']) ?></td></tr>
            <tr><th>E-mail</th><td><?= e($uczen['email']) ?></td></tr>
            <tr><th>Telefon</th><td><?= e($uczen['phone'] ?? '-') ?></td></tr>
        </tbody>
    </table>
    <p style="margin-top:14px;">
        <a href="<?= BASE_URL ?>/publiczne/profil.php" class="przycisk przycisk-szary">Edytuj dane i zmień hasło</a>
        <a href="<?= BASE_URL ?>/publiczne/uslugi.php" class="przycisk">Zarezerwuj lekcję</a>
    </p>
</div>

<div class="karta">
    <h2>Nadchodzące lekcje</h2>

    <?php if (empty($nadchodzace)): ?>
        <p>Nie masz żadnych zaplanowanych lekcji.</p>
    <?php else: ?>
        <?php tabela_zapisow($nadchodzace, true); ?>
    <?php endif; ?>
</div>

<div class="karta">
    <h2>Zakończone i anulowane</h2>

    <?php if (empty($historia)): ?>
        <p>Brak zapisów w historii.</p>
    <?php else: ?>
        <?php tabela_zapisow($historia, false); ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
