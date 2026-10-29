<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

// dane formularza dodawania lektora
$f = ['imie' => '', 'nazwisko' => '', 'email' => '', 'telefon' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $akcja = $_POST['akcja'] ?? '';
        $id    = (int) ($_POST['id'] ?? 0);

        if ($akcja === 'dodaj') {
            $f['imie']     = trim((string) ($_POST['imie'] ?? ''));
            $f['nazwisko'] = trim((string) ($_POST['nazwisko'] ?? ''));
            $f['email']    = trim((string) ($_POST['email'] ?? ''));
            $f['telefon']  = trim((string) ($_POST['telefon'] ?? ''));
            $haslo         = (string) ($_POST['haslo'] ?? '');

            // --- walidacja ---
            if ($f['imie'] === '' || $f['nazwisko'] === '' || $f['email'] === '' || $haslo === '') {
                $bledy[] = 'Imię, nazwisko, e-mail i hasło są wymagane.';
            }
            if (mb_strlen($f['imie']) > 50 || mb_strlen($f['nazwisko']) > 50) {
                $bledy[] = 'Imię i nazwisko mogą mieć maksymalnie 50 znaków.';
            }
            if ($f['email'] !== '' && !validate_email_format($f['email'])) {
                $bledy[] = 'Podaj poprawny adres e-mail.';
            }
            if ($f['telefon'] !== '' && !preg_match('/^[0-9+ ]{6,20}$/', $f['telefon'])) {
                $bledy[] = 'Numer telefonu ma nieprawidłowy format.';
            }
            if ($haslo !== '' && strlen($haslo) < 8) {
                $bledy[] = 'Hasło musi mieć co najmniej 8 znaków.';
            }

            // e-mail nie moze juz istniec ani wsrod kont, ani wsrod lektorow
            if (empty($bledy)) {
                if (email_exists($pdo, $f['email'])) {
                    $bledy[] = 'Konto z takim adresem e-mail już istnieje.';
                } else {
                    $stmt = $pdo->prepare('SELECT id FROM employees WHERE email = :email');
                    $stmt->execute(['email' => $f['email']]);
                    if ($stmt->fetch()) {
                        $bledy[] = 'Lektor z takim adresem e-mail już istnieje.';
                    }
                }
            }

            if (empty($bledy)) {
                $udalo_sie = false;

                // dodajemy dwa rekordy naraz (konto + lektor), wiec transakcja:
                // albo wejda oba, albo zaden
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare(
                        'INSERT INTO users (first_name, last_name, email, password_hash, phone, role_id, is_email_verified)
                         VALUES (:imie, :nazwisko, :email, :haslo, :telefon, :rola, 1)'
                    );
                    $stmt->execute([
                        'imie'     => $f['imie'],
                        'nazwisko' => $f['nazwisko'],
                        'email'    => $f['email'],
                        'haslo'    => password_hash($haslo, PASSWORD_DEFAULT),
                        'telefon'  => $f['telefon'] !== '' ? $f['telefon'] : null,
                        'rola'     => get_role_id($pdo, 'employee'),
                    ]);
                    $id_konta = (int) $pdo->lastInsertId();

                    $stmt = $pdo->prepare(
                        'INSERT INTO employees (user_id, first_name, last_name, email, phone)
                         VALUES (:konto, :imie, :nazwisko, :email, :telefon)'
                    );
                    $stmt->execute([
                        'konto'    => $id_konta,
                        'imie'     => $f['imie'],
                        'nazwisko' => $f['nazwisko'],
                        'email'    => $f['email'],
                        'telefon'  => $f['telefon'] !== '' ? $f['telefon'] : null,
                    ]);

                    $pdo->commit();
                    $udalo_sie = true;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($e->getMessage());
                    $bledy[] = 'Nie udało się dodać lektora. Spróbuj ponownie.';
                }

                if ($udalo_sie) {
                    flash('success', 'Dodano lektora. Przekaż mu hasło, które ustawiłeś - może je zmienić w swoim profilu.');
                    redirect('/administrator/lektorzy.php');
                }
            }
        } elseif ($akcja === 'przelacz') {
            $stmt = $pdo->prepare('UPDATE employees SET active = 1 - active WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash('success', 'Zmieniono status lektora.');
            redirect('/administrator/lektorzy.php');
        } elseif ($akcja === 'usun') {
            // lektora z zapisami nie usuwamy - mozna go tylko dezaktywowac
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE employee_id = :id');
            $stmt->execute(['id' => $id]);

            if ((int) $stmt->fetchColumn() > 0) {
                flash('error', 'Ten lektor ma zapisy na lekcje, więc nie można go usunąć. Możesz go zdezaktywować.');
            } else {
                $stmt = $pdo->prepare('SELECT user_id FROM employees WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $lektor = $stmt->fetch();

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare('DELETE FROM employees WHERE id = :id');
                    $stmt->execute(['id' => $id]);

                    // razem z lektorem usuwamy jego konto logowania
                    if ($lektor && $lektor['user_id'] !== null) {
                        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                        $stmt->execute(['id' => $lektor['user_id']]);
                    }

                    $pdo->commit();
                    flash('success', 'Lektor został usunięty.');
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($e->getMessage());
                    flash('error', 'Nie udało się usunąć lektora.');
                }
            }
            redirect('/administrator/lektorzy.php');
        }
    }
}

// lista lektorow z liczba prowadzonych lekcji
$lektorzy = $pdo->query(
    'SELECT e.id, e.first_name, e.last_name, e.email, e.phone, e.active,
            (SELECT COUNT(*) FROM employee_services es WHERE es.employee_id = e.id) AS ile_lekcji
     FROM employees e
     ORDER BY e.last_name, e.first_name'
)->fetchAll();

$tytul = 'Lektorzy';
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<div class="karta">
    <h2>Dodaj lektora</h2>
    <p>Dodając lektora tworzysz też jego konto logowania (rola: lektor).</p>

    <form method="post" action="<?= BASE_URL ?>/administrator/lektorzy.php" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="dodaj">

        <div class="siatka-formularza">
            <label>Imię
                <input type="text" name="imie" value="<?= e($f['imie']) ?>" maxlength="50" required>
            </label>
            <label>Nazwisko
                <input type="text" name="nazwisko" value="<?= e($f['nazwisko']) ?>" maxlength="50" required>
            </label>
            <label>E-mail (służy do logowania)
                <input type="email" name="email" value="<?= e($f['email']) ?>" maxlength="100" required>
            </label>
            <label>Telefon
                <input type="tel" name="telefon" value="<?= e($f['telefon']) ?>" maxlength="20">
            </label>
            <label>Hasło na start (min. 8 znaków)
                <input type="password" name="haslo" required>
            </label>
        </div>

        <button type="submit" class="przycisk">Dodaj lektora</button>
    </form>
</div>

<div class="karta">
    <h2>Wszyscy lektorzy</h2>

    <?php if (empty($lektorzy)): ?>
        <p>Nie ma jeszcze żadnych lektorów.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>Lektor</th>
                        <th>E-mail</th>
                        <th>Telefon</th>
                        <th>Lekcje</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lektorzy as $l): ?>
                        <tr>
                            <td><?= e($l['first_name'] . ' ' . $l['last_name']) ?></td>
                            <td><?= e($l['email']) ?></td>
                            <td><?= e($l['phone'] ?? '') ?></td>
                            <td><?= (int) $l['ile_lekcji'] ?></td>
                            <td>
                                <?php if ($l['active']): ?>
                                    <span class="status status-completed">aktywny</span>
                                <?php else: ?>
                                    <span class="status status-nieaktywna">nieaktywny</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="akcje">
                                    <a href="<?= BASE_URL ?>/administrator/lektor_edycja.php?id=<?= (int) $l['id'] ?>" class="przycisk przycisk-maly przycisk-szary">Edytuj / lekcje / godziny</a>
                                    <?= przycisk_post('przelacz', (int) $l['id'], $l['active'] ? 'Dezaktywuj' : 'Aktywuj') ?>
                                    <?= przycisk_post('usun', (int) $l['id'], 'Usuń', 'przycisk-czerwony', 'Czy na pewno usunąć lektora ' . $l['first_name'] . ' ' . $l['last_name'] . '? Zostanie usunięte też jego konto.') ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../czesci_wspolne/admin_dol.php'; ?>
