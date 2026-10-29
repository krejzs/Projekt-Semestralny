<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

// --- szybkie anulowanie zapisu z listy ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } elseif (($_POST['akcja'] ?? '') === 'anuluj') {
        $stmt = $pdo->prepare('UPDATE reservations SET status_id = :status WHERE id = :id');
        $stmt->execute([
            'status' => id_statusu($pdo, 'cancelled'),
            'id'     => (int) ($_POST['id'] ?? 0),
        ]);
        flash('success', 'Zapis został anulowany.');
        redirect('/administrator/rezerwacje.php');
    }
}

// --- filtry z adresu (formularz wysyla je metoda GET) ---
$data_od = trim((string) ($_GET['od'] ?? ''));
$data_do = trim((string) ($_GET['do'] ?? ''));
$status  = (int) ($_GET['status'] ?? 0);
$lektor  = (int) ($_GET['lektor'] ?? 0);
$szukaj  = trim((string) ($_GET['szukaj'] ?? ''));

if ($data_od !== '' && !poprawna_data($data_od)) {
    $bledy[] = 'Data "od" ma nieprawidłowy format.';
    $data_od = '';
}
if ($data_do !== '' && !poprawna_data($data_do)) {
    $bledy[] = 'Data "do" ma nieprawidłowy format.';
    $data_do = '';
}

// skladamy warunki WHERE tylko z tych filtrow, ktore uzytkownik wypelnil
$warunki   = [];
$parametry = [];

if ($data_od !== '') {
    $warunki[]            = 'r.reservation_date >= :data_od';
    $parametry['data_od'] = $data_od;
}
if ($data_do !== '') {
    $warunki[]            = 'r.reservation_date <= :data_do';
    $parametry['data_do'] = $data_do;
}
if ($status > 0) {
    $warunki[]           = 'r.status_id = :status';
    $parametry['status'] = $status;
}
if ($lektor > 0) {
    $warunki[]           = 'r.employee_id = :lektor';
    $parametry['lektor'] = $lektor;
}
if ($szukaj !== '') {
    // ta sama fraza dwa razy, ale pod dwiema nazwami (PDO nie lubi powtorzen)
    $warunki[]            = "(CONCAT(u.first_name, ' ', u.last_name) LIKE :szukaj1 OR u.email LIKE :szukaj2)";
    $parametry['szukaj1'] = '%' . $szukaj . '%';
    $parametry['szukaj2'] = '%' . $szukaj . '%';
}

$gdzie = empty($warunki) ? '' : 'WHERE ' . implode(' AND ', $warunki);

// wspolna czesc zapytania (polaczenia tabel)
$z_tabel = 'FROM reservations r
            JOIN users u ON u.id = r.client_id
            JOIN services s ON s.id = r.service_id
            JOIN employees e ON e.id = r.employee_id
            JOIN reservation_statuses st ON st.id = r.status_id ';

// --- paginacja: 10 zapisow na strone ---
$na_stronie = 10;

$stmt = $pdo->prepare('SELECT COUNT(*) ' . $z_tabel . $gdzie);
$stmt->execute($parametry);
$razem = (int) $stmt->fetchColumn();

$ile_stron = max(1, (int) ceil($razem / $na_stronie));
$strona    = min(numer_strony(), $ile_stron);
$od_wiersza = ($strona - 1) * $na_stronie;

$stmt = $pdo->prepare(
    "SELECT r.id, r.reservation_date, r.start_time, r.end_time, r.price_at_booking,
            s.name AS lekcja,
            CONCAT(u.first_name, ' ', u.last_name) AS uczen,
            CONCAT(e.first_name, ' ', e.last_name) AS lektor,
            st.name AS status "
    . $z_tabel . $gdzie .
    ' ORDER BY r.reservation_date DESC, r.start_time DESC
      LIMIT :limit OFFSET :offset'
);
foreach ($parametry as $nazwa => $wartosc) {
    $stmt->bindValue(':' . $nazwa, $wartosc);
}
// LIMIT i OFFSET musza byc liczbami, dlatego PARAM_INT
$stmt->bindValue(':limit', $na_stronie, PDO::PARAM_INT);
$stmt->bindValue(':offset', $od_wiersza, PDO::PARAM_INT);
$stmt->execute();
$zapisy = $stmt->fetchAll();

// listy do filtrow
$statusy  = $pdo->query('SELECT id, name FROM reservation_statuses ORDER BY id')->fetchAll();
$lektorzy = $pdo->query('SELECT id, first_name, last_name FROM employees ORDER BY last_name, first_name')->fetchAll();

$tytul = 'Zapisy na lekcje';
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<div class="karta">
    <h2>Filtry</h2>

    <form method="get" action="<?= BASE_URL ?>/administrator/rezerwacje.php" class="filtry">
        <label>Data od
            <input type="date" name="od" value="<?= e($data_od) ?>">
        </label>
        <label>Data do
            <input type="date" name="do" value="<?= e($data_do) ?>">
        </label>
        <label>Status
            <select name="status">
                <option value="0">Wszystkie</option>
                <?php foreach ($statusy as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $status ? 'selected' : '' ?>>
                        <?= e(nazwa_statusu($s['name'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Lektor
            <select name="lektor">
                <option value="0">Wszyscy</option>
                <?php foreach ($lektorzy as $l): ?>
                    <option value="<?= (int) $l['id'] ?>" <?= (int) $l['id'] === $lektor ? 'selected' : '' ?>>
                        <?= e($l['first_name'] . ' ' . $l['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Uczeń (imię, nazwisko, e-mail)
            <input type="text" name="szukaj" value="<?= e($szukaj) ?>">
        </label>
        <div>
            <button type="submit" class="przycisk">Szukaj</button>
            <a href="<?= BASE_URL ?>/administrator/rezerwacje.php" class="przycisk przycisk-szary">Wyczyść</a>
        </div>
    </form>
</div>

<div class="karta">
    <h2>Znalezione zapisy: <?= $razem ?></h2>

    <?php if (empty($zapisy)): ?>
        <p>Brak zapisów spełniających podane warunki.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Godzina</th>
                        <th>Uczeń</th>
                        <th>Lekcja</th>
                        <th>Lektor</th>
                        <th>Cena</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($zapisy as $z): ?>
                        <tr>
                            <td><?= e($z['reservation_date']) ?></td>
                            <td><?= e(substr($z['start_time'], 0, 5)) ?>-<?= e(substr($z['end_time'], 0, 5)) ?></td>
                            <td><?= e($z['uczen']) ?></td>
                            <td><?= e($z['lekcja']) ?></td>
                            <td><?= e($z['lektor']) ?></td>
                            <td><?= e(number_format((float) $z['price_at_booking'], 2, ',', ' ')) ?> zł</td>
                            <td><span class="status status-<?= e($z['status']) ?>"><?= e(nazwa_statusu($z['status'])) ?></span></td>
                            <td>
                                <div class="akcje">
                                    <a href="<?= BASE_URL ?>/administrator/rezerwacja_szczegoly.php?id=<?= (int) $z['id'] ?>" class="przycisk przycisk-maly przycisk-szary">Szczegóły</a>
                                    <?php if ($z['status'] === 'pending' || $z['status'] === 'confirmed'): ?>
                                        <?= przycisk_post('anuluj', (int) $z['id'], 'Anuluj', 'przycisk-czerwony', 'Czy na pewno anulować ten zapis?') ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php pokaz_strony($strona, $ile_stron); ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/admin_dol.php'; ?>
