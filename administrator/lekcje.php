<?php

declare(strict_types=1);
require_once __DIR__ . '/../konfiguracja/ustawienia.php';

require_role(['admin']);

$pdo   = getPDO();
$bledy = [];

$jezyki     = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$id_jezykow = array_map('intval', array_column($jezyki, 'id'));

$f = [
    'id'      => 0,
    'nazwa'   => '',
    'jezyk'   => 0,
    'opis'    => '',
    'cena'    => '',
    'czas'    => '60',
    'aktywna' => 1,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $bledy[] = 'Sesja formularza wygasła. Odśwież stronę i spróbuj ponownie.';
    } else {
        $akcja = $_POST['akcja'] ?? '';
        $id    = (int) ($_POST['id'] ?? 0);

        if ($akcja === 'zapisz') {
            $f['id']      = $id;
            $f['nazwa']   = trim((string) ($_POST['nazwa'] ?? ''));
            $f['jezyk']   = (int) ($_POST['jezyk'] ?? 0);
            $f['opis']    = trim((string) ($_POST['opis'] ?? ''));
            $f['cena']    = trim((string) ($_POST['cena'] ?? ''));
            $f['czas']    = trim((string) ($_POST['czas'] ?? ''));
            $f['aktywna'] = isset($_POST['aktywna']) ? 1 : 0;

            
            if ($f['nazwa'] === '') {
                $bledy[] = 'Podaj nazwę lekcji.';
            } elseif (mb_strlen($f['nazwa']) > 100) {
                $bledy[] = 'Nazwa lekcji może mieć maksymalnie 100 znaków.';
            }

            if (!in_array($f['jezyk'], $id_jezykow, true)) {
                $bledy[] = 'Wybierz język z listy.';
            }

            if (mb_strlen($f['opis']) > 1000) {
                $bledy[] = 'Opis może mieć maksymalnie 1000 znaków.';
            }

            
            $cena = str_replace(',', '.', $f['cena']);
            if (!is_numeric($cena) || (float) $cena < 0 || (float) $cena > 9999.99) {
                $bledy[] = 'Cena musi być liczbą od 0 do 9999,99.';
            }

            $czas = filter_var($f['czas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 480]]);
            if ($czas === false) {
                $bledy[] = 'Czas trwania musi być liczbą minut od 5 do 480.';
            }

            if (empty($bledy)) {
                $parametry = [
                    'jezyk'   => $f['jezyk'],
                    'nazwa'   => $f['nazwa'],
                    'opis'    => $f['opis'] !== '' ? $f['opis'] : null,
                    'cena'    => round((float) $cena, 2),
                    'czas'    => $czas,
                    'aktywna' => $f['aktywna'],
                ];

                if ($id > 0) {
                    $parametry['id'] = $id;
                    $stmt = $pdo->prepare(
                        'UPDATE services
                         SET category_id = :jezyk, name = :nazwa, description = :opis,
                             price = :cena, duration_minutes = :czas, active = :aktywna
                         WHERE id = :id'
                    );
                    $stmt->execute($parametry);
                    flash('success', 'Zapisano zmiany w lekcji.');
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO services (category_id, name, description, price, duration_minutes, active)
                         VALUES (:jezyk, :nazwa, :opis, :cena, :czas, :aktywna)'
                    );
                    $stmt->execute($parametry);
                    flash('success', 'Dodano lekcję.');
                }
                redirect('/administrator/lekcje.php');
            }
        } elseif ($akcja === 'przelacz') {
            
            $stmt = $pdo->prepare('UPDATE services SET active = 1 - active WHERE id = :id');
            $stmt->execute(['id' => $id]);
            flash('success', 'Zmieniono widoczność lekcji.');
            redirect('/administrator/lekcje.php');
        } elseif ($akcja === 'usun') {
            
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE service_id = :id');
            $stmt->execute(['id' => $id]);

            if ((int) $stmt->fetchColumn() > 0) {
                flash('error', 'Ta lekcja ma zapisy uczniów, więc nie można jej usunąć. Możesz ją zdezaktywować.');
            } else {
                $stmt = $pdo->prepare('DELETE FROM services WHERE id = :id');
                $stmt->execute(['id' => $id]);
                flash('success', 'Lekcja została usunięta.');
            }
            redirect('/administrator/lekcje.php');
        }
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edytuj'])) {
    $stmt = $pdo->prepare('SELECT * FROM services WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edytuj']]);
    $lekcja = $stmt->fetch();

    if ($lekcja) {
        $f['id']      = (int) $lekcja['id'];
        $f['nazwa']   = $lekcja['name'];
        $f['jezyk']   = (int) $lekcja['category_id'];
        $f['opis']    = $lekcja['description'] ?? '';
        $f['cena']    = (string) $lekcja['price'];
        $f['czas']    = (string) $lekcja['duration_minutes'];
        $f['aktywna'] = (int) $lekcja['active'];
    }
}


$filtr_jezyk = (int) ($_GET['jezyk'] ?? 0);

$sql = 'SELECT s.id, s.name, s.price, s.duration_minutes, s.active, c.name AS jezyk
        FROM services s
        JOIN categories c ON c.id = s.category_id';
$parametry_listy = [];

if ($filtr_jezyk > 0) {
    $sql .= ' WHERE s.category_id = :jezyk';
    $parametry_listy['jezyk'] = $filtr_jezyk;
}
$sql .= ' ORDER BY c.name, s.name';

$stmt = $pdo->prepare($sql);
$stmt->execute($parametry_listy);
$lekcje = $stmt->fetchAll();

$tytul = 'Lekcje';
require __DIR__ . '/../czesci_wspolne/admin_gora.php';
?>

<div class="karta">
    <h2><?= $f['id'] > 0 ? 'Edytuj lekcję' : 'Dodaj lekcję' ?></h2>

    <?php if (empty($jezyki)): ?>
        <p>Żeby dodać lekcję, najpierw dodaj przynajmniej jeden <a href="<?= BASE_URL ?>/administrator/jezyki.php">język</a>.</p>
    <?php else: ?>
        <form method="post" action="<?= BASE_URL ?>/administrator/lekcje.php" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="zapisz">
            <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">

            <div class="siatka-formularza">
                <label>Nazwa lekcji
                    <input type="text" name="nazwa" value="<?= e($f['nazwa']) ?>" maxlength="100" required>
                </label>

                <label>Język
                    <select name="jezyk" required>
                        <option value="">-- wybierz --</option>
                        <?php foreach ($jezyki as $j): ?>
                            <option value="<?= (int) $j['id'] ?>" <?= (int) $j['id'] === (int) $f['jezyk'] ? 'selected' : '' ?>>
                                <?= e($j['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Cena (zł)
                    <input type="text" name="cena" value="<?= e($f['cena']) ?>" required>
                </label>

                <label>Czas trwania (minuty)
                    <input type="number" name="czas" value="<?= e($f['czas']) ?>" min="5" max="480" required>
                </label>
            </div>

            <label>Opis
                <textarea name="opis" rows="3" maxlength="1000"><?= e($f['opis']) ?></textarea>
            </label>

            <div class="lista-checkbox">
                <label>
                    <input type="checkbox" name="aktywna" value="1" <?= $f['aktywna'] ? 'checked' : '' ?>>
                    Lekcja aktywna (widoczna dla uczniów)
                </label>
            </div>

            <button type="submit" class="przycisk">Zapisz</button>
            <?php if ($f['id'] > 0): ?>
                <a href="<?= BASE_URL ?>/administrator/lekcje.php" class="przycisk przycisk-szary">Anuluj</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<div class="karta">
    <h2>Oferta lekcji</h2>

    <form method="get" action="<?= BASE_URL ?>/administrator/lekcje.php" class="filtry">
        <label>Pokaż tylko język
            <select name="jezyk">
                <option value="0">Wszystkie</option>
                <?php foreach ($jezyki as $j): ?>
                    <option value="<?= (int) $j['id'] ?>" <?= (int) $j['id'] === $filtr_jezyk ? 'selected' : '' ?>>
                        <?= e($j['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div>
            <button type="submit" class="przycisk przycisk-szary">Filtruj</button>
        </div>
    </form>

    <?php if (empty($lekcje)): ?>
        <p>Brak lekcji do wyświetlenia.</p>
    <?php else: ?>
        <div class="tabela-przewijana">
            <table>
                <thead>
                    <tr>
                        <th>Lekcja</th>
                        <th>Język</th>
                        <th>Czas</th>
                        <th>Cena</th>
                        <th>Status</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lekcje as $l): ?>
                        <tr>
                            <td><?= e($l['name']) ?></td>
                            <td><?= e($l['jezyk']) ?></td>
                            <td><?= (int) $l['duration_minutes'] ?> min</td>
                            <td><?= e(number_format((float) $l['price'], 2, ',', ' ')) ?> zł</td>
                            <td>
                                <?php if ($l['active']): ?>
                                    <span class="status status-completed">aktywna</span>
                                <?php else: ?>
                                    <span class="status status-nieaktywna">ukryta</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="akcje">
                                    <a href="?edytuj=<?= (int) $l['id'] ?>" class="przycisk przycisk-maly przycisk-szary">Edytuj</a>
                                    <?= przycisk_post('przelacz', (int) $l['id'], $l['active'] ? 'Dezaktywuj' : 'Aktywuj') ?>
                                    <?= przycisk_post('usun', (int) $l['id'], 'Usuń', 'przycisk-czerwony', 'Czy na pewno usunąć lekcję "' . $l['name'] . '"?') ?>
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
