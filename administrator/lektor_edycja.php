<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM employees WHERE id = :id');
$stmt->execute(['id' => $id]);
$lektor = $stmt->fetch();

if (!$lektor) {
    flash('error', 'Nie ma takiego lektora.');
    redirect('/administrator/lektorzy.php');
}

$id_konta = (int) ($lektor['user_id'] ?? 0);

$dane = [
    'imie'     => $lektor['first_name'],
    'nazwisko' => $lektor['last_name'],
    'email'    => $lektor['email'],
    'telefon'  => $lektor['phone'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $akcja = $_POST['akcja'] ?? '';

        if ($akcja === 'dane') {
            $dane['imie']     = trim((string) ($_POST['imie'] ?? ''));
            $dane['nazwisko'] = trim((string) ($_POST['nazwisko'] ?? ''));
            $dane['email']    = trim((string) ($_POST['email'] ?? ''));
            $dane['telefon']  = trim((string) ($_POST['telefon'] ?? ''));

            if ($dane['imie'] === '' || $dane['nazwisko'] === '' || $dane['email'] === '') {
                $bledy[] = 'Imię, nazwisko i e-mail są wymagane.';
            }
            if (mb_strlen($dane['imie']) > 50 || mb_strlen($dane['nazwisko']) > 50) {
                $bledy[] = 'Imię i nazwisko mogą mieć maksymalnie 50 znaków.';
            }
            if ($dane['email'] !== '' && !validate_email_format($dane['email'])) {
                $bledy[] = 'Podaj poprawny adres e-mail.';
            }
            if ($dane['telefon'] !== '' && !preg_match('/^[0-9+ ]{6,20}$/', $dane['telefon'])) {
                $bledy[] = 'Numer telefonu ma nieprawidłowy format.';
            }

            if (empty($bledy)) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :konto');
                $stmt->execute(['email' => $dane['email'], 'konto' => $id_konta]);
                if ($stmt->fetch()) {
                    $bledy[] = 'Ten adres e-mail jest już używany przez inne konto.';
                }

                $stmt = $pdo->prepare('SELECT id FROM employees WHERE email = :email AND id <> :id');
                $stmt->execute(['email' => $dane['email'], 'id' => $id]);
                if ($stmt->fetch()) {
                    $bledy[] = 'Ten adres e-mail ma już inny lektor.';
                }
            }

            if (empty($bledy)) {
                $udalo_sie = false;
                $telefon   = $dane['telefon'] !== '' ? $dane['telefon'] : null;

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'UPDATE employees SET first_name = :imie, last_name = :nazwisko, email = :email, phone = :telefon
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'imie' => $dane['imie'], 'nazwisko' => $dane['nazwisko'],
                        'email' => $dane['email'], 'telefon' => $telefon, 'id' => $id,
                    ]);

                    if ($id_konta > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE users SET first_name = :imie, last_name = :nazwisko, email = :email, phone = :telefon
                             WHERE id = :konto'
                        );
                        $stmt->execute([
                            'imie' => $dane['imie'], 'nazwisko' => $dane['nazwisko'],
                            'email' => $dane['email'], 'telefon' => $telefon, 'konto' => $id_konta,
                        ]);
                    }

                    $pdo->commit();
                    $udalo_sie = true;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($e->getMessage());
                    $bledy[] = 'Nie udało się zapisać danych lektora.';
                }

                if ($udalo_sie) {
                    flash('success', 'Zapisano dane lektora.');
                    redirect('/administrator/lektor_edycja.php?id=' . $id);
                }
            }

        } elseif ($akcja === 'lekcje') {
            $wybrane = $_POST['lekcje'] ?? [];
            if (!is_array($wybrane)) {
                $wybrane = [];
            }
            $wybrane = array_unique(array_map('intval', $wybrane));

            $poprawne = array_map('intval', $pdo->query('SELECT id FROM services')->fetchAll(PDO::FETCH_COLUMN));
            foreach ($wybrane as $id_lekcji) {
                if (!in_array($id_lekcji, $poprawne, true)) {
                    $bledy[] = 'Wybrano nieistniejącą lekcję.';
                    break;
                }
            }

            if (empty($bledy)) {
                $udalo_sie = false;

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('DELETE FROM employee_services WHERE employee_id = :id');
                    $stmt->execute(['id' => $id]);

                    $stmt = $pdo->prepare('INSERT INTO employee_services (employee_id, service_id) VALUES (:lektor, :lekcja)');
                    foreach ($wybrane as $id_lekcji) {
                        $stmt->execute(['lektor' => $id, 'lekcja' => $id_lekcji]);
                    }

                    $pdo->commit();
                    $udalo_sie = true;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($e->getMessage());
                    $bledy[] = 'Nie udało się zapisać lekcji lektora.';
                }

                if ($udalo_sie) {
                    flash('success', 'Zapisano lekcje prowadzone przez lektora.');
                    redirect('/administrator/lektor_edycja.php?id=' . $id);
                }
            }

        } elseif ($akcja === 'dodaj_dostepnosc') {
            $dzien = (int) ($_POST['dzien'] ?? 0);
            $od    = trim((string) ($_POST['od'] ?? ''));
            $do    = trim((string) ($_POST['do'] ?? ''));

            if ($dzien < 1 || $dzien > 7) {
                $bledy[] = 'Wybierz dzień tygodnia.';
            }
            if (!poprawna_godzina($od) || !poprawna_godzina($do)) {
                $bledy[] = 'Podaj godziny w formacie GG:MM.';
            } elseif ($do <= $od) {
                $bledy[] = 'Godzina zakończenia musi być późniejsza niż rozpoczęcia.';
            }

            if (empty($bledy)) {
                $stmt = $pdo->prepare(
                    'SELECT id FROM employee_availability
                     WHERE employee_id = :lektor AND day_of_week = :dzien
                       AND start_time < :koniec AND end_time > :poczatek'
                );
                $stmt->execute(['lektor' => $id, 'dzien' => $dzien, 'koniec' => $do, 'poczatek' => $od]);
                if ($stmt->fetch()) {
                    $bledy[] = 'Te godziny nachodzą na już dodane godziny pracy w tym dniu.';
                }
            }

            if (empty($bledy)) {
                $stmt = $pdo->prepare(
                    'INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
                     VALUES (:lektor, :dzien, :poczatek, :koniec)'
                );
                $stmt->execute(['lektor' => $id, 'dzien' => $dzien, 'poczatek' => $od, 'koniec' => $do]);
                flash('success', 'Dodano godziny pracy.');
                redirect('/administrator/lektor_edycja.php?id=' . $id);
            }

        } elseif ($akcja === 'usun_dostepnosc') {
            $stmt = $pdo->prepare('DELETE FROM employee_availability WHERE id = :id AND employee_id = :lektor');
            $stmt->execute(['id' => (int) ($_POST['id'] ?? 0), 'lektor' => $id]);
            flash('success', 'Usunięto godziny pracy.');
            redirect('/administrator/lektor_edycja.php?id=' . $id);
        }
    }
}

$wszystkie_lekcje = $pdo->query(
    'SELECT s.id, s.name, s.duration_minutes, s.active, c.name AS jezyk
     FROM services s
     JOIN categories c ON c.id = s.category_id
     ORDER BY c.name, s.name'
)->fetchAll();

$stmt = $pdo->prepare('SELECT service_id FROM employee_services WHERE employee_id = :id');
$stmt->execute(['id' => $id]);
$przypisane = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

$stmt = $pdo->prepare(
    'SELECT id, day_of_week, start_time, end_time
     FROM employee_availability
     WHERE employee_id = :id
     ORDER BY day_of_week, start_time'
);
$stmt->execute(['id' => $id]);
$godziny = $stmt->fetchAll();

$aktywne_menu = 'lektorzy.php';
$tytul        = 'Lektor: ' . $lektor['first_name'] . ' ' . $lektor['last_name'];
require __DIR__ . '/../czesci_wspolne/admin_gora.php';

$adres_formularza = BASE_URL . '/administrator/lektor_edycja.php?id=' . $id;
?>

<p><a href="<?= BASE_URL ?>/administrator/lektorzy.php">&larr; Wróć do listy lektorów</a></p>

<div class="karta">
    <h2>Dane lektora</h2>

    <form method="post" action="<?= e($adres_formularza) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="dane">

        <div class="siatka-formularza">
            <label>Imię
                <input type="text" name="imie" value="<?= e($dane['imie']) ?>" maxlength="50" required>
            </label>
            <label>Nazwisko
                <input type="text" name="nazwisko" value="<?= e($dane['nazwisko']) ?>" maxlength="50" required>
            </label>
            <label>E-mail
                <input type="email" name="email" value="<?= e($dane['email']) ?>" maxlength="100" required>
            </label>
            <label>Telefon
                <input type="tel" name="telefon" value="<?= e($dane['telefon']) ?>" maxlength="20">
            </label>
        </div>

        <button type="submit" class="przycisk">Zapisz dane</button>
    </form>
</div>

<div class="karta">
    <h2>Lekcje, które prowadzi</h2>

    <?php if (empty($wszystkie_lekcje)): ?>
        <p>Nie ma jeszcze żadnych lekcji w ofercie. Dodaj je w zakładce <a href="<?= BASE_URL ?>/administrator/lekcje.php">Lekcje</a>.</p>
    <?php else: ?>
        <form method="post" action="<?= e($adres_formularza) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="lekcje">

            <div class="lista-checkbox">
                <?php foreach ($wszystkie_lekcje as $lekcja): ?>
                    <label>
                        <input type="checkbox" name="lekcje[]" value="<?= (int) $lekcja['id'] ?>"
                               <?= in_array((int) $lekcja['id'], $przypisane, true) ? 'checked' : '' ?>>
                        <?= e($lekcja['jezyk']) ?>: <?= e($lekcja['name']) ?> (<?= (int) $lekcja['duration_minutes'] ?> min)
                        <?= $lekcja['active'] ? '' : ' - ukryta' ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="przycisk">Zapisz lekcje</button>
        </form>
    <?php endif; ?>
</div>

<div class="karta">
    <h2>Godziny pracy (dostępność)</h2>

    <?php if (empty($godziny)): ?>
        <p>Lektor nie ma jeszcze ustawionych godzin pracy.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>Dzień</th>
                        <th>Od</th>
                        <th>Do</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($godziny as $g): ?>
                        <tr>
                            <td><?= e(nazwa_dnia((int) $g['day_of_week'])) ?></td>
                            <td><?= e(substr($g['start_time'], 0, 5)) ?></td>
                            <td><?= e(substr($g['end_time'], 0, 5)) ?></td>
                            <td><?= przycisk_post('usun_dostepnosc', (int) $g['id'], 'Usuń', 'przycisk-czerwony', 'Usunąć te godziny pracy?') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 style="margin-top:22px;">Dodaj godziny pracy</h2>

    <form method="post" action="<?= e($adres_formularza) ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="dodaj_dostepnosc">

        <div class="siatka-formularza">
            <label>Dzień tygodnia
                <select name="dzien" required>
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <option value="<?= $d ?>"><?= e(nazwa_dnia($d)) ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>Od godziny
                <input type="time" name="od" required>
            </label>
            <label>Do godziny
                <input type="time" name="do" required>
            </label>
        </div>

        <button type="submit" class="przycisk">Dodaj</button>
    </form>
</div>

<?php require __DIR__ . '/../czesci_wspolne/admin_dol.php'; ?>
