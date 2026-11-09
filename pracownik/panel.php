<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['employee']);

$pdo   = getPDO();
$bledy = [];

uzupelnij_profile_lektorow($pdo);

$stmt = $pdo->prepare('SELECT id FROM employees WHERE user_id = :konto');
$stmt->execute(['konto' => current_user_id()]);
$lektor = $stmt->fetch();

$jezyki_lektora = $lektor ? jezyki_lektora($pdo, (int) $lektor['id']) : [];

$widok = (string) ($_GET['widok'] ?? 'dzis');
if (!in_array($widok, ['dzis', 'przyszle', 'historia'], true)) {
    $widok = 'dzis';
}

$wizyty    = [];
$razem     = 0;
$ile_stron = 1;
$strona    = 1;

if ($lektor) {
    if ($widok === 'dzis') {
        $warunek_daty = 'r.reservation_date = :dzis';
        $kolejnosc    = 'r.start_time ASC';
    } elseif ($widok === 'przyszle') {
        $warunek_daty = 'r.reservation_date > :dzis';
        $kolejnosc    = 'r.reservation_date ASC, r.start_time ASC';
    } else {
        $warunek_daty = 'r.reservation_date < :dzis';
        $kolejnosc    = 'r.reservation_date DESC, r.start_time DESC';
    }

    $z_tabel = "FROM reservations r
                JOIN services s ON s.id = r.service_id
                JOIN users u ON u.id = r.client_id
                JOIN reservation_statuses st ON st.id = r.status_id
                WHERE r.employee_id = :lektor AND " . $warunek_daty;

    $parametry = ['lektor' => $lektor['id'], 'dzis' => date('Y-m-d')];

    $na_stronie = 10;

    $stmt = $pdo->prepare('SELECT COUNT(*) ' . $z_tabel);
    $stmt->execute($parametry);
    $razem = (int) $stmt->fetchColumn();

    $ile_stron  = max(1, (int) ceil($razem / $na_stronie));
    $strona     = min(numer_strony(), $ile_stron);
    $od_wiersza = ($strona - 1) * $na_stronie;

    $stmt = $pdo->prepare(
        "SELECT r.id, r.reservation_date, r.start_time, r.end_time,
                s.name AS lekcja,
                CONCAT(u.first_name, ' ', u.last_name) AS uczen, u.phone AS telefon,
                st.name AS status "
        . $z_tabel .
        ' ORDER BY ' . $kolejnosc . ' LIMIT :limit OFFSET :offset'
    );
    foreach ($parametry as $nazwa => $wartosc) {
        $stmt->bindValue(':' . $nazwa, $wartosc);
    }
    $stmt->bindValue(':limit', $na_stronie, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $od_wiersza, PDO::PARAM_INT);
    $stmt->execute();
    $wizyty = $stmt->fetchAll();
}

$tytul = 'Moje wizyty';
require __DIR__ . '/../czesci_wspolne/strona_gora.php';
?>

<?php if (!$lektor): ?>
    <div class="alert alert-error">
        Twoje konto nie ma jeszcze profilu lektora. Skontaktuj się z administratorem.
    </div>
<?php else: ?>

    <div class="karta">
        <?php if (empty($jezyki_lektora)): ?>
            Nie masz jeszcze przypisanych lekcji, więc nie możesz być rezerwowany. Skontaktuj się z administratorem.
        <?php else: ?>
            Uczysz języków: <strong><?= e(implode(', ', $jezyki_lektora)) ?></strong>
        <?php endif; ?>
    </div>

    <p>
        <a href="?widok=dzis" class="przycisk <?= $widok === 'dzis' ? '' : 'przycisk-szary' ?>">Dzisiaj</a>
        <a href="?widok=przyszle" class="przycisk <?= $widok === 'przyszle' ? '' : 'przycisk-szary' ?>">Nadchodzące</a>
        <a href="?widok=historia" class="przycisk <?= $widok === 'historia' ? '' : 'przycisk-szary' ?>">Historia</a>
    </p>

    <div class="karta">
        <h2>
            <?php if ($widok === 'dzis'): ?>Wizyty na dziś
            <?php elseif ($widok === 'przyszle'): ?>Nadchodzące wizyty
            <?php else: ?>Historia wizyt<?php endif; ?>
            (<?= $razem ?>)
        </h2>

        <?php if (empty($wizyty)): ?>
            <p>Brak wizyt w tej zakładce.</p>
        <?php else: ?>
            <div class="tabela-przewijana">
                <table>
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Godzina</th>
                            <th>Uczeń</th>
                            <th>Telefon</th>
                            <th>Lekcja</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wizyty as $w): ?>
                            <tr>
                                <td><?= e($w['reservation_date']) ?></td>
                                <td><?= e(substr($w['start_time'], 0, 5)) ?>-<?= e(substr($w['end_time'], 0, 5)) ?></td>
                                <td><?= e($w['uczen']) ?></td>
                                <td><?= e($w['telefon'] ?? '-') ?></td>
                                <td><?= e($w['lekcja']) ?></td>
                                <td><span class="status status-<?= e($w['status']) ?>"><?= e(nazwa_statusu($w['status'])) ?></span></td>
                                <td><a href="<?= BASE_URL ?>/pracownik/wizyta.php?id=<?= (int) $w['id'] ?>" class="przycisk przycisk-maly przycisk-szary">Szczegóły</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php pokaz_strony($strona, $ile_stron); ?>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/../czesci_wspolne/strona_dol.php'; ?>
